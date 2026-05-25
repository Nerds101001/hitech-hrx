<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\HRPolicyAcknowledgment;
use App\Notifications\PolicyAcknowledgedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPolicyAcknowledgmentEmail
{
    use Dispatchable, Queueable, SerializesModels;

    protected $user;
    protected $acknowledgment;

    /**
     * Create a new job instance.
     *
     * @param User $user
     * @param HRPolicyAcknowledgment $acknowledgment
     */
    public function __construct(User $user, HRPolicyAcknowledgment $acknowledgment)
    {
        $this->user = $user;
        $this->acknowledgment = $acknowledgment;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $this->user->notify(new PolicyAcknowledgedNotification($this->acknowledgment));
        } catch (\Exception $e) {
            Log::error("Policy email failed: " . $e->getMessage());
        }
    }
}
