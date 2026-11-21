<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------
 * macOS-style app shell: translucent sidebar (source list) + toolbar.
 * - Desktop (lg+): sidebar is always visible on the left.
 * - Mobile: sidebar slides in as a Bootstrap offcanvas drawer.
 * Set $pageTitle before including. footer.php closes the shell.
 */
$pageTitle   = $pageTitle ?? 'Clinic Appointment System';
$currentPage = basename($_SERVER['PHP_SELF']);

/** Sidebar menu, grouped like a macOS source list. Add pages here. */
$menu = [
    'Clinic' => [
        ['href' => 'dashboard.php',    'icon' => 'grid-1x2',        'label' => 'Overview',     'pages' => ['dashboard.php']],
        ['href' => 'schedules.php',    'icon' => 'calendar2-week',  'label' => 'Schedules',    'pages' => ['schedules.php']],
        ['href' => 'book.php',         'icon' => 'plus-circle',     'label' => 'Book',         'pages' => ['book.php']],
    ],
    'Records' => [
        ['href' => 'customers.php',    'icon' => 'people',          'label' => 'Patients',     'pages' => ['customers.php', 'customer_add.php', 'customer_edit.php']],
        ['href' => 'appointments.php', 'icon' => 'clipboard2-pulse','label' => 'Appointments', 'pages' => ['appointments.php', 'appointment_edit.php', 'appointment_view.php']],
        ['href' => 'history.php',      'icon' => 'clock-history',   'label' => 'History',      'pages' => ['history.php']],
    ],
];
$adminName = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | AppointEase Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<a class="skip-link" href="#mainContent">Skip to main content</a>

<div class="app-shell">

    <!-- Sidebar (source list) -->
    <aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="offcanvas-header d-lg-none">
            <span class="fw-semibold">Menu</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
        </div>
        <div class="offcanvas-body">
            <div class="sidebar-inner">
                <a class="sidebar-brand" href="dashboard.php">
                    <span class="brand-badge" aria-hidden="true"><i class="bi bi-heart-pulse-fill"></i></span>
                    <span>
                        <span class="d-block fw-bold lh-1">AppointEase</span>
                        <small class="text-muted">Clinic Admin</small>
                    </span>
                </a>

                <nav class="flex-grow-1">
                    <?php foreach ($menu as $section => $items): ?>
                        <div class="side-label"><?= e($section) ?></div>
                        <ul class="list-unstyled mb-3">
                            <?php foreach ($items as $item):
                                $isActive = in_array($currentPage, $item['pages'], true); ?>
                                <li>
                                    <a href="<?= $item['href'] ?>" class="side-link <?= $isActive ? 'active' : '' ?>"
                                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                                        <i class="bi bi-<?= $item['icon'] ?><?= $isActive ? '-fill' : '' ?>" aria-hidden="true"></i>
                                        <span><?= e($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </nav>

                <!-- Account -->
                <div class="sidebar-account dropup">
                    <button class="account-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= avatar_initials($adminName) ?>
                        <span class="text-start flex-grow-1 text-truncate">
                            <span class="d-block fw-semibold small text-truncate"><?= e($adminName) ?></span>
                            <small class="text-muted">Administrator</small>
                        </span>
                        <i class="bi bi-chevron-expand text-muted" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu apple-dropdown border-0 w-100 mb-2">
                        <li><a class="dropdown-item" href="change_password.php"><i class="bi bi-key me-2" aria-hidden="true"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main column -->
    <div class="app-main">
        <header class="app-toolbar">
            <button class="btn toolbar-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-layout-sidebar" aria-hidden="true"></i>
            </button>
            <div class="toolbar-title text-truncate"><?= e($pageTitle) ?></div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="toolbar-date d-none d-md-inline"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= date('D, M j') ?></span>
            </div>
        </header>

        <main id="mainContent" class="app-content" tabindex="-1">
            <?php show_flash(); ?>
