<?php
/**
 * EDD File Watermarking Functions
 *
 * @package EDDFileWatermarking
 * @subpackage Functions
 * @since 1.0
 */

namespace EDDFileWatermarking;

/**
 * Stop an eligible download when its personalized archive cannot be produced.
 *
 * EDD's `edd_requested_file` filter requires a file-path string. Returning an
 * empty value or a WP_Error is not portable across EDD and Software Licensing
 * versions, so a generic retryable HTTP error is the only reliable fail-closed
 * result at this boundary.
 *
 * @since 1.3.0
 *
 * @param string $requested_file Original requested file.
 * @param string $reason         Internal machine-readable failure reason.
 * @return string Original path only when the emergency fail-open filter is enabled.
 * @throws \RuntimeException If a custom wp_die handler returns.
 */
function fail_watermarked_download( $requested_file, $reason ) {
	/**
	 * Permit an operator to temporarily restore the legacy fail-open behavior.
	 *
	 * This emergency escape hatch serves the unwatermarked source ZIP and should
	 * only be enabled while diagnosing an outage.
	 *
	 * @since 1.3.0
	 *
	 * @param bool   $fail_open      Whether to serve the original source ZIP.
	 * @param string $reason         Internal machine-readable failure reason.
	 * @param string $requested_file Original requested file.
	 */
	$fail_open = (bool) apply_filters( 'edd_file_watermarking_fail_open', false, $reason, $requested_file );

	/**
	 * Fires whenever an eligible personalized download cannot be produced.
	 *
	 * @since 1.3.0
	 *
	 * @param string $reason         Internal machine-readable failure reason.
	 * @param string $requested_file Original requested file.
	 * @param bool   $fail_open      Whether the emergency escape hatch is active.
	 */
	do_action( 'edd_file_watermarking_failed', $reason, $requested_file, $fail_open );

	if ( $fail_open ) {
		return $requested_file;
	}

	wp_die(
		esc_html__( 'The personalized download could not be prepared. Please try again or contact support.', 'edd-file-watermarking' ),
		esc_html__( 'Download temporarily unavailable', 'edd-file-watermarking' ),
		[ 'response' => 503 ]
	);

	// A custom wp_die handler may return; throwing still prevents source delivery.
	throw new \RuntimeException( 'EDD File Watermarking could not safely prepare the requested download.' );
}

/**
 * Create a collision-resistant request directory while preserving the ZIP name.
 *
 * @since 1.3.0
 *
 * @param string $customer_directory Customer's temporary root directory.
 * @return string|false Created directory, or false on failure.
 */
function create_watermark_request_directory( $customer_directory ) {
	try {
		$request_token = bin2hex( random_bytes( 16 ) );
	} catch ( \Throwable $exception ) {
		return false;
	}

	$request_directory = $customer_directory . '/' . $request_token;

	return wp_mkdir_p( $request_directory ) ? $request_directory : false;
}

/**
 * Detect a single top-level ZIP directory, including implicit directory entries.
 *
 * Many release builders add `plugin/file.php` without a separate `plugin/`
 * entry. All archive entries must share the same first path segment before it
 * can be treated as the package base path.
 *
 * @since 1.3.0
 *
 * @param \ZipArchive $zip Open archive.
 * @return string|false Base path, an empty string for a flat/multi-root ZIP, or false on read failure.
 */
function detect_zip_base_path( $zip ) {
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$num_files = $zip->numFiles;

	if ( 0 === $num_files ) {
		return '';
	}

	$base_path = null;

	for ( $index = 0; $index < $num_files; $index++ ) {
		$entry_name = $zip->getNameIndex( $index );

		if ( false === $entry_name ) {
			return false;
		}

		$separator = strpos( $entry_name, '/' );

		if ( false === $separator || 0 === $separator ) {
			return '';
		}

		$entry_base_path = substr( $entry_name, 0, $separator + 1 );

		if ( null === $base_path ) {
			$base_path = $entry_base_path;
		} elseif ( $base_path !== $entry_base_path ) {
			return '';
		}
	}

	return is_string( $base_path ) ? $base_path : '';
}

