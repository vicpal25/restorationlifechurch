var mediaPopup = '';
(function ($) {
    "use strict";
    $(function () {
        /**
         * Clears any existing Media Manager instances
         *
         * @author Gabe Shackle <gabe@hereswhatidid.com>
         * @modified Joe Dolson <plugins@joedolson.com>
         * @return void
         */
        function clear_existing() {
            if (typeof mediaPopup !== 'string') {
                mediaPopup.detach();
                mediaPopup = '';
            }
        }

        $('.mc-image-upload')
            .on('click', '.textfield-field', function (e) {
                e.preventDefault();
                var $self = $(this),
                    $inpField = $self.parent('.field-holder').find('#e_image'),
                    $idField = $self.parent('.field-holder').find('#e_image_id'),
                    $displayField = $self.parent('.field-holder').find('.event_image');
                clear_existing();
                mediaPopup = wp.media({
                    multiple: false, // add, reset, false
                    title: 'Choose an Uploaded Document',
                    button: {
                        text: 'Select'
                    }
                });

                mediaPopup.on('select', function () {
                    var selection = mediaPopup.state().get('selection'),
                        id = '',
                        img = '',
                        height = '',
                        width = '';
                    if (selection) {
                        id = selection.first().attributes.id;
                        height = thumbHeight;
                        width = ( ( selection.first().attributes.width ) / ( selection.first().attributes.height ) ) * thumbHeight;
                        img = "<img src='" + selection.first().attributes.url + "' width='" + width + "' height='" + height + "' />";
                        $inpField.val(selection.first().attributes.url);
                        $idField.val(id);
                        $displayField.html(img);
                    }
                });
                mediaPopup.open();
            })
    });
})(jQuery);