<?php
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
class Dependencies_Test extends Rest_Test_Case {
 public function test_bridge_blocks_tickets_but_keeps_events(): void {
  $event = $this->event();
  $ticket = $this->ticket( $event );
  $on = static function () { return true; };
  add_filter( 'tickera_bridge_for_woocommerce_is_active', $on );
  try {
   foreach ( array( '/events/' . $event . '/tickets', '/tickets/' . $ticket ) as $path ) {
    $this->assertSame( 409, $this->api( 'GET', $path )->get_status() );
   }
   $this->assertSame( 409, $this->api( 'POST', '/events/' . $event . '/tickets', array( 'title' => 'No standalone', 'price' => '1' ) )->get_status() );
   $this->assertSame( 200, $this->api( 'GET', '/events/' . $event )->get_status() );
   $this->event();
  } finally {
   remove_filter( 'tickera_bridge_for_woocommerce_is_active', $on );
  }
 }

 public function test_dependency_and_version_fail_closed(): void {
  global $tc;
  $real = $tc;
  $version = $tc->version;
  try {
   $tc = null;
   $this->assertSame( 503, $this->api( 'GET', '/events' )->get_status() );
   $tc = $real;
   $tc->version = '0.0.0';
   $this->assertSame( 503, $this->api( 'GET', '/events' )->get_status() );
  } finally {
   $tc = $real;
   $tc->version = $version;
  }
 }
}
