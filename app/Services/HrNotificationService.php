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
     * The ID documents uploaded during onboarding, as absolute paths ready to
     * attach. Only files that actually exist are returned.
     *
     * @return array<int, array{path: string, name: string}>
     */
    public static function onboardingDocuments(User $user): array
    {
        $folder = Constants::BaseFolderOnboardingDocuments . $user->id;
        $disk   = Storage::disk('public');

        if (!$disk->exists($folder)) {
            return [];
        }

        $out = [];
        foreach ($disk->files($folder) as $file) {
            $abs = $disk->path($file);
            if (!is_file($abs)) {
                continue;
            }
            $out[] = [
                'path' => $abs,
                'name' => self::friendlyName(basename($file)),
            ];
        }

        return $out;
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
