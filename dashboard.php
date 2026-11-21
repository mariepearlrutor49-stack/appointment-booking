<?php
/**
 * dashboard.php - Apple Health-inspired Overview: Large Title header,
 * Summary Platter Cards, Today's Queue, and Grouped Clinic Actions.
 */
require_once 'includes/auth.php';

$today = date('Y-m-d');

// ---- Summary counts for today (one query, grouped by status) ----
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM appointments WHERE appointment_date = ? GROUP BY status');
$stmt->execute([$today]);
$counts = array_fill_keys(STATUSES, 0);
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$totalToday = array_sum($counts);

// ---- Today's upcoming appointments (not yet finished) ----
$stmt = $pdo->prepare(
    "SELECT a.id, a.appointment_time, a.status, a.notes,
            c.full_name, c.contact_number, s.name AS service_name
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN services  s ON s.id = a.service_id
     WHERE a.appointment_date = ?
       AND a.status IN ('Pending', 'Confirmed')
     ORDER BY a.appointment_time"
);
$stmt->execute([$today]);
$upcoming = $stmt->fetchAll();

// ---- Overall numbers ----
$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE is_active = 1')->fetchColumn();

// Slots still bookable today (not booked and not already past)
$allSlots    = generate_time_slots();
$bookedToday = get_booked_slots($pdo, $today);
$freeSlots   = 0;
foreach ($allSlots as $slot) {
    if (!isset($bookedToday[$slot]) && !is_past_slot($today, $slot)) {
        $freeSlots++;
    }
}
$occupancyPct = count($allSlots) ? (int) round((count($bookedToday) / count($allSlots)) * 100) : 0;

$cards = [
    ['label' => "Today's Visits",  'sub' => 'All scheduled',     'value' => $totalToday,          'icon' => 'calendar2-check-fill', 'accent' => 'emerald', 'href' => 'appointments.php?date=' . $today],
    ['label' => 'Pending',         'sub' => 'Awaiting check-in', 'value' => $counts['Pending'],   'icon' => 'hourglass-split',      'accent' => 'amber',   'href' => 'appointments.php?date=' . $today . '&status=Pending'],
    ['label' => 'Completed',       'sub' => 'Patients seen',     'value' => $counts['Completed'], 'icon' => 'patch-check-fill',     'accent' => 'teal',    'href' => 'appointments.php?date=' . $today . '&status=Completed'],
    ['label' => 'Cancelled',       'sub' => 'Released slots',    'value' => $counts['Cancelled'], 'icon' => 'x-circle-fill',        'accent' => 'slate',   'href' => 'appointments.php?date=' . $today . '&status=Cancelled'],
];

$links = [
    ['href' => 'customer_add.php', 'icon' => 'person-plus-fill',     'label' => 'Register Walk-in',    'sub' => 'New patient record'],
    ['href' => 'schedules.php',    'icon' => 'calendar2-week-fill',  'label' => 'Check Schedules',     'sub' => '30-minute slots'],
    ['href' => 'book.php',         'icon' => 'plus-circle-fill',     'label' => 'Book Appointment',    'sub' => 'Assign slot & service'],
    ['href' => 'appointments.php', 'icon' => 'clipboard2-pulse-fill','label' => 'Manage Appointments', 'sub' => 'Filter, edit, status'],
    ['href' => 'history.php',      'icon' => 'clock-history',        'label' => 'View History',        'sub' => 'Past visits & revenue'],
    ['href' => 'customers.php',    'icon' => 'people-fill',          'label' => 'Patient Directory',   'sub' => $totalCustomers . ' registered'],
];

$pageTitle = 'Overview';
require 'includes/header.php';
?>

<!-- Apple Health Large Title Header -->
<div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
    <div>
        <div class="apple-eyebrow text-uppercase"><?= date('l, F j') ?></div>
        <h1 class="apple-large-title mb-0">Clinic Overview</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="customer_add.php" class="btn btn-outline-primary">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Walk-in Patient
        </a>
        <a href="book.php" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> New Appointment
        </a>
    </div>
</div>

