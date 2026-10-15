<?php
if ( 'cli' !== PHP_SAPI ) {
 http_response_code( 404 );
 exit;
}
/** Real installed WordPress + bundled Tickera bootstrap. Never use production. */
$path = getenv( 'QUIXDEVS_WP_PATH' );
if ( ! $path || ! is_file( $path . '/wp-config.php' ) ) {
 throw new RuntimeException( 'Set QUIXDEVS_WP_PATH to an isolated installed WordPress. See docs/testing.md.' );
}
$config = file_get_contents( $path . '/wp-config.php' );
if ( ! preg_match( "/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]quixdevs_tickera_test['\"]\s*\)/", $config ) ) {
 throw new RuntimeException( 'Tests require the dedicated database quixdevs_tickera_test.' );
}
error_reporting( E_ALL & ~E_DEPRECATED ); // Upstream Tickera uses dynamic properties on PHP 8.2+.
putenv( 'WP_ENVIRONMENT_TYPE=local' );
define( 'WP_RUN_CORE_TESTS', true );
require $path . '/wp-load.php';
if ( ! class_exists( '\Tickera\TC' ) || ! class_exists( '\QuixDevs\TickeraRestAPI\Plugin' ) ) {
 throw new RuntimeException( 'Activate bundled Tickera and this addon before testing.' );
}
require __DIR__ . '/Rest_Test_Case.php';
