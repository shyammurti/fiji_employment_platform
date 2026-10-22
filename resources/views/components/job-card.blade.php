{{-- Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213). --}}
@props(['job', 'score' => null])
<a href="{{ route('jobs.show', $job) }}" {{ $attributes->merge(['class' => 'card-link flex h-full flex-col']) }}>
    <div class="flex items-start justify-between gap-3">
        <h3 class="min-w-0 font-semibold text-pretty text-fiji-900">{{ $job->title }}</h3>
        <span class="status-pill shrink-0 bg-ocean-50 text-ocean-700">{{ $job->type }}</span>
    </div>
    <p class="mt-1 truncate text-sm text-slate-600">{{ $job->company }}, {{ $job->location }}</p>
    @if ($job->salary_range)
        <p class="mt-2 text-sm font-medium text-slate-700">{{ $job->salary_range }}</p>
    @endif
    <p class="mt-3 line-clamp-2 flex-1 text-sm leading-6 text-slate-600">{{ Str::limit(strip_tags($job->description), 140) }}</p>
    @if ($score !== null)
        <div class="mt-4 border-t border-slate-100 pt-3">
            <div class="flex items-center justify-between text-xs font-semibold">
                <span class="text-slate-600" title="Share of this job's required skills that are on your profile">Your skill match</span>
                <span class="tabular-nums {{ $score >= 70 ? 'text-success-700' : ($score >= 40 ? 'text-amber-800' : 'text-slate-600') }}">{{ $score }}%</span>
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="presentation">
                <div class="h-full rounded-full {{ $score >= 70 ? 'bg-success-600' : ($score >= 40 ? 'bg-amber-500' : 'bg-slate-400') }}" style="width: {{ max($score, 3) }}%"></div>
            </div>
        </div>
    @endif
</a>
