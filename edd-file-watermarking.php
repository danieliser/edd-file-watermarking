<?php

/**
 * Plugin Name: EDD File Watermarking
 * Description: Adds an identifier to downloaded EDD files to track which customer downloaded it. Thanks @westguard!
 * Plugin URI: https://danieliser.com/downloads/edd-file-watermarking/
 * Version: 1.1.0
 * Author: Daniel Iser
 * Author URI: https://danieliser.com/
 * GitHub Plugin URI: https://github.com/danieliser/edd-file-watermarking
 * GitHub Branch: master
 *
 * @source https://gist.github.com/verygoodplugins/434398fb94b28cce0ca87d02b80d1dbc
 *
 * @author  Daniel Iser
 * @package EDDFileWatermarking
 */

namespace EDDFileWatermarking;

require_once __DIR__ . '/inc/functions.php';

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Hook in our initial check to see if we need to watermark the file.
 *
 * This calls the `watermark_edd_download` and `watermark_edd_download_{$plugin_filename}` action if the file should be watermarked.
 */
add_filter('edd_requested_file', __NAMESPACE__ . '\\watermark_edd_download', 10, 4);

/**
 * Process the built global & per download settings in watermarks on a given zip file.
 */
add_action('watermark_edd_download', __NAMESPACE__ . '\\process_zip_builtin_watermarks', 10, 2);

/**
 * Add global extension settings.
 */
add_filter('edd_settings_sections_extensions', __NAMESPACE__ . '\\edd_watermark_register_settings_section');
add_filter('edd_settings_extensions', __NAMESPACE__ . '\\edd_watermark_add_settings');
add_filter('edd_settings_sanitize_watermark_repeater', __NAMESPACE__ . '\\sanitize_watermark_repeater_settings', 10, 2);

/**
 * Add the watermark meta box.
 */
add_action('add_meta_boxes', __NAMESPACE__ . '\\edd_watermark_add_meta_box');
add_action('save_post', __NAMESPACE__ . '\\edd_watermark_save_post_meta');

/**
 * Cleanup old watermarks on a daily basis.
 */
add_action('edd_daily_scheduled_events', __NAMESPACE__ . '\\edd_watermark_cleanup');
