<?php
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
use QuixDevs\TickeraRestAPI\Support\Date_Time;
use QuixDevs\TickeraRestAPI\Support\Validator;
class Date_And_Money_Test extends Rest_Test_Case {
 public function test_decimal_normalization_is_exact(): void {
  $this->assertSame( '99999999.99', Validator::price( '99999999.99' ) );
  $this->assertSame( '0.10', Validator::price( '0.1' ) );
  $this->assertSame( '50.00', Validator::price( '50' ) );
 }
 public function test_local_dates_and_dst_are_not_silently_reinterpreted(): void {
  $this->assertSame( '2027-01-14 09:00', Date_Time::storage( '2027-01-14T09:00' ) );
  $this->assertSame( '2027-01-14T09:00', Date_Time::external( '2027-01-14 09:00' ) );
  $this->assertFalse( Date_Time::parse( '2027-02-30T09:00' ) );
  update_option( 'timezone_string', 'America/New_York' );
  $this->assertFalse( Date_Time::parse( '2027-03-14T02:30' ) );
  $this->assertFalse( Date_Time::parse( '2027-11-07T01:30' ) );
  $this->assertNotFalse( Date_Time::parse( '2027-11-07T03:30' ) );
 }
}
