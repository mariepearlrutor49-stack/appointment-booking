<?php
/**
 * customer_add.php - Register a walk-in patient/customer (INSERT).
 * Option to proceed directly to booking after saving.
 */
require_once 'includes/auth.php';

$customer = ['full_name' => '', 'contact_number' => '', 'email' => '', 'notes' => ''];
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$customer, $errors] = validate_customer($_POST);

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO customers (full_name, contact_number, email, notes) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $customer['full_name'],
            $customer['contact_number'],
            $customer['email'] !== '' ? $customer['email'] : null,
            $customer['notes'] !== '' ? $customer['notes'] : null,
        ]);
        $newId = (int) $pdo->lastInsertId();

        flash('Patient "' . $customer['full_name'] . '" registered successfully.');

        // Which button was clicked?
        if (($_POST['action'] ?? '') === 'save_book') {
            redirect('book.php?customer_id=' . $newId);
        }
        redirect('customers.php');
    }
}

$pageTitle = 'Register Walk-in Patient';
require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="customers.php">Patients</a></li>
        <li class="breadcrumb-item active" aria-current="page">Register Walk-in</li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h1 class="h5 mb-0"><i class="bi bi-person-plus text-primary" aria-hidden="true"></i> Register Walk-in Patient</h1>
            </div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <?php require 'includes/customer_form.php'; ?>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" name="action" value="save_book" class="btn btn-primary">
                            <i class="bi bi-calendar-plus" aria-hidden="true"></i> Save &amp; Book Appointment
                        </button>
                        <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                            <i class="bi bi-save" aria-hidden="true"></i> Save Patient Only
                        </button>
                        <a href="customers.php" class="btn btn-outline-secondary ms-auto">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
