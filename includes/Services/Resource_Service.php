<?php
/**
 * Authorize and validate again at the mutation boundary.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QuixDevs\TickeraRestAPI\Authorization\Ownership_Policy;
use QuixDevs\TickeraRestAPI\Data\Tickera_Repository;
use QuixDevs\TickeraRestAPI\Support\Validator;
use QuixDevs\TickeraRestAPI\Support\Write_Lock;

/** Resource Service implementation. */
class Resource_Service {
	/**
	 * Serialize related API writes and recheck authorization after acquisition.
	 *
	 * @param bool  $ticket Whether this is a ticket type.
	 * @param array $changes Supplied fields.
	 * @param int   $id Resource ID.
	 * @param int   $event_id Parent event ID.
	 */
	public function save( $ticket, $changes, $id = 0, $event_id = 0 ) {
		$policy = new Ownership_Policy();
		$check  = $policy->gate( $ticket, true );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( $id ) {
			$check = $ticket ? $policy->ticket( $id ) : $policy->event( $id );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$event_id = $ticket ? (int) get_post_meta( $id, 'event_name', true ) : $id;
		} elseif ( $ticket ) {
			$check = $policy->event( $event_id );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		$key   = $event_id ? 'event:' . $event_id : 'creator:' . get_current_user_id();
		$lock  = new Write_Lock();
		$check = $lock->acquire( $key );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		try {
			// Drop stale post/meta state before validating a potentially queued write.
			if ( $id ) {
				clean_post_cache( $id );
			}
			if ( $event_id && $event_id !== $id ) {
				clean_post_cache( $event_id );
			}
			return $this->save_locked( $ticket, $changes, $id, $event_id );
		} finally {
			$lock->release();
		}
	}

	/**
	 * Create or partially update a verified event/ticket.
	 *
	 * @param bool  $ticket Whether this is a ticket type.
	 * @param array $changes Validated supplied fields.
	 * @param int   $id Resource ID.
	 * @param int   $event_id Verified parent event ID.
	 */
	private function save_locked( $ticket, $changes, $id = 0, $event_id = 0 ) {
		$policy     = new Ownership_Policy();
		$repository = new Tickera_Repository();
		$type       = $ticket ? 'tc_tickets' : 'tc_events';
		$changes    = Validator::changes( $changes, $ticket, ! $id );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		$check = $policy->gate( $ticket, true );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( $id ) {
			$existing = $ticket ? $policy->ticket( $id ) : $policy->event( $id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$data = $repository->read( $existing );
		} else {
			$check = $policy->collection( $type, true );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			if ( $ticket ) {
				$check = $policy->event( $event_id );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
			$data = array(
				'title'         => '',
				'status'        => 'draft',
				'start_at'      => null,
				'end_at'        => null,
				'sale_start_at' => null,
				'sale_end_at'   => null,
			);
		}
		if ( isset( $changes['event_id'] ) && $changes['event_id'] !== $event_id ) {
			return Validator::error( __( 'The event_id must match the authorized route event.', 'quixdevs-tickera-rest-api' ) );
		}
		$data   = array_replace( $data, $changes );
		$checks = array( Validator::business( $data, $ticket ) );
		if ( isset( $changes['status'] ) ) {
			$checks[] = $policy->status( $type, $changes['status'] );
		}
		foreach ( array( 'featured_image_id', 'logo_id' ) as $field ) {
			if ( isset( $changes[ $field ] ) ) {
				$checks[] = Validator::image( $changes[ $field ] );
			}
		}
		if ( isset( $changes['category_ids'] ) ) {
			$checks[] = Validator::categories( $changes['category_ids'] );
		}
		if ( isset( $changes['template_id'] ) ) {
			$checks[] = Validator::template( $changes['template_id'] );
		}
		foreach ( $checks as $check ) {
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		// Re-resolve resource and parent immediately before persistence.
		$check = $id ? ( $ticket ? $policy->ticket( $id ) : $policy->event( $id ) ) : ( $ticket ? $policy->event( $event_id ) : $policy->collection( $type, true ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return $repository->save( $type, $changes, $id, $event_id );
	}
}
