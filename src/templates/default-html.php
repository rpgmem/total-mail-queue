<?php
/**
 * Default HTML wrapper rendered by {@see \TotalMailQueue\Templates\Engine}.
 *
 * Table-based layout — email clients (Outlook, Gmail's web view) are
 * unreliable with flex/grid. Styling is inline so message bodies survive
 * `<style>`-stripping clients.
 *
 * Available locals (the file is `include`d from inside
 * Engine::renderWrapper(), so all variables are method-scoped, not global):
 *   - string $body     Pre-processed message body (HTML).
 *   - array  $args     Original wp_mail() arguments.
 *   - array  $options  Resolved engine options.
 *   - string $subject  Email subject (from $args, may be empty).
 *
 * @package TotalMailQueue
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pre-processed body (HTML).
 *
 * @var string $body
 */

/**
 * Original wp_mail() arguments.
 *
 * @var array<string,mixed> $args
 */

/**
 * Resolved engine options.
 *
 * @var array<string,mixed> $options
 */

/**
 * Email subject.
 *
 * @var string $subject
 */

$wp_tmq_font_stacks = array(
	'system'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
	'serif'   => 'Georgia, "Times New Roman", Times, serif',
	'mono'    => '"SFMono-Regular", Menlo, Monaco, Consolas, "Courier New", monospace',
	'arial'   => 'Arial, Helvetica, sans-serif',
	'georgia' => 'Georgia, "Times New Roman", Times, serif',
);

$wp_tmq_font_family_key = isset( $options['body_font_family'] ) ? (string) $options['body_font_family'] : 'system';
$wp_tmq_font_family     = $wp_tmq_font_stacks[ $wp_tmq_font_family_key ] ?? $wp_tmq_font_stacks['system'];

$wp_tmq_header_bg         = (string) ( sanitize_hex_color( (string) ( $options['header_bg'] ?? '' ) ) ?? '#2c3e50' );
$wp_tmq_header_text_color = (string) ( sanitize_hex_color( (string) ( $options['header_text_color'] ?? '' ) ) ?? '#ffffff' );
$wp_tmq_header_alignment  = in_array( $options['header_alignment'] ?? 'center', array( 'left', 'center', 'right' ), true )
	? (string) $options['header_alignment']
	: 'center';
$wp_tmq_header_padding    = absint( $options['header_padding'] ?? 24 );
$wp_tmq_header_logo_url   = esc_url_raw( (string) ( $options['header_logo_url'] ?? '' ) );
$wp_tmq_logo_max_width    = absint( $options['header_logo_max_width'] ?? 180 );

$wp_tmq_body_bg         = (string) ( sanitize_hex_color( (string) ( $options['body_bg'] ?? '' ) ) ?? '#ffffff' );
$wp_tmq_body_text_color = (string) ( sanitize_hex_color( (string) ( $options['body_text_color'] ?? '' ) ) ?? '#333333' );
$wp_tmq_body_link_color = (string) ( sanitize_hex_color( (string) ( $options['body_link_color'] ?? '' ) ) ?? '#2271b1' );
$wp_tmq_body_font_size  = absint( $options['body_font_size'] ?? 14 );
$wp_tmq_body_padding    = absint( $options['body_padding'] ?? 24 );
$wp_tmq_body_max_width  = absint( $options['body_max_width'] ?? 600 );

$wp_tmq_footer_bg         = (string) ( sanitize_hex_color( (string) ( $options['footer_bg'] ?? '' ) ) ?? '#f5f5f5' );
$wp_tmq_footer_text_color = (string) ( sanitize_hex_color( (string) ( $options['footer_text_color'] ?? '' ) ) ?? '#666666' );
$wp_tmq_footer_text       = (string) ( $options['footer_text'] ?? '' );
$wp_tmq_footer_powered_by = ! empty( $options['footer_powered_by'] );

$wp_tmq_wrapper_bg            = (string) ( sanitize_hex_color( (string) ( $options['wrapper_bg'] ?? '' ) ) ?? '#f0f0f1' );
$wp_tmq_wrapper_border_radius = absint( $options['wrapper_border_radius'] ?? 6 );
$wp_tmq_wrapper_padding       = absint( $options['wrapper_padding'] ?? 32 );

