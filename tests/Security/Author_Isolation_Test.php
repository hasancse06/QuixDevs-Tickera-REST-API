<?php
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
class Author_Isolation_Test extends Rest_Test_Case {
 public function test_two_authors_are_isolated_for_all_operations(): void {
  $ea = $this->event( array( 'status' => 'private' ) );
  $ta = $this->ticket( $ea, array( 'status' => 'private' ) );
  wp_set_current_user( $this->b );
  $eb = $this->event( array( 'status' => 'private' ) );
  $tb = $this->ticket( $eb, array( 'status' => 'private' ) );
  foreach ( array( array( $this->a, $ea, $ta, $eb, $tb ), array( $this->b, $eb, $tb, $ea, $ta ) ) as $case ) {
   list( $user, $own_event, $own_ticket, $other_event, $other_ticket ) = $case;
   wp_set_current_user( $user );
   foreach ( array( '/events/' . $own_event, '/tickets/' . $own_ticket ) as $route ) {
    $this->assertSame( 200, $this->api( 'GET', $route )->get_status() );
    $this->assertSame( 200, $this->api( 'PATCH', $route, array( 'title' => 'Updated by owner' ) )->get_status() );
   }
   foreach ( array( '/events/' . $other_event, '/tickets/' . $other_ticket ) as $route ) {
    $this->assertSame( 404, $this->api( 'GET', $route )->get_status() );
    $this->assertSame( 404, $this->api( 'PATCH', $route, array( 'title' => 'Attack' ) )->get_status() );
   }
   $this->assertSame( 404, $this->api( 'GET', '/events/' . $other_event . '/tickets' )->get_status() );
   $this->assertSame( 404, $this->api( 'POST', '/events/' . $other_event . '/tickets', array( 'title' => 'Attack', 'price' => '1' ) )->get_status() );
   $this->assertSame( 400, $this->api( 'PATCH', '/tickets/' . $own_ticket, array( 'event_id' => $other_event ) )->get_status() );
   foreach ( array( 1, 2, 99 ) as $page ) {
    $list = $this->api( 'GET', '/events', null, array( 'per_page' => 1, 'page' => $page ) );
    foreach ( $list->get_data() as $item ) {
     $this->assertSame( $user, $item['author_id'] );
    }
   }
   $list = $this->api( 'GET', '/events/' . $own_event . '/tickets' );
   $this->assertSame( array( $own_ticket ), array_column( $list->get_data(), 'id' ) );
  }
 }

