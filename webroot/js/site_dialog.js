/**
 * The site's own confirm dialog, in place of the browser's.
 *
 * Every "are you sure?" on the site is a CakePHP 'confirm' option, which
 * renders as a data-confirm-message attribute plus an inline onclick that
 * calls the browser's confirm(). That box looks like the browser, not the
 * studio, and cannot be styled - so clicks on those elements are caught
 * here first, on the way down, before the inline handler ever runs, and
 * the question is asked in a dialog of the site's own.
 *
 * Saying yes repeats the original click with the browser's confirm()
 * answering yes by itself, so the element does exactly what it always
 * did - follows its link, submits its form, posts its hidden form. Nothing
 * that renders a confirm has to change.
 *
 * Optional, on the same element:
 *   data-confirm-label  the yes button's words (default "Confirm")
 *   data-cancel-label   the no button's words (default "Go back")
 *   data-confirm-danger present when yes cannot be undone - shown in red
 *
 * Scripts can ask too: window.siteConfirm(message, options) resolves to
 * true or false.
 *
 * Without scripting, or before this has loaded, the inline confirm() still
 * runs - the browser's box, but never no question at all.
 */
(function () {
    'use strict';

    var dialog = null;
    var bypass = false;

    /** Settles the question on show, once, with the answer given. */
    var answer = null;

    function settle(confirmed) {
        var done = answer;

        answer = null;

        if (dialog.open) {
            dialog.close();
        }

        if (done) {
            done(confirmed);
        }
    }

    /**
     * Built once, on first use, so a page that never asks pays nothing.
     */
    function build() {
        dialog = document.createElement('dialog');
        dialog.className = 'site-dialog';
        dialog.setAttribute('aria-labelledby', 'site-dialog-message');
        dialog.innerHTML =
            '<p class="site-dialog-message" id="site-dialog-message"></p>' +
            '<div class="site-dialog-actions">' +
            '<button type="button" class="site-dialog-cancel" value="no"></button>' +
            '<button type="button" class="site-dialog-confirm" value="yes"></button>' +
            '</div>';

        document.body.appendChild(dialog);

        /*
         * Answered straight from the click rather than from the dialog's
         * own "close" event, which the browser fires a moment later and, in
         * a tab in the background, can hold back for a good while.
         */
        dialog.addEventListener('click', function (event) {
            var button = event.target.closest('button');

            if (button) {
                settle(button.value === 'yes');

                return;
            }

            // A click on the dimmed page around it, not on the dialog itself.
            if (event.target === dialog) {
                settle(false);
            }
        });

        // Escape, which <dialog> closes by itself.
        dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            settle(false);
        });

        return dialog;
    }

    /**
     * @param {string} message
     * @param {{confirmLabel?: string, cancelLabel?: string, danger?: boolean}} [options]
     * @return {Promise<boolean>}
     */
    function siteConfirm(message, options) {
        options = options || {};

        // A browser too old for <dialog>: its own box is the fallback.
        if (typeof HTMLDialogElement !== 'function') {
            return Promise.resolve(window.confirm(message));
        }

        var box = dialog || build();
        var yes = box.querySelector('.site-dialog-confirm');

        box.querySelector('.site-dialog-message').textContent = message;
        box.querySelector('.site-dialog-cancel').textContent = options.cancelLabel || 'Go back';
        yes.textContent = options.confirmLabel || 'Confirm';
        yes.classList.toggle('is-danger', !!options.danger);

        // A question still on show is answered "no" before the next is asked.
        if (answer) {
            settle(false);
        }

        return new Promise(function (resolve) {
            answer = resolve;

            box.showModal();

            /*
             * The safe answer has the focus, so Enter or a stray key press
             * never confirms something that cannot be undone. Escape closes
             * it as "no", which <dialog> does by itself.
             */
            box.querySelector('.site-dialog-cancel').focus();
        });
    }

    window.siteConfirm = siteConfirm;

    /*
     * Capture phase, on the document: this runs before the element's own
     * inline onclick, and stopping the event here means that handler - and
     * the browser's box in it - never runs for the first click.
     */
    document.addEventListener('click', function (event) {
        if (bypass) {
            return;
        }

        var element = event.target.closest('[data-confirm-message]');

        // Only a plain click: a new-tab click on a link is the browser's.
        if (!element || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        siteConfirm(element.dataset.confirmMessage, {
            confirmLabel: element.dataset.confirmLabel,
            cancelLabel: element.dataset.cancelLabel,
            danger: element.hasAttribute('data-confirm-danger'),
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            // Once more, for real, with the browser's question answered.
            var browserConfirm = window.confirm;

            bypass = true;
            window.confirm = function () {
                return true;
            };

            try {
                element.click();
            } finally {
                window.confirm = browserConfirm;
                bypass = false;
            }
        });
    }, true);
}());
