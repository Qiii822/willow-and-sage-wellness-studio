/**
 * Turns the category links on the treatment menu into an instant filter.
 *
 * The links already work on their own: each one loads the page with a
 * ?category= parameter and the server marks the other categories hidden.
 * This just saves the round trip, and keeps the address bar in step so a
 * filtered menu can still be shared or reached with the back button.
 *
 * If anything here fails the links keep working the slow way, which is why
 * the click is only intercepted once the switch has actually happened.
 */
(function () {
    'use strict';

    var ALL = 'all';

    function init(bar) {
        var links = Array.prototype.slice.call(
            bar.querySelectorAll('[data-filter]')
        );
        var sections = Array.prototype.slice.call(
            document.querySelectorAll('[data-category]')
        );

        if (!links.length || !sections.length) {
            return;
        }

        function show(filter) {
            sections.forEach(function (section) {
                section.hidden = filter !== ALL &&
                    section.dataset.category !== filter;
            });

            links.forEach(function (link) {
                var active = link.dataset.filter === filter;

                link.classList.toggle('is-active', active);

                if (active) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        /*
         * The filter the current address asks for. Read from the URL rather
         * than remembered in a variable so that the back button, a pasted
         * link and a fresh load all arrive at the same answer.
         */
        function filterFromUrl() {
            var match = window.location.search.match(/[?&]category=([^&]*)/);

            return match ? decodeURIComponent(match[1]) : ALL;
        }

        bar.addEventListener('click', function (event) {
            var link = event.target.closest('[data-filter]');

            if (!link || !bar.contains(link)) {
                return;
            }

            // Let the browser handle anything that is not a plain left click
            // - opening in a new tab should still load a real page.
            if (event.defaultPrevented || event.button !== 0 ||
                event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            show(link.dataset.filter);

            if (window.history && window.history.pushState) {
                window.history.pushState(null, '', link.href);
            }

            /*
             * Bring the list back into view. Someone who has scrolled down
             * and then narrows the menu would otherwise be left staring at
             * whitespace where the rest of it used to be.
             */
            bar.scrollIntoView({ block: 'nearest' });
        });

        window.addEventListener('popstate', function () {
            show(filterFromUrl());
        });
    }

    function start() {
        var bar = document.querySelector('[data-services-filter]');

        if (bar) {
            init(bar);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
