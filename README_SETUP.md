# Fiji Employment & Skill-Matching Platform

IS314 Computing Project, Group 14 (Semester 2, 2026).

## Team and contributions

| Student name | Student ID | Contribution |
|---|---|---|
| Shyam Murti (Leader) | S11229913 | Coding for AI Chatbot and Mentorship module, including career guidance, chatbot responses and mentorship functions. |
| Maimuna Nisha | S11229653 | Coding for Admin Governance and Verification module, including admin dashboard, employer approvals and credential verification. |
| Sameet Singh | S11231364 | Coding for Student Profile module, including profile editing, document management and verification status features. |
| Hritish Pawar | S11219177 | Coding for Employer Portal, including vacancy editing, application review and interview scheduling features. |
| Ammara Dean | S11220213 | Coding for Application Tracking and Skill Matching module, including application submission, status tracking and skill matching. |

## Prerequisites

- PHP 8.3+
- Composer
- Node.js compatible with the locked Vite version and npm (tested with Node 24.17)
- MySQL 8+ (or MariaDB)
- Ollama (with llama3.2 model downloaded)

## 1. Install dependencies

```bash
composer install
npm install
```

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your MySQL credentials. Create the database:

```sql
CREATE DATABASE fiji_employment_platform;
```

## 3. Migrate & seed demo data

```bash
php artisan migrate --seed 
php artisan storage:link
```

## 4. Build frontend assets & run

```bash
npm run build     
php artisan serve
```

## Test Cases
| Name | Email | Role | Password |
|---|---|---|---|
| Jone Rokotuiveikau | mentor2@example.com | mentor | `password` |
| Kelera Naidu | employer@example.com | employer | `password` |
| Losalini Vosanibola | mentor1@example.com | mentor | `password` |
| Mereoni Tuilagi | jobseeker@example.com | jobseeker | `password` |
| Peni Vakaloloma | jobseeker2@example.com | jobseeker | `password` |
| Platform Admin | admin@example.com | admin | `Vinaka-Mango-7681-asgfpb` |
| Ana Tuisawau | ana.tuisawau@example.com | admin | `Vinaka-Mango-7681-asgfpb` |
| Sanjay Chand | pending-employer@example.com | employer | `password` |

The seeder also creates a realistic dataset (all fictional, all `@example.com`, so nobody can be emailed by accident): 72 jobseekers, 20 employers (16 approved, 3 waiting for approval, 1 rejected), 10 mentors, 2 admins, 39 vacancies in every review state, about 185 applications with full status history, 85 mentorship sessions, saved chats and notifications. Every non-admin account uses the password `password`.

The two admin passwords were changed (19 Oct 2026) because the site can be shared publicly with `npm run live`. Re-running `php artisan migrate:fresh --seed` resets **every** account, including the admins, back to `password`.

Note: `php artisan migrate:fresh --seed` **deletes all existing data** before re-creating it.

Visit `http://localhost:8000`.

## What's implemented per module

- **M1 (Jobseeker Profile & Verification):** registration, credential
  upload (PDF/JPG/PNG) with verification reset on re-upload, predefined +
  custom skill tagging via an Alpine tag input, green "Verified" badge once
  an admin approves the credential.
- **M2 (Employer Portal):** employer registration -> `pending` ->
  admin approval; job posting -> `pending_approval` queue; candidate search
  by skill/qualification/region/experience; application workflow controls
  (status, interview time, notes); edit/close/archive listings.
- **M3 (AI Chatbot & Mentorship Engine):** existing career-hub, CV assistance,
  interview practice, mentorship booking and feedback flows. The working copy
  has local Ollama and optional OpenAI integration paths. These integrations
  were preserved during the lecturer-review changes.
- **M4 (Application Tracking & Skill Matching Engine):** applying computes
  `match_percentage` immediately via `SkillMatchService::calculate()`;
  any overlapping skill triggers a vacancy notification at approval, deduplicated per candidate/listing; the full pipeline
  (Submitted -> ... -> Hired) is editable by employers and dispatches email
  + in-app database notifications on every change; `/my-applications` gives
  the jobseeker the saved application outcomes, with retained archived listings.
- **M5 (Admin Governance & Verification Dashboard):** `/admin/dashboard`
  totals, employer approval queue, job moderation queue, credential
  verification panel, and a request-info/flag system that writes to
  `admin_notes` on the relevant user or listing.

## Active AI configuration

The authenticated Career Hub uses handleMessage() for intent detection, server-owned history, model responses and saved timestamps. Ollama is the default provider, so to run (test) the AI chatbot you need to install Ollama and llama3.2 so the configration matches and the chatbot works while testing.

## Running the tests

The tests use an in-memory SQLite database. If `php artisan test` reports "could not find driver",
either uncomment `extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`, or run:

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit
```

See `FR_AUDIT.md` for the functional-requirement audit against the SRS and SAD.

## Email

Users receive a welcome email after registering, plus emails for application updates, approvals and mentorship sessions.
By default (`MAIL_MAILER=log`) emails are written to `storage/logs/laravel.log`. To send real email, edit `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-address@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS="your-address@gmail.com"
```

then run `php artisan config:clear`. Gmail requires an App Password (Google Account, Security, 2-Step Verification, App passwords).

- `APP_URL` must be the address you open the site on (for example `http://127.0.0.1:8000` with `php artisan serve`), because links in emails are built from it.
- With `MAIL_MAILER=smtp`, new accounts must click the emailed verification link before using the platform; the welcome email follows verification. With `MAIL_MAILER=log`, accounts are trusted automatically.
- Seeded demo accounts use `@example.com`, a reserved domain, so they are never emailed. Register with a real address to receive emails.
- If the mail server is unavailable, the action still succeeds and the error is logged.

## Career Hub assistant speed

Replies stream word by word. The page warms the model up when it opens and Ollama keeps it loaded for 30 minutes,
so only the first question after a long pause waits for the model to load. Ollama runs on the CPU unless a supported GPU
is available; for faster replies on a CPU you can use a smaller model:

```bash
ollama pull llama3.2:1b
```

and set `OLLAMA_MODEL=llama3.2:1b` in `.env`.

## Going live for testers (free)

The site can be shared over the internet from this PC with a free Cloudflare Tunnel (no account needed).

One-time setup:

```bash
winget install --id Cloudflare.cloudflared -e
```

Each time you want testers to use it:

1. Start **MySQL** in the XAMPP Control Panel and make sure **Ollama** is running.
2. In the project folder run:

```bash
npm run live
```

3. Share the `https://<random-words>.trycloudflare.com` address it prints. Keep the window open; press **Ctrl+C** to take the site offline.

Notes:
- The address changes every time you run `npm run live`, so send testers the new link each time. It can take up to a minute before a new address starts working.
- The live site runs with debug pages switched off and 4 PHP workers behind a small load balancer (`scripts/go-live.mjs`), so one tester waiting on a long chatbot reply does not freeze the site for others. Set `WORKERS=6` before the command for more.
- Links in emails sent during live testing point to the public address automatically.
- The two admin accounts no longer use the demo password. Demo jobseeker, employer and mentor accounts still use `password`; testers can also register their own accounts.
