<?php
/**
 * REST controller for the message-preview lazy-load endpoint.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Rest;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Support\HtmlPreview;
use TotalMailQueue\Support\Serializer;
use WP_Error;
use WP_REST_Request;

/**
 * Exposes `GET /tmq/v1/message/{id}` so the Log/Retention table can lazy-load
 * the rendered HTML preview of a stored message without bloating the initial
 * page response.
 */
final class MessageController {

	/**
	 * REST namespace + version segment.
	 */
	public const NAMESPACE = 'tmq/v1';

	/**
	 * Hook the route registration onto rest_api_init.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'registerRoutes' ), 10, 0 );
	}

	/**
	 * `rest_api_init` callback.
	 */
	public static function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/message/(?P<id>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'getMessage' ),
				'permission_callback' => array( self::class, 'permissionCheck' ),
			)
		);
	}

	/**
	 * Capability gate — only manage_options users can read raw message bodies.
	 */
	public static function permissionCheck(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Render the message preview for the requested ID.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array{status:string,data:array{html:string}}|WP_Error
	 */
	public static function getMessage( WP_REST_Request $request ) {
		global $wpdb;
		$table = Schema::queueTable();
		$id    = (int) $request['id'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `id` = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'no_message', __( 'Message not found', 'total-mail-queue' ), array( 'status' => 404 ) );
		}

		$headers = Serializer::decode( (string) $row['headers'] );
		if ( is_string( $headers ) ) {
			$headers = array( $headers );
		} elseif ( ! is_array( $headers ) ) {
			$headers = array();
		}

		$is_html = false;
		foreach ( $headers as $header ) {
			if ( preg_match( '/content-type: ?text\/html/i', (string) $header ) ) {
				$is_html = true;
				break;
			}
		}

		return array(
			'status' => 'ok',
			'data'   => array(
				'html' => HtmlPreview::renderListMessage( Serializer::decode( (string) $row['message'] ), $is_html ),
			),
		);
	}
}
