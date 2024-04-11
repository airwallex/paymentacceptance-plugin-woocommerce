<?php
defined( 'ABSPATH' ) || exit();
?>
<div class="wc-airwallex-currency-switching-quote-expire-mask" style="display: none;"></div>
<div class="wc-airwallex-currency-switching-quote-expire" style="display: none;">
    <div class="wc-airwallex-currency-switching-quote-expire-inner">
        <div class="wc-airwallex-currency-switching-quote-expire-close">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.41467 8.00045L12.9502 11.536C13.3407 11.9265 13.3407 12.5597 12.9502 12.9502C12.5597 13.3407 11.9265 13.3407 11.536 12.9502L8.00045 9.41467L4.46492 12.9502C4.0744 13.3407 3.44123 13.3407 3.05071 12.9502C2.66018 12.5597 2.66018 11.9265 3.05071 11.536L6.58624 8.00045L3.05071 4.46492C2.66018 4.0744 2.66018 3.44123 3.05071 3.05071C3.44123 2.66018 4.0744 2.66018 4.46492 3.05071L8.00045 6.58624L11.536 3.05071C11.9265 2.66018 12.5597 2.66018 12.9502 3.05071C13.3407 3.44123 13.3407 4.0744 12.9502 4.46492L9.41467 8.00045Z" fill="#545B63"/>
        </svg>
        </div>
        <div class="wc-airwallex-currency-switching-quote-expire-header"><?php esc_html_e('Your amount to pay has been updated', 'airwallex-online-payments-gateway'); ?></div>
        <div style="display: flex; flex-direction:column; gap: 24px;">
            <div style="display: flex; flex-direction:column; gap: 8px;">
                <div id="wc-airwallex-quote-expire-convert-text" class="wc-airwallex-currency-switching-quote-expire-convert-text"></div>
                <div class="wc-airwallex-currency-switching-quote-expire-text"><?php esc_html_e('The previous conversion quote has expired. Here is your new quote:', 'airwallex-online-payments-gateway'); ?></div>
            </div>
            <div style="width: 100%; display: flex; flex-direction:column; gap: 8px;">
                <div class="wc-airwallex-currency-switching-quote-expire-text-medium" style="display: flex;">
                    <div style="flex: 1;"><?php esc_html_e('Total', 'airwallex-online-payments-gateway'); ?></div>
                    <div style="flex: 1;"><?php esc_html_e('$', 'airwallex-online-payments-gateway'); ?> <span id="wc-airwallex-currency-switching-base-amount" class="wc-airwallex-currency-switching-base-amount"></span></div>
                </div>
                <div style="display: flex;">
                    <div style="flex: 1;"></div>
                    <div id="wc-airwallex-currency-switching-conversion-rate" style="flex: 1; justify-content: flex-start;" class="wc-airwallex-currency-switching-conversion-rate">
                        <div class="wc-airwallex-currency-switching-convert-icon">
                            <div class="wc-airwallex-currency-switching-convert-icon-line"><div></div></div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.8751 6.00603C11.2227 5.84685 11.641 5.97553 11.836 6.31338C12.0431 6.6721 11.9202 7.13079 11.5615 7.33789L9.93769 8.27539C9.57897 8.4825 9.12028 8.3596 8.91317 8.00088L7.97567 6.37708C7.76857 6.01836 7.89147 5.55967 8.25019 5.35256C8.60891 5.14545 9.0676 5.26836 9.27471 5.62708L9.36849 5.78951C9.25886 4.02452 7.79267 2.62695 6.00007 2.62695C5.0122 2.62695 4.12347 3.05137 3.50626 3.72782L2.44482 2.66638C3.33417 1.71884 4.598 1.12695 6.00007 1.12695C8.69245 1.12695 10.8751 3.30957 10.8751 6.00195C10.8751 6.00331 10.8751 6.00467 10.8751 6.00603ZM1.12576 6.08873L1.12513 6.0891C0.766406 6.2962 0.307713 6.1733 0.100606 5.81458C-0.106501 5.45586 0.0164058 4.99717 0.375125 4.79006L1.99892 3.85256C2.35764 3.64545 2.81633 3.76836 3.02344 4.12708L3.96094 5.75088C4.16805 6.1096 4.04514 6.56829 3.68642 6.77539C3.3277 6.9825 2.86901 6.8596 2.6619 6.50088L2.66152 6.50022C2.90238 8.12792 4.30533 9.37695 6 9.37695C6.85293 9.37695 7.63196 9.06056 8.22613 8.53874L9.28834 9.60095C8.42141 10.3935 7.26716 10.877 6 10.877C3.3366 10.877 1.17206 8.74108 1.12576 6.08873Z" fill="#B0B6BF" />
                            </svg>
                            <div class="wc-airwallex-currency-switching-convert-icon-line"><div></div></div>
                        </div>
                        <div id="wc-airwallex-currency-switching-convert-text" class="wc-airwallex-currency-switching-convert-text">
                        </div>
                    </div>
                </div>
                <div style="display: flex;">
                    <div class="wc-airwallex-currency-switching-quote-expire-text-medium" style="flex: 1; "><?php esc_html_e('You Pay', 'airwallex-online-payments-gateway') ?></div>
                    <div style="flex: 1;" class="wc-airwallex-currency-switching-container">
                        <div id="wc-airwallex-currency-switching-converted-amount" class="wc-airwallex-currency-switching-converted-amount wc-airwallex-currency-switching-quote-expire-text-large">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="wc-airwallex-currency-switching-quote-expire-footer">
            <div class="wc-airwallex-currency-switching-quote-expire-button-group">
                <div class="wc-airwallex-currency-switching-quote-expire-place-back"><?php esc_html_e('Back to checkout', 'airwallex-online-payments-gateway'); ?></div>
                <div class="wc-airwallex-currency-switching-quote-expire-place-order-mask"></div>
                <div id="wc-airwallex-quote-expire-confirm" class="wc-airwallex-currency-switching-quote-expire-place-order">
                    <?php esc_html_e('Place Order', 'airwallex-online-payments-gateway'); ?>
                </div>
            </div>
        </div>
    </div>
</div>