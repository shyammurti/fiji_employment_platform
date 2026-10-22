<x-app-layout title="Career Hub">
<x-slot name="header">
    <h1 class="page-title">Career Hub</h1>
    <p class="page-intro">Practise interviews, build your CV and get career guidance. When you need a person, book an industry mentor.</p>
</x-slot>

@if (auth()->user()->isJobseeker())
<div x-data="careerHub({
        conversations: {{ Illuminate\Support\Js::from($conversations->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'last_message_at' => ($c->last_message_at ?? $c->created_at)?->toIso8601String()])) }},
        activeId: {{ Illuminate\Support\Js::from($activeConversation?->id) }},
        messages: {{ Illuminate\Support\Js::from($history) }},
     })">
<div class="grid items-start gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">

    {{-- Chat sessions --}}
    <aside class="card !p-3 lg:sticky lg:top-24" aria-label="Your chats">
        <button type="button" class="btn-primary w-full" @click="newChat()" :disabled="busy">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z"/></svg>
            New chat
        </button>
        <button type="button" class="mt-3 flex w-full items-center justify-between rounded-xl px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 lg:pointer-events-none" @click="showList = !showList" :aria-expanded="showList">
            Recent chats <span class="lg:hidden" x-text="showList ? 'Hide' : 'Show'"></span>
        </button>
        <ul class="mt-1 max-h-[55vh] space-y-0.5 overflow-y-auto overscroll-contain" :class="showList ? '' : 'hidden lg:block'">
            <template x-for="chat in conversations" :key="chat.id">
                <li class="group flex items-center gap-1 rounded-xl transition-colors" :class="chat.id === activeId ? 'bg-ocean-50' : 'hover:bg-slate-50'">
                    <button type="button" class="min-w-0 flex-1 px-3 py-2 text-left" @click="openChat(chat.id)" :aria-current="chat.id === activeId ? 'true' : null">
                        <span class="block truncate text-sm font-medium" :class="chat.id === activeId ? 'text-ocean-800' : 'text-slate-700'" x-text="chat.title"></span>
                        <span class="block text-xs text-slate-500" x-text="relative(chat.last_message_at)"></span>
                    </button>
                    <button type="button" class="mr-1 rounded-lg p-1.5 text-slate-500 opacity-100 transition hover:bg-slate-100 hover:text-red-700 lg:opacity-0 lg:group-hover:opacity-100 lg:focus:opacity-100" @click="deleteChat(chat)" :aria-label="'Delete chat: ' + chat.title">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 2a1 1 0 0 0-.9.6L6.4 4H4a1 1 0 0 0 0 2h.1l.8 10.1A2 2 0 0 0 6.9 18h6.2a2 2 0 0 0 2-1.9L15.9 6h.1a1 1 0 1 0 0-2h-2.4l-.7-1.4A1 1 0 0 0 12 2H8Zm0 6a1 1 0 0 1 2 0v6a1 1 0 1 1-2 0V8Zm4-1a1 1 0 0 0-1 1v6a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1Z" clip-rule="evenodd"/></svg>
                    </button>
                </li>
            </template>
            <li x-show="!conversations.length" class="px-3 py-2 text-sm text-slate-600">No chats yet. Ask your first question.</li>
        </ul>
    </aside>

    {{-- Conversation --}}
    <section class="card flex min-h-[70vh] flex-col !p-0" aria-labelledby="chat-title">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <span class="brand-mark !h-10 !w-10" aria-hidden="true">B</span>
            <div class="min-w-0 flex-1">
                <h2 id="chat-title" class="truncate font-semibold text-fiji-900" x-text="activeTitle()">BulaCareer assistant</h2>
                <p class="text-xs text-slate-600">BulaCareer assistant: interviews, CVs and career guidance</p>
            </div>
            <button type="button" x-show="messages.length" x-cloak class="btn-secondary btn-sm shrink-0" @click="copyChat()" :disabled="busy" :aria-label="copied === 'all' ? 'Chat copied' : 'Copy the whole chat'">
                <svg x-show="copied !== 'all'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M13 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/></svg>
                <svg x-show="copied === 'all'" class="h-4 w-4 text-success-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>
                <span x-text="copied === 'all' ? 'Copied' : 'Copy chat'">Copy chat</span>
            </button>
        </div>
        <p class="sr-only" role="status" aria-live="polite" x-text="copied ? 'Copied to clipboard' : ''"></p>

        <div class="flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 py-6" style="max-height: 62vh" x-ref="log" role="log" aria-live="polite" aria-label="Messages">
            {{-- Empty state --}}
            <div x-show="!messages.length && !busy" class="mx-auto max-w-lg py-8 text-center">
                <p class="text-lg font-semibold text-fiji-900">Bula! How can I help today?</p>
                <p class="mt-2 text-sm text-slate-600">Your chats are saved, so you can come back to them later.</p>
                <div class="mt-6 grid gap-3 text-left sm:grid-cols-3">
                    <template x-for="starter in starters" :key="starter.title">
                        <button type="button" class="rounded-2xl border border-slate-200 p-4 text-sm transition-colors hover:border-ocean-500 hover:bg-ocean-50" @click="starter.action === 'cv' ? buildCv() : send(starter.prompt)">
                            <span class="block font-semibold text-fiji-900" x-text="starter.title"></span>
                            <span class="mt-1 block text-slate-600" x-text="starter.text"></span>
                        </button>
                    </template>
                </div>
            </div>

            <template x-for="(entry, index) in messages" :key="index">
                <div>
                    {{-- Your messages --}}
                    <div x-show="entry.sender === 'user'" class="ml-10 flex justify-end">
                        <div class="max-w-full whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-fiji-800 px-4 py-2.5 text-sm leading-6 text-white" x-text="entry.message"></div>
                    </div>
                    {{-- Assistant messages --}}
                    <div x-show="entry.sender !== 'user'" class="mr-6 flex gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ocean-100 text-xs font-bold text-ocean-800" aria-hidden="true">B</span>
                        <div class="min-w-0 flex-1">
                            {{-- Thinking stage: shown until the first words arrive --}}
                            <div x-show="entry.thinking" class="flex items-center gap-2 py-1 text-sm text-slate-600">
                                <span class="thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                <span x-text="entry.status"></span>
                            </div>
                            <div x-show="!entry.thinking" :class="isCv(entry) ? 'cv-sheet' : ''">
                                <div class="chat-prose" :class="entry.streaming ? 'is-streaming' : ''" x-html="render(entry.message)"></div>
                                <button type="button" x-show="isCv(entry) && !entry.streaming" class="btn-secondary btn-sm mt-4" @click="printCv($el.closest('.cv-sheet'))">Print or save as PDF</button>
                            </div>
                            <div class="mt-1 flex items-center gap-3 text-[11px] text-slate-500" x-show="!entry.thinking">
                                <span><span x-text="time(entry.created_at)"></span><span x-show="entry.took" x-text="' · replied in ' + entry.took"></span></span>
                                <button type="button" x-show="!entry.streaming" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-fiji-900" @click="copyMessage(entry, index)" :aria-label="copied === index ? 'Reply copied' : 'Copy this reply'">
                                    <svg x-show="copied !== index" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M13 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/></svg>
                                    <svg x-show="copied === index" class="h-3.5 w-3.5 text-success-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>
                                    <span x-text="copied === index ? 'Copied' : 'Copy'">Copy</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            <div x-show="escalated" x-cloak class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ocean-200 bg-ocean-50 p-3 text-sm text-ocean-800">
                <span>A human mentor can help with this. Choose one below and request a session.</span>
                <a href="#mentors" class="btn-primary btn-sm">Book a mentor</a>
            </div>
            <p x-show="error" x-text="error" x-cloak class="mb-3 rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert"></p>
            <form @submit.prevent="send()" class="flex items-end gap-2">
                <label class="sr-only" for="chat-message">Your message</label>
                <textarea id="chat-message" x-ref="input" x-model="input" rows="1" maxlength="4000" autocomplete="off"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }" @input="grow()"
                          class="input !mt-0 max-h-40 min-w-0 flex-1 resize-none" placeholder="Ask about interviews, your CV or your career…"></textarea>
                <button type="submit" class="btn-primary" :disabled="busy || !input.trim()" aria-label="Send message">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.1 2.3a.75.75 0 0 0-1 .9l1.9 6.05h6.25a.75.75 0 0 1 0 1.5H4l-1.9 6.05a.75.75 0 0 0 1 .9l15-7.25a.75.75 0 0 0 0-1.35l-15-7.25Z"/></svg>
                </button>
            </form>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" class="chip-action" :disabled="busy" @click="buildCv()" title="Builds a CV from your profile. Anything typed in the box is added as experience.">Build my CV</button>
                <button type="button" class="chip-action" :disabled="busy" @click="send('Give me an interview question for the kind of role I am looking for.')">Practise an interview</button>
                <button type="button" class="chip-action" :disabled="busy" @click="send('I would like to talk to a mentor.')">Talk to a mentor</button>
                <span class="ml-auto hidden text-xs text-slate-500 sm:inline">Enter to send, Shift+Enter for a new line</span>
            </div>
            <p class="hint mt-3">General guidance only. Check suggestions, keep CV claims accurate, and book a mentor for decisions that matter.</p>
        </div>
    </section>
