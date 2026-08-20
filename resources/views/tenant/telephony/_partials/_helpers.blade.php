{{-- Formatting helpers shared by every telephony screen.

     Defined once here so "3m 18s" and the status colours mean the same thing
     on every page. Included near the top of each view. --}}
@php
  if (!function_exists('telephony_hms')) {
    /** Seconds as a compact human duration. 0 stays "0s", null becomes a dash. */
    function telephony_hms($seconds): string
    {
      if ($seconds === null || $seconds === '') {
        return '—';
      }
      $s = max(0, (int) $seconds);
      if ($s < 60) {
        return $s . 's';
      }
      $h = intdiv($s, 3600);
      $m = intdiv($s % 3600, 60);
      $rest = $s % 60;

      return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm %02ds', $m, $rest);
    }
  }

  if (!function_exists('telephony_tone')) {
    /**
     * Background and text colour for a call status.
     *
     * Answered is the only green: everything else is an outcome someone may
     * need to act on, so no other status gets to look like success.
     */
    function telephony_tone(?string $status): array
    {
      return match ($status) {
        'answered'                      => ['#dcfce7', '#15803d'],
        'no_answer', 'busy', 'not_reachable',
        'switched_off', 'congestion'    => ['#fef3c7', '#92400e'],
        'abandoned'                     => ['#ffe4e6', '#9f1239'],
        'failed', 'rejected', 'cancelled' => ['#fee2e2', '#991b1b'],
        default                         => ['#f1f5f9', '#475569'],
      };
    }
  }

  if (!function_exists('telephony_origin_label')) {
    function telephony_origin_label(?string $origin): string
    {
      return match ($origin) {
        'api_click_to_call' => 'Click to call',
        'manual_dial'       => 'Manual dial',
        'inbound'           => 'Inbound',
        'ivr'               => 'IVR',
        'predictive'        => 'Predictive',
        'preview'           => 'Preview',
        'progressive'       => 'Progressive',
        'callback'          => 'Callback',
        'internal'          => 'Internal',
        null, ''            => 'Unknown',
        default             => ucfirst(str_replace('_', ' ', $origin)),
      };
    }
  }

  if (!function_exists('telephony_number')) {
    /**
     * A customer number, masked unless this viewer is allowed the full value.
     *
     * Masking is the default rather than the exception: most people looking at
     * a call report have no business reading the customer's full number.
     */
    function telephony_number(?string $number, bool $unmasked): string
    {
      if (!$number) {
        return '—';
      }

      return $unmasked ? $number : \App\Models\Telephony\TelephonyCall::maskNumber($number);
    }
  }
@endphp
