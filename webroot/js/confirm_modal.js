document.addEventListener('DOMContentLoaded', function () {
    const overlays = document.querySelectorAll('.confirm-modal-overlay');
    let activeOverlay = null;

    overlays.forEach(function (overlay) {
        const form = overlay.dataset.formId
            ? document.getElementById(overlay.dataset.formId)
            : document.querySelector(overlay.dataset.formSelector);
        const okButton = overlay.querySelector('[id$="-confirm-ok"]');
        const cancelButton = overlay.querySelector('[id$="-confirm-cancel"]');

        if (!form || !okButton || !cancelButton) {
            return;
        }

        document.body.appendChild(overlay);
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.zIndex = '1000';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.padding = '20px';
        overlay.style.background = 'rgba(47, 45, 43, 0.45)';

        const hideOverlay = function () {
            overlay.hidden = true;
            overlay.style.display = 'none';
            activeOverlay = null;
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            overlay.hidden = false;
            overlay.style.display = 'flex';
            activeOverlay = overlay;
            okButton.focus();
        });

        okButton.addEventListener('click', function () {
            hideOverlay();
            form.submit();
        });

        cancelButton.addEventListener('click', hideOverlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                hideOverlay();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeOverlay) {
            activeOverlay.hidden = true;
            activeOverlay.style.display = 'none';
            activeOverlay = null;
        }
    });
});