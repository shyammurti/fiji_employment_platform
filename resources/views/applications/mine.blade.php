<x-app-layout title="My applications">
    <x-slot name="header">
        <h1 class="page-title">My applications</h1>
        <p class="page-intro">Follow each application from Submitted to a final outcome. You are notified whenever an employer changes its status.</p>
    </x-slot>

    @php($stages = ['Submitted', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Hired'])

    <div class="space-y-5">
        @forelse ($applications as $app)
            @php($step = $app->progressStep())
            @php($rejected = $app->status === 'Rejected')
            <article class="card" x-data="{ history: false }" aria-labelledby="app-{{ $app->id }}-title">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="app-{{ $app->id }}-title" class="font-semibold text-fiji-900"><a href="{{ route('jobs.show', $app->jobListing) }}" class="hover:text-ocean-700">{{ $app->jobListing->title }}</a></h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $app->jobListing->company }}. Applied {{ $app->created_at->timezone('Pacific/Fiji')->format('d M Y') }}.
                            @if ($app->jobListing->requiredSkillsList())<span class="tabular-nums">Skill match {{ $app->match_percentage }}%.</span>@endif
                        </p>
                        @if ($app->jobListing->is_archived || ! $app->jobListing->is_active)
                            <p class="mt-1 text-xs text-slate-600">{{ $app->jobListing->is_archived ? 'Listing archived' : 'Applications closed' }}. Your record is retained.</p>
                        @endif
                    </div>
                    <span class="status-pill {{ $app->statusColor() }}">{{ $app->status }}</span>
                </div>

                @if ($app->status === 'Interview Scheduled' && $app->interview_at)
                    <p class="mt-4 rounded-xl bg-purple-50 px-4 py-3 text-sm text-purple-900"><strong>Interview:</strong> {{ $app->interview_at->timezone('Pacific/Fiji')->format('l d M Y, g:i A') }} (Fiji time)</p>
                @endif

                {{-- Stage stepper (FR4.3 / FR4.5) --}}
                <ol class="mt-6 grid grid-cols-5 gap-1" aria-label="Application progress">
                    @foreach ($stages as $i => $stage)
                        @php($done = $i <= $step)
                        @php($current = $i === $step && ! $rejected)
                        <li class="min-w-0" @if($current) aria-current="step" @endif>
                            <div class="h-1.5 rounded-full {{ $done ? ($rejected ? 'bg-red-300' : 'bg-ocean-600') : 'bg-slate-200' }}"></div>
                            <p class="mt-2 hidden text-xs sm:block {{ $current ? 'font-semibold text-fiji-900' : ($done ? 'text-slate-700' : 'text-slate-500') }}">{{ $stage }}</p>
                            <span class="sr-only">{{ $stage }}: {{ $done ? 'reached' : 'not reached' }}</span>
                        </li>
                    @endforeach
                </ol>
                @if ($rejected)
                    <p class="mt-3 text-sm text-red-800">This application was not successful after the {{ $stages[$step] }} stage.</p>
                @endif

                <button type="button" class="link mt-4 text-sm" @click="history = !history" :aria-expanded="history" aria-controls="history-{{ $app->id }}">
                    <span x-text="history ? 'Hide status history' : 'Show status history ({{ $app->statusChanges->count() }})'">Show status history</span>
                </button>
                <ol id="history-{{ $app->id }}" x-show="history" x-cloak class="mt-4 space-y-3 border-l-2 border-slate-200 pl-5">
                    @foreach ($app->statusChanges->reverse() as $change)
                        <li class="relative">
                            <span class="absolute -left-[1.62rem] top-1.5 h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-ocean-600' : 'bg-slate-300' }}" aria-hidden="true"></span>
                            <p class="text-sm font-medium text-fiji-900">{{ $change->to_status }}</p>
                            <p class="text-xs text-slate-600"><time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->timezone('Pacific/Fiji')->format('d M Y, g:i A') }}</time>
                            </p>
                            @if ($change->interview_at)<p class="text-xs text-slate-600">Interview set for {{ $change->interview_at->timezone('Pacific/Fiji')->format('d M Y, g:i A') }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </article>
        @empty
            <div class="card py-12 text-center">
                <h2 class="text-lg font-semibold text-fiji-900">You have not applied to any jobs yet</h2>
                <p class="mt-2 text-sm text-slate-600">Browse approved vacancies and apply with your resume.</p>
                <a href="{{ route('jobs.index') }}" class="btn-primary mt-5">Browse jobs</a>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $applications->links() }}</div>
</x-app-layout>
