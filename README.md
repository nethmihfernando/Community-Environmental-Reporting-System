# 🌿 Community Environmental Reporting System

A beginner-friendly PHP + MySQL web platform where citizens report environmental
issues (illegal dumping, pollution, hazards) and authorities manage them.

---

## 📁 Folder Structure

```
envreport/
├── index.php               ← Public homepage (recent reports + stats)
├── login.php               ← Login page (citizens + admins)
├── register.php            ← Citizen registration
├── logout.php              ← Destroys session and redirects
├── map_view.php            ← Full-screen Google Map of all reports
├── database.sql            ← Run this ONCE to set up the database
│
├── config/
│   ├── db.php              ← Database credentials + mysqli connection
│   └── auth.php            ← Session helpers (require_login, is_admin, etc.)
│
├── user/
│   ├── submit_report.php   ← Report submission form (with map + image upload)
│   ├── my_reports.php      ← Citizen's own reports + status tracking
│   └── view_report.php     ← Single report detail + timeline
│
├── admin/
│   ├── dashboard.php       ← Stats cards + Chart.js charts
│   ├── manage_reports.php  ← Filter, update status, delete reports
│   └── categories.php      ← Add / delete report categories
│
├── uploads/
│   ├── .htaccess           ← Blocks PHP execution in this folder (security!)
│   └── index.html          ← Prevents directory listing
│
└── assets/
    ├── css/style.css       ← All custom CSS (variables, components)
    └── js/main.js          ← Shared JS (map init, image preview, etc.)
```

---

## 🚀 Setup Instructions (Step by Step)

### Step 1 — Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- A web server: XAMPP / WAMP / MAMP / Laragon (all free)

### Step 2 — Copy project files
Place the `envreport/` folder inside your web server's root:
- XAMPP → `C:\xampp\htdocs\envreport\`
- WAMP  → `C:\wamp64\www\envreport\`

### Step 3 — Create the database
1. Open phpMyAdmin (`http://localhost/phpmyadmin`)
2. Click **Import** → choose `database.sql` → click **Go**

This creates the `env_reporting` database with all tables and seed data.

### Step 4 — Configure the connection
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password (blank for XAMPP default)
define('SITE_URL', 'http://localhost/envreport');
define('GOOGLE_MAPS_API_KEY', 'YOUR_KEY_HERE');   // see Step 5
```

### Step 5 — Google Maps API Key (free)
1. Go to https://console.cloud.google.com
2. Create a project → Enable **Maps JavaScript API** + **Geocoding API**
3. Create an API Key
4. Paste it into `config/db.php` as `GOOGLE_MAPS_API_KEY`

### Step 6 — Upload folder permissions
On Linux/Mac, make the uploads folder writable:
```bash
chmod 755 uploads/
```
On Windows/XAMPP this is usually automatic.

### Step 7 — Open in your browser
```
http://localhost/envreport/
```

---

## 🔑 Default Admin Account

| Email                   | Password   |
|-------------------------|------------|
| admin@gmail.com         | admin123   |

⚠️ **Change this password immediately after first login!**

---

## 🛡️ Security Features

| Feature | How it's implemented |
|---|---|
| Password hashing | `password_hash()` with bcrypt — never stored plain text |
| SQL Injection | All queries use **prepared statements** with `?` placeholders |
| XSS Prevention | All output uses `htmlspecialchars()` via the `h()` helper |
| File upload safety | Extension + MIME type check + max size + unique filename + no PHP in `/uploads` |
| Session fixation | `session_regenerate_id(true)` called on login |
| Access control | `require_login()` / `require_admin()` guards every protected page |

---

## 🗺️ Google Maps Integration

- **submit_report.php** — User clicks the map to set a pin; lat/lng are stored in hidden form fields; Geocoding API converts coordinates to a readable address.
- **view_report.php** — Mini map shows the exact pin location.
- **map_view.php** — Full-screen overview map with colour-coded markers for all reports.

---

## 📊 Chart.js Dashboard

`admin/dashboard.php` uses Chart.js (loaded from CDN) to show:
- **Bar chart** — number of reports per category
- **Doughnut chart** — reports split by status (Pending / In Progress / Resolved / Rejected)
