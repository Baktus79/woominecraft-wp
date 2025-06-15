<?php

namespace WooMinecraft\REST;

use function WooMinecraft\Helpers\get_meta_key_delivered;
use function WooMinecraft\Orders\Cache\bust_command_cache;
use function WooMinecraft\Orders\Manager\get_orders_for_server;

function setup() {
    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_endpoints' );
}

function get_rest_namespace() {
    return 'wmc/v1';
}

function register_endpoints() {
    register_rest_route(
        get_rest_namespace(),
        '/server/(?P<server>[\S]+)',
        [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => __NAMESPACE__ . '\\get_pending_orders',
            'permission_callback' => __NAMESPACE__ . '\\verify_api_key',
        ]
    );

    register_rest_route(
        get_rest_namespace(),
        '/server/(?P<server>[\S]+)',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => __NAMESPACE__ . '\\process_orders',
            'permission_callback' => __NAMESPACE__ . '\\verify_api_key',
        ]
    );
}

function verify_api_key( $request ) {
    $server_key = sanitize_text_field( $request->get_param( 'server' ) );
    $servers    = get_option( 'wm_servers', [] );

    if ( empty( $servers ) ) {
        error_log( '[WooMinecraft] Ingen servere konfigurert.' );
        return new \WP_Error( 'no_servers', 'No servers configured.', [ 'status' => 500 ] );
    }

    $keys = wp_list_pluck( $servers, 'key' );
    if ( ! in_array( $server_key, $keys, true ) ) {
        error_log( '[WooMinecraft] Ugyldig server-key: ' . $server_key );
        return new \WP_Error( 'invalid_key', 'Invalid server key.', [ 'status' => 401 ] );
    }

    return true;
}

function get_pending_orders( $request ) {
    $server_key = sanitize_text_field( $request->get_param( 'server' ) );

    $pending_orders = wp_cache_get( $server_key, 'wmc_commands' );
    if ( false === $pending_orders ) {
        $pending_orders = get_orders_for_server( $server_key );
        if ( is_wp_error( $pending_orders ) ) {
            error_log( '[WooMinecraft] Feil ved henting av ordrer: ' . $pending_orders->get_error_message() );
            return $pending_orders;
        }
        wp_cache_set( $server_key, $pending_orders, 'wmc_commands', HOUR_IN_SECONDS );
    }

    return rest_ensure_response( [ 'orders' => $pending_orders ] );
}

function sanitized_orders_post( $post_data ) {
    if ( is_string( $post_data ) ) {
        $post_data = json_decode( stripslashes( urldecode( $post_data ) ), true );
    }

    if ( ! is_array( $post_data ) ) {
        return [];
    }

    return array_map( 'intval', $post_data );
}

function process_orders( $request ) {
    $server_key = sanitize_text_field( $request->get_param( 'server' ) );
    $body       = $request->get_json_params();

    if ( empty( $body['processedOrders'] ) ) {
        return new \WP_Error( 'bad_request', 'Missing processedOrders.', [ 'status' => 400 ] );
    }

    $orders = sanitized_orders_post( $body['processedOrders'] );
    if ( empty( $orders ) ) {
        return new \WP_Error( 'invalid_data', 'Invalid or empty order list.', [ 'status' => 400 ] );
    }

    foreach ( $orders as $order_id ) {
        update_post_meta( $order_id, get_meta_key_delivered( $server_key ), true );
    }

    bust_command_cache( $server_key );

    return rest_ensure_response( [ 'status' => 'ok', 'processed' => count( $orders ) ] );
}
