<?php
/**
 * Gutenberg block discovery and synced-pattern abilities.
 *
 * @package SiteAbilitiesMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the blocks category. */
function samcp_register_block_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'site-abilities-blocks',
			array(
				'label'       => __( 'Site Blocks', 'site-abilities-mcp' ),
				'description' => __( 'Discovers Gutenberg blocks, analyzes block markup and manages synced patterns with revision-backed writes.', 'site-abilities-mcp' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_block_category' );

/** Pattern payload schema. */
function samcp_pattern_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'status', 'title', 'content', 'modified_gmt', 'content_sha256' ),
		'properties' => array(
			'id'             => array( 'type' => 'integer' ),
			'status'         => array( 'type' => 'string' ),
			'title'          => array( 'type' => 'string' ),
			'content'        => array( 'type' => 'string' ),
			'modified_gmt'   => array( 'type' => 'string' ),
			'content_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			'block_count'    => array( 'type' => 'integer' ),
			'block_names'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		),
	);
}

/** Block analysis payload schema. */
function samcp_block_analysis_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'content_sha256', 'block_count', 'block_names', 'has_blocks', 'roundtrip_sha256', 'roundtrip_stable' ),
		'properties' => array(
			'post_id'            => array( 'type' => 'integer' ),
			'post_type'          => array( 'type' => 'string' ),
			'content_sha256'     => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			'block_count'        => array( 'type' => 'integer' ),
			'block_names'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'has_blocks'         => array( 'type' => 'boolean' ),
			'roundtrip_sha256'   => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			'roundtrip_stable'   => array( 'type' => 'boolean' ),
		),
	);
}

/** Block replacement preview payload schema. */
function samcp_block_preview_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'post_id', 'current', 'proposed', 'added_block_names', 'removed_block_names', 'length_delta' ),
		'properties' => array(
			'post_id'             => array( 'type' => 'integer' ),
			'current'             => samcp_block_analysis_schema(),
			'proposed'            => samcp_block_analysis_schema(),
			'added_block_names'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'removed_block_names' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'length_delta'        => array( 'type' => 'integer' ),
		),
	);
}

