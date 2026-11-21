# AppointEase – Appointment Booking System

A simple, admin-only appointment booking system built with **PHP (PDO), MySQL, HTML, CSS and Bootstrap 5**.
Made for the *Web Systems Technologies* final project (CRUD + InfinityFree deployment).

## Features

| # | Module | What it does | CRUD |
|---|--------|--------------|------|
| 1 | Admin Login | Hashed passwords (`password_hash` / `password_verify`), session auth, logout, change password | – |
| 2 | Dashboard | Today's totals (all / pending / completed / cancelled), today's upcoming table, quick links | SELECT |
| 3 | Walk-in Customers | Register (with "Save & Book"), list/search, edit, delete | INSERT / SELECT / UPDATE / DELETE |
| 4 | Check Schedules | Pick a date, see 30-min slots from 8:00 AM–5:00 PM; booked/past slots are disabled | SELECT |
| 5 | Book Appointment | Searchable customer list, service, date, available slot; double booking blocked; default **Pending** | INSERT |
| 6 | Manage Appointments | Search by customer, filter by date/status, edit, delete with confirmation | SELECT / UPDATE / DELETE |
| 7 | Update Status | Pending, Confirmed, Completed, Cancelled, No-show (dropdown or buttons); every change is logged with date & time | UPDATE + INSERT log |
| 8 | History | Past appointments overall or per customer, filter by date range & status, read-only, printable | SELECT |

**Extras (creativity):** status-change timeline per appointment, "free slots left today" badge, completed-revenue
summary in History, print-friendly history report, cancelled/no-show slots become bookable again, CSRF protection
on every form, and race-safe booking (DB transaction + row lock).

## Folder structure

```
appointment-booking/
├── db_connect.php          ← database settings (edit for XAMPP / InfinityFree)
├── database.sql            ← tables + sample data (import in phpMyAdmin)
├── index.php               ← redirects to login or dashboard
├── login.php / logout.php / change_password.php
├── dashboard.php
├── customers.php / customer_add.php / customer_edit.php
├── schedules.php
├── book.php
├── appointments.php / appointment_view.php / appointment_edit.php
├── appointment_status.php / appointment_delete.php   ← POST handlers
├── history.php
├── includes/
│   ├── auth.php            ← include on protected pages
│   ├── functions.php       ← helpers (escaping, flash, CSRF, slots, validation)
│   ├── header.php / footer.php
│   ├── customer_form.php   ← shared customer fields
│   └── slot_picker.php     ← shared time-slot grid
└── assets/ css/style.css, js/app.js
```

## Database design

```
admins (id PK, username UNIQUE, password_hash, full_name, created_at)
customers (id PK, full_name, contact_number, email NULL, notes, created_at)
services (id PK, name, description, price, is_active)
appointments (id PK, customer_id FK→customers, service_id FK→services,
              appointment_date, appointment_time, status ENUM, notes, created_at, updated_at)
appointment_status_logs (id PK, appointment_id FK→appointments, old_status, new_status,
                         changed_by FK→admins, changed_at)
```

Relationships: one customer → many appointments; one service → many appointments;
one appointment → many status logs.

## Run locally (XAMPP)

1. Copy the `appointment-booking` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin, create a database named `appointment_db`, select it, and **Import** `database.sql`.
4. Visit http://localhost/appointment-booking/
5. Log in with **username:** `admin` **password:** `password` – then change it via the user menu → *Change Password*.

## Deploy to InfinityFree

1. Create a hosting account at https://www.infinityfree.net.
2. **Control Panel → MySQL Databases**: create a database; note the *hostname*, *username*, *password* and *database name*.
3. Open **phpMyAdmin** for that database and **Import** `database.sql`.
4. Edit `db_connect.php`:
   ```php
   define('DB_HOST', 'sqlXXX.infinityfree.com');
   define('DB_NAME', 'if0_XXXXXXX_appointment_db');
   define('DB_USER', 'if0_XXXXXXX');
   define('DB_PASS', 'your-vpanel-password');
   ```
5. Upload **the contents** of the project folder into `htdocs/` with the File Manager (or FTP).
6. Open your live link and log in. Change the default password immediately.

## Extending the system

- **New status?** Add it to the `ENUM` in `database.sql` and to `STATUSES` in `includes/functions.php`.
- **Different business hours / slot length?** Change `OPEN_TIME`, `CLOSE_TIME`, `SLOT_MINUTES` in `includes/functions.php`.
- **New page?** Start with `require_once 'includes/auth.php';`, set `$pageTitle`, include `header.php` and `footer.php`.
