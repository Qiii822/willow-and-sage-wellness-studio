document.addEventListener('DOMContentLoaded', function () {

    const form =
        document.getElementById('user-edit-form');

    const overlay =
        document.getElementById('save-confirm-overlay');

    const okButton =
        document.getElementById('save-confirm-ok');

    const cancelButton =
        document.getElementById('save-confirm-cancel');

    if (!form || !overlay || !okButton || !cancelButton) {
        return;
    }

    const showOverlay = function () {
        overlay.hidden = false;
        okButton.focus();
    };

    const hideOverlay = function () {
        overlay.hidden = true;
    };

    // Intercept the form submission and ask for confirmation first.
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        showOverlay();
    });

    okButton.addEventListener('click', function () {
        hideOverlay();
        // Programmatic submit bypasses the 'submit' listener above,
        // so the form is actually sent without re-triggering the modal.
        form.submit();
    });

    cancelButton.addEventListener('click', hideOverlay);

    // Close when clicking the dimmed backdrop outside the dialog.
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            hideOverlay();
        }
    });

    // Close on Escape for keyboard users.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            hideOverlay();
        }
    });

});
