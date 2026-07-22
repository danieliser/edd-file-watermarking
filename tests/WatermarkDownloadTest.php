<?php
/**
 * Customer-download transaction regression tests.
 *
 * @package EDDFileWatermarking
 */

use PHPUnit\Framework\TestCase;
use function EDDFileWatermarking\edd_watermark_cleanup;
use function EDDFileWatermarking\watermark_edd_download;
use function EDDFileWatermarking\watermark_zip;

/**
 * Exercise the complete EDD download-filter path against real ZIP files.
 */
class WatermarkDownloadTest extends TestCase {

	/**
	 * Isolated runtime directory.
	 *
	 * @var string
	 */
	private $runtime_directory;

	/**
	 * Isolated EDD upload directory.
	 *
	 * @var string
	 */
	private $upload_directory;

	/**
	 * Prepare isolated EDD and WordPress runtime state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->runtime_directory = sprintf(
			'%stests/.watermark-runtime-%s',
			ABSPATH,
			bin2hex( random_bytes( 6 ) )
		);
		$this->upload_directory  = $this->runtime_directory . '/uploads';

		$this->assertTrue( mkdir( $this->runtime_directory . '/source', 0777, true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Isolated test fixture.

		$GLOBALS['edd_file_watermarking_test_filters']     = [];
		$GLOBALS['edd_file_watermarking_test_upload_dir']  = $this->upload_directory;
		$GLOBALS['edd_file_watermarking_test_customer_id'] = 42;
		$GLOBALS['edd_file_watermarking_test_licensing']   = new class() {
			/**
			 * Return a valid license record.
			 *
			 * @param string $license_key License key.
			 * @return object
			 */
			public function get_license( $license_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				return (object) [ 'payment_id' => 501 ];
			}

