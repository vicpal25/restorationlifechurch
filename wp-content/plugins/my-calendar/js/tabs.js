jQuery(document).ready(function ($) {
    var tabs = $('.mc-tabs .wptab').length;
    $('.mc-tabs .tabs a[href="#' + firstItem + '"]').addClass('active');
    if (tabs > 1) {
        $('.mc-tabs .wptab').not('#' + firstItem).hide();
        $('.mc-tabs .tabs a').on('click', function (e) {
            e.preventDefault();
            $('.mc-tabs .tabs a').removeClass('active');
            $(this).addClass('active');
            var target = $(this).attr('href');
            $('.mc-tabs .wptab').not(target).hide();
            $(target).show();
        });
    }
});