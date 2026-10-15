<?php
/**
 * Shared thin REST controller.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Authorization\Ownership_Policy;
use QuixDevs\TickeraRestAPI\Data\Tickera_Repository;
use QuixDevs\TickeraRestAPI\Services\Resource_Service;
use QuixDevs\TickeraRestAPI\Support\Validator;

/** Resource Controller implementation. */
abstract class Resource_Controller extends \WP_REST_Controller {
	/**
	 * Whether this controller handles tickets.
	 *
	 * @var bool
	 */
	protected $ticket = false;
	/**
	 * Authorization policy.
	 *
	 * @var Ownership_Policy
	 */
	protected $policy;
	/**
	 * Verified persistence adapter.
	 *
	 * @var Tickera_Repository
	 */
	protected $repository;

	/** Initialize small, explicit dependencies. */
	public function __construct() {
		$this->namespace  = 'quixdevs-tickera/v1';
		$this->policy     = new Ownership_Policy();
		$this->repository = new Tickera_Repository();
	}

	/** Shared request schemas for bounded lists. */
	public function get_collection_params() {
		$params = array(
			'page'     => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100000,
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
				'default' => 20,
			),
			'status'   => array(
				'type' => 'string',
				'enum' => array( 'draft', 'pending', 'publish', 'private' ),
			),
			'search'   => array(
				'type'      => 'string',
				'maxLength' => 200,
			),
			'orderby'  => array(
				'type'    => 'string',
				'enum'    => array( 'date', 'modified', 'title', 'ID' ),
				'default' => 'date',
			),
			'order'    => array(
				'type'    => 'string',
				'enum'    => array( 'ASC', 'DESC' ),
				'default' => 'DESC',
			),
		);
		foreach ( $params as &$param ) {
			$param['validate_callback'] = array( Validator::class, 'validate_argument' );
			$param['sanitize_callback'] = 'rest_sanitize_request_arg';
		}
		$params['search']['sanitize_callback'] = array( Validator::class, 'sanitize_argument' );
		return $params;
	}

	/** An explicit positive path ID schema. */
	protected function id_args() {
		return array(
			'id' => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => PHP_INT_MAX,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * Authorize list/read/create/update without an administrator bypass.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 */
	public function permissions( $request ) {
		$write = in_array( $request->get_method(), array( 'POST', 'PATCH' ), true );
		$check = $this->policy->gate( $this->ticket, $write );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$check = $this->envelope( $request, $write );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$url    = $request->get_url_params();
		$id     = isset( $url['id'] ) ? (int) $url['id'] : 0;
		$nested = $this->ticket && false !== strpos( $request->get_route(), '/events/' );
		if ( $id ) {
			$check = $nested || ! $this->ticket ? $this->policy->event( $id ) : $this->policy->ticket( $id );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		if ( 'POST' === $request->get_method() || ! $id || $nested ) {
			return $this->policy->collection( $this->ticket ? 'tc_tickets' : 'tc_events', 'POST' === $request->get_method() );
		}
		return true;
	}

	/**
	 * Refuse mass assignment, query overrides, and non-object/form write bodies.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 * @param bool             $write Whether the operation writes data.
	 */
	private function envelope( $request, $write ) {
		$collection    = preg_match( '#/events(?:/[1-9][0-9]*/tickets)?$#D', $request->get_route() );
		$allowed_query = ! $write && 'GET' === $request->get_method() && $collection ? array_keys( $this->get_collection_params() ) : array();
		foreach ( array_keys( $request->get_query_params() ) as $key ) {
			if ( ! in_array( $key, $allowed_query, true ) ) {
				return Validator::error( __( 'Unsupported query parameter.', 'quixdevs-tickera-rest-api' ) );
			}
		}
		if ( ! $write ) {
			if ( ! empty( $request->get_body() ) ) {
				return Validator::error( __( 'Read requests must not have a body.', 'quixdevs-tickera-rest-api' ) );
			}
			return true;
		}
		if ( ! $request->is_json_content_type() || ! is_object( json_decode( $request->get_body() ) ) ) {
			return Validator::error( __( 'Send a JSON object with Content-Type: application/json.', 'quixdevs-tickera-rest-api' ) );
		}
		$body   = $request->get_json_params();
		$fields = Validator::fields( $this->ticket, 'POST' === $request->get_method() );
		foreach ( array_keys( $body ) as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				return Validator::error( __( 'Unsupported request field. Ownership, metadata and existing ticket associations cannot be changed.', 'quixdevs-tickera-rest-api' ) );
			}
		}
		if ( ! $body ) {
			return Validator::error( __( 'Supply at least one writable field.', 'quixdevs-tickera-rest-api' ) );
		}
		return true;
	}

	/**
	 * Return a verified resource.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 */
	public function get_item( $request ) {
		$id   = (int) $request->get_url_params()['id'];
		$post = $this->ticket ? $this->policy->ticket( $id ) : $this->policy->event( $id );
		return is_wp_error( $post ) ? $post : new \WP_REST_Response( $this->repository->read( $post ), 200 );
	}

	/**
	 * List only accessible owned resources with pagination headers.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 */
	public function get_items( $request ) {
		$event_id = $this->ticket ? (int) $request->get_url_params()['id'] : 0;
		if ( $event_id ) {
			$check = $this->policy->event( $event_id );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		$query = $this->repository->query( $this->ticket ? 'tc_tickets' : 'tc_events', $request, $event_id );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		$items = array();
		foreach ( $query->posts as $candidate ) {
			$post = $this->ticket ? $this->policy->ticket( $candidate->ID ) : $this->policy->event( $candidate->ID );
			if ( ! is_wp_error( $post ) ) {
				$items[] = $this->repository->read( $post );
			}
		}
		$response = new \WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	/**
	 * Create with server-enforced author/event association.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 */
	public function create_item( $request ) {
		return $this->persist( $request, true );
	}

	/**
	 * PATCH only supplied fields.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 */
	public function update_item( $request ) {
		return $this->persist( $request, false );
	}

	/**
	 * Delegate validation and persistence; format a safe REST response.
	 *
	 * @param \WP_REST_Request $request Current REST request.
	 * @param bool             $create Whether this creates a resource.
	 */
	private function persist( $request, $create ) {
		$changes = array();
		foreach ( array_keys( $request->get_json_params() ) as $key ) {
			$changes[ $key ] = $request[ $key ];
		}
		$url      = $request->get_url_params();
		$id       = ! $create ? (int) $url['id'] : 0;
		$event_id = $create && $this->ticket ? (int) $url['id'] : 0;
		$post     = ( new Resource_Service() )->save( $this->ticket, $changes, $id, $event_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		// Recheck visibility after save hooks, before returning any data.
		$check = $create ? $this->policy->created( get_post( $post->ID ), $this->ticket ) : ( $this->ticket ? $this->policy->ticket( $post->ID ) : $this->policy->event( $post->ID ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$response = new \WP_REST_Response( $this->repository->read( $post ), $create ? 201 : 200 );
		if ( $create ) {
			$response->header( 'Location', rest_url( $this->namespace . ( $this->ticket ? '/tickets/' : '/events/' ) . $post->ID ) );
		}
		return $response;
	}

	/** OPTIONS exposes the implemented request/response field schema. */
	public function get_item_schema() {
		$fields = Validator::fields( $this->ticket );
		foreach ( $fields as &$field ) {
			unset( $field['validate_callback'], $field['sanitize_callback'], $field['required'] );
		}
		$fields += array(
			'id'         => array(
				'type'     => 'integer',
				'readonly' => true,
			),
			'author_id'  => array(
				'type'     => 'integer',
				'readonly' => true,
			),
			'active'     => array(
				'type'     => 'boolean',
				'readonly' => true,
			),
			'timezone'   => array(
				'type'     => 'string',
				'readonly' => true,
			),
			'created_at' => array(
				'type'     => 'string',
				'format'   => 'date-time',
				'readonly' => true,
			),
			'updated_at' => array(
				'type'     => 'string',
				'format'   => 'date-time',
				'readonly' => true,
			),
		);
		if ( ! $this->ticket ) {
			$fields['start_at']['type'] = array( 'string', 'null' );
			$fields['end_at']['type']   = array( 'string', 'null' );
		} else {
			$fields['template_id']['minimum'] = 0;
			$fields['event_id']               = array(
				'type'     => 'integer',
				'readonly' => true,
			);
		}
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => $this->ticket ? 'ticket' : 'event',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $fields,
		);
	}
}