</div>

<section id="mentors" class="mt-12" aria-labelledby="mentors-title">
    <h2 id="mentors-title" class="text-2xl font-semibold tracking-tight text-fiji-900">Find a mentor</h2>
    <p class="mt-2 text-sm text-slate-600">Request a one-hour conversation. Times use Fiji time and your mentor confirms the request.</p>
    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($mentors as $mentor)
            <article class="card flex flex-col" x-data="{ booking: {{ old('mentor_id') == $mentor->id ? 'true' : 'false' }} }" aria-labelledby="mentor-{{ $mentor->id }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-ocean-100 text-lg font-bold text-ocean-700" aria-hidden="true">{{ mb_substr($mentor->name, 0, 1) }}</span>
                    <div class="min-w-0">
                        <h3 id="mentor-{{ $mentor->id }}" class="font-semibold text-fiji-900">{{ $mentor->name }}</h3>
                        <p class="text-sm text-ocean-700">{{ $mentor->mentor_expertise }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @if ($mentor->mentor_industry)<span class="skill-chip">{{ $mentor->mentor_industry }}</span>@endif
                    @if ($mentor->rating_count)
                        <span class="status-pill bg-amber-50 text-amber-900"><span aria-hidden="true">★</span> <span class="tabular-nums">{{ number_format($mentor->rating_avg, 1) }}</span> from {{ $mentor->rating_count }} {{ Str::plural('review', $mentor->rating_count) }}</span>
                    @else
                        <span class="status-pill bg-slate-50 text-slate-600">No reviews yet</span>
                    @endif
                </div>
                <p class="mt-4 flex-1 text-sm leading-7 text-slate-600">{{ $mentor->mentor_bio }}</p>
                <button type="button" class="btn-secondary mt-5 w-full" @click="booking = !booking" :aria-expanded="booking" aria-controls="booking-{{ $mentor->id }}">Request a session</button>
                <form id="booking-{{ $mentor->id }}" x-show="booking" x-cloak method="POST" action="{{ route('mentorship.schedule') }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="mentor_id" value="{{ $mentor->id }}">
                    <input type="hidden" name="escalated_from_chatbot" :value="escalated ? 1 : 0">
                    <div>
                        <label for="topic-{{ $mentor->id }}" class="label">What would you like help with?</label>
                        <input id="topic-{{ $mentor->id }}" name="topic" required minlength="3" maxlength="255" value="{{ old('mentor_id') == $mentor->id ? old('topic') : '' }}" class="input" placeholder="e.g. Preparing for a hotel interview…">
                    </div>
                    <div>
                        <label for="time-{{ $mentor->id }}" class="label">Session time (Fiji time)</label>
                        <input id="time-{{ $mentor->id }}" type="datetime-local" name="scheduled_at" required data-future step="900" class="input" aria-describedby="time-hint-{{ $mentor->id }}">
                        <p id="time-hint-{{ $mentor->id }}" class="hint">At least one hour from now and within the next 90 days.</p>
                    </div>
                    <button type="submit" class="btn-primary w-full">Send request</button>
                </form>
            </article>
        @empty
            <div class="card md:col-span-2">
                <h3 class="font-semibold text-fiji-900">No mentors are accepting requests right now.</h3>
                <p class="mt-2 text-sm text-slate-600">You can keep using the assistant. Check back for new availability.</p>
            </div>
        @endforelse
    </div>
</section>
</div>{{-- /careerHub --}}
@endif

<section class="mt-12" aria-labelledby="sessions-title">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 id="sessions-title" class="text-2xl font-semibold tracking-tight text-fiji-900">Mentorship sessions</h2>
        @if (auth()->user()->isMentor())<a class="btn-secondary" href="{{ route('profile.edit') }}">Manage profile and availability</a>@endif
    </div>
    <div class="space-y-4">
        @forelse ($sessions as $session)
            <article class="card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-fiji-900">{{ $session->topic }}</h3>
                        <p class="mt-1 text-sm text-slate-600">With {{ (auth()->user()->isMentor() ? $session->mentee?->name : $session->mentor?->name) ?? 'a removed account' }}, {{ $session->scheduled_at->timezone('Pacific/Fiji')->format('d M Y, g:i A') }} Fiji time</p>
                        @if ($session->escalated_from_chatbot)<p class="mt-1 text-xs text-slate-600">Referred from the Career Hub assistant</p>@endif
                    </div>
                    <span class="status-pill {{ match ($session->status) { 'confirmed' => 'bg-ocean-50 text-ocean-700', 'completed' => 'bg-success-100 text-success-700', 'cancelled' => 'bg-slate-100 text-slate-700', default => 'bg-amber-100 text-amber-800' } }}">{{ ucfirst($session->status) }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    @if (auth()->user()->isMentor() && $session->status === 'pending' && $session->scheduled_at->isFuture())
                        <form method="POST" action="{{ route('mentorship.updateStatus', $session) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button type="submit" class="btn-primary">Confirm session</button></form>
                    @endif
                    @if (auth()->user()->isMentor() && $session->status === 'confirmed' && $session->scheduled_at->copy()->addHour()->isPast())
                        <form method="POST" action="{{ route('mentorship.updateStatus', $session) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button type="submit" class="btn-primary">Mark completed</button></form>
                    @endif
                    @if (in_array($session->status, ['pending', 'confirmed']) && $session->scheduled_at->isFuture())
                        <form method="POST" action="{{ route('mentorship.updateStatus', $session) }}" onsubmit="return confirm('Cancel this mentorship session?');">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button type="submit" class="btn-secondary">Cancel session</button></form>
                    @endif
                </div>
                @if ($session->rating)
                    <div class="mt-4 rounded-xl bg-ocean-50 p-4 text-sm">
                        <p class="font-semibold">Rating: <span class="tabular-nums">{{ $session->rating }}/5</span></p>
                        @if ($session->feedback)<p class="mt-1 text-slate-700">{{ $session->feedback }}</p>@endif
                    </div>
                @elseif (auth()->user()->isJobseeker() && in_array($session->status, ['confirmed', 'completed']) && $session->scheduled_at->copy()->addHour()->isPast())
                    <form method="POST" action="{{ route('mentorship.feedback') }}" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
                        @csrf
                        <input type="hidden" name="mentorship_session_id" value="{{ $session->id }}">
                        <div>
                            <label for="rating-{{ $session->id }}" class="label">Rating</label>
                            <select id="rating-{{ $session->id }}" name="rating" required class="input">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} / 5</option>@endfor</select>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="feedback-{{ $session->id }}" class="label">Your feedback</label>
                            <textarea id="feedback-{{ $session->id }}" name="feedback" maxlength="2000" rows="2" class="input"></textarea>
                        </div>
                        <button type="submit" class="btn-primary sm:col-span-4 sm:justify-self-start">Submit feedback</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="card py-10 text-center">
                <h3 class="text-lg font-semibold text-fiji-900">No sessions yet</h3>
                <p class="mt-2 text-sm text-slate-600">{{ auth()->user()->isMentor() ? 'Session requests from jobseekers will appear here.' : 'Choose an available mentor above to book your first session.' }}</p>
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $sessions->links() }}</div>
</section>

