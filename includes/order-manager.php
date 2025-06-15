<?php

namespace WooMinecraft\Orders\Manager;

use function WooMinecraft\Helpers\wmc_items_have_commands;
use function WooMinecraft\Orders\Cache\bust_command_cache;

function setup() {
	$n = fn( $string ) => __NAMESPACE__ . '\\' . $string;

	add_action( 'woocommerce_checkout_update_order_meta', $n( 'save_commands_to_order' ) );
	add_action( 'woocommerce_before_checkout_billing_form', $n( 'additional_checkout_field' ) );
	add_action( 'woocommerce_thankyou', $n( 'thanks' ) );
	add_action( 'woocommerce_checkout_process', $n( 'require_fields' ) );
}

function require_fields() {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	$items = WC()->cart->cart_contents;

	if ( ! wmc_items_have_commands( $items ) ) {
		return;
	}

	$player_id = $_POST['player_id'] ?? '';
	if ( ! sanitize_text_field( $player_id ) ) {
		wc_add_notice( __( 'You MUST provide a Minecraft username.', 'woominecraft' ), 'error' );
	}
}

function save_commands_to_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$items = $order->get_items();
	$tmp   = [];

	$player_name = sanitize_text_field( $_POST['player_id'] ?? '' );
	if ( empty( $player_name ) ) {
		return;
	}
	
	update_post_meta( $order_id, 'player_id', $player_name );

	foreach ( $items as $item ) {
		$product_id = $item->get_variation_id() ?: $item->get_product_id();
		if ( ! $product_id ) continue;

		$commands = get_post_meta( $product_id, 'wmc_commands', true );
		if ( empty( $commands ) ) continue;

		$qty = $item->get_quantity();
		for ( $n = 0; $n < $qty; $n++ ) {
			foreach ( (array) $commands as $server_key => $cmd_list ) {
				$tmp[ $server_key ] ??= [];
				foreach ( (array) $cmd_list as $cmd ) {
					$tmp[ $server_key ][] = apply_filters(
						'woominecraft_order_command',
						str_replace( '%s', $player_name, $cmd ),
						$cmd,
						$player_name
					);
				}
			}
		}
	}

	// Fjern tomme linjer og lagre
	foreach ( $tmp as $server_key => $cmds ) {
		$filtered = array_filter( array_map( 'trim', $cmds ) );
		if ( ! empty( $filtered ) ) {
			update_post_meta( $order_id, '_wmc_commands_' . $server_key, $filtered );
		}
	}
}

function additional_checkout_field( $cart ) {
	$items = WC()->cart->cart_contents;
	if ( ! wmc_items_have_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
		return false;
	}

	echo '<div id="woo_minecraft">';
	woocommerce_form_field(
		'player_id',
		[
			'type'        => 'text',
			'class'       => [],
			'label'       => __( 'Player ID ( Minecraft Username ):', 'woominecraft' ),
			'placeholder' => __( 'Required Field', 'woominecraft' ),
			'required'    => true,
		],
		$cart->get_value( 'player_id' )
	);
	echo '</div>';

	return true;
}

function thanks( $id ) {
	$player_name = get_post_meta( $id, 'player_id', true );
	if ( ! empty( $player_name ) ) {
		echo '<div class="woo_minecraft"><h4>' . esc_html__( 'Minecraft Details', 'woominecraft' ) . '</h4>';
		echo '<p><strong>' . esc_html__( 'Username:', 'woominecraft' ) . '</strong> ' . esc_html( $player_name ) . '</p></div>';
	}
}

function reset_order( $order_id, $server_key ) {
	delete_post_meta( $order_id, '_wmc_delivered_' . $server_key );
	bust_command_cache( $server_key );
	return true;
}

function get_player_id_for_order( $order_id ) {
	$order_id = is_object( $order_id ) && isset( $order_id->ID ) ? (int) $order_id->ID : (int) $order_id;
	return get_post_meta( $order_id, 'player_id', true );
}

function generate_order_json( $order_post, $key ) {
	$order_id = is_object( $order_post ) && isset( $order_post->ID ) ? (int) $order_post->ID : (int) $order_post;
	return get_post_meta( $order_id, '_wmc_commands_' . $key, true );
}

function get_orders_for_server( $server_key ) {
	$args = \WooMinecraft\Helpers\get_order_query_params( $server_key );

	if ( empty( $args ) ) {
		error_log( '[WooMinecraft] Invalid query params for server: ' . $server_key );
		return new \WP_Error( 'invalid_args', 'Malformed request for server orders.', [ 'status' => 500 ] );
	}

	$orders = wc_get_orders( $args );
	$output = [];

	foreach ( $orders as $order ) {
		$order_id = $order->get_id();
		if ( ! $order_id ) continue;

		$player = get_player_id_for_order( $order_id );
		$data   = generate_order_json( $order_id, $server_key );
		if ( empty( $data ) ) continue;

		$output[] = [
			'player'   => $player,
			'order_id' => $order_id,
			'commands' => $data,
		];
	}

	return $output;
}
