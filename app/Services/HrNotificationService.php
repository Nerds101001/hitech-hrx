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
     * Recipients for exit alerts and NOC updates: everyone holding the
     * configured roles, the named managers, and the leaver's own manager.
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

        return self::dedupe($to, collect());
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
                $out[] = [
                    'name' => self::friendlyName(basename($file)),
                    'mime' => self::mimeFor(pathinfo($file, PATHINFO_EXTENSION)),
                    'data' => $data,
                ];
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
    public static function raiseExitClearances(User $leaver, string $exitType, int $exitId): int
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

        self::notifyExit($leaver, $exitType, $exitId);

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

        foreach ((array) $only as $needle) {
            if (str_contains($dept, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /** Email the exit alert to accounts, HR and the named managers. */
    public static function notifyExit(User $leaver, string $exitType, int $exitId): void
    {
        try {
            $r = self::exitRecipients($leaver);
            if (empty($r['to'])) {
                Log::warning("Exit alert skipped for user {$leaver->id}: no recipients configured.");
                return;
            }

            \Illuminate\Support\Facades\Mail::to($r['to'])->send(
                new \App\Mail\ExitClearanceAlert($leaver, $exitType, $exitId)
            );
        } catch (\Throwable $e) {
            Log::error("Exit alert failed for user {$leaver->id}: " . $e->getMessage());
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
