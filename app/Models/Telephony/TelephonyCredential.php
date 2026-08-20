<?php

namespace App\Models\Telephony;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One vendor's API credentials.
 *
 * The api_key is stored in the clear because it is an identifier -- it travels
 * in an Authorization header on every request and has to be looked up by
 * value. The shared secret is encrypted, because that one never crosses the
 * wire and is the thing that actually proves a request is genuine.
 */
class TelephonyCredential extends Model
{
  protected $table = 'telephony_credentials';
  protected $guarded = ['id'];

  protected $casts = [
    'secret' => 'encrypted',
    'previous_secret' => 'encrypted',
    'previous_secret_expires_at' => 'datetime',
    'is_active' => 'boolean',
    'last_used_at' => 'datetime',
    'rotated_at' => 'datetime',
  ];

  /**
   * Never let the secret reach a log line, an exception trace or a
   * dd() by accident.
   */
  protected $hidden = ['secret', 'previous_secret'];

  public function createdBy(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by_id');
  }

  // ────────────────────────────────────────────────────────────── minting

  /** 48 hex characters. Long enough that guessing is not a strategy. */
  public static function newApiKey(): string
  {
    return 'hrx_' . bin2hex(random_bytes(24));
  }

  /** 64 hex characters, for the HMAC. */
  public static function newSecret(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Every secret this credential currently accepts.
   *
   * Two during a rotation window, so the vendor can switch over on their own
   * schedule without a coordinated cutover and without dropping a call. The
   * previous one drops out automatically once it expires -- nobody has to
   * remember to remove it.
   *
   * @return array<int,string>
   */
  public function acceptedSecrets(): array
  {
    $secrets = [$this->secret];

    if (
      $this->previous_secret
      && $this->previous_secret_expires_at
      && $this->previous_secret_expires_at->isFuture()
    ) {
      $secrets[] = $this->previous_secret;
    }

    return array_values(array_filter($secrets));
  }

  public function isRotating(): bool
  {
    return count($this->acceptedSecrets()) > 1;
  }

  /**
   * The allowlist for this credential, or null to fall back to config.
   *
   * @return array<int,string>|null
   */
  public function allowlist(): ?array
  {
    if ($this->allowed_ips === null || trim($this->allowed_ips) === '') {
      return null;
    }

    return array_values(array_filter(array_map('trim', explode(',', $this->allowed_ips))));
  }

  /** Enough of the key to recognise it, never enough to use it. */
  public function maskedKey(): string
  {
    $key = (string) $this->api_key;

    return strlen($key) <= 12
      ? str_repeat('•', strlen($key))
      : substr($key, 0, 8) . str_repeat('•', 12) . substr($key, -4);
  }

  /**
   * Rotate the secret, keeping the old one valid for a grace period.
   *
   * Returns the new secret in the clear -- the ONLY time it is ever readable.
   * It is encrypted at rest and never rendered again.
   */
  public function rotateSecret(int $graceDays = 14): string
  {
    $new = static::newSecret();

    $this->forceFill([
      'previous_secret' => $this->secret,
      'previous_secret_expires_at' => now()->addDays($graceDays),
      'secret' => $new,
      'rotated_at' => now(),
    ])->save();

    return $new;
  }

  /**
   * Record that this credential was just used.
   *
   * saveQuietly and a direct increment: this runs on every accepted webhook,
   * so it must not fire model events or read-modify-write a counter that two
   * concurrent requests would clobber.
   */
  public function markUsed(?string $ip): void
  {
    $this->forceFill([
      'last_used_at' => now(),
      'last_used_ip' => $ip,
    ])->saveQuietly();

    static::whereKey($this->getKey())->increment('use_count');
  }
}
