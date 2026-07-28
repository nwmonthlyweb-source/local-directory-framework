(function () {
    'use strict';

    var openButtons = document.querySelectorAll(
        '[data-nwmd-dialog-open]'
    );

    var dialogs = document.querySelectorAll(
        '[data-nwmd-dialog]'
    );

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

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var dialogId = button.getAttribute(
                'data-nwmd-dialog-open'
            );

            var dialog = document.getElementById(
                dialogId
            );

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
})();