<?php
/**
 * Controlled abilities for posts and explicitly allowlisted content types.
 *
 * @package SiteAbilitiesMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return the content types this module may manage. */
function samcp_allowed_content_types() {
	$types = array( 'post' );

	if ( function_exists( 'apply_filters' ) ) {
		$types = apply_filters( 'site_abilities_mcp_allowed_post_types', $types );
	}

	$types = array_filter( array_map( 'sanitize_key', (array) $types ) );
	return array_values( array_unique( $types ) );
}

/** Register the generic-content category. */
function samcp_register_content_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'site-abilities-content',
			array(
				'label'       => __( 'Site Content', 'site-abilities-mcp' ),
				'description' => __( 'Controlled management of posts and explicitly allowlisted custom post types.', 'site-abilities-mcp' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_content_category' );

/** Basic post type selector schema. */
function samcp_content_type_schema() {
	return array( 'type' => 'string', 'enum' => samcp_allowed_content_types() );
}

/** Stable generic content payload schema. */
function samcp_content_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'post_type', 'status', 'title', 'content', 'excerpt', 'slug', 'modified_gmt', 'content_sha256' ),
		'properties' => array(
			'id'             => array( 'type' => 'integer' ),
			'post_type'      => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string' ),
			'title'          => array( 'type' => 'string' ),
			'content'        => array( 'type' => 'string' ),
			'excerpt'        => array( 'type' => 'string' ),
			'slug'           => array( 'type' => 'string' ),
			'modified_gmt'   => array( 'type' => 'string' ),
			'content_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
		),
	);
}

/** Shared input schema for one content item. */
function samcp_content_id_input_schema( $mutation = false, $confirmation = '' ) {
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'content_id' ),
		'properties'           => array(
			'content_id' => array( 'type' => 'integer', 'minimum' => 1 ),
		),
	);
	if ( $mutation ) {
		$schema['required'] = array_merge( $schema['required'], array( 'expected_modified_gmt', 'expected_content_sha256' ) );
		$schema['properties']['expected_modified_gmt']   = samcp_expected_modified_schema();
		$schema['properties']['expected_content_sha256'] = samcp_expected_hash_schema();
		if ( $confirmation ) {
			$schema['required'][] = 'confirmation';
			$schema['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( $confirmation ) );
		}
	}
	return $schema;
}

