jQuery(document).ready(function ($) {
    $('#mc-sortable').sortable({
        update: function (event, ui) {
            $('#mc-sortable-update').html('Submit form to save changes');
            $('#mc-sortable-update').css({'padding': '6px', 'background-color': '#ffc', 'font-weight': 'bold'});
        }
    });
    $('#mc-sortable .up').on('click', function (e) {
        e.preventDefault();
        $(this).parents('li').insertBefore($(this).parents('li').prev());
        $(this).parents('li').css({'background': '#ffc'});
    });
    $('#mc-sortable .down').on('click', function (e) {
        e.preventDefault();
        $(this).parents('li').insertAfter($(this).parents('li').next());
        $(this).parents('li').css({'background': '#ffc'});
    });
});