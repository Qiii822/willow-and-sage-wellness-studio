document.addEventListener('DOMContentLoaded', function () {

    const form =
        document.getElementById('user-edit-form');

    const overlay =
        document.getElementById('save-confirm-overlay');

    const okButton =
        document.getElementById('save-confirm-ok');

    const cancelButton =
        document.getElementById('save-confirm-cancel');

    const deleteForm =
        document.querySelector('.user-delete-actions form');

    const deleteOverlay =
        document.getElementById('delete-confirm-overlay');

    const deleteOkButton =
        document.getElementById('delete-confirm-ok');

    const deleteCancelButton =
        document.getElementById('delete-confirm-cancel');

    const showOverlay = function () {
        overlay.hidden = false;
        okButton.focus();
    };

    const hideOverlay = function () {
        overlay.hidden = true;
    };

    if (form && overlay && okButton && cancelButton) {
        // Intercept the form submission and ask for confirmation first.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            showOverlay();
        });

        okButton.addEventListener('click', function () {
            hideOverlay();
            // Programmatic submit bypasses the 'submit' listener above.
            form.submit();
        });

        cancelButton.addEventListener('click', hideOverlay);

        // Close when clicking the dimmed backdrop outside the dialog.
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                hideOverlay();
            }
        });
    }

    if (deleteForm && deleteOverlay && deleteOkButton && deleteCancelButton) {
        const showDeleteOverlay = function () {
            deleteOverlay.hidden = false;
            deleteOkButton.focus();
        };

        const hideDeleteOverlay = function () {
            deleteOverlay.hidden = true;
        };

        deleteForm.addEventListener('submit', function (event) {
            event.preventDefault();
            showDeleteOverlay();
        });

        deleteOkButton.addEventListener('click', function () {
            hideDeleteOverlay();
            deleteForm.submit();
        });

        deleteCancelButton.addEventListener('click', hideDeleteOverlay);

        deleteOverlay.addEventListener('click', function (event) {
            if (event.target === deleteOverlay) {
                hideDeleteOverlay();
            }
        });
    }

    // Close on Escape for keyboard users.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay && !overlay.hidden) {
            hideOverlay();
        }

        if (event.key === 'Escape' && deleteOverlay && !deleteOverlay.hidden) {
            deleteOverlay.hidden = true;
        }
    });

});