/**
 * Close an archive without allowing an extension exception to skip rollback.
 *
 * @since 1.3.0
 *
 * @param \ZipArchive $zip Open archive.
 * @return bool Whether the archive committed successfully.
 */
function close_watermark_zip( $zip ) {
	try {
		return true === $zip->close();
	} catch ( \Throwable $exception ) {
		return false;
	}
}

/**
 * Sign the file before download.
 *
 * @since  1.0.0
 *
 * @param  string                   $requested_file The requested file.
 * @param  array<string,mixed>      $download_files The download files.
 * @param  string                   $file_key       The file key.
 * @param  array<string,mixed>|null $args The args.
 *
 * @return string The requested file.
 */
function watermark_edd_download( $requested_file, $download_files, $file_key, $args = null ) {
	$requested_url   = wp_parse_url( $requested_file );
	$requested_path  = is_array( $requested_url ) && isset( $requested_url['path'] ) ? $requested_url['path'] : $requested_file;
	$plugin_filename = basename( $requested_path );
	$is_zip          = 'zip' === strtolower( pathinfo( $plugin_filename, PATHINFO_EXTENSION ) );

	// This is a request from the EDD Software Licensing plugin. Backfill $args.
	if ( null === $args ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = [
			'download' => isset( $_GET['id'] ) ? intval( $_GET['id'] ) : null,
			'license'  => isset( $_GET['license'] ) ? sanitize_text_field( wp_unslash( $_GET['license'] ) ) : null,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Filter the plugin file names to apply the watermark to.
	 *
	 * Example, Here you add plugins you want to watermark.
	 * [
	 *    'wp-fusion.zip',
	 * ]
	 *
	 * @param array  $plugin_matches The plugin file names to apply the watermark to.
	 * @param string $plugin_filename The plugin file name.
	 *
	 * @return null|array The file names to watermark, or null|empty to apply to all ZIP archives.
	 */
	$plugin_matches = apply_filters( 'watermark_edd_download_list', [], $plugin_filename );

	if ( ! empty( $plugin_matches ) && ! in_array( $plugin_filename, $plugin_matches, true ) ) {
		return $requested_file;
	}

	// Without an explicit allowlist, non-ZIP EDD products are outside scope.
	if ( empty( $plugin_matches ) && ! $is_zip ) {
		return $requested_file;
	}

	if ( ! class_exists( '\ZipArchive' ) ) {
		return fail_watermarked_download( $requested_file, 'zip_extension_unavailable' );
	}

	/**
	 * Filter whether ZIP watermarking is available for this request.
	 *
	 * This primarily allows an environment health check to disable watermarking
	 * without allowing an unmodified temporary copy to escape.
	 *
	 * @since 1.3.0
	 *
	 * @param bool $available Whether ZIP watermarking is available.
	 */
	if ( ! apply_filters( 'edd_file_watermarking_zip_archive_available', true ) ) {
		return fail_watermarked_download( $requested_file, 'zip_health_check_failed' );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended

	$payment_id  = null;
	$license_key = '';

	// Get eddfile query variable request.
	if ( isset( $_GET['eddfile'] ) ) {
		// Get eddfile query string parameter.
		$eddfile = rawurldecode( sanitize_text_field( wp_unslash( $_GET['eddfile'] ) ) );

		// Split EDD file.
		$order_parts = explode( ':', $eddfile );

		// Purchase ID.
		$payment_id = intval( $order_parts[0] );

		// Get the license from the payment.
		$licensing = function_exists( 'edd_software_licensing' ) ? \edd_software_licensing() : null;
		$license   = is_object( $licensing ) && method_exists( $licensing, 'get_license_by_purchase' )
			? $licensing->get_license_by_purchase( $payment_id )
			: null;

		// Get the license key.
		$license_key = is_object( $license ) && isset( $license->license_key ) ? (string) $license->license_key : '';
	} elseif ( isset( $_GET['license'] ) ) {
		// Process /edd-sl/package_download/<base64 encoded> requests.

		// Get license key.
		$license_key = sanitize_text_field( wp_unslash( $_GET['license'] ) );

		if ( empty( $license_key ) ) {
			return fail_watermarked_download( $requested_file, 'license_key_missing' );
		}

		// Get license.
		$licensing = function_exists( 'edd_software_licensing' ) ? \edd_software_licensing() : null;
		$license   = is_object( $licensing ) && method_exists( $licensing, 'get_license' )
			? $licensing->get_license( $license_key )
			: null;

		if ( empty( $license ) ) {
			return fail_watermarked_download( $requested_file, 'license_not_found' );
		}

		// Get payment ID.
		if ( ! isset( $license->payment_id ) ) {
			return fail_watermarked_download( $requested_file, 'license_payment_missing' );
		}

		$payment_id = $license->payment_id;
	} else {
		// Unknown method or missing parameters.
		return fail_watermarked_download( $requested_file, 'request_identity_missing' );
	}

	// Check if we got the essential customer ID before proceeding.
	if ( empty( $payment_id ) ) {
		return fail_watermarked_download( $requested_file, 'payment_id_missing' );
	}

	$payment = new \EDD_Payment( $payment_id );

	// Get customer ID.
	$customer_id = intval( $payment->customer_id );

	// Check customer ID.
	if ( empty( $customer_id ) ) {
		return fail_watermarked_download( $requested_file, 'customer_id_missing' );
	}

	// Determine the base upload directory.
	if ( function_exists( 'edd_get_upload_dir' ) ) {
		// @phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.edd_get_upload_dirRemoved
		$upload_base_dir = \edd_get_upload_dir(); // EDD function returns path string.
	} else {
		$wp_upload_info  = wp_upload_dir();
		$upload_base_dir = $wp_upload_info['basedir']; // WP function returns array.
	}

	$zip_path = sprintf( '%s/%s/%d', rtrim( $upload_base_dir, '/' ), 'temp/edd-file-watermarking', $customer_id );

	if ( ! wp_mkdir_p( $zip_path ) ) {
		return fail_watermarked_download( $requested_file, 'customer_directory_failed' );
	}

	$request_directory = create_watermark_request_directory( $zip_path );

	if ( false === $request_directory ) {
		return fail_watermarked_download( $requested_file, 'request_directory_failed' );
	}

	// Create old zip file name.
	$zip_url_path_parsed = wp_parse_url( $requested_file );
	if ( ! is_array( $zip_url_path_parsed ) || empty( $zip_url_path_parsed['path'] ) ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'source_path_invalid' );
	}
	$requested_file_old = $zip_url_path_parsed['path'];

	if ( 0 !== strpos( $requested_file_old, ABSPATH ) ) {
		// During a manual download, ABSPATH is included, but during an EDDSL update we only have the request URL, so this accounts for that.
		$requested_file_old = sprintf( '%s%s', rtrim( ABSPATH, '/' ), $requested_file_old );
	}

	if ( ! is_file( $requested_file_old ) || ! is_readable( $requested_file_old ) ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'source_unreadable' );
	}

	// Create new zip file name.
	$requested_file_new = sprintf( '%s/%s', $request_directory, $plugin_filename );

	// Copy old file to new.
	if ( ! @copy( $requested_file_old, $requested_file_new ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Copy failure is handled and fails closed.
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'source_copy_failed' );
	}

	$unwatermarked_hash = hash_file( 'sha256', $requested_file_new );

	if ( false === $unwatermarked_hash ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'source_hash_failed' );
	}

	// Unzip file.
	$zip = new \ZipArchive();

	/**
	 * Filter the ZIP archive instance used for a customer copy.
	 *
	 * The returned object must extend ZipArchive. This seam also permits focused
	 * failure testing of the transactional copy path.
	 *
	 * @since 1.3.0
	 *
	 * @param \ZipArchive $zip                ZIP archive instance.
	 * @param string      $requested_file_new Customer-copy path.
	 */
	$zip = get_filtered_zip_archive( $zip, $requested_file_new );

	if ( ! $zip instanceof \ZipArchive || true !== $zip->open( $requested_file_new ) ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'zip_open_failed' );
	}

	// Get download ID.
	$download_id = isset( $args['download'] ) ? $args['download'] : null;

	if ( null === $download_id ) {
		// Get download ID from query string.
		$download_id = isset( $_GET['download_id'] ) ? absint( $_GET['download_id'] ) : null;
	}

	$base_path = detect_zip_base_path( $zip );

	if ( false === $base_path ) {
		close_watermark_zip( $zip );
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'zip_index_failed' );
	}

	$zip_args = [
		'license_key'    => $license_key,
		'requested_file' => $requested_file,
		'base_path'      => $base_path,
		'customer_id'    => $customer_id,
		'download_id'    => $download_id,
		'payment_id'     => $payment_id,
	];

	try {
		do_action( 'watermark_edd_download', $zip, $zip_args );
		do_action( "watermark_edd_download_{$plugin_filename}", $zip, $zip_args );
	} catch ( \Throwable $exception ) {
		consume_watermark_operation_failure( $zip );
		close_watermark_zip( $zip );
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'watermark_callback_failed' );
	}

	if ( consume_watermark_operation_failure( $zip ) ) {
		close_watermark_zip( $zip );
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'watermark_operation_failed' );
	}

	// A failed close means the customer archive was not committed reliably.
	if ( ! close_watermark_zip( $zip ) ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'zip_close_failed' );
	}

	$watermarked_hash = hash_file( 'sha256', $requested_file_new );

	if ( false === $watermarked_hash ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'watermarked_hash_failed' );
	}

	/**
	 * Filter whether an eligible archive must differ from its source copy.
	 *
	 * Disabling this check can allow a misconfigured watermark rule to serve an
	 * unpersonalized archive and should only be used for intentional no-op hooks.
	 *
	 * @since 1.3.0
	 *
	 * @param bool                $require_change Whether the archive must change.
	 * @param string              $requested_file Original requested file.
	 * @param array<string,mixed> $zip_args       Watermark context.
	 */
	$require_change = (bool) apply_filters( 'edd_file_watermarking_require_changed_archive', true, $requested_file, $zip_args );

	if ( $require_change && hash_equals( $unwatermarked_hash, $watermarked_hash ) ) {
		delete_watermark_temp_path( $request_directory );
		return fail_watermarked_download( $requested_file, 'archive_unchanged' );
	}

	// Return the new file path to EDD.
	return $requested_file_new;
}

