/**
 * The account dropdown behind the header avatar.
 *
 * Kept to the three things a menu has to get right: it closes when you
 * click away, it closes on Escape and hands focus back to the avatar, and
 * the button reports its state through aria-expanded.
 */
(function () {
    'use strict';

    function init() {
        var button = document.getElementById('account-menu-button');
        var panel = document.getElementById('account-menu-panel');

        if (!button || !panel) {
            return;
        }

        function isOpen() {
            return !panel.hidden;
        }

        function setOpen(open) {
            panel.hidden = !open;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function close(returnFocus) {
            if (!isOpen()) {
                return;
            }

            setOpen(false);

            if (returnFocus) {
                button.focus();
            }
        }

        button.addEventListener('click', function (event) {
            // Without this the document listener below sees the same click
            // and closes the panel again immediately.
            event.stopPropagation();

            setOpen(!isOpen());
        });

        // Clicks inside the panel are for its own controls.
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function () {
            close(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                close(true);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
