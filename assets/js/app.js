/* =========================================================
   AppointEase Clinic - UX Interaction Scripts
   ========================================================= */

// 1. Focus error summary banner when present (WCAG error-summary pattern)
const errorSummary = document.querySelector('.error-summary[tabindex="-1"]');
if (errorSummary) {
    errorSummary.focus();
}

// 2. Accessible Bootstrap Confirmation Modal (replaces native blocking confirm())
const confirmModalEl = document.getElementById('confirmModal');
let pendingAction = null;

function askConfirmation(message, isDanger, onConfirm, onCancel) {
    if (!confirmModalEl || typeof bootstrap === 'undefined') {
        if (confirm(message)) onConfirm();
        else if (onCancel) onCancel();
        return;
    }
    const modal   = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
    const msgEl   = document.getElementById('confirmModalMessage');
    const titleEl = document.getElementById('confirmModalTitle');
    const iconEl  = document.getElementById('confirmModalIcon');
    const okBtn   = document.getElementById('confirmModalOk');

    msgEl.textContent   = message;
    titleEl.textContent = isDanger ? 'Confirm Deletion' : 'Please Confirm';
    iconEl.classList.toggle('confirm-danger', Boolean(isDanger));
    iconEl.innerHTML    = isDanger
        ? '<i class="bi bi-exclamation-triangle-fill"></i>'
        : '<i class="bi bi-question-circle-fill"></i>';
    okBtn.className     = 'btn flex-fill ' + (isDanger ? 'btn-danger' : 'btn-primary');
    okBtn.textContent   = isDanger ? 'Yes, Proceed' : 'Confirm';

    pendingAction = { confirmed: false, onConfirm, onCancel };
    modal.show();
}

if (confirmModalEl) {
    document.getElementById('confirmModalOk').addEventListener('click', function () {
        if (pendingAction) {
            pendingAction.confirmed = true;
            const fn = pendingAction.onConfirm;
            bootstrap.Modal.getInstance(confirmModalEl).hide();
            fn();
        }
    });
    confirmModalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingAction && !pendingAction.confirmed && pendingAction.onCancel) {
            pendingAction.onCancel();
        }
        pendingAction = null;
    });
}

// Intercept forms with data-confirm="message"
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form.dataset.confirm && !form.dataset.confirmedOk) {
        event.preventDefault();
        const submitter = event.submitter;
        askConfirmation(
            form.dataset.confirm,
            form.dataset.confirmDanger === '1',
            function () {
                form.dataset.confirmedOk = '1';
                if (submitter && submitter.name) {
                    const hidden = document.createElement('input');
                    hidden.type  = 'hidden';
                    hidden.name  = submitter.name;
                    hidden.value = submitter.value;
                    form.appendChild(hidden);
                }
                form.submit();
            }
        );
        return;
    }

    // 3. Submit Loading State (prevents accidental double clicks)
    const submitter = event.submitter || form.querySelector('button[type="submit"]');
    if (submitter && form.method.toLowerCase() === 'post' && !submitter.dataset.loading) {
        submitter.dataset.loading = '1';
        if (submitter.name) {
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = submitter.name;
            hidden.value = submitter.value;
            form.appendChild(hidden);
        }
        submitter.disabled = true;
        submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
    }
});

// 4. Auto-submit a status <select> when it changes (Manage Appointments list)
document.querySelectorAll('.auto-submit').forEach(function (select) {
    select.addEventListener('change', function () {
        const form = select.closest('form');
        const msg  = form.dataset.confirm || 'Change appointment status?';
        askConfirmation(
            msg,
            select.value === 'Cancelled' || select.value === 'No-show',
            function () {
                form.dataset.confirmedOk = '1';
                form.submit();
            },
            function () {
                select.value = select.dataset.original; // revert if cancelled
            }
        );
    });
});

// 5. Filter a <select> list with a text box (patient search on booking form)
const customerSearch = document.getElementById('customerSearch');
if (customerSearch) {
    const select = document.getElementById('customer_id');
    customerSearch.addEventListener('input', function () {
        const term = customerSearch.value.toLowerCase();
        Array.from(select.options).forEach(function (opt) {
            if (!opt.value) return;
            opt.hidden = !opt.text.toLowerCase().includes(term);
        });
    });
}

// 6. Live Booking Summary Receipt & Service Description helper on book.php
const bookingForm = document.getElementById('bookingForm');
if (bookingForm) {
    const patientSel  = document.getElementById('customer_id');
    const serviceSel  = document.getElementById('service_id');
    const serviceHelp = document.getElementById('serviceDescHelp');
    const sumPatient  = document.getElementById('summaryPatient');
    const sumService  = document.getElementById('summaryService');
    const sumTime     = document.getElementById('summaryTime');

    function updateBookingSummary() {
        if (patientSel && sumPatient) {
            const opt = patientSel.options[patientSel.selectedIndex];
            sumPatient.textContent = (opt && opt.value) ? opt.text : '—';
        }
        if (serviceSel && sumService) {
            const opt = serviceSel.options[serviceSel.selectedIndex];
            sumService.textContent = (opt && opt.value) ? opt.text : '—';
            if (serviceHelp) {
                serviceHelp.textContent = (opt && opt.dataset.desc) ? opt.dataset.desc : '';
            }
        }
        if (sumTime) {
            const checkedSlot = bookingForm.querySelector('input[name="appointment_time"]:checked');
            if (checkedSlot) {
                const label = bookingForm.querySelector('label[for="' + checkedSlot.id + '"] span');
                sumTime.textContent = label ? label.textContent : checkedSlot.value;
            } else {
                sumTime.textContent = 'Pick a slot';
            }
        }
    }

    bookingForm.addEventListener('change', updateBookingSummary);
    updateBookingSummary();
}

// 7. Inline Blur Validation for required inputs
document.querySelectorAll('form[novalidate] input[required], form[novalidate] select[required]').forEach(function (field) {
    field.addEventListener('blur', function () {
        if (!field.checkValidity()) {
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    });
    field.addEventListener('input', function () {
        if (field.classList.contains('is-invalid') && field.checkValidity()) {
            field.classList.remove('is-invalid');
        }
    });
});

// 8. Password visibility toggle (Login & Change Password)
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.innerHTML = show
            ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
            : '<i class="bi bi-eye" aria-hidden="true"></i>';
    });
});

// 9. Auto-hide flash messages after 5 seconds
setTimeout(function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Alert.getOrCreateInstance(el).close();
        }
    });
}, 5000);

// 10. Booking / edit forms: reload slots when the date changes
document.querySelectorAll('[data-reload-slots]').forEach(function (input) {
    input.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('date', input.value);
        url.searchParams.delete('time');
        (input.dataset.keep || '').split(',').filter(Boolean).forEach(function (name) {
            const field = input.form.elements[name];
            if (field && field.value) url.searchParams.set(name, field.value);
        });
        window.location.href = url.toString();
    });
});
