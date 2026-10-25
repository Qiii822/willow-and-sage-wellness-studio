/**
 * Works the amount out when an offer is picked while taking payment.
 *
 * The amount starts at what the customer was quoted. Choosing one of the
 * studio's Square discounts takes it off that quoted price - a percentage or
 * a fixed amount, whichever the discount is - and choosing "No offer" puts
 * the quoted price back. The box stays editable afterwards, for a treatment
 * added on the day.
 *
 * Without this the picker still records which offer was used; the amount
 * just has to be typed in by hand, as it always could be.
 */
(function () {
    'use strict';

    var offer = document.querySelector('[data-pay-offer]');
    var amount = document.getElementById('pay-amount');

    if (!offer || !amount) {
        return;
    }

    var base = parseInt(offer.dataset.baseCents, 10) || 0;

    offer.addEventListener('change', function () {
        // No quoted price to work from: leave the amount to staff.
        if (base <= 0) {
            return;
        }

        var option = offer.options[offer.selectedIndex];
        var percent = parseInt(option.dataset.percent, 10);
        var off = parseInt(option.dataset.amountCents, 10);
        var cents = base;

        if (!isNaN(percent)) {
            cents = Math.round(base * (100 - percent) / 100);
        } else if (!isNaN(off)) {
            cents = Math.max(0, base - off);
        }

        amount.value = (cents / 100).toFixed(2);
    });
}());
