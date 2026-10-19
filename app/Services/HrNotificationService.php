<?php

namespace App\Services;

use App\Models\User;
use App\Constants\Constants;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Resolves who is told about joiners and leavers, and collects the documents
 * that go with them.
 *
 * All addresses come from config/hr_notifications.php so recipients can be
 * changed without a deploy. Role-based recipients are resolved at send time,
 * so new staff in those roles are picked up automatically.
 */
class HrNotificationService
{
    /**
     * Recipients for the "new joiner approved" mail.
     *
     * @return array{to: string[], cc: string[]}
     */
    public static function onboardingRecipients(User $joiner): array
    {
        $cfg = config('hr_notifications.onboarding', []);

        $to = collect($cfg['to'] ?? []);
        $cc = collect($cfg['cc'] ?? []);

        // The joiner's reporting manager is the person who actually needs to
        // know a new report has landed, so they go on the To line.
        if (!empty($cfg['include_reporting_manager']) && $joiner->reporting_to_id) {
            $mgr = User::find($joiner->reporting_to_id);
            if ($mgr && filter_var($mgr->email, FILTER_VALIDATE_EMAIL)) {
                $to->push($mgr->email);
            }
        }

        return self::dedupe($to, $cc);
    }

    /** Where the ID documents are sent (accounts only). */
    public static function onboardingDocumentRecipients(): array
    {
        return collect(config('hr_notifications.onboarding.documents_to', []))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    /**
     * "Important Contacts" card data for any dashboard: the viewer's own
     * reporting manager (if any) plus HR — never the viewer themself.
     *
     * Shared across all 5 role dashboards (admin/hr/manager/finance/employee)
     * so every one shows the same thing rather than each maintaining its own
     * copy. HR contacts are looked up by email rather than hardcoded ids, so
     * name/phone/photo stay live from their own user record; if both named
     * HR addresses are gone, falls back to whoever currently holds the `hr`
     * role.
     *
     * Deliberately HR + manager only — an earlier version also hardcoded a
     * named IT contact here, which meant that person's own phone/email went
     * out to every viewer's dashboard by default rather than being something
     * they had to look up. Removed 2026-08-17.
     *
     * @return array<int, array{name: string, role: string, phone: ?string, email: string, bg: string, photo: ?string}>
     */
    public static function importantContactsFor(User $user): array
    {
        $contacts = [];

        if ($user->reporting_to_id && ($mgr = User::find($user->reporting_to_id))) {
            $contacts[] = [
                'name' => $mgr->getFullName(), 'role' => 'Reporting Manager',
                'phone' => $mgr->phone ?? null,
                'email' => $mgr->email, 'bg' => '0d9488',
                'photo' => $mgr->getProfilePicture() ?: null,
            ];
        }

        $keyContacts = [
            ['email' => 'suchita@rustx.com', 'role' => 'Human Resources', 'bg' => '6366f1'],
            ['email' => 'hr@rustx.com',      'role' => 'Human Resources', 'bg' => 'ec4899'],
        ];
        $already = collect($contacts)->pluck('email')->all();
        foreach ($keyContacts as $kc) {
            if (in_array($kc['email'], $already, true)) {
                continue; // already listed as their manager
            }
            $c = \App\Models\User::where('email', $kc['email'])
                ->where('status', \App\Enums\UserAccountStatus::ACTIVE)->first();
            if (!$c || $c->id === $user->id) {
                continue;
            }
            $contacts[] = [
                'name' => $c->getFullName(), 'role' => $kc['role'],
                'phone' => $c->phone ?? null, 'email' => $c->email, 'bg' => $kc['bg'],
                'photo' => $c->getProfilePicture() ?: null,
            ];
            $already[] = $c->email;
        }

        // Fall back to whoever holds the HR role if the named contacts are gone.
        if (!collect($contacts)->contains(fn($c) => $c['role'] === 'Human Resources')) {
            $hrContact = User::role('hr')->where('status', \App\Enums\UserAccountStatus::ACTIVE)->first();
            if ($hrContact && $hrContact->id !== $user->id) {
                $contacts[] = [
                    'name' => $hrContact->getFullName(), 'role' => 'Human Resources',
                    'phone' => $hrContact->phone ?? null,
                    'email' => $hrContact->email, 'bg' => '6366f1',
                    'photo' => $hrContact->getProfilePicture() ?: null,
                ];
            }
        }

        // IT Services — resolved by department, not a hardcoded person, so it
        // stays correct as the team changes. This tenant's department is
        // actually named "AI & IT" (not "IT" - an exact match on "IT" never
        // matched anything, so this contact never appeared for anyone).
        // Exact match rather than a fuzzy "like %IT%": this tenant also has
        // "Quality Department", which contains the substring "IT" (qualITy)
        // and would false-match. Prefers whoever holds a Manager/Head
        // designation in it, falling back to any active member.
        $itDept = \App\Models\Department::where('name', 'AI & IT')->first();
        if ($itDept) {
            $itContact = User::where('department_id', $itDept->id)
                ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
                ->whereHas('designation', fn ($q) => $q->where('name', 'like', '%Manager%')->orWhere('name', 'like', '%Head%'))
                ->first()
                ?? User::where('department_id', $itDept->id)
                    ->where('status', \App\Enums\UserAccountStatus::ACTIVE)->first();

            if ($itContact && $itContact->id !== $user->id) {
                $contacts[] = [
                    'name' => $itContact->getFullName(), 'role' => 'AI & IT Services',
                    'phone' => $itContact->phone ?? null,
                    'email' => $itContact->email, 'bg' => 'f59e0b',
                    'photo' => $itContact->getProfilePicture() ?: null,
                ];
            }
        }

        return $contacts;
    }

    /**
     * The joiner's new teammates: active users sharing the same team_id,
     * excluding the joiner themself. Empty (not an error) when the joiner
     * has no team assigned, or is the only member of it.
     */
    public static function teamRecipients(User $joiner): array
    {
        if (!$joiner->team_id) {
            return [];
        }

        return User::where('team_id', $joiner->team_id)
            ->where('id', '!=', $joiner->id)
            ->get()
            ->filter(fn($u) => self::isActive($u))
            ->pluck('email')
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn($e) => strtolower(trim($e)))
            ->unique()->values()->all();
    }

