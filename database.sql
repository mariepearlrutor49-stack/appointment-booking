-- =====================================================================
--  Appointment Booking System - Database Schema
--  Import this file in phpMyAdmin (XAMPP locally / InfinityFree online).
--
--  NOTE for InfinityFree: you cannot run CREATE DATABASE there.
--  Create the database in the control panel first, select it in
--  phpMyAdmin, then import this file (the CREATE DATABASE / USE lines
--  below are commented out for that reason).
-- =====================================================================

-- CREATE DATABASE IF NOT EXISTS appointment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE appointment_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointment_status_logs;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. admins : users who can log in to the system
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)   NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,           -- created with password_hash()
    full_name     VARCHAR(100)  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. customers : walk-in customers
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(100)  NOT NULL,
    contact_number VARCHAR(20)   NOT NULL,
    email          VARCHAR(100)  NULL,
    notes          TEXT          NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. services : what a customer can book
-- ---------------------------------------------------------------------
CREATE TABLE services (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)   NOT NULL,
    description VARCHAR(255)   NULL,
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. appointments : one row per booked 30-minute slot
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id       INT UNSIGNED NOT NULL,
    service_id        INT UNSIGNED NOT NULL,
    appointment_date  DATE         NOT NULL,
    appointment_time  TIME         NOT NULL,
    status            ENUM('Pending','Confirmed','Completed','Cancelled','No-show')
                      NOT NULL DEFAULT 'Pending',
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appt_slot (appointment_date, appointment_time),
    KEY idx_appt_status (status),
    CONSTRAINT fk_appt_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_appt_service  FOREIGN KEY (service_id)  REFERENCES services(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. appointment_status_logs : records every status change (audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE appointment_status_logs (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT UNSIGNED NOT NULL,
    old_status     VARCHAR(20)  NULL,               -- NULL when the appointment is first created
    new_status     VARCHAR(20)  NOT NULL,
    changed_by     INT UNSIGNED NULL,               -- admin who made the change
    changed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_appt (appointment_id),
    CONSTRAINT fk_log_appt  FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_log_admin FOREIGN KEY (changed_by) REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  Sample data
-- =====================================================================

-- Default admin  ->  username: admin   password: password
-- (Change it right away using "Change Password" in the user menu.)
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator');

INSERT INTO services (name, description, price) VALUES
('General Check-up',           'Standard 30-minute physical and health assessment', 500.00),
('Follow-up Consultation',     'Review of lab results or ongoing treatment check',  300.00),
('Medical Certificate',        'Fit-to-work or school medical clearance exam',      350.00),
('Blood Pressure & Vitals',    'Vital signs monitoring and wellness screening',     200.00),
('Vaccination / Immunization', 'Routine adult or pediatric immunization shot',      850.00),
('Minor Wound Dressing',       'Cleaning and sterile dressing of minor wounds',     450.00);

INSERT INTO customers (full_name, contact_number, email, notes) VALUES
('Juan Dela Cruz',  '09171234567', 'juan@example.com',  'Prefers morning schedules'),
('Maria Santos',    '09181234567', NULL,                'First-time customer'),
('Pedro Reyes',     '09191234567', 'pedro@example.com', NULL),
('Ana Villanueva',  '09201234567', 'ana@example.com',   'Allergic to latex');

-- A few appointments relative to "today" so the dashboard has data
INSERT INTO appointments (customer_id, service_id, appointment_date, appointment_time, status, notes) VALUES
(1, 1, CURDATE(),                          '09:00:00', 'Confirmed', 'Annual check'),
(2, 3, CURDATE(),                          '10:30:00', 'Pending',   NULL),
(3, 4, CURDATE(),                          '14:00:00', 'Pending',   NULL),
(4, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY),'11:00:00', 'Completed', NULL),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY),'08:30:00', 'Completed', 'Follow-up done'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY),'15:00:00', 'No-show',   NULL),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY),'13:00:00', 'Pending',   NULL);

INSERT INTO appointment_status_logs (appointment_id, old_status, new_status, changed_by) VALUES
(1, NULL, 'Pending', 1), (1, 'Pending', 'Confirmed', 1),
(2, NULL, 'Pending', 1),
(3, NULL, 'Pending', 1),
(4, NULL, 'Pending', 1), (4, 'Pending', 'Completed', 1),
(5, NULL, 'Pending', 1), (5, 'Pending', 'Completed', 1),
(6, NULL, 'Pending', 1), (6, 'Pending', 'No-show', 1),
(7, NULL, 'Pending', 1);
