<?php
/**
 * Dependency and operating-mode checks.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dependencies implementation. */
class Dependencies {
	/** Return the active Tickera version, without reading untrusted options. */
	public function version() {
		global $tc;
		return $tc instanceof \Tickera\TC ? (string) $tc->version : '';
	}

	/**
	 * Reject unverified persistence models.
	 *
	 * @param bool $tickets Whether standalone tickets are required.
	 */
	public function check( $tickets = false ) {
		if ( ! class_exists( '\Tickera\TC' ) || ! $this->version() || ! post_type_exists( 'tc_events' ) ) {
			return new \WP_Error( 'quixdevs_tickera_missing', __( 'Tickera must be installed and active.', 'quixdevs-tickera-rest-api' ), array( 'status' => 503 ) );
		}
		if ( '3.6.0.8' !== $this->version() || ! class_exists( '\Tickera\Addons\TC_Better_Events' ) || ! function_exists( 'tickera_apply_filters' ) ) {
			return new \WP_Error( 'quixdevs_tickera_unsupported', __( 'This release supports the verified Tickera 3.6.0.8 persistence model.', 'quixdevs-tickera-rest-api' ), array( 'status' => 503 ) );
		}
		if ( $tickets && ( $this->bridge() || ! post_type_exists( 'tc_tickets' ) || 'event_name' !== tickera_apply_filters( 'tickera_event_name_field_name', 'event_name', 0 ) ) ) {
			return new \WP_Error( 'quixdevs_ticket_mode_unsupported', __( 'Standalone ticket operations are unavailable in Bridge/WooCommerce mode or with a customized relationship field. Event operations remain available.', 'quixdevs-tickera-rest-api' ), array( 'status' => 409 ) );
		}
		return true;
	}

	/** Detect both Tickera filters and plugin/network activation. */
	public function bridge() {
		$plugins = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		return class_exists( '\TC_WooCommerce_Bridge' )
		|| in_array( 'bridge-for-woocommerce/bridge-for-woocommerce.php', $plugins, true )
		|| ( function_exists( 'tickera_apply_filters' ) && ( tickera_apply_filters( 'tickera_bridge_for_woocommerce_is_active', false ) || tickera_apply_filters( 'tickera_is_woo', false ) ) );
	}
}
