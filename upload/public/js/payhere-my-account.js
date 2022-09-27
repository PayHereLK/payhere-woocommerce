(function ($) {

    $("#payhere-method-remove-btn").click(function () {
        let ok = confirm("Are you want to delete saved payment method?");
        if (ok) {
            $.ajax({
                url: payhere_data.admin_ajax,
                type: "POST",
                dataType: 'JSON',
                data: {action: 'payhere_remove_card'},
                success: function (data) {
                    if (data.type == 'OK') {
                        alert('Saved method removed successfully.');
                        window.location.href = payhere_data.my_account;
                    }
                }
            });
        }
    });
})(jQuery);