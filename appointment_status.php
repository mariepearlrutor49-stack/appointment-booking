<?php
/**
 * appointment_status.php - Changes an appointment's status (UPDATE)
 * and records the change in appointment_status_logs.
 * Receives POST from appointments.php and appointment_view.php.
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('appointments.php');
}
verify_csrf();

$id        = (int) ($_POST['id'] ?? 0);
$newStatus = (string) ($_POST['status'] ?? '');
// Go back to where the admin came from (only allow local pages)
$back = $_POST['back'] ?? 'appointments.php';
if (!preg_match('/^[a-z_]+\.php(\?[\w=&%\-.+]*)?$/i', $back)) {
    $back = 'appointments.php';
}

if (!in_array($newStatus, STATUSES, true)) {
    flash('Invalid status.', 'danger');
    redirect($back);
}

$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    $pdo->rollBack();
    flash('Appointment not found.', 'danger');
    redirect($back);
}

if ($appt['status'] === $newStatus) {
    $pdo->rollBack();
    flash('Status is already ' . $newStatus . '.', 'info');
    redirect($back);
}

// Re-activating a Cancelled / No-show appointment: make sure nobody took the slot meanwhile
$wasFree   = in_array($appt['status'], FREE_SLOT_STATUSES, true);
$willBlock = !in_array($newStatus, FREE_SLOT_STATUSES, true);
if ($wasFree && $willBlock && is_slot_taken($pdo, $appt['appointment_date'], $appt['appointment_time'], $id)) {
    $pdo->rollBack();
    flash('Cannot set to ' . $newStatus . ': this time slot is already booked by another appointment.', 'danger');
    redirect($back);
}

$stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
$stmt->execute([$newStatus, $id]);
log_status_change($pdo, $id, $appt['status'], $newStatus);
$pdo->commit();

flash('Appointment #' . $id . ' marked as ' . $newStatus . '.');
redirect($back);
