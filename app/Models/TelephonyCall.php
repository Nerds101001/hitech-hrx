<?php

namespace App\Models;

use App\Enums\CallEndedBy;
use App\Enums\CallOrigin;
use App\Enums\TelephonyCallStatus;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TelephonyCall extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'telephony_calls';

  protected $fillable = [
    'call_uuid',
    'vendor_ref',
    'vendor',
    'origin',
    'agent_id',
    'campaign_id',
    'from_number',
    'to_number',
    'status',
    'ended_by',
    'end_reason',
    'queued_at',
    'ringing_at',
    'connected_at',
    'ended_at',
    'duration',
    'ring_seconds',
    'disposition_code',
    'disposition_notes',
    'vendor_disposition',
    'recording_url',
    'redial_of_call_uuid',
    'idempotency_key',
    'raw_vendor_response',
    'crm_client_mapping_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'status' => TelephonyCallStatus::class,
    'ended_by' => CallEndedBy::class,
    'origin' => CallOrigin::class,
    'queued_at' => 'datetime',
    'ringing_at' => 'datetime',
    'connected_at' => 'datetime',
    'ended_at' => 'datetime',
    'duration' => 'integer',
    'ring_seconds' => 'integer',
    'raw_vendor_response' => 'array',
  ];

  public function agent()
  {
    return $this->belongsTo(User::class, 'agent_id');
  }

  public function redialOf()
  {
    return $this->belongsTo(self::class, 'redial_of_call_uuid', 'call_uuid');
  }

  public function crmClientMapping()
  {
    return $this->belongsTo(CrmClientMapping::class);
  }

  /**
   * The canonical, vendor-agnostic shape every consumer of this API gets
   * back — the whole point of this contract. Whichever adapter sits behind
   * TelephonyCallService, every response comes from this one method.
   */
  public function toContract(): array
  {
    return [
      'call_id'       => $this->call_uuid,
      'status'        => $this->status?->value,
      'origin'        => $this->origin?->value,
      'from'          => $this->from_number,
      'to'            => $this->to_number,
      'agent_id'      => $this->agent_id,
      'queued_at'     => $this->queued_at?->toIso8601String(),
      'ringing_at'    => $this->ringing_at?->toIso8601String(),
      'connected_at'  => $this->connected_at?->toIso8601String(),
      'ended_at'      => $this->ended_at?->toIso8601String(),
      'duration'      => $this->duration,
      'ring_seconds'  => $this->ring_seconds,
      'ended_by'      => $this->ended_by?->value,
      'end_reason'    => $this->end_reason,
      'disposition'   => $this->disposition_code ? [
        'code'  => $this->disposition_code,
        'notes' => $this->disposition_notes,
      ] : null,
      // The vendor's own wording ("Answered By Agent", "Ringing - No
      // Answer", "Rejected By Switch", ...) -- kept alongside the
      // normalised `status` above, not instead of it, so reports can show
      // language people already recognise from CentroVision's own reports.
      'vendor_disposition' => $this->vendor_disposition,
      'recording_url' => $this->recording_url,
      'redial_of'     => $this->redial_of_call_uuid,
      'vendor'        => $this->vendor,
    ];
  }
}
