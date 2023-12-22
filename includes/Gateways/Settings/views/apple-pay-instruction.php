<p style="font-weight: 600;"><?php echo esc_html( $data['title'] ) ?></p>
<span><?php esc_html_e('To use Apple Pay, you\'ll need to register your domain with Apple.', 'airwallex-online-payments-gateway'); ?></span><br/>
<span><?php esc_html_e('1. Select "Register" to add the Apple Pay domain association file to your server.', 'airwallex-online-payments-gateway') ?></span><br/>
<span>
    <?php printf(
            __('2. Go to <a href="%1$s" target="_blank">Airwallex</a> to specify the domain names that you\'ll register with Apple.', 'airwallex-online-payments-gateway'),
            $this->is_sandbox() ? self::DEMO_REGISTER_DOMAIN_URL : self::REGISTER_DOMAIN_URL
        )
    ?>
</span>
<div class="wc-airwallex-settings-field-container">
    <label for="<?php echo esc_attr( $fieldKey ); ?>">
        <?php echo __('Register domain file', 'airwallex-online-payments-gateway'); ?>
    </label>
    <fieldset>
        <legend class="screen-reader-text">
            <span><?php echo __('Register domain file', 'airwallex-online-payments-gateway'); ?></span>
        </legend>
        <label for="<?php echo esc_attr( $fieldKey ); ?>">
            <button type="submit"
                    class="<?php echo esc_attr( $data['class'] ); ?>"
                    name="<?php echo esc_attr( $fieldKey ); ?>"
                    id="<?php echo esc_attr( $fieldKey ); ?>"
                    value="<?php echo esc_attr( $fieldKey ); ?>"
                    <?php disabled( $data['disabled'], true ); ?>>
                <?php echo wp_kses_post( $data['label'] ); ?>
            </button>
        </label>
        <br />
    </fieldset>
</div>
<hr>
