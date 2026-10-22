<?php

namespace Database\Seeders;

use App\Models\ApplicationStatusChange;
use App\Models\ChatConversation;
use App\Models\ChatbotMessage;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use App\Notifications\NewQualifiedMatch;
use App\Notifications\PlatformAlert;
use App\Services\SkillMatchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds a realistic, "already in use" platform: about 100 accounts, 45
 * vacancies in every review state, ~250 applications with full status
 * history, mentorship sessions, chats and notifications.
 *
 * All people and companies are fictional. Every address uses the reserved
 * example.com domain, so no seeded account can ever receive real email.
 * Every password is: password
 */
class DatabaseSeeder extends Seeder
{
    private string $password;
    private string $pdf;
    private SkillMatchService $matcher;
    /** @var array<string, true> taken "who|Y-m-d H" appointment slots */
    private array $busy = [];
    private int $phoneSeq = 1000;
    /** @var array<int, string> jobseeker id => career track */
    private array $trackOf = [];
    /** @var array<int, string> listing id => seeded lifecycle state */
    private array $stateOf = [];

    private const REGIONS = ['Central Division', 'Western Division', 'Northern Division', 'Eastern Division'];

    /** Career tracks drive which skills people have and which jobs they apply for. */
    private const TRACKS = [
        'hospitality' => [
            'skills' => ['Customer Service', 'Front Office Operations', 'Food & Beverage Service', 'Housekeeping', 'Communication', 'Conflict Resolution', 'Teamwork', 'Tour Guiding'],
            'about' => ['Hospitality graduate who enjoys making guests feel welcome.', 'Experienced in busy resort front offices and guest relations.', 'Friendly team player with a strong customer service record in tourism.'],
        ],
        'business' => [
            'skills' => ['Microsoft Excel', 'Microsoft Word', 'Data Entry', 'Bookkeeping', 'Accounting', 'Sales', 'Report Writing', 'Time Management', 'Communication'],
            'about' => ['Accounting graduate looking for an accounts or administration role.', 'Organised office administrator with strong spreadsheet skills.', 'Sales and customer account professional who likes clear targets.'],
        ],
        'technology' => [
            'skills' => ['PHP', 'Laravel', 'JavaScript', 'MySQL', 'Networking', 'IT Support', 'Graphic Design', 'Problem Solving'],
            'about' => ['Software developer building web applications with Laravel and JavaScript.', 'IT support technician who enjoys fixing networks and helping users.', 'Recent computing graduate keen to join a development team.'],
        ],
        'trades' => [
            'skills' => ['Carpentry', 'Electrical Work', 'Plumbing', 'Driving', 'Health & Safety', 'Agriculture', 'Teamwork'],
            'about' => ['Qualified tradesperson with site and maintenance experience.', 'Reliable driver and labourer with a clean safety record.', 'Farm supervisor experienced in crop and equipment management.'],
        ],
    ];

    public function run(): void
    {
        mt_srand(14);
        $this->password = Hash::make('password');
        $this->pdf = $this->samplePdf();
        $this->matcher = app(SkillMatchService::class);

        // The database is fresh, so remove files that belonged to the old rows.
        foreach (['credentials', 'resumes', 'job-documents', 'business-documents'] as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }

        $admins = $this->seedAdmins();
        $mentors = $this->seedMentors();
        $employers = $this->seedEmployers();
        $jobseekers = $this->seedJobseekers();
        $listings = $this->seedListings($employers['approved']);
        $this->seedPendingEmployerListing($employers['pending']);
        $applications = $this->seedApplications($listings, $jobseekers);
        $this->seedMentorship($mentors, $jobseekers);
        $this->seedChats($jobseekers);
        $this->seedNotifications($admins, $employers, $jobseekers, $applications);
    }

    // ------------------------------------------------------------------ people

    private function makeUser(array $attributes, ?Carbon $joined = null): User
    {
        $joined ??= now()->subDays(mt_rand(20, 200));
        $user = new User(array_merge([
            'email_verified_at' => $joined,
            'employer_approval_status' => 'approved',
            'is_verified' => false,
        ], $attributes));
        $user->forceFill(['password' => $this->password, 'created_at' => $joined, 'updated_at' => $joined])->save();

        return $user;
    }

    private function phone(): string
    {
        $phone = '+6797'.str_pad((string) $this->phoneSeq++, 6, '0', STR_PAD_LEFT);

        return $phone;
    }

    private function seedAdmins(): array
    {
        return [
            $this->makeUser(['name' => 'Platform Admin', 'email' => 'admin@example.com', 'role' => 'admin'], now()->subDays(240)),
            $this->makeUser(['name' => 'Ana Tuisawau', 'email' => 'ana.tuisawau@example.com', 'role' => 'admin'], now()->subDays(180)),
        ];
    }

    private function seedMentors(): array
    {
        $mentors = [
            ['Losalini Vosanibola', 'mentor1@example.com', 'Tourism & Hospitality Management', 'Hotels and resorts', '12 years leading guest operations at Nadi resorts. I help hospitality graduates prepare for front office and supervisor interviews.'],
            ['Jone Rokotuiveikau', 'mentor2@example.com', 'Software Engineering & IT', 'Technology startups', 'Full-stack engineer and startup founder based in Suva, mentoring early-career developers on portfolios, code reviews and technical interviews.'],
            ['Priya Lata', 'priya.lata@example.com', 'Accounting & Finance', 'Banking and finance', 'Chartered accountant with 15 years in audit and banking. I mentor graduates moving into accounts, audit and finance roles.'],
            ['Semi Koroi', 'semi.koroi@example.com', 'Construction & Trades', 'Civil construction', 'Site manager who started as an apprentice carpenter. Happy to talk about trade certificates, safety and moving into supervision.'],
            ['Mere Naivalu', 'mere.naivalu@example.com', 'Human Resources & Recruitment', 'Recruitment', 'HR manager who has reviewed thousands of applications. I help with CVs, cover letters and interview confidence.'],
            ['Arvind Prasad', 'arvind.prasad@example.com', 'Sales & Marketing', 'Retail', 'Regional sales manager for a retail group. I coach new sales staff on targets, customer relationships and promotion pathways.'],
            ['Tevita Tuilau', 'tevita.tuilau@example.com', 'Agriculture & Agribusiness', 'Farming and exports', 'Agribusiness consultant working with Northern Division farmers on exports and farm management careers.'],
            ['Shalini Devi', 'shalini.devi@example.com', 'Healthcare Careers', 'Public health', 'Registered nurse and clinical educator. I mentor students applying for nursing and allied health roles.'],
            ['Isikeli Waqa', 'isikeli.waqa@example.com', 'Networking & IT Support', 'Telecommunications', 'Network engineer supporting outer-island connectivity. I mentor IT support technicians preparing for certifications.'],
            ['Rachel Chand', 'rachel.chand@example.com', 'Public Sector Careers', 'Government', 'Former senior civil servant who helps graduates understand public sector applications and selection criteria.'],
        ];

        return collect($mentors)->map(fn ($m, $i) => $this->makeUser([
            'name' => $m[0], 'email' => $m[1], 'role' => 'mentor', 'phone' => $phone = $this->phone(), 'login_phone' => $phone,
            'mentor_expertise' => $m[2], 'mentor_industry' => $m[3], 'mentor_bio' => $m[4], 'mentor_available' => $i !== 9,
            'region' => self::REGIONS[$i % 3],
        ], now()->subDays(150 - $i * 7)))->all();
    }

