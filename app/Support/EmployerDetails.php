<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Business details and proof of registration an employer must supply before
 * an administrator can approve the account (FR2.1 / M5 FR2). Shared by the
 * public registration form, the "Register as employer" form and the profile.
 */
class EmployerDetails
{
    /** @param bool $whenEmployerRole only require the fields when the form's role is employer */
    public static function rules(bool $documentRequired, bool $whenEmployerRole = false): array
    {
        $required = $whenEmployerRole ? 'required_if:role,employer' : 'required';

        return [
            'company_name' => [$required, 'nullable', 'string', 'max:255'],
            'industry_sector' => [$required, 'nullable', 'string', 'max:100'],
            'business_registration_number' => [$required, 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/ ]{3,100}$/'],
            'company_address' => [$required, 'nullable', 'string', 'min:5', 'max:255'],
            'company_website' => ['nullable', 'url:http,https', 'max:255'],
            'company_description' => [$required, 'nullable', 'string', 'min:30', 'max:2000'],
            'business_document' => [$documentRequired ? $required : 'nullable', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public static function messages(): array
    {
        return [
            'business_registration_number.required' => 'Enter your business registration number (from the Registrar of Companies).',
            'business_registration_number.required_if' => 'Enter your business registration number (from the Registrar of Companies).',
            'business_registration_number.regex' => 'The registration number can contain letters, numbers, spaces, dashes and slashes only.',
            'company_description.min' => 'Describe your business in at least 30 characters so the administrator can review it.',
            'business_document.required' => 'Upload your business registration certificate for the administrator to verify.',
            'business_document.required_if' => 'Upload your business registration certificate for the administrator to verify.',
            'business_document.mimes' => 'The business registration certificate must be a PDF, JPG or PNG file.',
            'business_document.max' => 'The business registration certificate must be 5 MB or smaller.',
        ];
    }

    /**
     * Copies validated business fields onto the user and stores a new
     * certificate privately. Returns true when a new document was uploaded.
     */
    public static function apply(User $user, Request $request, array $validated): bool
    {
        $user->fill([
            'company_name' => $validated['company_name'] ?? $user->company_name,
            'industry_sector' => $validated['industry_sector'] ?? $user->industry_sector,
            'business_registration_number' => $validated['business_registration_number'] ?? $user->business_registration_number,
            'company_address' => $validated['company_address'] ?? $user->company_address,
            'company_website' => $validated['company_website'] ?? null,
            'company_description' => $validated['company_description'] ?? $user->company_description,
        ]);

        if (! $request->hasFile('business_document')) {
            return false;
        }
        if ($user->business_document_path) {
            Storage::disk('local')->delete($user->business_document_path);
        }
        $user->business_document_path = $request->file('business_document')->store('business-documents', 'local');
        $user->business_document_name = $request->file('business_document')->getClientOriginalName();

        return true;
    }
}
