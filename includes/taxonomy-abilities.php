<?php
/**
 * Guarded taxonomy and term abilities.
 *
 * @package SiteAbilitiesMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the taxonomy ability category. */
function samcp_register_taxonomy_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'site-abilities-taxonomies',
			array(
				'label'       => __( 'Site Taxonomies', 'site-abilities-mcp' ),
				'description' => __( 'Discovers taxonomies and safely manages terms and content assignments without permanent term deletion.', 'site-abilities-mcp' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_taxonomy_category' );

/** Return a stable term payload schema. */
function samcp_term_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'taxonomy', 'name', 'slug', 'description', 'parent', 'count', 'term_sha256' ),
		'properties' => array(
			'id'          => array( 'type' => 'integer' ),
			'taxonomy'    => array( 'type' => 'string' ),
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'parent'      => array( 'type' => 'integer' ),
			'count'       => array( 'type' => 'integer' ),
			'term_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			'snapshot_id' => array( 'type' => 'string' ),
		),
	);
}

/** Return the taxonomies that may be managed. */
function samcp_allowed_taxonomies() {
	$names = array();
	if ( function_exists( 'get_taxonomies' ) ) {
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $name => $taxonomy ) {
			if ( ! empty( $taxonomy->public ) || ! empty( $taxonomy->show_in_rest ) ) {
				$names[] = sanitize_key( $name );
			}
		}
	} else {
		$names = array( 'category', 'post_tag' );
	}

	if ( function_exists( 'apply_filters' ) ) {
		$names = apply_filters( 'samcp_allowed_taxonomies', $names );
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $names ) ) ) );
}

/** Build an input taxonomy enum. */
function samcp_taxonomy_name_schema() {
	$names = samcp_allowed_taxonomies();
	return $names
		? array( 'type' => 'string', 'enum' => $names )
		: array( 'type' => 'string', 'pattern' => '^[a-z0-9_-]+$' );
}

