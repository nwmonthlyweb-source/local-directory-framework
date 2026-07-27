(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var chooseButton = document.getElementById('nwmd_ad_choose_image');
        var removeButton = document.getElementById('nwmd_ad_remove_image');
        var attachmentInput = document.getElementById(
            'nwmd_ad_image_attachment_id'
        );
        var preview = document.getElementById('nwmd_ad_image_preview');
        var mediaFrame = null;

        if (
            !chooseButton ||
            !removeButton ||
            !attachmentInput ||
            !preview ||
            !window.wp ||
            !window.wp.media
        ) {
            return;
        }

        chooseButton.addEventListener('click', function () {
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = window.wp.media({
                title: 'Choose advertising image',
                button: {
                    text: 'Use this image'
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame
                    .state()
                    .get('selection')
                    .first()
                    .toJSON();

                var imageUrl = attachment.url;

                if (
                    attachment.sizes &&
                    attachment.sizes.medium &&
                    attachment.sizes.medium.url
                ) {
                    imageUrl = attachment.sizes.medium.url;
                }

                attachmentInput.value = String(attachment.id);
                preview.replaceChildren();

                var image = document.createElement('img');
                image.src = imageUrl;
                image.alt = attachment.alt || attachment.title || '';
                image.style.display = 'block';
                image.style.maxWidth = '320px';
                image.style.height = 'auto';
                image.style.marginBottom = '12px';

                preview.appendChild(image);
                removeButton.hidden = false;
                removeButton.style.setProperty(
                    'display',
                    'inline-block',
                    'important'
                );
            });

            mediaFrame.open();
        });

        removeButton.addEventListener('click', function () {
            attachmentInput.value = '';
            preview.replaceChildren();
            removeButton.hidden = true;
            removeButton.style.setProperty(
                'display',
                'none',
                'important'
            );
        });
    });
}());
