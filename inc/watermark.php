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
 * Sign the file before download.
 *
 * @since  1.0.0
 *
 * @param  string $requested_file The requested file.
 * @param  array  $download_files The download files.
 * @param  string $file_key       The file key.
 * @param  array  $args           The args.
 *
 * @return string The requested file.
 */
function watermark_edd_download( $requested_file, $download_files, $file_key, $args = null ) {
	// Plugin file name.
	$plugin_filename = basename( $requested_file );

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
	 * @return null|array The plugin file names to apply the watermark to, or null|empty array to apply to all.
	 */
	$plugin_matches = apply_filters( 'watermark_edd_download_list', [], $plugin_filename );

	if ( ! empty( $plugin_matches ) && ! in_array( $plugin_filename, $plugin_matches, true ) ) {
		return $requested_file;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended

	$payment_id  = null;
	$license_key = null;

	// Get eddfile query variable request.
	if ( isset( $_GET['eddfile'] ) ) {
		// Get eddfile query string parameter.
		$eddfile = rawurldecode( sanitize_text_field( wp_unslash( $_GET['eddfile'] ) ) );

		// Split EDD file.
		$order_parts = explode( ':', $eddfile );

		// Purchase ID.
		$payment_id = intval( $order_parts[0] );

		// Get the license from the payment.
		$license = function_exists( 'edd_software_licensing' ) ? \edd_software_licensing()->get_license_by_purchase( $payment_id ) : null;

		// Get the license key.
		$license_key = $license ? $license->license_key : '';
	} elseif ( isset( $_GET['license'] ) ) {
		// Process /edd-sl/package_download/<base64 encoded> requests.

		// Get license key.
		$license_key = sanitize_text_field( wp_unslash( $_GET['license'] ) );

		if ( empty( $license_key ) ) {
			return $requested_file;
		}

		// Get license.
		$license = function_exists( 'edd_software_licensing' ) ? \edd_software_licensing()->get_license( $license_key ) : null;

		if ( empty( $license ) ) {
			return $requested_file;
		}

		// Get payment ID.
		if ( ! isset( $license->payment_id ) ) {
			return $requested_file;
		}

		$payment_id = $license->payment_id;
	} else {
		// Unknown method or missing parameters.
		return $requested_file;
	}

	// Check if we got the essential customer ID before proceeding.
	if ( empty( $payment_id ) ) {
		return $requested_file;
	}

	$payment = new \EDD_Payment( $payment_id );

	// Get customer ID.
	$customer_id = intval( $payment->customer_id );

	// Check customer ID.
	if ( empty( $customer_id ) ) {
		return $requested_file;
	}

	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// Determine the base upload directory.
	if ( function_exists( 'edd_get_upload_dir' ) ) {
		// @phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.edd_get_upload_dirRemoved
		$upload_base_dir = \edd_get_upload_dir(); // EDD function returns path string.
	} else {
		$wp_upload_info  = wp_upload_dir();
		$upload_base_dir = $wp_upload_info['basedir']; // WP function returns array.
	}

	$zip_path = sprintf( '%s/%s/%d', rtrim( $upload_base_dir, '/' ), 'temp', $customer_id );

	$split_path = explode( '/', $zip_path );

	// Check if the temp directory exists.
	foreach ( $split_path as $key => $path ) {
		// Check each directory in the path to see if it exists as mkdir doesn't support recursive creation.
		$check_path = implode( '/', array_slice( $split_path, 0, $key + 1 ) );

		if ( ! file_exists( $check_path ) ) {
			$wp_filesystem->mkdir( $check_path );
		}
	}

	// Create old zip file name.
	$zip_url_path_parsed = wp_parse_url( $requested_file );
	$requested_file_old  = $zip_url_path_parsed['path'];

	if ( false === strpos( $requested_file_old, ABSPATH ) ) {
		// During a manual download, ABSPATH is included, but during an EDDSL update we only have the request URL, so this accounts for that.
		$requested_file_old = sprintf( '%s%s', rtrim( ABSPATH, '/' ), $requested_file_old );
	}

	// Create new zip file name.
	$requested_file_new = sprintf( '%s/%s', $zip_path, $plugin_filename );

	// If new file already exists, delete it.
	if ( file_exists( $requested_file_new ) ) {
		wp_delete_file( $requested_file_new );
	}

	// Copy old file to new.
	if ( ! copy( $requested_file_old, $requested_file_new ) ) {
		return $requested_file;
	}

	if ( ! class_exists( '\ZipArchive' ) ) {
		return $requested_file;
	}

	// Unzip file.
	$zip = new \ZipArchive();
	if ( $zip->open( $requested_file_new ) === true ) {
		// Get download ID.
		$download_id = isset( $args['download'] ) ? $args['download'] : null;

		if ( null === $download_id ) {
			// Get download ID from query string.
			$download_id = isset( $_GET['download_id'] ) ? absint( $_GET['download_id'] ) : null;
		}

		// Get the expected directory name from the requested file.
		$expected_directory = basename( $requested_file, '.zip' ) . '/';

		// Get the name of the first file/folder in the zip.
		$first_entry = $zip->getNameIndex( 0 );

		// Determine if the plugin is zipped within the expected directory.
		$directory = ( strpos( $first_entry, $expected_directory ) === 0 ) ? $expected_directory : '';

		$zip_args = [
			'license_key'    => isset( $license_key ) ? $license_key : '',
			'requested_file' => $requested_file,
			'directory'      => $directory,
			'customer_id'    => $customer_id,
			'download_id'    => $download_id,
			'payment_id'     => $payment_id,
		];

		do_action( 'watermark_edd_download', $zip, $zip_args );
		do_action( "watermark_edd_download_{$plugin_filename}", $zip, $zip_args );

		// Close the zip file.
		$zip->close();
	}

	// Return the new file path to EDD.
	return $requested_file_new;
}

/**
 * Process the watermarks for the zip.
 *
 * @param \ZipArchive $zip The zip archive.
 * @param array       $args The args.
 *
 * @return void
 */
function process_zip_builtin_watermarks( $zip, $args = [] ) {
	$download_id = isset( $args['download_id'] ) ? $args['download_id'] : null;
	$directory   = isset( $args['directory'] ) ? $args['directory'] : '';

	$watermarks = function_exists( 'edd_get_option' ) ? \edd_get_option( 'edd_watermarks', [] ) : get_option( 'edd_settings', [] )['edd_watermarks'] ?? [];

	if ( ! is_array( $watermarks ) ) {
		$watermarks = [];
	}

	$download_watermarks = get_post_meta( $download_id, 'edd_watermark_settings', true );

	if ( is_array( $download_watermarks ) ) {
		$watermarks = array_merge( $watermarks, $download_watermarks );
	}

	foreach ( $watermarks as $watermark ) {
		// Set the file with the directory.
		$watermark['file'] = $directory . $watermark['file'];

		// Add the watermark.
		watermark_zip( $zip, $watermark, $args );
	}
}

/**
 * Add watermark to zip.
 *
 * @param \ZipArchive $zip The zip archive.
 * @param array       $watermark The watermark.
 * @param array       $args The args.
 *
 * @return void
 */
function watermark_zip( $zip, $watermark = [], $args ) {
	$watermark = wp_parse_args( $watermark, [
		'type'    => 'add_file',
		'file'    => '',
		'search'  => '',
		'content' => '',
	] );

	// Necessary var if you want to apply more than one string_replacement rule on the same file
	// because getFromName() will return false on subsequent calls after modifying $zip with addFromString() and would not apply the subsequent rule.
	// We now store the *full path* found in the zip as the key.
	static $file_contents = [];

	$file_to_modify = $watermark['file'];
	$content        = parse_watermark_content( $watermark['content'], $args );

	if ( ! isset( $file_contents[ $file_to_modify ] ) ) {
		$file_contents[ $file_to_modify ] = $zip->getFromName( $file_to_modify );
	}

	// Logic to apply the watermark.
	switch ( $watermark['type'] ) {
		case 'add_file':
			$zip->addFromString( $file_to_modify, $content );
			break;

		case 'string_replacement':
			if ( $file_contents[ $file_to_modify ] ) {
				$replaced_contents = str_replace( $watermark['search'], $content, $file_contents[ $file_to_modify ] );

				if ( $file_contents[ $file_to_modify ] !== $replaced_contents ) {
					$zip->deleteName( $file_to_modify );
					$zip->addFromString( $file_to_modify, $replaced_contents );

					$file_contents[ $file_to_modify ] = $replaced_contents;
				}
			}
			break;

		case 'append_to_file':
			if ( $file_contents[ $file_to_modify ] ) {
				$replaced_contents = $file_contents[ $file_to_modify ] . $content;

				if ( $file_contents[ $file_to_modify ] !== $replaced_contents ) {
					$zip->deleteName( $file_to_modify );
					$zip->addFromString( $file_to_modify, $replaced_contents );

					$file_contents[ $file_to_modify ] = $replaced_contents;
				}
			}
			break;

		default:
			break;
	}
}

/**
 * Parse watermark content.
 *
 * @param string $content The content.
 * @param array  $args The args.
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
$pattern = '/{([a-z_]+)(?:\s+([a-z_]+)(?:=([a-z0-9_]+))?)?}/i';
	preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$shortcode = $match[0]; // The full {shortcode ...} string.
		$tag       = $match[1]; // The shortcode name (e.g., customer_id).
		$attribute = isset( $match[2] ) ? $match[2] : null; // The attribute name (e.g., times).
		$value     = isset( $match[4] ) ? $match[4] : null; // The attribute value (e.g., 2).

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
	$content = str_replace( '\\r\\n', PHP_EOL, $content );

	return $content;
}
