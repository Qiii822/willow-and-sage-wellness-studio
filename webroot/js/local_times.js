/**
 * Shows appointment times on the visitor's own clock, as Square's booking
 * page does.
 *
 * The server prints every time in the studio's zone, which is what a
 * browser that never runs this keeps. Here each one marked with
 * data-local-time - the instant, in RFC 3339 - is rewritten in whatever
 * zone the browser is in, in the same format the server used. For anyone
 * in Adelaide that changes nothing; from Melbourne a 10:15 slot reads
 * 10:45, exactly as it does on Square.
 *
 * Square also says when the two clocks differ - "This business is in ACDT.
 * Times are shown in AEDT." - and so does every [data-zone-note] here.
 * When they are the same the note keeps whatever the server wrote.
 *
 * Times grouped under Morning, Afternoon and Evening ([data-periods]) are
 * re-sorted into those groups by the visitor's clock as well.
 */
(function () {
    'use strict';

    if (!window.Intl || !Intl.DateTimeFormat.prototype.formatToParts) {
        return;
    }

    /**
     * The named pieces of a date on the visitor's clock.
     */
    function parts(date, options) {
        var named = {};

        new Intl.DateTimeFormat('en-AU', options).formatToParts(date).forEach(function (part) {
            named[part.type] = part.value;
        });

        return named;
    }

    /**
     * "10:45 am", matching PHP's 'g:i a'.
     */
    function clock(date) {
        var p = parts(date, {hour: 'numeric', minute: '2-digit', hour12: true});

        return p.hour + ':' + p.minute + ' ' + (p.dayPeriod || '').toLowerCase();
    }

    var formats = {
        // 'g:i a'
        time: clock,
        // 'l j F'
        date: function (date) {
            var p = parts(date, {weekday: 'long', day: 'numeric', month: 'long'});

            return p.weekday + ' ' + p.day + ' ' + p.month;
        },
        // 'l j F, g:i a'
        long: function (date) {
            return formats.date(date) + ', ' + clock(date);
        },
        // 'D j M Y, g:i a'
        short: function (date) {
            var p = parts(date, {weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'});

            return p.weekday + ' ' + p.day + ' ' + p.month + ' ' + p.year + ', ' + clock(date);
        },
    };

    /**
     * "ACDT", "AEDT" - the zone's short name today.
     */
    function abbreviation(zone) {
        return parts(new Date(), {timeZone: zone, timeZoneName: 'short'}).timeZoneName;
    }

    document.querySelectorAll('[data-local-time]').forEach(function (el) {
        var date = new Date(el.dataset.localTime);
        var format = formats[el.dataset.localFormat] || formats.time;

        if (!isNaN(date.getTime())) {
            el.textContent = format(date);
        }
    });

    /**
     * The hour, 0-23, on the visitor's clock.
     */
    function hour(date) {
        return parseInt(parts(date, {hour: 'numeric', hourCycle: 'h23'}).hour, 10) % 24;
    }

    /*
     * The server put each time under Morning, Afternoon or Evening by the
     * studio's clock. Moved here under the one the visitor's clock says,
     * so a time that now reads 12:15 is not left under Morning. Taken in
     * the server's order, which is by time, so each group stays in order.
     */
    document.querySelectorAll('[data-periods]').forEach(function (container) {
        var periods = Array.prototype.slice.call(container.querySelectorAll('[data-period]'));

        container.querySelectorAll('[data-local-time]').forEach(function (button) {
            var at = hour(new Date(button.dataset.localTime));
            var target = periods[0];

            periods.forEach(function (period) {
                if (at >= Number(period.dataset.periodFrom)) {
                    target = period;
                }
            });

            target.querySelector('[data-period-slots]').appendChild(button);
        });

        periods.forEach(function (period) {
            period.querySelector('[data-period-empty]').hidden =
                period.querySelector('[data-period-slots]').children.length > 0;
        });
    });

    document.querySelectorAll('[data-zone-note]').forEach(function (el) {
        var studio = abbreviation(el.dataset.zoneNote);
        var visitor = abbreviation(undefined);

        if (studio && visitor && studio !== visitor) {
            el.textContent = 'This business is in ' + studio + '. Times are shown in ' + visitor + '.';
        }
    });
})();