 /** @dataProvider forged_fields */
 public function test_forged_fields_are_rejected( $field, $value ): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  $this->assertSame( 400, $this->api( 'POST', '/events', array( 'title' => 'Attack', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00', $field => $value ) )->get_status() );
  $this->assertSame( 400, $this->api( 'PATCH', '/events/' . $event, array( $field => $value ) )->get_status() );
  $this->assertSame( 400, $this->api( 'PATCH', '/tickets/' . $ticket, array( $field => $value ) )->get_status() );
  $this->assertSame( $this->a, (int) get_post( $event )->post_author );
  $this->assertSame( $event, (int) get_post_meta( $ticket, 'event_name', true ) );
 }
 public static function forged_fields(): array {
  return array( array( 'author_id', 999 ), array( 'post_author', 999 ), array( 'user_id', 999 ), array( 'created_by', 999 ), array( 'meta', array( 'api_key' => 'attack' ) ), array( 'meta_input', array( 'event_name' => 999 ) ), array( 'event_name', 999 ), array( 'quantity_sold', 999 ), array( 'api_key', 'attack' ), array( 'active', true ), array( 'currency', 'USD' ), array( 'id', 999 ) );
 }

 public function test_unauthenticated_and_underprivileged_users_are_rejected(): void {
  wp_set_current_user( 0 );
  foreach ( array( '/events', '/events/1', '/events/1/tickets', '/tickets/1' ) as $path ) {
   $this->assertSame( 401, $this->api( 'GET', $path )->get_status() );
  }
  $this->assertSame( 401, $this->api( 'POST', '/events', array( 'title' => 'No', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00' ) )->get_status() );
  $user = new WP_User( $this->a );
  $user->remove_cap( 'create_tc_events' );
  $user->remove_cap( 'edit_tc_events' );
  wp_set_current_user( 0 );
  wp_set_current_user( $this->a );
  $this->assertSame( 403, $this->api( 'GET', '/events' )->get_status() );
  $this->assertSame( 403, $this->api( 'POST', '/events', array( 'title' => 'No', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00' ) )->get_status() );
 }

 public function test_admin_and_editor_do_not_bypass_ownership(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  foreach ( array( 'administrator', 'editor' ) as $role ) {
   ( new WP_User( $this->b ) )->set_role( $role );
   wp_set_current_user( $this->b );
   $this->assertSame( 404, $this->api( 'PATCH', '/events/' . $event, array( 'title' => 'Attack' ) )->get_status() );
   $this->assertSame( 404, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'title' => 'Attack' ) )->get_status() );
  }
 }

 public function test_invalid_parents_wrong_types_and_mixed_ownership(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  $this->assertSame( 400, $this->api( 'POST', '/events/' . $event . '/tickets', array( 'title' => 'No', 'price' => '1', 'event_id' => 999999 ) )->get_status() );
  $this->assertSame( 404, $this->api( 'GET', '/events/' . $ticket )->get_status() );
  $this->assertSame( 404, $this->api( 'GET', '/tickets/' . $event )->get_status() );
  update_post_meta( $ticket, 'event_name', 999999 );
  $this->assertSame( 404, $this->api( 'GET', '/tickets/' . $ticket )->get_status() );
  wp_set_current_user( $this->b );
  $other_event = $this->event();
  update_post_meta( $ticket, 'event_name', $other_event );
  wp_set_current_user( 0 );
  wp_set_current_user( $this->a );
  $this->assertSame( 404, $this->api( 'GET', '/tickets/' . $ticket )->get_status() );
  update_post_meta( $ticket, 'event_name', $event );
  wp_update_post( array( 'ID' => $ticket, 'post_author' => $this->b ) );
  $this->assertSame( array(), $this->api( 'GET', '/events/' . $event . '/tickets' )->get_data() );
 }

 public function test_publication_requires_publish_capabilities(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  $user = new WP_User( $this->a );
  $user->remove_cap( 'publish_tc_events' );
  $user->remove_cap( 'publish_tc_tickets' );
  wp_set_current_user( 0 );
  wp_set_current_user( $this->a );
  foreach ( array( '/events/' . $event, '/tickets/' . $ticket ) as $route ) {
   foreach ( array( 'publish', 'private' ) as $status ) {
    $this->assertSame( 403, $this->api( 'PATCH', $route, array( 'status' => $status ) )->get_status() );
   }
  }
  $this->event();
 }

 public function test_query_override_private_metadata_and_form_injection(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  update_post_meta( $event, 'api_key', 'private-value' );
  update_post_meta( $event, 'show_tickets_automatically', 1 );
  update_post_meta( $event, 'hide_event_after_expiration', 1 );
  foreach ( array( 'author', 'author_id', 'meta_query', 'post_status', 'post_type', 'offset' ) as $field ) {
   $this->assertSame( 400, $this->api( 'GET', '/events', null, array( $field => $this->b ) )->get_status() );
  }
  $_POST = array( 'api_key_post_meta' => 'injected', 'event_name_post_meta' => 999 );
  $this->assertSame( 200, $this->api( 'PATCH', '/events/' . $event, array( 'title' => 'Preserve metadata' ) )->get_status() );
  $this->assertSame( 'private-value', get_post_meta( $event, 'api_key', true ) );
  $this->assertSame( '1', get_post_meta( $event, 'show_tickets_automatically', true ) );
  $this->assertSame( '1', get_post_meta( $event, 'hide_event_after_expiration', true ) );
  $this->assertSame( 200, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'title' => 'Safe' ) )->get_status() );
  $this->assertSame( $event, (int) get_post_meta( $ticket, 'event_name', true ) );
  $json = wp_json_encode( $this->api( 'GET', '/events/' . $event )->get_data() );
  $this->assertStringNotContainsString( 'private-value', $json );
  $this->assertStringNotContainsString( 'api_key', $json );
 }

 public function test_production_https_is_enforced(): void {
  putenv( 'WP_ENVIRONMENT_TYPE=production' );
  $_SERVER['HTTPS'] = '';
  $body = array( 'title' => 'TLS', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00' );
  $this->assertSame( 403, $this->api( 'POST', '/events', $body )->get_status() );
  $_SERVER['HTTPS'] = 'on';
  $this->assertSame( 201, $this->api( 'POST', '/events', $body )->get_status() );
 }
}
