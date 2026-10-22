<x-app-layout title="Help">
@php($showAdminHelp = auth()->user()?->isAdmin() ?? false)
<x-slot name="header"><p class="eyebrow">Help and FAQ</p><h1 class="page-title">A little guidance goes a long way.</h1><p class="page-intro">Find answers for your next step, from creating a profile to reviewing an application.</p></x-slot>
<div class="grid items-start gap-6 lg:grid-cols-4">
<aside class="card lg:sticky lg:top-6"><h3 class="font-semibold">How can we help?</h3><nav class="mt-4 space-y-1" aria-label="Help topics">@foreach(['account' => 'Accounts & profiles', 'jobs' => 'Jobs & applications', 'career' => 'Career Hub & mentors', 'employers' => 'For employers', 'admin' => 'For administrators'] as $id => $title)@continue($id === 'admin' && ! $showAdminHelp)<a class="menu-link" href="#{{ $id }}">{{ $title }}</a>@endforeach</nav><p class="mt-5 text-xs leading-6 text-slate-500">Use the relevant form's error message to correct a problem. Never send your password to another user.</p></aside>
<div class="space-y-6 lg:col-span-3 prose-help">
@foreach([
'account' => ['Accounts & profiles', [
'Can I register with a phone number?' => 'Yes. Use an email address, a phone number or both. Enter a seven-digit Fiji number, or include the country code for an international number. Phone-only accounts receive in-app notifications. This does not perform SMS ownership verification.',
'How do I complete my jobseeker profile?' => 'Open Your profile from the account menu. Add contact details, region, qualification, experience and skills. Select skills from the grouped list or type your own and press Enter. Upload your academic credential before saving a complete profile.',
'Which credential files are supported?' => 'PDF, JPG and PNG files up to 5 MB. Credentials and resumes are stored privately and are available only through authorized account access.',
'What does my credential status mean?' => 'Pending means an administrator still needs to review your upload. Verified means it has been approved. Rejected or Changes requested includes a reason in your profile. Upload a corrected document to return it for review.',
'How can I reset my password?' => 'Use Forgot password if your account has an email address and the site mail service is configured. Phone-only accounts cannot use email password recovery. Contact the project administrator for assistance; SMS password reset is not provided.',
'Why can I not delete an account with records?' => 'Deleting it could remove applications or mentorship history needed by other people. Accounts with shared records are protected. Accounts without shared records can be permanently deleted after entering their current password. The last administrator cannot be deleted.'
]],
'jobs' => ['Jobs & applications', [
'How do skill matches work?' => 'The score is the percentage of required job skills found in your saved profile, ignoring case and surrounding spaces. A newly approved, open vacancy triggers one notification if it shares at least one skill with your profile. Each vacancy page lists which required skills you have and which are missing. A score is guidance, not a hiring decision.',
'How do I apply?' => 'Open an approved, available vacancy and attach a resume in PDF, DOC or DOCX format, up to 5 MB. You may add a cover letter. Each account can apply to a vacancy once.',
'Where can I track an application?' => 'Open Applications. Stages are Submitted, Under Review, Shortlisted, Interview Scheduled, Rejected and Hired. Each application shows a progress bar and a dated status history. You receive email and in-app notifications when the employer changes your status or schedules an interview.',
'What happens when a listing closes?' => 'New applications stop. Existing applications and outcomes remain available, including hired outcomes. Archived vacancies remain accessible to their applicants and authorized staff.'
]],
'career' => ['Career Hub & mentorship', [
'Does the chatbot remember conversations?' => 'Yes. Each chat is saved with timestamps. Choose New chat to start a fresh conversation, and pick any earlier chat from the list to continue it. You can delete chats you no longer need. AI guidance should be checked for accuracy.',
'What if the AI service is offline?' => 'The assistant says it is offline and labels its general guidance. Building a CV and booking a mentor still work without the AI model.',
'How do I create a CV?' => 'Complete your profile (including About me), then choose Build my CV in the Career Hub. Anything you type in the message box first is added as experience. Review the CV in the chat and use Print or save as PDF. You can then ask the assistant to improve the wording. It never invents qualifications.',
'How do mentorship sessions work?' => 'Choose a mentor who is accepting requests, enter a topic and a Fiji-time appointment. Sessions are reserved for one hour. Overlapping pending/confirmed requests are rejected. The mentor confirms the request; either participant can cancel before completion.',
'When can I leave feedback?' => 'Once the scheduled time has passed and the session is confirmed or completed, its jobseeker can submit one rating and feedback. Cancelled, pending and future sessions cannot receive feedback.',
'How do I become a mentor?' => 'Choose Mentor when registering and provide your expertise, industry background and bio. Jobseekers see your average rating once sessions are reviewed. Use Your profile to update them or pause new requests. Existing sessions stay in your history when you pause availability.'
]],
'employers' => ['For employers', [
'When can I access employer functions?' => 'Register as an employer with company and industry details. An administrator must approve the account before you can post vacancies, search candidates or manage applications.',
'How do I publish a vacancy?' => 'Choose My listings → Post a Job. Account details fill automatically. Add a title, duties, requirements, location, positions and salary information. You may attach a PDF/DOC/DOCX description. It appears publicly only after administrator approval.',
'How do I correct a vacancy?' => 'Use Edit in My listings. Corrections are sent back for review. If an administrator requests changes, their comments appear on the listing. Address them and resubmit.',
'How do I finish recruitment?' => 'Record candidate outcomes and schedule interviews from the vacancy applications page. After recording at least one hired candidate, you may explicitly confirm Complete recruitment. Remaining undecided applicants become Rejected and receive notifications; hired outcomes remain unchanged.',
'Can I find candidates without an application?' => 'Approved employers can search jobseeker profiles by skills, qualification, region and experience, and filter to verified candidates. Verified credentials can be viewed from a profile; unverified documents and resumes are visible only for applications to your own listings.'
]],
'admin' => ['For administrators', [
'What should I review before approval?' => 'Review the full requester/company and vacancy details, attachments, salary and positions. Approve, reject with a reason or request specific changes. Employer approval and vacancy approval are separate decisions.',
'How do I review credentials?' => 'Open Credential review, inspect the document and select Approve, Reject or Request more information. Explain rejection or missing information. The account holder receives the decision and comments.',
'How do role changes work?' => 'Open User access, find the account and choose a new role. Give a reason and confirm with your administrator password. Role-specific records prevent unsafe changes, and at least one administrator must remain. Assigning Employer does not bypass employer approval.',
'Where are operating instructions?' => 'This Help page explains the main account and application workflows. Administrators can run php artisan backup:run for encrypted backups and php artisan backup:restore-check with a snapshot identifier to verify recovery in an isolated database.'
]]
] as $id => [$title, $questions])
@continue($id === 'admin' && ! $showAdminHelp)
<section id="{{ $id }}" class="card scroll-mt-24"><h3 class="mt-2 text-2xl font-semibold">{{ $title }}</h3><div class="mt-4">@foreach($questions as $question => $answer)<details><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>@endforeach</div></section>
@endforeach
</div></div>
</x-app-layout>
