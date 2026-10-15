<?php
/**
 * Verified Tickera persistence mappings.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Support\Date_Time;
use QuixDevs\TickeraRestAPI\Support\Validator;

/** Tickera Repository implementation. */
class Tickera_Repository {
	const EVENT_META = array(
		'start_at' => 'event_date_time',
		'end_at'   => 'event_end_date_time',
		'location' => 'event_location',
		'terms'    => 'event_terms',
	);

	/**
	 * Format only documented fields; never enumerate private metadata.
	 *
	 * @param \WP_Post $post Verified resource.
	 */
	public function read( $post ) {
		$ticket = 'tc_tickets' === $post->post_type;
		$data   = array(
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'description' => wp_kses_post( $post->post_content ),
			'status'      => $post->post_status,
			'active'      => 'publish' === $post->post_status,
			'author_id'   => (int) $post->post_author,
			'timezone'    => wp_timezone_string(),
			'created_at'  => mysql_to_rfc3339( $post->post_date_gmt ),
			'updated_at'  => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
		// WordPress stores *_gmt as UTC. Make the timezone explicit to consumers.
		$data['created_at'] .= 'Z';
		$data['updated_at'] .= 'Z';
		if ( $ticket ) {
			$quantity = get_post_meta( $post->ID, 'quantity_available', true );
			$range    = 'range' === get_post_meta( $post->ID, '_ticket_availability', true );
			$data    += array(
				'event_id'      => (int) get_post_meta( $post->ID, 'event_name', true ),
				'price'         => (string) get_post_meta( $post->ID, 'price_per_ticket', true ),
				'quantity'      => '' === $quantity ? null : (int) $quantity,
				'sale_start_at' => $range ? Date_Time::external( get_post_meta( $post->ID, '_ticket_availability_from_date', true ) ) : null,
				'sale_end_at'   => $range ? Date_Time::external( get_post_meta( $post->ID, '_ticket_availability_to_date', true ) ) : null,
				'template_id'   => (int) get_post_meta( $post->ID, 'ticket_template', true ),
			);
		} else {
			foreach ( self::EVENT_META as $key => $meta ) {
				$value        = get_post_meta( $post->ID, $meta, true );
				$data[ $key ] = in_array( $key, array( 'start_at', 'end_at' ), true ) ? Date_Time::external( $value ) : ( 'terms' === $key ? wp_kses_post( $value ) : (string) $value );
			}
			$categories                = wp_get_object_terms( $post->ID, 'event_category', array( 'fields' => 'ids' ) );
			$data['category_ids']      = is_wp_error( $categories ) ? array() : array_map( 'intval', $categories );
			$data['featured_image_id'] = (int) get_post_thumbnail_id( $post->ID );
			$logo                      = get_post_meta( $post->ID, 'event_logo_file_url', true );
			$data['logo_id']           = $logo ? attachment_url_to_postid( $logo ) : 0;
		}
		return $data;
	}

	/**
	 * Persist only supplied fields with metadata before WordPress save hooks.
	 *
	 * @param string $type Verified post type.
	 * @param array  $changes Validated supplied fields.
	 * @param int    $id Resource ID.
	 * @param int    $event_id Verified parent event ID.
	 */
	public function save( $type, $changes, $id = 0, $event_id = 0 ) {
		$post = array( 'post_type' => $type );
		if ( $id ) {
			$post['ID'] = $id;
		} else {
			$post['post_author']    = get_current_user_id();
			$post['post_status']    = $changes['status'] ?? 'draft';
			$post['comment_status'] = 'closed';
			$post['ping_status']    = 'closed';
		}
		foreach ( array(
			'title'       => 'post_title',
			'description' => 'post_content',
			'status'      => 'post_status',
		) as $field => $column ) {
			if ( array_key_exists( $field, $changes ) ) {
				$post[ $column ] = $changes[ $field ];
			}
		}
		$meta = array();
		if ( 'tc_events' === $type ) {
			foreach ( self::EVENT_META as $field => $key ) {
				if ( array_key_exists( $field, $changes ) ) {
					$meta[ $key ] = in_array( $field, array( 'start_at', 'end_at' ), true ) ? Date_Time::storage( $changes[ $field ] ) : $changes[ $field ];
				}
			}
			if ( array_key_exists( 'featured_image_id', $changes ) ) {
				$meta['_thumbnail_id'] = $changes['featured_image_id'];
			}
			if ( array_key_exists( 'logo_id', $changes ) ) {
				$meta['event_logo_file_url'] = $changes['logo_id'] ? wp_get_attachment_url( $changes['logo_id'] ) : '';
			}
		} else {
			if ( ! $id ) {
				$meta['event_name']           = $event_id;
				$meta['quantity_available']   = '';
				$meta['_ticket_availability'] = 'open_ended';
			}
			foreach ( array(
				'price'       => 'price_per_ticket',
				'quantity'    => 'quantity_available',
				'template_id' => 'ticket_template',
			) as $field => $key ) {
				if ( array_key_exists( $field, $changes ) ) {
					$meta[ $key ] = 'price' === $field ? Validator::price( $changes[ $field ] ) : ( null === $changes[ $field ] ? '' : $changes[ $field ] );
				}
			}
			if ( array_key_exists( 'sale_start_at', $changes ) || array_key_exists( 'sale_end_at', $changes ) ) {
				$old                                    = $id ? $this->read( get_post( $id ) ) : array(
					'sale_start_at' => null,
					'sale_end_at'   => null,
				);
				$dates                                  = array_replace( $old, $changes );
				$meta['_ticket_availability']           = null === $dates['sale_start_at'] ? 'open_ended' : 'range';
				$meta['_ticket_availability_from_date'] = null === $dates['sale_start_at'] ? '' : Date_Time::storage( $dates['sale_start_at'] );
				$meta['_ticket_availability_to_date']   = null === $dates['sale_end_at'] ? '' : Date_Time::storage( $dates['sale_end_at'] );
			}
		}
		$post['meta_input'] = $meta;
		// Tickera's admin hooks parse the ambient form payload without a REST schema.
		// Isolate that payload while retaining the real save_post lifecycle.
	 // phpcs:disable WordPress.Security.NonceVerification.Missing
		$form  = $_POST;
		$_POST = array();
		try {
			$saved = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
		} catch ( \Throwable $error ) {
			return $this->failure();
		} finally {
			$_POST = $form;
		}
	 // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( is_wp_error( $saved ) ) {
			return $this->failure();
		}
		if ( isset( $changes['category_ids'] ) ) {
			$terms = wp_set_object_terms( $saved, array_map( 'intval', $changes['category_ids'] ), 'event_category', false );
			if ( is_wp_error( $terms ) ) {
				return $this->failure();
			}
		}
		// Detect failed metadata writes; WordPress post/meta operations are not atomic.
		foreach ( $meta as $key => $value ) {
			if ( (string) get_post_meta( $saved, $key, true ) !== (string) $value ) {
				return $this->failure();
			}
		}
		$resource = get_post( $saved );
		return $resource instanceof \WP_Post ? $resource : $this->failure();
	}

