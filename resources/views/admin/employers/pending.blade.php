{{-- Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653). --}}
<x-app-layout title="Employer approvals" :breadcrumbs="['dashboard', ['Employer approvals']]" help="faq-what-should-i-review-before-approval">
    <x-slot name="header">
        <h1 class="page-title">Employer approvals</h1>
        <p class="page-intro">Check each business's details and registration certificate, then approve, reject with a reason, or ask for more information.</p>
    </x-slot>

    <div class="space-y-5">
        @forelse ($employers as $employer)
            <article class="card" x-data="{ action: null }" aria-labelledby="employer-{{ $employer->id }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="employer-{{ $employer->id }}" class="text-lg font-semibold text-fiji-900">{{ $employer->company_name }}</h2>
                        <p class="text-sm text-slate-600">{{ $employer->industry_sector }}. Requested {{ $employer->updated_at->timezone('Pacific/Fiji')->format('d M Y') }}.</p>
                    </div>
                    <span class="status-pill {{ $employer->business_document_path ? 'bg-success-100 text-success-700' : 'bg-red-100 text-red-700' }}">{{ $employer->business_document_path ? 'Certificate supplied' : 'No certificate' }}</span>
                </div>

                <dl class="mt-5 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-slate-600">Registration number</dt><dd class="font-medium text-fiji-900">{{ $employer->business_registration_number ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-600">Contact person</dt><dd class="font-medium text-fiji-900">{{ $employer->name }}</dd></div>
                    <div><dt class="text-slate-600">Email / phone</dt><dd class="break-all font-medium text-fiji-900">{{ $employer->email ?: 'No email' }}<br>{{ $employer->phone ?: 'No phone' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-600">Address</dt><dd class="font-medium text-fiji-900">{{ $employer->company_address ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-600">Website</dt><dd class="font-medium">@if ($employer->company_website)<a href="{{ $employer->company_website }}" target="_blank" rel="noopener noreferrer" class="link break-all">{{ $employer->company_website }}</a>@else<span class="text-fiji-900">Not provided</span>@endif</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-slate-600">About the business</dt><dd class="mt-1 whitespace-pre-line text-slate-700">{{ $employer->company_description ?: 'Not provided' }}</dd></div>
                </dl>
                @if ($employer->admin_notes)
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Earlier note:</strong> {{ $employer->admin_notes }}</p>
                @endif

                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    @if ($employer->business_document_path)
                        <a href="{{ route('employers.document', $employer) }}" target="_blank" rel="noopener" class="btn-secondary">Open certificate</a>
                    @endif
                    <form method="POST" action="{{ route('admin.employers.approve', $employer) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-primary" @disabled(! $employer->business_document_path) @if(! $employer->business_document_path) title="A registration certificate is required before approval" @endif>Approve</button>
                    </form>
                    <button type="button" class="btn-secondary" @click="action = action === 'info' ? null : 'info'" :aria-expanded="action === 'info'">Request information</button>
                    <button type="button" class="btn-danger-soft" @click="action = action === 'reject' ? null : 'reject'" :aria-expanded="action === 'reject'">Reject</button>
                </div>

                <form x-show="action === 'info'" x-cloak method="POST" action="{{ route('admin.governance.requestInfo') }}" class="mt-4 space-y-2">
                    @csrf
                    <input type="hidden" name="target_type" value="user"><input type="hidden" name="target_id" value="{{ $employer->id }}">
                    <label for="info-{{ $employer->id }}" class="label">What is missing?</label>
                    <textarea id="info-{{ $employer->id }}" name="note" required minlength="10" maxlength="2000" rows="2" class="input" placeholder="e.g. The certificate is unreadable. Please upload a clearer scan…"></textarea>
                    <button type="submit" class="btn-primary">Send request</button>
                </form>
                <form x-show="action === 'reject'" x-cloak method="POST" action="{{ route('admin.employers.reject', $employer) }}" class="mt-4 space-y-2">
                    @csrf @method('PATCH')
                    <label for="reject-{{ $employer->id }}" class="label">Reason for rejection (sent to the employer)</label>
                    <textarea id="reject-{{ $employer->id }}" name="admin_notes" required minlength="10" maxlength="1000" rows="2" class="input"></textarea>
                    <button type="submit" class="btn-danger">Confirm rejection</button>
                </form>
            </article>
        @empty
            <div class="card py-12 text-center">
                <h2 class="text-lg font-semibold text-fiji-900">No employer requests waiting</h2>
                <p class="mt-2 text-sm text-slate-600">New requests appear here with their business documents.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $employers->links() }}</div>
</x-app-layout>
