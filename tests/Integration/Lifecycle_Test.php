<?php
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
class Lifecycle_Test extends Rest_Test_Case {
 public function test_event_ticket_lifecycle_and_tickera_recognition(): void {
  $event = $this->event( array( 'description' => '<p>Welcome</p>', 'location' => 'Dhaka', 'terms' => '<p>Terms</p>' ) );
  $this->assertSame( 'draft', get_post_status( $event ) );
  $this->assertSame( $this->a, (int) get_post( $event )->post_author );
  $this->assertSame( '2027-01-14 09:00', get_post_meta( $event, 'event_date_time', true ) );
  $this->assertSame( (string) $event, get_post_meta( $event, 'event_presentation_page', true ) );
  $format = array( 'all' => array( wp_json_encode( $event ) ) );
  $keys = new \Tickera\TC_API_Keys_Search( '', '', maybe_serialize( $format ) );
  $this->assertCount( 1, $keys->get_results() );
  $key_id = $keys->get_results()[0]->ID;
  $secret = get_post_meta( $key_id, 'api_key', true );
  $this->assertNotEmpty( $secret );
  $response = $this->api( 'PATCH', '/events/' . $event, array( 'title' => 'Published conference', 'status' => 'publish' ) );
  $this->assertSame( 200, $response->get_status() );
  $this->assertTrue( $response->get_data()['active'] );
  $this->assertSame( 'Dhaka', $response->get_data()['location'] );
  $this->assertCount( 1, ( new \Tickera\TC_API_Keys_Search( '', '', maybe_serialize( $format ) ) )->get_results() );
  $this->assertSame( $secret, get_post_meta( $key_id, 'api_key', true ) );
  $this->assertSame( 'Published conference', get_post_meta( $key_id, 'api_key_name', true ) );
  $ticket = $this->ticket( $event, array( 'price' => '0.1', 'quantity' => null, 'sale_start_at' => '2027-01-01T09:00', 'sale_end_at' => '2027-01-14T09:00' ) );
  $this->assertSame( '0.10', get_post_meta( $ticket, 'price_per_ticket', true ) );
  $this->assertSame( '', get_post_meta( $ticket, 'quantity_available', true ) );
  $this->assertSame( 'range', get_post_meta( $ticket, '_ticket_availability', true ) );
  $this->assertContains( $ticket, ( new \Tickera\TC_Event( $event ) )->get_event_ticket_types() );
  $tickera_ticket = new \Tickera\TC_Ticket( $ticket );
  $this->assertSame( 9999, $tickera_ticket->get_tickets_quantity_left() );
  $this->assertSame( 'Published conference', ( new \Tickera\TC_Event( $event ) )->details->post_title );
  $updated = $this->api( 'PATCH', '/tickets/' . $ticket, array( 'quantity' => 0, 'status' => 'publish', 'sale_start_at' => null, 'sale_end_at' => null ) );
  $this->assertSame( 200, $updated->get_status() );
  $this->assertSame( 0, $updated->get_data()['quantity'] );
  $this->assertTrue( $updated->get_data()['active'] );
  $this->assertSame( 'open_ended', get_post_meta( $ticket, '_ticket_availability', true ) );
  $this->assertSame( '0.10', ( new \Tickera\TC_Ticket( $ticket ) )->details->price_per_ticket );
 }

 public function test_pagination_and_search(): void {
  $first = $this->event( array( 'title' => 'Alpha' ) );
  $second = $this->event( array( 'title' => 'Bravo' ) );
  $result = $this->api( 'GET', '/events', null, array( 'per_page' => 1, 'page' => 2, 'orderby' => 'ID', 'order' => 'ASC' ) );
  $this->assertSame( array( $second ), array_column( $result->get_data(), 'id' ) );
  $this->assertSame( '2', $result->get_headers()['X-WP-Total'] );
  $this->assertSame( '2', $result->get_headers()['X-WP-TotalPages'] );
  $result = $this->api( 'GET', '/events', null, array( 'search' => 'Alpha' ) );
  $this->assertSame( array( $first ), array_column( $result->get_data(), 'id' ) );
 }