/**
 * Pass a ZIP archive through WordPress's dynamically typed filter boundary.
 *
 * @since 1.3.0
 *
 * @param \ZipArchive $zip       ZIP archive instance.
 * @param string      $file_path Customer-copy path.
 * @return mixed Filtered value; callers must validate the object type.
 */
function get_filtered_zip_archive( $zip, $file_path ) {
	return apply_filters( 'edd_file_watermarking_zip_archive', $zip, $file_path );
}

/**
 * Get per-archive watermark operation status storage.
 *
 * @since 1.3.0
 *
 * @return \SplObjectStorage<\ZipArchive,array{attempted:int,failed:bool}>
 */
function watermark_operation_statuses() {
	static $statuses;

	if ( ! $statuses instanceof \SplObjectStorage ) {
		$statuses = new \SplObjectStorage();
	}

	return $statuses;
}

/**
 * Record whether one configured watermark operation applied successfully.
 *
 * @since 1.3.0
 *
 * @param \ZipArchive $zip     Archive being modified.
 * @param bool        $success Whether the operation applied.
 * @return void
 */
function record_watermark_operation( $zip, $success ): void {
	$statuses = watermark_operation_statuses();
	$status   = $statuses->offsetExists( $zip )
		? $statuses[ $zip ]
		: [
			'attempted' => 0,
			'failed'    => false,
		];

	++$status['attempted'];
	$status['failed'] = $status['failed'] || ! $success;
	$statuses[ $zip ] = $status;
}

