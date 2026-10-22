<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\JobListing;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Notifications\WelcomeToPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chat sessions and streaming, required documentation, scheduling rules,
 * welcome email and removed-record handling.
 */
class PlatformImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.career_ai.provider' => 'ollama', 'services.career_ai.url' => 'http://model.test']);
    }

    private function employer(): User
    {
        return User::factory()->create(['role' => 'employer', 'employer_approval_status' => 'approved', 'company_name' => 'Test Co', 'industry_sector' => 'IT']);
    }

    private function listing(User $employer, array $overrides = []): JobListing
    {
        return $employer->jobListings()->create($overrides + ['title' => 'Clerk', 'company' => 'Test Co', 'location' => 'Suva', 'region' => 'Central Division', 'type' => 'Full-time', 'description' => 'A test vacancy description.', 'required_skills' => ['Excel'], 'approval_status' => 'approved', 'is_active' => true]);
    }

    private function fijiInput($time): string
    {
        return $time->copy()->timezone('Pacific/Fiji')->format('Y-m-d\TH:i');
    }

    // ---- Chat sessions ----

    public function test_each_new_chat_keeps_its_own_history_and_is_private(): void
    {
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        Http::fake(['model.test/*' => Http::response(['message' => ['content' => 'First answer.']])]);

        $first = $this->actingAs($seeker)->postJson('/chatbot/message', ['message' => 'Tell me about careers in tourism', 'conversation_id' => null])->assertOk()->json('conversation.id');
        $second = $this->postJson('/chatbot/message', ['message' => 'Different topic entirely', 'conversation_id' => null])->assertOk()->json('conversation.id');

        $this->assertNotSame($first, $second);
        $this->getJson("/chatbot/conversations/{$first}")->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('conversation.title', 'Tell me about careers in tourism');

        $other = User::factory()->create(['role' => 'jobseeker']);
        $this->actingAs($other)->getJson("/chatbot/conversations/{$first}")->assertNotFound();

        $this->actingAs($seeker)->deleteJson("/chatbot/conversations/{$first}")->assertOk();
        $this->assertDatabaseMissing('chatbot_messages', ['chat_conversation_id' => $first]);
        $this->getJson("/chatbot/conversations/{$first}")->assertNotFound()->assertJsonPath('removed', true);
    }

    public function test_streaming_reply_arrives_in_parts_and_is_saved(): void
    {
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $chunks = collect(['**Tip:** ', 'use the ', 'STAR method.'])->map(fn ($t) => json_encode(['message' => ['content' => $t], 'done' => false]))->implode("\n")."\n".json_encode(['done' => true])."\n";
        Http::fake(['model.test/*' => Http::response($chunks, 200, ['Content-Type' => 'application/x-ndjson'])]);

        $response = $this->actingAs($seeker)->post('/chatbot/stream', ['message' => 'How do I answer interview questions?', 'conversation_id' => null], ['Accept' => 'application/x-ndjson']);
        $response->assertOk();
        $events = collect(explode("\n", trim($response->streamedContent())))->map(fn ($line) => json_decode($line, true));

        $this->assertSame('meta', $events->first()['type']);
        $this->assertSame(['**Tip:** ', 'use the ', 'STAR method.'], $events->where('type', 'token')->pluck('text')->values()->all());
        $this->assertSame('**Tip:** use the STAR method.', $events->last()['reply']);
        $this->assertSame('interview_practice', $events->last()['intent']);
        $this->assertDatabaseCount('chatbot_messages', 2);
        Http::assertSent(fn ($request) => $request['stream'] === true && $request['keep_alive'] === '30m');
    }

    public function test_cv_is_built_inside_the_chat(): void
    {
        $seeker = User::factory()->create(['role' => 'jobseeker', 'skills' => ['Excel', 'Sales'], 'qualification_level' => 'Diploma', 'about_me' => 'Organised graduate.']);
        $response = $this->actingAs($seeker)->postJson('/chatbot/cv-builder', ['experience' => 'Sales intern at a hardware store.', 'conversation_id' => 0])->assertOk();

        $this->assertStringStartsWith('<!--cv-->', $response['reply']);
        $this->assertStringContainsString('Sales intern at a hardware store.', $response['reply']);
        $this->assertStringContainsString('- Excel', $response['reply']);
        $this->assertSame(1, ChatConversation::count());
        $this->assertDatabaseCount('chatbot_messages', 2);
    }

    // ---- Required documentation ----

    public function test_employer_registration_requires_business_documents(): void
    {
        $base = ['name' => 'Owner Name', 'email' => 'owner@example.test', 'role' => 'employer', 'password' => 'password123', 'password_confirmation' => 'password123', 'company_name' => 'Owner Co', 'industry_sector' => 'Retail'];
        $this->post('/register', $base)->assertSessionHasErrors(['business_registration_number', 'company_address', 'company_description', 'business_document']);

        $this->post('/register', $base + [
            'business_registration_number' => 'BN2024-001234', 'company_address' => 'Victoria Parade, Suva',
            'company_description' => 'A family retail business with four stores in Suva and Nausori.',
            'business_document' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('pending', $owner->employer_approval_status);
        Storage::disk('local')->assertExists($owner->business_document_path);
        Notification::assertSentTo($owner, WelcomeToPlatform::class);

        $this->actingAs(User::factory()->create(['role' => 'jobseeker']))->get(route('employers.document', $owner))->assertForbidden();
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']))->get(route('employers.document', $owner))->assertOk();

        $undocumented = User::factory()->create(['role' => 'employer', 'employer_approval_status' => 'pending', 'company_name' => 'No Docs Ltd']);
        $this->patch(route('admin.employers.approve', $undocumented))->assertSessionHasErrors('employer');
        $this->assertSame('pending', $undocumented->fresh()->employer_approval_status);
    }

    public function test_vacancies_need_documentation_and_close_on_their_closing_date(): void
    {
        $employer = $this->employer();
        $form = ['title' => 'Accounts Officer', 'location' => 'Suva', 'region' => 'Central Division', 'type' => 'Full-time', 'salary_range' => 'FJD 16,000 per year', 'description' => str_repeat('Maintain the accounts ledger. ', 3), 'responsibilities' => 'Reconcile accounts and prepare monthly reports', 'required_degree' => 'Bachelor of Commerce', 'required_skills' => 'Accounting, Excel', 'vacancy_count' => 1];

        $this->actingAs($employer)->post(route('jobs.store'), $form + ['closing_date' => now()->subDay()->toDateString()])->assertSessionHasErrors(['closing_date', 'job_document']);
        $this->post(route('jobs.store'), $form + ['closing_date' => now()->addDays(14)->toDateString(), 'job_document' => UploadedFile::fake()->create('pd.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', JobListing::firstOrFail()->approval_status);

        $closed = $this->listing($employer, ['title' => 'Expired role', 'closing_date' => now()->subDays(2)->toDateString()]);
        $this->assertFalse(JobListing::publiclyVisible()->whereKey($closed->id)->exists());
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $this->actingAs($seeker)->post(route('jobs.apply', $closed), ['resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertForbidden();
    }

    // ---- Scheduling rules ----

    public function test_mentorship_bookings_reject_past_times_and_double_booking(): void
    {
        $mentorA = User::factory()->create(['role' => 'mentor', 'mentor_available' => true]);
        $mentorB = User::factory()->create(['role' => 'mentor', 'mentor_available' => true]);
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $other = User::factory()->create(['role' => 'jobseeker']);
        $slot = now()->addDays(3)->startOfHour();

        $this->actingAs($seeker)->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'CV review', 'scheduled_at' => $this->fijiInput(now()->subDay())])->assertSessionHasErrors('scheduled_at');
        $this->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'CV review', 'scheduled_at' => $this->fijiInput(now()->addMinutes(20))])->assertSessionHasErrors('scheduled_at');
        $this->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'CV review', 'scheduled_at' => $this->fijiInput(now()->addDays(120))])->assertSessionHasErrors('scheduled_at');

        $this->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'CV review', 'scheduled_at' => $this->fijiInput($slot)])->assertSessionHasNoErrors();
        // Same jobseeker, different mentor, overlapping hour.
        $this->post('/career-hub/sessions', ['mentor_id' => $mentorB->id, 'topic' => 'Interview practice', 'scheduled_at' => $this->fijiInput($slot->copy()->addMinutes(30))])->assertSessionHasErrors('scheduled_at');
        // Same mentor, different jobseeker, overlapping hour.
        $this->actingAs($other)->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'Career change', 'scheduled_at' => $this->fijiInput($slot->copy()->subMinutes(30))])->assertSessionHasErrors('scheduled_at');
        // Two hours later is fine.
        $this->post('/career-hub/sessions', ['mentor_id' => $mentorA->id, 'topic' => 'Career change', 'scheduled_at' => $this->fijiInput($slot->copy()->addHours(2))])->assertSessionHasNoErrors();

        $this->assertSame(2, MentorshipSession::count());
    }

    public function test_interviews_cannot_clash_with_other_appointments(): void
    {
        $employerA = $this->employer();
        $employerB = $this->employer();
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $appA = $this->listing($employerA)->applications()->create(['user_id' => $seeker->id, 'resume_path' => 'r.pdf', 'match_percentage' => 50]);
        $appB = $this->listing($employerB)->applications()->create(['user_id' => $seeker->id, 'resume_path' => 'r.pdf', 'match_percentage' => 50]);
        $slot = now()->addDays(5)->startOfHour();

        $this->actingAs($employerA)->patch(route('employer.applications.update', $appA), ['status' => 'Interview Scheduled', 'interview_at' => $this->fijiInput(now()->subHour())])->assertSessionHasErrors('interview_at');
        $this->patch(route('employer.applications.update', $appA), ['status' => 'Interview Scheduled', 'interview_at' => $this->fijiInput($slot)])->assertSessionHasNoErrors();
        $this->actingAs($employerB)->patch(route('employer.applications.update', $appB), ['status' => 'Interview Scheduled', 'interview_at' => $this->fijiInput($slot->copy()->addMinutes(45))])->assertSessionHasErrors('interview_at');

        $mentor = User::factory()->create(['role' => 'mentor', 'mentor_available' => true]);
        $this->actingAs($seeker)->post('/career-hub/sessions', ['mentor_id' => $mentor->id, 'topic' => 'Prep', 'scheduled_at' => $this->fijiInput($slot->copy()->addMinutes(30))])->assertSessionHasErrors('scheduled_at');
    }

    // ---- Removed records, help, dark mode ----

    public function test_removed_records_explain_that_they_were_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'jobseeker']))
            ->get('/jobs/99999')->assertNotFound()->assertSee('This job listing was removed')->assertSee('removed from the system');
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
    }

    public function test_admin_help_is_only_shown_to_admins_and_pages_offer_dark_mode(): void
    {
        $this->get(route('help'))->assertOk()->assertDontSee('For administrators')->assertSee('Toggle dark mode');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('help'))->assertOk()->assertSee('For administrators');
    }
}
