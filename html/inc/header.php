<?php
// get custom logo
$airwallexWPCustomLogoUrl = '';
if (has_custom_logo()) {
    $airwallexWPcustomLogoInfo = wp_get_attachment_image_src(get_theme_mod('custom_logo'));
    if (!empty($airwallexWPcustomLogoInfo)) {
        $airwallexWPCustomLogoUrl = esc_url($airwallexWPcustomLogoInfo[0]);
    }
}
?>

<div class="airwallex-checkout-header">
<div class="airwallex-checkout-header-inner">
    <div class="airwallex-checkout-header-content">
        <?php if (!empty($airwallexWPCustomLogoUrl)) : ?>
            <div class="airwallex-checkout-header-shop-logo"><img src=<?php echo $airwallexWPCustomLogoUrl ?>></div>
            <div direction="VERTICAL" class="airwallex-checkout-header-pipe"></div>
        <?php endif; ?>
        <div class="airwallex-checkout-header-text"><h1><?php esc_html_e('Make a payment') ?></h1></div> 
    </div>
</div>
</div>
<div class="airwallex-checkout-header-space"></div>