    private function seedEmployers(): array
    {
        $companies = [
            ['Kelera Naidu', 'employer@example.com', 'South Pacific Resorts Ltd', 'Tourism & Hospitality', 'Queens Road, Nadi, Western Division', 'Beachfront resort group operating three properties on the Coral Coast and in Nadi, employing around 400 staff.'],
            ['Viliame Ravuvu', 'viliame.ravuvu@example.com', 'Coral Coast Hospitality Group', 'Tourism & Hospitality', 'Korotogo, Sigatoka, Western Division', 'Family-owned group of boutique hotels and restaurants along the Coral Coast.'],
            ['Asha Kumar', 'asha.kumar@example.com', 'Viti Digital Solutions', 'Information Technology', 'Victoria Parade, Suva, Central Division', 'Software house building booking, payroll and e-commerce systems for Pacific businesses.'],
            ['Josefa Nawaqa', 'josefa.nawaqa@example.com', 'Rewa Construction Ltd', 'Construction', 'Nausori Industrial Park, Central Division', 'Civil and building contractor delivering roads, schools and commercial buildings since 1998.'],
            ['Neha Singh', 'neha.singh@example.com', 'South Seas Finance', 'Finance & Banking', 'Thomson Street, Suva, Central Division', 'Licensed credit institution offering business and personal loans across Fiji.'],
            ['Sakiusa Tora', 'sakiusa.tora@example.com', 'Bula Retail Group', 'Retail', 'Vitogo Parade, Lautoka, Western Division', 'Supermarket and hardware chain with 11 stores in Viti Levu and Vanua Levu.'],
            ['Kamal Reddy', 'kamal.reddy@example.com', 'Labasa Agri Supplies', 'Agriculture', 'Nasekula Road, Labasa, Northern Division', 'Supplier of seed, fertiliser and farm equipment to Northern Division growers.'],
            ['Litia Bose', 'litia.bose@example.com', 'Island Connect Telecom Services', 'Information Technology', 'Ratu Mara Road, Suva, Central Division', 'Installation and support contractor for fibre and wireless networks, including outer islands.'],
            ['Rohit Lal', 'rohit.lal@example.com', 'Namaka Freight & Customs', 'Transport & Logistics', 'Namaka, Nadi, Western Division', 'Freight forwarding and customs brokerage serving importers at Nadi airport and Lautoka port.'],
            ['Salote Vuki', 'salote.vuki@example.com', 'Mana Learning Academy', 'Education', 'Waimanu Road, Suva, Central Division', 'Private tutoring and vocational training provider for school leavers and adults.'],
            ['Daniel Whippy', 'daniel.whippy@example.com', 'Savusavu Marine Services', 'Transport & Logistics', 'Main Street, Savusavu, Northern Division', 'Marine engineering, boat maintenance and inter-island charter services.'],
            ['Sunita Prakash', 'sunita.prakash@example.com', 'Suva Central Medical Centre', 'Healthcare', 'Amy Street, Suva, Central Division', 'Private medical centre with general practice, pharmacy and diagnostic services.'],
            ['Inoke Delai', 'inoke.delai@example.com', 'Kadavu Eco Tours', 'Tourism & Hospitality', 'Vunisea, Kadavu, Eastern Division', 'Community-owned eco-tourism operator offering diving, kayaking and village stays.'],
            ['Avinesh Narayan', 'avinesh.narayan@example.com', 'Ba Auto Works', 'Manufacturing', 'Kings Road, Ba, Western Division', 'Vehicle servicing, panel beating and spare parts supplier for Western Division fleets.'],
            ['Merewalesi Cama', 'merewalesi.cama@example.com', 'Sigatoka Valley Farms', 'Agriculture', 'Sigatoka Valley Road, Western Division', 'Vegetable and fruit grower supplying hotels and supermarkets across Viti Levu.'],
            ['Pita Rabuka', 'pita.rabuka@example.com', 'Pacific Harbour Logistics', 'Transport & Logistics', 'Queens Road, Pacific Harbour, Central Division', 'Warehousing and last-mile delivery company for online retailers and pharmacies.'],
        ];
        $approved = [];
        foreach ($companies as $i => $c) {
            $approved[] = $this->employer($c, 'approved', $i);
        }
        $pending = [
            $this->employer(['Sanjay Chand', 'pending-employer@example.com', 'Suva Tech Solutions', 'Information Technology', 'Gordon Street, Suva, Central Division', 'Small IT consultancy offering website development and computer repairs to Suva businesses.'], 'pending', 20, now()->subDays(2)),
            $this->employer(['Vani Seru', 'vani.seru@example.com', 'Lautoka Clean Energy', 'Manufacturing', 'Drasa Avenue, Lautoka, Western Division', 'Installer of solar systems for homes and small businesses in the Western Division.'], 'pending', 21, now()->subDays(1)),
            $this->employer(['Roneel Kumar', 'roneel.kumar@example.com', 'Tavua Mining Support Services', 'Construction', 'Tavua Town, Western Division', 'Equipment hire and maintenance for mining and quarry operations.'], 'pending', 22, now()->subHours(6)),
        ];
        $rejected = $this->employer(['Tom Fisher', 'tom.fisher@example.com', 'Quick Cash Jobs Fiji', 'Finance & Banking', 'PO Box only', 'We find jobs.'], 'rejected', 23, now()->subDays(12));
        $rejected->update(['admin_notes' => 'The registration certificate did not match the business name and no physical address was provided. Upload a current certificate and street address to resubmit.']);

        return ['approved' => $approved, 'pending' => $pending];
    }

