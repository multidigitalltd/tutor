<?php
/**
 * Reusable, escaped admin field renderers.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders accessible, escaped form controls for metaboxes.
 *
 * Every control outputs a real <label> tied to its field (WCAG / Multi Digital
 * accessibility standard) and escapes all output.
 */
final class Field_Renderer {

	/**
	 * Text input.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param string $type  Input type.
	 */
	public static function text( string $name, string $label, string $value, string $type = 'text' ): void {
		$id = self::id( $name );
		?>
		<p class="mdds-field">
			<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<input type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="widefat" />
		</p>
		<?php
	}

	/**
	 * Textarea.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param int    $rows  Rows.
	 */
	public static function textarea( string $name, string $label, string $value, int $rows = 4 ): void {
		$id = self::id( $name );
		?>
		<p class="mdds-field">
			<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<textarea id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				rows="<?php echo esc_attr( (string) $rows ); ?>"
				class="widefat"><?php echo esc_textarea( $value ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Checkbox.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Field label.
	 * @param bool   $checked Whether checked.
	 */
	public static function checkbox( string $name, string $label, bool $checked ): void {
		$id = self::id( $name );
		?>
		<p class="mdds-field">
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="1" <?php checked( $checked ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Media picker (stores attachment ID).
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param int    $attachment  Current attachment ID.
	 * @param string $button_text Button text.
	 */
	public static function media( string $name, string $label, int $attachment, string $button_text ): void {
		$id  = self::id( $name );
		$url = $attachment > 0 ? wp_get_attachment_url( $attachment ) : '';
		?>
		<div class="mdds-field mdds-media" data-mdds-media>
			<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment ); ?>" data-mdds-media-id />
			<span class="mdds-media-name" data-mdds-media-name><?php echo esc_html( $url ? wp_basename( $url ) : __( 'לא נבחר קובץ', 'md-deschool' ) ); ?></span>
			<button type="button" class="button" data-mdds-media-select><?php echo esc_html( $button_text ); ?></button>
			<button type="button" class="button-link mdds-media-remove" data-mdds-media-remove<?php echo $attachment > 0 ? '' : ' hidden'; ?>><?php esc_html_e( 'הסרה', 'md-deschool' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Build a sanitised DOM id from a field name.
	 *
	 * @param string $name Field name.
	 */
	private static function id( string $name ): string {
		return 'mdds-' . sanitize_key( str_replace( array( '[', ']' ), array( '-', '' ), $name ) );
	}
}