/** Register controlled generic-content abilities. */
function samcp_register_content_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'site-abilities/list-content',
		array(
			'label' => __( 'List site content', 'site-abilities-mcp' ),
			'description' => __( 'Lists posts or an explicitly allowlisted custom post type.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content',
			'input_schema' => array(
				'type' => 'object', 'additionalProperties' => false,
				'required' => array( 'post_type' ),
				'properties' => array(
					'post_type' => samcp_content_type_schema(),
					'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ), 'default' => 'any' ),
					'search' => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				),
			),
			'output_schema' => array( 'type' => 'array', 'items' => samcp_content_schema() ),
			'execute_callback' => 'samcp_list_content',
			'permission_callback' => 'samcp_can_list_content',
			'meta' => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/get-content',
		array(
			'label' => __( 'Read site content', 'site-abilities-mcp' ),
			'description' => __( 'Reads one allowed content item without changing it.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content',
			'input_schema' => samcp_content_id_input_schema(),
			'output_schema' => samcp_content_schema(),
			'execute_callback' => 'samcp_get_content',
			'permission_callback' => 'samcp_can_read_content',
			'meta' => samcp_ability_meta( true, false, true ),
		)
	);

	$draft_schema = array(
		'type' => 'object', 'additionalProperties' => false,
		'required' => array( 'post_type', 'title' ),
		'properties' => array(
			'post_type' => samcp_content_type_schema(),
			'title' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 500 ),
			'content' => array( 'type' => 'string' ),
			'excerpt' => array( 'type' => 'string' ),
		),
	);
	wp_register_ability(
		'site-abilities/create-content-draft',
		array(
			'label' => __( 'Create site content draft', 'site-abilities-mcp' ),
			'description' => __( 'Creates an allowed content item as a draft and never publishes it.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content', 'input_schema' => $draft_schema, 'output_schema' => samcp_content_schema(),
			'execute_callback' => 'samcp_create_content_draft', 'permission_callback' => 'samcp_can_create_content',
			'meta' => samcp_ability_meta( false, false, false ),
		)
	);

	$update_schema = samcp_content_id_input_schema( true );
	$update_schema['properties']['title'] = array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 500 );
	$update_schema['properties']['content'] = array( 'type' => 'string' );
	$update_schema['properties']['excerpt'] = array( 'type' => 'string' );
	wp_register_ability(
		'site-abilities/update-content-draft',
		array(
			'label' => __( 'Update site content draft', 'site-abilities-mcp' ),
			'description' => __( 'Updates an allowed draft after a stale-write check.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content', 'input_schema' => $update_schema, 'output_schema' => samcp_content_schema(),
			'execute_callback' => 'samcp_update_content_draft', 'permission_callback' => 'samcp_can_edit_content',
			'meta' => samcp_ability_meta( false, true, false ),
		)
	);

	$live_schema = $update_schema;
	$live_schema['required'][] = 'confirmation';
	$live_schema['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( 'UPDATE_LIVE_CONTENT' ) );
	wp_register_ability(
		'site-abilities/update-published-content',
		array(
			'label' => __( 'Update published site content', 'site-abilities-mcp' ),
			'description' => __( 'Updates published content only after explicit confirmation and a verified safety revision.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content', 'input_schema' => $live_schema,
			'output_schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string' ), 'safety_revision_id' => array( 'type' => 'integer' ), 'safety_snapshot_id' => array( 'type' => 'string' ), 'content' => samcp_content_schema() ) ),
			'execute_callback' => 'samcp_update_published_content', 'permission_callback' => 'samcp_can_publish_content',
			'meta' => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/publish-content-draft',
		array(
			'label' => __( 'Publish site content draft', 'site-abilities-mcp' ),
			'description' => __( 'Publishes a reviewed draft after an exact snapshot check and explicit confirmation.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content', 'input_schema' => samcp_content_id_input_schema( true, 'PUBLISH_CONTENT' ),
			'output_schema' => samcp_content_schema(), 'execute_callback' => 'samcp_publish_content_draft',
			'permission_callback' => 'samcp_can_publish_content', 'meta' => samcp_ability_meta( false, true, false ),
		)
	);

	foreach ( array( 'trash-content' => array( 'TRASH_CONTENT', 'samcp_trash_content' ), 'restore-trashed-content' => array( 'RESTORE_TRASHED_CONTENT', 'samcp_restore_trashed_content' ) ) as $name => $config ) {
		wp_register_ability(
			'site-abilities/' . $name,
			array(
				'label' => 'trash-content' === $name ? __( 'Move site content to trash', 'site-abilities-mcp' ) : __( 'Restore trashed site content', 'site-abilities-mcp' ),
				'description' => __( 'Performs a recoverable trash status change after snapshot validation and explicit confirmation. Permanent deletion is unavailable.', 'site-abilities-mcp' ),
				'category' => 'site-abilities-content', 'input_schema' => samcp_content_id_input_schema( true, $config[0] ),
				'output_schema' => samcp_content_schema(), 'execute_callback' => $config[1],
				'permission_callback' => 'samcp_can_delete_content', 'meta' => samcp_ability_meta( false, true, false ),
			)
		);
	}

	wp_register_ability(
		'site-abilities/list-content-snapshots',
		array(
			'label' => __( 'List content snapshots', 'site-abilities-mcp' ),
			'description' => __( 'Lists recent fallback snapshots created for revision-disabled content types.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content',
			'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'content_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
			'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'execute_callback' => 'samcp_list_content_snapshots',
			'permission_callback' => 'samcp_can_list_content_snapshots',
			'meta' => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/restore-content-snapshot',
		array(
			'label' => __( 'Restore content snapshot', 'site-abilities-mcp' ),
			'description' => __( 'Restores title, content and excerpt from a fallback snapshot after validating the current content state. Current publication status is preserved.', 'site-abilities-mcp' ),
			'category' => 'site-abilities-content',
			'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'snapshot_id', 'expected_modified_gmt', 'expected_content_sha256', 'confirmation' ), 'properties' => array( 'snapshot_id' => array( 'type' => 'string', 'pattern' => '^[a-zA-Z0-9-]{8,64}$' ), 'expected_modified_gmt' => samcp_expected_modified_schema(), 'expected_content_sha256' => samcp_expected_hash_schema(), 'confirmation' => array( 'type' => 'string', 'enum' => array( 'RESTORE_CONTENT_SNAPSHOT' ) ) ) ),
			'output_schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string' ), 'safety_revision_id' => array( 'type' => 'integer' ), 'safety_snapshot_id' => array( 'type' => 'string' ), 'content' => samcp_content_schema() ) ),
			'execute_callback' => 'samcp_restore_content_snapshot',
			'permission_callback' => 'samcp_can_restore_content_snapshot',
			'meta' => samcp_ability_meta( false, true, false ),
		)
	);
}
add_action( 'wp_abilities_api_init', 'samcp_register_content_abilities' );

function samcp_find_content( $id ) {
	$post = get_post( absint( $id ) );
	if ( ! $post || ! in_array( $post->post_type, samcp_allowed_content_types(), true ) ) {
		return new WP_Error( 'samcp_content_not_found', __( 'The requested content item is unavailable or its type is not allowed.', 'site-abilities-mcp' ) );
	}
	return $post;
}

function samcp_format_content( $post ) {
	return array(
		'id' => (int) $post->ID, 'post_type' => (string) $post->post_type, 'status' => (string) $post->post_status,
		'title' => (string) $post->post_title, 'content' => (string) $post->post_content, 'excerpt' => (string) $post->post_excerpt,
		'slug' => (string) $post->post_name, 'modified_gmt' => (string) $post->post_modified_gmt,
		'content_sha256' => hash( 'sha256', (string) $post->post_content ),
	);
}

function samcp_can_list_content( $input ) {
	$type = isset( $input['post_type'] ) ? $input['post_type'] : '';
	$object = in_array( $type, samcp_allowed_content_types(), true ) ? get_post_type_object( $type ) : null;
	return $object && current_user_can( $object->cap->edit_posts );
}

function samcp_can_read_content( $input ) { return ! empty( $input['content_id'] ) && current_user_can( 'read_post', absint( $input['content_id'] ) ); }
function samcp_can_edit_content( $input ) { return ! empty( $input['content_id'] ) && current_user_can( 'edit_post', absint( $input['content_id'] ) ); }
function samcp_can_delete_content( $input ) { return ! empty( $input['content_id'] ) && current_user_can( 'delete_post', absint( $input['content_id'] ) ); }
function samcp_can_create_content( $input ) {
	$type = isset( $input['post_type'] ) ? $input['post_type'] : '';
	$object = in_array( $type, samcp_allowed_content_types(), true ) ? get_post_type_object( $type ) : null;
	return $object && current_user_can( $object->cap->edit_posts );
}
function samcp_can_publish_content( $input ) {
	$post = ! empty( $input['content_id'] ) ? get_post( absint( $input['content_id'] ) ) : null;
	$object = $post ? get_post_type_object( $post->post_type ) : null;
	return $post && $object && current_user_can( 'edit_post', $post->ID ) && current_user_can( $object->cap->publish_posts );
}
function samcp_can_list_content_snapshots() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); }
function samcp_can_restore_content_snapshot( $input ) { foreach ( get_option( 'samcp_content_snapshots', array() ) as $item ) { if ( isset( $input['snapshot_id'] ) && hash_equals( (string) $item['id'], (string) $input['snapshot_id'] ) ) { $post = get_post( absint( $item['content_id'] ) ); $object = $post ? get_post_type_object( $post->post_type ) : null; return $post && current_user_can( 'edit_post', $post->ID ) && ( 'publish' !== $post->post_status || ( $object && current_user_can( $object->cap->publish_posts ) ) ); } } return false; }

