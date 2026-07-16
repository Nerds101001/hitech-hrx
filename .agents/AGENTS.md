# Global Agent Rules for Hitech HRX

## Deployment Protocol
- Always run `php artisan cache:clear`, `php artisan config:clear`, and `php artisan optimize:clear` after any manual patch or deployment to the live server.
- Ensure that the deployment script (`deployment/hostinger/remote_deploy.sh`) automatically clears the server cache and optimizes the application after extracting the files.
