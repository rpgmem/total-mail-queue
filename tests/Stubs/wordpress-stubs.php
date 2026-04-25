<?php
/**
 * Minimal WordPress function/constant stubs.
 *
 * Only what's needed for total-mail-queue.php to load and for the functions
 * under test to behave correctly. Heavier integration coverage (mocking hook
 * dispatch, database queries, etc.) lives in the integration test suite.
 */

declare(strict_types=1);

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
    define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ): string {
        // Deterministic per-scheme salt so encryption round-trips are stable.
        return 'tmq-test-salt-' . $scheme;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ): string|false {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'is_serialized' ) ) {
    /**
     * Simplified port of WordPress core's is_serialized().
     *
     * Detects the standard PHP serialization prefixes (s:, i:, d:, b:, a:, O:, C:, N;).
     * Sufficient for wp_tmq_decode() to correctly route legacy data to unserialize().
     */
    function is_serialized( $data, $strict = true ): bool {
        if ( ! is_string( $data ) ) {
            return false;
        }
        $data = trim( $data );
        if ( 'N;' === $data ) {
            return true;
        }
        if ( strlen( $data ) < 4 ) {
            return false;
        }
        if ( ':' !== $data[1] ) {
            return false;
        }
        return (bool) preg_match( '/^(s|i|d|b|a|O|C):\d+:/', $data );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value, $autoload = null ): bool {
        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $name ): bool {
        return true;
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ): array {
        if ( is_array( $args ) ) {
            $parsed = $args;
        } elseif ( is_object( $args ) ) {
            $parsed = get_object_vars( $args );
        } else {
            $parsed = array();
            parse_str( (string) $args, $parsed );
        }
        return is_array( $defaults ) ? array_merge( $defaults, $parsed ) : $parsed;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        if ( $type === 'mysql' ) {
            return gmdate( 'Y-m-d H:i:s' );
        }
        return gmdate( $type );
    }
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
    function wp_doing_cron(): bool {
        return false;
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook, $args = array() ) {
        return false;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ): bool {
        return true;
    }
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $timestamp, $hook, $args = array() ): bool {
        return true;
    }
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( $hook, $args = array() ): bool {
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $args = 1 ): bool {
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $args = 1 ): bool {
        return true;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) {
        return $value;
    }
}

if ( ! function_exists( 'do_action_ref_array' ) ) {
    function do_action_ref_array( $tag, $args ): void {}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ): void {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ): void {}
}

if ( ! function_exists( 'register_uninstall_hook' ) ) {
    function register_uninstall_hook( $file, $callback ): void {}
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin(): bool {
        return false;
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ): string {
        return rtrim( dirname( $file ), '/\\' ) . '/';
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ): string {
        return basename( $file );
    }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $path = false ): bool {
        return false;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ): string {
        return (string) $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ): string {
        return (string) $text;
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ): string {
        return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ): string {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ): string {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ): string {
        return (string) $url;
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = 'default' ): string {
        return esc_attr( (string) $text );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ): string {
        return is_string( $text ) ? trim( $text ) : '';
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ): string {
        return is_string( $email ) ? filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '' : '';
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ): string {
        return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
    }
}

if ( ! function_exists( 'register_setting' ) ) {
    function register_setting( $option_group, $option_name, $args = array() ): void {}
}

if ( ! function_exists( 'add_settings_section' ) ) {
    function add_settings_section( ...$args ): void {}
}

if ( ! function_exists( 'add_settings_field' ) ) {
    function add_settings_field( ...$args ): void {}
}

// Minimal WP_List_Table replacement so total-mail-queue-options.php can declare
// wp_tmq_Log_Table without requiring WordPress's admin includes.
if ( ! class_exists( 'WP_List_Table' ) ) {
    class WP_List_Table {
        protected $_args = array( 'plural' => 'items' );
        protected $_column_headers = array();
        public $items = array();

        public function __construct( $args = array() ) {}
        public function get_pagenum() { return 1; }
        public function set_pagination_args( $args ) {}
        public function display() {}
        public function prepare_items() {}
        public function current_action() { return false; }
    }
}
