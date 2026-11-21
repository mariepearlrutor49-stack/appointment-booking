<?php
/**
 * schedules.php - Pick a date and see available / booked 30-minute slots.
 */
require_once 'includes/auth.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!is_valid_date($date)) {
    $date = date('Y-m-d');
}

$slots  = generate_time_slots();
$booked = get_booked_slots($pdo, $date);

// Count available slots (not booked and not in the past)
$available = 0;
foreach ($slots as $slot) {
    if (!isset($booked[$slot]) && !is_past_slot($date, $slot)) {
        $available++;
    }
}

$totalSlots = count($slots);
$bookedPct  = $totalSlots ? (int) round((count($booked) / $totalSlots) * 100) : 0;

$prevDay  = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDay  = date('Y-m-d', strtotime($date . ' +1 day'));
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$groups = [
    ['title' => 'Morning Session (8:00 AM – 12:00 PM)',   'icon' => 'sunrise', 'slots' => array_filter($slots, fn($s) => $s < '12:00')],
    ['title' => 'Afternoon Session (12:00 PM – 5:00 PM)', 'icon' => 'sun',     'slots' => array_filter($slots, fn($s) => $s >= '12:00')],
];

$pageTitle = 'Check Available Schedules';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-clock text-primary" aria-hidden="true"></i> Clinic Schedule</h1>
        <p class="text-muted small mb-0">Select any green time slot to book an appointment directly.</p>
    </div>
    <a href="book.php?date=<?= e($date) ?>" class="btn btn-primary">
        <i class="bi bi-calendar-plus" aria-hidden="true"></i> Book on <?= format_date($date) ?>
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <a href="?date=<?= $prevDay ?>" class="btn btn-outline-secondary"
                   aria-label="Previous day (<?= format_date($prevDay) ?>)" title="Previous day">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
            </div>
            <div class="col-sm-5 col-md-3">
                <label for="date" class="form-label small fw-semibold mb-1">Select Date</label>
                <input type="date" id="date" name="date" class="form-control" value="<?= e($date) ?>"
                       onchange="this.form.submit()">
            </div>
            <div class="col-auto">
                <a href="?date=<?= $nextDay ?>" class="btn btn-outline-secondary"
                   aria-label="Next day (<?= format_date($nextDay) ?>)" title="Next day">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="col-auto d-flex gap-1">
                <a href="?date=<?= $today ?>" class="btn <?= $date === $today ? 'btn-primary' : 'btn-outline-primary' ?>">Today</a>
                <a href="?date=<?= $tomorrow ?>" class="btn <?= $date === $tomorrow ? 'btn-primary' : 'btn-outline-primary' ?>">Tomorrow</a>
            </div>
            <div class="col-md-auto ms-md-auto small text-muted">
                <div class="d-flex align-items-center gap-2">
                    <span>Occupancy: <strong><?= count($booked) ?>/<?= $totalSlots ?></strong> (<?= $bookedPct ?>%)</span>
                </div>
                <div class="progress mt-1" style="height: 6px; width: 180px;" role="progressbar"
                     aria-label="Daily schedule occupancy" aria-valuenow="<?= $bookedPct ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-primary" style="width: <?= $bookedPct ?>%"></div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0"><i class="bi bi-calendar3 text-primary" aria-hidden="true"></i> <?= date('l, F j, Y', strtotime($date)) ?></h2>
        <div class="d-flex flex-wrap gap-2 small">
            <span class="badge slot-available"><i class="bi bi-check2" aria-hidden="true"></i> Available: <?= $available ?></span>
            <span class="badge slot-booked"><i class="bi bi-lock-fill" aria-hidden="true"></i> Booked: <?= count($booked) ?></span>
            <span class="badge slot-past"><i class="bi bi-dash-circle" aria-hidden="true"></i> Passed / Closed</span>
        </div>
    </div>
    <div class="card-body">
        <?php foreach ($groups as $gIndex => $group): ?>
            <div class="<?= $gIndex > 0 ? 'mt-4' : '' ?>">
                <div class="d-flex align-items-center gap-2 text-muted small fw-semibold text-uppercase mb-2">
                    <i class="bi bi-<?= $group['icon'] ?> text-primary" aria-hidden="true"></i>
                    <span><?= e($group['title']) ?></span>
                </div>
                <div class="slot-grid">
                    <?php foreach ($group['slots'] as $slot): ?>
                        <?php if (isset($booked[$slot])): $b = $booked[$slot]; ?>
                            <!-- Booked: click to view the appointment details -->
                            <a href="appointment_view.php?id=<?= $b['id'] ?>" class="slot slot-booked text-decoration-none"
                               title="<?= e($b['full_name'] . ' — ' . $b['service_name'] . ' (' . $b['status'] . ')') ?>"
                               aria-label="<?= format_time($slot) ?> booked by <?= e($b['full_name']) ?> for <?= e($b['service_name']) ?>">
                                <span><?= format_time($slot) ?></span>
                                <small class="text-truncate"><i class="bi bi-lock-fill" aria-hidden="true"></i> <?= e($b['full_name']) ?></small>
                            </a>
                        <?php elseif (is_past_slot($date, $slot)): ?>
                            <div class="slot slot-past" aria-disabled="true">
                                <span><?= format_time($slot) ?></span>
                                <small><i class="bi bi-dash-circle" aria-hidden="true"></i> Passed</small>
                            </div>
                        <?php else: ?>
                            <!-- Available: click to book this slot -->
                            <a href="book.php?date=<?= e($date) ?>&time=<?= e($slot) ?>" class="slot slot-available"
                               aria-label="Book <?= format_time($slot) ?> on <?= format_date($date) ?>">
                                <span><?= format_time($slot) ?></span>
                                <small><i class="bi bi-plus-circle" aria-hidden="true"></i> Book Slot</small>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
