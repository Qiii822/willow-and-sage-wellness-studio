/**
 * Keeps the reminder email's preview in step with the fields as they are
 * typed in, so staff see the email they are writing rather than imagining it.
 *
 * Placeholders are filled with the preview's example customer, and an empty
 * field shows its original wording - the same thing saving it would do.
 * Nothing is sent or saved from here.
 */
(function () {
    'use strict';

    function init() {
        var frame = document.querySelector('.email-preview-frame');
        var preview = document.querySelector('.email-preview');

        if (!frame || !preview) {
            return;
        }

        var sample = {
            '{name}': frame.dataset.sampleName || '',
            '{days}': frame.dataset.sampleDays || ''
        };

        function fill(text) {
            return text.replace(/\{name\}|\{days\}/g, function (tag) {
                return sample[tag];
            });
        }

        document.querySelectorAll('[data-preview-field]').forEach(function (input) {
            var target = preview.querySelector('[data-email-field="' + input.dataset.previewField + '"]');

            if (!target) {
                return;
            }

            input.addEventListener('input', function () {
                var text = input.value.trim() === '' ? input.dataset.default : input.value;

                // innerText turns line breaks into <br>, as the email does.
                target.innerText = fill(text);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
