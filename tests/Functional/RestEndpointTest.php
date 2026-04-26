<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use WP_REST_Request;

/**
 * @covers \TotalMailQueue\Rest\MessageController::getMessage
 * @covers \TotalMailQueue\Rest\MessageController::registerRoutes
 */
final class RestEndpointTest extends FunctionalTestCase {

    public function test_endpoint_requires_manage_options_capability(): void {
        $id = $this->insertQueueItem( array( 'message' => 'secret body' ) );

        // Subscriber: should be denied.
        $subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber );

        $request  = new WP_REST_Request( 'GET', "/tmq/v1/message/$id" );
        $response = rest_do_request( $request );

        self::assertSame( 403, $response->get_status(), 'Non-admin users must not be able to read queue messages.' );
    }

    public function test_admin_user_receives_rendered_message(): void {
        $id = $this->insertQueueItem( array( 'message' => 'hello world' ) );

        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $request  = new WP_REST_Request( 'GET', "/tmq/v1/message/$id" );
        $response = rest_do_request( $request );

        self::assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        self::assertSame( 'ok', $data['status'] );
        self::assertArrayHasKey( 'html', $data['data'] );
        self::assertStringContainsString( 'hello world', $data['data']['html'] );
    }

    public function test_unknown_id_returns_404(): void {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $request  = new WP_REST_Request( 'GET', '/tmq/v1/message/9999999' );
        $response = rest_do_request( $request );

        self::assertSame( 404, $response->get_status() );
    }
}
