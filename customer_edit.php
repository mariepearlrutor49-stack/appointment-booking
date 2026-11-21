<?php
/**
 * customer_edit.php - Edit an existing patient/customer (UPDATE).
 */
require_once 'includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('Patient not found.', 'danger');
    redirect('customers.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$data, $errors] = validate_customer($_POST);
    $customer = array_merge($customer, $data); // keep typed values on error

    if (!$errors) {
        $stmt = $pdo->prepare(
            'UPDATE customers SET full_name = ?, contact_number = ?, email = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['contact_number'],
            $data['email'] !== '' ? $data['email'] : null,
            $data['notes'] !== '' ? $data['notes'] : null,
            $id,
        ]);
        flash('Patient details updated.');
        redirect('customers.php');
    }
}

$pageTitle = 'Edit Patient';
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="customers.php">Patients</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit <?= e($customer['full_name']) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h1 class="h5 mb-0"><i class="bi bi-pencil-square text-primary" aria-hidden="true"></i> Edit Patient Details</h1>
            </div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <?php require 'includes/customer_form.php'; ?>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save" aria-hidden="true"></i> Save Changes</button>
                        <a href="customers.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