/** Register block and pattern abilities. */
function samcp_register_block_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) { return; }

	wp_register_ability( 'site-abilities/list-block-types', array(
		'label' => __( 'List registered block types', 'site-abilities-mcp' ), 'description' => __( 'Lists registered Gutenberg block names, titles and attribute schemas without changing the site.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'search' => array( 'type' => 'string' ), 'namespace' => array( 'type' => 'string' ) ) ),
		'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'execute_callback' => 'samcp_list_block_types', 'permission_callback' => 'samcp_can_read_blocks', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/analyze-block-content', array(
		'label' => __( 'Analyze Gutenberg block content', 'site-abilities-mcp' ), 'description' => __( 'Parses one permitted content item and reports its block tree, validation state and checksum without changing it.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'post_id' ), 'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
		'output_schema' => samcp_block_analysis_schema(), 'execute_callback' => 'samcp_analyze_block_content', 'permission_callback' => 'samcp_can_read_block_post', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/preview-block-replacement', array(
		'label' => __( 'Preview Gutenberg replacement', 'site-abilities-mcp' ), 'description' => __( 'Compares current and proposed Gutenberg markup and reports structural changes without writing.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'post_id', 'proposed_content' ), 'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'proposed_content' => array( 'type' => 'string' ) ) ),
		'output_schema' => samcp_block_preview_schema(), 'execute_callback' => 'samcp_preview_block_replacement', 'permission_callback' => 'samcp_can_read_block_post', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/list-synced-patterns', array(
		'label' => __( 'List synced patterns', 'site-abilities-mcp' ), 'description' => __( 'Lists reusable synced patterns without changing them.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'search' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'trash', 'any' ), 'default' => 'any' ), 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ) ) ),
		'output_schema' => array( 'type' => 'array', 'items' => samcp_pattern_schema() ), 'execute_callback' => 'samcp_list_synced_patterns', 'permission_callback' => 'samcp_can_list_patterns', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/get-synced-pattern', array(
		'label' => __( 'Read synced pattern', 'site-abilities-mcp' ), 'description' => __( 'Reads one synced pattern, its block structure and checksum.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'pattern_id' ), 'properties' => array( 'pattern_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
		'output_schema' => samcp_pattern_schema(), 'execute_callback' => 'samcp_get_synced_pattern', 'permission_callback' => 'samcp_can_read_pattern', 'meta' => samcp_ability_meta( true, false, true ),
	) );

	wp_register_ability( 'site-abilities/create-synced-pattern-draft', array(
		'label' => __( 'Create synced pattern draft', 'site-abilities-mcp' ), 'description' => __( 'Creates a reusable synced pattern as a draft after validating its block markup.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'title', 'content', 'confirmation' ), 'properties' => array( 'title' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 500 ), 'content' => array( 'type' => 'string' ), 'confirmation' => array( 'type' => 'string', 'enum' => array( 'CREATE_PATTERN_DRAFT' ) ) ) ),
		'output_schema' => samcp_pattern_schema(), 'execute_callback' => 'samcp_create_synced_pattern_draft', 'permission_callback' => 'samcp_can_create_pattern', 'meta' => samcp_ability_meta( false, false, false ),
	) );

	$update = array(
		'type' => 'object', 'additionalProperties' => false,
		'required' => array( 'pattern_id', 'expected_modified_gmt', 'expected_content_sha256', 'confirmation' ),
		'properties' => array(
			'pattern_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'title' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 500 ), 'content' => array( 'type' => 'string' ),
			'expected_modified_gmt' => samcp_expected_modified_schema(), 'expected_content_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ), 'confirmation' => array( 'type' => 'string', 'enum' => array( 'UPDATE_PATTERN' ) ),
		),
	);
	wp_register_ability( 'site-abilities/update-synced-pattern', array(
		'label' => __( 'Update synced pattern', 'site-abilities-mcp' ), 'description' => __( 'Updates a synced pattern after stale-write and block validation checks and creates a WordPress revision.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => $update, 'output_schema' => samcp_pattern_change_result_schema(), 'execute_callback' => 'samcp_update_synced_pattern', 'permission_callback' => 'samcp_can_edit_pattern', 'meta' => samcp_ability_meta( false, true, false ),
	) );

	$publish = $update;
	$publish['properties']['confirmation']['enum'] = array( 'PUBLISH_PATTERN' );
	unset( $publish['properties']['title'], $publish['properties']['content'] );
	wp_register_ability( 'site-abilities/publish-synced-pattern-draft', array(
		'label' => __( 'Publish synced pattern draft', 'site-abilities-mcp' ), 'description' => __( 'Publishes an existing pattern draft after stale-write validation and creates a safety revision.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => $publish, 'output_schema' => samcp_pattern_change_result_schema(), 'execute_callback' => 'samcp_publish_synced_pattern_draft', 'permission_callback' => 'samcp_can_publish_pattern', 'meta' => samcp_ability_meta( false, true, false ),
	) );

	$trash = $publish;
	$trash['properties']['confirmation']['enum'] = array( 'TRASH_PATTERN' );
	wp_register_ability( 'site-abilities/trash-synced-pattern', array(
		'label' => __( 'Trash synced pattern', 'site-abilities-mcp' ), 'description' => __( 'Moves a synced pattern to trash after validation. Permanent deletion is unavailable.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => $trash, 'output_schema' => samcp_pattern_change_result_schema(), 'execute_callback' => 'samcp_trash_synced_pattern', 'permission_callback' => 'samcp_can_delete_pattern', 'meta' => samcp_ability_meta( false, true, false ),
	) );

	$restore = $publish;
	$restore['properties']['confirmation']['enum'] = array( 'RESTORE_TRASHED_PATTERN' );
	wp_register_ability( 'site-abilities/restore-trashed-pattern', array(
		'label' => __( 'Restore trashed synced pattern', 'site-abilities-mcp' ), 'description' => __( 'Restores a trashed synced pattern as a draft after stale-write validation.', 'site-abilities-mcp' ), 'category' => 'site-abilities-blocks',
		'input_schema' => $restore, 'output_schema' => samcp_pattern_change_result_schema(), 'execute_callback' => 'samcp_restore_trashed_pattern', 'permission_callback' => 'samcp_can_delete_pattern', 'meta' => samcp_ability_meta( false, true, false ),
	) );
}
add_action( 'wp_abilities_api_init', 'samcp_register_block_abilities' );

function samcp_can_read_blocks() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); }
function samcp_can_read_block_post( $input ) { return ! empty( $input['post_id'] ) && current_user_can( 'read_post', absint( $input['post_id'] ) ); }
function samcp_can_list_patterns() { $type = get_post_type_object( 'wp_block' ); return $type && current_user_can( $type->cap->edit_posts ); }
function samcp_can_read_pattern( $input ) { return ! empty( $input['pattern_id'] ) && current_user_can( 'read_post', absint( $input['pattern_id'] ) ); }
function samcp_can_create_pattern() { $type = get_post_type_object( 'wp_block' ); return $type && current_user_can( $type->cap->create_posts ); }
function samcp_can_edit_pattern( $input ) { return ! empty( $input['pattern_id'] ) && current_user_can( 'edit_post', absint( $input['pattern_id'] ) ); }
function samcp_can_publish_pattern( $input ) { $type = get_post_type_object( 'wp_block' ); return samcp_can_edit_pattern( $input ) && $type && current_user_can( $type->cap->publish_posts ); }
function samcp_can_delete_pattern( $input ) { return ! empty( $input['pattern_id'] ) && current_user_can( 'delete_post', absint( $input['pattern_id'] ) ); }

/** Flatten parsed block names. */
function samcp_flatten_block_names( $blocks ) {
	$names = array();
	foreach ( (array) $blocks as $block ) {
		if ( ! empty( $block['blockName'] ) ) { $names[] = (string) $block['blockName']; }
		if ( ! empty( $block['innerBlocks'] ) ) { $names = array_merge( $names, samcp_flatten_block_names( $block['innerBlocks'] ) ); }
	}
	return $names;
}

function samcp_block_analysis( $content ) {
	$blocks = parse_blocks( (string) $content );
	$names = samcp_flatten_block_names( $blocks );
	$roundtrip = serialize_blocks( $blocks );
	return array(
		'content_sha256' => hash( 'sha256', (string) $content ),
		'block_count'    => count( $names ),
		'block_names'    => $names,
		'has_blocks'     => has_blocks( (string) $content ),
		'roundtrip_sha256'=> hash( 'sha256', (string) $roundtrip ),
		'roundtrip_stable'=> hash_equals( hash( 'sha256', trim( (string) $content ) ), hash( 'sha256', trim( (string) $roundtrip ) ) ),
	);
}

function samcp_find_pattern( $id ) {
	$post = get_post( absint( $id ) );
	return $post && 'wp_block' === $post->post_type ? $post : new WP_Error( 'samcp_pattern_not_found', __( 'The requested synced pattern was not found.', 'site-abilities-mcp' ) );
}

function samcp_format_pattern( $post ) {
	$analysis = samcp_block_analysis( $post->post_content );
	return array(
		'id' => (int) $post->ID, 'status' => (string) $post->post_status, 'title' => (string) $post->post_title, 'content' => (string) $post->post_content,
		'modified_gmt' => (string) $post->post_modified_gmt, 'content_sha256' => hash( 'sha256', (string) $post->post_content ),
		'block_count' => (int) $analysis['block_count'], 'block_names' => $analysis['block_names'],
	);
}

function samcp_list_block_types( $input ) {
	$registry = WP_Block_Type_Registry::get_instance();
	$items = array();
	foreach ( $registry->get_all_registered() as $name => $type ) {
		if ( ! empty( $input['namespace'] ) && 0 !== strpos( $name, sanitize_key( $input['namespace'] ) . '/' ) ) { continue; }
		$title = ! empty( $type->title ) ? (string) $type->title : (string) $name;
		if ( ! empty( $input['search'] ) && false === stripos( $name . ' ' . $title, sanitize_text_field( $input['search'] ) ) ) { continue; }
		$items[] = array( 'name' => (string) $name, 'title' => $title, 'category' => (string) $type->category, 'supports' => (array) $type->supports, 'attributes' => (array) $type->attributes, 'dynamic' => is_callable( $type->render_callback ) );
	}
	return $items;
}

function samcp_analyze_block_content( $input ) {
	$post = get_post( absint( $input['post_id'] ) );
	if ( ! $post ) { return new WP_Error( 'samcp_content_not_found', __( 'The requested content item was not found.', 'site-abilities-mcp' ) ); }
	$analysis = samcp_block_analysis( $post->post_content );
	$analysis['post_id'] = (int) $post->ID;
	$analysis['post_type'] = (string) $post->post_type;
	return $analysis;
}

function samcp_preview_block_replacement( $input ) {
	$post = get_post( absint( $input['post_id'] ) );
	if ( ! $post ) { return new WP_Error( 'samcp_content_not_found', __( 'The requested content item was not found.', 'site-abilities-mcp' ) ); }
	$current = samcp_block_analysis( $post->post_content );
	$proposed = samcp_block_analysis( $input['proposed_content'] );
	return array( 'post_id' => (int) $post->ID, 'current' => $current, 'proposed' => $proposed, 'added_block_names' => array_values( array_diff( $proposed['block_names'], $current['block_names'] ) ), 'removed_block_names' => array_values( array_diff( $current['block_names'], $proposed['block_names'] ) ), 'length_delta' => strlen( (string) $input['proposed_content'] ) - strlen( (string) $post->post_content ) );
}

function samcp_list_synced_patterns( $input ) {
	$args = array( 'post_type' => 'wp_block', 'post_status' => isset( $input['status'] ) ? $input['status'] : 'any', 'posts_per_page' => isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 20, 'orderby' => 'modified', 'order' => 'DESC' );
	if ( ! empty( $input['search'] ) ) { $args['s'] = sanitize_text_field( $input['search'] ); }
	$posts = array_filter( get_posts( $args ), static function ( $post ) { return current_user_can( 'read_post', $post->ID ); } );
	return array_values( array_map( 'samcp_format_pattern', $posts ) );
}
function samcp_get_synced_pattern( $input ) { $post = samcp_find_pattern( $input['pattern_id'] ); return is_wp_error( $post ) ? $post : samcp_format_pattern( $post ); }

function samcp_validate_block_markup( $content ) {
	$analysis = samcp_block_analysis( $content );
	if ( ! $analysis['has_blocks'] ) { return new WP_Error( 'samcp_blocks_required', __( 'Synced pattern content must contain Gutenberg block markup.', 'site-abilities-mcp' ) ); }
	return true;
}

function samcp_validate_pattern_snapshot( $post, $input ) {
	if ( (string) $post->post_modified_gmt !== (string) $input['expected_modified_gmt'] || ! hash_equals( hash( 'sha256', (string) $post->post_content ), strtolower( (string) $input['expected_content_sha256'] ) ) ) {
		return new WP_Error( 'samcp_pattern_changed', __( 'The synced pattern changed after it was read. Read it again before retrying.', 'site-abilities-mcp' ) );
	}
	return true;
}

function samcp_create_synced_pattern_draft( $input ) {
	if ( empty( $input['confirmation'] ) || 'CREATE_PATTERN_DRAFT' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit CREATE_PATTERN_DRAFT confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_block_markup( $input['content'] ); if ( is_wp_error( $check ) ) { return $check; }
	$id = wp_insert_post( wp_slash( array( 'post_type' => 'wp_block', 'post_status' => 'draft', 'post_title' => sanitize_text_field( $input['title'] ), 'post_content' => (string) $input['content'] ) ), true );
	return is_wp_error( $id ) ? $id : samcp_format_pattern( get_post( $id ) );
}

function samcp_format_pattern_change_result( $action, $post, $revision_id ) {
	return array( 'action' => (string) $action, 'safety_revision_id' => (int) $revision_id, 'pattern' => samcp_format_pattern( $post ) );
}

function samcp_update_synced_pattern( $input ) {
	if ( empty( $input['confirmation'] ) || 'UPDATE_PATTERN' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_PATTERN confirmation is required.', 'site-abilities-mcp' ) ); }
	$post = samcp_find_pattern( $input['pattern_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	$check = samcp_validate_pattern_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	if ( isset( $input['content'] ) ) { $valid = samcp_validate_block_markup( $input['content'] ); if ( is_wp_error( $valid ) ) { return $valid; } }
	$data = array( 'ID' => $post->ID ); if ( isset( $input['title'] ) ) { $data['post_title'] = sanitize_text_field( $input['title'] ); } if ( isset( $input['content'] ) ) { $data['post_content'] = (string) $input['content']; }
	if ( 1 === count( $data ) ) { return new WP_Error( 'samcp_no_changes', __( 'No synced pattern changes were supplied.', 'site-abilities-mcp' ) ); }
	$revision = samcp_create_safety_revision( $post ); if ( is_wp_error( $revision ) ) { return $revision; }
	$id = wp_update_post( wp_slash( $data ), true ); return is_wp_error( $id ) ? $id : samcp_format_pattern_change_result( 'updated_synced_pattern', get_post( $id ), $revision );
}

function samcp_publish_synced_pattern_draft( $input ) {
	if ( empty( $input['confirmation'] ) || 'PUBLISH_PATTERN' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit PUBLISH_PATTERN confirmation is required.', 'site-abilities-mcp' ) ); }
	$post = samcp_find_pattern( $input['pattern_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	if ( 'draft' !== $post->post_status ) { return new WP_Error( 'samcp_pattern_not_draft', __( 'Only a draft synced pattern can be published.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_pattern_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$revision = samcp_create_safety_revision( $post ); if ( is_wp_error( $revision ) ) { return $revision; }
	$id = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ), true ); return is_wp_error( $id ) ? $id : samcp_format_pattern_change_result( 'published_synced_pattern', get_post( $id ), $revision );
}

function samcp_trash_synced_pattern( $input ) {
	if ( empty( $input['confirmation'] ) || 'TRASH_PATTERN' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit TRASH_PATTERN confirmation is required.', 'site-abilities-mcp' ) ); }
	$post = samcp_find_pattern( $input['pattern_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	$check = samcp_validate_pattern_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$revision = samcp_create_safety_revision( $post ); if ( is_wp_error( $revision ) ) { return $revision; }
	$result = wp_trash_post( $post->ID ); return $result ? samcp_format_pattern_change_result( 'trashed_synced_pattern', get_post( $post->ID ), $revision ) : new WP_Error( 'samcp_pattern_trash_failed', __( 'WordPress could not trash the synced pattern.', 'site-abilities-mcp' ) );
}

function samcp_restore_trashed_pattern( $input ) {
	if ( empty( $input['confirmation'] ) || 'RESTORE_TRASHED_PATTERN' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_TRASHED_PATTERN confirmation is required.', 'site-abilities-mcp' ) ); }
	$post = samcp_find_pattern( $input['pattern_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	if ( 'trash' !== $post->post_status ) { return new WP_Error( 'samcp_pattern_not_trashed', __( 'Only a trashed synced pattern can be restored.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_pattern_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$result = wp_untrash_post( $post->ID );
	if ( ! $result ) { return new WP_Error( 'samcp_pattern_restore_failed', __( 'WordPress could not restore the synced pattern.', 'site-abilities-mcp' ) ); }
	$id = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true );
	return is_wp_error( $id ) ? $id : samcp_format_pattern_change_result( 'restored_synced_pattern_as_draft', get_post( $id ), 0 );
}