$wp_tmq_show_header_image = '' !== $wp_tmq_header_logo_url;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo '' !== $subject ? esc_html( $subject ) : esc_html( (string) get_bloginfo( 'name', 'display' ) ); ?></title>
	<style type="text/css">
		body { margin: 0; padding: 0; }
		table { border-collapse: collapse; }
		img { border: 0; outline: none; text-decoration: none; max-width: 100%; height: auto; }
		a { color: <?php echo esc_attr( $wp_tmq_body_link_color ); ?>; text-decoration: underline; }
		.tmq-content a { color: <?php echo esc_attr( $wp_tmq_body_link_color ); ?> !important; }
		@media only screen and (max-width: <?php echo esc_attr( (string) ( $wp_tmq_body_max_width + 40 ) ); ?>px) {
			.tmq-card { width: 100% !important; }
			.tmq-card-cell { padding: <?php echo esc_attr( (string) max( 12, $wp_tmq_body_padding - 8 ) ); ?>px !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: <?php echo esc_attr( $wp_tmq_wrapper_bg ); ?>; font-family: <?php echo esc_attr( $wp_tmq_font_family ); ?>;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: <?php echo esc_attr( $wp_tmq_wrapper_bg ); ?>; padding: <?php echo esc_attr( (string) $wp_tmq_wrapper_padding ); ?>px 0;">
	<tr>
		<td align="center" valign="top">
			<table role="presentation" class="tmq-card" cellpadding="0" cellspacing="0" border="0" width="<?php echo esc_attr( (string) $wp_tmq_body_max_width ); ?>" style="width: <?php echo esc_attr( (string) $wp_tmq_body_max_width ); ?>px; max-width: 100%; background-color: <?php echo esc_attr( $wp_tmq_body_bg ); ?>; border-radius: <?php echo esc_attr( (string) $wp_tmq_wrapper_border_radius ); ?>px; overflow: hidden;">
				<tr>
					<td align="<?php echo esc_attr( $wp_tmq_header_alignment ); ?>" valign="middle" style="background-color: <?php echo esc_attr( $wp_tmq_header_bg ); ?>; color: <?php echo esc_attr( $wp_tmq_header_text_color ); ?>; padding: <?php echo esc_attr( (string) $wp_tmq_header_padding ); ?>px; font-family: <?php echo esc_attr( $wp_tmq_font_family ); ?>;">
						<?php if ( $wp_tmq_show_header_image ) : ?>
							<img src="<?php echo esc_url( $wp_tmq_header_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( (string) $wp_tmq_logo_max_width ); ?>" style="display: inline-block; max-width: <?php echo esc_attr( (string) $wp_tmq_logo_max_width ); ?>px; height: auto;">
						<?php else : ?>
							<span style="font-size: 22px; font-weight: 600; color: <?php echo esc_attr( $wp_tmq_header_text_color ); ?>;"><?php echo esc_html( (string) get_bloginfo( 'name', 'display' ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td class="tmq-card-cell" valign="top" style="background-color: <?php echo esc_attr( $wp_tmq_body_bg ); ?>; color: <?php echo esc_attr( $wp_tmq_body_text_color ); ?>; font-family: <?php echo esc_attr( $wp_tmq_font_family ); ?>; font-size: <?php echo esc_attr( (string) $wp_tmq_body_font_size ); ?>px; line-height: 1.6; padding: <?php echo esc_attr( (string) $wp_tmq_body_padding ); ?>px;">
						<div class="tmq-content">
							<?php
							// $body is pre-processed HTML — wpautop / wptexturize / convert_chars
							// already ran in Engine::preprocessBody() and the engine's
							// `tmq_template_skip` filter lets callers veto the wrap entirely.
							echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					</td>
				</tr>
				<?php if ( '' !== $wp_tmq_footer_text || $wp_tmq_footer_powered_by ) : ?>
				<tr>
					<td align="center" valign="top" style="background-color: <?php echo esc_attr( $wp_tmq_footer_bg ); ?>; color: <?php echo esc_attr( $wp_tmq_footer_text_color ); ?>; font-family: <?php echo esc_attr( $wp_tmq_font_family ); ?>; font-size: 12px; line-height: 1.6; padding: 16px <?php echo esc_attr( (string) $wp_tmq_body_padding ); ?>px;">
						<?php if ( '' !== $wp_tmq_footer_text ) : ?>
							<div style="margin: 0 0 4px;"><?php echo wp_kses_post( $wp_tmq_footer_text ); ?></div>
						<?php endif; ?>
						<?php if ( $wp_tmq_footer_powered_by ) : ?>
							<div style="margin: 0; font-size: 11px; opacity: 0.75;">
								<?php
								/* translators: %s: plugin name */
								echo esc_html( sprintf( __( 'Sent via %s', 'total-mail-queue' ), 'Total Mail Queue' ) );
								?>
							</div>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
