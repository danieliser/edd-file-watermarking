<?php
/**
 * Store-owned watermark configuration regression tests.
 *
 * @package EDDFileWatermarking
 */

use PHPUnit\Framework\TestCase;

/**
 * Validate configurations that are intentionally excluded from product ZIPs.
 */
class WatermarkConfigTest extends TestCase {

	/**
	 * Popup Maker's updater must read the packaged version dynamically.
	 *
	 * @return void
	 */
	public function test_popup_maker_updater_does_not_pin_a_release_version(): void {
		$config_path = dirname( __DIR__ ) . '/watermark-configs/popup-maker-core-updater.json';
		$config_json = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.

		$this->assertIsString( $config_json );
		$config = json_decode( $config_json, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'watermarks', $config );
		$this->assertIsArray( $config['watermarks'] );
		$this->assertCount( 1, $config['watermarks'] );
		$this->assertIsArray( $config['watermarks'][0] );
		$this->assertArrayHasKey( 'content', $config['watermarks'][0] );

		$content = $config['watermarks'][0]['content'];
		$this->assertIsString( $content );
		$this->assertStringContainsString( "popup_maker_config( 'version' )", $content );
		$this->assertDoesNotMatchRegularExpression( "/'version'\\s*=>\\s*'\\d+\\.\\d+\\.\\d+'/", $content );
	}
}
