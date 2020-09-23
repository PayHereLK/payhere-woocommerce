<button type='button' class='button' style='margin-bottom: 15px;' id="remove-btn">Remove
    Card <?php echo substr($card_info->card_no, -8) ?></button>
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
