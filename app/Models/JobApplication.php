<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213).
 */
class JobApplication extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Submitted',
        'Under Review',
        'Shortlisted',
        'Interview Scheduled',
        'Rejected',
        'Hired',
    ];

    protected $fillable = [
        'job_listing_id',
        'user_id',
        'cover_letter',
        'resume_path',
        'status',
        'match_percentage',
        'interview_at',
        'employer_notes',
    ];

    protected function casts(): array
    {
        return [
            'interview_at' => 'datetime',
            'match_percentage' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // FR4.5: record each stage so the jobseeker can follow the full progress.
        static::created(function (JobApplication $application) {
            $application->statusChanges()->create([
                'to_status' => $application->status ?? 'Submitted',
                'changed_by' => auth()->id(),
            ]);
        });

        static::updated(function (JobApplication $application) {
            if ($application->wasChanged('status')) {
                $application->statusChanges()->create([
                    'from_status' => $application->getOriginal('status'),
                    'to_status' => $application->status,
                    'interview_at' => $application->interview_at,
                    'changed_by' => auth()->id(),
                ]);
            }
        });
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusChange::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Position on the happy path (Submitted -> Hired) used by the progress
     * stepper. Rejected applications stop at the last stage they reached.
     */
    public function progressStep(): int
    {
        $path = ['Submitted', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Hired'];
        $index = array_search($this->status, $path, true);
        if ($index !== false) {
            return $index;
        }

        $reached = $this->relationLoaded('statusChanges')
            ? $this->statusChanges->pluck('to_status')
            : $this->statusChanges()->pluck('to_status');

        return (int) $reached->map(fn ($status) => array_search($status, $path, true))->filter(fn ($i) => $i !== false)->max();
    }

    public function jobseeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'Submitted' => 'bg-slate-100 text-slate-700',
            'Under Review' => 'bg-blue-100 text-blue-700',
            'Shortlisted' => 'bg-amber-100 text-amber-700',
            'Interview Scheduled' => 'bg-purple-100 text-purple-700',
            'Rejected' => 'bg-red-100 text-red-700',
            'Hired' => 'bg-success-100 text-success-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
