<?php
/**
 * Shared REST schemas and business rules.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validator implementation. */
class Validator {
	/**
	 * Explicit write-field allowlist and WordPress REST schemas.
	 *
	 * @param bool $ticket Whether this is a ticket type.
	 * @param bool $create Whether this creates a resource.
	 */
	public static function fields( $ticket = false, $create = false ) {
		$fields = array(
			'title'       => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 200,
				'required'  => $create,
			),
			'description' => array(
				'type'      => 'string',
				'maxLength' => 100000,
			),
			'status'      => array(
				'type' => 'string',
				'enum' => array( 'draft', 'pending', 'publish', 'private' ),
			),
		);
		if ( $ticket ) {
			$fields += array(
				'price'         => array(
					'type'     => 'string',
					'pattern'  => '^(0|[1-9][0-9]{0,7})(\.[0-9]{1,2})?$(?!\n)',
					'required' => $create,
				),
				'quantity'      => array(
					'type'    => array( 'integer', 'null' ),
					'minimum' => 0,
					'maximum' => 2147483647,
				),
				'sale_start_at' => array( 'type' => array( 'string', 'null' ) ),
				'sale_end_at'   => array( 'type' => array( 'string', 'null' ) ),
				'template_id'   => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			);
			if ( $create ) {
				$fields['event_id'] = array(
					'type'    => 'integer',
					'minimum' => 1,
				);
			}
		} else {
			$fields += array(
				'start_at'          => array(
					'type'     => 'string',
					'required' => $create,
				),
				'end_at'            => array(
					'type'     => 'string',
					'required' => $create,
				),
				'location'          => array(
					'type'      => 'string',
					'maxLength' => 2000,
				),
				'terms'             => array(
					'type'      => 'string',
					'maxLength' => 100000,
				),
				'category_ids'      => array(
					'type'        => 'array',
					'maxItems'    => 100,
					'uniqueItems' => true,
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'featured_image_id' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'logo_id'           => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
			);
		}
		foreach ( $fields as $key => &$schema ) {
			$schema['validate_callback'] = array( self::class, 'validate_argument' );
			$schema['sanitize_callback'] = array( self::class, 'sanitize_argument' );
		}
		return $fields;
	}

	/**
	 * WordPress schema validation with date rules.
	 *
	 * @param mixed            $value Value to validate or convert.
	 * @param \WP_REST_Request $request Current REST request.
	 * @param string           $key Schema field name.
	 */
	public static function validate_argument( $value, $request, $key ) {
		$result = rest_validate_request_arg( $value, $request, $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( in_array( $key, array( 'start_at', 'end_at', 'sale_start_at', 'sale_end_at' ), true ) && null !== $value && ! Date_Time::parse( $value ) ) {
			return self::error( __( 'Dates must be valid, unambiguous site-local YYYY-MM-DDTHH:MM values.', 'quixdevs-tickera-rest-api' ) );
		}
		return true;
	}

	/**
	 * Sanitize rich content, plain text, and validated primitive values.
	 *
	 * @param mixed            $value Value to validate or convert.
	 * @param \WP_REST_Request $request Current REST request.
	 * @param string           $key Schema field name.
	 */
	public static function sanitize_argument( $value, $request, $key ) {
		if ( in_array( $key, array( 'description', 'terms' ), true ) ) {
			return wp_kses_post( $value );
		}
		if ( in_array( $key, array( 'title', 'location', 'search' ), true ) ) {
			return sanitize_text_field( $value );
		}
		return rest_sanitize_request_arg( $value, $request, $key );
	}

	/**
	 * Apply the shared REST schema to direct service callers as well.
	 *
	 * @param array $changes Supplied fields.
	 * @param bool  $ticket Whether this is a ticket type.
	 * @param bool  $create Whether this creates a resource.
	 */
	public static function changes( $changes, $ticket, $create ) {
		$request = new \WP_REST_Request( $create ? 'POST' : 'PATCH' );
		$fields  = self::fields( $ticket, $create );
		$request->set_attributes( array( 'args' => $fields ) );
		if ( ! $changes || array_diff_key( $changes, $fields ) ) {
			return self::error( __( 'Supply only documented writable fields.', 'quixdevs-tickera-rest-api' ) );
		}
		foreach ( $fields as $key => $schema ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				if ( ! empty( $schema['required'] ) ) {
					return self::error( __( 'A required field is missing.', 'quixdevs-tickera-rest-api' ) );
				}
				continue;
			}
			$valid = self::validate_argument( $changes[ $key ], $request, $key );
			if ( is_wp_error( $valid ) ) {
				$valid->add_data( array( 'status' => 400 ) );
				return $valid;
			}
			$changes[ $key ] = self::sanitize_argument( $changes[ $key ], $request, $key );
		}
		return $changes;
	}

