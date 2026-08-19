document.addEventListener('DOMContentLoaded', function () {

    const passwordInput =
        document.getElementById('password');

    const toggleButton =
        document.getElementById('toggle-password');

    const passwordIcon =
        document.getElementById('password-icon');

    if (!passwordInput || !toggleButton) {
        return;
    }

    toggleButton.addEventListener('click', function () {

        const hidden =
            passwordInput.type === 'password';

        passwordInput.type =
            hidden ? 'text' : 'password';

        toggleButton.setAttribute(
            'aria-label',
            hidden ? 'Hide password' : 'Show password'
        );

        if (passwordIcon) {

            passwordIcon.setAttribute(
                'data-lucide',
                hidden ? 'eye-off' : 'eye'
            );

            if (window.lucide) {
                lucide.createIcons();
            }
        }
    });

});
