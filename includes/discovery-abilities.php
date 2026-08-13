<?php
/**
 * Site discovery and privacy-preserving ability activity.
 *
 * @package SiteAbilitiesMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the discovery category. */
function samcp_register_discovery_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'site-abilities-discovery',
			array(
				'label'       => __( 'Site Discovery', 'site-abilities-mcp' ),
				'description' => __( 'Provides bounded site, content-type and activity context for safer planning.', 'site-abilities-mcp' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_discovery_category' );

/** Register discovery abilities. */
function samcp_register_discovery_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) { return; }

	wp_register_ability( 'site-abilities/get-site-overview', array(
		'label' => __( 'Get safe site overview', 'site-abilities-mcp' ), 'description' => __( 'Returns non-secret site, environment and active-theme context for planning. It does not expose credentials or filesystem paths.', 'site-abilities-mcp' ), 'category' => 'site-abilities-discovery',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false ), 'output_schema' => array( 'type' => 'object' ), 'execute_callback' => 'samcp_get_site_overview', 'permission_callback' => 'samcp_can_read_site_overview', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/list-content-types', array(
		'label' => __( 'List content types', 'site-abilities-mcp' ), 'description' => __( 'Lists manageable WordPress post types and their capability summary without changing the site.', 'site-abilities-mcp' ), 'category' => 'site-abilities-discovery',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false ), 'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'execute_callback' => 'samcp_list_content_types', 'permission_callback' => 'samcp_can_read_site_overview', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/get-content-terms', array(
		'label' => __( 'Read content term assignments', 'site-abilities-mcp' ), 'description' => __( 'Returns allowlisted taxonomy assignments and stable assignment checksums for one content item.', 'site-abilities-mcp' ), 'category' => 'site-abilities-discovery',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'content_id' ), 'properties' => array( 'content_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
		'output_schema' => array( 'type' => 'object' ), 'execute_callback' => 'samcp_get_content_terms', 'permission_callback' => 'samcp_can_read_content_terms', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/list-ability-activity', array(
		'label' => __( 'List ability activity', 'site-abilities-mcp' ), 'description' => __( 'Lists recent Site Abilities MCP execution metadata. Inputs, outputs, content and credentials are never recorded.', 'site-abilities-mcp' ), 'category' => 'site-abilities-discovery',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ) ) ),
		'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'execute_callback' => 'samcp_list_ability_activity', 'permission_callback' => 'samcp_can_read_ability_activity', 'meta' => samcp_ability_meta( true, false, true ),
	) );
}
add_action( 'wp_abilities_api_init', 'samcp_register_discovery_abilities' );

function samcp_can_read_site_overview() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); }
function samcp_can_read_content_terms( $input ) { return ! empty( $input['content_id'] ) && current_user_can( 'read_post', absint( $input['content_id'] ) ); }
function samcp_can_read_ability_activity() { return current_user_can( 'manage_options' ); }

function samcp_get_site_overview() {
	$theme = wp_get_theme();
	return array(
		'name'                => (string) get_bloginfo( 'name' ),
		'description'         => (string) get_bloginfo( 'description' ),
		'home_url'            => (string) home_url( '/' ),
		'site_url'            => (string) site_url( '/' ),
		'language'            => (string) get_locale(),
		'timezone'            => (string) wp_timezone_string(),
		'wordpress_version'   => (string) get_bloginfo( 'version' ),
		'php_version'         => (string) PHP_VERSION,
		'environment_type'    => function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production',
		'multisite'           => is_multisite(),
		'active_theme'        => array( 'name' => (string) $theme->get( 'Name' ), 'version' => (string) $theme->get( 'Version' ), 'stylesheet' => (string) get_stylesheet(), 'template' => (string) get_template() ),
		'plugin_version'      => defined( 'SAMCP_VERSION' ) ? SAMCP_VERSION : '0.2.0-alpha.1',
		'exposure_profile'    => samcp_get_profile(),
		'allowed_post_types'  => samcp_allowed_content_types(),
		'allowed_taxonomies'  => samcp_allowed_taxonomies(),
		'permanent_deletion'  => false,
	);
}

function samcp_list_content_types() {
	$allowed = array_values( array_unique( array_merge( array( 'page' ), samcp_allowed_content_types(), array( 'wp_block' ) ) ) );
	$items = array();
	foreach ( $allowed as $name ) {
		$type = get_post_type_object( $name );
		if ( ! $type ) { continue; }
		$items[] = array(
			'name' => (string) $type->name, 'label' => (string) $type->label, 'hierarchical' => (bool) $type->hierarchical,
			'rest_base' => (string) $type->rest_base, 'supports' => array_values( get_all_post_type_supports( $name ) ? array_keys( get_all_post_type_supports( $name ) ) : array() ),
			'can_create' => current_user_can( $type->cap->create_posts ), 'can_edit' => current_user_can( $type->cap->edit_posts ), 'can_publish' => current_user_can( $type->cap->publish_posts ), 'can_delete' => current_user_can( $type->cap->delete_posts ),
		);
	}
	return $items;
}

function samcp_get_content_terms( $input ) {
	$post = get_post( absint( $input['content_id'] ) );
	if ( ! $post ) { return new WP_Error( 'samcp_content_not_found', __( 'The requested content item was not found.', 'site-abilities-mcp' ) ); }
	$assignments = array();
	foreach ( samcp_allowed_taxonomies() as $name ) {
		if ( ! is_object_in_taxonomy( $post->post_type, $name ) ) { continue; }
		$state = samcp_term_assignment_state( $post->ID, $name );
		if ( is_wp_error( $state ) ) { return $state; }
		$assignments[ $name ] = $state;
	}
	return array( 'content_id' => (int) $post->ID, 'post_type' => (string) $post->post_type, 'modified_gmt' => (string) $post->post_modified_gmt, 'content_sha256' => hash( 'sha256', (string) $post->post_content ), 'assignments' => $assignments );
}

/** Store execution metadata without ability input or output data. */
function samcp_record_ability_activity( $ability_name, $input = null, $result = null ) {
	unset( $input, $result );
	if ( 0 !== strpos( (string) $ability_name, 'site-abilities/' ) ) { return; }
	$items = (array) get_option( 'samcp_ability_activity', array() );
	array_unshift( $items, array( 'ability' => (string) $ability_name, 'executed_gmt' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => get_current_user_id() ) );
	update_option( 'samcp_ability_activity', array_slice( $items, 0, 200 ), false );
}
add_action( 'wp_after_execute_ability', 'samcp_record_ability_activity', 10, 3 );

function samcp_list_ability_activity( $input ) {
	$limit = isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 50;
	return array_slice( (array) get_option( 'samcp_ability_activity', array() ), 0, $limit );
}
