# HRX Portal - Daily Work & Human Resource Management System

HRX is a premium multi-tenant Human Resource Management (HRMS) and Sales Tracking web application built with Laravel, MySQL, Bootstrap, and Javascript. 

---

## 📋 Table of Contents
1. [Prerequisites](#-prerequisites)
2. [Local Installation Steps](#-local-installation-steps)
3. [Environment Configuration (.env)](#-environment-configuration-env)
4. [Build & Development Commands](#-build--development-commands)
5. [Azure DevOps CI/CD Setup](#-azure-devops-cicd-setup)
6. [Live Server Deployment (Hostinger)](#-live-server-deployment-hostinger)

---

## 🛠️ Prerequisites
Ensure your system meets the following requirements before installing:
- **PHP**: 8.2 or 8.3
- **Database**: MySQL 8.0+ / MariaDB
- **Dependency Manager**: Composer 2.x
- **Build Tools**: Node.js 18+ & NPM

---

## 🚀 Local Installation Steps

Follow these simple steps to configure and run the application locally:

### Step 1: Clone the Repository
```bash
git clone https://csenerds@dev.azure.com/csenerds/HRX/_git/HRX
cd HRX
```

### Step 2: Install Backend Dependencies
```bash
composer install
```

### Step 3: Install Frontend Dependencies
```bash
npm install
```

### Step 4: Configure Environment File
Copy the example environment template to create your active `.env` file:
```bash
cp .env.example .env
```
*(Open `.env` and fill in your Database details, Mail configuration, and App URL)*

### Step 5: Generate Application Key
```bash
php artisan key:generate
```

### Step 6: Run Database Migrations & Seeders
Run the migrations to build the tables and insert base seed data (roles, permissions, settings):
```bash
php artisan migrate --seed
```

### Step 7: Start the Servers
Open two terminal windows/tabs:
- **Terminal 1: Laravel Backend**
  ```bash
  php artisan serve
  ```
- **Terminal 2: Vite Assets Bundler**
  ```bash
  npm run dev
  ```

---

## ⚙️ Environment Configuration (.env)
Make sure your database config matches your local environment:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrx_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

---

## 📦 Build & Development Commands

### Development Server
Run Vite with hot-reload:
```bash
npm run dev
```

### Production Build
Compile and minify assets for deployment:
```bash
npm run build
```

---

## ☁️ Azure DevOps CI/CD Setup
To build a standard continuous integration pipeline in Azure DevOps:

1. Add an `azure-pipelines.yml` file to the root.
2. In Azure DevOps, go to **Pipelines** -> **Create Pipeline** -> select **Azure Repos Git** -> select your repository -> select **Existing Azure Pipelines YAML file**.
3. Use a standard Laravel CI configuration to run tests, compile assets, and publish build artifacts.

Example configuration is provided in the repository's `azure-pipelines.yml` file.

---

## 🚀 Live Server Deployment (Hostinger)

> [!IMPORTANT]
> **Azure DevOps Pipeline** and **Live Server (Hostinger) Deployment** are two separate environments. The deployment script in this repository targets Hostinger via SSH.

All scripts related to production deployments are located in the `deployment/hostinger` directory.

### Quick Local Deployment (PowerShell)
To deploy current local files directly to the Hostinger production server:
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 -SshPassword "YOUR_SSH_PASSWORD"
```

### Deployment with Database Import
To deploy files and simultaneously import your local database to the production database:
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 -SshPassword "YOUR_SSH_PASSWORD" -ImportDatabase -DbPassword "YOUR_PRODUCTION_DB_PASSWORD"
```
