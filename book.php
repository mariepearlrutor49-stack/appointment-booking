<?php
/**
 * book.php - Book an appointment (INSERT).
 * Choose customer, service, date and an available time slot.
 * Double booking is blocked both in the UI (disabled slots) and on the server.
 */
require_once 'includes/auth.php';

// ---- Form values (from GET when arriving, from POST when submitting) ----
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$form = [
    'customer_id'      => (int) ($src['customer_id'] ?? 0),
    'service_id'       => (int) ($src['service_id'] ?? 0),
    'appointment_date' => (string) ($src['appointment_date'] ?? ($src['date'] ?? date('Y-m-d'))),
    'appointment_time' => (string) ($src['appointment_time'] ?? ($src['time'] ?? '')),
    'notes'            => trim((string) ($src['notes'] ?? '')),
];
if (!is_valid_date($form['appointment_date'])) {
    $form['appointment_date'] = date('Y-m-d');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ---- Validation (field-keyed so the error summary can link to each field) ----
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE id = ? AND is_active = 1');
    $stmt->execute([$form['customer_id']]);
    if (!$stmt->fetchColumn()) {
        $errors['customer_id'] = 'Please select an active patient.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM services WHERE id = ? AND is_active = 1');
    $stmt->execute([$form['service_id']]);
    if (!$stmt->fetchColumn()) {
        $errors['service_id'] = 'Please select a clinic service.';
    }

    if (!is_valid_slot($form['appointment_time'])) {
        $errors['slot0'] = 'Please select an available 30-minute time slot.';
    } elseif (is_past_slot($form['appointment_date'], $form['appointment_time'])) {
        $errors['slot0'] = 'You cannot book a time slot that has already passed.';
    }

    // ---- Save (inside a transaction so the slot check + insert are atomic) ----
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if (is_slot_taken($pdo, $form['appointment_date'], $form['appointment_time'])) {
                $pdo->rollBack();
                $errors['slot0'] = 'Sorry, that time slot was just booked. Please choose another one.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO appointments (customer_id, service_id, appointment_date, appointment_time, status, notes)
                     VALUES (?, ?, ?, ?, 'Pending', ?)"
                );
                $stmt->execute([
                    $form['customer_id'],
                    $form['service_id'],
                    $form['appointment_date'],
                    $form['appointment_time'] . ':00',
                    $form['notes'] !== '' ? $form['notes'] : null,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_status_change($pdo, $newId, null, 'Pending'); // first entry in the timeline
                $pdo->commit();

                flash('Appointment booked for ' . format_date($form['appointment_date'])
                    . ' at ' . format_time($form['appointment_time']) . '.');
                redirect('appointment_view.php?id=' . $newId);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['customer_id'] = 'Could not save the appointment. Please try again.';
        }
    }
}

// ---- Data for the form (only active patients can book new appointments) ----
$customers = $pdo->query('SELECT id, full_name, contact_number FROM customers WHERE is_active = 1 ORDER BY full_name')->fetchAll();
$services  = $pdo->query('SELECT id, name, description, price FROM services WHERE is_active = 1 ORDER BY name')->fetchAll();

$date         = $form['appointment_date'];
$booked       = get_booked_slots($pdo, $date);
$selectedTime = $form['appointment_time'];

$pageTitle = 'Book an Appointment';
require 'includes/header.php';
?>

<div class="mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-calendar-plus text-primary" aria-hidden="true"></i> Book an Appointment</h1>
    <p class="text-muted small mb-0">Choose a patient, clinic service, and an open 30-minute slot.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger error-summary" role="alert" tabindex="-1" aria-labelledby="errorSummaryTitle">
        <div class="fw-semibold mb-1" id="errorSummaryTitle">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Please fix the following before booking:
        </div>
        <ul class="mb-0 small">
            <?php foreach ($errors as $fieldId => $err): ?>
                <li><a href="#<?= e($fieldId) ?>" class="alert-link"><?= e($err) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" id="bookingForm" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Left: details -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h2 class="h6 mb-0">1. Patient &amp; Clinic Service</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="customer_id" class="form-label fw-semibold">Patient <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                            <input type="search" id="customerSearch" class="form-control"
                                   placeholder="Filter patient by name or phone..." aria-label="Filter patient dropdown">
                        </div>
                        <select id="customer_id" name="customer_id" class="form-select <?= invalid_class($errors, 'customer_id') ?>" required>
                            <option value="">-- Select patient --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $form['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['full_name'] . ' (' . $c['contact_number'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'customer_id') ?>
                    </div>

                    <div class="mb-3">
                        <label for="service_id" class="form-label fw-semibold">Clinic Service <span class="text-danger">*</span></label>
                        <select id="service_id" name="service_id" class="form-select <?= invalid_class($errors, 'service_id') ?>" required>
                            <option value="">-- Select clinic service --</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                        data-price="₱<?= number_format($s['price'], 2) ?>"
                                        data-desc="<?= e($s['description'] ?? '') ?>"
                                        <?= $form['service_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> — ₱<?= number_format($s['price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'service_id') ?>
                        <div id="serviceDescHelp" class="form-text text-muted"></div>
                    </div>

                    <div class="mb-3">
                        <label for="appointment_date" class="form-label fw-semibold">Appointment Date <span class="text-danger">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" class="form-control"
                               value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" required
                               data-reload-slots data-keep="customer_id,service_id,notes">
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Clinical Notes / Reason for Visit</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control"
                                  placeholder="Symptoms, vital requests, doctor preference..."><?= e($form['notes']) ?></textarea>
                    </div>

                    <!-- Live Booking Summary Receipt -->
                    <div class="booking-summary p-3 rounded-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-2">
                            <i class="bi bi-receipt text-primary" aria-hidden="true"></i> Booking Summary
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Patient:</span>
                            <span class="fw-semibold text-end" id="summaryPatient">—</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Service:</span>
                            <span class="fw-semibold text-end" id="summaryService">—</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Schedule:</span>
                            <span class="fw-semibold text-end" id="summarySlot"><?= format_date($date) ?> &middot; <span id="summaryTime">Pick a slot</span></span>
                        </div>
                        <div class="d-flex justify-content-between small pt-1 border-top mt-1">
                            <span class="text-muted">Initial Status:</span>
                            <span><?= status_badge('Pending') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: time slots -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">2. Choose an Available 30-Minute Slot</h2>
                    <span class="badge slot-available"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('D, M j, Y', strtotime($date)) ?></span>
                </div>
                <div class="card-body d-flex flex-column justify-content-around">
                    <?php require 'includes/slot_picker.php'; ?>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="appointments.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Confirm Booking
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