    private function employer(array $c, string $status, int $i, ?Carbon $joined = null): User
    {
        $path = 'business-documents/registration-'.Str::slug($c[2]).'.pdf';
        Storage::disk('local')->put($path, $this->pdf);

        return $this->makeUser([
            'name' => $c[0], 'email' => $c[1], 'role' => 'employer', 'employer_approval_status' => $status,
            'phone' => $phone = $this->phone(), 'login_phone' => $phone,
            'company_name' => $c[2], 'industry_sector' => $c[3], 'company_address' => $c[4], 'company_description' => $c[5],
            'business_registration_number' => 'BN'.(2010 + $i % 14).'-'.str_pad((string) (1200 + $i * 37), 6, '0', STR_PAD_LEFT),
            'company_website' => $status === 'approved' && $i % 3 === 0 ? 'https://'.Str::slug($c[2]).'.example.com' : null,
            'business_document_path' => $path, 'business_document_name' => 'Certificate of Registration - '.$c[2].'.pdf',
            'region' => collect(self::REGIONS)->first(fn ($r) => str_contains($c[4], $r)) ?? 'Central Division',
        ], $joined ?? now()->subDays(200 - $i * 9));
    }

    private function seedJobseekers(): array
    {
        $first = ['Mereoni', 'Peni', 'Ilisapeci', 'Ratu', 'Akanisi', 'Sereana', 'Josaia', 'Vilisi', 'Apisalome', 'Losana', 'Kalesi', 'Tomasi', 'Unaisi', 'Waisea', 'Adi', 'Ana', 'Rohini', 'Shivneel', 'Kavita', 'Ravinesh', 'Anjali', 'Sachin', 'Pooja', 'Vikash', 'Shreya', 'Nikhil', 'Sneha', 'Ajay', 'Deepika', 'Rajneel', 'Mohammed', 'Aisha', 'Ilaisa', 'Torika', 'Eroni', 'Makereta', 'Samuela', 'Taina', 'Jiten', 'Monika'];
        $last = ['Tuilagi', 'Vakaloloma', 'Ravai', 'Nabou', 'Qalo', 'Tikoduadua', 'Lewaravu', 'Bulivou', 'Naqasima', 'Vunibola', 'Ratumaitavuki', 'Sharma', 'Prasad', 'Kumar', 'Naidu', 'Reddy', 'Singh', 'Ali', 'Khan', 'Chand', 'Lal', 'Devi', 'Goundar', 'Raj', 'Tabua', 'Matai'];
        $quals = ['Certificate', 'Diploma', 'Diploma', 'Bachelor', 'Bachelor', 'Bachelor', 'Postgraduate'];
        $tracks = array_keys(self::TRACKS);
        $people = [];

        // Demo accounts from README_SETUP.md keep their logins.
        $people[] = $this->jobseeker('Mereoni Tuilagi', 'jobseeker@example.com', 'hospitality', 'Central Division', 'Bachelor', 3, 'pending',
            ['Customer Service', 'Front Office Operations', 'Microsoft Excel', 'Communication', 'Conflict Resolution'], 'Hospitality graduate with three years on a busy hotel front desk in Suva. I enjoy solving guest problems and training new staff.', now()->subDays(95));
        $people[] = $this->jobseeker('Peni Vakaloloma', 'jobseeker2@example.com', 'technology', 'Western Division', 'Diploma', 1, 'verified',
            ['PHP', 'Laravel', 'JavaScript', 'MySQL'], 'Diploma holder in IT from Lautoka. I build small web tools with Laravel and want my first full-time developer role.', now()->subDays(80));

        $used = ['Mereoni Tuilagi' => true, 'Peni Vakaloloma' => true];
        while (count($people) < 72) {
            $name = $first[mt_rand(0, count($first) - 1)].' '.$last[mt_rand(0, count($last) - 1)];
            if (isset($used[$name])) { continue; }
            $used[$name] = true;
            $track = $tracks[mt_rand(0, 3)];
            $pool = self::TRACKS[$track]['skills'];
            shuffle($pool);
            $skills = array_slice($pool, 0, mt_rand(3, 6));
            if (mt_rand(1, 4) === 1) { $skills[] = ['Tok Pisin', 'First Aid', 'Fijian Language', 'Hindi Language', 'Forklift Licence', 'Social Media Marketing'][mt_rand(0, 5)]; }
            $status = $this->weighted(['verified' => 55, 'pending' => 22, 'changes_requested' => 8, 'rejected' => 5, 'not_submitted' => 10]);
            $email = mt_rand(1, 10) === 1 ? null : Str::lower(Str::ascii(str_replace(' ', '.', $name))).'@example.com';
            $about = self::TRACKS[$track]['about'][mt_rand(0, 2)];
            $people[] = $this->jobseeker($name, $email, $track, self::REGIONS[$this->weightedIndex([45, 35, 15, 5])], $quals[mt_rand(0, 6)], mt_rand(0, 12), $status, $skills, mt_rand(1, 5) > 1 ? $about : null, now()->subDays(mt_rand(5, 180)));
        }

        return $people;
    }

    private function jobseeker(string $name, ?string $email, string $track, string $region, string $qual, int $years, string $credential, array $skills, ?string $about, Carbon $joined): User
    {
        $phone = $this->phone();
        $user = $this->makeUser([
            'name' => $name, 'email' => $email, 'role' => 'jobseeker', 'phone' => $phone, 'login_phone' => $phone,
            'region' => $region, 'qualification_level' => $qual, 'years_experience' => $years, 'skills' => array_values(array_unique($skills)), 'about_me' => $about,
            'credential_status' => $credential, 'is_verified' => $credential === 'verified',
            'credential_notes' => match ($credential) {
                'changes_requested' => 'The scan is cut off at the bottom. Please upload the full certificate including the seal.',
                'rejected' => 'The institution name on the document could not be confirmed. Upload an official transcript or certificate.',
                default => null,
            },
        ], $joined);
        $this->trackOf[$user->id] = $track;
        if ($credential !== 'not_submitted') {
            $path = 'credentials/credential-'.$user->id.'.pdf';
            Storage::disk('local')->put($path, $this->pdf);
            $user->forceFill(['degree_certificate_path' => $path])->saveQuietly();
        }

        return $user;
    }

