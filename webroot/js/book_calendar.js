/**
 * Drives the day picker over the month of slots the server fetched.
 *
 * Four weeks are already on the page, so moving between weeks and between
 * days is only a matter of which rows are on show - no network, no wait.
 * Only stepping outside that month, or picking a date beyond it, goes back
 * to the server, and those are an ordinary link and an ordinary form that
 * work whether or not this script runs.
 *
 * Without it the week rows stay hidden and every day in the month is listed
 * one after another under a heading naming the whole span. That is why the
 * rows start hidden in the markup rather than being hidden here: a browser
 * that never runs this leaves them out of sight instead of showing buttons
 * that do nothing.
 */
(function () {
    'use strict';

    function init(calendar) {
        var weeks = Array.prototype.slice.call(
            calendar.querySelectorAll('[data-week]')
        );
        var days = Array.prototype.slice.call(
            document.querySelectorAll('[data-day]')
        );
        var arrows = Array.prototype.slice.call(
            calendar.querySelectorAll('[data-week-step]')
        );
        var title = calendar.querySelector('[data-week-title]');

        if (!weeks.length || !days.length) {
            return;
        }

        var current = 0;

        /**
         * Which loaded week a date falls in, or -1 if it is outside them.
         */
        function weekOf(date) {
            for (var i = 0; i < weeks.length; i++) {
                var cells = weeks[i].querySelectorAll('[data-date]');
                var first = cells[0].dataset.date;
                var last = cells[cells.length - 1].dataset.date;

                if (date >= first && date <= last) {
                    return i;
                }
            }

            return -1;
        }

        function showDay(date) {
            days.forEach(function (day) {
                day.hidden = day.dataset.day !== date;
            });

            Array.prototype.forEach.call(
                calendar.querySelectorAll('[data-date]'),
                function (button) {
                    var active = button.dataset.date === date;

                    button.classList.toggle('is-selected', active);

                    if (active) {
                        button.setAttribute('aria-current', 'date');
                    } else {
                        button.removeAttribute('aria-current');
                    }
                }
            );
        }

        /**
         * Puts a week on show, and with it the first of its days that has
         * anything - landing on a closed day would make the week look empty
         * when it is not.
         */
        function showWeek(index) {
            current = index;

            weeks.forEach(function (week, i) {
                week.hidden = i !== index;
            });

            if (title) {
                title.textContent = weeks[index].dataset.label;
            }

            var open = weeks[index].querySelector('[data-date]:not([disabled])');

            showDay(open ? open.dataset.date : null);

            arrows.forEach(function (arrow) {
                var forward = arrow.dataset.weekStep === 'forward';
                var inMonth = forward ? index < weeks.length - 1 : index > 0;

                /*
                 * An arrow that is a link always has somewhere to go, even
                 * at the edge of the month: it loads the next one. Only the
                 * marker rendered in place of a missing link can go dead,
                 * and then only when no loaded week lies that way either.
                 */
                arrow.classList.toggle(
                    'is-disabled',
                    !inMonth && arrow.tagName !== 'A'
                );
            });
        }

        arrows.forEach(function (arrow) {
            arrow.addEventListener('click', function (event) {
                var forward = arrow.dataset.weekStep === 'forward';
                var target = current + (forward ? 1 : -1);

                if (target < 0 || target >= weeks.length) {
                    // Off the end of what was fetched: let the link load the
                    // next month, or do nothing if there is no link.
                    return;
                }

                event.preventDefault();
                showWeek(target);
            });
        });

        calendar.addEventListener('click', function (event) {
            var button = event.target.closest('[data-date]');

            if (button && !button.disabled) {
                showDay(button.dataset.date);
            }
        });

        /*
         * Left and right move between days the way a date picker should,
         * skipping the closed ones - stepping onto a day with nothing on it
         * would be a keypress that appears to do nothing.
         */
        calendar.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }

            var open = Array.prototype.slice.call(
                weeks[current].querySelectorAll('[data-date]:not([disabled])')
            );
            var here = open.indexOf(document.activeElement);

            if (here === -1) {
                return;
            }

            var next = open[here + (event.key === 'ArrowRight' ? 1 : -1)];

            if (next) {
                event.preventDefault();
                next.focus();
                showDay(next.dataset.date);
            }
        });

        /*
         * A date inside the fetched month is shown without leaving the
         * page; anything further off is left to the form, which asks the
         * server for the month around it.
         */
        var jump = document.querySelector('[data-jump]');

        if (jump) {
            jump.addEventListener('submit', function (event) {
                var box = jump.querySelector('input[type="date"]');
                var wanted = box ? box.value : '';
                var index = wanted ? weekOf(wanted) : -1;

                if (index === -1) {
                    return;
                }

                event.preventDefault();
                showWeek(index);

                var cell = calendar.querySelector('[data-date="' + wanted + '"]');

                // A closed day still brings its week up, but the day left
                // showing stays the first one that has times on it.
                if (cell && !cell.disabled) {
                    showDay(wanted);
                }
            });
        }

        // Open on the first week with anything free, so the page does not
        // land on an empty one when the studio is shut all week.
        var opening = weeks.findIndex(function (week) {
            return week.querySelector('[data-date]:not([disabled])') !== null;
        });

        showWeek(opening === -1 ? 0 : opening);
    }

    function start() {
        var calendar = document.querySelector('.book-calendar');

        if (calendar) {
            init(calendar);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
