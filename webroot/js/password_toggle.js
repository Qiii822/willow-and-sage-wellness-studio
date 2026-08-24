/**
 * Show/hide control for password fields on the auth pages.
 *
 * Any `input[type=password][data-toggle]` gets an eye button placed inside
 * its right-hand edge. The wrapper and button are built here rather than in
 * the template so the form helper keeps rendering the field, label and
 * validation error as usual.
 *
 * Without JavaScript the field is simply a normal password input, so nothing
 * is lost beyond the convenience of peeking at what you typed.
 */
(function () {
    'use strict';

    function attach(input) {
        var wrapper = document.createElement('div');
        wrapper.className = 'password-wrapper';

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.innerHTML = '<i data-lucide="eye"></i>';

        button.addEventListener('click', function () {
            var hidden = input.type === 'password';

            input.type = hidden ? 'text' : 'password';

            button.setAttribute(
                'aria-label',
                hidden ? 'Hide password' : 'Show password'
            );

            var icon = button.querySelector('[data-lucide], svg');

            if (icon) {
                // Lucide replaces the <i> with an <svg>, so rebuild the node
                // rather than trying to mutate whatever is currently there.
                button.innerHTML =
                    '<i data-lucide="' + (hidden ? 'eye-off' : 'eye') + '"></i>';

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            // Keep the caret where the user left it.
            input.focus();
        });

        wrapper.appendChild(button);
    }

    function init() {
        var inputs = document.querySelectorAll('input[type="password"][data-toggle]');

        Array.prototype.forEach.call(inputs, attach);

        // The layout's own createIcons() call has already run by now, so the
        // buttons added above need a second pass to get their icons.
        if (inputs.length && window.lucide) {
            window.lucide.createIcons();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