    // ---------------------------------------------------------------- listings

    private function seedListings(array $employers): array
    {
        $templates = [
            'Tourism & Hospitality' => [
                ['Front Office Supervisor', ['Customer Service', 'Front Office Operations', 'Communication', 'Conflict Resolution'], 'Diploma in Hospitality or equivalent', 'FJD 12,000 - 15,000 per year'],
                ['Reservations Officer', ['Customer Service', 'Microsoft Excel', 'Data Entry', 'Communication'], 'Certificate in Tourism or equivalent', 'FJD 9,500 - 11,500 per year'],
                ['Food & Beverage Attendant', ['Food & Beverage Service', 'Customer Service', 'Teamwork'], 'Certificate or equivalent experience', 'FJD 4.50 - 5.50 per hour'],
                ['Housekeeping Team Leader', ['Housekeeping', 'Teamwork', 'Time Management', 'Leadership'], 'Certificate or equivalent experience', 'FJD 10,000 - 12,000 per year'],
                ['Tour Guide', ['Tour Guiding', 'Communication', 'Customer Service', 'First Aid'], 'Certificate in Tourism or equivalent', 'FJD 8,500 - 10,500 per year'],
            ],
            'Information Technology' => [
                ['Junior Web Developer', ['PHP', 'Laravel', 'JavaScript', 'MySQL'], 'Diploma in Computing or equivalent', 'FJD 18,000 - 24,000 per year'],
                ['IT Support Technician', ['IT Support', 'Networking', 'Problem Solving', 'Customer Service'], 'Certificate IV in IT or equivalent', 'FJD 14,000 - 17,000 per year'],
                ['Network Field Engineer', ['Networking', 'IT Support', 'Driving', 'Health & Safety'], 'Diploma in Electronics or Networking', 'FJD 20,000 - 26,000 per year'],
                ['UI Designer', ['Graphic Design', 'JavaScript', 'Communication'], 'Bachelor degree in Design or Computing', 'FJD 22,000 - 28,000 per year'],
            ],
            'Construction' => [
                ['Site Carpenter', ['Carpentry', 'Health & Safety', 'Teamwork'], 'Trade certificate in Carpentry', 'FJD 6.00 - 8.00 per hour'],
                ['Electrician', ['Electrical Work', 'Health & Safety', 'Problem Solving'], 'Licensed electrician certificate', 'FJD 8.00 - 11.00 per hour'],
                ['Project Administrator', ['Microsoft Excel', 'Report Writing', 'Time Management', 'Communication'], 'Diploma in Business or equivalent', 'FJD 15,000 - 18,000 per year'],
            ],
            'Finance & Banking' => [
                ['Accounts Officer', ['Accounting', 'Bookkeeping', 'Microsoft Excel', 'Report Writing'], 'Bachelor of Commerce (Accounting)', 'FJD 16,000 - 20,000 per year'],
                ['Loans Officer', ['Sales', 'Customer Service', 'Microsoft Excel', 'Communication'], 'Bachelor degree in Business or Finance', 'FJD 17,000 - 21,000 per year'],
                ['Graduate Credit Analyst', ['Accounting', 'Microsoft Excel', 'Report Writing', 'Problem Solving'], 'Bachelor of Commerce', 'FJD 19,000 - 23,000 per year'],
            ],
            'Retail' => [
                ['Store Supervisor', ['Leadership', 'Customer Service', 'Sales', 'Time Management'], 'Certificate or equivalent experience', 'FJD 11,000 - 14,000 per year'],
                ['Sales Assistant', ['Sales', 'Customer Service', 'Teamwork'], 'Year 12 or equivalent', 'FJD 4.00 - 4.80 per hour'],
                ['Inventory Clerk', ['Data Entry', 'Microsoft Excel', 'Time Management'], 'Certificate in Business or equivalent', 'FJD 9,000 - 11,000 per year'],
            ],
            'Agriculture' => [
                ['Farm Supervisor', ['Agriculture', 'Leadership', 'Health & Safety', 'Driving'], 'Diploma in Agriculture', 'FJD 13,000 - 16,000 per year'],
                ['Agricultural Sales Representative', ['Sales', 'Agriculture', 'Driving', 'Communication'], 'Certificate in Agriculture or Sales', 'FJD 12,000 - 15,000 per year'],
            ],
            'Transport & Logistics' => [
                ['Delivery Driver', ['Driving', 'Customer Service', 'Time Management'], 'Class 4 driver licence', 'FJD 4.50 - 5.50 per hour'],
                ['Customs Clerk', ['Data Entry', 'Microsoft Excel', 'Report Writing', 'Communication'], 'Certificate in Customs Brokerage or Business', 'FJD 12,000 - 15,000 per year'],
                ['Warehouse Coordinator', ['Time Management', 'Microsoft Excel', 'Leadership', 'Health & Safety'], 'Certificate or equivalent experience', 'FJD 13,000 - 16,000 per year'],
                ['Marine Engineer Assistant', ['Electrical Work', 'Problem Solving', 'Health & Safety'], 'Trade certificate in Marine Engineering', 'FJD 7.00 - 9.00 per hour'],
            ],
            'Education' => [
                ['Mathematics Tutor', ['Communication', 'Time Management', 'Report Writing'], 'Bachelor degree with Mathematics major', 'FJD 12.00 - 15.00 per hour'],
                ['Training Coordinator', ['Microsoft Excel', 'Communication', 'Leadership', 'Report Writing'], 'Bachelor degree in Education or HR', 'FJD 18,000 - 22,000 per year'],
            ],
            'Healthcare' => [
                ['Medical Receptionist', ['Customer Service', 'Data Entry', 'Communication', 'Microsoft Word'], 'Certificate in Office Administration', 'FJD 9,500 - 11,500 per year'],
                ['Pharmacy Assistant', ['Customer Service', 'Data Entry', 'Teamwork', 'First Aid'], 'Certificate III in Pharmacy or equivalent', 'FJD 10,000 - 12,500 per year'],
            ],
            'Manufacturing' => [
                ['Automotive Technician', ['Electrical Work', 'Problem Solving', 'Health & Safety'], 'Trade certificate in Automotive Engineering', 'FJD 6.50 - 9.00 per hour'],
                ['Parts Interpreter', ['Customer Service', 'Data Entry', 'Sales'], 'Certificate or equivalent experience', 'FJD 10,000 - 12,000 per year'],
            ],
        ];
        $types = ['Full-time', 'Full-time', 'Full-time', 'Part-time', 'Contract'];
        $listings = [];

        foreach ($employers as $e => $employer) {
            $options = $templates[$employer->industry_sector] ?? $templates['Retail'];
            shuffle($options);
            $count = $e === 0 ? 4 : mt_rand(2, 3);
            foreach (array_slice($options, 0, $count) as $n => [$title, $skills, $degree, $salary]) {
                $created = now()->subDays(mt_rand(3, 75))->subHours(mt_rand(0, 20));
                $state = $this->weighted(['open' => 70, 'pending' => 9, 'changes_requested' => 4, 'rejected' => 3, 'closed' => 7, 'archived' => 3, 'completed' => 4]);
                if ($e === 0 && $n === 0) { $state = 'open'; }
                $location = trim(explode(',', $employer->company_address)[1] ?? 'Suva');
                $path = 'job-documents/position-description-'.$employer->id.'-'.Str::slug($title).'.pdf';
                Storage::disk('local')->put($path, $this->pdf);

                $listing = new JobListing([
                    'user_id' => $employer->id,
                    'title' => $title,
                    'company' => $employer->company_name,
                    'location' => $location,
                    'region' => $employer->region,
                    'industry_sector' => $employer->industry_sector,
                    'type' => $types[mt_rand(0, 4)],
                    'salary_range' => $salary,
                    'description' => "{$employer->company_name} is looking for a {$title} to join our team in {$location}. ".$this->roleSummary($title).' We offer on-the-job training, a supportive team and opportunities to grow within the business.',
                    'responsibilities' => implode("\n", $this->duties($title, $skills)),
                    'required_degree' => $degree,
                    'required_skills' => $skills,
                    'vacancy_count' => mt_rand(1, 10) > 8 ? mt_rand(2, 5) : 1,
                    'closing_date' => in_array($state, ['closed', 'completed', 'archived'], true) ? $created->copy()->addDays(21) : now()->addDays(mt_rand(6, 60)),
                    'approval_status' => match ($state) { 'pending' => 'pending_approval', 'changes_requested' => 'changes_requested', 'rejected' => 'rejected', default => 'approved' },
                    'is_active' => in_array($state, ['open', 'pending', 'changes_requested', 'rejected'], true),
                    'is_archived' => $state === 'archived',
                    'admin_notes' => match ($state) {
                        'changes_requested' => 'Please state the weekly hours and whether accommodation or transport is provided.',
                        'rejected' => 'The salary shown is below the national minimum wage for this role. Correct the pay rate and resubmit.',
                        default => null,
                    },
                    'closure_reason' => match ($state) {
                        'closed' => 'The employer is no longer accepting new applications. Existing applications can still be reviewed.',
                        'archived' => 'The employer has withdrawn this listing.',
                        'completed' => 'Recruitment is complete. All applicants have been given an outcome.',
                        default => null,
                    },
                    'document_path' => $path,
                    'document_name' => $title.' - Position Description.pdf',
                ]);
                $listing->forceFill(['created_at' => $created, 'updated_at' => $created, 'recruitment_completed_at' => $state === 'completed' ? $created->copy()->addDays(30) : null])->save();
                $this->stateOf[$listing->id] = $state;
                $listings[] = $listing;
            }
        }

        return $listings;
    }

