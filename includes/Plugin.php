<?php
/**
 * Plugin wiring and minimal information screen.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Rest\Events_Controller;
use QuixDevs\TickeraRestAPI\Rest\Tickets_Controller;

/** Plugin implementation. */
class Plugin {
	/** Wire hooks after plugins have loaded. */
	public function boot() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'private_response' ), 10, 3 );
	}

	/** Register controllers after Tickera's init CPT registration. */
	public function routes() {
		( new Events_Controller() )->register_routes();
		( new Tickets_Controller() )->register_routes();
	}

	/**
	 * Prevent caches from sharing author-scoped responses.
	 *
	 * @param \WP_REST_Response $response Current REST response.
	 * @param \WP_REST_Server   $server REST server instance.
	 * @param \WP_REST_Request  $request Current REST request.
	 */
	public function private_response( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/quixdevs-tickera/v1/' ) ) {
			$response->header( 'Cache-Control', 'private, no-store' );
			$response->header( 'Vary', 'Authorization, Cookie' );
		}
		return $response;
	}

	/** Admin-only information menu. */
	public function menu() {
		global $menu;
		$parent = 'tools.php';
		foreach ( (array) $menu as $item ) {
			if ( 'edit.php?post_type=tc_events' === $item[2] ) {
				$parent = $item[2];
				break;
			}
		}
		add_submenu_page( $parent, __( 'QuixDevs REST API', 'quixdevs-tickera-rest-api' ), __( 'QuixDevs REST API', 'quixdevs-tickera-rest-api' ), 'manage_options', 'quixdevs-tickera-rest-api', array( $this, 'screen' ) );
	}

	/** Safe dependency notice without activation fatals. */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$check = ( new Dependencies() )->check();
		if ( is_wp_error( $check ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $check->get_error_message() ) . '</p></div>';
		}
	}

	/** Render static guidance; no secrets or author data. */
	public function screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$dependencies = new Dependencies();
		$check        = $dependencies->check();
		echo '<div class="wrap"><h1>' . esc_html__( 'QuixDevs Tickera REST API', 'quixdevs-tickera-rest-api' ) . '</h1>';
		echo '<p>' . esc_html__( 'Event & Ticket Type Management for Authors. Manage Events and Tickets Through REST API.', 'quixdevs-tickera-rest-api' ) . '</p>';
		echo '<p>' . esc_html( is_wp_error( $check ) ? $check->get_error_message() : __( 'API enabled. Tickera dependency is active.', 'quixdevs-tickera-rest-api' ) ) . '</p>';
		echo '<p>' . esc_html__( 'Tickera version:', 'quixdevs-tickera-rest-api' ) . ' ' . esc_html( $dependencies->version() ) . '</p>';
		echo '<p><code>' . esc_html( rest_url( 'quixdevs-tickera/v1' ) ) . '</code></p>';
		echo '<p>' . esc_html__( 'Use user-specific Application Passwords over HTTPS, or WordPress login cookies with an X-WP-Nonce REST nonce. Grant selected users Tickera creation and editing capabilities; this addon grants none.', 'quixdevs-tickera-rest-api' ) . '</p>';
		echo '<p>' . esc_html( $dependencies->bridge() ? __( 'Bridge mode: event operations enabled; standalone ticket operations disabled.', 'quixdevs-tickera-rest-api' ) : __( 'Standalone Tickera mode.', 'quixdevs-tickera-rest-api' ) ) . '</p>';
		echo '<p><a href="' . esc_url( plugins_url( '../docs/rest-api.md', __FILE__ ) ) . '">' . esc_html__( 'API documentation', 'quixdevs-tickera-rest-api' ) . '</a></p></div>';
	}
}
