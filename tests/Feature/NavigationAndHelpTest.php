<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Rubric items: breadcrumbs (Ease of Use), tooltips and per-page help
 * (Help Features), error summary that leads to the field (Error Handling)
 * and styled confirmation dialogs (consistent UI).
 */
class NavigationAndHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function people(): array
    {
        $employer = User::factory()->create(['role' => 'employer', 'employer_approval_status' => 'approved', 'company_name' => 'Test Co', 'industry_sector' => 'IT']);
        $listing = $employer->jobListings()->create(['title' => 'Clerk', 'company' => 'Test Co', 'location' => 'Suva', 'region' => 'Central Division', 'type' => 'Full-time', 'description' => 'A test vacancy description.', 'required_skills' => ['Excel'], 'approval_status' => 'approved', 'is_active' => true]);
        $seeker = User::factory()->create(['role' => 'jobseeker']);

        return [
            'jobseeker' => [$seeker, ['/dashboard', '/jobs', "/jobs/{$listing->id}", '/my-applications', '/career-hub', '/profile', '/employer/register']],
            'employer' => [$employer, ['/employer/jobs', '/employer/applications', '/employer/candidates', "/employer/candidates/{$seeker->id}", "/jobs/{$listing->id}/applications", '/jobs/create', "/jobs/{$listing->id}/edit"]],
            'mentor' => [User::factory()->create(['role' => 'mentor', 'mentor_expertise' => 'IT', 'mentor_bio' => 'Bio']), ['/career-hub', '/profile']],
            'admin' => [User::factory()->create(['role' => 'admin']), ['/admin/dashboard', '/admin/employers/pending', '/admin/jobs/pending', "/admin/jobs/{$listing->id}/review", '/admin/verifications', '/admin/users']],
        ];
    }

    public function test_every_page_has_breadcrumbs_and_a_help_link_to_a_real_faq_answer(): void
    {
        foreach ($this->people() as $role => [$user, $paths]) {
            $helpPage = $this->actingAs($user)->get('/help')->assertOk()->getContent();
            foreach ($paths as $path) {
                $html = $this->get($path)->assertOk()->getContent();
                $this->assertStringContainsString('aria-label="Breadcrumb"', $html, "$role $path has no breadcrumbs");
                preg_match('#<nav aria-label="Breadcrumb".*?</nav>#s', $html, $crumbs);
                $this->assertStringContainsString('href="'.route('home').'"', $crumbs[0], "$role $path breadcrumbs do not start at Home");
                $this->assertStringContainsString('aria-current="page"', $crumbs[0], "$role $path breadcrumbs do not mark the current page");

                $this->assertMatchesRegularExpression('#href="[^"]*/help\#([a-z0-9-]+)"#', $html, "$role $path has no help link");
                preg_match('#href="[^"]*/help\#([a-z0-9-]+)"#', $html, $match);
                $this->assertStringContainsString('id="'.$match[1].'"', $helpPage, "$role $path links to missing FAQ anchor #{$match[1]}");
            }
        }
    }

    public function test_breadcrumbs_show_the_path_to_the_current_page(): void
    {
        [$employer] = $this->people()['employer'];
        $listing = JobListing::first();

        $this->actingAs($employer)->get("/jobs/{$listing->id}/applications")->assertOk()
            ->assertSeeInOrder(['Home', 'Dashboard', 'My listings', 'Clerk'])
            ->assertSee(route('employer.jobs.index'), false);

        $this->get('/jobs')->assertOk(); // guests see Home › Browse jobs too
        auth()->logout();
        $this->get("/jobs/{$listing->id}")->assertOk()->assertSeeInOrder(['Home', 'Browse jobs', 'Clerk']);
    }

    public function test_tooltips_explain_key_fields(): void
    {
        [$seeker, $paths] = $this->people()['jobseeker'];
        $this->actingAs($seeker)->get('/profile')->assertOk()
            ->assertSee('role="tooltip"', false)
            ->assertSee('Your skill match score for each job compares these skills');

        [$employer] = $this->people()['employer'];
        $this->actingAs($employer)->get('/jobs/create')->assertOk()
            ->assertSee('The last day applications are accepted')
            ->assertSee('aria-describedby="tip-', false);
    }

    public function test_help_page_has_search_and_answers_for_new_features(): void
    {
        $this->get('/help')->assertOk()
            ->assertSee('id="faq-search"', false)
            ->assertSee('id="faq-how-do-i-move-through-long-lists"', false)
            ->assertSee('id="faq-why-do-i-need-to-verify-my-email"', false)
            ->assertSee('Can I copy the assistant', false);
    }

    public function test_failed_submit_lists_errors_that_lead_to_each_field(): void
    {
        [$employer] = $this->people()['employer'];
        $this->actingAs($employer)->from('/jobs/create')->followingRedirects()
            ->post('/jobs', ['title' => '', 'location' => 'Suva', 'region' => 'Central Division', 'type' => 'Full-time'])
            ->assertOk()
            ->assertSee('data-error-summary', false)
            ->assertSee('data-error-for="title"', false)
            ->assertSee('data-error-fields="title,', false)
            ->assertSee('things need fixing before you can continue');
    }

    public function test_destructive_actions_use_the_styled_confirmation(): void
    {
        $views = collect(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'));
        foreach ($views as $file) {
            $this->assertStringNotContainsString('confirm(\'', file_get_contents($file->getPathname()), $file->getFilename().' still uses the browser confirm() box');
        }

        [$employer] = $this->people()['employer'];
        $this->actingAs($employer)->get('/employer/jobs')->assertOk()
            ->assertSee('data-confirm-title="Archive this listing?"', false)
            ->assertSee('btn-danger-soft', false);
    }
}
