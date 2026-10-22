<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_registration_emails_are_rejected_even_with_a_phone(): void
    {
        foreach (['missing-at.com', 'person@example', 'person@@example.com', 'person@example.c', 'person@-example.com', 'person@gmail.comm', 'example@fmail.com'] as $email) {
            $this->post('/register', ['name'=>'Applicant', 'email'=>$email, 'phone'=>'7001234', 'password'=>'password123', 'password_confirmation'=>'password123'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_fmail_requires_explicit_confirmation_and_preserves_the_address(): void
    {
        Notification::fake();
        $data=['name'=>'Applicant', 'email'=>'example@fmail.com', 'password'=>'password123', 'password_confirmation'=>'password123'];
        $this->post('/register', $data)->assertSessionHasErrors('email');
        $this->post('/register', $data+['confirm_email_domain'=>'1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email'=>'example@fmail.com']);
    }

    public function test_valid_non_com_domains_are_allowed_and_normalized(): void
    {
        Notification::fake();
        $this->post('/register', ['name'=>'Applicant', 'email'=>'  PERSON@EXAMPLE.ORG ', 'password'=>'password123', 'password_confirmation'=>'password123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email'=>'person@example.org']);
    }

    public function test_profile_rejects_incomplete_email_without_changing_saved_address(): void
    {
        $user=User::factory()->create(['role'=>'employer']);
        $this->actingAs($user)->patch('/profile', ['name'=>$user->name,'email'=>'person@example','company_name'=>'Business','industry_sector'=>'Tourism'])->assertSessionHasErrors('email');
        $this->assertSame($user->email,$user->fresh()->email);
    }
}