function samcp_list_content( $input ) {
	$args = array( 'post_type' => $input['post_type'], 'post_status' => isset( $input['status'] ) ? $input['status'] : 'any', 'posts_per_page' => isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 20, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true );
	if ( ! empty( $input['search'] ) ) { $args['s'] = sanitize_text_field( $input['search'] ); }
	return array_values( array_map( 'samcp_format_content', array_filter( get_posts( $args ), static function ( $post ) { return current_user_can( 'read_post', $post->ID ); } ) ) );
}

function samcp_get_content( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	return is_wp_error( $post ) ? $post : samcp_format_content( $post );
}

function samcp_create_content_draft( $input ) {
	$data = array( 'post_type' => $input['post_type'], 'post_status' => 'draft', 'post_title' => sanitize_text_field( $input['title'] ), 'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '', 'post_excerpt' => isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '' );
	$id = wp_insert_post( wp_slash( $data ), true );
	return is_wp_error( $id ) ? $id : samcp_format_content( get_post( $id ) );
}

function samcp_content_update_array( $post, $input, $status ) {
	$data = array( 'ID' => $post->ID, 'post_status' => $status );
	foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $key => $field ) {
		if ( array_key_exists( $key, $input ) ) { $data[ $field ] = 'title' === $key ? sanitize_text_field( $input[ $key ] ) : (string) $input[ $key ]; }
	}
	return $data;
}

