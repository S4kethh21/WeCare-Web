# WeCare Hospital — Render Deployment Guide

This guide details how to deploy the WeCare Hospital patient portal to **Render** connected to an external **Cloud MySQL** database.

---

## 1. Architecture Overview

```
Browser  ───►  Render Web Service (Apache + PHP 8.2 Docker)  ───►  Cloud MySQL Database
```

- **Hosting Platform**: Render (Web Service running Docker)
- **Application Server**: Apache 2.4 + PHP 8.2 (Official Docker container)
- **Database Engine**: MySQL Database (Render Private Service or Cloud MySQL)
- **Configuration Mode**: 100% environment-variable driven (Zero hardcoded secrets)

---

## 2. Step-by-Step Deployment Instructions

### Step 1: Prepare Your MySQL Database
Use your MySQL database (e.g. Render internal MySQL service, Aiven, AWS RDS, or any standard MySQL host).

### Step 2: Gather Your Database Credentials
From your MySQL provider dashboard or service settings, obtain:
- **Host**: The MySQL hostname (e.g. Render internal service name `mysql` or cloud host)
- **Port**: The port number (default `3306`)
- **User**: The database username
- **Password**: The database password
- **Database Name**: The database name (e.g. `hospital_management`)

> [!IMPORTANT]
> Ensure your database allows connections from your Render Web Service.

### Step 3: Configure Environment Variables in Render
1. Go to the [Render Dashboard](https://dashboard.render.com/).
2. Select your **wecare-hospital** Web Service.
3. In the left navigation, click **Environment**.
4. Add the following environment variables:

| Key | Description | Example / Note |
|---|---|---|
| `DB_HOST` | MySQL Hostname | Render internal service name (e.g. `mysql`) or cloud host |
| `DB_PORT` | MySQL Port | `3306` |
| `DB_USER` | MySQL Username | e.g. `root` |
| `DB_PASSWORD` | MySQL Password | Your database password |
| `DB_NAME` | Target Database Name | e.g. `hospital_management` |

5. Click **Save Changes**.

### Step 4: Import `schema.sql` into Cloud Database
Before patients can log in or book consultations, the database tables and verified specialists must be initialized:
1. Open your database management tool (phpMyAdmin, DBeaver, TablePlus, or MySQL CLI).
2. Connect to your Cloud MySQL database.
3. Import the provided [schema.sql](schema.sql) file.
   - All 5 tables (`patients`, `doctors`, `appointments`, `bills`, `prescriptions`) will be created.
   - All 14 medical specialist profiles will be seeded.
   - A verified demo patient account (`rahul.sharma@gmail.com` / password: `123456789`) will be created.

### Step 5: Redeploy on Render
1. In the Render Dashboard under **wecare-hospital**, click **Manual Deploy** → **Deploy latest commit**.
2. Monitor the **Logs** tab.
3. You should see:
   ```text
   Starting WeCare Hospital Server on port 10000...
   AH00558: apache2: Could not reliably determine the server's fully qualified domain name...
   [mpm_prefork:notice] Apache/2.4.xx (Debian) PHP/8.2.xx configured -- resuming normal operations
   ```

### Step 6: Verify Deployment
1. Visit your Render URL: `https://wecare-hospital.onrender.com`
2. Test the safe diagnostic page: `https://wecare-hospital.onrender.com/test_db.php`
   - Confirm it outputs: `Database Connection: SUCCESSFUL!` and `5 / 5 tables present`.
3. Log in with the demo account or register a new patient account:
   - **Email**: `rahul.sharma@gmail.com`
   - **Password**: `123456789`

---

## 3. Local Development (XAMPP)

The application automatically detects when it is running locally and falls back to local XAMPP MySQL defaults without requiring cloud environment variables:
1. Start Apache and MySQL in the XAMPP Control Panel (or execute `serve.bat`).
2. Navigate to `http://127.0.0.1:5500/`.
