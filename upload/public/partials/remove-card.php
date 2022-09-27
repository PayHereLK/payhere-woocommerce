<style></style>
<div class="payhere-card-container">
    <h4>Saved Payment Methods.</h4>
    <p><small>Payment Methods Saved through PayHere Payment Gateway.</small></p>

    <div class="payhere-saved-card-wrapper">
        <div class="payhere-method-icon">
            <?php
            echo "<img src='" . (plugin_dir_url(__FILE__) . '../images/' . strtolower($card_info->method) . '.png') . "'/>";
            ?>
        </div>
        <div class="payhere-method-info">
            <p><?php echo substr($card_info->card_no, -8) ?></p>
            <p>Saved on : <?php echo date('l jS F Y', $card_info->saved_date) ?></p>
            <button type='button' class='button' style='margin-bottom: 15px;' id="payhere-method-remove-btn">Remove Card</button>
        </div>
    </div>
</div>
