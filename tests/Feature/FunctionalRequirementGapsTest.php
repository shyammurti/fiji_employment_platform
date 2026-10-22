<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the gaps closed after auditing the build against the SRS and SAD.
 */
class FunctionalRequirementGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function employer(): User
    {
        return User::factory()->create(['role' => 'employer', 'employer_approval_status' => 'approved', 'company_name' => 'Test Co', 'industry_sector' => 'IT']);
    }

    private function listing(User $employer, array $skills = ['Excel', 'Sales']): JobListing
    {
        return $employer->jobListings()->create(['title' => 'Clerk', 'company' => 'Test Co', 'location' => 'Suva', 'region' => 'Central Division', 'type' => 'Full-time', 'description' => 'A test vacancy description.', 'required_skills' => $skills, 'approval_status' => 'approved', 'is_active' => true]);
    }

    private function apply(JobListing $listing, User $seeker): JobApplication
    {
        return $listing->applications()->create(['user_id' => $seeker->id, 'resume_path' => 'resumes/r.pdf', 'match_percentage' => 50]);
    }

    // FR4.5: history of status progress
    public function test_status_history_is_recorded_and_shown_to_the_jobseeker(): void
    {
        $employer = $this->employer();
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $application = $this->apply($this->listing($employer), $seeker);

        $this->actingAs($employer)->patch(route('employer.applications.update', $application), ['status' => 'Under Review'])->assertSessionHasNoErrors();
        $this->patch(route('employer.applications.update', $application), ['status' => 'Shortlisted'])->assertSessionHasNoErrors();

        $this->assertSame(['Submitted', 'Under Review', 'Shortlisted'], $application->statusChanges()->pluck('to_status')->all());
        $this->assertSame($employer->id, $application->statusChanges->last()->changed_by);
        $this->assertSame(2, $application->fresh()->progressStep());

        $this->actingAs($seeker)->get(route('applications.mine'))->assertOk()
            ->assertSee('Show status history (3)', false)->assertSee('Shortlisted');
    }

    public function test_rejected_application_keeps_the_last_stage_reached(): void
    {
        $employer = $this->employer();
        $application = $this->apply($this->listing($employer), User::factory()->create(['role' => 'jobseeker']));
        $this->actingAs($employer)->patch(route('employer.applications.update', $application), ['status' => 'Shortlisted']);
        $this->patch(route('employer.applications.update', $application), ['status' => 'Rejected']);

        $this->assertSame(2, $application->fresh()->progressStep());
    }

    // FR4.4: notify on status change, not on internal note edits
    public function test_note_only_updates_do_not_notify_the_applicant(): void
    {
        $employer = $this->employer();
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $application = $this->apply($this->listing($employer), $seeker);

        $this->actingAs($employer)->patch(route('employer.applications.update', $application), ['status' => 'Submitted', 'employer_notes' => 'Strong CV']);
        Notification::assertNotSentTo($seeker, ApplicationStatusUpdated::class);

        $this->patch(route('employer.applications.update', $application), ['status' => 'Under Review']);
        Notification::assertSentTo($seeker, ApplicationStatusUpdated::class);
    }

    // REQ-2 and REQ-7
    public function test_chat_intents_follow_the_srs_and_empty_messages_get_guidance(): void
    {
        $seeker = User::factory()->create(['role' => 'jobseeker']);
        $this->actingAs($seeker)->postJson('/chatbot/message', ['message' => '   '])
            ->assertOk()->assertJsonPath('intent', 'general_enquiry')->assertJsonPath('escalate', false);
        $this->assertDatabaseCount('chatbot_messages', 0);

        $this->postJson('/chatbot/message', ['message' => 'Can I speak to a real person?'])->assertJsonPath('intent', 'mentor_escalation');

        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson('/chatbot/message', ['message' => 'What does a human resources officer do?'])->assertJsonPath('intent', 'general_enquiry');
        $this->postJson('/chatbot/message', ['message' => 'Can you check my CV?'])->assertJsonPath('intent', 'cv_assistance');
    }

    // FR2.4 + SRS 5.2: credential privacy for employers
    public function test_employers_only_see_verified_or_applicant_credentials(): void
    {
        $employer = $this->employer();
        Storage::disk('local')->put('credentials/a.pdf', 'doc');
        $unverified = User::factory()->create(['role' => 'jobseeker', 'degree_certificate_path' => 'credentials/a.pdf', 'is_verified' => false]);
        $verified = User::factory()->create(['role' => 'jobseeker', 'degree_certificate_path' => 'credentials/a.pdf', 'is_verified' => true]);

        $this->actingAs($employer)->get(route('credentials.document', $unverified))->assertForbidden();
        $this->get(route('credentials.document', $verified))->assertOk();

        $this->apply($this->listing($employer), $unverified);
        $this->get(route('credentials.document', $unverified))->assertOk();

        $this->actingAs($this->employer())->get(route('credentials.document', $unverified))->assertForbidden();
    }

    // FR4.2: match shown before applying, recommendations ranked by match
    public function test_jobseekers_see_their_skill_match_breakdown(): void
    {
        $listing = $this->listing($this->employer(), ['Excel', 'Sales', 'Driving']);
        $seeker = User::factory()->create(['role' => 'jobseeker', 'skills' => ['excel', 'Sales']]);

        $this->actingAs($seeker)->get(route('jobs.show', $listing))->assertOk()
            ->assertSee('Your skill match')->assertSee('67%')->assertSee('Not on your profile yet');
        $this->get(route('jobs.index'))->assertOk()->assertSee('67%');
        $this->get(route('dashboard'))->assertOk()->assertSee('Recommended for you')->assertSee('Clerk');
    }

    // FR1.2 / FR-14 / REQ-5
    public function test_about_me_and_mentor_industry_are_saved_and_used(): void
    {
        $seeker = User::factory()->create(['role' => 'jobseeker', 'about_me' => 'Graduate accountant.', 'skills' => ['Excel']]);
        $this->actingAs($seeker)->postJson('/chatbot/cv-builder', [])->assertOk()->assertJsonPath('cv.summary', 'Graduate accountant.');

        $mentor = User::factory()->create(['role' => 'mentor', 'mentor_expertise' => 'IT', 'mentor_bio' => 'Bio']);
        $this->actingAs($mentor)->patch(route('profile.update'), ['name' => $mentor->name, 'email' => $mentor->email, 'mentor_expertise' => 'IT', 'mentor_bio' => 'Bio', 'mentor_industry' => 'Banking', 'mentor_available' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Banking', $mentor->fresh()->mentor_industry);
    }

    public function test_every_role_dashboard_and_main_pages_render(): void
    {
        $employer = $this->employer();
        $this->apply($this->listing($employer), $seeker = User::factory()->create(['role' => 'jobseeker']));
        $admin = User::factory()->create(['role' => 'admin']);
        $mentor = User::factory()->create(['role' => 'mentor']);

        $this->get('/')->assertOk()->assertSee('Latest vacancies');
        $this->actingAs($seeker)->get(route('career.hub'))->assertOk();
        $this->actingAs($mentor)->get(route('dashboard'))->assertOk();
        $this->actingAs($employer)->get(route('employer.candidates.index', ['verified_only' => 1]))->assertOk();
        $this->get(route('employer.jobs.applications', $employer->jobListings()->first()))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Recent application activity');
        $this->patch(route('notifications.readAll'))->assertRedirect();
    }
}
