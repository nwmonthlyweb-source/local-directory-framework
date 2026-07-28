(function () {
    'use strict';

    var splash = document.querySelector(
        '[data-nwmd-app-splash]'
    );

    var body = document.body;
    var storageKey = 'nwmd_app_splash_seen';
    var hasSeenSplash = false;

    if (!splash || !body) {
        return;
    }

    try {
        hasSeenSplash =
            window.sessionStorage.getItem(storageKey) === '1';
    } catch (error) {
        hasSeenSplash = false;
    }

    function removeSplash() {
        body.classList.remove('nwmd-splash-pending');

        if (splash.parentNode) {
            splash.parentNode.removeChild(splash);
        }
    }

    if (hasSeenSplash) {
        removeSplash();
        return;
    }

    window.setTimeout(function () {
        splash.classList.add('is-hiding');

        try {
            window.sessionStorage.setItem(
                storageKey,
                '1'
            );
        } catch (error) {
            // Continue normally when storage is unavailable.
        }

        window.setTimeout(
            removeSplash,
            240
        );
    }, 1200);
})();