 /** @dataProvider invalid_event_data */
 public function test_invalid_event_fields( $body ): void {
  $response = $this->api( 'POST', '/events', array_replace( array( 'title' => 'Test', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00' ), $body ) );
  $this->assertContains( $response->get_status(), array( 400, 422 ) );
 }
 public static function invalid_event_data(): array {
  return array( array( array( 'title' => '' ) ), array( array( 'title' => '   ' ) ), array( array( 'title' => '<script></script>' ) ), array( array( 'title' => str_repeat( 'x', 201 ) ) ), array( array( 'start_at' => '2027-02-30T09:00' ) ), array( array( 'start_at' => '2027-01-14T09:00Z' ) ), array( array( 'end_at' => '2027-01-14T08:00' ) ), array( array( 'status' => 'trash' ) ) );
 }

 /** @dataProvider invalid_ticket_data */
 public function test_invalid_ticket_fields( $body ): void {
  $event = $this->event();
  $response = $this->api( 'POST', '/events/' . $event . '/tickets', array_replace( array( 'title' => 'VIP', 'price' => '1.00' ), $body ) );
  $this->assertContains( $response->get_status(), array( 400, 422 ) );
 }
 public static function invalid_ticket_data(): array {
  return array( array( array( 'price' => '-1' ) ), array( array( 'price' => "50.00\n" ) ), array( array( 'price' => 12.34 ) ), array( array( 'price' => '1.234' ) ), array( array( 'price' => '1e3' ) ), array( array( 'price' => '100000000' ) ), array( array( 'quantity' => -1 ) ), array( array( 'quantity' => 1.5 ) ), array( array( 'quantity' => 2147483648 ) ), array( array( 'sale_start_at' => '2027-01-01T09:00' ) ), array( array( 'sale_start_at' => '2027-01-14T10:00', 'sale_end_at' => '2027-01-14T09:00' ) ) );
 }

 public function test_json_missing_fields_invalid_id_and_empty_patch(): void {
  $request = new WP_REST_Request( 'POST', '/quixdevs-tickera/v1/events' );
  $request->set_header( 'Content-Type', 'application/json' );
  $request->set_body( '{bad' );
  $this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
  $this->assertSame( 400, $this->api( 'POST', '/events', array( 'title' => 'Missing dates' ) )->get_status() );
  $event = $this->event();
  $this->assertSame( 400, $this->api( 'PATCH', '/events/' . $event, array() )->get_status() );
  $this->assertSame( 404, $this->api( 'GET', '/events/0' )->get_status() );
  foreach ( array( 0, 101 ) as $page_size ) {
   $this->assertSame( 400, $this->api( 'GET', '/events', null, array( 'per_page' => $page_size ) )->get_status() );
  }
  $request->set_body( '[]' );
  $this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
 }

 public function test_categories_images_and_templates_enforce_authorization(): void {
  $event = $this->event();
  $term = wp_insert_term( 'Conference', 'event_category' );
  $this->assertSame( 403, $this->api( 'PATCH', '/events/' . $event, array( 'category_ids' => array( $term['term_id'] ) ) )->get_status() );
  $tax = get_taxonomy( 'event_category' );
  wp_get_current_user()->add_cap( $tax->cap->assign_terms );
  $result = $this->api( 'PATCH', '/events/' . $event, array( 'category_ids' => array( $term['term_id'] ) ) );
  $this->assertSame( 200, $result->get_status() );
  $this->assertSame( array( $term['term_id'] ), $result->get_data()['category_ids'] );
  $this->assertSame( 400, $this->api( 'PATCH', '/events/' . $event, array( 'category_ids' => array( 999999 ) ) )->get_status() );
  wp_get_current_user()->add_cap( 'edit_posts' );
  $image = wp_insert_attachment( array( 'post_title' => 'Logo', 'post_author' => $this->a, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
  update_post_meta( $image, '_wp_attached_file', '2027/01/test-logo.png' );
  $result = $this->api( 'PATCH', '/events/' . $event, array( 'featured_image_id' => $image, 'logo_id' => $image ) );
  $this->assertSame( 200, $result->get_status() );
  $this->assertSame( $image, $result->get_data()['featured_image_id'] );
  $this->assertSame( wp_get_attachment_url( $image ), get_post_meta( $event, 'event_logo_file_url', true ) );
  wp_update_post( array( 'ID' => $image, 'post_author' => $this->b ) );
  $this->assertSame( 403, $this->api( 'PATCH', '/events/' . $event, array( 'logo_id' => $image ) )->get_status() );
  $ticket = $this->ticket( $event );
  $template = wp_insert_post( array( 'post_type' => 'tc_templates', 'post_title' => 'Owned template', 'post_author' => $this->a, 'post_status' => 'publish' ) );
  $this->assertSame( 403, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'template_id' => $template ) )->get_status() );
  wp_get_current_user()->add_cap( 'manage_ticket_templates_cap' );
  $this->assertSame( 200, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'template_id' => $template ) )->get_status() );
  $this->assertSame( 403, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'template_id' => $event ) )->get_status() );
 }

 public function test_safe_persistence_failure_and_no_delete_route(): void {
  $fail = static function () { return true; };
  add_filter( 'wp_insert_post_empty_content', $fail );
  try {
   $response = $this->api( 'POST', '/events', array( 'title' => 'Fail', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T10:00' ) );
   $this->assertSame( 500, $response->get_status() );
   $this->assertSame( 'quixdevs_persistence_failed', $response->get_data()['code'] );
  } finally {
   remove_filter( 'wp_insert_post_empty_content', $fail );
  }
  $event = $this->event();
  $this->assertSame( 404, $this->api( 'DELETE', '/events/' . $event )->get_status() );
 }

 public function test_html_sanitization_and_partial_date_updates(): void {
  $event = $this->event( array( 'description' => '<p>Hello</p><script>alert(1)</script>' ) );
  $result = $this->api( 'GET', '/events/' . $event )->get_data();
  $this->assertStringNotContainsString( '<script', $result['description'] );
  $this->assertSame( 'Asia/Dhaka', $result['timezone'] );
  $this->assertSame( 422, $this->api( 'PATCH', '/events/' . $event, array( 'start_at' => '2027-01-15T09:00' ) )->get_status() );
  $this->assertSame( '2027-01-14 09:00', get_post_meta( $event, 'event_date_time', true ) );
  $this->assertSame( 200, $this->api( 'PATCH', '/events/' . $event, array( 'end_at' => '2027-01-15T09:00' ) )->get_status() );
 }
 public function test_save_hook_exceptions_are_safe_and_restore_form_state(): void {
  $event = $this->event();
  $_POST = array( 'unrelated' => 'preserved' );
  $fail = static function () { throw new RuntimeException( 'private database detail must never escape' ); };
  add_action( 'save_post_tc_events', $fail );
  try {
   $response = $this->api( 'PATCH', '/events/' . $event, array( 'title' => 'Late save failure' ) );
   $this->assertSame( 500, $response->get_status() );
   $this->assertStringNotContainsString( 'private database', wp_json_encode( $response->get_data() ) );
   $this->assertSame( array( 'unrelated' => 'preserved' ), $_POST );
  } finally {
   remove_action( 'save_post_tc_events', $fail );
  }
 }

 public function test_create_permission_is_independent_of_later_edit_permission(): void {
  wp_get_current_user()->remove_cap( 'edit_tc_events' );
  $event = $this->event();
  $this->assertSame( 403, $this->api( 'GET', '/events/' . $event )->get_status() );
  wp_get_current_user()->add_cap( 'edit_tc_events' );
  wp_get_current_user()->remove_cap( 'edit_tc_tickets' );
  $ticket = $this->ticket( $event );
  $this->assertSame( 403, $this->api( 'GET', '/tickets/' . $ticket )->get_status() );
 }

 public function test_unrelated_ticket_metadata_and_records_are_preserved(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  update_post_meta( $ticket, 'quantity_sold', 7 );
  update_post_meta( $ticket, 'min_tickets_per_order', 2 );
  $order = wp_insert_post( array( 'post_type' => 'tc_orders', 'post_status' => 'order_paid', 'post_title' => 'Unrelated test order' ) );
  $instance = wp_insert_post( array( 'post_type' => 'tc_tickets_instances', 'post_status' => 'publish', 'post_title' => 'Unrelated test instance', 'post_parent' => $order, 'meta_input' => array( 'ticket_type_id' => $ticket, 'ticket_code' => 'private-issued-code' ) ) );
  $before = get_post( $instance );
  $this->assertSame( 200, $this->api( 'PATCH', '/tickets/' . $ticket, array( 'price' => '51.00' ) )->get_status() );
  $this->assertSame( '7', get_post_meta( $ticket, 'quantity_sold', true ) );
  $this->assertSame( '2', get_post_meta( $ticket, 'min_tickets_per_order', true ) );
  $this->assertEquals( $before, get_post( $instance ) );
  $this->assertSame( 'order_paid', get_post_status( $order ) );
  $this->assertStringNotContainsString( 'private-issued-code', wp_json_encode( $this->api( 'GET', '/tickets/' . $ticket )->get_data() ) );
 }

}
