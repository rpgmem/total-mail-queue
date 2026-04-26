<?php
/**
 * Bootstrap loaded by PHPStan to declare project-specific globals and
 * constants that are not part of WordPress core.
 */

declare(strict_types=1);

// WordPress runtime constants that the WP stubs don't define.
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}

// Provided by lib/html2text/html2text.php (excluded from analysis paths,
// so PHPStan needs its signature here).
if ( ! function_exists( 'convert_html_to_text' ) ) {
    function convert_html_to_text( string $html ): string { return $html; }
}

