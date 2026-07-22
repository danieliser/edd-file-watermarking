<?php
/**
 * Real ZIP watermark regression tests.
 *
 * @package EDDFileWatermarking
 */

use PHPUnit\Framework\TestCase;
use function EDDFileWatermarking\parse_watermark_content;
use function EDDFileWatermarking\watermark_zip;

/**
 * Exercise the actual ZipArchive mutation path.
 */
class WatermarkZipTest extends TestCase {

	/**
	 * Temporary archives created by a test.
	 *
	 * @var string[]
	 */
	private $temporary_archives = [];

	/**
	 * Delete temporary archives after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->temporary_archives as $archive ) {
			if ( file_exists( $archive ) ) {
				unlink( $archive ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-only temporary file cleanup.
			}
		}

		$this->temporary_archives = [];
		parent::tearDown();
	}

	/**
	 * Advanced placeholders support quoted and unquoted values.
	 *
	 * @return void
	 */
	public function test_advanced_placeholders_use_the_captured_attribute_value(): void {
		$parsed = parse_watermark_content(
			'{customer_id times=3}|{license_key encoded="base64"}|{license_key encoded=base64}|{license_key encoded=\'base64\'}',
			[
				'customer_id' => 7,
				'license_key' => 'license-123',
			]
		);

		$encoded = base64_encode( 'license-123' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Verifies the product's explicit encoded-watermark feature.
		$this->assertSame( "21|{$encoded}|{$encoded}|{$encoded}", $parsed );
	}

	/**
	 * Multiple rules mutate a rooted ZIP and preserve one installable root.
	 *
	 * @return void
	 */
	public function test_multiple_rules_modify_a_real_rooted_zip(): void {
		$archive = $this->create_archive(
			[
				'example-plugin/example-plugin.php' => "<?php\n/* TOKEN */\n",
				'example-plugin/readme.txt'         => "Example\n",
			]
		);
		$zip     = $this->open_archive( $archive );
		$args    = [
			'base_path'   => 'example-plugin/',
			'customer_id' => 42,
			'license_key' => 'abc-123',
			'payment_id'  => 99,
		];

		watermark_zip(
			$zip,
			[
				'type'    => 'string_replacement',
				'file'    => 'example-plugin.php',
				'search'  => '/* TOKEN */',
				'content' => '/* customer={customer_id times=2}; license={license_key encoded="base64"} */',
			],
			$args
		);
		watermark_zip(
			$zip,
			[
				'type'    => 'append_to_file',
				'file'    => 'example-plugin.php',
				'content' => "// payment={payment_id}\n",
			],
			$args
		);
		watermark_zip(
			$zip,
			[
				'type'    => 'add_file',
				'file'    => 'assets/customer.css',
				'content' => '/* account-{customer_id} */',
			],
			$args
		);

		$this->assertTrue( $zip->close() );

		$zip      = $this->open_archive( $archive );
		$contents = $zip->getFromName( 'example-plugin/example-plugin.php' );
		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'customer=84', $contents );
		$this->assertStringContainsString( 'license=' . base64_encode( 'abc-123' ), $contents ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Verifies the product's explicit encoded-watermark feature.
		$this->assertStringContainsString( 'payment=99', $contents );
		$this->assertSame( '/* account-42 */', $zip->getFromName( 'example-plugin/assets/customer.css' ) );

		for ( $index = 0; $index < $zip->numFiles; $index++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$entry_name = $zip->getNameIndex( $index );
			$this->assertIsString( $entry_name );
			$this->assertStringStartsWith( 'example-plugin/', $entry_name );
		}

		$this->assertTrue( $zip->close() );
	}

	/**
	 * Content caches cannot leak between archives with the same internal path.
	 *
	 * @return void
	 */
	public function test_modified_content_cache_is_isolated_per_archive(): void {
		$first_archive  = $this->create_archive( [ 'plugin/file.txt' => 'first TOKEN' ] );
		$second_archive = $this->create_archive( [ 'plugin/file.txt' => 'second TOKEN' ] );
		$first_zip      = $this->open_archive( $first_archive );
		$second_zip     = $this->open_archive( $second_archive );
		$watermark      = [
			'type'    => 'string_replacement',
			'file'    => 'file.txt',
			'search'  => 'TOKEN',
			'content' => 'customer-{customer_id}',
		];

		watermark_zip( $first_zip, $watermark, [
			'base_path'   => 'plugin/',
			'customer_id' => 1,
		] );
		watermark_zip( $second_zip, $watermark, [
			'base_path'   => 'plugin/',
			'customer_id' => 2,
		] );

		$this->assertSame( 'first customer-1', $first_zip->getFromName( 'plugin/file.txt' ) );
		$this->assertSame( 'second customer-2', $second_zip->getFromName( 'plugin/file.txt' ) );
		$this->assertTrue( $first_zip->close() );
		$this->assertTrue( $second_zip->close() );
	}

	/**
	 * Unsafe archive paths and empty searches are non-mutating.
	 *
	 * @return void
	 */
	public function test_unsafe_paths_and_empty_searches_do_not_mutate_archive(): void {
		$archive = $this->create_archive( [ 'plugin/file.txt' => 'original' ] );
		$zip     = $this->open_archive( $archive );
		$count   = $zip->numFiles; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		watermark_zip(
			$zip,
			[
				'type'    => 'add_file',
				'file'    => '../escape.php',
				'content' => 'unsafe',
			],
			[ 'base_path' => 'plugin/' ]
		);
		watermark_zip(
			$zip,
			[
				'type'    => 'string_replacement',
				'file'    => 'file.txt',
				'search'  => '',
				'content' => 'unsafe',
			],
			[ 'base_path' => 'plugin/' ]
		);

		$this->assertSame( $count, $zip->numFiles ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->assertFalse( $zip->locateName( '../escape.php' ) );
		$this->assertSame( 'original', $zip->getFromName( 'plugin/file.txt' ) );
		$this->assertTrue( $zip->close() );
	}

	/**
	 * Create a real ZIP fixture.
	 *
	 * @param array<string,string> $entries Archive entries and contents.
	 * @return string
	 */
	private function create_archive( array $entries ): string {
		$filename = tempnam( sys_get_temp_dir(), 'edd-watermark-' );
		$this->assertIsString( $filename );
		$this->temporary_archives[] = $filename;

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $filename, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		foreach ( $entries as $entry => $contents ) {
			$this->assertTrue( $zip->addFromString( $entry, $contents ) );
		}
		$this->assertTrue( $zip->close() );

		return $filename;
	}

	/**
	 * Open an existing ZIP fixture.
	 *
	 * @param string $filename Archive path.
	 * @return ZipArchive
	 */
	private function open_archive( string $filename ): ZipArchive {
		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $filename ) );

		return $zip;
	}
}