/**
 * Read and clear whether any configured operation failed for an archive.
 *
 * @since 1.3.0
 *
 * @param \ZipArchive $zip Archive being modified.
 * @return bool Whether at least one attempted operation failed.
 */
function consume_watermark_operation_failure( $zip ) {
	$statuses = watermark_operation_statuses();

	if ( ! $statuses->offsetExists( $zip ) ) {
		return false;
	}

	$status = $statuses[ $zip ];
	$statuses->offsetUnset( $zip );

	return $status['failed'];
}

/**
 * Process the watermarks for the zip.
 *
 * @param \ZipArchive         $zip The zip archive.
 * @param array<string,mixed> $args The args.
 *
 * @return void
 */
function process_zip_builtin_watermarks( $zip, $args = [] ) {
	$download_id = isset( $args['download_id'] ) ? $args['download_id'] : null;
	$base_path   = isset( $args['base_path'] ) ? $args['base_path'] : ''; // Ensure base_path is available.

	$watermarks = function_exists( 'edd_get_option' ) ? \edd_get_option( 'edd_watermarks', [] ) : get_option( 'edd_settings', [] )['edd_watermarks'] ?? [];

	if ( ! is_array( $watermarks ) ) {
		$watermarks = [];
	}

	$download_watermarks = get_post_meta( $download_id, 'edd_watermark_settings', true );

	if ( is_array( $download_watermarks ) ) {
		$watermarks = array_merge( $watermarks, $download_watermarks );
	}

	foreach ( $watermarks as $watermark ) {
		// Add the watermark, passing the full args including base_path.
		watermark_zip( $zip, $watermark, $args );
	}
}

