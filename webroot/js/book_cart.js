/**
 * Keeps the booking cart in step with what is ticked on the menu.
 *
 * The page works without this: every treatment is a checkbox inside one
 * form, and submitting it sends whatever is ticked. All this adds is the
 * running list, the total and the total time, so someone can see what they
 * have chosen without scrolling back through the menu.
 *
 * If it fails, the checkboxes and the submit button still do their job -
 * which is why nothing here disables or replaces them.
 */
(function () {
    'use strict';

    function money(cents) {
        return 'AU$' + (cents / 100).toFixed(2);
    }

    /**
     * Minutes as the studio would say them: "1 hr 15 min", not "75".
     */
    function duration(minutes) {
        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;

        if (hours === 0) {
            return rest + ' min';
        }

        return rest === 0 ? hours + ' hr' : hours + ' hr ' + rest + ' min';
    }

    function init(cart) {
        var rows = Array.prototype.slice.call(
            document.querySelectorAll('[data-treatment]')
        );

        if (!rows.length) {
            return;
        }

        var list = cart.querySelector('[data-cart-items]');
        var empty = cart.querySelector('[data-cart-empty]');
        var totals = cart.querySelector('[data-cart-totals]');
        var totalCell = cart.querySelector('[data-cart-total]');
        var durationCell = cart.querySelector('[data-cart-duration]');
        var error = cart.querySelector('[data-cart-error]');

        function chosen() {
            return rows.filter(function (row) {
                var input = row.querySelector('.book-choice-input');

                return input && input.checked;
            });
        }

        function render() {
            var picked = chosen();
            var cents = 0;
            var minutes = 0;

            list.innerHTML = '';

            picked.forEach(function (row) {
                var price = parseInt(row.dataset.price, 10) || 0;
                var item = document.createElement('li');
                var name = document.createElement('span');
                var cost = document.createElement('span');

                cents += price;
                minutes += parseInt(row.dataset.duration, 10) || 0;

                name.className = 'book-cart-name';
                name.textContent = row.dataset.name;

                cost.className = 'book-cart-price';
                // A treatment Square prices on the day has no figure to show,
                // and inventing one here would be worse than saying so.
                cost.textContent = price > 0 ? money(price) : 'Price varies';

                item.appendChild(name);
                item.appendChild(cost);
                list.appendChild(item);

                row.classList.add('is-chosen');
            });

            rows.forEach(function (row) {
                if (picked.indexOf(row) === -1) {
                    row.classList.remove('is-chosen');
                }
            });

            empty.hidden = picked.length > 0;
            list.hidden = picked.length === 0;
            totals.hidden = picked.length === 0;

            totalCell.textContent = money(cents);
            durationCell.textContent = minutes > 0 ? duration(minutes) : '-';
        }

        rows.forEach(function (row) {
            var input = row.querySelector('.book-choice-input');

            if (input) {
                input.addEventListener('change', function () {
                    /*
                     * Cleared on any change, not once the cart is non-empty:
                     * the message described the cart as it stood when the
                     * button was pressed, and that is just as stale whether
                     * it said "add a treatment" or "those cannot be booked".
                     * Only the server can say whether the new pick is any
                     * better, so nothing takes its place until it answers.
                     */
                    if (error) {
                        error.hidden = true;
                    }

                    render();
                });
            }
        });

        /*
         * Run once on load rather than assuming an empty cart: coming back
         * with the browser's back button restores the ticks, and the cart
         * should agree with them.
         */
        render();
    }

    function start() {
        var cart = document.querySelector('[data-cart]');

        if (cart) {
            init(cart);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
