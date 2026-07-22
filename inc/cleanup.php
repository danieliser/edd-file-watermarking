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
function edd_watermark_cleanup(): void {
	if ( ! function_exists( 'edd_get_upload_dir' ) ) {
		return;
	}

	$path                 = sprintf( '%s/%s/*', rtrim( \edd_get_upload_dir(), '/' ), 'temp/edd-file-watermarking' );
	$customer_directories = glob( $path );

	/**
	 * Filter how old a customer copy must be before scheduled cleanup removes it.
	 *
	 * The default grace period prevents the daily event from deleting an archive
	 * while another request is still preparing or streaming it.
	 *
	 * @since 1.3.0
	 *
	 * @param int $minimum_age Minimum age in seconds.
	 */
	$minimum_age = max( 0, (int) apply_filters( 'edd_file_watermarking_cleanup_minimum_age', 3600 ) );
	$cutoff      = time() - $minimum_age;

	if ( is_array( $customer_directories ) ) {
		foreach ( $customer_directories as $customer_directory ) {
			$entries = is_dir( $customer_directory ) ? scandir( $customer_directory ) : false;

			if ( false === $entries ) {
				continue;
			}

			foreach ( array_diff( $entries, [ '.', '..' ] ) as $entry ) {
				$entry_path = $customer_directory . '/' . $entry;
				$modified   = @filemtime( $entry_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A concurrent cleanup can remove the path.

				if ( false !== $modified && $modified <= $cutoff ) {
					delete_watermark_temp_path( $entry_path );
				}
			}

			remove_empty_watermark_temp_directory( $customer_directory );
		}
	}
}

/**
 * Remove an empty plugin-owned temporary directory.
 *
 * @since 1.3.0
 *
 * @param string $path Temporary directory path.
 * @return void
 */
function remove_empty_watermark_temp_directory( $path ): void {
	$entries = is_dir( $path ) ? scandir( $path ) : false;

	if ( false !== $entries && [] === array_values( array_diff( $entries, [ '.', '..' ] ) ) ) {
		// A concurrent cleanup may remove the directory first.
		@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}

/**
 * Recursively remove one plugin-owned temporary path without following links.
 *
 * Random per-request directories prevent concurrent downloads from sharing a
 * customer copy, so scheduled and rollback cleanup must support nested paths.
 *
 * @since 1.3.0
 *
 * @param string $path Temporary file or directory path.
 * @return void
 */
function delete_watermark_temp_path( $path ): void {
	if ( is_link( $path ) || is_file( $path ) ) {
		wp_delete_file( $path );
		return;
	}

	if ( ! is_dir( $path ) ) {
		return;
	}

	$entries = scandir( $path );

	if ( false === $entries ) {
		return;
	}

	foreach ( array_diff( $entries, [ '.', '..' ] ) as $entry ) {
		delete_watermark_temp_path( $path . '/' . $entry );
	}

	// The directory is plugin-owned and empty; a concurrent cleanup may win.
	@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
