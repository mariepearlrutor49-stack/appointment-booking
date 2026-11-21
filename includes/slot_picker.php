<?php
/**
 * includes/slot_picker.php
 * ---------------------------------------------------------------
 * Radio-button grid of time slots grouped into Morning & Afternoon
 * so the admin can scan 18 slots effortlessly.
 * Expects: $date (Y-m-d), $booked (from get_booked_slots), $selectedTime ('H:i').
 * Optional: $currentSlot = ['date' => ..., 'time' => 'H:i'] on the edit page.
 */
$currentSlot = $currentSlot ?? ['date' => '', 'time' => ''];
$allSlots    = generate_time_slots();

$groups = [
    ['title' => 'Morning Session (8:00 AM – 12:00 PM)',  'icon' => 'sunrise', 'slots' => array_filter($allSlots, fn($s) => $s < '12:00')],
    ['title' => 'Afternoon Session (12:00 PM – 5:00 PM)','icon' => 'sun',     'slots' => array_filter($allSlots, fn($s) => $s >= '12:00')],
];
?>
<?php foreach ($groups as $gIndex => $group): ?>
    <div class="<?= $gIndex > 0 ? 'mt-4' : '' ?>">
        <div class="d-flex align-items-center gap-2 text-muted small fw-semibold text-uppercase mb-2">
            <i class="bi bi-<?= $group['icon'] ?> text-primary" aria-hidden="true"></i>
            <span><?= e($group['title']) ?></span>
        </div>
        <div class="slot-grid" role="radiogroup" aria-label="<?= e($group['title']) ?>">
            <?php foreach ($group['slots'] as $i => $slot):
                $isOwn    = ($currentSlot['date'] === $date && $currentSlot['time'] === $slot);
                $isBooked = isset($booked[$slot]);
                $isPast   = !$isOwn && is_past_slot($date, $slot);
                $disabled = $isBooked || $isPast;
                $class    = $isBooked ? 'slot-booked' : ($isPast ? 'slot-past' : 'slot-available');
                $label    = $isOwn ? 'Current' : ($isBooked ? 'Booked' : ($isPast ? 'Passed' : 'Available'));
                $icon     = $isOwn ? 'check-circle-fill' : ($isBooked ? 'lock-fill' : ($isPast ? 'dash-circle' : 'check2'));
            ?>
                <div>
                    <input type="radio" class="slot-radio" name="appointment_time" id="slot<?= $i ?>"
                           value="<?= $slot ?>" <?= $disabled ? 'disabled' : '' ?>
                           <?= (!$disabled && $selectedTime === $slot) ? 'checked' : '' ?>>
                    <label for="slot<?= $i ?>" class="slot <?= $class ?>">
                        <span><?= format_time($slot) ?></span>
                        <small><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i> <?= $label ?></small>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
