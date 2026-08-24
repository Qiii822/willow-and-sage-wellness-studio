/**
 * Stops a form being submitted twice.
 *
 * Marks any `form[data-guard]` as busy on submit: the button is disabled and
 * relabelled so an impatient second click does nothing and the wait is
 * visible.
 *
 * Server-side state is what actually keeps a double submit harmless; this
 * only removes the easiest way to trigger one.
 */
(function () {
    'use strict';

    function attach(form) {
        var button = form.querySelector('button[type="submit"], button:not([type])');

        if (!button) {
            return;
        }

        var originalLabel = button.textContent;

        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === '1') {
                // Already on its way; swallow the extra click.
                event.preventDefault();

                return;
            }

            // Let the browser's own required/type checks run first.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            form.dataset.submitting = '1';
            button.setAttribute('aria-busy', 'true');
            button.textContent = button.dataset.busyLabel || 'Saving…';

            /*
             * Disabled only after the current task, so the button's own
             * name/value still makes it into the submitted data.
             */
            window.setTimeout(function () {
                button.disabled = true;
            }, 0);
        });

        /*
         * Coming back via the browser's history can restore the page with the
         * button still disabled, which would leave the form unusable.
         */
        window.addEventListener('pageshow', function () {
            delete form.dataset.submitting;
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = originalLabel;
        });
    }

    function init() {
        var forms = document.querySelectorAll('form[data-guard]');

        Array.prototype.forEach.call(forms, attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
