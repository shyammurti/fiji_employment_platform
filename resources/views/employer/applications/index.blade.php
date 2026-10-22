<x-app-layout title="Applications">
    <x-slot name="header">
        <h1 class="page-title">All applications</h1><p class="page-intro">Every application across your listings, newest first.</p>
    </x-slot>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Job</th>
                    <th>Match</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($applications as $app)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $app->jobseeker->name }}</td>
                        <td class="text-slate-600">{{ $app->jobListing->title }}</td>
                        <td class="text-slate-600">{{ $app->match_percentage }}%</td>
                        <td><span class="status-pill {{ $app->statusColor() }}">{{ $app->status }}</span></td>
                        <td class="text-slate-500">{{ $app->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('employer.jobs.applications', $app->jobListing) }}" class="text-ocean-700 hover:underline">Manage<span class="sr-only"> application from {{ $app->jobseeker->name }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">No applications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $applications->links() }}</div>
</x-app-layout>