    /**
     * Recipients for exit alerts and NOC updates: everyone holding the
     * configured roles, the named managers, and the leaver's own manager —
     * plus Finance in Cc, but only when the leaver's department actually has
     * dues with Finance.
     *
     * @return array{to: string[], cc: string[]}
     */
    public static function exitRecipients(User $leaver): array
    {
        $cfg = config('hr_notifications.exit_notify', []);

        $to = collect();
        foreach (($cfg['roles'] ?? []) as $role) {
            try {
                $to = $to->merge(
                    User::role($role)->get()
                        ->filter(fn($u) => self::isActive($u))
                        ->pluck('email')
                );
            } catch (\Throwable $e) {
                Log::warning("HrNotificationService: role '{$role}' not resolvable — " . $e->getMessage());
            }
        }

        $to = $to->merge($cfg['emails'] ?? []);

        if (!empty($cfg['include_reporting_manager']) && $leaver->reporting_to_id) {
            $mgr = User::find($leaver->reporting_to_id);
            if ($mgr && filter_var($mgr->email, FILTER_VALIDATE_EMAIL)) {
                $to->push($mgr->email);
            }
        }

        $cc = collect();
        $dept = strtolower(trim(optional($leaver->department)->name ?? ''));
        $financeDepts = array_map(
            fn($n) => strtolower(trim($n)), (array) ($cfg['cc_finance_for_departments'] ?? [])
        );
        if ($dept !== '' && in_array($dept, $financeDepts, true) && !empty($cfg['cc_finance_email'])) {
            $cc->push($cfg['cc_finance_email']);
        }

        return self::dedupe($to, $cc);
    }

