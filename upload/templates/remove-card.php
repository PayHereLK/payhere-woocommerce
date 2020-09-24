<style>
    .card-container {
        padding: 20px;
        background-color: #f8f8f8;
    }
</style>
<div class="card-container">
    <h3>Added Card Details.</h3>
    <p><?php echo $card_info->card_no ?> <em><?php echo $card_info->method ?></em></p>

    <button type='button' class='button' style='margin-bottom: 15px;' id="remove-btn">Remove Card</button>
</div>
<script>
    jQuery("#remove-btn").click(function () {
        let ok = confirm("Are you want to delete saved cards?");
        if (ok) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php') ?>',
                type: "POST",
                dataType: 'JSON',
                data: {action: 'payhere_remove_card'},
                success: function (data) {
                    if (data.type == 'OK') {
                        alert('Saved card removed successfully.');
                        window.location.reload();
                    }
                }
            });
        }
    });

</script>
