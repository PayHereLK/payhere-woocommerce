var scr = document.createElement('script'),
    head = document.head || document.getElementsByTagName('head')[0];
scr.src = 'https://www.payhere.lk/lib/payhere.js';
scr.async = false;
scr.id = 'payhere_js';
scr.defer = false;

head.insertBefore(scr, head.firstChild);

let hash = payhere_config.payhere_args.hash;
let on_site_checkout_enabled = payhere_config.onsite_enabled;


var payhere_js = document.querySelector('#payhere_js');
payhere_js.addEventListener('load', function () {
    payhere.onCompleted = function onCompleted(orderId) {
        window.location = payhere_config.payhere_args.return_url;
    };
    payhere.onError = function onError(error) {
        alert("An error occurred while making the payment. Error: " + error);
    };
    payhere_submit_trigger();
});

function payhere_submit_trigger() {
    if (on_site_checkout_enabled) {
        let payment = payhere_config.payhere_obj;
        payhere.startPayment(payment);
    } else {
        jQuery("#payhere_payment_form").trigger('submit');
    }
}