    /**
     * The ID documents uploaded during onboarding.
     *
     * Onboarding files are written through FileSecurityHelper::encryptAndStore,
     * so what sits on disk is a Laravel-encrypted payload, not the original
     * image/PDF. Attaching the file straight from disk would deliver an
     * unreadable blob — so each one is decrypted here and attached as raw
     * data. decryptAndGet also passes through older unencrypted uploads.
     *
     * @return array<int, array{name: string, mime: string, data: string}>
     */
    /**
     * Total attachment budget for one NewJoinerDocuments email. Gmail rejects
     * anything over 25MB once MIME/base64-encoded (~1.37x raw), and a large
     * enough payload can kill the PHP process outright before Symfony Mailer
     * ever gets a chance to throw a catchable exception — which is exactly
     * what silently dropped this email for a joiner whose upload folder had
     * ballooned to 105MB (2026-08-17). The 500KB-per-file cap at upload time
     * (OnboardingController::uploadFile) stops new folders from growing this
     * large, but folders uploaded before that cap existed can still hit it.
     */
    private const MAX_TOTAL_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    public static function onboardingDocuments(User $user): array
    {
        $folder = Constants::BaseFolderOnboardingDocuments . $user->id;
        $disk   = Storage::disk('public');

        if (!$disk->exists($folder)) {
            return [];
        }

        // Strict allow-list: only the documents payroll actually needs leave
        // the system. Education certificates and anything else stay in HRX.
        $allowed = (array) config('hr_notifications.onboarding.share_documents', []);

        $out = [];
        $totalBytes = 0;
        foreach ($disk->files($folder) as $file) {
            if ($allowed && !self::isShareable(basename($file), $allowed)) {
                continue;
            }
            try {
                $data = \App\Helpers\FileSecurityHelper::decryptAndGet($file, 'public');
                if ($data === null || $data === '') {
                    Log::warning("Onboarding document unreadable, skipped: {$file}");
                    continue;
                }

                if ($totalBytes + strlen($data) > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                    Log::warning("Onboarding documents mail for user {$user->id} hit the "
                        . round(self::MAX_TOTAL_ATTACHMENT_BYTES / 1024 / 1024) . "MB attachment "
                        . "budget — {$file} and any remaining files were left out so the email "
                        . "could still send instead of failing outright. Check the folder manually.");
                    break;
                }

                $out[] = [
                    'name' => self::friendlyName(basename($file)),
                    'mime' => self::mimeFor(pathinfo($file, PATHINFO_EXTENSION)),
                    'data' => $data,
                ];
                $totalBytes += strlen($data);
            } catch (\Throwable $e) {
                Log::warning("Onboarding document failed to decrypt ({$file}): " . $e->getMessage());
            }
        }

        return $out;
    }

    /** True when the file's prefix is on the share allow-list. */
    private static function isShareable(string $filename, array $allowed): bool
    {
        $name = strtolower($filename);
        foreach ($allowed as $prefix) {
            if (str_starts_with($name, strtolower($prefix))) {
                return true;
            }
        }
        return false;
    }

    private static function mimeFor(string $ext): string
    {
        return [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif',  'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ][strtolower($ext)] ?? 'application/octet-stream';
    }

    /** Turn "aadhaar_card_1738.pdf" into "Aadhaar Card.pdf" for the attachment name. */
    private static function friendlyName(string $filename): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/[_\-]?\d{6,}$/', '', $base);      // drop trailing timestamps
        $base = ucwords(trim(str_replace(['_', '-'], ' ', $base)));

