<?php
/**
 * login.php - Apple-style translucent sheet login on calm daylight surface.
 */
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Already logged in? Go straight to the dashboard.
if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // password_verify() compares the typed password with the stored hash
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            redirect('dashboard.php');
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | AppointEase Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page p-3">

<div class="card login-card">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="brand-badge brand-badge-lg mx-auto mb-3" aria-hidden="true">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <h1 class="h4 fw-bold mb-1">AppointEase Clinic</h1>
            <p class="text-muted small mb-0">Sign in to manage patients &amp; clinic schedules</p>
        </div>

        <?php show_flash(); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 error-summary" role="alert" tabindex="-1">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($username) ?>" placeholder="admin"
                           autocomplete="username" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="password"
                            aria-label="Show password" title="Show or hide password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                Continue <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
