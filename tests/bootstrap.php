<?php
/**
 * Unit/integration test bootstrap.
 *
 * @package EDDFileWatermarking
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/phpstan-bootstrap.php';
require_once __DIR__ . '/CloseFailingZipArchive.php';

$GLOBALS['edd_file_watermarking_test_filters'] = [];

/**
 * Exception used to assert fail-closed wp_die responses.
 */
class WatermarkWpDieException extends RuntimeException {

	/** @var int */
	public $response;

	/**
	 * @param string $message  Failure message.
	 * @param int    $response HTTP response status.
	 */
	public function __construct( $message, $response ) {
		parent::__construct( $message );
		$this->response = $response;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a focused test filter.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return bool
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['edd_file_watermarking_test_filters'][ $hook_name ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		];
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register a focused test action.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return bool
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply focused test filters.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Filtered value.
	 * @param mixed  ...$args   Extra arguments.
	 * @return mixed
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		$callbacks = isset( $GLOBALS['edd_file_watermarking_test_filters'][ $hook_name ] )
			? $GLOBALS['edd_file_watermarking_test_filters'][ $hook_name ]
			: [];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $registered ) {
				$filter_args = array_merge( [ $value ], $args );
				$value       = call_user_func_array(
					$registered['callback'],
					array_slice( $filter_args, 0, $registered['accepted_args'] )
				);
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Run focused test actions.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Arguments.
	 * @return void
	 */
	function do_action( $hook_name, ...$args ) {
		$callbacks = isset( $GLOBALS['edd_file_watermarking_test_filters'][ $hook_name ] )
			? $GLOBALS['edd_file_watermarking_test_filters'][ $hook_name ]
			: [];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $registered ) {
				call_user_func_array(
					$registered['callback'],
					array_slice( $args, 0, $registered['accepted_args'] )
				);
			}
		}
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/** @param mixed $value Value. @return string */
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/** @param string $text Text. @param string $domain Text domain. @return string */
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $text;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Throw instead of terminating the PHPUnit process.
	 *
	 * @param string              $message Failure message.
	 * @param string              $title   Failure title.
	 * @param array<string,mixed> $args    Error arguments.
	 * @return void
	 * @throws WatermarkWpDieException Always.
	 */
	function wp_die( $message = '', $title = '', $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$response = isset( $args['response'] ) ? (int) $args['response'] : 500;
		throw new WatermarkWpDieException( $message, $response );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/** @param mixed $value Value. @return string */
	function sanitize_key( $value ) {
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/** @param mixed $value Value. @return string */
	function sanitize_textarea_field( $value ) {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/** @param mixed $value Value. @return mixed */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/** @param mixed $value Value. @return int */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/** @param string $url URL or path. @return array<string,mixed>|false */
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/** @param string $target Directory path. @return bool */
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test bootstrap.
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/** @param string $file File path. @return void */
	function wp_delete_file( $file ) {
		if ( is_link( $file ) || is_file( $file ) ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test bootstrap.
		}
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/** @return array{basedir:string} */
	function wp_upload_dir() {
		return [ 'basedir' => sys_get_temp_dir() ];
	}
}

if ( ! function_exists( 'edd_software_licensing' ) ) {
	/** @return object */
	function edd_software_licensing() {
		return $GLOBALS['edd_file_watermarking_test_licensing'];
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Minimal WordPress-compatible argument merge for focused tests.
	 *
	 * @param mixed               $args     Supplied arguments.
	 * @param array<string,mixed> $defaults Default arguments.
	 * @return array<string,mixed>
	 */
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

require_once dirname( __DIR__ ) . '/inc/cleanup.php';
require_once dirname( __DIR__ ) . '/inc/watermark.php';
require_once dirname( __DIR__ ) . '/inc/settings.php';
