<?php
/**
 * Plugin Name: QuixDevs Tickera REST API
 * Plugin URI: https://quixdevs.com/
 * Description: Secure REST API endpoints for Tickera event and ticket type authors to create, view, and edit their own events and ticket types.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: tickera-event-ticketing-system
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quixdevs-tickera-rest-api
 *
 * Copyright 2026 QuixDevs. Developed by Md Abu Hasan (https://hasan.online/).
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		if ( ! preg_match( '/^[A-Za-z_\\\\]+$/D', $relative ) ) {
			return;
		}
		$file = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action( 'plugins_loaded', array( new Plugin(), 'boot' ) );
