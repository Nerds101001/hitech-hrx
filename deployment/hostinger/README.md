# HRX Hostinger Deployment

This folder contains a production-safe deployment flow for:
- Domain: `https://hrx.hitechgroup.in`
- App root: `/home/u989061032/domains/hitechgroup.in/public_html/hrx`
- Public dir: `/home/u989061032/domains/hitechgroup.in/public_html/hrx/public`

## Files
- `exclude.lst`: Excludes non-essential/local-only files from release package
- `remote_deploy.sh`: Server-side deploy logic (composer, migrate, cache, permissions)
- `deploy.ps1`: Local one-command deploy + optional DB import via SSH
- `export-db.ps1`: Local full SQL export from `.env` database

## Local deploy (without DB import)
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 `
  -SshPassword "YOUR_SSH_PASSWORD"
```

## Local deploy + DB import
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 `
  -SshPassword "YOUR_SSH_PASSWORD" `
  -ImportDatabase `
  -DbPassword "YOUR_DB_PASSWORD"
```

## GitHub Actions deploy
Update repository secrets:
- `SSH_HOST`
- `SSH_PORT`
- `SSH_USER`
- `SSH_PASSWORD`
- `DEPLOY_APP_ROOT`

Then push to `main` to deploy.
