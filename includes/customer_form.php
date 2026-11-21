<?php
/**
 * includes/customer_form.php
 * ---------------------------------------------------------------
 * Shared form fields for adding and editing a patient/customer.
 * Expects: $customer (array of values), $errors (array).
 */
?>
<?php if ($errors): ?>
    <div class="alert alert-danger error-summary mb-3" role="alert" tabindex="-1" aria-labelledby="errorSummaryTitle">
        <div class="fw-semibold mb-1" id="errorSummaryTitle">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Please review the highlighted fields:
        </div>
        <ul class="mb-0 small">
            <?php foreach ($errors as $fieldId => $err): ?>
                <li><a href="#<?= e($fieldId) ?>" class="alert-link"><?= e($err) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" id="full_name" name="full_name" maxlength="100" required
               class="form-control <?= invalid_class($errors, 'full_name') ?>"
               value="<?= e($customer['full_name']) ?>" placeholder="e.g. Juan Dela Cruz"
               aria-describedby="full_name_help">
        <?= field_error($errors, 'full_name') ?>
        <div id="full_name_help" class="form-text">Patient's complete legal name.</div>
    </div>
    <div class="col-md-6">
        <label for="contact_number" class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
        <input type="tel" id="contact_number" name="contact_number" maxlength="20" required
               pattern="[0-9+\-\s()]{7,20}"
               class="form-control <?= invalid_class($errors, 'contact_number') ?>"
               value="<?= e($customer['contact_number']) ?>" placeholder="e.g. 09171234567"
               aria-describedby="contact_number_help">
        <?= field_error($errors, 'contact_number') ?>
        <div id="contact_number_help" class="form-text">7–20 digits (mobile or landline).</div>
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label fw-semibold">Email <span class="text-muted small fw-normal">(optional)</span></label>
        <input type="email" id="email" name="email" maxlength="100"
               class="form-control <?= invalid_class($errors, 'email') ?>"
               value="<?= e($customer['email']) ?>" placeholder="name@example.com">
        <?= field_error($errors, 'email') ?>
    </div>
    <div class="col-12">
        <label for="notes" class="form-label fw-semibold">Medical / Patient Notes <span class="text-muted small fw-normal">(optional)</span></label>
        <textarea id="notes" name="notes" rows="3" class="form-control"
                  placeholder="Known allergies, medical history notes, or scheduling preferences..."><?= e($customer['notes']) ?></textarea>
    </div>
</div>
