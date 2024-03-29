<?php
defined('ABSPATH') || exit();

global $current_section;
$tabs = apply_filters( 'wc_airwallex_local_gateways_tab', array() );
ksort($tabs);
?>
<div class="airwallex-settings-nav-local-payment-methods">
	<?php foreach ( $tabs as $id => $tab ) : ?>
		<a
		class="awx-nav-link 
		<?php
		if ( $current_section === $id ) {
			echo 'awx-nav-link-active';}
		?>
		"
		href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id ); ?>"><?php echo esc_attr( $tab ); ?></a>
	<?php endforeach; ?>
</div>
<div class="clear"></div>
