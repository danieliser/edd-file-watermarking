<?php
/**
 * EDD File Watermarking Settings
 *
 * @package EDDFileWatermarking
 * @subpackage Settings
 * @since 1.0
 */

namespace EDDFileWatermarking;

/**
 * Register the watermark settings section.
 *
 * @param array<string,string> $sections The sections.
 *
 * @return array<string,string> The sections.
 */
function edd_watermark_register_settings_section( $sections ) {
	$sections['watermarking'] = __( 'Watermarking', 'edd-file-watermarking' );
	return $sections;
}

/**
 * Add the watermark settings.
 *
 * @param array<string,mixed> $settings The settings.
 *
 * @return array<string,mixed> The settings.
 */
function edd_watermark_add_settings( $settings ) {
	$watermark_settings = [
		'watermarking' => [
			[
				'id'   => 'edd_watermark_settings',
				'name' => '<strong>' . __( 'Watermark Settings', 'edd-file-watermarking' ) . '</strong>',
				'type' => 'header',
			],
			[
				'id'   => 'edd_watermarks',
				'name' => __( 'Watermarks', 'edd-file-watermarking' ),
				'type' => 'watermark_repeater',
			],
		],
	];

	return array_merge( $settings, $watermark_settings );
}

/**
 * Render the watermark repeater.
 *
 * @param mixed  $watermarks The watermarks.
 * @param string $name The name.
 *
 * @return void
 */
function render_watermark_table( $watermarks, $name = 'watermark_repeater' ) {
	$watermarks = is_array( $watermarks ) ? $watermarks : [];
	?>
	<div id="edd-watermark-fields">
		<p>Watermarking allows you to add a unique identifier to the files that are downloaded by your customers. This can be useful for tracking down the source of a leak if your files are shared publicly.</p>
		<br />
		<p><strong>Note for content</strong> <?php echo esc_html( __( 'Use `\r\n` for line breaks as well as shortcodes like the following for dynamic data.', 'edd-file-watermarking' ) ); ?></p>
						<ul>
							<li>{customer_id}</li>
							<li>{customer_id times=2}</li>
							<li>{license_key}</li>
							<li>{license_key encoded="base64"}</li>
							<li>{download_id}</li>
							<li>{payment_id}</li>
						</ul>
		<table id="watermark-repeater-table">
		<thead>
				<tr>
					<th>Watermark Type</th>
					<th>File to Modify</th>
					<th>Search String</th>
					<th>Watermark Content</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="5">

					</td>
				</tr>
			</tfoot>
			<tbody>
				
				<?php if ( ! empty( $watermarks ) ) : ?>
					<?php foreach ( $watermarks as $watermark ) : ?>
						<?php
						$watermark = wp_parse_args( $watermark, [
							'type'    => '',
							'file'    => '',
							'search'  => '',
							'content' => '',
						] );
						?>
						<tr class="watermark-repeater-row">
							<td>
								<select name="<?php echo esc_attr( $name ); ?>[type][]">
									<option value="add_file" <?php selected( $watermark['type'], 'add_file' ); ?>>Add File</option>
									<option value="string_replacement" <?php selected( $watermark['type'], 'string_replacement' ); ?>>String Replacement</option>
									<option value="append_to_file" <?php selected( $watermark['type'], 'append_to_file' ); ?>>Append to File</option>
								</select>
							</td>
							<td><input type="text" name="<?php echo esc_attr( $name ); ?>[file][]" value="<?php echo esc_attr( $watermark['file'] ); ?>" /></td>
							<td><input type="text" name="<?php echo esc_attr( $name ); ?>[search][]" value="<?php echo esc_attr( $watermark['search'] ); ?>" /></td>
							<td><textarea name="<?php echo esc_attr( $name ); ?>[content][]"><?php echo esc_textarea( $watermark['content'] ); ?></textarea></td>
							<td><button type="button" class="remove-row">Remove</button></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>


			</tbody>
		</table>

		<button type="button" class="add-watermark-row">Add Watermark</button>
	</div>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Functionality to add a new row to the watermark table
			$('.add-watermark-row').click(function() {
				var rowHTML = '<tr class="watermark-repeater-row">' +
								'<td><select name="<?php echo esc_attr( $name ); ?>[type][]">' +
								'   <option value="add_file">Add File</option>' +
								'   <option value="string_replacement">String Replacement</option>' +
								'   <option value="append_to_file">Append to File</option>' +
								'</select></td>' +
								'<td><input type="text" name="<?php echo esc_attr( $name ); ?>[file][]" /></td>' +
								'<td><input type="text" name="<?php echo esc_attr( $name ); ?>[search][]" /></td>' +
								'<td><textarea name="<?php echo esc_attr( $name ); ?>[content][]"></textarea></td>' +
								'<td><button type="button" class="remove-row">Remove</button></td>' +
								'</tr>';

				$('#watermark-repeater-table tbody').append(rowHTML);
			});

			// Functionality to remove a row from the watermark table
			$('body').on('click', '.remove-row', function() {
				$(this).closest('tr.watermark-repeater-row').remove();
			});
		});
	</script>
	<?php
}

