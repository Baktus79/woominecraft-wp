<?php

namespace WooMinecraft\Helpers;

const WM_SERVERS = 'wm_servers';

function setup() {
	$n = fn( $string ) => __NAMESPACE__ . '\\' . $string;

	add_action( 'template_redirect', $n( 'deprecate_json_feed' ) );
	add_filter( 'woocommerce_get_wp_query_args', $n( 'filter_query' ), 10, 2 );
}

function filter_query( $wp_query_args, $query_vars ) {
	if ( isset( $query_vars['meta_query'] ) ) {
		$existing_meta = $wp_query_args['meta_query'] ?? [];
		$wp_query_args['meta_query'] = array_merge( $existing_meta, $query_vars['meta_query'] );
	}
	return $wp_query_args;
}

function deprecate_json_feed() {
	if ( isset( $_GET['wmc_key'] ) ) {
		wp_send_json_error([
			'code' => 'client_outdated',
			'msg'  => esc_html__( 'You are using an older version, please update your Minecraft plugin.', 'woominecraft' )
		]);
		exit;
	}
}

function wmc_items_have_commands( array $items ) {
	foreach ( $items as $item ) {
		$post_id = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
		$commands = get_post_meta( $post_id, 'wmc_commands', true );
		if ( ! empty( $commands ) && is_array( $commands ) ) {
			return true;
		}
	}
	return false;
}

function get_meta_key_delivered( $server ) {
	return '_wmc_delivered_' . sanitize_key( $server );
}

function get_meta_key_pending( $server ) {
	return '_wmc_commands_' . sanitize_key( $server );
}

function get_order_query_params( $server ) {
	$pending_key  = get_meta_key_pending( $server );
	$delivered_key = get_meta_key_delivered( $server );

	return apply_filters( 'woo_minecraft_json_orders_args', [
		'limit'      => -1,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'status'     => 'completed',
		'meta_query' => [
			'relation' => 'AND',
			[
				'key'     => $pending_key,
				'compare' => 'EXISTS',
			],
			[
				'key'     => $delivered_key,
				'compare' => 'NOT EXISTS',
			],
		],
	] );
}
