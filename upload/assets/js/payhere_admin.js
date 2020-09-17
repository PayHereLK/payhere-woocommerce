(function($){

    $(document).ready(function(){

        const app_credential_rows = $('.ph_app_credentials').closest('tr');
        const enable_sub_cancellation = $('#woocommerce_payhere_enable_sub_cancel');

        function check_sub_cancellation(){
            if (enable_sub_cancellation.prop('checked')){
                app_credential_rows.show();
            }
            else{
                app_credential_rows.hide();
            }
        }

        check_sub_cancellation();
        enable_sub_cancellation.on('change', check_sub_cancellation);
    });

})(jQuery);