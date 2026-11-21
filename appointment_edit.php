<?php
/**
 * appointment_edit.php - Edit appointment details (UPDATE): date, time, service, notes.
 * Status is changed separately (appointment_status.php) so every change is logged.
 */
require_once 'includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.*, c.full_name, c.contact_number
     FROM appointments a JOIN customers c ON c.id = a.customer_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    flash('Appointment not found.', 'danger');
    redirect('appointments.php');
}

$originalTime = substr($appt['appointment_time'], 0, 5);
$currentSlot  = ['date' => $appt['appointment_date'], 'time' => $originalTime];

// ---- Form values: POST > GET (date change reload) > database ----
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$form = [
    'service_id'       => (int) ($src['service_id'] ?? $appt['service_id']),
    'appointment_date' => (string) ($src['appointment_date'] ?? ($src['date'] ?? $appt['appointment_date'])),
    'appointment_time' => '',
    'notes'            => trim((string) ($src['notes'] ?? ($appt['notes'] ?? ''))),
];
if (!is_valid_date($form['appointment_date'])) {
    $form['appointment_date'] = $appt['appointment_date'];
}
// Time: use the submitted one, otherwise pre-select the current slot when on the original date
if (isset($src['appointment_time'])) {
    $form['appointment_time'] = (string) $src['appointment_time'];
} elseif ($form['appointment_date'] === $appt['appointment_date']) {
    $form['appointment_time'] = $originalTime;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM services WHERE id = ?');
    $stmt->execute([$form['service_id']]);
    if (!$stmt->fetchColumn()) {
        $errors['service_id'] = 'Please select a clinic service.';
    }

    $slotChanged = $form['appointment_date'] !== $appt['appointment_date']
                || $form['appointment_time'] !== $originalTime;

    if (!is_valid_slot($form['appointment_time'])) {
        $errors['slot0'] = 'Please select a valid 30-minute time slot.';
    } elseif ($slotChanged && is_past_slot($form['appointment_date'], $form['appointment_time'])) {
        $errors['slot0'] = 'You cannot move an appointment to a time that has already passed.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        // Only check for double booking if the slot actually changed and this appointment is active
        $isActive = !in_array($appt['status'], FREE_SLOT_STATUSES, true);
        if ($slotChanged && $isActive
            && is_slot_taken($pdo, $form['appointment_date'], $form['appointment_time'], $id)) {
            $pdo->rollBack();
            $errors['slot0'] = 'That time slot is already booked. Please choose another one.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE appointments
                 SET service_id = ?, appointment_date = ?, appointment_time = ?, notes = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $form['service_id'],
                $form['appointment_date'],
                $form['appointment_time'] . ':00',
                $form['notes'] !== '' ? $form['notes'] : null,
                $id,
            ]);
            $pdo->commit();
            flash('Appointment #' . $id . ' updated.');
            redirect('appointment_view.php?id=' . $id);
        }
    }
}

$services = $pdo->query('SELECT id, name, price, is_active FROM services ORDER BY name')->fetchAll();

$date         = $form['appointment_date'];
$booked       = get_booked_slots($pdo, $date, $id); // ignore this appointment's own slot
$selectedTime = $form['appointment_time'];

$pageTitle = 'Edit Appointment #' . $id;
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="appointments.php">Appointments</a></li>
        <li class="breadcrumb-item"><a href="appointment_view.php?id=<?= $id ?>">Appointment #<?= $id ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>

<h1 class="h3 mb-3"><i class="bi bi-pencil-square text-primary" aria-hidden="true"></i> Edit Appointment #<?= $id ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-danger error-summary" role="alert" tabindex="-1" aria-labelledby="errorSummaryTitle">
        <div class="fw-semibold mb-1" id="errorSummaryTitle">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Please fix the following before saving:
        </div>
        <ul class="mb-0 small">
            <?php foreach ($errors as $fieldId => $err): ?>
                <li><a href="#<?= e($fieldId) ?>" class="alert-link"><?= e($err) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h2 class="h6 mb-0">Appointment Details</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="<?= e($appt['full_name'] . ' (' . $appt['contact_number'] . ')') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status</label>
                        <div><?= status_badge($appt['status']) ?>
                            <a href="appointment_view.php?id=<?= $id ?>" class="small ms-2">Change status &amp; view log</a></div>
                    </div>
                    <div class="mb-3">
                        <label for="service_id" class="form-label fw-semibold">Clinic Service <span class="text-danger">*</span></label>
                        <select id="service_id" name="service_id" class="form-select <?= invalid_class($errors, 'service_id') ?>" required>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $form['service_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> — ₱<?= number_format($s['price'], 2) ?><?= $s['is_active'] ? '' : ' (inactive)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'service_id') ?>
                    </div>
                    <div class="mb-3">
                        <label for="appointment_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" class="form-control"
                               value="<?= e($date) ?>" required data-reload-slots data-keep="service_id,notes">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Clinical Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control"><?= e($form['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">Select 30-Minute Time Slot</h2>
                    <span class="badge slot-available"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('D, M j, Y', strtotime($date)) ?></span>
                </div>
                <div class="card-body d-flex flex-column justify-content-around">
                    <?php require 'includes/slot_picker.php'; ?>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="appointment_view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save" aria-hidden="true"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
