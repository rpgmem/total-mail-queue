<?php
/**
 * Microsoft 365 access tokens for SMTP.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

use TotalMailQueue\Support\Encryption;

/**
 * Gets the token an SMTP account signs in to Microsoft 365 with, when its
 * sign-in method is the Microsoft 365 app (#186).
 *
 * It uses the OAuth 2.0 client-credentials flow, which Exchange Online
 * accepts for SMTP AUTH when the app holds `SMTP.SendAsApp` and its
 * service principal has access to the mailbox: no person signs in and no
 * mailbox password is stored. The token is cached until two minutes before
 * it expires, so a batch asks Microsoft once, not once per email.
 *
 * A failure keeps Microsoft's own explanation (`error_description`, its
 * first line), so the Log and Test Connection say "invalid client secret"
 * instead of a generic authentication error.
 */
final class M365Token {

	/**
	 * Scope Exchange Online issues SMTP tokens for.
	 */
	public const SCOPE = 'https://outlook.office365.com/.default';

	/**
	 * Sign-in method value stored in `auth_type`.
	 */
	public const AUTH_TYPE = 'm365';

	/**
	 * Transient prefix for cached tokens.
	 */
	private const CACHE_PREFIX = 'tmq_m365_token_';

	/**
	 * Seconds trimmed from a token's lifetime before it is reused.
	 */
	private const EXPIRY_MARGIN = 120;

	/**
	 * Why the last token request in this process failed, or ''.
	 *
	 * @var string
	 */
	private static string $last_error = '';

	/**
	 * A token for an SMTP account row.
	 *
	 * @param array<string,mixed> $account Row from the SMTP accounts table.
	 * @return string|\WP_Error
	 */
	public static function forAccount( array $account ) {
		return self::fetch(
			(string) ( $account['oauth_tenant'] ?? '' ),
			(string) ( $account['oauth_client_id'] ?? '' ),
			Encryption::decrypt( (string) ( $account['oauth_client_secret'] ?? '' ) )
		);
	}

	/**
	 * A token for the app, from the cache or from Microsoft.
	 *
	 * @param string $tenant    Directory (tenant) ID or verified domain.
	 * @param string $client_id Application (client) ID.
	 * @param string $secret    Client secret, in clear.
	 * @param bool   $cached    Whether a cached token may be used.
	 * @return string|\WP_Error
	 */
	public static function fetch( string $tenant, string $client_id, string $secret, bool $cached = true ) {
		self::$last_error = '';
		$tenant           = trim( $tenant );
		$client_id        = trim( $client_id );
		if ( ! self::validTenant( $tenant ) ) {
			return self::fail( 'tmq_m365_tenant', __( 'The Tenant ID is not valid: use the Directory (tenant) ID, or the organisation\'s domain.', 'total-mail-queue' ) );
		}
		if ( ! self::validClientId( $client_id ) ) {
			return self::fail( 'tmq_m365_client', __( 'The Client ID is not valid: use the Application (client) ID, a GUID.', 'total-mail-queue' ) );
		}
		if ( '' === $secret ) {
			return self::fail( 'tmq_m365_secret', __( 'The client secret is missing.', 'total-mail-queue' ) );
		}

		$key = self::CACHE_PREFIX . md5( $tenant . '|' . strtolower( $client_id ) . '|' . hash( 'sha256', $secret ) );
		if ( $cached ) {
			$token = get_transient( $key );
			if ( is_string( $token ) && '' !== $token ) {
				return $token;
			}
		}

		$response = wp_remote_post(
			'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $client_id,
					'client_secret' => $secret,
					'scope'         => self::SCOPE,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: network error */
			return self::fail( 'tmq_m365_http', sprintf( __( 'Microsoft could not be reached: %s', 'total-mail-queue' ), $response->get_error_message() ) );
		}

		$body  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body  = is_array( $body ) ? $body : array();
		$token = isset( $body['access_token'] ) && is_string( $body['access_token'] ) ? $body['access_token'] : '';
		if ( '' === $token ) {
			$reason = isset( $body['error_description'] ) && is_string( $body['error_description'] ) ? strtok( $body['error_description'], "\r\n" ) : '';
			if ( false === $reason || '' === $reason ) {
				/* translators: %d: HTTP status code */
				$reason = sprintf( __( 'HTTP %d', 'total-mail-queue' ), (int) wp_remote_retrieve_response_code( $response ) );
			}
			/* translators: %s: Microsoft's explanation */
			return self::fail( 'tmq_m365_token', sprintf( __( 'Microsoft refused the sign-in: %s', 'total-mail-queue' ), $reason ) );
		}

		$lifetime = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		set_transient( $key, $token, max( 60, $lifetime - self::EXPIRY_MARGIN ) );
		return $token;
	}

	/**
	 * Why the last token request failed, cleared once read.
	 */
	public static function takeLastError(): string {
		$error            = self::$last_error;
		self::$last_error = '';
		return $error;
	}

	/**
	 * A tenant GUID or domain: letters, digits, dots and hyphens.
	 *
	 * @param string $tenant Value to check.
	 */
	public static function validTenant( string $tenant ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,98}[A-Za-z0-9])?$/', $tenant );
	}

	/**
	 * A GUID.
	 *
	 * @param string $client_id Value to check.
	 */
	public static function validClientId( string $client_id ): bool {
		return 1 === preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $client_id );
	}

	/**
	 * Record and return a failure.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message for the Log and Test Connection.
	 */
	private static function fail( string $code, string $message ): \WP_Error {
		self::$last_error = $message;
		return new \WP_Error( $code, $message );
	}
}