    private function seedPendingEmployerListing(array $pendingEmployers): void
    {
        // A listing from a not-yet-approved employer shows why both approvals matter.
        $employer = $pendingEmployers[0];
        $path = 'job-documents/position-description-'.$employer->id.'-computer-technician.pdf';
        Storage::disk('local')->put($path, $this->pdf);
        JobListing::create([
            'user_id' => $employer->id, 'title' => 'Computer Repair Technician', 'company' => $employer->company_name, 'location' => 'Suva',
            'region' => 'Central Division', 'industry_sector' => $employer->industry_sector, 'type' => 'Full-time', 'salary_range' => 'FJD 11,000 - 13,000 per year',
            'description' => 'Diagnose and repair desktop and laptop computers for small business clients in Suva, and keep repair records up to date.',
            'responsibilities' => "Diagnose hardware and software faults\nRepair and upgrade computers\nRecord jobs in the repair system\nExplain repairs to customers",
            'required_degree' => 'Certificate IV in IT or equivalent', 'required_skills' => ['IT Support', 'Problem Solving', 'Customer Service'],
            'vacancy_count' => 1, 'closing_date' => now()->addDays(30), 'approval_status' => 'pending_approval',
            'document_path' => $path, 'document_name' => 'Computer Repair Technician - Position Description.pdf',
        ]);
    }

    private function roleSummary(string $title): string
    {
        return match (true) {
            str_contains($title, 'Front Office'), str_contains($title, 'Reservations') => 'You will be the first point of contact for guests, manage bookings and make sure every stay starts well.',
            str_contains($title, 'Developer'), str_contains($title, 'Designer') => 'You will build and maintain web applications used by customers across the Pacific.',
            str_contains($title, 'Support'), str_contains($title, 'Network') => 'You will keep our clients connected, solving hardware, software and network issues on site and remotely.',
            str_contains($title, 'Account'), str_contains($title, 'Credit'), str_contains($title, 'Loans') => 'You will work with customers and our finance team to keep accurate records and sound lending decisions.',
            str_contains($title, 'Driver'), str_contains($title, 'Warehouse'), str_contains($title, 'Customs') => 'You will help goods move reliably from the port and warehouse to our customers.',
            default => 'You will play a key part in our day-to-day operations and customer service.',
        };
    }