	/**
	 * Validate the combined old/new data before any mutation.
	 *
	 * @param array $data Combined existing and supplied fields.
	 * @param bool  $ticket Whether this is a ticket type.
	 */
	public static function business( $data, $ticket = false ) {
		if ( '' === trim( $data['title'] ) ) {
			return self::error( __( 'Title must contain text.', 'quixdevs-tickera-rest-api' ) );
		}
		$start = $ticket ? 'sale_start_at' : 'start_at';
		$end   = $ticket ? 'sale_end_at' : 'end_at';
		if ( $ticket && ( null === $data[ $start ] ) !== ( null === $data[ $end ] ) ) {
			return self::error( __( 'Supply both sale dates or set both to null for open-ended sales.', 'quixdevs-tickera-rest-api' ), 422 );
		}
		if ( null !== $data[ $start ] || null !== $data[ $end ] ) {
			$from = Date_Time::parse( $data[ $start ] );
			$to   = Date_Time::parse( $data[ $end ] );
			if ( ! $from || ! $to || $to < $from ) {
				return self::error( __( 'A valid end date must be on or after the start date.', 'quixdevs-tickera-rest-api' ), 422 );
			}
		} elseif ( ! $ticket ) {
			return self::error( __( 'Start and end dates are required.', 'quixdevs-tickera-rest-api' ), 422 );
		}
		return true;
	}

	/**
	 * Validate attachment ownership, media type and editing permission.
	 *
	 * @param int $id Resource ID.
	 */
	public static function image( $id ) {
		if ( 0 === $id ) {
			return true;
		}
		$post = get_post( $id );
		return $post && 'attachment' === $post->post_type && get_current_user_id() === (int) $post->post_author && wp_attachment_is_image( $id ) && current_user_can( 'edit_post', $id ) && wp_get_attachment_url( $id )
		? true : self::error( __( 'Image must be an existing image attachment you own and can edit.', 'quixdevs-tickera-rest-api' ), 403 );
	}

	/**
	 * Validate taxonomy assignment via the registered taxonomy capability.
	 *
	 * @param int[] $ids Existing term IDs.
	 */
	public static function categories( $ids ) {
		$taxonomy = get_taxonomy( 'event_category' );
		if ( ! $taxonomy || ! is_object_in_taxonomy( 'tc_events', 'event_category' ) || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return self::error( __( 'You cannot assign event categories.', 'quixdevs-tickera-rest-api' ), 403 );
		}
		foreach ( $ids as $id ) {
			if ( ! term_exists( $id, 'event_category' ) ) {
				return self::error( __( 'Category IDs must identify existing event categories.', 'quixdevs-tickera-rest-api' ) );
			}
		}
		return true;
	}

	/**
	 * Require an owned template and Tickera template-management capability.
	 *
	 * @param int $id Resource ID.
	 */
	public static function template( $id ) {
		$post = get_post( $id );
		return $post && 'tc_templates' === $post->post_type && 'publish' === $post->post_status && get_current_user_id() === (int) $post->post_author && current_user_can( 'manage_ticket_templates_cap' )
		? true : self::error( __( 'Template must be published, owned by you, and assignable with the Tickera template capability.', 'quixdevs-tickera-rest-api' ), 403 );
	}

	/**
	 * Normalize decimal strings without floating-point arithmetic.
	 *
	 * @param mixed $value Value to validate or convert.
	 */
	public static function price( $value ) {
		$parts = explode( '.', $value, 2 );
		return $parts[0] . '.' . str_pad( $parts[1] ?? '', 2, '0' );
	}

	/**
	 * Safe validation error.
	 *
	 * @param string $message Safe error message.
	 * @param int    $status HTTP status code.
	 */
	public static function error( $message, $status = 400 ) {
		return new \WP_Error( 'quixdevs_invalid_request', $message, array( 'status' => $status ) );
	}
}
