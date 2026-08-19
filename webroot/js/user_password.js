document.addEventListener('DOMContentLoaded', function () {
    const toggleButtons =
        document.querySelectorAll('.user-password-toggle');

    toggleButtons.forEach(function (toggleButton) {
        toggleButton.addEventListener('click', function () {
            const targetId =
                toggleButton.getAttribute('data-target');

            const passwordInput =
                document.getElementById(targetId);

            if (!passwordInput) {
                return;
            }

            const hidden =
                passwordInput.type === 'password';

            passwordInput.type =
                hidden ? 'text' : 'password';

            toggleButton.setAttribute(
                'aria-label',
                hidden ? 'Hide password' : 'Show password'
            );

            const icon =
                toggleButton.querySelector('[data-lucide]');

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