<!-- Apple Health Summary Platters -->
<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e($card['href']) ?>" class="card stat-card stat-<?= $card['accent'] ?> h-100 text-decoration-none text-reset">
                <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="stat-icon" aria-hidden="true"><i class="bi bi-<?= $card['icon'] ?>"></i></span>
                            <span class="fw-semibold small text-secondary"><?= e($card['label']) ?></span>
                        </div>
                        <i class="bi bi-chevron-right stat-chevron text-muted small" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="apple-metric"><?= $card['value'] ?></div>
                        <div class="text-muted small"><?= e($card['sub']) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Today's upcoming appointments -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 fw-bold mb-0">Today's Upcoming Queue</h2>
                    <small class="text-muted fw-normal">Pending &amp; Confirmed visits in chronological order</small>
                </div>
                <a href="schedules.php?date=<?= $today ?>" class="badge slot-available text-decoration-none">
                    <i class="bi bi-clock" aria-hidden="true"></i> <?= $freeSlots ?> free slot(s) today
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!$upcoming): ?>
                    <div class="empty-state py-5 text-center px-3">
                        <div class="empty-state-icon mx-auto mb-2" aria-hidden="true"><i class="bi bi-calendar2-check"></i></div>
                        <h3 class="h6 fw-bold mb-1">No Upcoming Visits Today</h3>
                        <p class="text-muted small mb-3">All scheduled patients have been seen, or no slots are booked yet.</p>
                        <a href="book.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Book an Appointment</a>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($upcoming as $a): ?>
                            <tr>
                                <td class="fw-semibold text-nowrap">
                                    <span class="time-chip"><?= format_time($a['appointment_time']) ?></span>
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
                                <td><?= status_badge($a['status']) ?></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <?php if ($a['status'] === 'Pending'): ?>
                                            <form method="post" action="appointment_status.php" class="d-inline"
                                                  data-confirm="Confirm appointment for <?= e($a['full_name']) ?> at <?= format_time($a['appointment_time']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="status" value="Confirmed">
                                                <input type="hidden" name="back" value="dashboard.php">
                                                <button class="btn btn-sm btn-outline-primary" title="Confirm appointment"
                                                        aria-label="Confirm appointment for <?= e($a['full_name']) ?>">
                                                    <i class="bi bi-check2" aria-hidden="true"></i> Confirm
                                                </button>
                                            </form>
                                        <?php elseif ($a['status'] === 'Confirmed'): ?>
                                            <form method="post" action="appointment_status.php" class="d-inline"
                                                  data-confirm="Mark <?= e($a['full_name']) ?>'s visit as Completed?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="status" value="Completed">
                                                <input type="hidden" name="back" value="dashboard.php">
                                                <button class="btn btn-sm btn-outline-success" title="Mark Completed"
                                                        aria-label="Mark appointment for <?= e($a['full_name']) ?> as Completed">
                                                    <i class="bi bi-check2-all" aria-hidden="true"></i> Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="appointment_view.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary"
                                           title="View details" aria-label="View details for appointment #<?= $a['id'] ?>">
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- iOS-style Grouped Clinic Actions + Daily Capacity Platter -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h2 class="h6 fw-bold mb-0">Quick Actions</h2>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="apple-action-list">
                    <?php foreach ($links as $link): ?>
                        <a href="<?= $link['href'] ?>" class="apple-action-row">
                            <span class="quick-link-icon" aria-hidden="true">
                                <i class="bi bi-<?= $link['icon'] ?>"></i>
                            </span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold small text-dark"><?= e($link['label']) ?></span>
                                <small class="text-muted" style="font-size: 0.74rem;"><?= e($link['sub']) ?></small>
                            </span>
                            <i class="bi bi-chevron-right text-muted small" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="occupancy-box p-3 rounded-4 mt-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">Today's Capacity</span>
                        <span class="fw-bold text-primary"><?= count($bookedToday) ?> of <?= count($allSlots) ?> slots</span>
                    </div>
                    <div class="progress" style="height: 6px;" role="progressbar"
                         aria-label="Today's slot occupancy" aria-valuenow="<?= $occupancyPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-primary" style="width: <?= $occupancyPct ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-2">
                        <span>Patients: <strong><?= $totalCustomers ?></strong></span>
                        <span>Confirmed today: <strong><?= $counts['Confirmed'] ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
