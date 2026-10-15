<?php
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
abstract class Rest_Test_Case extends \PHPUnit\Framework\TestCase {
 protected $a;
 protected $b;
 protected $server;
 protected $timezone;

 protected function setUp(): void {
  global $wpdb, $wp_rest_server;
  $wpdb->query( 'START TRANSACTION' );
  wp_cache_flush();
  $this->timezone = get_option( 'timezone_string' );
  update_option( 'timezone_string', 'Asia/Dhaka' );
  putenv( 'WP_ENVIRONMENT_TYPE=local' );
  $_POST = array();
  $this->a = $this->author( 'qa_a' );
  $this->b = $this->author( 'qa_b' );
  wp_set_current_user( $this->a );
  $wp_rest_server = new WP_REST_Server();
  $this->server = rest_get_server();
  do_action( 'rest_api_init', $this->server );
 }

 protected function tearDown(): void {
  global $wpdb;
  $_POST = array();
  $_SERVER['HTTPS'] = '';
  putenv( 'WP_ENVIRONMENT_TYPE=local' );
  wp_set_current_user( 0 );
  $wpdb->query( 'ROLLBACK' );
  wp_cache_flush();
 }

 protected function author( $login ) {
  $id = wp_insert_user( array( 'user_login' => $login . '_' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
  $this->assertIsInt( $id );
  $user = new WP_User( $id );
  foreach ( array( 'create_tc_events', 'edit_tc_events', 'edit_published_tc_events', 'edit_private_tc_events', 'publish_tc_events', 'create_tc_tickets', 'edit_tc_tickets', 'edit_published_tc_tickets', 'edit_private_tc_tickets', 'publish_tc_tickets' ) as $cap ) {
   $user->add_cap( $cap );
  }
  return $id;
 }

 protected function api( $method, $path, $body = null, $query = array() ) {
  $request = new WP_REST_Request( $method, '/quixdevs-tickera/v1' . $path );
  if ( null !== $body ) {
   $request->set_header( 'Content-Type', 'application/json' );
   $request->set_body( wp_json_encode( $body ) );
  }
  $request->set_query_params( $query );
  return $this->server->dispatch( $request );
 }

 protected function event( $body = array() ) {
  $response = $this->api( 'POST', '/events', array_replace( array( 'title' => 'QuixDevs conference', 'start_at' => '2027-01-14T09:00', 'end_at' => '2027-01-14T18:00' ), $body ) );
  $this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
  return $response->get_data()['id'];
 }

 protected function ticket( $event, $body = array() ) {
  $response = $this->api( 'POST', '/events/' . $event . '/tickets', array_replace( array( 'title' => 'VIP', 'price' => '50.00', 'quantity' => 100 ), $body ) );
  $this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
  return $response->get_data()['id'];
 }
}
