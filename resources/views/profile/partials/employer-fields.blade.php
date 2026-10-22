{{--
    Business details an administrator reviews before approving an employer.
    $user: existing account (profile page) or null. $alpineRequired: only
    mark fields required while the registration role select is "employer".
--}}
@php
    $user ??= null;
    $req = ($alpineRequired ?? false) ? ':required="role === \'employer\'"' : 'required';
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="company_name" class="label">Company or business name</label>
        <input id="company_name" name="company_name" {!! $req !!} maxlength="255" autocomplete="organization" value="{{ old('company_name', $user?->company_name) }}" class="input">
        <x-field-error name="company_name" />
    </div>
    <div>
        <label for="industry_sector" class="label">Industry sector</label>
        <input id="industry_sector" name="industry_sector" {!! $req !!} maxlength="100" list="industry-options" value="{{ old('industry_sector', $user?->industry_sector) }}" class="input" placeholder="e.g. Tourism & Hospitality…">
        <datalist id="industry-options">@foreach (['Tourism & Hospitality', 'Information Technology', 'Finance & Banking', 'Retail', 'Construction', 'Agriculture', 'Healthcare', 'Education', 'Manufacturing', 'Transport & Logistics', 'Government & Public Sector'] as $industry)<option value="{{ $industry }}">@endforeach</datalist>
        <x-field-error name="industry_sector" />
    </div>
    <div>
        <label for="business_registration_number" class="label">Business registration number <x-tip align="start">The number on your certificate from the Registrar of Companies. Letters, numbers, spaces, dashes and slashes only.</x-tip></label>
        <input id="business_registration_number" name="business_registration_number" {!! $req !!} maxlength="100" spellcheck="false" value="{{ old('business_registration_number', $user?->business_registration_number) }}" class="input" placeholder="e.g. BN-2024-001234…">
        <x-field-error name="business_registration_number" />
    </div>
    <div>
        <label for="company_website" class="label">Website <span class="font-normal text-slate-600">(optional)</span></label>
        <input id="company_website" type="url" name="company_website" maxlength="255" inputmode="url" spellcheck="false" value="{{ old('company_website', $user?->company_website) }}" class="input" placeholder="https://…">
        <x-field-error name="company_website" />
    </div>
    <div class="sm:col-span-2">
        <label for="company_address" class="label">Business address</label>
        <input id="company_address" name="company_address" {!! $req !!} maxlength="255" autocomplete="street-address" value="{{ old('company_address', $user?->company_address) }}" class="input" placeholder="Street, town and division…">
        <x-field-error name="company_address" />
    </div>
    <div class="sm:col-span-2">
        <label for="company_description" class="label">About the business</label>
        <textarea id="company_description" name="company_description" {!! $req !!} minlength="30" maxlength="2000" rows="3" class="input" placeholder="What the business does, how many staff you employ and the roles you hire for…">{{ old('company_description', $user?->company_description) }}</textarea>
        <x-field-error name="company_description" />
    </div>
    <div class="sm:col-span-2">
        <label for="business_document" class="label">Business registration certificate <x-tip align="start">A scan or photo of your current registration certificate. Administrators use it to confirm your business is real before approving your account.</x-tip></label>
        @if ($user?->business_document_path)
            <p class="mt-1 text-sm text-slate-600">On file: <a href="{{ route('employers.document', $user) }}" target="_blank" rel="noopener" class="link">{{ $user->business_document_name ?? 'certificate' }}</a>. Upload a new file only to replace it.</p>
        @endif
        <input id="business_document" type="file" name="business_document" accept=".pdf,.jpg,.jpeg,.png" {!! $user?->business_document_path ? '' : $req !!} class="input" aria-describedby="business-document-hint">
        <p id="business-document-hint" class="hint">PDF, JPG or PNG, up to 5&nbsp;MB. Stored privately and seen only by administrators.</p>
        <x-field-error name="business_document" />
    </div>
</div>
