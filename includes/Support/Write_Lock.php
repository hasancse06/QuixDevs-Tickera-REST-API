<?php
/**
 * Connection-scoped serialization of this addon's related REST writes.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native MySQL/MariaDB advisory lock; no tables or stored credentials. */
class Write_Lock {
	/**
	 * Acquired lock name.
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Acquire without blocking a worker or bypassing a conflicting writer.
	 *
	 * @param string $resource_key Resource group to serialize.
	 */
	public function acquire( $resource_key ) {
		global $wpdb;
		$name = 'qtra-' . substr( hash( 'sha256', $wpdb->prefix . ':' . $resource_key ), 0, 59 );
	 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-level database lock cannot be cached or represented by post APIs.
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
		if ( '1' !== (string) $result ) {
			return new \WP_Error( 'quixdevs_resource_busy', __( 'A related resource is being updated or its write lock is unavailable. Retrieve it and retry shortly.', 'quixdevs-tickera-rest-api' ), array( 'status' => 409 ) );
		}
		$this->name = $name;
		return true;
	}

	/** Release only this connection's acquired lock. */
	public function release() {
		global $wpdb;
		if ( '' !== $this->name ) {
		 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the uncached connection-level lock in a finally block.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->name ) );
			$this->name = '';
		}
	}
}