/**
 * Add watermark to zip.
 *
 * @param \ZipArchive         $zip The zip archive.
 * @param array<string,mixed> $watermark The watermark.
 * @param array<string,mixed> $args The args (contains base_path).
 *
 * @return bool Whether the watermark operation applied successfully.
 */
function watermark_zip( $zip, $watermark, $args = [] ) {
	$watermark = wp_parse_args( $watermark, [
		'type'    => 'add_file',
		'file'    => '',
		'search'  => '',
		'content' => '',
	] );

	// Keep modified content available for later rules targeting the same file.
	// SplObjectStorage isolates caches when one request processes multiple archives.
	static $archive_contents;

	if ( ! $archive_contents instanceof \SplObjectStorage ) {
		$archive_contents = new \SplObjectStorage();
	}

	if ( ! $archive_contents->offsetExists( $zip ) ) {
		$archive_contents[ $zip ] = [];
	}

	$file_contents = $archive_contents[ $zip ];

	$target_filename  = str_replace( '\\', '/', (string) $watermark['file'] );
	$content          = parse_watermark_content( $watermark['content'], $args );
	$base_path        = isset( $args['base_path'] ) ? $args['base_path'] : ''; // Extract base_path.
	$full_path_in_zip = null;

	// Never create absolute or traversal entries inside customer archives.
	if (
		'' === $target_filename
		|| '/' === substr( $target_filename, 0, 1 )
		|| preg_match( '#(?:^|/)\.\.(?:/|$)#', $target_filename )
		|| false !== strpos( $target_filename, "\0" )
	) {
		record_watermark_operation( $zip, false );
		return false;
	}

	// --- Modify logic for finding existing file slightly ---
	// We need the target relative path *within* the base_path for comparison if base_path exists.
	$target_relative_path = $base_path . $target_filename;

	// Find the actual full path of the target file within the zip archive.
	// Avoid searching if we already found/processed this target file.
	if ( array_key_exists( $target_relative_path, $file_contents ) ) {
		// Already found or processed this file.
		$full_path_in_zip = $target_relative_path;
	} else {
		// Search for the file in the zip using the target relative path.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$filename_in_zip = $zip->getNameIndex( $i );
			// Check if the filename in the zip matches the target relative path exactly.
			if ( is_string( $filename_in_zip ) && $filename_in_zip === $target_relative_path ) {
				$full_path_in_zip = $filename_in_zip;
				break; // Found it.
			}
		}
	}
	// --- End modification for finding existing file ---

	// If the file wasn't found for replacement/append, full_path_in_zip remains null.
	// For 'add_file', we don't need to pre-check existence this way.

	// Get original content using the full path (or check cache) *if* found.
	$original_content = null;
	if ( $full_path_in_zip && ! array_key_exists( $full_path_in_zip, $file_contents ) ) {
		$original_content = $zip->getFromName( $full_path_in_zip );
		if ( false === $original_content ) {
			// Could not read the file content.
			$file_contents[ $full_path_in_zip ] = false; // Mark as failed.
		} else {
			$file_contents[ $full_path_in_zip ] = $original_content;
		}
	} elseif ( $full_path_in_zip ) {
		$original_content = $file_contents[ $full_path_in_zip ];
	}

	$success = false;

	// Logic to apply the watermark (using $full_path_in_zip where appropriate).
	switch ( $watermark['type'] ) {
		case 'add_file':
			// Construct the full path using the base_path and target_filename.
			$full_target_path = $base_path . $target_filename;
			$success          = $zip->addFromString( $full_target_path, $content );
			if ( $success ) {
				$file_contents[ $full_target_path ] = $content;
			}
			break;

		case 'string_replacement':
			if ( $full_path_in_zip && false !== $original_content && '' !== $watermark['search'] ) {
				$replaced_contents = str_replace( $watermark['search'], $content, $original_content );

				if ( $original_content !== $replaced_contents ) {
					$deleted = $zip->deleteName( $full_path_in_zip ); // Delete by full path.
					$success = $deleted && $zip->addFromString( $full_path_in_zip, $replaced_contents ); // Add by full path.

					// Update static cache with full path as key.
					if ( $success ) {
						$file_contents[ $full_path_in_zip ] = $replaced_contents;
					}
				}
			}
			break;

		case 'append_to_file':
			if ( $full_path_in_zip && false !== $original_content && '' !== $content ) {
				$replaced_contents = $original_content . $content;
				$deleted           = $zip->deleteName( $full_path_in_zip ); // Delete by full path.
				$success           = $deleted && $zip->addFromString( $full_path_in_zip, $replaced_contents ); // Add by full path.

				// Update static cache with full path as key.
				if ( $success ) {
					$file_contents[ $full_path_in_zip ] = $replaced_contents;
				}
			}
			break;

		default:
			break;
	}

	$archive_contents[ $zip ] = $file_contents;
	record_watermark_operation( $zip, $success );

	return $success;
}

