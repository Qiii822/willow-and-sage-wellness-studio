/**
 * Shows the picture you just chose, before it is uploaded.
 *
 * Without this the box still displays the photo already on the site, so
 * there is no way to tell whether the right file was picked - or that
 * anything was picked at all.
 *
 * Reads the file straight from the browser; nothing is sent until the form
 * is saved.
 */
(function () {
    'use strict';

    function attach(input) {
        var name = input.dataset.preview;
        var box = document.querySelector('[data-preview-for="' + name + '"]');

        if (!box) {
            return;
        }

        var image = box.querySelector('img');
        var note = box.querySelector('.content-image-note');
        var objectUrl = null;

        function clearObjectUrl() {
            if (objectUrl) {
                // Each createObjectURL holds the file in memory until revoked.
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        function restoreOriginal() {
            clearObjectUrl();

            var original = image.dataset.originalSrc || '';

            image.src = original;
            box.hidden = original === '';

            if (note) {
                note.hidden = true;
            }
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            // Cleared the picker, or picked nothing.
            if (!file) {
                restoreOriginal();

                return;
            }

            /*
             * The server checks the real contents on upload; this only
             * decides whether a preview is worth attempting.
             */
            if (file.type.indexOf('image/') !== 0) {
                restoreOriginal();

                return;
            }

            clearObjectUrl();
            objectUrl = URL.createObjectURL(file);

            image.src = objectUrl;
            box.hidden = false;

            if (note) {
                note.hidden = false;
            }
        });

        // A file kept by the browser's back/forward cache is no longer valid.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                input.value = '';
                restoreOriginal();
            }
        });
    }

    /**
     * Up/down reordering of the page's blocks.
     *
     * Rows carry their key in a hidden input, so moving the row in the DOM
     * is all that is needed - the form posts them in their new order and the
     * server reads that sequence. No position numbers to keep in step.
     */
    function attachReordering(list) {
        function refresh() {
            var rows = list.querySelectorAll('.section-row');

            rows.forEach(function (row, index) {
                var up = row.querySelector('[data-move="up"]');
                var down = row.querySelector('[data-move="down"]');

                // Nothing to swap with at the ends.
                if (up) {
                    up.disabled = index === 0;
                }

                if (down) {
                    down.disabled = index === rows.length - 1;
                }
            });
        }

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-move]');

            if (!button) {
                return;
            }

            var row = button.closest('.section-row');

            if (button.dataset.move === 'up') {
                var previous = row.previousElementSibling;

                if (previous) {
                    list.insertBefore(row, previous);
                }
            } else {
                var next = row.nextElementSibling;

                if (next) {
                    list.insertBefore(next, row);
                }
            }

            refresh();

            // Keep the keyboard on the button that just moved.
            button.focus();
        });

        list.addEventListener('change', function (event) {
            if (event.target.type === 'checkbox') {
                event.target.closest('.section-row')
                    .classList.toggle('is-hidden', !event.target.checked);
            }
        });

        refresh();
    }

    function init() {
        document.querySelectorAll('input[type="file"][data-preview]')
            .forEach(attach);

        document.querySelectorAll('[data-section-list]')
            .forEach(attachReordering);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
