console.log(payhere_capture_data);

(function ($) {
    'use strict';

    $('#payhere_capture_initiator').click(function () {
        let ele = this;

        let capture_amount = $("#payhere-capture-amount").val();
        if (Number(payhere_capture_data.authorize_amount) >= Number(capture_amount)) {

            disable_button(ele);
            disable_button($('#cancel-button'));
            $.post(payhere_capture_data.admin_ajax, {
                action: 'payhere_capture',
                order_id: payhere_capture_data.order_id,
                authorize_amount: payhere_capture_data.authorize_amount,
            }, function (data) {
                enable_button(ele);
                enable_button($('#cancel-button'));
                if (data.type == 'OK') {
                    $("#info-div").html(data.message).addClass('text-success');
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    $("#info-div").html(data.message).addClass('text-danger');
                }
            }, 'JSON');
        } else {
            $("#info-div").html('Warning!!!.<br/>You are trying to capture more than authorized amount').addClass('text-danger');

        }
    });

    function disable_button(ele) {
        $(ele).attr({disabled: true});
        $(ele).find('img').show();
    }

    function enable_button(ele) {
        $(ele).attr({disabled: false});
        $(ele).find('img').hide();
    }
})(jQuery);