<?php
/**
 * Standalone ticket routes.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Support\Validator;

/** Tickets Controller implementation. */
class Tickets_Controller extends Resource_Controller {
	/**
	 * Whether this controller handles tickets.
	 *
	 * @var bool
	 */
	protected $ticket = true;

	/** Register precisely the four standalone ticket operations. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/events/(?P<id>[1-9][0-9]*)/tickets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => $this->id_args() + $this->get_collection_params(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => $this->id_args() + Validator::fields( true, true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/tickets/(?P<id>[1-9][0-9]*)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => $this->id_args(),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => $this->id_args() + Validator::fields( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}
}
