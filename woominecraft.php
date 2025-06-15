<?php
/*
Plugin Name: WooMinecraft
Plugin URI: https://github.com/Baktus79/woominecraft-wp
Description: A WooCommerce plugin which allows donations and commands to be sent to your Minecraft server.
Author: Baktus79 (original by Jerry Wood)
Version: 1.5.0
License: GPLv2
Text Domain: woominecraft
Domain Path: /languages
WC requires at least: 6.0
WC tested up to: 8.9.0
Requires PHP: 8.0
Update URI: false
*/

namespace WooMinecraft;

define( 'WMC_INCLUDES', plugin_dir_path( __FILE__ ) . 'includes/' );
define( 'WMC_URL', plugin_dir_url( __FILE__ ) );
define( 'WMC_VERSION', '1.5.0' );

// Require the helpers file, for use in :allthethings:
require_once WMC_INCLUDES . 'helpers.php';
Helpers\setup();

// Handle everything order-related.
require_once WMC_INCLUDES . 'order-manager.php';
Orders\Manager\setup();

// Handle everything order-cache related.
require_once WMC_INCLUDES . 'order-cache-controller.php';
Orders\Cache\setup();

// Load the REST API
require_once WMC_INCLUDES . 'rest-api.php';
REST\setup();

require_once WMC_INCLUDES . 'woocommerce-admin.php';
WooCommerce\setup();

// Fire an action after all is done.
do_action( 'woominecraft_setup' );
