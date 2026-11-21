<?php
/**
 * history.php - Read-only view of past appointments.
 * Overall or per customer, filtered by date range and status. Printable.
 */
require_once 'includes/auth.php';

$filters = [
    'customer_id' => (int) ($_GET['customer_id'] ?? 0),
    'from'        => (string) ($_GET['from'] ?? ''),
    'to'          => (string) ($_GET['to'] ?? ''),
    'status'      => (string) ($_GET['status'] ?? ''),
];

// "Past" = any appointment before now (earlier date, or today with an earlier time)
$where  = ["(a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))"];
$params = [];

if ($filters['customer_id'] > 0) {
    $where[]  = 'a.customer_id = ?';
    $params[] = $filters['customer_id'];
}
if (is_valid_date($filters['from'])) {
    $where[]  = 'a.appointment_date >= ?';
    $params[] = $filters['from'];
}
if (is_valid_date($filters['to'])) {
    $where[]  = 'a.appointment_date <= ?';
    $params[] = $filters['to'];
}
if (in_array($filters['status'], STATUSES, true)) {
    $where[]  = 'a.status = ?';
    $params[] = $filters['status'];
}

$stmt = $pdo->prepare(
    'SELECT a.*, c.full_name, c.contact_number, s.name AS service_name, s.price
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN services  s ON s.id = a.service_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY a.appointment_date DESC, a.appointment_time DESC'
);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Small summary per status for the filtered result
$summary = array_fill_keys(STATUSES, 0);
$revenue = 0;
foreach ($history as $h) {
    $summary[$h['status']]++;
    if ($h['status'] === 'Completed') {
        $revenue += $h['price'];
    }
}

$customers = $pdo->query('SELECT id, full_name FROM customers ORDER BY full_name')->fetchAll();

// Selected customer's details (per-customer view)
$selectedCustomer = null;
foreach ($customers as $c) {
    if ((int) $c['id'] === $filters['customer_id']) {
        $selectedCustomer = $c;
    }
}

$pageTitle = 'Appointment History';
require 'includes/header.php';
?>

<div class="mb-3">
    <h1 class="h3 mb-0">
        <i class="bi bi-clock-history text-primary" aria-hidden="true"></i> Appointment History
        <?php if ($selectedCustomer): ?><small class="text-muted fs-5">— <?= e($selectedCustomer['full_name']) ?></small><?php endif; ?>
    </h1>
    <p class="text-muted small mb-0">Read-only archive of past clinic visits, filterable by patient, date range, and status.</p>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="histCustomer" class="form-label small fw-semibold mb-1">Patient</label>
                <select id="histCustomer" name="customer_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All patients</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filters['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="histFrom" class="form-label small fw-semibold mb-1">From Date</label>
                <input type="date" id="histFrom" name="from" class="form-control" value="<?= e($filters['from']) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-6 col-md-2">
                <label for="histTo" class="form-label small fw-semibold mb-1">To Date</label>
                <input type="date" id="histTo" name="to" class="form-control" value="<?= e($filters['to']) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label for="histStatus" class="form-label small fw-semibold mb-1">Status</label>
                <select id="histStatus" name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (STATUSES as $s): ?>
                        <option <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex">
                <a href="history.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary bar -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center gap-3">
        <?php foreach ($summary as $status => $count): ?>
            <span class="small d-inline-flex align-items-center gap-1"><?= status_badge($status) ?> <strong><?= $count ?></strong></span>
        <?php endforeach; ?>
        <span class="small ms-md-auto">Completed clinic revenue: <strong class="text-primary">₱<?= number_format($revenue, 2) ?></strong></span>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>#</th><th>Date</th><th>Time</th><th>Patient</th><th>Clinic Service</th><th>Status</th><th>Notes</th></tr>
            </thead>
            <tbody>
            <?php if (!$history): ?>
                <tr>
                    <td colspan="7" class="p-0">
                        <div class="empty-state py-5 text-center px-3">
                            <div class="empty-state-icon mx-auto mb-2" aria-hidden="true"><i class="bi bi-clock-history"></i></div>
                            <h2 class="h6 fw-semibold mb-1">No past appointments found</h2>
                            <p class="text-muted small mb-0">Adjust your date or status filters to view earlier clinic records.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="text-muted fw-semibold">#<?= $h['id'] ?></td>
                    <td class="text-nowrap fw-semibold"><?= format_date($h['appointment_date']) ?></td>
                    <td class="text-nowrap"><span class="time-chip"><?= format_time($h['appointment_time']) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= avatar_initials($h['full_name']) ?>
                            <span><?= e($h['full_name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($h['service_name']) ?></td>
                    <td>
                        <a href="appointment_view.php?id=<?= $h['id'] ?>" class="text-decoration-none" title="View status timeline">
                            <?= status_badge($h['status']) ?>
                        </a>
                    </td>
                    <td class="small text-muted"><?= e($h['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted"><?= count($history) ?> record(s) &middot; read-only view</div>
</div>

<?php require 'includes/footer.php'; ?>
