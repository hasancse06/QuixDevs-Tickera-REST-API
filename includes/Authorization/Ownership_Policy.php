<?php
/**
 * Strict author-scoped authorization.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Authorization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Dependencies;

/** Ownership Policy implementation. */
class Ownership_Policy {
	/**
	 * Authenticate and verify dependencies and production transport.
	 *
	 * @param bool $tickets Whether standalone tickets are required.
	 * @param bool $write Whether the operation writes data.
	 */
	public function gate( $tickets = false, $write = false ) {
		if ( ! get_current_user_id() ) {
			return new \WP_Error( 'quixdevs_authentication_required', __( 'Authentication is required.', 'quixdevs-tickera-rest-api' ), array( 'status' => 401 ) );
		}
		if ( $write && ! is_ssl() && ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			return new \WP_Error( 'quixdevs_https_required', __( 'Write requests require HTTPS.', 'quixdevs-tickera-rest-api' ), array( 'status' => 403 ) );
		}
		return ( new Dependencies() )->check( $tickets );
	}

	/**
	 * Check the registered CPT's real creation/list capability.
	 *
	 * @param string $type Verified post type.
	 * @param bool   $create Whether this creates a resource.
	 */
	public function collection( $type, $create = false ) {
		$object = get_post_type_object( $type );
		$cap    = $create ? 'create_posts' : 'edit_posts';
		return $object && current_user_can( $object->cap->$cap ) ? true : $this->forbidden();
	}

	/**
	 * Resolve an owned editable event. Admin capabilities never bypass ownership.
	 *
	 * @param int $id Resource ID.
	 */
	public function event( $id ) {
		return $this->owned( $id, 'tc_events' );
	}

	/**
	 * Resolve both the owned ticket and its owned editable parent.
	 *
	 * @param int $id Resource ID.
	 */
	public function ticket( $id ) {
		$ticket = $this->owned( $id, 'tc_tickets' );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}
		$parent = get_post_meta( $id, 'event_name', true );
		if ( ! is_scalar( $parent ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $parent ) ) {
			return $this->hidden();
		}
		$event = $this->event( (int) $parent );
		return is_wp_error( $event ) ? $this->hidden() : $ticket;
	}

	/**
	 * Confirm created-resource ownership without requiring a separate edit grant.
	 *
	 * @param \WP_Post|null $post Created resource, absent if a concurrent hook removed it.
	 * @param bool          $ticket Whether this is a ticket type.
	 */
	public function created( $post, $ticket = false ) {
		$type = $ticket ? 'tc_tickets' : 'tc_events';
		if ( ! $post || $type !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
			return $this->hidden();
		}
		if ( $ticket ) {
			$event = $this->event( (int) get_post_meta( $post->ID, 'event_name', true ) );
			if ( is_wp_error( $event ) ) {
				return $this->hidden();
			}
		}
		return $post;
	}

	/**
	 * Check ownership first, before capability evaluation or returning data.
	 *
	 * @param int    $id Resource ID.
	 * @param string $type Verified post type.
	 */
	private function owned( $id, $type ) {
		$post = get_post( $id );
		if ( ! $post || $type !== $post->post_type || get_current_user_id() !== (int) $post->post_author || ! in_array( $post->post_status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
			return $this->hidden();
		}
		$object = get_post_type_object( $type );
		return $object && current_user_can( $object->cap->edit_post, $post->ID ) ? $post : $this->forbidden();
	}

	/**
	 * Authorize publication separately from editing.
	 *
	 * @param string $type Verified post type.
	 * @param string $status Requested post status.
	 */
	public function status( $type, $status ) {
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( get_post_type_object( $type )->cap->publish_posts ) ) {
			return $this->forbidden();
		}
		return true;
	}

	/** Generic invisible-resource response. */
	public function hidden() {
		return new \WP_Error( 'quixdevs_not_found', __( 'Resource not found.', 'quixdevs-tickera-rest-api' ), array( 'status' => 404 ) );
	}

	/** Generic permission response. */
	public function forbidden() {
		return new \WP_Error( 'quixdevs_forbidden', __( 'You do not have the required capability.', 'quixdevs-tickera-rest-api' ), array( 'status' => 403 ) );
	}
}
