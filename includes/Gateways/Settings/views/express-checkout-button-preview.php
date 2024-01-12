<?php

/**
 * Express checkout settings customize view
 */

use Airwallex\Services\Util;

if (!defined('ABSPATH')) {
	exit;
}
?>

<tr>
	<td colspan="2">
		<div class="awx-ec-settings-button-preview">
			<div class="awx-ec-button-preview awx-apple-pay-btn">
				<apple-pay-button locale="<?php echo esc_attr(Util::getLocale()); ?>" buttonstyle="<?php echo esc_attr($this->getButtonTheme()); ?>" type="<?php echo esc_attr($this->getButtonType()); ?>"></apple-pay-button>
			</div>
			<div class="awx-ec-button-preview awx-google-pay-btn">
			</div>
		</div>
	</td>
</tr>