	/**
	 * Bounded query with server-controlled ownership and event association.
	 *
	 * @param string           $type Verified post type.
	 * @param \WP_REST_Request $request Current REST request.
	 * @param int              $event_id Verified parent event ID.
	 */
	public function query( $type, $request, $event_id = 0 ) {
		$object   = get_post_type_object( $type );
		$statuses = array( 'draft', 'pending' );
		foreach ( array(
			'publish' => 'edit_published_posts',
			'private' => 'edit_private_posts',
		) as $status => $capability ) {
			if ( current_user_can( $object->cap->$capability ) ) {
				$statuses[] = $status;
			}
		}
		if ( isset( $request['status'] ) && ! in_array( $request['status'], $statuses, true ) ) {
			return new \WP_Error( 'quixdevs_forbidden', __( 'You cannot list this status.', 'quixdevs-tickera-rest-api' ), array( 'status' => 403 ) );
		}
		$args = array(
			'post_type'              => $type,
			'author'                 => get_current_user_id(),
			'post_status'            => $request['status'] ?? $statuses,
			'posts_per_page'         => $request['per_page'] ?? 20,
			'paged'                  => $request['page'] ?? 1,
			's'                      => $request['search'] ?? '',
			'orderby'                => $request['orderby'] ?? 'date',
			'order'                  => $request['order'] ?? 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => 'tc_events' === $type,
		);
		if ( $event_id ) {
		 // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Tickera's verified relationship uses postmeta; query remains paginated and author-filtered.
			$args['meta_query'] = array(
				array(
					'key'     => 'event_name',
					'value'   => (string) $event_id,
					'compare' => '=',
				),
			);
		}
		return new \WP_Query( $args );
	}

	/** Avoid leaking database errors to clients. */
	private function failure() {
		return new \WP_Error( 'quixdevs_persistence_failed', __( 'The resource could not be saved. Retrieve it before retrying.', 'quixdevs-tickera-rest-api' ), array( 'status' => 500 ) );
	}
}
