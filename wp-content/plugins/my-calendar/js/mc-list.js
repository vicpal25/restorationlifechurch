(function ($) {
    'use strict';
    $(function () {
        $("li.mc-events").children().not(".event-date").hide();
        $("li.current-day").children().show();
        $(document).on("click", ".event-date",
            function (e) {
                e.preventDefault();
                $(this).parent().children().not(".event-date").toggle().attr("tabindex", "-1").focus();
                var visible = $(this).parent().find(".vevent").is(":visible");
                if (visible) {
                    $(this).parent().find(".vevent").attr("aria-expanded", "true");
                } else {
                    $(this).parent().find(".vevent").attr("aria-expanded", "false");
                }
            });
    });
}(jQuery));	