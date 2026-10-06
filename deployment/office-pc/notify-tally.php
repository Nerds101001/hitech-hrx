<?php
// Emails ai@rustx.com a short Tally-related notice. Used by watch-tally.ps1 (the "Tally is waiting for its
// password" reminder and the "catch-up sync failed" alert).
//
//   php notify-tally.php "<subject>" <path-to-body-text-file> [--dry-run]
//
// The body comes from a file, not the command line, so quotes and line breaks in it cannot be mangled by the shell.
// Exit codes: 0 sent (or dry run), 1 bad input, 2 NOT sent because no real mail transport is configured,
// 3 the mail transport refused. Never claims success on the "log" driver.
//
// Same transport as notify-tunnel.php: the "gmail" mailer when MAIL_GMAIL_USERNAME/MAIL_GMAIL_PASSWORD are set in
// D:\liveserver\.env. No credential of any kind is ever written into these mails.

require 'D:/liveserver/vendor/autoload.php';
$app = require 'D:/liveserver/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '--dry-run'));
$dry = in_array('--dry-run', $argv, true);
$subject = trim((string) ($args[0] ?? ''));
$bodyFile = (string) ($args[1] ?? '');
if ($subject === '' || !is_file($bodyFile)) {
    fwrite(STDERR, "usage: php notify-tally.php \"<subject>\" <body-file> [--dry-run]\n");
    exit(1);
}
// PowerShell 5.1 writes a UTF-8 byte-order mark when it saves the file; it must not become the first character of the mail.
$body = rtrim(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($bodyFile))) . "\n\n-- Sent automatically by HRX on " . gethostname() . ' at ' . date('d M Y, h:i A') . ".\n";
$to = 'ai@rustx.com';

$gmail = (string) env('MAIL_GMAIL_USERNAME') !== '' && (string) env('MAIL_GMAIL_PASSWORD') !== '';
$mailer = $gmail ? 'gmail' : (string) config('mail.default');

if ($dry) {
    echo "mailer: {$mailer}\nto: {$to}\nsubject: {$subject}\n\n{$body}";
    exit(0);
}
if (in_array($mailer, ['log', 'array'], true)) {
    fwrite(STDERR, "NOT SENT: this instance's mail driver is '{$mailer}' and no MAIL_GMAIL_USERNAME/MAIL_GMAIL_PASSWORD are set in .env.\n");
    exit(2);
}

try {
    Mail::mailer($mailer)->raw($body, function ($m) use ($to, $subject, $gmail) {
        $m->to($to)->subject($subject);
        if ($gmail) {
            $m->from((string) env('MAIL_GMAIL_USERNAME'), 'HRX Tally watch');
        }
    });
    echo "sent to {$to} via {$mailer}\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(3);
}
