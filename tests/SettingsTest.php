<?php
/**
 * Watermark settings sanitization regression tests.
 *
 * @package EDDFileWatermarking
 */

use PHPUnit\Framework\TestCase;
use function EDDFileWatermarking\remap_watermark_repeater_values;
use function EDDFileWatermarking\sanitize_watermark_repeater_settings;

/**
 * Exercise both EDD repeater payload shapes at the sanitization boundary.
 */
class SettingsTest extends TestCase {

	/**
	 * A pre-remapped list must not bypass type and field sanitization.
	 *
	 * @return void
	 */
	public function test_pre_remapped_rows_are_still_sanitized(): void {
		$result = sanitize_watermark_repeater_settings(
			[
				[
					'type'    => 'PHP_CALLBACK<script>',
					'file'    => ' plugin.php ',
					'search'  => ' token ',
					'content' => ' <b>customer</b> ',
				],
			]
		);

		$this->assertSame(
			[
				[
					'type'    => '',
					'file'    => 'plugin.php',
					'search'  => 'token',
					'content' => 'customer',
				],
			],
			$result
		);
	}

	/**
	 * Missing or scalar parallel fields must become empty strings, not offsets.
	 *
	 * @return void
	 */
	public function test_misaligned_parallel_fields_are_safely_remapped(): void {
		$result = remap_watermark_repeater_values(
			[
				'type'    => [ 'add_file', 'append_to_file' ],
				'file'    => 'not-an-array',
				'search'  => [ 'unused' ],
				'content' => [ 'first' ],
			]
		);

		$this->assertSame(
			[
				[
					'type'    => 'add_file',
					'file'    => '',
					'search'  => 'unused',
					'content' => 'first',
				],
				[
					'type'    => 'append_to_file',
					'file'    => '',
					'search'  => '',
					'content' => '',
				],
			],
			$result
		);
	}

	/**
	 * Non-row values are ignored rather than generating warnings.
	 *
	 * @return void
	 */
	public function test_non_array_rows_are_ignored(): void {
		$result = sanitize_watermark_repeater_settings(
			[
				'not-a-row',
				[
					'type'    => 'add_file',
					'file'    => 'id.txt',
					'content' => '{customer_id}',
				],
			]
		);

		$this->assertSame(
			[
				[
					'type'    => 'add_file',
					'file'    => 'id.txt',
					'search'  => '',
					'content' => '{customer_id}',
				],
			],
			$result
		);
	}
}