			/**
			 * Return the purchase's license record.
			 *
			 * @param int $payment_id Payment ID.
			 * @return object
			 */
			public function get_license_by_purchase( $payment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				return (object) [ 'license_key' => 'license-123' ];
			}
		};

		$_GET = [ 'license' => 'license-123' ];
	}

	/**
	 * Remove every isolated fixture.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_GET = [];
		$GLOBALS['edd_file_watermarking_test_filters'] = [];
		$this->remove_directory( $this->runtime_directory );
		parent::tearDown();
	}

	/**
	 * A successful request returns a changed customer copy and never mutates the source.
	 *
	 * @return void
	 */
	public function test_customer_copy_is_watermarked_while_original_stays_untouched(): void {
		$source      = $this->create_plugin_archive( 'example-plugin.zip' );
		$source_hash = hash_file( 'sha256', $source );

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'example-plugin.php',
						'search'  => '/* WATERMARK */',
						'content' => '/* customer={customer_id};license={license_key};download={download_id} */',
					],
					$args
				);
			},
			10,
			2
		);

		$result = watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );

		$this->assertNotSame( $source, $result );
		$this->assert_customer_copy_path( $result, 'example-plugin.zip' );
		$this->assertFileExists( $result );
		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assertSame( "<?php\n/* WATERMARK */\n", $this->read_archive_file( $source, 'example-plugin/example-plugin.php' ) );
		$this->assertSame(
			"<?php\n/* customer=42;license=license-123;download=77 */\n",
			$this->read_archive_file( $result, 'example-plugin/example-plugin.php' )
		);
	}

	/**
	 * Disabling ZipArchive support must not create an unwatermarked customer copy.
	 *
	 * @return void
	 */
	public function test_unavailable_zip_support_fails_closed_without_creating_copy(): void {
		$source      = $this->create_plugin_archive( 'unavailable.zip' );
		$source_hash = hash_file( 'sha256', $source );

		add_filter(
			'edd_file_watermarking_zip_archive_available',
			static function () {
				return false;
			}
		);

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assertDirectoryDoesNotExist( $this->upload_directory );
	}

	/**
	 * Non-ZIP EDD files are outside scope unless explicitly allowlisted.
	 *
	 * @return void
	 */
	public function test_non_zip_download_bypasses_default_watermark_scope(): void {
		$source = $this->runtime_directory . '/source/manual.pdf';
		$this->assertNotFalse( file_put_contents( $source, 'PDF fixture' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated test fixture.

		add_filter(
			'edd_file_watermarking_zip_archive_available',
			static function () {
				return false;
			}
		);

		$this->assertSame( $source, watermark_edd_download( $source, [], '0', [ 'download' => 77 ] ) );
		$this->assertDirectoryDoesNotExist( $this->upload_directory );
	}

	/**
	 * An archive-open failure removes the copied payload and falls back to source.
	 *
	 * @return void
	 */
	public function test_open_failure_removes_customer_copy_and_fails_closed(): void {
		$source = $this->runtime_directory . '/source/broken.zip';
		$this->assertNotFalse( file_put_contents( $source, 'not a zip archive' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated test fixture.

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( 'not a zip archive', file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Isolated test fixture.
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * An unreadable or missing source is rejected before copy emits a warning.
	 *
	 * @return void
	 */
	public function test_missing_source_fails_closed_before_copy(): void {
		$source = $this->runtime_directory . '/source/missing.zip';

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertFileDoesNotExist( $source );
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * A failed ZIP commit removes the modified copy and falls back to source.
	 *
	 * @return void
	 */
	public function test_close_failure_removes_customer_copy_and_fails_closed(): void {
		$source      = $this->create_plugin_archive( 'close-failure.zip' );
		$source_hash = hash_file( 'sha256', $source );

		add_filter(
			'edd_file_watermarking_zip_archive',
			static function ( $zip, $file_path ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				return new CloseFailingZipArchive();
			},
			10,
			2
		);
		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'example-plugin.php',
						'search'  => '/* WATERMARK */',
						'content' => '/* customer={customer_id} */',
					],
					$args
				);
			},
			10,
			2
		);

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * The explicit emergency override restores the historical source response.
	 *
	 * @return void
	 */
	public function test_emergency_fail_open_filter_returns_original_source(): void {
		$source = $this->create_plugin_archive( 'emergency.zip' );

		add_filter(
			'edd_file_watermarking_zip_archive_available',
			static function () {
				return false;
			}
		);
		add_filter(
			'edd_file_watermarking_fail_open',
			static function () {
				return true;
			}
		);

		$this->assertSame( $source, watermark_edd_download( $source, [], '0', [ 'download' => 77 ] ) );
	}

	/**
	 * An unchanged archive indicates absent or ineffective watermark rules.
	 *
	 * @return void
	 */
	public function test_unchanged_archive_is_removed_and_fails_closed(): void {
		$source      = $this->create_plugin_archive( 'unchanged.zip' );
		$source_hash = hash_file( 'sha256', $source );

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * One successful rule cannot mask a later configured-rule failure.
	 *
	 * @return void
	 */
	public function test_partial_watermark_operation_failure_rolls_back_changed_copy(): void {
		$source      = $this->create_plugin_archive( 'partial-failure.zip' );
		$source_hash = hash_file( 'sha256', $source );

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'example-plugin.php',
						'search'  => '/* WATERMARK */',
						'content' => '/* customer={customer_id} */',
					],
					$args
				);
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'missing.php',
						'search'  => 'marker',
						'content' => 'required identifier',
					],
					$args
				);
			},
			10,
			2
		);

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * A throwing watermark callback cannot leave or serve a partial copy.
	 *
	 * @return void
	 */
	public function test_callback_exception_rolls_back_copy_and_fails_closed(): void {
		$source      = $this->create_plugin_archive( 'callback-failure.zip' );
		$source_hash = hash_file( 'sha256', $source );

		add_action(
			'watermark_edd_download',
			static function () {
				throw new RuntimeException( 'Synthetic callback failure.' );
			}
		);

		$this->assert_download_fails_closed(
			static function () use ( $source ) {
				watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );
			}
		);

		$this->assertSame( $source_hash, hash_file( 'sha256', $source ) );
		$this->assert_customer_root_has_no_payloads();
	}

	/**
	 * Two same-customer requests must never overwrite or delete one another.
	 *
	 * @return void
	 */
	public function test_same_customer_downloads_receive_independent_request_directories(): void {
		$source = $this->create_plugin_archive( 'concurrent.zip' );

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'example-plugin.php',
						'search'  => '/* WATERMARK */',
						'content' => '/* request={download_id} */',
					],
					$args
				);
			},
			10,
			2
		);

		$first  = watermark_edd_download( $source, [], '0', [ 'download' => 101 ] );
		$second = watermark_edd_download( $source, [], '0', [ 'download' => 202 ] );

		$this->assertNotSame( $first, $second );
		$this->assert_customer_copy_path( $first, 'concurrent.zip' );
		$this->assert_customer_copy_path( $second, 'concurrent.zip' );
		$this->assertFileExists( $first );
		$this->assertFileExists( $second );
		$this->assertStringContainsString( 'request=101', $this->read_archive_file( $first, 'example-plugin/example-plugin.php' ) );
		$this->assertStringContainsString( 'request=202', $this->read_archive_file( $second, 'example-plugin/example-plugin.php' ) );
	}

	/**
	 * Flat archives keep an empty base path and remain watermarkable.
	 *
	 * @return void
	 */
	public function test_flat_archive_uses_empty_base_path(): void {
		$source    = $this->create_flat_archive( 'flat.zip' );
		$base_path = null;

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) use ( &$base_path ) {
				$base_path = $args['base_path'];
				watermark_zip(
					$zip,
					[
						'type'    => 'string_replacement',
						'file'    => 'example-plugin.php',
						'search'  => '/* WATERMARK */',
						'content' => '/* customer={customer_id} */',
					],
					$args
				);
			},
			10,
			2
		);

		$result = watermark_edd_download( $source, [], '0', [ 'download' => 77 ] );

		$this->assertSame( '', $base_path );
		$this->assertStringContainsString( 'customer=42', $this->read_archive_file( $result, 'example-plugin.php' ) );
	}

	/**
	 * Daily cleanup removes nested request directories and keeps sources intact.
	 *
	 * @return void
	 */
	public function test_scheduled_cleanup_removes_nested_customer_copies(): void {
		$source = $this->create_plugin_archive( 'cleanup.zip' );
		$shared = $this->upload_directory . '/temp/42/other-extension.tmp';
		$this->assertTrue( mkdir( dirname( $shared ), 0777, true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Isolated test fixture.
		$this->assertNotFalse( file_put_contents( $shared, 'not ours' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated test fixture.

		add_filter(
			'edd_file_watermarking_cleanup_minimum_age',
			static function () {
				return 0;
			}
		);

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'append_to_file',
						'file'    => 'example-plugin.php',
						'content' => 'customer={customer_id}',
					],
					$args
				);
			},
			10,
			2
		);

		$first  = watermark_edd_download( $source, [], '0', [ 'download' => 1 ] );
		$second = watermark_edd_download( $source, [], '0', [ 'download' => 2 ] );
		$this->assertFileExists( $first );
		$this->assertFileExists( $second );

		edd_watermark_cleanup();

		$this->assertFileExists( $source );
		$this->assertFileExists( $shared );
		$this->assertDirectoryDoesNotExist( $this->upload_directory . '/temp/edd-file-watermarking/42' );
	}

	/**
	 * Scheduled cleanup leaves fresh in-flight copies inside the grace period.
	 *
	 * @return void
	 */
	public function test_scheduled_cleanup_preserves_fresh_customer_copy(): void {
		$source = $this->create_plugin_archive( 'fresh.zip' );

		add_action(
			'watermark_edd_download',
			static function ( $zip, $args ) {
				watermark_zip(
					$zip,
					[
						'type'    => 'append_to_file',
						'file'    => 'example-plugin.php',
						'content' => 'fresh={customer_id}',
					],
					$args
				);
			},
			10,
			2
		);

		$copy = watermark_edd_download( $source, [], '0', [ 'download' => 1 ] );
		edd_watermark_cleanup();

		$this->assertFileExists( $copy );
	}

	/**
	 * Create one installable, single-root plugin ZIP.
	 *
	 * @param string $filename Archive filename.
	 * @return string
	 */
	private function create_plugin_archive( $filename ) {
		$path = $this->runtime_directory . '/source/' . $filename;
		$zip  = new ZipArchive();

		$this->assertTrue( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		$this->assertTrue( $zip->addFromString( 'example-plugin/example-plugin.php', "<?php\n/* WATERMARK */\n" ) );
		$this->assertTrue( $zip->close() );

		return $path;
	}

	/**
	 * Create one flat archive without a top-level plugin directory.
	 *
	 * @param string $filename Archive filename.
	 * @return string
	 */
	private function create_flat_archive( $filename ) {
		$path = $this->runtime_directory . '/source/' . $filename;
		$zip  = new ZipArchive();

		$this->assertTrue( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		$this->assertTrue( $zip->addFromString( 'example-plugin.php', "<?php\n/* WATERMARK */\n" ) );
		$this->assertTrue( $zip->addFromString( 'readme.txt', "Example\n" ) );
		$this->assertTrue( $zip->close() );

		return $path;
	}

	/**
	 * Read a file from a real archive.
	 *
	 * @param string $archive Archive path.
	 * @param string $file    Internal file path.
	 * @return string
	 */
	private function read_archive_file( $archive, $file ) {
		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $archive ) );
		$content = $zip->getFromName( $file );
		$this->assertIsString( $content );
		$this->assertTrue( $zip->close() );

		return $content;
	}

	/**
	 * Assert a copy uses a random request directory and preserves its basename.
	 *
	 * @param string $path     Customer-copy path.
	 * @param string $filename Expected archive filename.
	 * @return void
	 */
	private function assert_customer_copy_path( $path, $filename ): void {
		$this->assertSame( $filename, basename( $path ) );
		$this->assertSame( $this->upload_directory . '/temp/edd-file-watermarking/42', dirname( dirname( $path ) ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', basename( dirname( $path ) ) );
	}

	/**
	 * Assert the eligible request terminates with a retryable generic error.
	 *
	 * @param callable $callback Download request.
	 * @return void
	 */
	private function assert_download_fails_closed( $callback ): void {
		try {
			$callback();
			$this->fail( 'Expected the personalized download to fail closed.' );
		} catch ( WatermarkWpDieException $exception ) {
			$this->assertSame( 503, $exception->response );
			$this->assertStringContainsString( 'could not be prepared', $exception->getMessage() );
		}
	}

	/**
	 * Assert rollback left no per-request directory or payload behind.
	 *
	 * @return void
	 */
	private function assert_customer_root_has_no_payloads(): void {
		$customer_root = $this->upload_directory . '/temp/edd-file-watermarking/42';
		$payloads      = is_dir( $customer_root ) ? glob( $customer_root . '/*' ) : [];

		$this->assertSame( [], is_array( $payloads ) ? $payloads : [] );
	}

	/**
	 * Recursively remove an isolated fixture directory.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function remove_directory( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );
		if ( false === $items ) {
			return;
		}

		foreach ( array_diff( $items, [ '.', '..' ] ) as $item ) {
			$path = $directory . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Isolated test fixture cleanup.
			}
		}

		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Isolated test fixture cleanup.
	}
}
