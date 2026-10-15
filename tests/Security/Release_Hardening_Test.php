<?php
/** Regression checks against the genuine WordPress/Tickera runtime. */
class Release_Hardening_Test extends Rest_Test_Case {
 public function test_collection_totals_exclude_uneditable_statuses() {
  $draft = $this->event();
  $this->event( array( 'status' => 'publish' ) );
  $this->event( array( 'status' => 'private' ) );
  $user = new WP_User( $this->a );
  $user->remove_cap( 'edit_published_tc_events' );
  $user->remove_cap( 'edit_private_tc_events' );
  wp_set_current_user( 0 ); wp_set_current_user( $this->a );
  $response = $this->api( 'GET', '/events' );
  $this->assertSame( 200, $response->get_status() );
  $this->assertCount( 1, $response->get_data() );
  $this->assertSame( $draft, $response->get_data()[0]['id'] );
  $this->assertSame( '1', $response->get_headers()['X-WP-Total'] );
  $this->assertSame( 403, $this->api( 'GET', '/events', null, array( 'status' => 'private' ) )->get_status() );
 }
 public function test_service_rejects_mass_assignment_without_rest_controller() {
  $id = $this->event();
  $service = new \QuixDevs\TickeraRestAPI\Services\Resource_Service();
  foreach ( array( array( 'post_author' => $this->b ), array( 'meta_input' => array( 'secret' => 'x' ) ), array( 'title' => array() ), array() ) as $changes ) {
   $result = $service->save( false, $changes, $id );
   $this->assertWPError( $result );
   $this->assertSame( 400, $result->get_error_data()['status'] );
  }
  $this->assertSame( $this->a, (int) get_post( $id )->post_author );
 }
 private function assertWPError( $result ) { $this->assertInstanceOf( WP_Error::class, $result ); }
 public function test_conflicting_connection_returns_409_and_releases_lock() {
  global $wpdb;
  $id = $this->event();
  $other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
  $name = 'qtra-' . substr( hash( 'sha256', $wpdb->prefix . ':event:' . $id ), 0, 59 );
  $this->assertSame( '1', $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) );
  try {
   $this->assertSame( 409, $this->api( 'PATCH', '/events/' . $id, array( 'title' => 'Blocked' ) )->get_status() );
  } finally { $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); $other->close(); }
  $this->assertSame( 200, $this->api( 'PATCH', '/events/' . $id, array( 'title' => 'Allowed' ) )->get_status() );
  $this->assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $name ) ) );
 }
 public function test_namespace_has_only_eight_management_operations() {
  $actual = array();
  foreach ( $this->server->get_routes() as $route => $endpoints ) {
   if ( 0 !== strpos( $route, '/quixdevs-tickera/v1/' ) ) { continue; }
   foreach ( $endpoints as $endpoint ) {
    foreach ( $endpoint['methods'] as $method => $enabled ) {
     if ( $enabled && 'HEAD' !== $method ) { $actual[] = $method; }
    }
   }
  }
  $this->assertCount( 8, $actual );
  $this->assertSame( array( 'GET', 'POST', 'GET', 'PATCH', 'GET', 'POST', 'GET', 'PATCH' ), $actual );
 }
 public function test_native_tickera_fields_render_api_created_and_updated_values() {
  $event = $this->event( array( 'title' => 'Native QA conference', 'status' => 'publish', 'location' => 'Dhaka QA hall' ) );
  $ticket = $this->ticket( $event, array( 'quantity' => 25 ) );
  ob_start(); \Tickera\Addons\tickera_render_metabox( $event, 'event_location' ); $location = ob_get_clean();
  $this->assertStringContainsString( 'Dhaka QA hall', $location );
  ob_start(); \Tickera\Addons\tickera_render_metabox( $event, 'event_date_time' ); $date = ob_get_clean();
  $this->assertStringContainsString( '2027', $date );
  ob_start(); \Tickera\Addons\tickera_render_ticket_type_metabox( $ticket, 'price_per_ticket' ); $price = ob_get_clean();
  $this->assertStringContainsString( '50.00', $price );
  ob_start(); \Tickera\Addons\tickera_render_ticket_type_metabox( $ticket, 'quantity_available' ); $quantity = ob_get_clean();
  $this->assertStringContainsString( '25', $quantity );
  ob_start(); \Tickera\Addons\tickera_render_ticket_type_metabox( $ticket, 'event_name' ); $parent = ob_get_clean();
  $this->assertStringContainsString( 'Native QA conference', $parent );
  $this->assertSame( 200, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'price' => '61.25' ) )->get_status() );
  ob_start(); \Tickera\Addons\tickera_render_ticket_type_metabox( $ticket, 'price_per_ticket' ); $price = ob_get_clean();
  $this->assertStringContainsString( '61.25', $price );
 }
}
