<?php

namespace WooMinecraft\Orders\Cache;

/**
 * Initializes order cache-related hooks.
 */
function setup() {
	add_action( 'save_post', __NAMESPACE__ . '\\maybe_bust_cache_on_save', 10, 3 );
}

/**
 * Deletes cached commands for the given server key.
 *
 * @param string $server_key
 */
function bust_command_cache( $server_key ) {
	$server_key = sanitize_key( $server_key );
	wp_cache_delete( $server_key, 'wmc_commands' );
}

/**
 * Hook into post saves to determine whether cache should be cleared.
 *
 * @param int     $post_ID
 * @param \WP_Post $post
 * @param bool    $update
 */
function maybe_bust_cache_on_save( $post_ID, $post, $update ) {
	if ( 'shop_order' !== $post->post_type ) {
		return;
	}

	$servers = get_option( 'wm_servers', [] );
	if ( empty( $servers ) ) {
		return;
	}

	foreach ( $servers as $server ) {
		if ( ! empty( $server['key'] ) ) {
			bust_command_cache( $server['key'] );
		}
	}
}