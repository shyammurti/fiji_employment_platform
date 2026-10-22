<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'login_phone',
        'password',
        'role',
        'employer_approval_status',
        'skills',
        'degree_certificate_path',
        'is_verified',
        'region',
        'qualification_level',
        'years_experience',
        'company_name',
        'industry_sector',
        'mentor_expertise',
        'mentor_bio',
        'admin_notes',
        'credential_status',
        'credential_notes',
        'mentor_available',
        'about_me',
        'mentor_industry',
        'business_registration_number',
        'company_address',
        'company_website',
        'company_description',
        'business_document_path',
        'business_document_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'mentor_available' => 'boolean',
            'skills' => 'array',
            'years_experience' => 'integer',
        ];
    }

    // ---- Role helpers ----
    public function hasVerifiedEmail(): bool
    {
        return $this->email === null || $this->email_verified_at !== null;
    }


    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEmployer(): bool
    {
        return $this->role === 'employer';
    }

    public function isJobseeker(): bool
    {
        return $this->role === 'jobseeker';
    }

    public function isMentor(): bool
    {
        return $this->role === 'mentor';
    }

    public function isApprovedEmployer(): bool
    {
        return $this->isEmployer() && $this->employer_approval_status === 'approved';
    }

    public function skillsList(): array
    {
        if (empty($this->skills)) {
            return [];
        }

        return is_array($this->skills) ? $this->skills : [];
    }

    // ---- Relationships ----

    public function jobListings(): HasMany
    {
        return $this->hasMany(JobListing::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function mentorSessions(): HasMany
    {
        return $this->hasMany(MentorshipSession::class, 'mentor_id');
    }

    public function menteeSessions(): HasMany
    {
        return $this->hasMany(MentorshipSession::class, 'mentee_id');
    }

    public function chatbotMessages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
