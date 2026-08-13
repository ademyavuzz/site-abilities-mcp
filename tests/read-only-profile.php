<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['samcp_profile_abilities'] = array();

function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function current_user_can() { return true; }
function wp_register_ability_category() { return true; }
function wp_register_ability( $name, $args ) {
	$GLOBALS['samcp_profile_abilities'][ $name ] = $args;
	return true;
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

require dirname( __DIR__ ) . '/site-abilities-mcp.php';

samcp_register_page_abilities();
samcp_register_content_abilities();
samcp_register_media_abilities();
samcp_register_menu_abilities();
samcp_register_seo_abilities();
samcp_register_product_abilities();
samcp_register_admin_abilities();
samcp_register_wpbakery_abilities();

$public_reads  = 0;
$public_writes = 0;

foreach ( $GLOBALS['samcp_profile_abilities'] as $name => $ability ) {
	$is_readonly = ! empty( $ability['meta']['annotations']['readonly'] );
	$is_public   = ! empty( $ability['meta']['mcp']['public'] );

	if ( $is_readonly && $is_public ) {
		++$public_reads;
	}

	if ( ! $is_readonly && $is_public ) {
		++$public_writes;
	}
}

if ( 0 === $public_reads || 0 !== $public_writes ) {
	throw new RuntimeException( 'The default profile must expose reads and hide all writes.' );
}

echo "Read-only profile tests passed.\n";
