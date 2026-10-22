# Functional Requirements Audit (Group 14)

Audit of `fiji_employment_platform_V2` against the SRS (section 4) and the SAD (FR-1 to FR-46).
Status key: **Met** = implemented and tested. **Fixed** = gap closed in this revision. **Note** = met, with a caveat to mention in your report.

## M1 Jobseeker Profile & Verification

| Req | Status | Evidence |
|---|---|---|
| FR1.1 Register with email or phone + password | Met | `RegisteredUserController@store`, phone normalisation in `App\Support\PhoneNumber` |
| FR1.2 Create / view / edit profile | Fixed | Added **About me** (SAD `JOBSEEKERS.about_me`), shown to employers and used by the CV Builder |
| FR1.3 Upload credential (PDF/image) | Met | `ProfileController@update`, private `local` disk |
| FR1.4 Predefined list **or** custom skills | Fixed | Was 8 skills hidden in a datalist, and Add allowed duplicates. Now 31 skills in 5 groups (`App\Support\SkillCatalog`) as toggle chips, plus custom entry with case-insensitive de-duplication |
| FR1.5 Verified badge after admin approval | Met | `badge-verified` on profile, candidate search and applications |
| FR1.6 Errors for invalid / incomplete input | Met | Inline `x-field-error` plus page summary; labels are now linked to every input |
| FR1.7 Reject bad credential formats and say which are allowed | Fixed | Custom message: "Credentials must be a PDF, JPG or PNG file…" |

## M2 Employer Portal

| Req | Status | Evidence |
|---|---|---|
| FR2.1 Register employer, admin approval | Met | Status `pending`, then `EnsureEmployerApproved` middleware |
| FR2.2 Create / submit vacancy for review | Met | `pending_approval` queue; edits re-queue |
| FR2.3 Search by skill, qualification, experience, location | Fixed | Added a **Verified only** filter, verified candidates sorted first, and skill suggestions |
| FR2.4 Browse profiles and **verified** credentials | Fixed (security) | Any approved employer could open **any** jobseeker's credential, including unverified ones. Now limited to verified credentials, or applicants to the employer's own listings (SRS 5.2, NFR-13) |
| FR2.5 Review applications, update status, schedule interviews | Met | Interview time is now shown only when the status is "Interview Scheduled" |

## M3 AI Chatbot & Mentorship (SRS REQ-1 to REQ-7, SAD FR-10 to FR-18)

| Req | Status | Evidence |
|---|---|---|
| REQ-1 Chat on Career Hub | Met | |
| REQ-2 Four intents | Fixed | Renamed to the SAD data-dictionary values `interview_practice`, `cv_assistance`, `mentor_escalation`, `general_enquiry`. "Human resources" no longer escalates to a mentor by mistake |
| REQ-3 Intent-relevant reply | Met | Ollama/OpenAI, with a labelled fallback |
| REQ-4 Store messages with timestamp and intent | Met | `chatbot_messages` |
| REQ-5 CV from profile skills + optional text | Fixed | Summary defaults to the profile's About me |
| REQ-6 Escalation tells user a mentor can be booked | Fixed | The UI ignored the `escalate` flag and `escalated_from_chatbot` was never set by any form. It now shows a **Book a mentor** banner and records the referral on the session |
| REQ-7 Empty input gives guidance, not an error | Fixed | Was a 422 validation error |
| FR-14 Mentor profile: expertise **and industry background** | Fixed | Added `mentor_industry` (register, profile, mentor cards) |
| FR-16/17 Booking and scheduling | Met | One-hour slots, overlap protection, Fiji time |
| FR-18 Ratings and feedback | Fixed (visibility) | Average rating and review count are now shown on mentor cards |

## M4 Application Tracking & Skill Matching

| Req | Status | Evidence |
|---|---|---|
| FR4.1 Apply to approved postings | Met | |
| FR4.2 Match notifications | Met + improved | Notified on approval. Dashboard "Recommended for you" was the latest 4 jobs; it is now **ranked by match**. Match % is shown on the job board, with matched and missing skills on each vacancy |
| FR4.3 Status stages | Met | Submitted, Under Review, Shortlisted, Interview Scheduled, Rejected, Hired |
| FR4.4 Notify on status change | Fixed | Notifications were also sent when the employer only edited private notes. They now go out only when the status or interview time changes |
| FR4.5 History **and status progress** | Fixed (main gap) | Only the current status was stored. New `application_status_changes` table, written by model events so every path is covered (including Complete recruitment). My Applications shows a stage stepper and a dated history |

## M5 Admin Governance

| Req | Status | Evidence |
|---|---|---|
| FR1 Dashboard: activity, user counts, metrics | Fixed | Added applications (total and this week), hires, mentorship sessions, new users this week, and a recent-activity feed |
| FR2 Employer approval | Met | |
| FR3 Vacancy approval before public listing | Met | |
| FR4 Verify / reject credentials | Met | |
| FR5 Request info + manage access | Met | Employer and listing request-info, credential "changes requested", and role changes behind a password with an audit table. Admin queues are now in the main navigation |

## Notes for your report (not changed)

* **Email verification is bypassed**: registration sets `email_verified_at = now()`. This is fine for a demo, but say so, or remove it to enforce verification.
* **SRS 2.4 says the chatbot is rule-based with no external AI**, but the build calls Ollama/OpenAI. Update the SRS wording, or describe the rule-based fallback as the default.
* **SRS 2.3 says PHP 8.5.9**; `composer.json` requires `^8.3` and the machine runs 8.3. Align the document.
* SAD `INTERVIEWS` (mode / meeting link) and a separate `SKILLS` table are modelled differently (interview fields on the application, skills as JSON). That is acceptable, but explain it as a design decision.

## Tests

`tests/Feature/FunctionalRequirementGapsTest.php` covers every **Fixed** item above. The full suite is 30 tests, all passing.
