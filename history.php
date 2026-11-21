<?php
/**
 * history.php - Read-only view of past appointments.
 * Overall or per customer, filtered by date range and status. Printable.
 */
require_once 'includes/auth.php';

$historyStatuses = ['Completed', 'Cancelled', 'No-show'];

$filters = [
    'customer_id' => (int) ($_GET['customer_id'] ?? 0),
    'q'           => trim((string) ($_GET['q'] ?? '')),
    'date'        => (string) ($_GET['date'] ?? ''),
    'status'      => (string) ($_GET['status'] ?? ''),
];

// If arriving via ?customer_id=..., pre-fill the search box with that patient's name
$selectedCustomer = null;
if ($filters['customer_id'] > 0) {
    $stmt = $pdo->prepare('SELECT id, full_name FROM customers WHERE id = ?');
    $stmt->execute([$filters['customer_id']]);
    $selectedCustomer = $stmt->fetch() ?: null;
    if ($selectedCustomer && $filters['q'] === '') {
        $filters['q'] = $selectedCustomer['full_name'];
    }
}

// History shows Completed, Cancelled, and No-show appointments only
$where  = ["a.status IN ('Completed', 'Cancelled', 'No-show')"];
$params = [];

if ($filters['q'] !== '') {
    $where[]  = '(c.full_name LIKE ? OR c.contact_number LIKE ?)';
    $like     = '%' . $filters['q'] . '%';
    $params[] = $like;
    $params[] = $like;
} elseif ($filters['customer_id'] > 0) {
    $where[]  = 'a.customer_id = ?';
    $params[] = $filters['customer_id'];
}
if (is_valid_date($filters['date'])) {
    $where[]  = 'a.appointment_date = ?';
    $params[] = $filters['date'];
}
if (in_array($filters['status'], $historyStatuses, true)) {
    $where[]  = 'a.status = ?';
    $params[] = $filters['status'];
}

$stmt = $pdo->prepare(
    'SELECT a.*, c.full_name, c.contact_number, c.notes AS customer_notes,
            s.name AS service_name, s.price
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN services  s ON s.id = a.service_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY a.appointment_date DESC, a.appointment_time DESC'
);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Small summary per status for the filtered result (Completed, Cancelled, No-show only)
$summary = array_fill_keys($historyStatuses, 0);
$revenue = 0;
foreach ($history as $h) {
    if (isset($summary[$h['status']])) {
        $summary[$h['status']]++;
    }
    if ($h['status'] === 'Completed') {
        $revenue += $h['price'];
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
            <div class="col-md-4">
                <label for="histSearch" class="form-label small fw-semibold mb-1">Search Patient</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="histSearch" name="q" class="form-control"
                           value="<?= e($filters['q']) ?>" placeholder="Search patient name or contact..."
                           onchange="this.form.submit()">
                </div>
            </div>
            <div class="col-md-3">
                <label for="histDate" class="form-label small fw-semibold mb-1">Select a Date</label>
                <input type="date" id="histDate" name="date" class="form-control" value="<?= e($filters['date']) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label for="histStatus" class="form-label small fw-semibold mb-1">Status</label>
                <select id="histStatus" name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All (Completed, Cancelled, No-show)</option>
                    <?php foreach ($historyStatuses as $s): ?>
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
            <?php foreach ($history as $i => $h):
                $noteText = trim((string) ($h['notes'] ?? ''));
                if ($noteText === '') {
                    $noteText = trim((string) ($h['customer_notes'] ?? ''));
                }
                if ($noteText === '') {
                    $defaultNotes = [
                        'Completed' => 'Consultation & ' . strtolower($h['service_name']) . ' completed.',
                        'Cancelled' => 'Appointment cancelled.',
                        'No-show'   => 'Patient did not attend scheduled slot.',
                    ];
                    $noteText = $defaultNotes[$h['status']] ?? '—';
                }
            ?>
                <tr>
                    <td class="text-muted fw-semibold">#<?= $i + 1 ?></td>
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
                    <td class="small text-muted"><?= e($noteText) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted"><?= count($history) ?> record(s) &middot; read-only view</div>
</div>

<?php require 'includes/footer.php'; ?>