    private function duties(string $title, array $skills): array
    {
        $duties = ['Work safely and follow company procedures', 'Communicate clearly with customers and colleagues'];
        foreach ($skills as $skill) {
            $duties[] = match ($skill) {
                'Customer Service' => 'Provide friendly, professional service to every customer',
                'Front Office Operations' => 'Manage check-in, check-out and guest requests at the front desk',
                'Microsoft Excel' => 'Prepare and update spreadsheets and daily reports',
                'Data Entry' => 'Enter records accurately into the company system',
                'PHP', 'Laravel' => 'Build and maintain features in our Laravel applications',
                'JavaScript' => 'Develop interactive pages and fix front-end issues',
                'Networking' => 'Install, test and troubleshoot network equipment',
                'IT Support' => 'Respond to support tickets and resolve user issues',
                'Accounting', 'Bookkeeping' => 'Reconcile accounts and process payments and receipts',
                'Sales' => 'Meet monthly sales targets and grow customer relationships',
                'Driving' => 'Make scheduled deliveries and keep the vehicle log up to date',
                'Leadership' => 'Supervise and roster a small team',
                'Health & Safety' => 'Carry out safety checks and report hazards',
                default => null,
            };
        }

        return array_values(array_unique(array_filter(array_slice($duties, 0, 6))));
    }

    // ------------------------------------------------------------ applications

    private function seedApplications(array $listings, array $jobseekers): array
    {
        $path = ['Submitted', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Hired'];
        $created = [];
        $open = array_filter($listings, fn ($l) => $l->approval_status === 'approved');

        foreach ($open as $listing) {
            $listingTrack = $this->trackFor($listing->required_skills);
            $candidates = array_values(array_filter($jobseekers, fn ($j) => $this->trackOf[$j->id] === $listingTrack));
            $others = array_values(array_filter($jobseekers, fn ($j) => $this->trackOf[$j->id] !== $listingTrack));
            shuffle($candidates);
            shuffle($others);
            $pool = array_merge(array_slice($candidates, 0, mt_rand(3, 8)), array_slice($others, 0, mt_rand(0, 2)));
            if ($listing->title === 'Front Office Supervisor' && $listing->user_id === $listings[0]->user_id) {
                array_unshift($pool, $jobseekers[0]);
            }
            if ($listing->title === 'Junior Web Developer') {
                array_unshift($pool, $jobseekers[1]);
            }
            $hired = 0;

            foreach (collect($pool)->unique('id') as $seeker) {
                $applied = $listing->created_at->copy()->addDays(mt_rand(1, 10))->addHours(mt_rand(1, 10));
                if ($applied->isFuture()) { $applied = now()->subHours(mt_rand(2, 30)); }
                $age = $applied->diffInDays(now());

                // Final stage depends on how old the application is and the listing state.
                $final = match (true) {
                    $this->stateOf[$listing->id] === 'completed' => $hired < $listing->vacancy_count && mt_rand(1, 3) === 1 ? 'Hired' : 'Rejected',
                    $age < 3 => 'Submitted',
                    default => $this->weighted(['Submitted' => 12, 'Under Review' => 22, 'Shortlisted' => 14, 'Interview Scheduled' => 14, 'Rejected' => 26, 'Hired' => $hired < $listing->vacancy_count ? 6 : 0]),
                };
                if ($final === 'Hired') { $hired++; }
                $reached = $final === 'Rejected' ? mt_rand(0, 3) : array_search($final, $path, true);

                $application = new JobApplication([
                    'job_listing_id' => $listing->id, 'user_id' => $seeker->id,
                    'cover_letter' => mt_rand(1, 3) > 1 ? "I am applying for the {$listing->title} role. My experience in ".implode(' and ', array_slice($seeker->skillsList(), 0, 2))." makes me a strong fit, and I would welcome the chance to discuss it." : null,
                    'resume_path' => $resume = 'resumes/resume-'.$seeker->id.'.pdf',
                    'status' => $final,
                    'match_percentage' => $this->matcher->calculate($seeker, $listing),
                    'employer_notes' => in_array($final, ['Shortlisted', 'Interview Scheduled', 'Hired'], true) ? 'Strong application. Good references.' : null,
                ]);
                Storage::disk('local')->put($resume, $this->pdf);

                // Build the dated status history (FR4.5) without model events.
                $steps = array_slice($path, 0, $reached + 1);
                if ($final === 'Rejected') { $steps[] = 'Rejected'; }
                $time = $applied->copy();
                $history = [];
                $interviewAt = null;
                foreach ($steps as $i => $step) {
                    if ($i > 0) {
                        $time = $time->copy()->addDays(mt_rand(1, 6))->addHours(mt_rand(1, 6));
                        if ($time->isFuture()) { $time = now()->subMinutes(mt_rand(30, 600)); }
                    }
                    if ($step === 'Interview Scheduled') {
                        $interviewAt = $final === 'Interview Scheduled' ? $this->freeSlot('seeker'.$seeker->id, mt_rand(2, 21), [9, 10, 11, 13, 14, 15]) : $time->copy()->addDays(3)->setTime(10, 0);
                        $interviewAt ??= now()->addDays(mt_rand(22, 40))->setTime(mt_rand(9, 15), 0);
                    }
                    $history[] = ['from_status' => $i === 0 ? null : $steps[$i - 1], 'to_status' => $step, 'interview_at' => $step === 'Interview Scheduled' ? $interviewAt : null, 'changed_by' => $i === 0 ? $seeker->id : $listing->user_id, 'created_at' => $time, 'updated_at' => $time];
                }
                $application->interview_at = $final === 'Interview Scheduled' ? $interviewAt : null;
                $application->forceFill(['created_at' => $applied, 'updated_at' => $time]);
                JobApplication::withoutEvents(fn () => $application->save());
                foreach ($history as $row) {
                    ApplicationStatusChange::insert($row + ['job_application_id' => $application->id]);
                }
                $application->setRelation('jobListing', $listing);
                $created[] = $application;
            }
        }

        return $created;
    }

    private function trackFor(array $skills): string
    {
        $scores = collect(self::TRACKS)->map(fn ($t) => count(array_intersect($t['skills'], $skills)));

        return $scores->sortDesc()->keys()->first();
    }

    // -------------------------------------------------------------- mentorship

    private function seedMentorship(array $mentors, array $jobseekers): void
    {
        $topics = ['Preparing for a front office interview', 'Reviewing my CV before applying', 'Moving from casual work into a supervisor role', 'Choosing between further study and work', 'Practising a technical interview', 'Writing a cover letter for a government job', 'Getting my first IT support job', 'Starting an apprenticeship', 'Negotiating my first salary', 'Building a portfolio as a junior developer'];
        $feedback = [5 => ['Very practical advice. I felt much more confident in my interview.', 'Excellent session, clear steps to improve my CV.'], 4 => ['Helpful and friendly. Would have liked more time on salary questions.', 'Good tips on answering behaviour questions.'], 3 => ['Useful overview, but some of it was too general for my field.']];
        $seekers = $jobseekers;

        for ($i = 0; $i < 85; $i++) {
            $mentor = $mentors[$this->weightedIndex([16, 14, 12, 8, 12, 8, 6, 8, 8, 8])];
            $seeker = $i < 4 ? $jobseekers[0] : $seekers[mt_rand(0, count($seekers) - 1)];
            $past = $i % 3 !== 0;
            $dayOffset = $past ? -mt_rand(1, 120) : mt_rand(1, 30);
            $time = $this->freeSlot(['mentor'.$mentor->id, 'seeker'.$seeker->id], $dayOffset, [9, 10, 11, 13, 14, 15, 16]);
            if (! $time) { continue; }
            $status = $past ? $this->weighted(['completed' => 75, 'cancelled' => 15, 'confirmed' => 10]) : $this->weighted(['confirmed' => 55, 'pending' => 35, 'cancelled' => 10]);
            $rating = $past && in_array($status, ['completed', 'confirmed'], true) && mt_rand(1, 10) > 2 ? $this->weighted([5 => 55, 4 => 35, 3 => 10]) : null;
            $session = new MentorshipSession([
                'mentor_id' => $mentor->id, 'mentee_id' => $seeker->id, 'topic' => $topics[mt_rand(0, count($topics) - 1)],
                'scheduled_at' => $time, 'status' => $rating ? 'completed' : $status, 'escalated_from_chatbot' => mt_rand(1, 3) === 1,
                'rating' => $rating, 'feedback' => $rating ? $feedback[$rating][mt_rand(0, count($feedback[$rating]) - 1)] : null,
            ]);
            $requested = $time->copy()->subDays(mt_rand(2, 10));
            $session->forceFill(['created_at' => $requested->isFuture() ? now()->subHours(3) : $requested, 'updated_at' => $past ? $time->copy()->addHours(2) : now()->subHours(mt_rand(1, 48))])->save();
        }
    }

    /**
     * Next free one-hour slot for every party, so seeded appointments obey
     * the same no-double-booking rule the app enforces.
     *
     * @param string|string[] $who
     */
    private function freeSlot(string|array $who, int $dayOffset, array $hours): ?Carbon
    {
        $who = (array) $who;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $day = now('Pacific/Fiji')->startOfDay()->addDays($dayOffset + intdiv($attempt, count($hours)) * ($dayOffset < 0 ? -1 : 1));
            if ($day->isWeekend()) { $day->addDays($dayOffset < 0 ? -2 : 2); }
            $local = $day->copy()->setTime($hours[($attempt + mt_rand(0, count($hours) - 1)) % count($hours)], 0);
            $key = $local->format('Y-m-d H');
            if (collect($who)->contains(fn ($w) => isset($this->busy[$w.'|'.$key]))) { continue; }
            foreach ($who as $w) { $this->busy[$w.'|'.$key] = true; }

            return $local->utc();
        }

        return null;
    }

