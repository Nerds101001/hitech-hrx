# HRX — Server & Deployment

Everything needed to deploy and operate HRX in production.

> **Credentials are never stored in this repository.** Copy `deployment/.env.deploy.example`
> to `deployment/.env.deploy`, fill in the passwords, and keep that file local —
> it is gitignored. Anything committed to git is effectively permanent: it
> survives in history, in every clone, and in CI caches.

---

## Production server

| | |
|---|---|
| Host | `89.117.188.10` (Hostinger) |
| SSH port | `65002` |
| SSH user | `u989061032` |
| App root | `/home/u989061032/domains/hitechgroup.in/public_html/hrx` |
| Public URL | https://hrx.hitechgroup.in |
| PHP binary | `/opt/alt/php84/usr/bin/php` (PHP 8.4 — **not** the default `php`) |
| Database | `u989061032_hrx` (MySQL), user `u989061032_hrx` |
| Web root | `public/` inside the app root |

Local tooling: PuTTY's `plink.exe` / `pscp.exe`, expected at
`C:\Program Files\PuTTY\`.

---

## Deploying

### Normal deploy

```bash
bash deployment/hostinger/deploy.sh app/Http/Controllers/tenant/FooController.php resources/views/tenant/foo.blade.php
```

Named files are tarred, copied up, extracted in place, and the view/route
caches cleared. Pass `--migrate` to run `artisan migrate --force` afterwards.

Before uploading, it also checks every `*.blade.php` tracked in git against
what actually exists on the server, and warns (without blocking the deploy)
about any that are missing - a view can be committed and referenced by an
already-live page without ever having been pushed, staying invisible until
some later deploy's `view:clear` exposes it as a live 500. Pass
`--skip-view-check` to skip that check.

### PowerShell equivalent

`deployment/hostinger/deploy.ps1` does a full build-and-deploy including
`npm run build`. Pass the password explicitly:

```powershell
.\deployment\hostinger\deploy.ps1 -SshPassword "…"
```

Use `-SkipBuild` for PHP-only changes.

---

## Rules learned the hard way

- **Never run `composer` on production.** Seeders and new classes autoload via
  PSR-4 without it; running composer there has broken the install before.
- **Always clear caches after deploying blades or routes:**
  `artisan view:clear`, `artisan route:clear`.
- **`php` on this host is the wrong version.** Always use the full
  `/opt/alt/php84/usr/bin/php` path, including inside cron.
- **A blank value in an import means "leave it alone", never "set to zero".**
  A July import wrote `sale = 0` over real figures and 213 sales had to be
  restored from backup.
- **Back up before any bulk data change**, and write a per-row CSV log of what
  changed. Every bulk operation in `deployment/` follows this.
- **Verify as a signed-in user, not just by HTTP status.** A guest request gets
  a 302 and hides render-time Blade errors entirely.

---

## Verifying a change on the server

Blade and permission bugs only show up when rendered as a real user, so run
the page through the kernel with an authenticated session:

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$boot = Illuminate\Http\Request::create('/','GET');
$app->instance('request', $boot);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/sales-visits/sales-report', 'GET', ['fy' => '2026-2027']);
$app->instance('request', $request);
Illuminate\Support\Facades\Auth::loginUsingId(9);      // 9 = admin
$response = $kernel->handle($request);
echo $response->getStatusCode(), "\n";
```

Upload it with `pscp`, run it with the PHP 8.4 binary, then delete it.

**Do not test against live records.** Use a throwaway row, or capture the
original values and restore them — a permission test once overwrote a real
sales target, and a mapping test dropped a live row that had to be repaired.

---

## Useful one-liners

```bash
# tail the application log
plink -ssh -batch -P 65002 -l u989061032 -pw "$HRX_SSH_PASSWORD" 89.117.188.10 \
  "tail -n 100 /home/u989061032/domains/hitechgroup.in/public_html/hrx/storage/logs/laravel.log"
```

```bash
# clear every cache
plink -ssh -batch -P 65002 -l u989061032 -pw "$HRX_SSH_PASSWORD" 89.117.188.10 \
  "cd /home/u989061032/domains/hitechgroup.in/public_html/hrx && \
   /opt/alt/php84/usr/bin/php artisan view:clear && \
   /opt/alt/php84/usr/bin/php artisan route:clear && \
   /opt/alt/php84/usr/bin/php artisan config:clear"
```

---

## Data backups

Bulk operations write their backups and logs to `storage/app/` on the server.
These hold customer and employee data, so they stay **out of this repository**:

| File | What it protects |
|---|---|
| `pipeline_shift_backup.json` | The 1,987 rows moved from the Pipeline book into the Sales Report |
| `pipeline_v1_batch_backup.json` | The first consolidated-pipeline import, before re-import |
| `cc_map_backup.json` | CCare / New Biz agent mappings before the role split |

Pull one down with `pscp` if a restore is ever needed.
