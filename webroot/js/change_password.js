document.addEventListener('DOMContentLoaded', function () {

    // Initialise Lucide icons
    if (window.lucide) {
        lucide.createIcons();
    }

    const toggleButtons =
        document.querySelectorAll('.password-toggle');

    toggleButtons.forEach(function (button) {

        button.addEventListener('click', function () {

            const targetId =
                button.dataset.passwordTarget;

            const passwordInput =
                document.getElementById(targetId);

            if (!passwordInput) {
                return;
            }

            const hidden =
                passwordInput.type === 'password';

            passwordInput.type =
                hidden ? 'text' : 'password';

            button.setAttribute(
                'aria-label',
                hidden ? 'Hide password' : 'Show password'
            );

            const icon =
                button.querySelector('[data-lucide]');

            if (icon) {
                icon.setAttribute(
                    'data-lucide',
                    hidden ? 'eye-off' : 'eye'
                );

                if (window.lucide) {
                    lucide.createIcons();
                }
            }
        });

    });

});
