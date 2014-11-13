(function ($) {
    'use strict';
    $(function () {
        $(".mini .has-events").children().not(".trigger").hide();
        $(document).on("click", ".mini .has-events .trigger", function (e) {
            e.preventDefault();
            $(this).parent().children().not(".trigger").toggle().attr("tabindex", "-1").focus();
        });
        $(document).on("click", ".mini-event .close", function (e) {
            e.preventDefault();
            $(this).closest("div.calendar-events").toggle();
        });
    });
}(jQuery));	