<?php
namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatbotController extends Controller
{
    /** Messages of context sent to the model. Fewer tokens = faster replies. */
    private const HISTORY_LIMIT = 8;

    public function hub(Request $request)
    {
        abort_unless($request->user()->isJobseeker() || $request->user()->isMentor(), 403);
        $user = $request->user();
        $conversations = $user->isJobseeker()
            ? $user->chatConversations()->orderByDesc('last_message_at')->orderByDesc('id')->take(50)->get(['id', 'title', 'last_message_at', 'created_at'])
            : collect();
        $active = $conversations->firstWhere('id', (int) $request->query('chat')) ?? $conversations->first();

        return view('career-hub', [
            // FR-18 ratings are surfaced on the mentor cards so feedback informs booking.
            'mentors' => User::where('role', 'mentor')->where('mentor_available', true)
                ->withAvg('mentorSessions as rating_avg', 'rating')
                ->withCount(['mentorSessions as rating_count' => fn ($query) => $query->whereNotNull('rating')])
                ->orderBy('name')->get(),
            'conversations' => $conversations,
            'activeConversation' => $active,
            'history' => $active ? $this->messagesFor($active) : [],
            'sessions' => ($user->isMentor() ? $user->mentorSessions()->with('mentee') : $user->menteeSessions()->with('mentor'))->orderByDesc('scheduled_at')->paginate(\App\Support\PerPage::resolve(10))->withQueryString(),
        ]);
    }

    public function mentorContext(Request $request)
    {
        abort_unless($request->user()->isJobseeker() || $request->user()->isMentor(), 403);
        return response()->json(['mentors' => User::where('role', 'mentor')->where('mentor_available', true)->get(['id', 'name', 'mentor_expertise', 'mentor_industry', 'mentor_bio'])]);
    }

    // ---- Conversations ("New chat" and per-chat history) ----

    public function createConversation(Request $request): JsonResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $conversation = $request->user()->chatConversations()->create(['title' => 'New chat', 'last_message_at' => now()]);

        return response()->json(['conversation' => $this->conversationSummary($conversation)], 201);
    }

    public function showConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return response()->json(['conversation' => $this->conversationSummary($conversation), 'messages' => $this->messagesFor($conversation)]);
    }

    public function destroyConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Loads the local model into memory before the first question so the
     * first reply is not delayed by a cold start. Fire-and-forget.
     */
    public function warmup(Request $request): JsonResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        if (config('services.career_ai.provider', 'ollama') === 'ollama') {
            try {
                Http::connectTimeout(1)->timeout(1)->post(rtrim(config('services.career_ai.url'), '/').'/api/generate', ['model' => config('services.career_ai.model'), 'keep_alive' => '30m']);
            } catch (\Throwable) {
                // The model keeps loading after the short timeout; nothing to report.
            }
        }

        return response()->json(['ok' => true]);
    }

    // ---- Messages ----

    /**
     * Non-streaming endpoint (also used by the /api/chat route and tests).
     */
    public function handleMessage(Request $request)
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:4000'], 'conversation_id' => ['nullable', 'integer']]);
        $user = $request->user();

        // REQ-7: an empty message gets guidance, not an error, and is not stored.
        if (trim((string) ($data['message'] ?? '')) === '') {
            return response()->json([
                'reply' => $this->emptyMessageGuidance(),
                'intent' => 'general_enquiry', 'escalate' => false, 'fallback' => false, 'created_at' => now()->toIso8601String(),
            ]);
        }

        $conversation = $this->resolveConversation($request, $data['conversation_id'] ?? null, $data['message']);
        $intent = $this->classify($conversation, $data['message']);
        $fallback = false;
        if ($intent === 'mentor_escalation') {
            $reply = $this->mentorReply();
        } else {
            [$reply, $fallback] = $this->generateReply($user, $conversation, $intent, $data['message']);
        }
        $saved = $this->store($conversation, $data['message'], $reply, $intent);

        return response()->json(['reply' => $reply, 'intent' => $intent, 'escalate' => $intent === 'mentor_escalation', 'fallback' => $fallback, 'created_at' => $saved->created_at->toIso8601String(), 'conversation' => $this->conversationSummary($conversation->fresh())]);
    }

    /**
     * Streaming endpoint: sends newline-delimited JSON events so the reply
     * appears as it is written (meta, status, token..., done).
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:4000'], 'conversation_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return $this->handleMessage($request);
        }

        $conversation = $this->resolveConversation($request, $data['conversation_id'] ?? null, $message);
        $intent = $this->classify($conversation, $message);
        // Store the question first so it is never lost if the model fails mid-reply.
        $user->chatbotMessages()->create(['chat_conversation_id' => $conversation->id, 'sender' => 'user', 'message' => $message, 'intent' => $intent]);

        return response()->stream(function () use ($user, $conversation, $intent, $message) {
            // A long answer from a local model can outlast PHP's default 30 second limit.
            @set_time_limit(120);
            // Push each event to the browser immediately (Apache/XAMPP buffer output by default).
            // Tests capture output in a buffer, so it is left alone there.
            $live = ! app()->runningUnitTests();
            while ($live && ob_get_level() > 0) {
                ob_end_flush();
            }
            $emit = function (array $event) use ($live) {
                echo json_encode($event, JSON_UNESCAPED_UNICODE)."\n";
                if ($live) {
                    flush();
                }
            };
            $emit(['type' => 'meta', 'intent' => $intent, 'conversation' => $this->conversationSummary($conversation)]);

            $fallback = false;
            if ($intent === 'mentor_escalation') {
                $emit(['type' => 'status', 'text' => 'Checking which mentors are available']);
                $reply = $this->mentorReply();
                $emit(['type' => 'token', 'text' => $reply]);
            } else {
                $emit(['type' => 'status', 'text' => match ($intent) {
                    'interview_practice' => 'Reviewing interview practice guidance',
                    'cv_assistance' => 'Reading your profile and CV guidance',
                    default => 'Looking into your question',
                }]);
                [$reply, $fallback] = $this->streamReply($user, $conversation, $intent, $message, fn (string $token) => $emit(['type' => 'token', 'text' => $token]));
            }

            $saved = $user->chatbotMessages()->create(['chat_conversation_id' => $conversation->id, 'sender' => 'bot', 'message' => $reply, 'intent' => $intent]);
            $conversation->update(['last_message_at' => now()]);
            $emit(['type' => 'done', 'reply' => $reply, 'intent' => $intent, 'escalate' => $intent === 'mentor_escalation', 'fallback' => $fallback, 'created_at' => $saved->created_at->toIso8601String(), 'conversation' => $this->conversationSummary($conversation->fresh())]);
        }, 200, ['Content-Type' => 'application/x-ndjson', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }

    /**
     * REQ-5: structured CV from saved profile skills plus optional text.
     * Shown inside the chat (and saved to it) instead of a separate panel.
     */
    public function buildCv(Request $request)
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:1000'], 'experience' => ['nullable', 'string', 'max:4000'], 'conversation_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $cv = [
            'name' => $user->name,
            'contact' => array_values(array_filter([$user->email, $user->phone, $user->region])),
            'summary' => ($data['summary'] ?? '') !== '' ? $data['summary'] : (string) $user->about_me,
            'skills' => $user->skillsList(),
            'experience' => $data['experience'] ?? '',
            'education' => $user->qualification_level ?: 'Add your qualification in your profile.',
        ];

        $payload = ['cv' => $cv];
        if ($request->has('conversation_id')) {
            $conversation = $this->resolveConversation($request, $data['conversation_id'], 'CV from my profile');
            $markdown = $this->cvMarkdown($cv, $user);
            $saved = $this->store($conversation, 'Build my CV from my profile'.(($data['experience'] ?? '') !== '' ? ' using: '.$data['experience'] : ''), $markdown, 'cv_assistance');
            $payload += ['reply' => $markdown, 'created_at' => $saved->created_at->toIso8601String(), 'conversation' => $this->conversationSummary($conversation->fresh())];
        }

        return response()->json($payload);
    }

    // ---- Helpers ----

    private function resolveConversation(Request $request, ?int $id, string $firstMessage): ChatConversation
    {
        $user = $request->user();
        $conversation = $id ? $user->chatConversations()->find($id) : null;
        // No conversation_id at all (older API clients): continue the latest chat.
        // An explicit null/0 from the page means "New chat".
        if (! $conversation && ! $request->has('conversation_id')) {
            $conversation = $user->chatConversations()->latest('last_message_at')->latest('id')->first();
        }
        if (! $conversation) {
            $conversation = $user->chatConversations()->create(['title' => 'New chat', 'last_message_at' => now()]);
        }
        if ($conversation->title === 'New chat') {
            $conversation->update(['title' => Str::limit(preg_replace('/\s+/', ' ', $firstMessage), 48, '…')]);
        }

        return $conversation;
    }

    private function classify(ChatConversation $conversation, string $message): string
    {
        $intent = $this->detectIntent($message);
        if ($intent === 'general_enquiry') {
            $previous = $conversation->messages()->where('sender', 'bot')->reorder()->latest('id')->first();
            if ($previous && $previous->intent === 'interview_practice') {
                $intent = 'interview_practice';
            }
        }

        return $intent;
    }

    private function store(ChatConversation $conversation, string $message, string $reply, string $intent)
    {
        return DB::transaction(function () use ($conversation, $message, $reply, $intent) {
            $conversation->user->chatbotMessages()->create(['chat_conversation_id' => $conversation->id, 'sender' => 'user', 'message' => $message, 'intent' => $intent]);
            $saved = $conversation->user->chatbotMessages()->create(['chat_conversation_id' => $conversation->id, 'sender' => 'bot', 'message' => $reply, 'intent' => $intent]);
            $conversation->update(['last_message_at' => now()]);

            return $saved;
        });
    }

    private function messagesFor(ChatConversation $conversation): array
    {
        return $conversation->messages()->get()->map(fn ($m) => [
            'sender' => $m->sender, 'message' => $m->message, 'intent' => $m->intent, 'created_at' => $m->created_at->toIso8601String(),
        ])->all();
    }

    private function conversationSummary(ChatConversation $conversation): array
    {
        return ['id' => $conversation->id, 'title' => $conversation->title, 'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String()];
    }

    /**
     * REQ-2: classify into the four intents named in the SRS/SAD data dictionary.
     */
    private function detectIntent(string $message): string
    {
        $lower = mb_strtolower($message);
        $wantsPerson = str_contains($lower, 'mentor')
            || preg_match('/\b(talk|speak|chat) (to|with) (a |an )?(real )?(person|human|someone)\b/u', $lower)
            || (str_contains($lower, 'human') && ! str_contains($lower, 'human resource'));

        return match (true) {
            (bool) $wantsPerson => 'mentor_escalation',
            str_contains($lower, 'interview') => 'interview_practice',
            (bool) preg_match('/\b(cv|cvs|resume|résumé|curriculum vitae)\b/u', $lower) => 'cv_assistance',
            default => 'general_enquiry',
        };
    }

    private function emptyMessageGuidance(): string
    {
        return "Bula! Type a question to get started. I can:\n\n- give you a **practice interview question** and feedback\n- help you **improve your CV** (or build one from your profile)\n- show which **mentors** are available";
    }

    private function mentorReply(): string
    {
        $mentors = User::where('role', 'mentor')->where('mentor_available', true)->orderBy('name')->get();
        if ($mentors->isEmpty()) {
            return 'No mentors are accepting sessions right now. Please check back later; I can keep helping you here in the meantime.';
        }
        $list = $mentors->map(fn ($mentor) => '- **'.$mentor->name.'**: '.collect([$mentor->mentor_expertise, $mentor->mentor_industry])->filter()->implode(', '))->implode("\n");

        return "A human mentor can help with this. These registered mentors are taking requests:\n\n".$list."\n\nUse **Book a mentor** below to request a one-hour session.";
    }

    /**
     * @param bool $questionStored stream() saves the question before calling the
     *                             model, so the newest row is skipped here and sent once below.
     */
    private function modelMessages(User $user, ChatConversation $conversation, string $intent, string $message, bool $questionStored = false): array
    {
        $history = $conversation->messages()->reorder()->orderByDesc('id')
            ->skip($questionStored ? 1 : 0)->take(self::HISTORY_LIMIT)->get()->reverse()
            ->map(fn ($entry) => ['role' => $entry->sender === 'user' ? 'user' : 'assistant', 'content' => $entry->message])->values()->all();

        $system = 'You are BulaCareer, a friendly, practical career assistant for jobseekers in Fiji. Detected intent: '.$intent.'. '
            .'Answer in clear Markdown: short paragraphs, **bold** key points, and bullet or numbered lists where they help. Keep answers under 200 words unless asked for more. '
            .'For interview practice ask one question at a time and give constructive feedback on the answer. Help with honest CV wording. '
            .'Never invent qualifications or claim to perform actions on the platform. '
            .'Profile: qualification '.($user->qualification_level ?: 'not provided').'; experience '.($user->years_experience ?? 'unknown').' years; region '.($user->region ?: 'not provided').'; skills: '.(implode(', ', $user->skillsList()) ?: 'none listed').'.';

        return array_merge([['role' => 'system', 'content' => $system]], $history, [['role' => 'user', 'content' => $message]]);
    }

    private function ollamaOptions(): array
    {
        return ['keep_alive' => '30m', 'options' => ['num_predict' => 400, 'num_ctx' => 2048, 'temperature' => 0.6]];
    }

    private function generateReply(User $user, ChatConversation $conversation, string $intent, string $message, bool $questionStored = false): array
    {
        $messages = $this->modelMessages($user, $conversation, $intent, $message, $questionStored);
        try {
            $provider = config('services.career_ai.provider', 'ollama');
            if ($provider === 'openai' && config('services.openai.key')) {
                $response = Http::withToken(config('services.openai.key'))->connectTimeout(3)->timeout(30)->post('https://api.openai.com/v1/chat/completions', ['model' => config('services.openai.model'), 'messages' => $messages, 'max_tokens' => 500]);
                $reply = $response->json('choices.0.message.content');
            } else {
                $response = Http::connectTimeout(3)->timeout(45)->post(rtrim(config('services.career_ai.url'), '/').'/api/chat', ['model' => config('services.career_ai.model'), 'messages' => $messages, 'stream' => false] + $this->ollamaOptions());
                $reply = $response->json('message.content');
            }
            if ($response->successful() && is_string($reply) && trim($reply) !== '') { return [trim($reply), false]; }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return [$this->fallbackReply($intent), true];
    }

    /**
     * Streams tokens from Ollama as they are generated. Other providers (or
     * any failure before the first token) fall back to a complete reply.
     */
    private function streamReply(User $user, ChatConversation $conversation, string $intent, string $message, callable $onToken): array
    {
        if (config('services.career_ai.provider', 'ollama') !== 'ollama') {
            [$reply, $fallback] = $this->generateReply($user, $conversation, $intent, $message, questionStored: true);
            $onToken($reply);

            return [$reply, $fallback];
        }

        $reply = '';
        try {
            $response = Http::connectTimeout(3)->timeout(90)->withOptions(['stream' => true])
                ->post(rtrim(config('services.career_ai.url'), '/').'/api/chat', ['model' => config('services.career_ai.model'), 'messages' => $this->modelMessages($user, $conversation, $intent, $message, questionStored: true), 'stream' => true] + $this->ollamaOptions());
            if ($response->successful()) {
                $body = $response->toPsrResponse()->getBody();
                $buffer = '';
                while (! $body->eof()) {
                    $buffer .= $body->read(1024);
                    while (($newline = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $newline));
                        $buffer = substr($buffer, $newline + 1);
                        if ($line === '') { continue; }
                        $chunk = json_decode($line, true);
                        $token = $chunk['message']['content'] ?? '';
                        if ($token !== '') {
                            $reply .= $token;
                            $onToken($token);
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        if (trim($reply) !== '') {
            return [trim($reply), false];
        }
        $fallback = $this->fallbackReply($intent);
        $onToken($fallback);

        return [$fallback, true];
    }

    private function fallbackReply(string $intent): string
    {
        $help = match ($intent) {
            'interview_practice' => "**Practice question:** Describe a challenge you resolved at work or during study.\n\nStructure your answer with **STAR**: the situation, your task, the action you took and the result.",
            'cv_assistance' => "Use **Build my CV** to create a CV from your saved profile. Add a clear summary, real experience and specific achievements, and never list qualifications you do not hold.",
            default => 'I can help you prepare a CV, practise interviews or find a registered mentor. CV building and mentor booking still work while the AI is offline.',
        };

        return "_The AI service is temporarily unavailable, so here is some general guidance._\n\n".$help;
    }

    private function cvMarkdown(array $cv, User $user): string
    {
        $lines = ['<!--cv-->', '# '.$cv['name'], implode(' | ', $cv['contact']), '', '## Professional summary', $cv['summary'] ?: '_Add a summary in your profile (About me) or type it before choosing Build my CV._', '', '## Skills'];
        $lines[] = $cv['skills'] ? '- '.implode("\n- ", $cv['skills']) : '_Add skills in your profile._';
        $lines = array_merge($lines, ['', '## Experience', $cv['experience'] ?: ($user->years_experience ? $user->years_experience.' years of work experience. _Type your roles and achievements in the message box, then choose Build my CV again to include them._' : '_Type your roles and achievements in the message box, then choose Build my CV again._'), '', '## Education', $cv['education']]);

        return implode("\n", $lines);
    }
}
