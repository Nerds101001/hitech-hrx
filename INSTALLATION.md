# Installation Guide for Hitech HRX

This guide will walk you through setting up the HRX project in your local development environment.

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** (>= 8.2)
- **Composer**
- **Node.js** (>= 18.0) & **NPM**
- **MySQL** or **MariaDB**
- **Git**

## Setup Steps

### 1. Clone the Repository

```bash
git clone https://github.com/csenerds-web/hrx.git
cd hrx
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file and configure your database settings.

```bash
cp .env.example .env
```

Open `.env` and update the following:
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `MAIL_HOST`, `MAIL_PORT`, etc. (for notifications)

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Database Migrations

Run the migrations to set up your database schema.

```bash
php artisan migrate
```

### 7. Build Frontend Assets

```bash
npm run build
```

### 8. Link Storage

```bash
php artisan storage:link
```

### 9. Start the Application

You can use the built-in PHP server or a local web server (like Apache/Nginx).

```bash
php artisan serve
```

The application will be accessible at `http://localhost:8000`.

## Scripts

The project includes a `start_servers.bat` script for Windows users to quickly launch the development environment.

## Troubleshooting

- Ensure your `storage` and `bootstrap/cache` directories are writable.
- If assets are not loading, verify that `Vite` has successfully built the files in `public/build`.
