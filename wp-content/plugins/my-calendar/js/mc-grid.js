(function ($) {
    'use strict';
    $(function () {
        $(".calendar-event").children().not(".event-title").hide();
        $(document).on("click", ".calendar-event .event-title",
            function (e) {
                e.preventDefault(); // remove line if you are using a link in the event title
                $(this).parent().children().not(".event-title").toggle().attr("tabindex", "-1").focus();
            });
        $(document).on("click", ".calendar-event .close",
            function (e) {
                e.preventDefault();
                $(this).closest(".vevent").find(".event-title a").focus();
                $(this).closest("div.details").toggle();
            });
    });
}(jQuery));	