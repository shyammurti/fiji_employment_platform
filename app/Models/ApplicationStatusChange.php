<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in an application's progress (FR4.5). Rows are written by
 * JobApplication model events, so every create/update path is covered.
 */
class ApplicationStatusChange extends Model
{
    protected $fillable = ['job_application_id', 'from_status', 'to_status', 'interview_at', 'changed_by'];

    protected function casts(): array
    {
        return ['interview_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