/**
 * Sanitize the watermark repeater.
 *
 * @param array<int,mixed> $value The value.
 *
 * @return array<int,array{type:string,file:string,search:string,content:string}> The sanitized value.
 */
function sanitize_watermark_repeater( $value ) {
	// Create a new empty array to hold our sanitized settings.
	$new_input = [];

	// Loop through each setting being saved and sanitize it.
	foreach ( $value as $watermark ) {
		if ( ! is_array( $watermark ) ) {
			continue;
		}

		// Sanitize each field within each repeater row.
		$type          = isset( $watermark['type'] ) ? sanitize_key( $watermark['type'] ) : '';
		$allowed_types = [ 'add_file', 'string_replacement', 'append_to_file' ];
		$new_watermark = [
			'type'    => in_array( $type, $allowed_types, true ) ? $type : '',
			'file'    => isset( $watermark['file'] ) ? sanitize_text_field( $watermark['file'] ) : '',
			'search'  => isset( $watermark['search'] ) ? sanitize_text_field( $watermark['search'] ) : '',
			'content' => isset( $watermark['content'] ) ? sanitize_textarea_field( $watermark['content'] ) : '',
		];

		// Add the sanitized watermark to our new input array.
		$new_input[] = $new_watermark;
	}

	// Return the sanitized array.
	return $new_input;
}

/**
 * Sanitize the watermark repeater.
 *
 * @param mixed  $value The value.
 * @param string $key The key.
 *
 * @return array<int,array{type:string,file:string,search:string,content:string}> The sanitized value.
 */
function sanitize_watermark_repeater_settings( $value, $key = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! is_array( $value ) || empty( $value ) ) {
		return [];
	}

	if ( ! array_key_exists( 'type', $value ) ) {
		// EDD may pass an already-remapped list; it still needs sanitization.
		return sanitize_watermark_repeater( $value );
	}

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
	// $value = apply_filters( 'edd_settings_sanitize_watermark_repeater', $value, $key );

	$value = remap_watermark_repeater_values( $value );

	return sanitize_watermark_repeater( $value );
}

/**
 * Remap the watermark repeater values.
 *
 * @param array<string,mixed> $values The values.
 *
 * @return array<int,array{type:mixed,file:mixed,search:mixed,content:mixed}> The remapped values.
 */
function remap_watermark_repeater_values( $values ) {
	$new_values = [];

	// Remap from separate post fields into a single  array of values.
	$types    = isset( $values['type'] ) && is_array( $values['type'] ) ? $values['type'] : [];
	$files    = isset( $values['file'] ) && is_array( $values['file'] ) ? $values['file'] : [];
	$searches = isset( $values['search'] ) && is_array( $values['search'] ) ? $values['search'] : [];
	$contents = isset( $values['content'] ) && is_array( $values['content'] ) ? $values['content'] : [];

	foreach ( $types as $index => $type ) {
		$new_values[] = [
			'type'    => $type,
			'file'    => isset( $files[ $index ] ) ? $files[ $index ] : '',
			'search'  => isset( $searches[ $index ] ) ? $searches[ $index ] : '',
			'content' => isset( $contents[ $index ] ) ? $contents[ $index ] : '',
		];
	}

	return $new_values;
}
