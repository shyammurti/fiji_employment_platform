{{-- Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653). --}}
<x-app-layout title="Credential review" :breadcrumbs="['dashboard', ['Credential review']]" help="faq-how-do-i-review-credentials">
<x-slot name="header"><h1 class="page-title">Credential review</h1><p class="page-intro">Review the submitted document, record a clear decision and explain what happens next.</p></x-slot>
<div class="mb-6 flex flex-wrap gap-2">@foreach(['' => 'All submissions', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'changes_requested' => 'More information'] as $value => $label)<a href="{{ route('admin.verifications.index', ['status' => $value]) }}" class="{{ $filter === $value ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>@endforeach</div>
<div class="space-y-5">@forelse($users as $account)
<section class="card">
    <div class="flex flex-wrap items-center justify-between gap-4"><div><h3 class="text-lg font-semibold">{{ $account->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $account->email ?: $account->phone }} · {{ $account->qualification_level }}</p></div><span class="status-pill {{ $account->is_verified ? 'bg-success-100 text-success-700' : 'bg-amber-100 text-amber-800' }}">{{ str($account->credential_status)->replace('_', ' ')->title() }}</span></div>
    <div class="mt-5 flex flex-wrap items-center gap-4"><a href="{{ route('credentials.document', $account) }}" target="_blank" class="btn-secondary">Open credential ↗</a>@if($account->credential_notes)<p class="text-sm text-slate-600">Last comment: {{ $account->credential_notes }}</p>@endif</div>
    <form method="POST" action="{{ route('admin.verifications.toggle', $account) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3" x-data="{ decision: 'verified' }">@csrf @method('PATCH')
        <div><label for="decision-{{ $account->id }}" class="label">Decision <x-tip align="start">Approve shows a Verified badge to employers. Reject or Request more information needs a comment, which the jobseeker sees with your decision.</x-tip></label><select id="decision-{{ $account->id }}" name="decision" x-model="decision" class="input" required><option value="verified">Approve credential</option><option value="rejected">Reject credential</option><option value="changes_requested">Request more information</option></select></div>
        <div class="sm:col-span-2"><label for="notes-{{ $account->id }}" class="label">Comments sent to the jobseeker</label><textarea id="notes-{{ $account->id }}" name="credential_notes" class="input" maxlength="2000" :required="decision !== 'verified'" placeholder="Explain missing information or the reason for rejection."></textarea></div>
        <button class="btn-primary sm:col-span-3">Save decision and notify</button>
    </form>
</section>
@empty<div class="card text-center"><h3 class="font-semibold">No submissions in this queue.</h3><p class="mt-2 text-sm text-slate-500">New credential uploads will appear here for review.</p></div>@endforelse</div><div class="mt-6">{{ $users->links() }}</div>
</x-app-layout>
