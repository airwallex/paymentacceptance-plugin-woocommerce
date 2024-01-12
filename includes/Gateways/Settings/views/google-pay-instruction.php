<p style="font-weight: 600;"><?php echo esc_html( $data['title'] ) ?></p>
<span>
    <?php printf(
            __('To use Google Pay, you\'ll need to register your website & get your merchant ID from the <a href="%s" target="_blank">Google Pay Business Console</a>.', 'airwallex-online-payments-gateway'),
            'https://pay.google.com/business/console'
        )
    ?>
</span><br />
<span><?php esc_html_e('To capture the screenshots needed for your integration request, you can enable sandbox mode so that Google Pay can work without a merchant ID.', 'airwallex-online-payments-gateway'); ?></span><br/>
<div class="wc-airwallex-settings-field-container">
    <label for="<?php echo esc_attr( $fieldKey ); ?>">
        <?php echo __('Google Pay merchant id', 'airwallex-online-payments-gateway'); ?>
    </label>
    <fieldset>
        <legend class="screen-reader-text">
            <span><?php echo __('Google Pay merchant id', 'airwallex-online-payments-gateway'); ?></span>
        </legend>
        <input type="text"
                class="<?php echo esc_attr( $data['class'] ); ?>"
                name="<?php echo esc_attr( $fieldKey ); ?>"
                id="<?php echo esc_attr( $fieldKey ); ?>"
                value="<?php echo esc_attr( $this->get_option( $key ) ); ?>""
                <?php disabled( $data['disabled'], true ); ?>
        />
    </fieldset>
</div>
<hr>
