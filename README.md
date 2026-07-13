# 🌟 The HRX Project

Welcome to the HRX Project! This is a website made to help companies manage their work and employees. 

If you want to run this website on your own computer, don't worry! Just follow these super simple steps. It's written so even a 5th grader can understand it!

---

## 🛠️ What You Need First
Before we start, you need to have a few helper programs installed on your computer. Ask an adult or IT person if you don't have them:
1. **PHP** (Think of it as the engine for the website)
2. **Node.js** (This helps make the website look pretty and interactive)
3. **Composer** (This helps PHP get the extra pieces it needs)
4. **MySQL** (This is where all the data is saved, like a giant filing cabinet)

---

## 🚀 How to Start the Website on Your Computer

Follow these steps one by one. You will type these commands into a program called "Terminal" or "Command Prompt".

### Step 1: Download the Project
First, we need to download the project files from the internet to your computer.
```bash
git clone https://csenerds@dev.azure.com/csenerds/HRX/_git/HRX
cd HRX
```

### Step 2: Get the PHP Helpers
Now, let's ask Composer to get all the backend pieces we need.
```bash
composer install
```

### Step 3: Get the Visual Helpers
Next, let's ask Node.js to get all the pieces that make the website look good on the screen.
```bash
npm install
```

### Step 4: Setup Your Secret File
We need a secret file that tells the website where to save data. We have an example file ready, so let's just make a copy of it!
```bash
cp .env.example .env
```
*(After this, open the `.env` file with Notepad. Find the part that says Database and type in your MySQL database name, username, and password!)*

### Step 5: Make a Secret Key
Every website needs a secret key to keep passwords safe. Let's make one!
```bash
php artisan key:generate
```

### Step 6: Build the Filing Cabinet
Now we tell the website to set up the database tables (the filing cabinet drawers) and put some starter data inside.
```bash
php artisan migrate --seed
```

### Step 7: Turn it On! 🟢
You are almost done! We just need to turn on the engine and the visuals. You will need to open **two** Terminal windows.

**In Terminal Window 1, type:**
```bash
php artisan serve
```

**In Terminal Window 2, type:**
```bash
npm run dev
```

**You did it! 🎉** Now open your web browser (like Google Chrome) and go to `http://localhost:8000`. You will see the HRX website!

---

## ☁️ For the Tech Team: Azure DevOps
We use **Azure DevOps** to automatically check our code. We have a file called `azure-pipelines.yml` that tells Azure exactly what to do. It runs tests and prepares the code automatically!

## 🌍 For the Tech Team: Live Server Deployment
> ⚠️ **Important Note:** Azure Deployment and Live Server Deployment (Hostinger) are **TWO COMPLETELY DIFFERENT TASKS!** 

If you want to put the website on the real internet (on our Hostinger Server), we have special scripts for that located in `deployment/hostinger/`.

### Send Website to the Internet
Open PowerShell and run this:
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 -SshPassword "YOUR_SSH_PASSWORD_HERE"
```

### Send Website to the Internet AND Copy Database
```powershell
powershell -ExecutionPolicy Bypass -File deployment/hostinger/deploy.ps1 -SshPassword "YOUR_SSH_PASSWORD_HERE" -ImportDatabase -DbPassword "YOUR_DB_PASSWORD_HERE"
```
