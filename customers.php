<?php
/**
 * customers.php - List of customers with search, edit, delete, and quick booking.
 */
require_once 'includes/auth.php';

// ---- Delete (POST only, with confirmation modal in the browser) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    // Appointments of this customer are removed too (ON DELETE CASCADE)
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->execute([(int) $_POST['delete_id']]);
    flash('Patient record deleted.', 'info');
    redirect('customers.php');
}

// ---- Search ----
$search = trim($_GET['q'] ?? '');
$sql = 'SELECT c.*, COUNT(a.id) AS appointment_count
        FROM customers c
        LEFT JOIN appointments a ON a.customer_id = c.id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE c.full_name LIKE ? OR c.contact_number LIKE ? OR c.email LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= ' GROUP BY c.id ORDER BY c.full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Patients';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-people text-primary" aria-hidden="true"></i> Walk-in Patients</h1>
        <p class="text-muted small mb-0">Registered patients, contact details, and quick booking shortcuts.</p>
    </div>
    <a href="customer_add.php" class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> Register Walk-in</a>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-6">
                <label for="patientSearch" class="form-label small fw-semibold mb-1">Search Patient</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="patientSearch" name="q" class="form-control"
                           placeholder="Search name, contact number or email..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
                <?php if ($search !== ''): ?><a href="customers.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>#</th><th>Patient Name</th><th>Contact</th><th>Email</th><th>Notes</th><th class="text-center">Visits</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$customers): ?>
                <tr>
                    <td colspan="7" class="p-0">
                        <div class="empty-state py-5 text-center px-3">
                            <div class="empty-state-icon mx-auto mb-2" aria-hidden="true"><i class="bi bi-person-plus"></i></div>
                            <h2 class="h6 fw-semibold mb-1">No patients found</h2>
                            <p class="text-muted small mb-3">Register a walk-in patient to book their first clinic appointment.</p>
                            <a href="customer_add.php" class="btn btn-sm btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> Register Walk-in</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td class="text-muted fw-semibold">#<?= $c['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= avatar_initials($c['full_name']) ?>
                            <span class="fw-semibold"><?= e($c['full_name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($c['contact_number']) ?></td>
                    <td><?= $c['email'] ? e($c['email']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small text-muted"><?= e(mb_strimwidth($c['notes'] ?? '', 0, 40, '...')) ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark border"><?= $c['appointment_count'] ?></span></td>
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex gap-1">
                            <a href="book.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary"
                               title="Book appointment for <?= e($c['full_name']) ?>"
                               aria-label="Book appointment for <?= e($c['full_name']) ?>">
                                <i class="bi bi-calendar-plus" aria-hidden="true"></i> Book
                            </a>
                            <a href="history.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary"
                               title="View visit history" aria-label="View history for <?= e($c['full_name']) ?>">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                            </a>
                            <a href="customer_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"
                               title="Edit patient details" aria-label="Edit <?= e($c['full_name']) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="post" class="d-inline"
                                  data-confirm="Delete <?= e($c['full_name']) ?>? All of their appointments will also be deleted."
                                  data-confirm-danger="1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete patient"
                                        aria-label="Delete <?= e($c['full_name']) ?>">
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
    <div class="card-footer small text-muted"><?= count($customers) ?> patient(s) registered</div>
</div>

<?php require 'includes/footer.php'; ?>
