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

    function init() {
        document.querySelectorAll('input[type="file"][data-preview]')
            .forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
