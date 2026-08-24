/**
 * Live character counters.
 *
 * Any input carrying `data-count` and a `maxlength` gets a "12/20" readout
 * placed in its label, so the limit is visible without spending a line of
 * its own under the field.
 *
 * The counter is built here rather than rendered server-side: with no
 * JavaScript the `maxlength` attribute still caps the field and the server
 * still validates, so nothing is lost beyond the readout itself.
 */
(function () {
    'use strict';

    function attach(input) {
        var max = parseInt(input.getAttribute('maxlength'), 10);

        if (!max) {
            return;
        }

        // Labels are rendered by the form helper, so find ours by id.
        var label = input.id
            ? document.querySelector('label[for="' + input.id + '"]')
            : null;

        if (!label) {
            return;
        }

        var counter = document.createElement('span');
        counter.className = 'field-counter';

        var update = function () {
            // Count characters the way a person reads them: astral symbols
            // (emoji and the like) are one character, not two code units.
            var used = Array.from(input.value).length;

            counter.textContent = used + '/' + max;
            counter.classList.toggle('is-full', used >= max);
        };

        input.addEventListener('input', update);
        label.appendChild(counter);

        // Reflect any value the server put back after a failed submit.
        update();
    }

    function init() {
        var inputs = document.querySelectorAll('input[data-count]');

        Array.prototype.forEach.call(inputs, attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
