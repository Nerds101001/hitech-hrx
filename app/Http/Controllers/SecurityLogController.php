<?php

namespace App\Http\Controllers;

use App\Models\AuthLoginLog;
use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin visibility over authentication: every login attempt (success + failure)
 * with IP, the live web sessions, and the auto-blocked IPs. Read-mostly; the
 * only writes are an admin revoking a session or lifting an IP block.
 */
class SecurityLogController extends Controller
{
    /** Human labels for the event codes stored by App\Support\AuthLog. */
    public const EVENT_LABELS = [
        AuthLog::LOGIN_SUCCESS   => 'Login success',
        AuthLog::OTP_SENT        => 'OTP sent',
        AuthLog::TRUSTED_DEVICE  => 'Trusted-device login',
        AuthLog::PASSWORD_FAILED => 'Wrong password',
        AuthLog::UNKNOWN_USER    => 'Unknown username',
        AuthLog::OTP_FAILED      => 'Wrong/expired OTP',
        AuthLog::OTP_LOCKED      => 'Locked (OTP strikes)',
        AuthLog::ACCOUNT_LOCKED  => 'Tried while locked',
        AuthLog::ACCOUNT_INACTIVE=> 'Inactive account',
        AuthLog::BRUTE_FORCE_LOCK=> 'Account locked (brute force)',
        AuthLog::LOGOUT          => 'Logout',
    ];

    public function index(Request $request)
    {
        $q = AuthLoginLog::query()->with('user:id,first_name,last_name,email,code');

        if ($s = trim((string) $request->input('q', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('email_input', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u
                      ->where('first_name', 'like', "%{$s}%")
                      ->orWhere('last_name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                      ->orWhere('code', 'like', "%{$s}%"));
            });
        }
        if ($event = $request->input('event')) {
            $q->where('event', $event);
        }
        if (($res = $request->input('result')) !== null && $res !== '') {
            $q->where('successful', $res === 'success');
        }
        if ($ip = trim((string) $request->input('ip', ''))) {
            $q->where('ip_address', 'like', "%{$ip}%");
        }
        if ($from = $request->input('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        $per = (int) $request->input('per', 50);
        if (!in_array($per, [25, 50, 100, 200], true)) {
            $per = 50;
        }

        $today = now()->toDateString();
        $stats = [
            'attempts_today' => AuthLoginLog::whereDate('created_at', $today)->count(),
            'failures_today' => AuthLoginLog::whereDate('created_at', $today)->where('successful', false)->count(),
            'ips_today'      => AuthLoginLog::whereDate('created_at', $today)->distinct()->count('ip_address'),
        ];

        $logs = $q->orderByDesc('id')->paginate($per)->withQueryString();

        return view('security.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'logs'        => $logs,
            'stats'       => $stats,
            'eventLabels' => self::EVENT_LABELS,
            'per'         => $per,
        ]);
    }

    public function sessions(Request $request)
    {
        // The sessions table is Laravel's own (SESSION_DRIVER=database): one row
        // per live browser session, carrying user_id, ip_address, user_agent and
        // last_activity. Mobile app logins are JWT (stateless) and do not appear.
        $rows = DB::table('sessions')
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->limit(500)
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name', 'email', 'code'])
            ->keyBy('id');

        $currentId = $request->session()->getId();

        $sessions = $rows->map(function ($r) use ($users, $currentId) {
            return (object) [
                'id'          => $r->id,
                'user'        => $users->get($r->user_id),
                'ip'          => $r->ip_address,
                'agent'       => $r->user_agent,
                'device'      => self::deviceLabel($r->user_agent),
                'last'        => $r->last_activity ? \Carbon\Carbon::createFromTimestamp($r->last_activity) : null,
                'is_current'  => $r->id === $currentId,
            ];
        });

        return view('security.sessions', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'sessions'    => $sessions,
        ]);
    }

    public function destroySession(Request $request, string $id)
    {
        $killed = DB::table('sessions')->where('id', $id)->delete();

        AuthLog::record('session_revoked', auth()->user(), auth()->user()?->email, $request, true, [
            'revoked_session' => substr($id, 0, 12) . '…',
        ]);

        return back()->with($killed ? 'success' : 'error',
            $killed ? 'Session revoked — that device is signed out.' : 'Session not found (already expired).');
    }

    /** Very small, dependency-free UA → "Browser · OS" labeller for the table. */
    private static function deviceLabel(?string $ua): string
    {
        if (!$ua) {
            return 'Unknown device';
        }
        $browser = 'Browser';
        foreach ([
            'Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox',
            'Safari' => 'Safari', 'HRX' => 'HRX App', 'okhttp' => 'Android App', 'Dart' => 'Flutter App',
        ] as $needle => $name) {
            if (stripos($ua, $needle) !== false) { $browser = $name; break; }
        }
        $os = 'Unknown OS';
        foreach ([
            'Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS',
            'Mac OS' => 'macOS', 'Linux' => 'Linux',
        ] as $needle => $name) {
            if (stripos($ua, $needle) !== false) { $os = $name; break; }
        }
        return "{$browser} · {$os}";
    }
}
