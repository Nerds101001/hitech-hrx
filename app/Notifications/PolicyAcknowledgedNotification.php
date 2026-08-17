<?php

namespace App\Notifications;

use App\Models\HRPolicyAcknowledgment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PolicyAcknowledgedNotification extends Notification
{
    use Queueable;

    protected $acknowledgment;

    public function __construct(HRPolicyAcknowledgment $acknowledgment)
    {
        $this->acknowledgment = $acknowledgment;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Previously this was built from MailMessage's greeting()/line() helpers,
     * which render Laravel's stock notification template — plain, unbranded and
     * nothing like the rest of the portal's mail. It also promised a receipt
     * that was never attached: tenant.hr-policies.receipt-pdf existed but
     * nothing rendered it.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $policy = $this->acknowledgment->policy;
        $user   = $this->acknowledgment->user;

        $data = [
            'userName'       => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'policyTitle'    => $policy->title ?? 'Company Policy',
            'policyCategory' => $policy->category ?? null,
            'acknowledgedAt' => optional($this->acknowledgment->acknowledged_at)->format('d M Y, h:i A'),
            'ipAddress'      => $this->acknowledgment->ip_address,
            'signatureHash'  => $this->acknowledgment->signature_hash ?? null,
        ];

        $mail = (new MailMessage)
            ->subject('Policy Acknowledged: ' . ($policy->title ?? 'Company Policy'))
            ->view('emails.policy_acknowledged', $data);

        // Attach the actual policy document the employee just agreed to — not
        // a generated acknowledgement receipt. A fetch failure must not cost
        // the employee their confirmation — send the mail either way.
        try {
            if ($policy->file_path && Storage::disk('r2')->exists($policy->file_path)) {
                $mail->attachData(
                    Storage::disk('r2')->get($policy->file_path),
                    ($policy->title ?: 'Company Policy') . '.pdf',
                    ['mime' => 'application/pdf']
                );
            } else {
                Log::warning('Policy acknowledgement mail: no file_path on disk for policy '
                    . ($policy->id ?? 'unknown') . ', sent without attachment.');
            }
        } catch (\Throwable $e) {
            Log::error('Policy PDF could not be attached for acknowledgment '
                . $this->acknowledgment->id . ': ' . $e->getMessage());
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Policy Acknowledged',
            'message' => 'You acknowledged ' . ($this->acknowledgment->policy->title ?? 'a company policy') . '.',
            'policy_id' => $this->acknowledgment->hr_policy_id ?? null,
        ];
    }
}
