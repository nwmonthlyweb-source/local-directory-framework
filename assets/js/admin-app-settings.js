(function () {
    'use strict';

    function initializeMediaField(field) {
        var selectButton = field.querySelector(
            '[data-nwmd-media-select]'
        );

        var removeButton = field.querySelector(
            '[data-nwmd-media-remove]'
        );

        var idInput = field.querySelector(
            '[data-nwmd-media-id]'
        );

        var preview = field.querySelector(
            '[data-nwmd-media-preview]'
        );

        var defaultSource = field.getAttribute(
            'data-default-src'
        );

        var frame = null;

        if (
            !selectButton ||
            !removeButton ||
            !idInput ||
            !preview
        ) {
            return;
        }

        selectButton.addEventListener(
            'click',
            function () {
                if (frame) {
                    frame.open();
                    return;
                }

                frame = window.wp.media({
                    title: 'Select First Page Image',
                    button: {
                        text: 'Use This Image'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                frame.on(
                    'select',
                    function () {
                        var attachment = frame
                            .state()
                            .get('selection')
                            .first()
                            .toJSON();

                        var previewSource = attachment.url;

                        if (
                            attachment.sizes &&
                            attachment.sizes.medium
                        ) {
                            previewSource =
                                attachment.sizes.medium.url;
                        }

                        idInput.value = attachment.id;
                        preview.src = previewSource;
                        removeButton.disabled = false;
                    }
                );

                frame.open();
            }
        );

        removeButton.addEventListener(
            'click',
            function () {
                idInput.value = '0';
                preview.src = defaultSource;
                removeButton.disabled = true;
            }
        );
    }

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            var fields = document.querySelectorAll(
                '[data-nwmd-media-field]'
            );

            Array.prototype.forEach.call(
                fields,
                initializeMediaField
            );
        }
    );
})();
