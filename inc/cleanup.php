<?php
/**
 * EDD File Watermarking Cleanup Functions
 *
 * @package EDDFileWatermarking
 * @subpackage Functions
 * @since 1.0
 */

namespace EDDFileWatermarking;

/**
 * Cleans up the temp download files once daily.
 *
 * @since 1.0.0
 */
function edd_watermark_cleanup() {
	if ( ! function_exists( 'edd_get_upload_dir' ) ) {
		return;
	}

	$path = sprintf( '%s/%s/*', rtrim( \edd_get_upload_dir(), '/' ), 'temp' );
	if ( ! empty( glob( $path ) ) ) {
		foreach ( glob( $path ) as $path ) {
			if ( is_dir( $path ) ) { // this is in case an index.php gets added to the /temp/ folder.

				array_map( 'unlink', array_filter( (array) glob( $path . '/*' ) ) );
				global $wp_filesystem;

				if ( ! empty( $wp_filesystem ) ) {
					$wp_filesystem->rmdir( $path, true );
				}
			}
		}
	}
}
