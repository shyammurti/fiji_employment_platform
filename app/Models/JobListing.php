<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class JobListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'company',
        'location',
        'region',
        'industry_sector',
        'type',
        'salary_range',
        'description',
        'required_degree',
        'required_skills',
        'approval_status',
        'is_active',
        'is_archived',
        'admin_notes',
        'vacancy_count',
        'document_path',
        'document_name',
        'closure_reason',
        'recruitment_completed_at',
        'responsibilities',
        'closing_date',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'array',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'vacancy_count' => 'integer',
            'recruitment_completed_at' => 'datetime',
            'closing_date' => 'date',
        ];
    }

    // ---- Relationships ----

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    // ---- Scopes ----

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(fn ($q) => $q->whereNull('closing_date')->orWhereDate('closing_date', '>=', now('Pacific/Fiji')->toDateString()))
            ->whereHas('employer', fn ($employer) => $employer->where('role', 'employer')->where('employer_approval_status', 'approved'));
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('approval_status', 'pending_approval');
    }

    public function hasClosed(): bool
    {
        return $this->closing_date !== null && $this->closing_date->lt(now('Pacific/Fiji')->startOfDay());
    }

    public function isAcceptingApplications(): bool
    {
        return $this->approval_status === 'approved' && $this->is_active && ! $this->is_archived && ! $this->hasClosed();
    }

    public function requiredSkillsList(): array
    {
        if (empty($this->required_skills)) {
            return [];
        }

        return is_array($this->required_skills) ? $this->required_skills : [];
    }

    public function canBeViewedBy(?User $user): bool
    {
        if ($user && ($user->isAdmin() || $this->user_id === $user->id)) {
            return true;
        }
        if ($this->approval_status === 'approved' && ! $this->is_archived && $this->employer->isApprovedEmployer()) {
            return true;
        }

        return $user && $this->applications()->where('user_id', $user->id)->exists();
    }
}
