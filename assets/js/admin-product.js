jQuery(document).ready(function($){
    var $sendCheckbox = $("#woogram_m_send");
    var $targetBox = $("#woogram_m");
    if ($sendCheckbox.length && $targetBox.length) {
        $targetBox.prop("hidden", !$sendCheckbox.is(":checked"));
        $sendCheckbox.on("change", function() {
            $targetBox.prop("hidden", !$sendCheckbox.is(":checked"));
        });
    }
});
