<?php
/**
 * Event routes.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Support\Validator;

/** Events Controller implementation. */
class Events_Controller extends Resource_Controller {
	/** Register precisely the four event operations. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/events',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => Validator::fields( false, true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/events/(?P<id>[1-9][0-9]*)',
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
					'args'                => $this->id_args() + Validator::fields(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}
}