    // -------------------------------------------------------------------- chats

    private function seedChats(array $jobseekers): void
    {
        $conversations = [
            [$jobseekers[0], 'Interview tips for front office supervisor', 6, [
                ['user', 'I have an interview for a front office supervisor role next week. What questions should I expect?', 'interview_practice'],
                ['bot', "Congratulations on getting the interview! For a **front office supervisor** role, expect questions in three areas:\n\n1. **Guest situations**: \"Tell me about a time you handled an unhappy guest.\"\n2. **Leading a team**: \"How would you roster staff for a fully booked weekend?\"\n3. **Systems and accuracy**: \"How do you check that billing is correct at check-out?\"\n\nAnswer with the **STAR** method: the situation, your task, the action you took and the result.\n\nWould you like to practise one now? Here is the first question: *Tell me about a time you resolved a complaint at the front desk.*", 'interview_practice'],
                ['user', 'A guest was charged twice for a room. I apologised, checked the folio, refunded the duplicate charge and gave them a late check-out.', 'interview_practice'],
                ['bot', "That is a solid answer. You showed **ownership** and a **clear fix**.\n\nTo make it stronger:\n- Start with the situation in one sentence (how busy it was, how upset the guest was).\n- Mention how you **prevented it happening again**, for example checking the night audit report.\n- Finish with the result: did the guest leave a good review or return?\n\nTry the next question: *How would you handle two staff calling in sick on a busy morning?*", 'interview_practice'],
            ]],
            [$jobseekers[0], 'Improving my CV summary', 20, [
                ['user', 'Can you help me improve my CV summary? It says: hard working person looking for a job in hotels.', 'cv_assistance'],
                ['bot', "Your summary should tell an employer **what you offer** in two or three lines. Based on your profile, try:\n\n> Hospitality graduate with three years of front desk experience in a busy Suva hotel. Skilled in guest relations, conflict resolution and Microsoft Excel reporting. Looking to grow into a front office supervisor role.\n\nWhy it works:\n- It names your **experience** and **skills**\n- It uses facts instead of general words like *hard working*\n- It states the **role you want**", 'cv_assistance'],
            ]],
            [$jobseekers[1], 'Preparing for a junior developer interview', 3, [
                ['user', 'What should I prepare for a junior Laravel developer interview?', 'interview_practice'],
                ['bot', "Good choice to prepare early. Focus on these:\n\n- **Laravel basics**: routes, controllers, Eloquent relationships, validation and migrations\n- **A small project you can explain**: what it does, one problem you solved and what you would improve\n- **Git**: branching and resolving a merge conflict\n- **SQL**: a simple JOIN and why indexes matter\n\nExpect a short practical task. Talk through your thinking out loud, because employers care about **how** you solve problems.\n\nShall I ask you a practice question about Eloquent relationships?", 'interview_practice'],
            ]],
        ];
        foreach (array_slice($jobseekers, 2, 8) as $i => $seeker) {
            $conversations[] = [$seeker, ['Which jobs suit my skills?', 'Help with a cover letter', 'How do I talk about a gap in my CV?', 'Talk to a mentor'][$i % 4], mt_rand(1, 40), [
                ['user', ['Which jobs suit my skills?', 'Can you help me start a cover letter?', 'How do I explain a two year gap in my CV?', 'I would like to talk to a mentor about my career.'][$i % 4], ['general_enquiry', 'cv_assistance', 'cv_assistance', 'mentor_escalation'][$i % 4]],
                ['bot', ["Based on your profile, roles that match your skills include **".implode('**, **', array_slice($seeker->skillsList(), 0, 2))."** positions. Check **Browse jobs** for vacancies showing a high skill match, and add any missing skills to your profile.", "Start with **why this role** and **why this company** in two sentences, then give one example that proves your strongest skill. Keep it under 250 words.", "Be brief and honest. Say what you did during the gap (caring for family, study, volunteering) and **what you learned**, then move on to why you are ready now.", "A human mentor can help with this. Use **Book a mentor** below the chat to request a one-hour session."][$i % 4], ['general_enquiry', 'cv_assistance', 'cv_assistance', 'mentor_escalation'][$i % 4]],
            ]];
        }

        foreach ($conversations as [$seeker, $title, $daysAgo, $messages]) {
            $start = now()->subDays($daysAgo)->setTime(mt_rand(8, 20), mt_rand(0, 59));
            $conversation = ChatConversation::forceCreate(['user_id' => $seeker->id, 'title' => $title, 'last_message_at' => $start->copy()->addMinutes(count($messages) * 2), 'created_at' => $start, 'updated_at' => $start]);
            foreach ($messages as $n => [$sender, $text, $intent]) {
                $at = $start->copy()->addMinutes($n * 2);
                ChatbotMessage::forceCreate(['user_id' => $seeker->id, 'chat_conversation_id' => $conversation->id, 'sender' => $sender, 'message' => $text, 'intent' => $intent, 'created_at' => $at, 'updated_at' => $at]);
            }
        }
    }

