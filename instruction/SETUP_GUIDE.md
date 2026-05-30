# Hitech HRX - System Implementation Manual

This guide provides a detailed, step-by-step walkthrough for setting up the Hitech HRX management system on a local development machine or a production server.

---

## 🛠️ System Requirements
Before starting, ensure your environment meets these requirements:
- **PHP:** 8.2 or higher
- **Database:** MySQL 8.0+ / MariaDB 10.4+
- **Memory Limit:** 256MB minimum
- **PHP Extensions:** BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

---

## 🚀 Installation Steps

### 1. Source Preparation
Download and extract the HRX source code into your web server's root directory (e.g., `C:\laragon\www\hrx`). Open your terminal in this directory.

### 2. Dependency Management
Install the core PHP and JavaScript packages required for the system to function:
```bash
composer install --no-dev --optimize-autoloader
npm install
```

### 3. Environment Configuration
Create your environment configuration file from the template:
```bash
cp .env.example .env
```
Open the `.env` file and update your database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) and set your `APP_URL`.

### 4. Application Initialization
Generate the application security key and build the database schema:
```bash
php artisan key:generate
php artisan migrate --seed
```
*Note: The `--seed` flag is required to populate initial admin data.*

### 5. Storage & Asset Compilation
Establish the file storage link and compile the frontend assets:
```bash
php artisan storage:link
npm run build
```

### 6. Launching Services
Start the main application server and the real-time notification engine:
```bash
php artisan serve
php artisan reverb:start
```

---
*© 2026 Hitech HRX. Proprietary & Confidential Implementation Guide.*
