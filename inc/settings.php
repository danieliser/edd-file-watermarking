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
 * @param array $sections The sections.
 *
 * @return array The sections.
 */
function edd_watermark_register_settings_section( $sections ) {
	$sections['watermarking'] = __( 'Watermarking', 'edd-file-watermarking' );
	return $sections;
}

/**
 * Add the watermark settings.
 *
 * @param array $settings The settings.
 *
 * @return array The settings.
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
 * @param array  $watermarks The watermarks.
 * @param string $name The name.
 *
 * @return void
 */
function render_watermark_table( $watermarks, $name = 'watermark_repeater' ) {
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
 * @param array $value The value.
 *
 * @return array The sanitized value.
 */
function sanitize_watermark_repeater( $value ) {
	// Create a new empty array to hold our sanitized settings.
	$new_input = [];

	// Loop through each setting being saved and sanitize it.
	foreach ( $value as $watermark ) {
		// Sanitize each field within each repeater row.
		$new_watermark = [
			'type'    => isset( $watermark['type'] ) ? sanitize_text_field( $watermark['type'] ) : '',
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
 * @param array  $value The value.
 * @param string $key The key.
 *
 * @return array The sanitized value.
 */
function sanitize_watermark_repeater_settings( $value, $key = null ) {
	if ( ! isset( $value ) ) {
		return $value;
	}

	if ( is_array( $value ) && ! empty( $value ) && isset( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['type'] ) ) {
		// This is already processed value, must be sanitizing before rendering ?
		return $value;
	}

	// $value = apply_filters( 'edd_settings_sanitize_watermark_repeater', $value, $key );

	$value = remap_watermark_repeater_values( $value );

	return sanitize_watermark_repeater( $value );
}

/**
 * Remap the watermark repeater values.
 *
 * @param array $values The values.
 *
 * @return array The remapped values.
 */
function remap_watermark_repeater_values( $values ) {
	$new_values = [];

	// Remap from separate post fields into a single  array of values.
	foreach ( $values['type'] as $index => $type ) {
		$new_values[] = [
			'type'    => $type,
			'file'    => $values['file'][ $index ],
			'search'  => $values['search'][ $index ],
			'content' => $values['content'][ $index ],
		];
	}

	return $new_values;
}
