/**
 * Email provider picker on the admin settings page.
 *
 * Two levels: the provider, and for providers offering more than one way in
 * (Mailtrap does), how to connect. Picking either fills in the server
 * details and shows only the fields that mode actually needs, so nobody has
 * to know that a Mailtrap API token doubles as an SMTP password.
 */
(function () {
    'use strict';

    function init() {
        var form = document.querySelector('.mail-form');

        if (!form) {
            return;
        }

        var providers = form.querySelectorAll('input[name="provider"]');

        if (!providers.length) {
            return;
        }

        var host = document.getElementById('mail-host');
        var port = document.getElementById('mail-port');

        function show(el, visible) {
            if (el) {
                el.hidden = !visible;
            }
        }

        function showAll(selector, visible) {
            form.querySelectorAll(selector).forEach(function (el) {
                el.hidden = !visible;
            });
        }

        function currentProvider() {
            return form.querySelector('input[name="provider"]:checked');
        }

        // Every host this form can fill in by itself.
        var presetHosts = Array.prototype.map.call(
            form.querySelectorAll('[data-host]'),
            function (el) {
                return el.dataset.host;
            }
        ).filter(Boolean);

        function isPresetHost(value) {
            return presetHosts.indexOf(value) !== -1;
        }

        /**
         * The mode block belonging to a provider, if it has a choice.
         */
        function modeBlockFor(providerValue) {
            return form.querySelector('.mail-modes[data-for-provider="' + providerValue + '"]');
        }

        function applyMode(providerValue, modeRadio) {
            var mode = modeRadio ? modeRadio.value : 'smtp';
            var isApi = mode === 'api';

            form.querySelectorAll('.mail-mode').forEach(function (label) {
                label.classList.toggle('is-selected', modeRadio !== null && label.contains(modeRadio));
            });

            showAll('[data-smtp-only]', !isApi);
            show(form.querySelector('[data-label-smtp]'), !isApi);
            show(form.querySelector('[data-label-api]'), isApi);

            // Only the hint for this exact provider and mode.
            form.querySelectorAll('.mail-mode-hint').forEach(function (hint) {
                hint.hidden = !(
                    hint.dataset.forProvider === providerValue
                    && hint.dataset.forMode === mode
                );
            });

            if (modeRadio) {
                if (host && modeRadio.dataset.host) {
                    host.value = modeRadio.dataset.host;
                }

                if (port && modeRadio.dataset.port) {
                    port.value = modeRadio.dataset.port;
                }
            }
        }

        function applyProvider(radio) {
            var value = radio.value;

            form.querySelectorAll('.mail-provider').forEach(function (label) {
                label.classList.toggle('is-selected', label.contains(radio));
            });

            var block = modeBlockFor(value);

            /*
             * Every mode radio stays in the document, so those belonging to
             * other providers are disabled: a disabled control is not
             * submitted, which stops the form posting a provider and a mode
             * that do not go together.
             */
            form.querySelectorAll('.mail-modes').forEach(function (group) {
                var mine = group === block;

                group.hidden = !mine;

                group.querySelectorAll('input[name="auth_mode"]').forEach(function (input) {
                    input.disabled = !mine;
                });
            });

            if (block) {
                var chosen = block.querySelector('input[name="auth_mode"]:checked')
                    || block.querySelector('input[name="auth_mode"]');

                if (chosen) {
                    chosen.checked = true;
                }

                applyMode(value, chosen);

                return;
            }

            /*
             * Single-mode provider: SMTP by definition, and the server
             * details are carried on the provider radio itself.
             */
            applyMode(value, null);

            if (host) {
                if (radio.dataset.host) {
                    host.value = radio.dataset.host;
                } else if (isPresetHost(host.value)) {
                    /*
                     * "Other" has no preset. Clear a host left behind by a
                     * previous choice so it is not mistaken for a suggestion,
                     * but keep anything actually typed here.
                     */
                    host.value = '';
                }
            }

            if (port && radio.dataset.port) {
                port.value = radio.dataset.port;
            }
        }

        Array.prototype.forEach.call(providers, function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) {
                    applyProvider(radio);
                }
            });
        });

        form.addEventListener('change', function (event) {
            if (event.target.name === 'auth_mode') {
                var provider = currentProvider();

                applyMode(provider ? provider.value : '', event.target);
            }
        });

        // Reflect whatever is already selected when the page loads.
        var initial = currentProvider();

        if (initial) {
            applyProvider(initial);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
