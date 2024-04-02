<?php
/**
 * @var $awxAlertAdditionalClass string
 * @var $awxAlertType            string
 * @var $awxAlertText            string
 */
defined( 'ABSPATH' ) || exit();

$awxAlertBoxClass = '';
$awxAlertBoxIcon  = '';
switch ($awxAlertType) {
    case 'critical':
        $awxAlertBoxClass = 'wc-airwallex-error';
        $awxAlertBoxIcon = 'critical_filled.svg';
        break;
    case 'warning':
        $awxAlertBoxClass = 'wc-airwallex-warning';
        $awxAlertBoxIcon = 'warning_filled.svg';
        break;
    default:
        $awxAlertBoxClass = 'wc-airwallex-info';
        $awxAlertBoxIcon = 'info_filled.svg';
        break;
}
?>

<div class="wc-airwallex-alert-box <?php echo $awxAlertBoxClass ?> <?php echo esc_attr($awxAlertAdditionalClass) ?>" style="display: none;">
    <img src="<?php echo AIRWALLEX_PLUGIN_URL . '/assets/images/' . $awxAlertBoxIcon ?>"></img>
    <div><?php echo wp_kses_post($awxAlertText) ?></div>
</div>
