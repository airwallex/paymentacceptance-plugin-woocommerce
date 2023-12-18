<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $current_section;
$airwallexAdminNavTabs = apply_filters( 'wc_airwallex_settings_nav_tabs', array() );
$awxTabsCnt            = count( $airwallexAdminNavTabs );
$awxCurrentIdx         = 0;
$awxActiveTabExist     = false;
?>
<div class="wc-airwallex-settings-logo">
	<img class="airwallex-logo" src="<?php echo esc_attr(AIRWALLEX_PLUGIN_URL . '/assets/images/logo.svg'); ?>"/>
	<span id="awx-account-not-connected" style="display: <?php echo $this->isConnected() ? 'none' : 'inline-block' ?>; padding: 3px 8px; font-weight:bold; border-radius:3px; background-color:#FFADAD">Not Connected</span>
</div>
<div class="airwallex-settings-nav">
	<?php
	foreach ( $airwallexAdminNavTabs as $awxTabId => $awxTab ) :
		++$awxCurrentIdx;
		?>
		<a class="nav-tab 
		<?php 
		if ( $current_section === $awxTabId || ( ! $awxActiveTabExist && $awxTabsCnt === $awxCurrentIdx ) ) {
			echo 'nav-tab-active';
			$awxActiveTabExist = true;
		}
		?>
		"
		   href="<?php echo esc_url(admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $awxTabId )); ?>"><?php echo esc_attr( $awxTab ); ?></a>
	<?php endforeach; ?>
</div>
<div class="clear"></div>
