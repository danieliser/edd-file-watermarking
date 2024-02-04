<?php
/**
 * EDD File Watermarking Post Editor Functions
 *
 * @package EDDFileWatermarking
 * @subpackage Post
 * @since 1.0
 */

namespace EDDFileWatermarking;

/**
 * Add the watermark meta box.
 *
 * @return void
 */
function edd_watermark_add_meta_box() {
	add_meta_box(
		'edd_watermark_meta_box',
		__( 'Watermark Settings', 'edd-file-watermarking' ),
		__NAMESPACE__ . '\\edd_watermark_meta_box_callback',
		'download',
		'normal',
		'high'
	);
}

/**
 * Render the watermark meta box.
 *
 * @param WP_Post $post The post.
 *
 * @return void
 */
function edd_watermark_meta_box_callback( $post ) {
	wp_nonce_field( plugin_basename( __FILE__ ), 'edd_watermark_nonce' );

	$watermarks = get_post_meta( $post->ID, 'edd_watermark_settings', true );
	render_watermark_table( $watermarks );
}

/**
 * Save post meta.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
function edd_watermark_save_post_meta( $post_id ) {
	if ( ! isset( $_POST['edd_watermark_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edd_watermark_nonce'] ) ), plugin_basename( __FILE__ ) ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['watermark_repeater'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$watermarks = remap_watermark_repeater_values( wp_unslash( $_POST['watermark_repeater'] ) );

	$watermarks = sanitize_watermark_repeater( $watermarks );

	update_post_meta( $post_id, 'edd_watermark_settings', $watermarks );
}
