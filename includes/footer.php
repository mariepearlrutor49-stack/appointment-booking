<?php
/**
 * includes/footer.php
 * ---------------------------------------------------------------
 * Closes the app shell opened in header.php, then adds the
 * confirmation sheet (modal) and scripts.
 */
?>
        </main>
    </div><!-- /.app-main -->
</div><!-- /.app-shell -->

<!-- Confirmation sheet (replaces the browser's confirm() dialog) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-4">
                <div class="confirm-icon mx-auto mb-3" id="confirmModalIcon" aria-hidden="true">
                    <i class="bi bi-question-circle"></i>
                </div>
                <h2 class="h6 fw-bold mb-2" id="confirmModalTitle">Please Confirm</h2>
                <p class="text-muted small mb-4" id="confirmModalMessage">Are you sure you want to continue?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary flex-fill" id="confirmModalCancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary flex-fill" id="confirmModalOk">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
