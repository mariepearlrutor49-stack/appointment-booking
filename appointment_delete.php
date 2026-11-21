<?php
/**
 * appointment_delete.php - Deletes an appointment (DELETE).
 * The browser asks for confirmation first (data-confirm on the form).
 * Its status logs are removed automatically (ON DELETE CASCADE).
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);

    flash($stmt->rowCount() ? 'Appointment deleted.' : 'Appointment not found.', $stmt->rowCount() ? 'info' : 'warning');
}
redirect('appointments.php');
