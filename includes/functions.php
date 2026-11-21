<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------
 * Small helper functions shared by all pages.
 */

// Start the session once for every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** All allowed appointment statuses (must match the ENUM in database.sql). */
const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No-show'];

/** Statuses that do NOT occupy a time slot (slot can be booked again). */
const FREE_SLOT_STATUSES = ['Cancelled', 'No-show'];

/** Business hours for the schedule (24-hour format). */
const OPEN_TIME  = '08:00';
const CLOSE_TIME = '17:00';   // last slot starts at 16:30
const SLOT_MINUTES = 30;

/** Escape output to prevent XSS. Use it every time you echo user data. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Redirect to another page and stop the script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Store a one-time message shown on the next page (Bootstrap alert). */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/** Print and clear the flash message, if any. */
function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
           . e($f['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
}

/* ---------------- CSRF protection for POST forms ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to put inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Call at the top of every POST handler. */
function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('Invalid request (CSRF token mismatch). Please go back and try again.');
    }
}

/* ---------------- Scheduling helpers ---------------- */

/** Returns all slot start times for a day, e.g. ['08:00', '08:30', ... '16:30']. */
function generate_time_slots(): array
{
    $slots = [];
    $time  = strtotime(OPEN_TIME);
    $end   = strtotime(CLOSE_TIME);
    while ($time < $end) {
        $slots[] = date('H:i', $time);
        $time += SLOT_MINUTES * 60;
    }
    return $slots;
}

/**
 * Returns booked slots for a date as ['09:00' => [appointment row], ...].
 * Cancelled / No-show appointments do not block a slot.
 * $excludeId lets the edit page ignore the appointment being edited.
 */
function get_booked_slots(PDO $pdo, string $date, int $excludeId = 0): array
{
    $placeholders = implode(',', array_fill(0, count(FREE_SLOT_STATUSES), '?'));
    $sql = "SELECT a.id, TIME_FORMAT(a.appointment_time, '%H:%i') AS slot, a.status,
                   c.full_name, s.name AS service_name
            FROM appointments a
            JOIN customers c ON c.id = a.customer_id
            JOIN services  s ON s.id = a.service_id
            WHERE a.appointment_date = ?
              AND a.status NOT IN ($placeholders)
              AND a.id <> ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$date], FREE_SLOT_STATUSES, [$excludeId]));

    $booked = [];
    foreach ($stmt->fetchAll() as $row) {
        $booked[$row['slot']] = $row;
    }
    return $booked;
}

/**
 * True if the slot is already taken (prevents double booking).
 * Call it inside a transaction: "FOR UPDATE" locks the matching rows so two
 * admins saving the same slot at the same moment cannot both succeed.
 */
function is_slot_taken(PDO $pdo, string $date, string $time, int $excludeId = 0): bool
{
    $placeholders = implode(',', array_fill(0, count(FREE_SLOT_STATUSES), '?'));
    $stmt = $pdo->prepare(
        "SELECT id FROM appointments
         WHERE appointment_date = ? AND appointment_time = ?
           AND status NOT IN ($placeholders) AND id <> ?
         FOR UPDATE"
    );
    $stmt->execute(array_merge([$date, substr($time, 0, 5) . ':00'], FREE_SLOT_STATUSES, [$excludeId]));
    return (bool) $stmt->fetch();
}

/** True if the date/time is already in the past (cannot be booked). */
function is_past_slot(string $date, string $time): bool
{
    return strtotime($date . ' ' . $time) < time();
}

/** Validates a Y-m-d date string. */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** Validates that a time ('H:i') is exactly one of the allowed slots. */
function is_valid_slot(string $time): bool
{
    return in_array($time, generate_time_slots(), true);
}

/** Saves a row in appointment_status_logs. */
function log_status_change(PDO $pdo, int $appointmentId, ?string $old, string $new): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO appointment_status_logs (appointment_id, old_status, new_status, changed_by, changed_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$appointmentId, $old, $new, $_SESSION['admin_id'] ?? null]);
}

/* ---------------- Validation helpers ---------------- */

/**
 * Cleans and validates customer form input.
 * Returns [$data, $errors] so the form can be re-filled on error.
 */
function validate_customer(array $input): array
{
    $data = [
        'full_name'      => trim($input['full_name'] ?? ''),
        'contact_number' => trim($input['contact_number'] ?? ''),
        'email'          => trim($input['email'] ?? ''),
        'notes'          => trim($input['notes'] ?? ''),
    ];
    $errors = [];

    if ($data['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($data['full_name']) > 100) {
        $errors['full_name'] = 'Full name must be 100 characters or less.';
    }

    if ($data['contact_number'] === '') {
        $errors['contact_number'] = 'Contact number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $data['contact_number'])) {
        $errors['contact_number'] = 'Enter a valid contact number (7-20 digits).';
    }

    // Email is optional, but must be valid if given
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    return [$data, $errors];
}

/** Bootstrap "is-invalid" class + message helpers for form fields. */
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? 'is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
}

/* ---------------- Formatting helpers ---------------- */

function format_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

function format_date(string $date): string
{
    return date('M j, Y', strtotime($date));
}

/** Semantic status badge with icon + accessible contrast (not relying on color alone). */
function status_badge(string $status): string
{
    $meta = [
        'Pending'   => ['class' => 'status-pending',   'icon' => 'hourglass-split'],
        'Confirmed' => ['class' => 'status-confirmed', 'icon' => 'check2-circle'],
        'Completed' => ['class' => 'status-completed', 'icon' => 'patch-check-fill'],
        'Cancelled' => ['class' => 'status-cancelled', 'icon' => 'x-circle'],
        'No-show'   => ['class' => 'status-noshow',    'icon' => 'person-slash'],
    ];
    $m = $meta[$status] ?? ['class' => 'status-cancelled', 'icon' => 'circle'];
    return '<span class="badge status-pill ' . $m['class'] . '">'
         . '<i class="bi bi-' . $m['icon'] . '" aria-hidden="true"></i> '
         . e($status) . '</span>';
}

/** Initials avatar helper for patient/customer rows. */
function avatar_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $init  = mb_substr($parts[0] ?? '?', 0, 1) . (isset($parts[1]) ? mb_substr(end($parts), 0, 1) : '');
    return '<span class="avatar-chip" aria-hidden="true">' . e(mb_strtoupper($init)) . '</span>';
}