    // ------------------------------------------------------------ notifications

    private function seedNotifications(array $admins, array $employers, array $jobseekers, array $applications): void
    {
        // Written straight to the table: seeding must never send email.
        $add = function (User $user, string $class, array $data, Carbon $at, bool $read = false) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => $class, 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
                'data' => json_encode($data), 'read_at' => $read ? $at->copy()->addHours(2) : null, 'created_at' => $at, 'updated_at' => $at,
            ]);
        };

        foreach ($applications as $application) {
            if (! in_array($application->user_id, [$jobseekers[0]->id, $jobseekers[1]->id], true) || $application->status === 'Submitted') {
                continue;
            }
            $add($jobseekers[0]->id === $application->user_id ? $jobseekers[0] : $jobseekers[1], ApplicationStatusUpdated::class, [
                'type' => 'application_status_updated', 'job_application_id' => $application->id, 'job_title' => $application->jobListing->title, 'company' => $application->jobListing->company,
                'status' => $application->status, 'interview_at' => $application->interview_at?->toIso8601String(),
                'message' => "Your application for \"{$application->jobListing->title}\" is now: {$application->status}", 'action_url' => route('applications.mine'),
            ], $application->updated_at);
        }
        $match = JobListing::publiclyVisible()->where('title', 'Reservations Officer')->first();
        if ($match) {
            $score = $this->matcher->calculate($jobseekers[0], $match);
            $add($jobseekers[0], NewQualifiedMatch::class, ['type' => 'new_qualified_match', 'action_url' => route('jobs.show', $match), 'job_listing_id' => $match->id, 'job_title' => $match->title, 'company' => $match->company, 'match_percentage' => $score, 'message' => "You're a {$score}% skill match for \"{$match->title}\" at {$match->company}"], now()->subDays(1));
        }
        $add($jobseekers[0], PlatformAlert::class, ['type' => 'platform_alert', 'message' => 'Losalini Vosanibola confirmed your mentorship session.', 'action_text' => 'View Career Hub', 'action_url' => route('career.hub')], now()->subDays(3), true);

        foreach (JobApplication::with('jobseeker', 'jobListing')->whereHas('jobListing', fn ($q) => $q->where('user_id', $employers['approved'][0]->id))->latest()->take(6)->get() as $application) {
            $add($employers['approved'][0], PlatformAlert::class, ['type' => 'platform_alert', 'message' => "{$application->jobseeker->name} applied for {$application->jobListing->title}.", 'action_text' => 'Review applications', 'action_url' => route('employer.jobs.applications', $application->jobListing)], $application->created_at, $application->created_at->lt(now()->subDays(7)));
        }

        foreach ($employers['pending'] as $employer) {
            $add($admins[0], PlatformAlert::class, ['type' => 'platform_alert', 'message' => "{$employer->company_name} registered as an employer and submitted business documents for review.", 'action_text' => 'Review employers', 'action_url' => route('admin.employers.pending')], $employer->created_at);
        }
        foreach (JobListing::pendingApproval()->latest()->take(4)->get() as $listing) {
            $add($admins[0], PlatformAlert::class, ['type' => 'platform_alert', 'message' => "{$listing->company} submitted \"{$listing->title}\" for review.", 'action_text' => 'Review listing', 'action_url' => route('admin.jobs.review', $listing)], $listing->created_at);
        }
    }

    // ----------------------------------------------------------------- helpers

    private function weighted(array $weights): string|int
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    private function weightedIndex(array $weights): int
    {
        return (int) $this->weighted($weights);
    }

    private function samplePdf(): string
    {
        $stream = "BT /F1 16 Tf 50 760 Td (PROJECT DEMONSTRATION DOCUMENT) Tj 0 -30 Td /F1 11 Tf (Fictional sample only. Not a real qualification, CV, certificate or vacancy.) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
