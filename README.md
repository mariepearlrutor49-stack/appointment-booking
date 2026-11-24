# AppointEase Clinic – Appointment Booking System

A clean, beginner-friendly **Clinic Appointment Booking System** for administrators, built with **PHP (PDO), MySQL, HTML5, CSS3, and Bootstrap 5**. Designed with an Apple Health–inspired emerald green sidebar interface.

---

## Core Features (Admin Only)

1. **Admin Login & Authentication**
   - Session-based login with `password_hash()` / `password_verify()` and CSRF protection.
   - Show/hide password toggle, Change Password page, and Sign Out.

2. **Clinic Overview (Dashboard)**
   - Summary cards for **Today's Visits**, **Pending**, **Completed**, and **Cancelled** appointments.
   - **Today's Upcoming Queue** table with 1-click **Confirm** and **Complete** actions.
   - Quick links to all clinic modules and a live **Today's Capacity** progress bar.

3. **Register & Manage Walk-in Patients**
   - Register walk-in patients with **Full Name**, **Contact Number**, **Email** (optional), and **Medical / Patient Notes**.
   - **Save & Book Appointment** option to jump straight into booking after registration.
   - Patient deletion is enabled only when a patient has **no upcoming schedule**, and deleting a patient hides them from active lists (`is_active = 0`) while **preserving their past records in Appointment History**.

4. **Check Available Schedules**
   - Select any date (with **Previous / Next Day**, **Today**, and **Tomorrow** shortcuts) to view 30-minute time slots from **8:00 AM to 5:00 PM**, grouped into **Morning** and **Afternoon** sessions.
   - Booked and past slots are disabled/marked; clicking an available green slot opens the booking form pre-filled with that date and time.

5. **Book an Appointment**
   - Searchable patient dropdown, clinic service selector (with price & description), date picker, and interactive 30-minute slot grid.
   - Live **Booking Summary** receipt preview.
   - Double booking is prevented both in the UI and on the server via database transactions and row locking (`SELECT ... FOR UPDATE`).
   - New appointments default to **`Pending`**.

6. **Manage Appointment Records**
   - Search by patient name/contact and filter by date or status (**instant auto-filtering** on change).
   - Sequential `#1, #2, #3...` row numbering that adjusts automatically.
   - View full appointment details or edit date, time slot, clinic service, and clinical notes.

7. **Update Appointment Status & Audit Trail**
   - Status options: **`Pending`**, **`Confirmed`**, **`Completed`**, **`Cancelled`**, **`No-show`**.
   - Update status directly from the table dropdown or from the appointment details page.
   - Every status change is recorded in `appointment_status_logs` with the old status, new status, admin user, and exact timestamp, displayed as a visual timeline.

8. **View Appointment History**
   - Read-only archive of **`Completed`**, **`Cancelled`**, and **`No-show`** appointments (including records of removed patients).
   - Instant filtering by **Search Patient**, **Select a Date**, and **Status**.
   - Displays status counts, completed clinic revenue, and clinical notes.

---

## Clinic Services Included

| Service | Description | Fee |
|---|---|---|
| **General Check-up** | Standard 30-minute physical and health assessment | ₱500.00 |
| **Follow-up Consultation** | Review of lab results or ongoing treatment check | ₱300.00 |
| **Medical Certificate** | Fit-to-work or school medical clearance exam | ₱350.00 |
| **Blood Pressure & Vitals** | Vital signs monitoring and wellness screening | ₱200.00 |
| **Vaccination / Immunization** | Routine adult or pediatric immunization shot | ₱850.00 |
| **Minor Wound Dressing** | Cleaning and sterile dressing of minor wounds | ₱450.00 |

---

## Database Schema (5 Tables)

- **`admins`** — `id` (PK), `username` (UNIQUE), `password_hash`, `full_name`, `created_at`
- **`customers`** — `id` (PK), `full_name`, `contact_number`, `email`, `notes`, `is_active`, `created_at`
- **`services`** — `id` (PK), `name`, `description`, `price`, `is_active`
- **`appointments`** — `id` (PK), `customer_id` (FK → `customers`), `service_id` (FK → `services`), `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`, `updated_at`
- **`appointment_status_logs`** — `id` (PK), `appointment_id` (FK → `appointments`), `old_status`, `new_status`, `changed_by` (FK → `admins`), `changed_at`

---

## Project Structure

```text
appointment-booking/
├── db_connect.php            # Auto-detects Local (XAMPP) vs Live (InfinityFree) DB credentials
├── database.sql              # Full SQL schema + sample clinic data
├── README.md                 # Project documentation
├── index.php                 # Redirects to dashboard or login
├── login.php                 # Admin sign-in page
├── logout.php                # Session logout handler
├── change_password.php       # Admin password update form
├── dashboard.php             # Clinic overview, summary platters & today's queue
├── customers.php             # Walk-in patients directory, search & safe delete
├── customer_add.php          # Register walk-in patient (Save / Save & Book)
├── customer_edit.php         # Edit patient details
├── schedules.php             # Daily 30-minute schedule availability grid
├── book.php                  # Book an appointment with live summary receipt
├── appointments.php          # Manage appointments with instant search & filters
├── appointment_view.php      # Appointment details & status change timeline
├── appointment_edit.php      # Edit appointment service, date, slot & notes
├── appointment_status.php    # POST handler for status updates + audit log
├── appointment_delete.php    # POST handler for appointment deletion
├── includes/
│   ├── auth.php              # Session guard for protected pages
│   ├── functions.php         # Shared helpers (CSRF, escaping, slots, validation)
│   ├── header.php            # Sidebar navigation, toolbar & page layout start
│   ├── footer.php            # Confirmation modal & page layout end
│   ├── customer_form.php     # Shared form fields for patient add/edit
│   └── slot_picker.php       # Morning & Afternoon 30-minute radio slot grid
└── assets/
    ├── css/style.css         # Custom Apple Health–inspired green theme
    └── js/app.js             # Confirmation modal, live booking summary & validation
```

---

## Running Locally (XAMPP)

1. Place the `appointment-booking` folder inside your XAMPP `htdocs/` directory.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), create a database named `appointment_db`, and **Import** `database.sql`.
4. Check `db_connect.php` under the `LOCAL (XAMPP)` block (configured for `127.0.0.1` and port `3307` / `3306`).
5. Visit `http://localhost/appointment-booking/` in your browser.
6. Default Admin Login:
   - **Username:** `admin`
   - **Password:** `password`

---

## Deploying to InfinityFree

1. Create a hosting account and MySQL database in the **InfinityFree Control Panel → MySQL Databases**.
2. Open **phpMyAdmin** on InfinityFree, select your database, and **Import** `database.sql`.
3. Update the `LIVE (InfinityFree)` credentials in `db_connect.php`:
   ```php
   define('DB_HOST', 'sqlXXX.infinityfree.com');
   define('DB_PORT', 3306);
   define('DB_NAME', 'if0_XXXXXXXX_appointment_db');
   define('DB_USER', 'if0_XXXXXXXX');
   define('DB_PASS', 'YOUR_VPANEL_PASSWORD');
   ```
4. Upload all project files into your InfinityFree `htdocs/` directory using File Manager or FTP.
