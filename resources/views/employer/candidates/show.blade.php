<x-app-layout :title="$candidate->name">
    <x-slot name="header">
        <a href="{{ route('employer.candidates.index') }}" class="link text-sm"><span aria-hidden="true">&larr;</span> Back to candidates</a>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $candidate->name }}</h1>
            @if ($candidate->is_verified)<span class="badge-verified">Verified credential</span>@endif
        </div>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <article class="card lg:col-span-2">
            @if ($candidate->about_me)
                <h2 class="text-lg font-semibold text-fiji-900">About</h2>
                <p class="mt-2 max-w-[70ch] whitespace-pre-line leading-7 text-slate-700">{{ $candidate->about_me }}</p>
            @endif
            <h2 class="{{ $candidate->about_me ? 'mt-8' : '' }} text-lg font-semibold text-fiji-900">Skills</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @forelse ($candidate->skillsList() as $skill)
                    <li class="status-pill bg-ocean-50 text-ocean-700">{{ $skill }}</li>
                @empty
                    <li class="text-sm text-slate-600">No skills listed.</li>
                @endforelse
            </ul>
        </article>

        <aside class="card">
            <dl class="space-y-4 text-sm">
                <div><dt class="text-slate-600">Qualification</dt><dd class="font-medium text-fiji-900">{{ $candidate->qualification_level ?: 'Not provided' }}</dd></div>
                <div><dt class="text-slate-600">Experience</dt><dd class="font-medium tabular-nums text-fiji-900">{{ $candidate->years_experience ?? 0 }} {{ Str::plural('year', $candidate->years_experience ?? 0) }}</dd></div>
                <div><dt class="text-slate-600">Region</dt><dd class="font-medium text-fiji-900">{{ $candidate->region ?: 'Not provided' }}</dd></div>
                @if ($candidate->email)<div><dt class="text-slate-600">Email</dt><dd class="break-all font-medium"><a href="mailto:{{ $candidate->email }}" class="link">{{ $candidate->email }}</a></dd></div>@endif
                @if ($candidate->phone)<div><dt class="text-slate-600">Phone</dt><dd class="font-medium text-fiji-900">{{ $candidate->phone }}</dd></div>@endif
            </dl>
            <div class="mt-6 border-t border-slate-100 pt-4">
                <h2 class="text-sm font-semibold text-fiji-900">Academic credential</h2>
                @if ($candidate->degree_certificate_path && $candidate->is_verified)
                    <a href="{{ route('credentials.document', $candidate) }}" target="_blank" rel="noopener" class="btn-secondary mt-3 w-full">View verified credential</a>
                @else
                    <p class="mt-2 text-sm text-slate-600">Not yet verified by an administrator. Unverified documents are visible only if this candidate applies to one of your listings.</p>
                @endif
            </div>
        </aside>
    </div>
</x-app-layout>
