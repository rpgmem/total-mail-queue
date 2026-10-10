<?php
/**
 * On/off switch for boolean fields.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Renders a checkbox as a toggle switch, the same markup and contract as Free
 * Form Certificate's `AdminUI::render_toggle()`.
 *
 * The real `<input type="checkbox">` stays in the markup, visually hidden, so
 * keyboard, screen readers and form posting behave exactly as a checkbox. The
 * track is a sibling the stylesheet paints from `:checked`.
 *
 * Markup:
 *
 *     <input type="hidden" name="…" value="0">        (when `off_value` is set)
 *     <label class="tmq-toggle" for="…">
 *         <input type="checkbox" id="…" name="…" value="1" checked>
 *         <span class="tmq-toggle-track" aria-hidden="true"></span>
 *         <span class="tmq-toggle-label">Label</span>
 *     </label>
 */
final class Toggle {

	/**
	 * Print a toggle.
	 *
	 * Keys of `$args`:
	 * - `name` (required) field name; `id` element id, the name made id-safe by default;
	 * - `value` posted when on, '1' by default; `checked`, `disabled`;
	 * - `label`, the text beside the switch;
	 * - `off_value`: when set, a hidden input posts it while the switch is
	 *   off, so a form save that replaces the whole option records "off";
	 * - `autosave`: a `target:key` of the {@see SettingsAutosave} allowlist,
	 *   saved the moment the switch flips;
	 * - `input_class`, an extra class on the checkbox.
	 *
	 * @param array<string,mixed> $args Toggle options, listed above.
	 */
	public static function render( array $args ): void {
		echo self::html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped in html().
	}

	/**
	 * The toggle markup as a string.
	 *
	 * @param array<string,mixed> $args See {@see self::render()}.
	 */
	public static function html( array $args ): string {
		$name = (string) ( $args['name'] ?? '' );
		if ( '' === $name ) {
			return '';
		}
		$id    = (string) ( $args['id'] ?? (string) preg_replace( '/[^A-Za-z0-9_-]+/', '_', trim( $name, ']' ) ) );
		$attrs = '';
		if ( ! empty( $args['checked'] ) ) {
			$attrs .= ' checked';
		}
		if ( ! empty( $args['disabled'] ) ) {
			$attrs .= ' disabled';
		}
		if ( ! empty( $args['input_class'] ) ) {
			$attrs .= ' class="' . esc_attr( (string) $args['input_class'] ) . '"';
		}
		if ( ! empty( $args['autosave'] ) ) {
			$attrs .= ' data-tmq-autosave="' . esc_attr( (string) $args['autosave'] ) . '"';
		}

		$html = '';
		if ( isset( $args['off_value'] ) ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $args['off_value'] ) . '" />';
		}
		$html .= '<label class="tmq-toggle" for="' . esc_attr( $id ) . '">';
		$html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) ( $args['value'] ?? '1' ) ) . '"' . $attrs . ' />';
		$html .= '<span class="tmq-toggle-track" aria-hidden="true"></span>';
		$label = (string) ( $args['label'] ?? '' );
		if ( '' !== $label ) {
			$html .= '<span class="tmq-toggle-label">' . esc_html( $label ) . '</span>';
		}
		$html .= '</label>';
		return $html;
	}
}
