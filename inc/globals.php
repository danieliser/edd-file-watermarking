<?php
/**
 * EDD File Watermarking Global Namespaced Functions
 *
 * @package EDDFileWatermarking
 * @subpackage Functions
 * @since 1.0
 */

/**
 * Textarea Callback
 *
 * Renders textarea fields.
 *
 * @since 1.0
 *
 * @param array $args Arguments passed by the setting.
 *
 * @return void
 */
function edd_textarea_unslashed_callback( $args ) {

	$edd_option = edd_get_option( $args['id'] );

	if ( $edd_option ) {
		if ( is_array( $edd_option ) ) {
			$value = implode( "\n", maybe_unserialize( $edd_option ) );
		} else {
			$value = $edd_option;
		}
	} else {
		$value = isset( $args['std'] ) ? $args['std'] : '';
	}

	$class       = edd_sanitize_html_class( $args['field_class'] );
	$placeholder = ! empty( $args['placeholder'] )
		? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"'
		: '';

	$readonly = true === $args['readonly'] ? ' readonly="readonly"' : '';

	$html  = '<textarea class="' . $class . '" cols="50" rows="5" ' . $placeholder . ' id="edd_settings[' . edd_sanitize_key( $args['id'] ) . ']" name="edd_settings[' . esc_attr( $args['id'] ) . ']"' . $readonly . '>' . esc_textarea( $value ) . '</textarea>';
	$html .= '<p class="description"> ' . wp_kses_post( $args['desc'] ) . '</p>';

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo apply_filters( 'edd_after_setting_output', $html, $args );
}

/**
 * Watermark Repeater Callback
 *
 * Renders the watermark repeater field.
 *
 * @since 1.0
 *
 * @param array $args Arguments passed by the setting.
 *
 * @return void
 */
function edd_watermark_repeater_callback( $args ) {
	$watermarks = edd_get_option( $args['id'], [] );

	\EDDFileWatermarking\render_watermark_table( $watermarks, $name = 'edd_settings[' . esc_attr( $args['id'] ) . ']' );
}

/**
 * Sanitize the watermark repeater.
 *
 * @param array  $value The value.
 * @param string $key The key.
 *
 * @return array The sanitized value.
 */
function sanitize_watermark_repeater_settings( $value, $key ) {
	if ( ! isset( $value ) ) {
		return $value;
	}

	if ( is_array( $value ) && ! empty( $value ) && is_array( $value[0] ) && isset( $value[0]['type'] ) ) {
		// This is already processed value, must be sanitizing before rendering ?
		return $value;
	}

	$value = \EDDFileWatermarking\remap_watermark_repeater_values( $value );

	return \EDDFileWatermarking\sanitize_watermark_repeater( $value );
}
