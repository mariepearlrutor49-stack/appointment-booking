<?php
/**
 * appointments.php - Manage appointment records.
 * Search & filter (date, status, customer name), change status, edit, delete.
 */
require_once 'includes/auth.php';

// ---- Read filters from the URL ----
$filters = [
    'q'      => trim((string) ($_GET['q'] ?? '')),
    'date'   => (string) ($_GET['date'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
];

// ---- Build the query step by step (only add conditions that are used) ----
$where  = [];
$params = [];

if ($filters['q'] !== '') {
    $where[]  = '(c.full_name LIKE ? OR c.contact_number LIKE ?)';
    $params[] = '%' . $filters['q'] . '%';
    $params[] = '%' . $filters['q'] . '%';
}
if ($filters['date'] !== '' && is_valid_date($filters['date'])) {
    $where[]  = 'a.appointment_date = ?';
    $params[] = $filters['date'];
}
if (in_array($filters['status'], STATUSES, true)) {
    $where[]  = 'a.status = ?';
    $params[] = $filters['status'];
}

$sql = 'SELECT a.*, c.full_name, c.contact_number, s.name AS service_name
        FROM appointments a
        JOIN customers c ON c.id = a.customer_id
        JOIN services  s ON s.id = a.service_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Current URL (with filters) so status changes return to the same view
$backUrl = 'appointments.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');

// Helper to build status tab URL preserving q & date
function status_tab_url(array $filters, string $status): string
{
    $q = array_filter([
        'q'      => $filters['q'],
        'date'   => $filters['date'],
        'status' => $status,
    ], fn($v) => $v !== '');
    return 'appointments.php' . ($q ? '?' . http_build_query($q) : '');
}

$pageTitle = 'Manage Appointments';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-list-check text-primary" aria-hidden="true"></i> Manage Appointments</h1>
        <p class="text-muted small mb-0">Filter by patient, date, or status; update status inline or edit details.</p>
    </div>
    <a href="book.php" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Appointment</a>
</div>

<!-- Quick Status Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-3" role="navigation" aria-label="Filter by appointment status">
    <a href="<?= e(status_tab_url($filters, '')) ?>"
       class="btn btn-sm <?= $filters['status'] === '' ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">
        All Statuses
    </a>
    <?php foreach (STATUSES as $s): ?>
        <a href="<?= e(status_tab_url($filters, $s)) ?>"
           class="btn btn-sm <?= $filters['status'] === $s ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">
            <?= e($s) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <!-- Filters -->
    <div class="card-body border-bottom">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="filterQ" class="form-label small fw-semibold mb-1">Patient Name / Contact</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="filterQ" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Search patient...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label for="filterDate" class="form-label small fw-semibold mb-1">Date</label>
                <input type="date" id="filterDate" name="date" class="form-control" value="<?= e($filters['date']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="filterStatus" class="form-label small fw-semibold mb-1">Status</label>
                <select id="filterStatus" name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (STATUSES as $s): ?>
                        <option <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filter</button>
                <a href="appointments.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Date &amp; Time</th><th>Patient</th><th>Clinic Service</th>
                    <th style="min-width:170px">Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$appointments): ?>
                <tr>
                    <td colspan="6" class="p-0">
                        <div class="empty-state py-5 text-center px-3">
                            <div class="empty-state-icon mx-auto mb-2" aria-hidden="true"><i class="bi bi-search"></i></div>
                            <h2 class="h6 fw-semibold mb-1">No appointments match your filters</h2>
                            <p class="text-muted small mb-3">Try clearing your search filters or book a new clinic appointment.</p>
                            <a href="appointments.php" class="btn btn-sm btn-outline-secondary me-1">Reset Filters</a>
                            <a href="book.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Book Appointment</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td class="text-muted fw-semibold">#<?= $a['id'] ?></td>
                    <td class="text-nowrap">
                        <div class="fw-semibold"><?= format_date($a['appointment_date']) ?></div>
                        <small class="text-muted"><i class="bi bi-clock" aria-hidden="true"></i> <?= format_time($a['appointment_time']) ?></small>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= avatar_initials($a['full_name']) ?>
                            <div>
                                <div class="fw-semibold"><?= e($a['full_name']) ?></div>
                                <small class="text-muted"><?= e($a['contact_number']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= e($a['service_name']) ?></td>
                    <td>
                        <div class="mb-1"><?= status_badge($a['status']) ?></div>
                        <!-- Change status with a dropdown (submits automatically with accessible modal) -->
                        <form method="post" action="appointment_status.php"
                              data-confirm="Change the status of appointment #<?= $a['id'] ?> (<?= e($a['full_name']) ?>)?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                            <select name="status" class="form-select form-select-sm auto-submit"
                                    aria-label="Change status for appointment #<?= $a['id'] ?>"
                                    data-original="<?= e($a['status']) ?>">
                                <?php foreach (STATUSES as $s): ?>
                                    <option <?= $a['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex gap-1">
                            <a href="appointment_view.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary"
                               title="View details &amp; timeline" aria-label="View appointment #<?= $a['id'] ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                            <a href="appointment_edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"
                               title="Edit appointment" aria-label="Edit appointment #<?= $a['id'] ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="post" action="appointment_delete.php" class="d-inline"
                                  data-confirm="Delete appointment #<?= $a['id'] ?> for <?= e($a['full_name']) ?>? This action cannot be undone."
                                  data-confirm-danger="1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete appointment"
                                        aria-label="Delete appointment #<?= $a['id'] ?>">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted"><?= count($appointments) ?> record(s) found</div>
</div>

<?php require 'includes/footer.php'; ?>