/**
 * Parse watermark content.
 *
 * @param string              $content The content.
 * @param array<string,mixed> $args The args.
 *
 * @return string The parsed content.
 */
function parse_watermark_content( $content, $args ) {
	$defaults = [
		'license_key' => '',
		'customer_id' => '',
		'download_id' => '',
		'payment_id'  => '',
	];

	$args = wp_parse_args( $args, $defaults );

	// Simple replacements first.
	$content = str_replace( '{license_key}', $args['license_key'], $content );
	$content = str_replace( '{customer_id}', $args['customer_id'], $content );
	$content = str_replace( '{download_id}', $args['download_id'], $content );
	$content = str_replace( '{payment_id}', $args['payment_id'], $content );

	// Parse shortcodes with attributes: {shortcode attr=value}.
	// Handles optional quotes around value: attr="value" or attr=value.
	$pattern = '/{([a-z_]+)(?:\s+([a-z_]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([a-z0-9_.-]+)))?)?}/i';
	preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL );

	foreach ( $matches as $match ) {
		$shortcode = $match[0]; // The full {shortcode ...} string.
		$tag       = strtolower( $match[1] ); // The shortcode name (e.g., customer_id).
		$attribute = isset( $match[2] ) ? strtolower( $match[2] ) : null; // The attribute name (e.g., times).
		$value     = $match[3] ?? $match[4] ?? $match[5] ?? null; // Quoted or unquoted attribute value.

		// Skip if this was already handled by simple replacement above and has no attributes.
		if ( null === $attribute && in_array( $tag, [ 'license_key', 'customer_id', 'download_id', 'payment_id' ], true ) ) {
			continue;
		}

		switch ( $tag ) {
			case 'customer_id':
				$customer_id = $args['customer_id'];

				if ( 'times' === $attribute && is_numeric( $value ) ) {
					$customer_id = $customer_id * intval( $value );
				}

				$content = str_replace( $shortcode, $customer_id, $content );
				break;

			case 'license_key':
				$license_key = $args['license_key'];

				if ( 'encoded' === $attribute && 'base64' === $value ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					$license_key = base64_encode( $license_key );
				}

				$content = str_replace( $shortcode, $license_key, $content );
				break;
		}
	}

	// Parse \r\n to PHP_EOL.
	$content = str_replace( [ '\r\n', '\\r\\n' ], PHP_EOL, $content );
	$content = str_replace( [ '\n', '\\n',  '\r', '\\r' ], PHP_EOL, $content );

	return $content;
}
