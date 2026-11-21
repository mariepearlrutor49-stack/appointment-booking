<?php
/**
 * appointment_view.php - Appointment details, quick status buttons,
 * and a timeline of every status change (with date & time).
 */
require_once 'includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.*, c.full_name, c.contact_number, c.email, s.name AS service_name, s.price
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN services  s ON s.id = a.service_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    flash('Appointment not found.', 'danger');
    redirect('appointments.php');
}

// Status change history (newest first)
$stmt = $pdo->prepare(
    'SELECT l.*, ad.full_name AS admin_name
     FROM appointment_status_logs l
     LEFT JOIN admins ad ON ad.id = l.changed_by
     WHERE l.appointment_id = ?
     ORDER BY l.changed_at DESC, l.id DESC'
);
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

// Colours & icons for the quick action buttons
$buttonMeta = [
    'Pending'   => ['color' => 'warning',   'icon' => 'hourglass-split'],
    'Confirmed' => ['color' => 'primary',   'icon' => 'check2-circle'],
    'Completed' => ['color' => 'success',   'icon' => 'patch-check'],
    'Cancelled' => ['color' => 'secondary', 'icon' => 'x-circle'],
    'No-show'   => ['color' => 'danger',    'icon' => 'person-slash'],
];

$pageTitle = 'Appointment #' . $id;
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="appointments.php">Appointments</a></li>
        <li class="breadcrumb-item active" aria-current="page">Appointment #<?= $id ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-calendar-event text-primary" aria-hidden="true"></i>
        <span>Appointment #<?= $id ?></span>
        <?= status_badge($appt['status']) ?>
    </h1>
    <div class="d-flex gap-2">
        <a href="appointments.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
        <a href="appointment_edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit Details</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Appointment Details</h2></div>
            <div class="card-body">
                <dl class="row mb-0 gy-2">
                    <dt class="col-sm-4 text-muted">Patient</dt>
                    <dd class="col-sm-8 fw-semibold">
                        <?= avatar_initials($appt['full_name']) ?>
                        <span class="ms-1"><?= e($appt['full_name']) ?></span>
                        <a href="history.php?customer_id=<?= $appt['customer_id'] ?>" class="small ms-2">View patient history</a>
                    </dd>
                    <dt class="col-sm-4 text-muted">Contact</dt>
                    <dd class="col-sm-8"><?= e($appt['contact_number']) ?><?= $appt['email'] ? ' &middot; ' . e($appt['email']) : '' ?></dd>
                    <dt class="col-sm-4 text-muted">Clinic Service</dt>
                    <dd class="col-sm-8 fw-semibold"><?= e($appt['service_name']) ?> <span class="text-muted fw-normal">(₱<?= number_format($appt['price'], 2) ?>)</span></dd>
                    <dt class="col-sm-4 text-muted">Date &amp; Time</dt>
                    <dd class="col-sm-8">
                        <span class="fw-semibold"><?= date('l, F j, Y', strtotime($appt['appointment_date'])) ?></span>
                        <span class="time-chip ms-1"><i class="bi bi-clock" aria-hidden="true"></i> <?= format_time($appt['appointment_time']) ?></span>
                    </dd>
                    <dt class="col-sm-4 text-muted">Clinical Notes</dt>
                    <dd class="col-sm-8"><?= $appt['notes'] ? nl2br(e($appt['notes'])) : '<span class="text-muted">None</span>' ?></dd>
                    <dt class="col-sm-4 text-muted">Created</dt>
                    <dd class="col-sm-8 small text-muted"><?= date('M j, Y g:i A', strtotime($appt['created_at'])) ?></dd>
                    <dt class="col-sm-4 text-muted">Last Updated</dt>
                    <dd class="col-sm-8 small text-muted mb-0"><?= date('M j, Y g:i A', strtotime($appt['updated_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Quick status buttons -->
        <div class="card shadow-sm">
            <div class="card-header"><h2 class="h6 mb-0">Update Appointment Status</h2></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Every status change is recorded with its exact date, time, and administrator in the timeline.</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (STATUSES as $s): if ($s === $appt['status']) continue; $bm = $buttonMeta[$s]; ?>
                        <form method="post" action="appointment_status.php"
                              data-confirm="Mark appointment #<?= $id ?> (<?= e($appt['full_name']) ?>) as <?= $s ?>?"
                              <?= in_array($s, ['Cancelled', 'No-show'], true) ? 'data-confirm-danger="1"' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="status" value="<?= $s ?>">
                            <input type="hidden" name="back" value="appointment_view.php?id=<?= $id ?>">
                            <button class="btn btn-outline-<?= $bm['color'] ?>">
                                <i class="bi bi-<?= $bm['icon'] ?>" aria-hidden="true"></i> Mark as <?= $s ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Status timeline -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-clock-history text-primary" aria-hidden="true"></i> Status Change Log</h2></div>
            <div class="card-body">
                <?php if (!$logs): ?>
                    <p class="text-muted mb-0">No status changes recorded.</p>
                <?php else: ?>
                    <ul class="timeline mb-0">
                        <?php foreach ($logs as $log): ?>
                            <li>
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                    <?php if ($log['old_status']): ?>
                                        <?= status_badge($log['old_status']) ?>
                                        <i class="bi bi-arrow-right text-muted small" aria-hidden="true"></i>
                                    <?php else: ?>
                                        <span class="small text-muted">Booked as</span>
                                    <?php endif; ?>
                                    <?= status_badge($log['new_status']) ?>
                                </div>
                                <small class="text-muted d-block">
                                    <i class="bi bi-clock" aria-hidden="true"></i> <?= date('M j, Y g:i A', strtotime($log['changed_at'])) ?>
                                    &middot; <?= e($log['admin_name'] ?? 'Admin') ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
