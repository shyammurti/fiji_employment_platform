<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Notifications\WelcomeToPlatform;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function register(string $email): void
    {
        $this->post('/register', ['name' => 'New Person', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123']);
    }

    public function test_with_live_mail_new_accounts_must_verify_their_email(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->register('new.person@gmail.com');
        $user = User::where('email', 'new.person@gmail.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNotSentTo($user, WelcomeToPlatform::class);
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('new.person@gmail.com');
        $this->get('/profile')->assertOk(); // a mistyped address can still be corrected

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, WelcomeToPlatform::class);
        $this->get('/dashboard')->assertOk();
    }

    public function test_without_live_mail_accounts_are_trusted_and_welcomed(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);

        $this->register('trusted@gmail.com');
        $user = User::where('email', 'trusted@gmail.com')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        Notification::assertNotSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, WelcomeToPlatform::class);
    }

    public function test_changing_email_requires_verifying_the_new_address(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $mentor = User::factory()->create(['role' => 'mentor', 'email' => 'old@gmail.com', 'mentor_expertise' => 'IT', 'mentor_bio' => 'Bio']);

        $this->actingAs($mentor)->patch('/profile', ['name' => $mentor->name, 'email' => 'new@gmail.com', 'mentor_expertise' => 'IT', 'mentor_bio' => 'Bio'])
            ->assertRedirect(route('verification.notice'));
        $this->assertNull($mentor->fresh()->email_verified_at);
        Notification::assertSentTo($mentor, VerifyEmail::class);
    }

    public function test_notifications_email_real_addresses_but_skip_demo_domains(): void
    {
        // Live SMTP pointed at a closed port: a real send fails fast and must not break the request.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        Log::spy();
        $real = User::factory()->create(['email' => 'someone@gmail.com']);
        $demo = User::factory()->create(['email' => 'demo.user@example.com']);

        $real->notify(new PlatformAlert('Your listing was approved.'));
        $demo->notify(new PlatformAlert('Your listing was approved.'));

        // In-app notifications are always stored.
        $this->assertSame(1, $real->notifications()->count());
        $this->assertSame(1, $demo->notifications()->count());
        // Only the demo address is skipped; the real one was attempted (and its failure logged, not thrown).
        Log::shouldHaveReceived('info')->with('Email skipped for demo address', \Mockery::on(fn ($context) => $context['to'] === 'demo.user@example.com'))->once();
        Log::shouldNotHaveReceived('info', ['Email skipped for demo address', \Mockery::on(fn ($context) => $context['to'] === 'someone@gmail.com')]);
        $this->assertTrue(\App\Support\MailDelivery::isReservedAddress('x@sub.example.org'));
        $this->assertFalse(\App\Support\MailDelivery::isReservedAddress('x@usp.ac.fj'));
    }

    public function test_pagination_offers_ranges_page_numbers_and_page_sizes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Aaa Admin']);
        User::factory()->count(44)->create(['role' => 'jobseeker']);

        $page = $this->actingAs($admin)->get('/admin/users?per_page=10&page=2')->assertOk();
        $page->assertSee('11–20', false)->assertSee('41–45', false)->assertSee('of 45')
            ->assertSee('name="per_page"', false)->assertSee('aria-current="page"', false)
            ->assertSee('per_page=10&amp;page=5', false);

        $this->get('/admin/users?per_page=5000')->assertOk()->assertSee('1–20', false); // unsupported size falls back
        $this->get('/jobs?per_page=18')->assertOk();
    }
}
