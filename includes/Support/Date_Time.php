<?php
/**
 * Site-local date conversion, preserving Tickera wall-clock semantics.
 *
 * @package QuixDevs\TickeraRestAPI
 */

namespace QuixDevs\TickeraRestAPI\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Date Time implementation. */
class Date_Time {
	/**
	 * Accept minute-precision site-local ISO dates only.
	 *
	 * @param mixed $value Value to validate or convert.
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D', $value ) ) {
			return false;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value || (int) $date->format( 'Y' ) < 1970 ) {
			return false;
		}
		// Refuse ambiguous repeated local times at a daylight-saving transition.
		$transitions = wp_timezone()->getTransitions( $date->getTimestamp() - 86400, $date->getTimestamp() + 86400 );
		if ( $transitions ) {
			$previous = $transitions[0]['offset'];
			$wall     = strtotime( str_replace( 'T', ' ', $value ) . ' UTC' );
			foreach ( $transitions as $transition ) {
				if ( $transition['offset'] < $previous && $wall >= $transition['ts'] + $transition['offset'] && $wall < $transition['ts'] + $previous ) {
					return false;
				}
				$previous = $transition['offset'];
			}
		}
		return $date;
	}

	/**
	 * Convert verified Tickera storage; preserve meaning without UTC assumptions.
	 *
	 * @param mixed $value Value to validate or convert.
	 */
	public static function external( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}(:00)?$/D', $value ) ) {
			return null;
		}
		$local = str_replace( ' ', 'T', substr( $value, 0, 16 ) );
		return self::parse( $local ) ? $local : null;
	}

	/**
	 * Normalize a validated date to Tickera's datepicker storage.
	 *
	 * @param mixed $value Value to validate or convert.
	 */
	public static function storage( $value ) {
		return str_replace( 'T', ' ', $value );
	}
}
