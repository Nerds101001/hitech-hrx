<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRequest extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'document_requests';

  protected $fillable = [
    'remarks',
    'document_type_id',
    'user_id',
    'status',
    'admin_remarks',
    'generated_file',
    'action_taken_by_id',
    'action_taken_at',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];
  protected $casts = [
    'action_taken_at' => 'datetime',
  ];

  public function documentType()
  {
    return $this->belongsTo(DocumentType::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the secure URL for the generated document file.
   *
   * @return string|null
   */
  public function getSecureUrl(): ?string
  {
      if (!$this->generated_file) {
          return null;
      }

      return \App\Helpers\FileSecurityHelper::generateSecureUrl($this->generated_file);
  }
}
