(function () {
    'use strict';

    var openButtons = document.querySelectorAll(
        '[data-nwmd-dialog-open]'
    );

    var dialogs = document.querySelectorAll(
        '[data-nwmd-dialog]'
    );

    var installButton = document.querySelector(
        '[data-nwmd-install]'
    );

    var deferredInstallPrompt = null;

    function closeDialog(dialog) {
        if (!dialog) {
            return;
        }

        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }

        document.body.classList.remove(
            'nwmd-dialog-open'
        );
    }

    function openDialog(dialog) {
        if (!dialog) {
            return;
        }

        document.body.classList.add(
            'nwmd-dialog-open'
        );

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var dialogId = button.getAttribute(
                'data-nwmd-dialog-open'
            );

            openDialog(
                document.getElementById(dialogId)
            );
        });
    });

    dialogs.forEach(function (dialog) {
        var closeButton = dialog.querySelector(
            '[data-nwmd-dialog-close]'
        );

        if (closeButton) {
            closeButton.addEventListener(
                'click',
                function () {
                    closeDialog(dialog);
                }
            );
        }

        dialog.addEventListener(
            'cancel',
            function () {
                document.body.classList.remove(
                    'nwmd-dialog-open'
                );
            }
        );

        dialog.addEventListener(
            'click',
            function (event) {
                if (event.target === dialog) {
                    closeDialog(dialog);
                }
            }
        );
    });

    function isStandalone() {
        return (
            window.matchMedia(
                '(display-mode: standalone)'
            ).matches ||
            window.navigator.standalone === true
        );
    }

    function updateInstallButton() {
        if (!installButton) {
            return;
        }

        var isMobile = window.matchMedia(
            '(max-width: 720px)'
        ).matches;

        installButton.hidden =
            !isMobile ||
            isStandalone();
    }

    window.addEventListener(
        'beforeinstallprompt',
        function (event) {
            event.preventDefault();
            deferredInstallPrompt = event;
            updateInstallButton();
        }
    );

    window.addEventListener(
        'appinstalled',
        function () {
            deferredInstallPrompt = null;

            if (installButton) {
                installButton.hidden = true;
            }
        }
    );

    if (installButton) {
        installButton.addEventListener(
            'click',
            function () {
                if (deferredInstallPrompt) {
                    deferredInstallPrompt.prompt();

                    deferredInstallPrompt.userChoice
                        .then(function (choice) {
                            if (
                                choice &&
                                choice.outcome === 'accepted'
                            ) {
                                installButton.hidden = true;
                            }

                            deferredInstallPrompt = null;
                        });

                    return;
                }

                openDialog(
                    document.getElementById(
                        'nwmd-install-help'
                    )
                );
            }
        );
    }

    updateInstallButton();

    window.addEventListener(
        'resize',
        updateInstallButton
    );

    if (
        'serviceWorker' in navigator &&
        typeof window.nwmdDirectoryApp === 'object' &&
        window.nwmdDirectoryApp.serviceWorkerUrl
    ) {
        window.addEventListener(
            'load',
            function () {
                navigator.serviceWorker.register(
                    window.nwmdDirectoryApp
                        .serviceWorkerUrl,
                    {
                        scope:
                            window.nwmdDirectoryApp
                                .serviceWorkerScope
                    }
                ).catch(function () {
                    // The directory remains usable without installation.
                });
            }
        );
    }
})();