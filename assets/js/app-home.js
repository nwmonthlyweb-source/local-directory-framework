(function () {
    'use strict';

    var firstPage = document.querySelector(
        '[data-nwmd-first-page]'
    );

    var appHome = document.querySelector(
        '[data-nwmd-app-home]'
    );

    var body = document.body;
    var root = document.documentElement;
    var storageKey = 'nwmd_app_first_page_seen';

    if (!firstPage || !appHome || !body || !root) {
        return;
    }

    var duration = parseInt(
        firstPage.getAttribute('data-duration'),
        10
    );

    var frequency = firstPage.getAttribute(
        'data-frequency'
    );

    if (!Number.isFinite(duration) || duration < 300) {
        duration = 1200;
    }

    function saveSessionState() {
        if ('session' !== frequency) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                storageKey,
                '1'
            );
        } catch (error) {
            // Continue when browser storage is unavailable.
        }
    }

    function revealDirectory(immediate) {
        if (!immediate) {
            firstPage.classList.add('is-leaving');
        }

        var reducedMotion = window.matchMedia &&
            window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches;

        var transitionDelay = (
            immediate ||
            reducedMotion
        )
            ? 0
            : 220;

        window.setTimeout(
            function () {
                saveSessionState();

                root.classList.add(
                    'nwmd-first-page-seen'
                );

                body.classList.add(
                    'nwmd-first-page-complete'
                );

                firstPage.hidden = true;

                appHome.removeAttribute(
                    'aria-hidden'
                );

                window.scrollTo(0, 0);
            },
            transitionDelay
        );
    }

    if (
        root.classList.contains(
            'nwmd-first-page-seen'
        )
    ) {
        revealDirectory(true);
        return;
    }

    appHome.setAttribute(
        'aria-hidden',
        'true'
    );

    window.setTimeout(
        function () {
            revealDirectory(false);
        },
        duration
    );
})();
