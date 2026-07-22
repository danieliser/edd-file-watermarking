<?php
/**
 * EDD symbols supplied by the commercial plugin at runtime.
 *
 * @package EDDFileWatermarking
 */

if ( ! function_exists( 'edd_get_option' ) ) {
	/**
	 * Get an EDD option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function edd_get_option( $key, $default = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $default;
	}
}

if ( ! function_exists( 'edd_sanitize_html_class' ) ) {
	/**
	 * Sanitize an EDD HTML class.
	 *
	 * @param mixed $class Class value.
	 * @return string
	 */
	function edd_sanitize_html_class( $class ) {
		return is_scalar( $class ) ? (string) $class : '';
	}
}

if ( ! function_exists( 'edd_sanitize_key' ) ) {
	/**
	 * Sanitize an EDD option key.
	 *
	 * @param mixed $key Key value.
	 * @return string
	 */
	function edd_sanitize_key( $key ) {
		return is_scalar( $key ) ? (string) $key : '';
	}
}

if ( ! function_exists( 'edd_get_upload_dir' ) ) {
	/**
	 * Get EDD's protected upload directory.
	 *
	 * @return string
	 */
	function edd_get_upload_dir() {
		return isset( $GLOBALS['edd_file_watermarking_test_upload_dir'] )
			? (string) $GLOBALS['edd_file_watermarking_test_upload_dir']
			: sys_get_temp_dir();
	}
}

if ( ! class_exists( 'EDD_Payment' ) ) {
	/**
	 * Minimal EDD payment shape used by the plugin.
	 */
	class EDD_Payment {

		/** @var int */
		public $customer_id = 0;

		/**
		 * Constructor.
		 *
		 * @param int $payment_id Payment ID.
		 */
		public function __construct( $payment_id ) {
			$this->customer_id = isset( $GLOBALS['edd_file_watermarking_test_customer_id'] )
				? (int) $GLOBALS['edd_file_watermarking_test_customer_id']
				: 0;
		}
	}
}
