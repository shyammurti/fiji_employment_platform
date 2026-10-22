<x-app-layout>
    <section class="hero-panel relative overflow-hidden" aria-labelledby="hero-title">
        <div class="relative z-10 grid items-center gap-12 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <p class="eyebrow text-[#a8dede]">Bula. Your next chapter starts here.</p>
                <h1 id="hero-title" class="mt-5 max-w-3xl text-4xl font-semibold leading-[1.05] tracking-tight text-balance text-white sm:text-6xl">Local talent.<br><span class="text-[#a8dede]">Lasting opportunity.</span></h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-200">Connect your skills with approved vacancies across Fiji and learn from experienced mentors.</p>
                <form action="{{ route('jobs.index') }}" method="GET" role="search" class="mt-8 flex flex-col gap-2 rounded-2xl bg-surface p-2 shadow-pop sm:flex-row">
                    <label class="sr-only" for="hero-search">Search jobs</label>
                    <input id="hero-search" name="q" type="search" autocomplete="off" class="min-w-0 flex-1 rounded-xl border-0 bg-transparent px-4 py-3 text-charcoal placeholder:text-slate-500 focus:ring-2 focus:ring-ocean-600" placeholder="Job title, company or keyword…">
                    <button type="submit" class="btn-primary !px-6 !py-3">Find jobs</button>
                </form>
                <p class="mt-5 text-sm text-slate-200"><span class="font-semibold tabular-nums text-white">{{ $liveCount }}</span> approved {{ Str::plural('vacancy', $liveCount) }} open right now.</p>
            </div>
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-white/15 bg-white/10 p-7 backdrop-blur">
                    <h2 class="text-xl font-semibold text-white">How it works</h2>
                    <ol class="mt-6 space-y-6">
                        @foreach (['Build your profile' => 'Add your skills and upload your credential for verification.', 'Get matched' => 'Receive a notice when an approved vacancy shares your skills.', 'Prepare and apply' => 'Practise interviews, build a CV and track every application.'] as $title => $description)
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#a8dede]/50 text-sm font-semibold text-[#d4f1f1]" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div><h3 class="font-semibold text-white">{{ $title }}</h3><p class="mt-1 text-sm leading-relaxed text-slate-200">{{ $description }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="mt-7 inline-flex items-center gap-2 font-semibold text-[#a8dede] hover:text-white">{{ auth()->check() ? 'Open your dashboard' : 'Create your profile' }} <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-16" aria-labelledby="latest-title">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h2 id="latest-title" class="text-2xl font-semibold tracking-tight text-fiji-900 sm:text-3xl">Latest vacancies</h2>
            <a href="{{ route('jobs.index') }}" class="link text-sm">See all jobs <span aria-hidden="true">&rarr;</span></a>
        </div>
        @if ($latestJobs->isNotEmpty())
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($latestJobs as $job)
                    <x-job-card :job="$job" />
                @endforeach
            </div>
        @else
            <div class="card text-center text-sm text-slate-600">New vacancies appear here once an administrator approves them.</div>
        @endif
    </section>

    <section class="mt-16 grid gap-10 lg:grid-cols-3" aria-labelledby="roles-title">
        <div>
            <h2 id="roles-title" class="text-2xl font-semibold tracking-tight text-balance text-fiji-900 sm:text-3xl">One platform for every part of hiring.</h2>
            <p class="mt-3 text-slate-600">Every vacancy and employer is reviewed by an administrator before it goes public.</p>
        </div>
        <ul class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-surface lg:col-span-2">
            @foreach ([
                ['Jobseekers', 'Create a profile, apply to reviewed vacancies and follow each application from Submitted to Hired.', route('register'), 'Create a profile'],
                ['Employers', 'Post vacancies for review, search candidates by skill and region, and schedule interviews.', route('register', ['role' => 'employer']), 'Register a business'],
                ['Mentors', 'List your expertise, accept session requests and help graduates take their next step.', route('mentor.register'), 'Become a mentor'],
            ] as [$label, $description, $link, $action])
                <li class="flex flex-col gap-3 p-6 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                    <div class="max-w-xl">
                        <h3 class="font-semibold text-fiji-900">{{ $label }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
                    </div>
                    <a href="{{ $link }}" class="btn-secondary shrink-0">{{ $action }}</a>
                </li>
            @endforeach
        </ul>
    </section>
</x-app-layout>