function samcp_save_content_snapshot( $post, $reason ) { $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'content-', true ); $items = get_option( 'samcp_content_snapshots', array() ); array_unshift( $items, array( 'id' => $id, 'content_id' => (int) $post->ID, 'post_type' => (string) $post->post_type, 'created_gmt' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => get_current_user_id(), 'reason' => $reason, 'title' => (string) $post->post_title, 'content' => (string) $post->post_content, 'excerpt' => (string) $post->post_excerpt ) ); update_option( 'samcp_content_snapshots', array_slice( $items, 0, 50 ), false ); return $id; }
function samcp_create_content_safety_point( $post, $reason ) { if ( wp_revisions_enabled( $post ) ) { $revision = samcp_create_safety_revision( $post ); return is_wp_error( $revision ) ? $revision : array( 'revision_id' => (int) $revision, 'snapshot_id' => '' ); } return array( 'revision_id' => 0, 'snapshot_id' => samcp_save_content_snapshot( $post, $reason ) ); }

function samcp_update_content_draft( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( 'draft' !== $post->post_status ) { return new WP_Error( 'samcp_content_not_draft', __( 'Only draft content can be updated here.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	if ( ! samcp_has_page_changes( $post, $input ) ) { return new WP_Error( 'samcp_no_changes', __( 'No content changes were supplied.', 'site-abilities-mcp' ) ); }
	$id = wp_update_post( wp_slash( samcp_content_update_array( $post, $input, 'draft' ) ), true );
	return is_wp_error( $id ) ? $id : samcp_format_content( get_post( $id ) );
}

function samcp_update_published_content( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( 'publish' !== $post->post_status ) { return new WP_Error( 'samcp_content_not_published', __( 'Only published content can be updated here.', 'site-abilities-mcp' ) ); }
	if ( empty( $input['confirmation'] ) || 'UPDATE_LIVE_CONTENT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_LIVE_CONTENT confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	if ( ! samcp_has_page_changes( $post, $input ) ) { return new WP_Error( 'samcp_no_changes', __( 'No content changes were supplied.', 'site-abilities-mcp' ) ); }
	$safety = samcp_create_content_safety_point( $post, 'before_live_update' );
	if ( is_wp_error( $safety ) ) { return $safety; }
	$id = wp_update_post( wp_slash( samcp_content_update_array( $post, $input, 'publish' ) ), true );
	return is_wp_error( $id ) ? $id : array( 'action' => 'updated_published_content', 'safety_revision_id' => $safety['revision_id'], 'safety_snapshot_id' => $safety['snapshot_id'], 'content' => samcp_format_content( get_post( $id ) ) );
}

function samcp_publish_content_draft( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( 'draft' !== $post->post_status ) { return new WP_Error( 'samcp_content_not_draft', __( 'Only draft content can be published.', 'site-abilities-mcp' ) ); }
	if ( empty( $input['confirmation'] ) || 'PUBLISH_CONTENT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit PUBLISH_CONTENT confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$id = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ), true );
	return is_wp_error( $id ) ? $id : samcp_format_content( get_post( $id ) );
}

function samcp_trash_content( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( 'trash' === $post->post_status ) { return new WP_Error( 'samcp_content_already_trashed', __( 'Content is already in trash.', 'site-abilities-mcp' ) ); }
	if ( empty( $input['confirmation'] ) || 'TRASH_CONTENT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit TRASH_CONTENT confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$result = wp_trash_post( $post->ID );
	return $result ? samcp_format_content( get_post( $post->ID ) ) : new WP_Error( 'samcp_trash_failed', __( 'WordPress could not move the content to trash.', 'site-abilities-mcp' ) );
}

function samcp_restore_trashed_content( $input ) {
	$post = samcp_find_content( $input['content_id'] );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( 'trash' !== $post->post_status ) { return new WP_Error( 'samcp_content_not_trashed', __( 'Only trashed content can be restored.', 'site-abilities-mcp' ) ); }
	if ( empty( $input['confirmation'] ) || 'RESTORE_TRASHED_CONTENT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_TRASHED_CONTENT confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$result = wp_untrash_post( $post->ID );
	if ( ! $result ) { return new WP_Error( 'samcp_untrash_failed', __( 'WordPress could not restore the content.', 'site-abilities-mcp' ) ); }
	$id = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true );
	return is_wp_error( $id ) ? $id : samcp_format_content( get_post( $id ) );
}

function samcp_list_content_snapshots( $input ) { $items = array_filter( get_option( 'samcp_content_snapshots', array() ), static function ( $item ) { return current_user_can( 'edit_post', absint( $item['content_id'] ) ); } ); if ( ! empty( $input['content_id'] ) ) { $items = array_filter( $items, static function ( $item ) use ( $input ) { return absint( $item['content_id'] ) === absint( $input['content_id'] ); } ); } return array_values( array_map( static function ( $item ) { unset( $item['content'] ); return $item; }, $items ) ); }
function samcp_restore_content_snapshot( $input ) { if ( empty( $input['confirmation'] ) || 'RESTORE_CONTENT_SNAPSHOT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_CONTENT_SNAPSHOT confirmation is required.', 'site-abilities-mcp' ) ); } $snapshot = null; foreach ( get_option( 'samcp_content_snapshots', array() ) as $candidate ) { if ( hash_equals( (string) $candidate['id'], (string) $input['snapshot_id'] ) ) { $snapshot = $candidate; break; } } if ( ! $snapshot ) { return new WP_Error( 'samcp_content_snapshot_not_found', __( 'The requested content snapshot was not found.', 'site-abilities-mcp' ) ); } $post = samcp_find_content( $snapshot['content_id'] ); if ( is_wp_error( $post ) || $post->post_type !== $snapshot['post_type'] ) { return is_wp_error( $post ) ? $post : new WP_Error( 'samcp_content_snapshot_mismatch', __( 'The snapshot no longer matches the content type.', 'site-abilities-mcp' ) ); } $check = samcp_validate_page_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; } $safety = samcp_create_content_safety_point( $post, 'before_snapshot_restore' ); if ( is_wp_error( $safety ) ) { return $safety; } $id = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_status' => $post->post_status, 'post_title' => $snapshot['title'], 'post_content' => $snapshot['content'], 'post_excerpt' => $snapshot['excerpt'] ) ), true ); return is_wp_error( $id ) ? $id : array( 'action' => 'restored_content_snapshot', 'safety_revision_id' => $safety['revision_id'], 'safety_snapshot_id' => $safety['snapshot_id'], 'content' => samcp_format_content( get_post( $id ) ) ); }
