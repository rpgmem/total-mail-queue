<?php
/**
 * The admin button family.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Markup of a `.tmq-btn` button: a slim copy of Free Form Certificate's
 * family (its `Core\Button`), so the two plugins' buttons read alike without
 * either depending on the other.
 *
 * The category names what the action does, never how loud it should look:
 * `primary` completes the screen's work (one per form), `neutral` opens,
 * picks, tests or navigates, `create` adds something, `caution` recomputes or
 * switches something off without losing data, `danger` deletes, empties or
 * replaces. The stylesheet draws each one from `--tmq-*` tokens, so it
 * follows the dark theme and `ColorTokensTest` measures it in both.
 *
 * Every button carries an icon from {@see Icons::PATHS}, as the
 * `tmq-icon-<name>` class on the button itself; the stylesheet paints it in
 * `::before`, so a script that rewrites the label keeps it.
 */
final class Button {

	/**
	 * The categories, in the order a screen reads them.
	 *
	 * @var array<int,string>
	 */
	public const CATEGORIES = array( 'primary', 'neutral', 'create', 'caution', 'danger' );

	/**
	 * What an unknown category falls back to: it says nothing about the action.
	 */
	public const FALLBACK = 'neutral';

	/**
	 * The `type` values a `<button>` accepts.
	 *
	 * @var array<int,string>
	 */
	private const TYPES = array( 'button', 'submit', 'reset' );

	/**
	 * The markup.
	 *
	 * Arguments: `label` (visible text, required); `category` (one of
	 * {@see Button::CATEGORIES}, default `neutral`); `icon` (a key of
	 * {@see Icons::PATHS}); `href` (makes it a link); `type` (`button`,
	 * `submit` or `reset`, ignored with `href`); `id`; `name` and `value`
	 * (ignored with `href`); `class` (extra classes, the hooks scripts read);
	 * `confirm` (a question asked before the action goes ahead, printed as
	 * `data-tmq-confirm` and asked by `tmq-admin.js`); `attrs` (further
	 * attributes: a string is escaped, `true` prints the bare attribute, an
	 * `on*` handler is refused).
	 *
	 * @param array<string,mixed> $args See above.
	 */
	public static function html( array $args ): string {
		$label = (string) ( $args['label'] ?? '' );
		if ( '' === $label ) {
			return '';
		}

		$category = (string) ( $args['category'] ?? self::FALLBACK );
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			$category = self::FALLBACK;
		}
		$classes = array( 'tmq-btn', 'tmq-btn-' . $category );

		$icon = (string) ( $args['icon'] ?? '' );
		if ( isset( Icons::PATHS[ $icon ] ) ) {
			$classes[] = 'tmq-icon-' . $icon;
		}

		$extras = preg_split( '/\s+/', trim( (string) ( $args['class'] ?? '' ) ) );
		foreach ( is_array( $extras ) ? $extras : array() as $extra ) {
			if ( '' !== $extra ) {
				$classes[] = $extra;
			}
		}

		$href  = isset( $args['href'] ) ? (string) $args['href'] : null;
		$attrs = array();
		if ( null === $href ) {
			$type          = (string) ( $args['type'] ?? 'button' );
			$attrs['type'] = in_array( $type, self::TYPES, true ) ? $type : 'button';
		} else {
			$attrs['href'] = $href;
		}
		$attrs['class'] = implode( ' ', array_unique( $classes ) );

		foreach ( array( 'id', 'name', 'value' ) as $key ) {
			if ( null !== $href && 'id' !== $key ) {
				continue;
			}
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$attrs[ $key ] = (string) $args[ $key ];
			}
		}

		if ( isset( $args['confirm'] ) && '' !== (string) $args['confirm'] ) {
			$attrs['data-tmq-confirm'] = (string) $args['confirm'];
		}

		$more = is_array( $args['attrs'] ?? null ) ? $args['attrs'] : array();
		foreach ( $more as $key => $value ) {
			$key = strtolower( (string) $key );
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_:.-]*$/', $key ) || str_starts_with( $key, 'on' ) || isset( $attrs[ $key ] ) ) {
				continue;
			}
			if ( true === $value ) {
				$attrs[ $key ] = true;
			} elseif ( is_scalar( $value ) && false !== $value ) {
				$attrs[ $key ] = (string) $value;
			}
		}

		$printed = '';
		foreach ( $attrs as $key => $value ) {
			if ( true === $value ) {
				$printed .= ' ' . $key;
			} elseif ( 'href' === $key ) {
				$printed .= ' href="' . esc_url( $value ) . '"';
			} else {
				$printed .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
		}

		$tag = null === $href ? 'button' : 'a';
		return '<' . $tag . $printed . '>' . esc_html( $label ) . '</' . $tag . '>';
	}

	/**
	 * Print the markup.
	 *
	 * @param array<string,mixed> $args See {@see Button::html()}.
	 */
	public static function render( array $args ): void {
		echo self::html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes every value it prints.
	}
}