        return $base . ($ext ? '.' . strtolower($ext) : '');
    }

    /** The details block shown in the joiner email. */
    public static function joinerDetails(User $user): array
    {
        $manager = $user->reporting_to_id ? User::find($user->reporting_to_id) : null;

        return [
            'Employee Name'     => $user->getFullName(),
            'Employee Code'     => $user->code ?: '—',
            'Designation'       => optional($user->designation)->name ?: '—',
            'Department'        => optional($user->department)->name ?: '—',
            'Location'          => optional($user->site)->name ?: '—',
            'Reporting Manager' => $manager ? $manager->getFullName() : '—',
            'Official Email'    => $user->email ?: '—',
            'Contact Number'    => $user->phone ?: '—',
            'Date of Joining'   => $user->date_of_joining
                ? \Carbon\Carbon::parse($user->date_of_joining)->format('d M Y')
                : '—',
        ];
    }

    /**
     * Publish the organisation-wide welcome post for an approved joiner.
     *
     * Deliberately minimal: photo, name, designation and department only.
     * Contact details, email, employee code, location and salary are NOT
     * included — the whole company sees this, unlike the HR/accounts mail.
     *
     * Returns the Announcement, or null if the module is unavailable.
     */
    public static function publishWelcomeAnnouncement(User $joiner): ?\App\Models\Announcement
    {
        try {
            $name  = $joiner->getFullName();
            $desig = optional($joiner->designation)->name;
            $dept  = optional($joiner->department)->name;
            $photo = $joiner->getProfilePicture();

            $line = trim(implode(' · ', array_filter([$desig, $dept])));

            $content = view('emails.hr.welcome_post', [
                'name'  => $name,
                'line'  => $line,
                'photo' => $photo,
            ])->render();

            return \App\Models\Announcement::create([
                'created_by_id'           => auth()->id() ?: $joiner->id,
                'title'                   => 'Welcome to the team, ' . $name . '!',
                'content'                 => $content,
                'is_html'                 => true, // server-rendered from a fixed partial, not user input
                'type'                    => 'general',
                // announcements.priority is enum('low','medium','high','critical')
                'priority'                => 'medium',
                'audience_type'           => 'global',
                'is_active'               => true,
                'requires_acknowledgment' => false,
                'notify_email'            => false,   // in-app only; email goes to HR/accounts separately
                'start_date'              => now()->toDateString(),
                'end_date'                => now()->addDays(14)->toDateString(),
                'published_at'            => now(),
                'tenant_id'               => $joiner->tenant_id,
            ]);
        } catch (\Throwable $e) {
            Log::error("Welcome announcement failed for user {$joiner->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Open the accounts setup task for an approved joiner.
     *
     * Biometric enrolment is waived for field departments (configured in
     * hr_notifications.biometric_exempt_departments) because those staff
     * never punch a device — but the employee ID is required for everyone.
     * If neither is outstanding the task is closed immediately rather than
     * nagging accounts about nothing.
     */
    public static function createAccountsSetupTask(User $user): ?\App\Models\EmployeeSetupTask
    {
        try {
            // Never open a second task for the same person.
            $existing = \App\Models\EmployeeSetupTask::where('user_id', $user->id)
                ->where('status', 'pending')->first();
            if ($existing) {
                return $existing;
            }

            // The employee ID is ALWAYS asked for. Onboarding auto-generates a
            // provisional code (and users.code is NOT NULL), so "is it empty?"
            // would never be true — accounts must still confirm or replace it
            // with the official ID that payroll uses.
            $needsEmpId = true;

            $needsBiometric = empty(trim((string) $user->biometric_id))
                && !self::isBiometricExempt($user);

            $task = \App\Models\EmployeeSetupTask::create([
                'user_id'         => $user->id,
                'needs_emp_id'    => $needsEmpId,
                'needs_biometric' => $needsBiometric,
                'status'          => ($needsEmpId || $needsBiometric) ? 'pending' : 'completed',
                'completed_at'    => ($needsEmpId || $needsBiometric) ? null : now(),
                'tenant_id'       => $user->tenant_id,
            ]);

            return $task;
        } catch (\Throwable $e) {
            Log::error("Accounts setup task failed for user {$user->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Raise the NOC set for a leaver and alert everyone who needs to know.
     *
     * The exit itself is already in force by the time this runs — access has
     * been revoked. These rows only track who still owes clearance, and gate
     * the point at which the exit can be marked fully closed.
     *
     * @param  string  $exitType  'resignation' | 'termination'
     */
    /**
     * Raise the clearance steps for an exit.
     *
     * $notify sends the exit alert to accounts/HR/the reporting manager. It is
     * a parameter rather than an unconditional side effect because this method
     * mails real people about a real colleague the moment it is called — a
     * caller that only wants to know which steps apply (a check, a dry run,
     * a test) must be able to ask without anyone receiving a resignation
     * notice.
     */
    public static function raiseExitClearances(
        User $leaver, string $exitType, int $exitId, bool $notify = true
    ): int
    {
        $steps = (array) config('hr_notifications.exit_clearances', []);
        $made  = 0;

        foreach ($steps as $key => $step) {
            if (!self::stepAppliesTo($step, $leaver)) {
                continue;
            }

            try {
                \App\Models\ExitClearance::firstOrCreate(
                    ['exit_type' => $exitType, 'exit_id' => $exitId, 'step_key' => $key],
                    [
                        'user_id'   => $leaver->id,
                        'label'     => $step['label'] ?? ucfirst($key),
                        'status'    => 'pending',
                        'tenant_id' => $leaver->tenant_id,
                    ]
                );
                $made++;
            } catch (\Throwable $e) {
                Log::error("Exit clearance '{$key}' failed for user {$leaver->id}: " . $e->getMessage());
            }
        }

        if ($notify) {
            self::notifyExit($leaver, $exitType, $exitId);
        }

        return $made;
    }

    /**
     * Does this clearance step apply to this leaver?
     *
     * A step with no 'only_for_departments' is raised for everyone. One that
     * has the list is raised only when the leaver's department matches, so
     * departments with no dues to that team never get a step that would sit
     * pending and stop the exit from ever closing.
     */
    private static function stepAppliesTo(array $step, User $leaver): bool
    {
        $only = $step['only_for_departments'] ?? null;
        if (empty($only)) {
            return true;
        }

        $dept = strtolower(trim(optional($leaver->department)->name ?? ''));
        if ($dept === '') {
            return false;
        }

        // Whole-name match, not substring: "Pre Sales" and "Channel Sales"
        // both contain "sales" and must not inherit the Sales rule.
        return in_array($dept, array_map(
            fn($n) => strtolower(trim($n)), (array) $only
        ), true);
    }

    /** Email the exit alert to accounts, HR and the named managers (Finance Cc'd where it applies). */
    public static function notifyExit(User $leaver, string $exitType, int $exitId): void
    {
        try {
            $r = self::exitRecipients($leaver);
            if (empty($r['to'])) {
                Log::warning("Exit alert skipped for user {$leaver->id}: no recipients configured.");
                return;
            }

            $mail = \Illuminate\Support\Facades\Mail::to($r['to']);
            if (!empty($r['cc'])) {
                $mail->cc($r['cc']);
            }
            $mail->send(new \App\Mail\ExitClearanceAlert($leaver, $exitType, $exitId));
        } catch (\Throwable $e) {
            Log::error("Exit alert failed for user {$leaver->id}: " . $e->getMessage());
        }
    }

    /**
     * Recipients for a warning/complaint alert: the employee it concerns,
     * HR, and their own reporting manager, when they have one.
     *
     * The employee was missing from this list — the notice went to HR and
     * the manager but never to the person it was actually about.
     *
     * @return array{to: string[], cc: string[]}
     */
    public static function lifecycleNoticeRecipients(User $employee): array
    {
        $cfg = config('hr_notifications.lifecycle_notify', []);

        $to = collect($cfg['emails'] ?? []);

        if (filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
            $to->push($employee->email);
        }

        if (!empty($cfg['include_reporting_manager']) && $employee->reporting_to_id) {
            $mgr = User::find($employee->reporting_to_id);
            if ($mgr && filter_var($mgr->email, FILTER_VALIDATE_EMAIL)) {
                $to->push($mgr->email);
            }
        }

        return self::dedupe($to, collect());
    }

    /**
     * Email HR and the reporting manager that a warning or complaint was
     * just recorded. Never throws — a mail failure must not undo a record
     * that has already been saved.
     *
     * @param  \App\Models\EmployeeWarning|\App\Models\EmployeeComplaint  $notice
     * @param  string  $kind  'warning' | 'complaint'
     */
    public static function notifyLifecycleNotice($notice, string $kind): void
    {
        try {
            $employee = $notice->user; // alias relation on both models
            if (!$employee) {
                Log::warning("Lifecycle notice alert skipped for {$kind} #{$notice->id}: no employee resolved.");
                return;
            }

            $r = self::lifecycleNoticeRecipients($employee);
            if (empty($r['to'])) {
                Log::warning("Lifecycle notice alert skipped for user {$employee->id}: no recipients configured.");
                return;
            }

            \Illuminate\Support\Facades\Mail::to($r['to'])->send(
                new \App\Mail\EmployeeLifecycleNoticeAlert($notice, $kind)
            );
        } catch (\Throwable $e) {
            Log::error("Lifecycle notice alert failed for {$kind} #{$notice->id}: " . $e->getMessage());
        }
    }

    /** Field departments that never punch a biometric device. */
    public static function isBiometricExempt(User $user): bool
    {
        $dept = strtolower(trim(optional($user->department)->name ?? ''));
        if ($dept === '') {
            return false;
        }

        foreach ((array) config('hr_notifications.biometric_exempt_departments', []) as $x) {
            if (str_contains($dept, strtolower($x))) {
                return true;
            }
        }

        return false;
    }

    private static function isActive(User $u): bool
    {
        $s = is_object($u->status) ? $u->status->value : $u->status;
        return $s === 'active';
    }

    /**
     * Valid, unique addresses, with anything already on To removed from Cc so
     * nobody receives two copies.
     */
    private static function dedupe($to, $cc): array
    {
        $to = collect($to)->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn($e) => strtolower(trim($e)))->unique()->values();

        $cc = collect($cc)->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn($e) => strtolower(trim($e)))->unique()
            ->reject(fn($e) => $to->contains($e))->values();

        return ['to' => $to->all(), 'cc' => $cc->all()];
    }
}
