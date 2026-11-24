# AppointEase Clinic – Appointment Booking System

---

## Core Features

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