@if (auth()->user()->isJobseeker())
<script>
function careerHub(initial) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const headers = () => ({'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()});
    const routes = {
        stream: @js(route('chatbot.stream')),
        cv: @js(route('chatbot.cvBuilder')),
        warmup: @js(route('chatbot.warmup')),
        create: @js(route('chatbot.conversations.store')),
        show: @js(url('/chatbot/conversations')),
    };
    // Status lines shown while the assistant "thinks", before the first words arrive.
    const thinkingSteps = ['Reading your question', 'Checking your profile', 'Gathering guidance', 'Writing a reply'];

    return {
        conversations: initial.conversations, activeId: initial.activeId, messages: initial.messages,
        input: '', busy: false, error: '', escalated: false, showList: false, copied: null,
        starters: [
            {title: 'Practise an interview', text: 'One question at a time, with feedback.', prompt: 'Give me an interview question for the kind of role I am looking for.'},
            {title: 'Build my CV', text: 'A CV from your saved profile.', action: 'cv'},
            {title: 'Plan my job search', text: 'Next steps based on your skills.', prompt: 'Based on my skills, what jobs should I look for and how should I prepare?'},
        ],

        init() {
            fetch(routes.warmup, {method: 'POST', headers: headers()}).catch(() => {});
            this.$nextTick(() => this.scroll());
        },
        render(text) { return window.renderMarkdown(String(text || '').replace('<!--cv-->', '')); },
        isCv(entry) { return entry.sender !== 'user' && String(entry.message || '').startsWith('<!--cv-->'); },
        activeTitle() { return this.conversations.find(c => c.id === this.activeId)?.title || 'New chat'; },
        scroll() { const log = this.$refs.log; if (log) log.scrollTop = log.scrollHeight; },
        grow() { const el = this.$refs.input; el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 160) + 'px'; },
        time(value) { return value ? new Intl.DateTimeFormat('en-FJ', {timeZone: 'Pacific/Fiji', dateStyle: 'medium', timeStyle: 'short'}).format(new Date(value)) : ''; },
        relative(value) {
            if (!value) return '';
            const minutes = Math.round((Date.now() - new Date(value)) / 60000);
            const rtf = new Intl.RelativeTimeFormat('en', {numeric: 'auto'});
            if (minutes < 60) return rtf.format(-minutes, 'minute');
            if (minutes < 1440) return rtf.format(-Math.round(minutes / 60), 'hour');
            return rtf.format(-Math.round(minutes / 1440), 'day');
        },
        upsertConversation(summary) {
            if (!summary) return;
            this.activeId = summary.id;
            this.conversations = [summary, ...this.conversations.filter(c => c.id !== summary.id)];
            history.replaceState(null, '', '?chat=' + summary.id);
        },

        newChat() {
            this.activeId = null; this.messages = []; this.error = ''; this.escalated = false;
            history.replaceState(null, '', location.pathname);
            this.$refs.input.focus();
        },
        async openChat(id) {
            if (this.busy || id === this.activeId) return;
            this.error = '';
            try {
                const response = await fetch(routes.show + '/' + id, {headers: headers()});
                if (!response.ok) throw new Error(response.status === 404 ? 'This chat was removed.' : 'Could not open this chat.');
                const data = await response.json();
                this.activeId = id; this.messages = data.messages; this.escalated = false; this.showList = false;
                history.replaceState(null, '', '?chat=' + id);
                this.$nextTick(() => this.scroll());
            } catch (error) {
                this.error = error.message;
                if (error.message.includes('removed')) this.conversations = this.conversations.filter(c => c.id !== id);
            }
        },
        async deleteChat(chat) {
            if (!confirm('Delete "' + chat.title + '"? Its messages will be removed.')) return;
            const response = await fetch(routes.show + '/' + chat.id, {method: 'DELETE', headers: headers()});
            if (response.ok || response.status === 404) {
                this.conversations = this.conversations.filter(c => c.id !== chat.id);
                if (chat.id === this.activeId) this.newChat();
            }
        },

        // Adds a placeholder reply that shows a thinking stage, then types out the answer.
        startReply() {
            this.messages.push({sender: 'bot', message: '', thinking: true, streaming: true, status: thinkingSteps[0], started: performance.now()});
            const entry = this.messages[this.messages.length - 1];
            let step = 0;
            entry.timer = setInterval(() => { if (entry.thinking && step < thinkingSteps.length - 1) entry.status = thinkingSteps[++step]; }, 1400);
            entry.pending = '';
            // Reveal text smoothly, faster when a big chunk arrives at once. A plain
            // timer is used (not animation frames, which browsers pause for hidden
            // or off-screen pages and which would leave the reply invisible).
            entry.pump = () => {
                if (entry.pending.length) {
                    const take = Math.max(2, Math.ceil(entry.pending.length / 12));
                    entry.message += entry.pending.slice(0, take);
                    entry.pending = entry.pending.slice(take);
                    this.scroll();
                }
                if (entry.streaming || entry.pending.length) entry.pumpTimer = setTimeout(entry.pump, 20);
            };
            return entry;
        },
        // Background tabs throttle timers, so text is shown at once there
        // instead of being typed out.
        flush(entry) {
            clearTimeout(entry.pumpTimer);
            entry.message += entry.pending;
            entry.pending = '';
            this.$nextTick(() => this.scroll());
        },
        receive(entry, text) {
            entry.pending += text;
            if (document.hidden) { entry.thinking = false; return this.flush(entry); }
            if (entry.thinking) { entry.thinking = false; entry.pump(); }
        },
        finish(entry, data) {
            clearInterval(entry.timer);
            entry.thinking = false; entry.streaming = false;
            entry.created_at = data.created_at;
            entry.took = ((performance.now() - entry.started) / 1000).toFixed(1) + 's';
            // The saved reply is the source of truth if anything went missing on the way.
            if (data.reply && (entry.message + entry.pending).trim() === '') entry.pending = data.reply;
            if (document.hidden) return this.flush(entry);
            clearTimeout(entry.pumpTimer);
            entry.pump();
            // Safety net: whatever is still waiting is shown within two seconds.
            setTimeout(() => { if (entry.pending) this.flush(entry); }, 2000);
        },

        async send(text = null) {
            const message = (text ?? this.input).trim();
            if (this.busy || !message) return;
            this.busy = true; this.error = '';
            this.messages.push({sender: 'user', message, created_at: new Date().toISOString()});
            this.input = ''; this.$nextTick(() => { this.grow(); this.scroll(); });
            const entry = this.startReply();
            try {
                const response = await fetch(routes.stream, {method: 'POST', headers: {...headers(), 'Accept': 'application/x-ndjson, application/json'}, body: JSON.stringify({message, conversation_id: this.activeId})});
                if (!response.ok) {
                    const result = await response.json().catch(() => ({}));
                    throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Please try again.');
                }
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                for (;;) {
                    const {value, done} = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, {stream: true});
                    let newline;
                    while ((newline = buffer.indexOf('\n')) >= 0) {
                        const line = buffer.slice(0, newline).trim();
                        buffer = buffer.slice(newline + 1);
                        if (!line) continue;
                        const event = JSON.parse(line);
                        if (event.type === 'meta') this.upsertConversation(event.conversation);
                        if (event.type === 'status' && entry.thinking) entry.status = event.text;
                        if (event.type === 'token') this.receive(entry, event.text);
                        if (event.type === 'done') { this.finish(entry, event); this.upsertConversation(event.conversation); if (event.escalate) this.escalated = true; }
                    }
                }
                if (entry.streaming) this.finish(entry, {created_at: new Date().toISOString()});
            } catch (error) {
                clearInterval(entry.timer);
                this.messages.splice(this.messages.indexOf(entry), 1);
                this.error = error.message || 'Could not send your message. Please try again.';
            } finally { this.busy = false; }
        },

        async buildCv() {
            if (this.busy) return;
            const experience = this.input.trim();
            this.busy = true; this.error = '';
            this.messages.push({sender: 'user', message: experience ? 'Build my CV from my profile using: ' + experience : 'Build my CV from my profile', created_at: new Date().toISOString()});
            this.input = ''; this.$nextTick(() => { this.grow(); this.scroll(); });
            const entry = this.startReply();
            entry.status = 'Collecting your profile, skills and qualifications';
            try {
                const response = await fetch(routes.cv, {method: 'POST', headers: headers(), body: JSON.stringify({experience, conversation_id: this.activeId ?? 0})});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Could not build your CV.');
                this.upsertConversation(data.conversation);
                this.receive(entry, data.reply);
                this.finish(entry, data);
            } catch (error) {
                clearInterval(entry.timer);
                this.messages.splice(this.messages.indexOf(entry), 1);
                this.error = error.message;
            } finally { this.busy = false; }
        },
        // ---- Copy to clipboard ----
        // Replies are Markdown; copy what the reader sees (bullets and line breaks kept), not the symbols.
        plainText(entry) {
            if (entry.sender === 'user') return String(entry.message || '');
            const box = document.createElement('div');
            box.style.cssText = 'position:fixed;left:-9999px;top:0;width:600px;white-space:normal';
            box.innerHTML = this.render(entry.message);
            box.querySelectorAll('li').forEach(li => li.prepend(li.parentElement.tagName === 'OL' ? (Array.from(li.parentElement.children).indexOf(li) + 1) + '. ' : '• '));
            document.body.appendChild(box);
            const text = box.innerText.replace(/\n{3,}/g, '\n\n').trim();
            box.remove();
            return text;
        },
        async writeClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // Older browsers or blocked permission: copy through a hidden text area.
                const area = Object.assign(document.createElement('textarea'), {value: text});
                area.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
        },
        flashCopied(key) {
            this.copied = key;
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => { this.copied = null; }, 2000);
        },
        async copyMessage(entry, index) {
            await this.writeClipboard(this.plainText(entry));
            this.flashCopied(index);
        },
        async copyChat() {
            const lines = this.messages.filter(m => !m.thinking && m.message).map(m => (m.sender === 'user' ? 'You' : 'BulaCareer') + ' (' + this.time(m.created_at) + '):\n' + this.plainText(m));
            await this.writeClipboard(this.activeTitle() + '\n\n' + lines.join('\n\n'));
            this.flashCopied('all');
        },

        printCv(sheet) {
            sheet.id = 'cv-output';
            window.print();
            sheet.removeAttribute('id');
        },
    };
}
</script>
@endif
</x-app-layout>