/** Register taxonomy abilities. */
function samcp_register_taxonomy_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'site-abilities/list-taxonomies',
		array(
			'label'               => __( 'List manageable taxonomies', 'site-abilities-mcp' ),
			'description'         => __( 'Lists public or REST-visible taxonomies that the authenticated user can access.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => array( 'type' => 'object', 'additionalProperties' => false ),
			'output_schema'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'execute_callback'    => 'samcp_list_taxonomies',
			'permission_callback' => 'samcp_can_discover_taxonomies',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/list-terms',
		array(
			'label'               => __( 'List taxonomy terms', 'site-abilities-mcp' ),
			'description'         => __( 'Lists terms in one allowlisted taxonomy without changing the site.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'taxonomy' ),
				'properties'           => array(
					'taxonomy' => samcp_taxonomy_name_schema(),
					'search'   => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
				),
			),
			'output_schema'       => array( 'type' => 'array', 'items' => samcp_term_schema() ),
			'execute_callback'    => 'samcp_list_terms',
			'permission_callback' => 'samcp_can_read_taxonomy',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	$term_id_input = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'taxonomy', 'term_id' ),
		'properties'           => array(
			'taxonomy' => samcp_taxonomy_name_schema(),
			'term_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
		),
	);

	wp_register_ability(
		'site-abilities/get-term',
		array(
			'label'               => __( 'Read taxonomy term', 'site-abilities-mcp' ),
			'description'         => __( 'Reads one taxonomy term and its checksum.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => $term_id_input,
			'output_schema'       => samcp_term_schema(),
			'execute_callback'    => 'samcp_get_term',
			'permission_callback' => 'samcp_can_read_taxonomy',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/create-term',
		array(
			'label'               => __( 'Create taxonomy term', 'site-abilities-mcp' ),
			'description'         => __( 'Creates a term in one allowlisted taxonomy after explicit confirmation.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'taxonomy', 'name', 'confirmation' ),
				'properties'           => array(
					'taxonomy'    => samcp_taxonomy_name_schema(),
					'name'        => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
					'slug'        => array( 'type' => 'string', 'maxLength' => 200 ),
					'description' => array( 'type' => 'string', 'maxLength' => 5000 ),
					'parent'      => array( 'type' => 'integer', 'minimum' => 0 ),
					'confirmation'=> array( 'type' => 'string', 'enum' => array( 'CREATE_TERM' ) ),
				),
			),
			'output_schema'       => samcp_term_schema(),
			'execute_callback'    => 'samcp_create_term',
			'permission_callback' => 'samcp_can_manage_taxonomy_terms',
			'meta'                => samcp_ability_meta( false, false, false ),
		)
	);

	$update_term = $term_id_input;
	$update_term['required'][] = 'expected_term_sha256';
	$update_term['required'][] = 'confirmation';
	$update_term['properties']['expected_term_sha256'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
	$update_term['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( 'UPDATE_TERM' ) );
	$update_term['properties']['name'] = array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 );
	$update_term['properties']['slug'] = array( 'type' => 'string', 'maxLength' => 200 );
	$update_term['properties']['description'] = array( 'type' => 'string', 'maxLength' => 5000 );
	$update_term['properties']['parent'] = array( 'type' => 'integer', 'minimum' => 0 );

	wp_register_ability(
		'site-abilities/update-term',
		array(
			'label'               => __( 'Update taxonomy term', 'site-abilities-mcp' ),
			'description'         => __( 'Updates a term after checksum validation and creates a bounded restorable snapshot.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => $update_term,
			'output_schema'       => samcp_term_schema(),
			'execute_callback'    => 'samcp_update_term',
			'permission_callback' => 'samcp_can_edit_taxonomy_term',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/assign-content-terms',
		array(
			'label'               => __( 'Assign terms to content', 'site-abilities-mcp' ),
			'description'         => __( 'Replaces one content item’s term assignments after content and assignment checksum validation.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'content_id', 'taxonomy', 'term_ids', 'expected_modified_gmt', 'expected_content_sha256', 'expected_assignment_sha256', 'confirmation' ),
				'properties'           => array(
					'content_id'                => array( 'type' => 'integer', 'minimum' => 1 ),
					'taxonomy'                  => samcp_taxonomy_name_schema(),
					'term_ids'                  => array( 'type' => 'array', 'maxItems' => 100, 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'expected_modified_gmt'     => samcp_expected_modified_schema(),
					'expected_content_sha256'   => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
					'expected_assignment_sha256'=> array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
					'confirmation'              => array( 'type' => 'string', 'enum' => array( 'ASSIGN_CONTENT_TERMS' ) ),
				),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => 'samcp_assign_content_terms',
			'permission_callback' => 'samcp_can_assign_content_terms',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/list-term-snapshots',
		array(
			'label'               => __( 'List term snapshots', 'site-abilities-mcp' ),
			'description'         => __( 'Lists bounded recovery snapshots created before term updates.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'taxonomy' => samcp_taxonomy_name_schema(),
					'term_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			'output_schema'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'execute_callback'    => 'samcp_list_term_snapshots',
			'permission_callback' => 'samcp_can_discover_taxonomies',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	$restore = $term_id_input;
	$restore['required'] = array_merge( $restore['required'], array( 'snapshot_id', 'expected_term_sha256', 'confirmation' ) );
	$restore['properties']['snapshot_id'] = array( 'type' => 'string', 'minLength' => 1 );
	$restore['properties']['expected_term_sha256'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
	$restore['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( 'RESTORE_TERM_SNAPSHOT' ) );

	wp_register_ability(
		'site-abilities/restore-term-snapshot',
		array(
			'label'               => __( 'Restore term snapshot', 'site-abilities-mcp' ),
			'description'         => __( 'Restores one term snapshot after checksum validation and saves the current state first.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-taxonomies',
			'input_schema'        => $restore,
			'output_schema'       => samcp_term_schema(),
			'execute_callback'    => 'samcp_restore_term_snapshot',
			'permission_callback' => 'samcp_can_edit_taxonomy_term',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);
}
add_action( 'wp_abilities_api_init', 'samcp_register_taxonomy_abilities' );

/** Resolve one allowed taxonomy object. */
function samcp_get_allowed_taxonomy( $name ) {
	$name = sanitize_key( $name );
	if ( ! in_array( $name, samcp_allowed_taxonomies(), true ) ) {
		return new WP_Error( 'samcp_taxonomy_not_allowed', __( 'The requested taxonomy is not allowlisted.', 'site-abilities-mcp' ) );
	}
	$taxonomy = get_taxonomy( $name );
	return $taxonomy ? $taxonomy : new WP_Error( 'samcp_taxonomy_not_found', __( 'The requested taxonomy was not found.', 'site-abilities-mcp' ) );
}

/** Hash the editable term state. */
function samcp_term_hash( $term ) {
	return hash( 'sha256', implode( "\n", array( $term->taxonomy, $term->name, $term->slug, $term->description, (string) $term->parent ) ) );
}

/** Format one term. */
function samcp_format_term( $term, $snapshot_id = '' ) {
	return array(
		'id'          => (int) $term->term_id,
		'taxonomy'    => (string) $term->taxonomy,
		'name'        => (string) $term->name,
		'slug'        => (string) $term->slug,
		'description' => (string) $term->description,
		'parent'      => (int) $term->parent,
		'count'       => (int) $term->count,
		'term_sha256' => samcp_term_hash( $term ),
		'snapshot_id' => (string) $snapshot_id,
	);
}

/** Read one term and verify its taxonomy. */
function samcp_find_term( $term_id, $taxonomy ) {
	$term = get_term( absint( $term_id ), sanitize_key( $taxonomy ) );
	return ( ! $term || is_wp_error( $term ) )
		? new WP_Error( 'samcp_term_not_found', __( 'The requested term was not found in that taxonomy.', 'site-abilities-mcp' ) )
		: $term;
}

/** Return the current term assignment hash for one object. */
function samcp_term_assignment_state( $content_id, $taxonomy ) {
	$terms = wp_get_object_terms( absint( $content_id ), sanitize_key( $taxonomy ), array( 'orderby' => 'term_id', 'order' => 'ASC' ) );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$ids = array_map( static function ( $term ) { return (int) $term->term_id; }, $terms );
	sort( $ids, SORT_NUMERIC );
	return array(
		'term_ids'          => $ids,
		'terms'             => array_map( 'samcp_format_term', $terms ),
		'assignment_sha256' => hash( 'sha256', implode( ',', $ids ) ),
	);
}

/** Permission callbacks. */
function samcp_can_discover_taxonomies() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); }
function samcp_can_read_taxonomy( $input ) {
	$taxonomy = ! empty( $input['taxonomy'] ) ? samcp_get_allowed_taxonomy( $input['taxonomy'] ) : null;
	return $taxonomy && ! is_wp_error( $taxonomy ) && current_user_can( $taxonomy->cap->assign_terms );
}
function samcp_can_manage_taxonomy_terms( $input ) {
	$taxonomy = ! empty( $input['taxonomy'] ) ? samcp_get_allowed_taxonomy( $input['taxonomy'] ) : null;
	return $taxonomy && ! is_wp_error( $taxonomy ) && current_user_can( $taxonomy->cap->manage_terms );
}
function samcp_can_edit_taxonomy_term( $input ) {
	$taxonomy = ! empty( $input['taxonomy'] ) ? samcp_get_allowed_taxonomy( $input['taxonomy'] ) : null;
	return $taxonomy && ! is_wp_error( $taxonomy ) && current_user_can( $taxonomy->cap->edit_terms );
}
function samcp_can_assign_content_terms( $input ) {
	if ( empty( $input['content_id'] ) || empty( $input['taxonomy'] ) || ! current_user_can( 'edit_post', absint( $input['content_id'] ) ) ) {
		return false;
	}
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	$post = get_post( absint( $input['content_id'] ) );
	return $post && ! is_wp_error( $taxonomy ) && is_object_in_taxonomy( $post->post_type, $taxonomy->name ) && current_user_can( $taxonomy->cap->assign_terms );
}

/** Ability callbacks. */
function samcp_list_taxonomies() {
	$items = array();
	foreach ( samcp_allowed_taxonomies() as $name ) {
		$taxonomy = get_taxonomy( $name );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			continue;
		}
		$items[] = array(
			'name'         => (string) $taxonomy->name,
			'label'        => (string) $taxonomy->label,
			'hierarchical' => (bool) $taxonomy->hierarchical,
			'object_types' => array_values( (array) $taxonomy->object_type ),
			'can_manage'   => current_user_can( $taxonomy->cap->manage_terms ),
			'can_edit'     => current_user_can( $taxonomy->cap->edit_terms ),
			'can_assign'   => current_user_can( $taxonomy->cap->assign_terms ),
		);
	}
	return $items;
}

function samcp_list_terms( $input ) {
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	if ( is_wp_error( $taxonomy ) ) {
		return $taxonomy;
	}
	$args = array(
		'taxonomy'   => $taxonomy->name,
		'hide_empty' => false,
		'number'     => isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 50,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);
	if ( ! empty( $input['search'] ) ) {
		$args['search'] = sanitize_text_field( $input['search'] );
	}
	$terms = get_terms( $args );
	return is_wp_error( $terms ) ? $terms : array_map( 'samcp_format_term', $terms );
}

function samcp_get_term( $input ) {
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	if ( is_wp_error( $taxonomy ) ) {
		return $taxonomy;
	}
	$term = samcp_find_term( $input['term_id'], $taxonomy->name );
	return is_wp_error( $term ) ? $term : samcp_format_term( $term );
}

function samcp_validate_term_parent( $taxonomy, $parent, $term_id = 0 ) {
	$parent = absint( $parent );
	if ( ! $parent ) {
		return 0;
	}
	if ( ! $taxonomy->hierarchical || $parent === absint( $term_id ) || ! term_exists( $parent, $taxonomy->name ) ) {
		return new WP_Error( 'samcp_term_parent_invalid', __( 'The supplied parent is not valid for this taxonomy.', 'site-abilities-mcp' ) );
	}
	return $parent;
}

function samcp_create_term( $input ) {
	if ( empty( $input['confirmation'] ) || 'CREATE_TERM' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit CREATE_TERM confirmation is required.', 'site-abilities-mcp' ) );
	}
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	if ( is_wp_error( $taxonomy ) ) {
		return $taxonomy;
	}
	$parent = samcp_validate_term_parent( $taxonomy, isset( $input['parent'] ) ? $input['parent'] : 0 );
	if ( is_wp_error( $parent ) ) {
		return $parent;
	}
	$args = array(
		'description' => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
		'parent'      => $parent,
	);
	if ( isset( $input['slug'] ) && '' !== $input['slug'] ) {
		$args['slug'] = sanitize_title( $input['slug'] );
	}
	$result = wp_insert_term( sanitize_text_field( $input['name'] ), $taxonomy->name, $args );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return samcp_format_term( get_term( $result['term_id'], $taxonomy->name ) );
}

function samcp_save_term_snapshot( $term, $reason ) {
	$id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'term-', true );
	$items = (array) get_option( 'samcp_term_snapshots', array() );
	array_unshift(
		$items,
		array(
			'id'          => $id,
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => (string) $term->taxonomy,
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'user_id'     => get_current_user_id(),
			'reason'      => (string) $reason,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
		)
	);
	update_option( 'samcp_term_snapshots', array_slice( $items, 0, 50 ), false );
	return $id;
}

function samcp_update_term( $input ) {
	if ( empty( $input['confirmation'] ) || 'UPDATE_TERM' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_TERM confirmation is required.', 'site-abilities-mcp' ) );
	}
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	$term = is_wp_error( $taxonomy ) ? $taxonomy : samcp_find_term( $input['term_id'], $taxonomy->name );
	if ( is_wp_error( $term ) ) {
		return $term;
	}
	if ( ! hash_equals( samcp_term_hash( $term ), strtolower( (string) $input['expected_term_sha256'] ) ) ) {
		return new WP_Error( 'samcp_term_changed', __( 'The term changed after it was read. Read it again before retrying.', 'site-abilities-mcp' ) );
	}
	$args = array();
	foreach ( array( 'name', 'slug', 'description' ) as $field ) {
		if ( array_key_exists( $field, $input ) ) {
			$args[ $field ] = 'slug' === $field ? sanitize_title( $input[ $field ] ) : ( 'description' === $field ? sanitize_textarea_field( $input[ $field ] ) : sanitize_text_field( $input[ $field ] ) );
		}
	}
	if ( array_key_exists( 'parent', $input ) ) {
		$args['parent'] = samcp_validate_term_parent( $taxonomy, $input['parent'], $term->term_id );
		if ( is_wp_error( $args['parent'] ) ) {
			return $args['parent'];
		}
	}
	if ( ! $args ) {
		return new WP_Error( 'samcp_no_changes', __( 'No term changes were supplied.', 'site-abilities-mcp' ) );
	}
	$snapshot_id = samcp_save_term_snapshot( $term, 'before_update' );
	$result = wp_update_term( $term->term_id, $taxonomy->name, $args );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return samcp_format_term( get_term( $term->term_id, $taxonomy->name ), $snapshot_id );
}

function samcp_assign_content_terms( $input ) {
	if ( empty( $input['confirmation'] ) || 'ASSIGN_CONTENT_TERMS' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit ASSIGN_CONTENT_TERMS confirmation is required.', 'site-abilities-mcp' ) );
	}
	$post = get_post( absint( $input['content_id'] ) );
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	if ( ! $post || is_wp_error( $taxonomy ) ) {
		return is_wp_error( $taxonomy ) ? $taxonomy : new WP_Error( 'samcp_content_not_found', __( 'The requested content item was not found.', 'site-abilities-mcp' ) );
	}
	$check = samcp_validate_page_snapshot( $post, $input );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$current = samcp_term_assignment_state( $post->ID, $taxonomy->name );
	if ( is_wp_error( $current ) ) {
		return $current;
	}
	if ( ! hash_equals( $current['assignment_sha256'], strtolower( (string) $input['expected_assignment_sha256'] ) ) ) {
		return new WP_Error( 'samcp_term_assignment_changed', __( 'Term assignments changed after they were read. Read them again before retrying.', 'site-abilities-mcp' ) );
	}
	$term_ids = array_values( array_unique( array_map( 'absint', $input['term_ids'] ) ) );
	foreach ( $term_ids as $term_id ) {
		if ( ! term_exists( $term_id, $taxonomy->name ) ) {
			return new WP_Error( 'samcp_term_not_found', __( 'One or more supplied term IDs do not belong to the taxonomy.', 'site-abilities-mcp' ) );
		}
	}
	$safety = samcp_create_content_safety_point( $post, 'before_term_assignment' );
	if ( is_wp_error( $safety ) ) {
		return $safety;
	}
	$result = wp_set_object_terms( $post->ID, $term_ids, $taxonomy->name, false );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$state = samcp_term_assignment_state( $post->ID, $taxonomy->name );
	$state['content_id'] = (int) $post->ID;
	$state['taxonomy'] = (string) $taxonomy->name;
	$state['safety_revision_id'] = (int) $safety['revision_id'];
	$state['safety_snapshot_id'] = (string) $safety['snapshot_id'];
	return $state;
}

function samcp_list_term_snapshots( $input ) {
	$items = (array) get_option( 'samcp_term_snapshots', array() );
	$items = array_filter(
		$items,
		static function ( $item ) use ( $input ) {
			$taxonomy = samcp_get_allowed_taxonomy( $item['taxonomy'] );
			if ( is_wp_error( $taxonomy ) || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
				return false;
			}
			return ( empty( $input['taxonomy'] ) || $input['taxonomy'] === $item['taxonomy'] ) && ( empty( $input['term_id'] ) || absint( $input['term_id'] ) === absint( $item['term_id'] ) );
		}
	);
	return array_values( $items );
}

function samcp_restore_term_snapshot( $input ) {
	if ( empty( $input['confirmation'] ) || 'RESTORE_TERM_SNAPSHOT' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_TERM_SNAPSHOT confirmation is required.', 'site-abilities-mcp' ) );
	}
	$taxonomy = samcp_get_allowed_taxonomy( $input['taxonomy'] );
	$term = is_wp_error( $taxonomy ) ? $taxonomy : samcp_find_term( $input['term_id'], $taxonomy->name );
	if ( is_wp_error( $term ) ) {
		return $term;
	}
	if ( ! hash_equals( samcp_term_hash( $term ), strtolower( (string) $input['expected_term_sha256'] ) ) ) {
		return new WP_Error( 'samcp_term_changed', __( 'The term changed after it was read. Read it again before retrying.', 'site-abilities-mcp' ) );
	}
	$snapshot = null;
	foreach ( (array) get_option( 'samcp_term_snapshots', array() ) as $candidate ) {
		if ( hash_equals( (string) $candidate['id'], (string) $input['snapshot_id'] ) && absint( $candidate['term_id'] ) === $term->term_id && $candidate['taxonomy'] === $taxonomy->name ) {
			$snapshot = $candidate;
			break;
		}
	}
	if ( ! $snapshot ) {
		return new WP_Error( 'samcp_term_snapshot_not_found', __( 'The requested term snapshot was not found.', 'site-abilities-mcp' ) );
	}
	$safety_id = samcp_save_term_snapshot( $term, 'before_restore' );
	$result = wp_update_term(
		$term->term_id,
		$taxonomy->name,
		array(
			'name'        => $snapshot['name'],
			'slug'        => $snapshot['slug'],
			'description' => $snapshot['description'],
			'parent'      => absint( $snapshot['parent'] ),
		)
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return samcp_format_term( get_term( $term->term_id, $taxonomy->name ), $safety_id );
}
