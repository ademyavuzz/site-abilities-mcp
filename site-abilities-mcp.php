<?php
/**
 * Plugin Name: Site Abilities MCP
 * Plugin URI:  https://github.com/ademyavuzz/site-abilities-mcp
 * Description: Exposes controlled WordPress content-management abilities through the official MCP Adapter.
 * Version:     0.1.0-alpha
 * Author:      Adem Yavuz
 * License:     GPL-2.0-or-later
 * Text Domain: site-abilities-mcp
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the active MCP exposure profile.
 *
 * The default profile only exposes read-only abilities. Site owners may set
 * SITE_ABILITIES_MCP_PROFILE to "full_access" in wp-config.php after they
 * review the capabilities granted to their dedicated MCP user.
 *
 * @return string
 */
function samcp_get_profile() {
	$profile = defined( 'SITE_ABILITIES_MCP_PROFILE' )
		? SITE_ABILITIES_MCP_PROFILE
		: 'read_only';

	if ( function_exists( 'apply_filters' ) ) {
		$profile = apply_filters( 'samcp_profile', $profile );
	}

	return 'full_access' === $profile ? 'full_access' : 'read_only';
}

/**
 * Determine whether a given ability should be discoverable over MCP.
 *
 * @param bool $readonly Whether the ability only reads data.
 * @return bool
 */
function samcp_ability_is_public( $readonly ) {
	return $readonly || 'full_access' === samcp_get_profile();
}

/**
 * Register the category used by the Site Abilities MCP abilities.
 */
function samcp_register_page_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'site-abilities-pages',
		array(
			'label'       => __( 'Site Pages', 'site-abilities-mcp' ),
			'description' => __( 'Safely reads, drafts, publishes and restores WordPress pages for the site.', 'site-abilities-mcp' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_page_category' );

/**
 * Register public MCP abilities.
 */
function samcp_register_page_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'site-abilities/list-pages',
		array(
			'label'               => __( 'List WordPress pages', 'site-abilities-mcp' ),
			'description'         => __( 'Lists WordPress pages without changing the site. Use this to find a page ID before reading or editing a draft.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Optional title or content search text.', 'site-abilities-mcp' ),
					),
					'status'   => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ),
						'default'     => 'any',
						'description' => __( 'Page status to return.', 'site-abilities-mcp' ),
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => 20,
						'description' => __( 'Maximum number of pages to return.', 'site-abilities-mcp' ),
					),
				),
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => samcp_page_summary_schema(),
			),
			'execute_callback'    => 'samcp_list_pages',
			'permission_callback' => 'samcp_can_list_pages',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/get-page',
		array(
			'label'               => __( 'Read a WordPress page', 'site-abilities-mcp' ),
			'description'         => __( 'Reads the title, content, excerpt and metadata of one WordPress page without changing the site.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'page_id' ),
				'properties'           => array(
					'page_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Numeric WordPress page ID.', 'site-abilities-mcp' ),
					),
				),
			),
			'output_schema'       => samcp_full_page_schema(),
			'execute_callback'    => 'samcp_get_page',
			'permission_callback' => 'samcp_can_read_page',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/create-page-draft',
		array(
			'label'               => __( 'Create a WordPress page draft', 'site-abilities-mcp' ),
			'description'         => __( 'Creates a new WordPress page as a draft. It never publishes the page.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_draft_input_schema( true ),
			'output_schema'       => samcp_full_page_schema(),
			'execute_callback'    => 'samcp_create_page_draft',
			'permission_callback' => 'samcp_can_create_page_draft',
			'meta'                => samcp_ability_meta( false, false, false ),
		)
	);

	wp_register_ability(
		'site-abilities/update-page-draft',
		array(
			'label'               => __( 'Update a WordPress page draft', 'site-abilities-mcp' ),
			'description'         => __( 'Updates the title, content or excerpt of an existing draft page. Published, private and pending pages are rejected.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_draft_input_schema( false ),
			'output_schema'       => samcp_full_page_schema(),
			'execute_callback'    => 'samcp_update_page_draft',
			'permission_callback' => 'samcp_can_update_page_draft',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/update-published-page',
		array(
			'label'               => __( 'Update a published WordPress page', 'site-abilities-mcp' ),
			'description'         => __( 'Updates an existing published page only after snapshot validation and creation of a restorable WordPress revision. Use only after the user explicitly approves the live change.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_published_update_input_schema(),
			'output_schema'       => samcp_change_result_schema(),
			'execute_callback'    => 'samcp_update_published_page',
			'permission_callback' => 'samcp_can_manage_published_page',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/publish-page-draft',
		array(
			'label'               => __( 'Publish a WordPress page draft', 'site-abilities-mcp' ),
			'description'         => __( 'Publishes an existing draft page after snapshot validation and creation of a restorable WordPress revision. Use only after the user explicitly approves publication.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_publish_input_schema(),
			'output_schema'       => samcp_change_result_schema(),
			'execute_callback'    => 'samcp_publish_page_draft',
			'permission_callback' => 'samcp_can_manage_published_page',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/list-page-revisions',
		array(
			'label'               => __( 'List WordPress page revisions', 'site-abilities-mcp' ),
			'description'         => __( 'Lists restorable revisions for a WordPress page without changing the site.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_revision_list_input_schema(),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => samcp_revision_summary_schema(),
			),
			'execute_callback'    => 'samcp_list_page_revisions',
			'permission_callback' => 'samcp_can_read_page',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/get-page-revision',
		array(
			'label'               => __( 'Read a WordPress page revision', 'site-abilities-mcp' ),
			'description'         => __( 'Reads the complete title, content and excerpt of one page revision without changing the site.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_revision_input_schema( false ),
			'output_schema'       => samcp_full_revision_schema(),
			'execute_callback'    => 'samcp_get_page_revision',
			'permission_callback' => 'samcp_can_read_page',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/restore-page-revision',
		array(
			'label'               => __( 'Restore a WordPress page revision', 'site-abilities-mcp' ),
			'description'         => __( 'Restores title, content and excerpt from a selected page revision after snapshot validation and creation of a new safety revision. The page status is preserved.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_revision_input_schema( true ),
			'output_schema'       => samcp_change_result_schema(),
			'execute_callback'    => 'samcp_restore_page_revision',
			'permission_callback' => 'samcp_can_restore_page_revision',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/trash-page',
		array(
			'label'               => __( 'Move a WordPress page to trash', 'site-abilities-mcp' ),
			'description'         => __( 'Moves a page to the WordPress trash after snapshot validation, explicit confirmation and creation of a safety revision. It never permanently deletes a page.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_page_status_change_input_schema( 'TRASH' ),
			'output_schema'       => samcp_change_result_schema(),
			'execute_callback'    => 'samcp_trash_page',
			'permission_callback' => 'samcp_can_trash_page',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/restore-trashed-page',
		array(
			'label'               => __( 'Restore a trashed WordPress page', 'site-abilities-mcp' ),
			'description'         => __( 'Restores a page from trash as a draft after snapshot validation and explicit confirmation. It does not publish the page.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-pages',
			'input_schema'        => samcp_page_status_change_input_schema( 'RESTORE_TRASHED' ),
			'output_schema'       => samcp_change_result_schema(),
			'execute_callback'    => 'samcp_restore_trashed_page',
			'permission_callback' => 'samcp_can_trash_page',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);
}
add_action( 'wp_abilities_api_init', 'samcp_register_page_abilities' );

/**
 * Metadata shared by MCP abilities.
 *
 * @param bool $readonly    Whether the ability only reads data.
 * @param bool $destructive Whether the ability can replace existing data.
 * @param bool $idempotent  Whether repeating the operation has no extra effect.
 * @param bool $open_world  Whether the ability can contact arbitrary external entities.
 * @return array
 */
function samcp_ability_meta( $readonly, $destructive, $idempotent, $open_world = false ) {
	return array(
		'annotations' => array(
			'readonly'      => $readonly,
			'destructive'   => $destructive,
			'idempotent'    => $idempotent,
			'openWorldHint' => (bool) $open_world,
		),
		'mcp'         => array(
			'public' => samcp_ability_is_public( $readonly ),
		),
	);
}

/**
 * Schema for a page summary.
 *
 * @return array
 */
function samcp_page_summary_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'title', 'status', 'slug', 'modified_gmt', 'parent_id' ),
		'properties' => array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'modified_gmt' => array( 'type' => 'string' ),
			'parent_id'    => array( 'type' => 'integer' ),
		),
	);
}

/**
 * Schema for a complete page payload.
 *
 * @return array
 */
function samcp_full_page_schema() {
	$schema = samcp_page_summary_schema();
	$schema['required'][] = 'content';
	$schema['required'][] = 'excerpt';
	$schema['required'][] = 'content_sha256';
	$schema['properties']['content'] = array( 'type' => 'string' );
	$schema['properties']['excerpt'] = array( 'type' => 'string' );
	$schema['properties']['content_sha256'] = array(
		'type'        => 'string',
		'pattern'     => '^[a-f0-9]{64}$',
		'description' => __( 'SHA-256 checksum used to prevent accidental overwrites.', 'site-abilities-mcp' ),
	);

	return $schema;
}

/**
 * Schema accepted by draft create/update abilities.
 *
 * @param bool $creating Whether the schema is for creation.
 * @return array
 */
function samcp_draft_input_schema( $creating ) {
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => array(
			'title'   => array(
				'type'        => 'string',
				'maxLength'   => 500,
				'description' => __( 'Page title.', 'site-abilities-mcp' ),
			),
			'content' => array(
				'type'        => 'string',
				'description' => __( 'Page content, including valid block markup when needed.', 'site-abilities-mcp' ),
			),
			'excerpt' => array(
				'type'        => 'string',
				'description' => __( 'Optional page excerpt.', 'site-abilities-mcp' ),
			),
		),
	);

	if ( $creating ) {
		$schema['required'] = array( 'title' );
		$schema['properties']['title']['minLength'] = 1;
	} else {
		$schema['required'] = array( 'page_id', 'expected_modified_gmt', 'expected_content_sha256' );
		$schema['properties'] = array_merge(
			array(
				'page_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Numeric ID of the draft page to update.', 'site-abilities-mcp' ),
				),
				'expected_modified_gmt' => samcp_expected_modified_schema(),
				'expected_content_sha256' => samcp_expected_hash_schema(),
			),
			$schema['properties']
		);
	}

	return $schema;
}

/**
 * Schema for an expected WordPress modified timestamp.
 *
 * @return array
 */
function samcp_expected_modified_schema() {
	return array(
		'type'        => 'string',
		'pattern'     => '^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$',
		'description' => __( 'Exact modified_gmt value returned by site-abilities/get-page.', 'site-abilities-mcp' ),
	);
}

/**
 * Schema for an expected content checksum.
 *
 * @return array
 */
function samcp_expected_hash_schema() {
	return array(
		'type'        => 'string',
		'pattern'     => '^[a-f0-9]{64}$',
		'description' => __( 'Exact content_sha256 value returned by site-abilities/get-page.', 'site-abilities-mcp' ),
	);
}

/**
 * Input schema for updating a published page.
 *
 * @return array
 */
function samcp_published_update_input_schema() {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'page_id', 'expected_modified_gmt', 'expected_content_sha256', 'confirmation' ),
		'properties'           => array(
			'page_id'                => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Numeric ID of the published page to update.', 'site-abilities-mcp' ),
			),
			'expected_modified_gmt'   => samcp_expected_modified_schema(),
			'expected_content_sha256' => samcp_expected_hash_schema(),
			'confirmation'            => array(
				'type'        => 'string',
				'enum'        => array( 'UPDATE_PUBLISHED' ),
				'description' => __( 'Required explicit confirmation token for a live update.', 'site-abilities-mcp' ),
			),
			'title'                   => array(
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 500,
				'description' => __( 'Replacement page title.', 'site-abilities-mcp' ),
			),
			'content'                 => array(
				'type'        => 'string',
				'description' => __( 'Replacement page content, including valid block or WPBakery markup.', 'site-abilities-mcp' ),
			),
			'excerpt'                 => array(
				'type'        => 'string',
				'description' => __( 'Replacement page excerpt.', 'site-abilities-mcp' ),
			),
		),
	);
}

/**
 * Input schema for publishing a draft.
 *
 * @return array
 */
function samcp_publish_input_schema() {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'page_id', 'expected_modified_gmt', 'expected_content_sha256', 'confirmation' ),
		'properties'           => array(
			'page_id'                => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Numeric ID of the draft page to publish.', 'site-abilities-mcp' ),
			),
			'expected_modified_gmt'   => samcp_expected_modified_schema(),
			'expected_content_sha256' => samcp_expected_hash_schema(),
			'confirmation'            => array(
				'type'        => 'string',
				'enum'        => array( 'PUBLISH' ),
				'description' => __( 'Required explicit confirmation token for publication.', 'site-abilities-mcp' ),
			),
		),
	);
}

/**
 * Input schema for recoverable page status changes.
 *
 * @param string $confirmation Required confirmation token.
 * @return array
 */
function samcp_page_status_change_input_schema( $confirmation ) {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'page_id', 'expected_modified_gmt', 'expected_content_sha256', 'confirmation' ),
		'properties'           => array(
			'page_id'                 => array( 'type' => 'integer', 'minimum' => 1 ),
			'expected_modified_gmt'   => samcp_expected_modified_schema(),
			'expected_content_sha256' => samcp_expected_hash_schema(),
			'confirmation'            => array( 'type' => 'string', 'enum' => array( $confirmation ) ),
		),
	);
}

/**
 * Input schema for listing page revisions.
 *
 * @return array
 */
function samcp_revision_list_input_schema() {
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'page_id' ),
		'properties'           => array(
			'page_id'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Numeric WordPress page ID.', 'site-abilities-mcp' ),
			),
			'per_page' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 50,
				'default'     => 10,
				'description' => __( 'Maximum number of revisions to return.', 'site-abilities-mcp' ),
			),
		),
	);
}

/**
 * Input schema for reading or restoring a revision.
 *
 * @param bool $restoring Whether the schema is for restoration.
 * @return array
 */
function samcp_revision_input_schema( $restoring ) {
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'page_id', 'revision_id' ),
		'properties'           => array(
			'page_id'     => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Numeric WordPress page ID.', 'site-abilities-mcp' ),
			),
			'revision_id' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Numeric revision ID belonging to the page.', 'site-abilities-mcp' ),
			),
		),
	);

	if ( $restoring ) {
		$schema['required'][] = 'expected_modified_gmt';
		$schema['required'][] = 'expected_content_sha256';
		$schema['required'][] = 'confirmation';
		$schema['properties']['expected_modified_gmt'] = samcp_expected_modified_schema();
		$schema['properties']['expected_content_sha256'] = samcp_expected_hash_schema();
		$schema['properties']['confirmation'] = array(
			'type'        => 'string',
			'enum'        => array( 'RESTORE' ),
			'description' => __( 'Required explicit confirmation token for restoration.', 'site-abilities-mcp' ),
		);
	}

	return $schema;
}

/**
 * Schema for a revision summary.
 *
 * @return array
 */
function samcp_revision_summary_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'page_id', 'author_id', 'date_gmt', 'modified_gmt', 'title', 'content_sha256' ),
		'properties' => array(
			'id'             => array( 'type' => 'integer' ),
			'page_id'        => array( 'type' => 'integer' ),
			'author_id'      => array( 'type' => 'integer' ),
			'date_gmt'       => array( 'type' => 'string' ),
			'modified_gmt'   => array( 'type' => 'string' ),
			'title'          => array( 'type' => 'string' ),
			'content_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
		),
	);
}

/**
 * Schema for a complete revision.
 *
 * @return array
 */
function samcp_full_revision_schema() {
	$schema = samcp_revision_summary_schema();
	$schema['required'][] = 'content';
	$schema['required'][] = 'excerpt';
	$schema['properties']['content'] = array( 'type' => 'string' );
	$schema['properties']['excerpt'] = array( 'type' => 'string' );

	return $schema;
}

/**
 * Schema returned by revision-safe mutations.
 *
 * @return array
 */
function samcp_change_result_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'action', 'safety_revision_id', 'page' ),
		'properties' => array(
			'action'             => array( 'type' => 'string' ),
			'safety_revision_id' => array( 'type' => 'integer' ),
			'page'               => samcp_full_page_schema(),
		),
	);
}

/**
 * Return a page as a stable MCP payload.
 *
 * @param WP_Post $page Page object.
 * @param bool    $with_content Include content and excerpt.
 * @return array
 */
function samcp_format_page( $page, $with_content = true ) {
	$result = array(
		'id'           => (int) $page->ID,
		'title'        => (string) $page->post_title,
		'status'       => (string) $page->post_status,
		'slug'         => (string) $page->post_name,
		'modified_gmt' => (string) $page->post_modified_gmt,
		'parent_id'    => (int) $page->post_parent,
	);

	if ( $with_content ) {
		$result['content']        = (string) $page->post_content;
		$result['excerpt']        = (string) $page->post_excerpt;
		$result['content_sha256'] = hash( 'sha256', (string) $page->post_content );
	}

	return $result;
}

/**
 * Fetch a page or return a useful error.
 *
 * @param int $page_id Page ID.
 * @return WP_Post|WP_Error
 */
function samcp_find_page( $page_id ) {
	$page = get_post( absint( $page_id ) );

	if ( ! $page || 'page' !== $page->post_type ) {
		return new WP_Error( 'samcp_page_not_found', __( 'The requested WordPress page was not found.', 'site-abilities-mcp' ) );
	}

	return $page;
}

/**
 * Fetch a revision and verify that it belongs to a page.
 *
 * @param int $page_id     Parent page ID.
 * @param int $revision_id Revision ID.
 * @return WP_Post|WP_Error
 */
function samcp_find_page_revision( $page_id, $revision_id ) {
	$revision = wp_get_post_revision( absint( $revision_id ) );

	if ( ! $revision || absint( $revision->post_parent ) !== absint( $page_id ) ) {
		return new WP_Error( 'samcp_revision_not_found', __( 'The requested revision does not belong to this page.', 'site-abilities-mcp' ) );
	}

	return $revision;
}

/**
 * Return a revision as a stable MCP payload.
 *
 * @param WP_Post $revision Revision object.
 * @param bool    $with_content Include content and excerpt.
 * @return array
 */
function samcp_format_revision( $revision, $with_content = true ) {
	$result = array(
		'id'             => (int) $revision->ID,
		'page_id'        => (int) $revision->post_parent,
		'author_id'      => (int) $revision->post_author,
		'date_gmt'       => (string) $revision->post_date_gmt,
		'modified_gmt'   => (string) $revision->post_modified_gmt,
		'title'          => (string) $revision->post_title,
		'content_sha256' => hash( 'sha256', (string) $revision->post_content ),
	);

	if ( $with_content ) {
		$result['content'] = (string) $revision->post_content;
		$result['excerpt'] = (string) $revision->post_excerpt;
	}

	return $result;
}

/**
 * Reject stale writes by comparing a previously read page snapshot.
 *
 * @param WP_Post $page  Current page object.
 * @param array   $input Ability input.
 * @return true|WP_Error
 */
function samcp_validate_page_snapshot( $page, $input ) {
	$expected_modified = isset( $input['expected_modified_gmt'] ) ? (string) $input['expected_modified_gmt'] : '';
	$expected_hash     = isset( $input['expected_content_sha256'] ) ? strtolower( (string) $input['expected_content_sha256'] ) : '';
	$current_hash      = hash( 'sha256', (string) $page->post_content );

	if ( $expected_modified !== (string) $page->post_modified_gmt || ! hash_equals( $current_hash, $expected_hash ) ) {
		return new WP_Error(
			'samcp_page_changed',
			__( 'The page changed after it was read. Read it again and review the latest content before retrying.', 'site-abilities-mcp' ),
			array(
				'current_modified_gmt'   => (string) $page->post_modified_gmt,
				'current_content_sha256' => $current_hash,
			)
		);
	}

	return true;
}

/**
 * Create or locate an exact revision of the current page before mutation.
 *
 * @param WP_Post $page Current page object.
 * @return int|WP_Error
 */
function samcp_create_safety_revision( $page ) {
	if ( ! wp_revisions_enabled( $page ) ) {
		return new WP_Error( 'samcp_revisions_disabled', __( 'WordPress revisions are disabled for this page. The live operation was stopped.', 'site-abilities-mcp' ) );
	}

	$revision_id = wp_save_post_revision( $page->ID );

	if ( is_wp_error( $revision_id ) ) {
		return $revision_id;
	}

	if ( $revision_id ) {
		return (int) $revision_id;
	}

	$revisions = wp_get_post_revisions(
		$page->ID,
		array(
			'posts_per_page' => 50,
			'orderby'        => 'date ID',
			'order'          => 'DESC',
		)
	);

	foreach ( $revisions as $revision ) {
		if (
			(string) $revision->post_title === (string) $page->post_title &&
			(string) $revision->post_content === (string) $page->post_content &&
			(string) $revision->post_excerpt === (string) $page->post_excerpt
		) {
			return (int) $revision->ID;
		}
	}

	return new WP_Error( 'samcp_revision_failed', __( 'A safety revision could not be created or verified. The live operation was stopped.', 'site-abilities-mcp' ) );
}

/**
 * Return whether input contains a meaningful content change.
 *
 * @param WP_Post $page  Current page object.
 * @param array   $input Ability input.
 * @return bool
 */
function samcp_has_page_changes( $page, $input ) {
	$fields = array(
		'title'   => 'post_title',
		'content' => 'post_content',
		'excerpt' => 'post_excerpt',
	);

	foreach ( $fields as $input_key => $post_key ) {
		if ( array_key_exists( $input_key, $input ) && (string) $input[ $input_key ] !== (string) $page->{$post_key} ) {
			return true;
		}
	}

	return false;
}

/**
 * Build a page update array from ability input.
 *
 * @param WP_Post $page   Current page object.
 * @param array   $input  Ability input.
 * @param string  $status Status to preserve.
 * @return array
 */
function samcp_build_page_update( $page, $input, $status ) {
	$postarr = array(
		'ID'          => $page->ID,
		'post_status' => $status,
	);

	if ( array_key_exists( 'title', $input ) ) {
		$postarr['post_title'] = sanitize_text_field( $input['title'] );
	}

	if ( array_key_exists( 'content', $input ) ) {
		$postarr['post_content'] = (string) $input['content'];
	}

	if ( array_key_exists( 'excerpt', $input ) ) {
		$postarr['post_excerpt'] = (string) $input['excerpt'];
	}

	return $postarr;
}

/**
 * Permission check for listing pages.
 *
 * @return bool
 */
function samcp_can_list_pages() {
	return current_user_can( 'edit_pages' );
}

/**
 * Permission check for reading a page.
 *
 * @param array $input Ability input.
 * @return bool
 */
function samcp_can_read_page( $input ) {
	$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

	return $page_id > 0 && current_user_can( 'read_post', $page_id );
}

/**
 * Permission check for creating a page draft.
 *
 * @return bool
 */
function samcp_can_create_page_draft() {
	return current_user_can( 'edit_pages' );
}

/**
 * Permission check for updating a page draft.
 *
 * @param array $input Ability input.
 * @return bool
 */
function samcp_can_update_page_draft( $input ) {
	$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

	return $page_id > 0 && current_user_can( 'edit_post', $page_id );
}

/**
 * Permission check for changing or publishing a page.
 *
 * @param array $input Ability input.
 * @return bool
 */
function samcp_can_manage_published_page( $input ) {
	$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

	return $page_id > 0 && current_user_can( 'edit_post', $page_id ) && current_user_can( 'publish_pages' );
}

/**
 * Permission check for restoring a page revision.
 *
 * @param array $input Ability input.
 * @return bool
 */
function samcp_can_restore_page_revision( $input ) {
	$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
	$page    = $page_id > 0 ? get_post( $page_id ) : null;

	if ( ! $page || 'page' !== $page->post_type || ! current_user_can( 'edit_post', $page_id ) ) {
		return false;
	}

	return 'publish' !== $page->post_status || current_user_can( 'publish_pages' );
}

/**
 * Permission check for moving a page into or out of trash.
 *
 * @param array $input Ability input.
 * @return bool
 */
function samcp_can_trash_page( $input ) {
	$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

	return $page_id > 0 && current_user_can( 'delete_post', $page_id ) && current_user_can( 'edit_post', $page_id );
}

/**
 * List pages.
 *
 * @param array $input Ability input.
 * @return array
 */
function samcp_list_pages( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$status   = isset( $input['status'] ) ? (string) $input['status'] : 'any';
	$statuses = array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' );

	if ( ! in_array( $status, $statuses, true ) ) {
		$status = 'any';
	}

	$args = array(
		'post_type'      => 'page',
		'post_status'    => $status,
		'posts_per_page' => isset( $input['per_page'] ) ? min( 100, max( 1, absint( $input['per_page'] ) ) ) : 20,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'perm'           => 'readable',
	);

	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}

	$pages = array_filter(
		get_posts( $args ),
		static function ( $page ) {
			return current_user_can( 'read_post', $page->ID );
		}
	);

	return array_values( array_map(
		static function ( $page ) {
			return samcp_format_page( $page, false );
		},
		$pages
	) );
}

/**
 * Read one page.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_get_page( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( ! current_user_can( 'read_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to read this page.', 'site-abilities-mcp' ) );
	}

	return samcp_format_page( $page );
}

/**
 * Create a page draft.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_create_page_draft( $input ) {
	$postarr = array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => sanitize_text_field( $input['title'] ),
	);

	if ( array_key_exists( 'content', $input ) ) {
		$postarr['post_content'] = (string) $input['content'];
	}

	if ( array_key_exists( 'excerpt', $input ) ) {
		$postarr['post_excerpt'] = (string) $input['excerpt'];
	}

	$page_id = wp_insert_post( wp_slash( $postarr ), true );

	if ( is_wp_error( $page_id ) ) {
		return $page_id;
	}

	return samcp_format_page( get_post( $page_id ) );
}

/**
 * Update an existing draft page while preserving draft status.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_update_page_draft( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( 'draft' !== $page->post_status ) {
		return new WP_Error( 'samcp_page_not_draft', __( 'Only draft pages can be updated through this ability.', 'site-abilities-mcp' ) );
	}

	if ( ! current_user_can( 'edit_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to edit this page.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );

	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	if ( ! samcp_has_page_changes( $page, $input ) ) {
		return new WP_Error( 'samcp_no_changes', __( 'Provide at least one changed title, content or excerpt value.', 'site-abilities-mcp' ) );
	}

	$postarr = samcp_build_page_update( $page, $input, 'draft' );
	$result = wp_update_post( wp_slash( $postarr ), true );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return samcp_format_page( get_post( $result ) );
}

/**
 * Update an existing published page after explicit confirmation.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_update_published_page( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( 'publish' !== $page->post_status ) {
		return new WP_Error( 'samcp_page_not_published', __( 'Only an already published page can be updated through this ability.', 'site-abilities-mcp' ) );
	}

	if ( ! current_user_can( 'edit_post', $page->ID ) || ! current_user_can( 'publish_pages' ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to update published pages.', 'site-abilities-mcp' ) );
	}

	if ( ! isset( $input['confirmation'] ) || 'UPDATE_PUBLISHED' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_PUBLISHED confirmation is required.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );

	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	if ( ! samcp_has_page_changes( $page, $input ) ) {
		return new WP_Error( 'samcp_no_changes', __( 'Provide at least one changed title, content or excerpt value.', 'site-abilities-mcp' ) );
	}

	$safety_revision_id = samcp_create_safety_revision( $page );

	if ( is_wp_error( $safety_revision_id ) ) {
		return $safety_revision_id;
	}

	$result = wp_update_post( wp_slash( samcp_build_page_update( $page, $input, 'publish' ) ), true );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'action'             => 'updated_published_page',
		'safety_revision_id' => (int) $safety_revision_id,
		'page'               => samcp_format_page( get_post( $result ) ),
	);
}

/**
 * Publish an existing draft after explicit confirmation.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_publish_page_draft( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( 'draft' !== $page->post_status ) {
		return new WP_Error( 'samcp_page_not_draft', __( 'Only a draft page can be published through this ability.', 'site-abilities-mcp' ) );
	}

	if ( ! current_user_can( 'edit_post', $page->ID ) || ! current_user_can( 'publish_pages' ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to publish pages.', 'site-abilities-mcp' ) );
	}

	if ( ! isset( $input['confirmation'] ) || 'PUBLISH' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit PUBLISH confirmation is required.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );

	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	$safety_revision_id = samcp_create_safety_revision( $page );

	if ( is_wp_error( $safety_revision_id ) ) {
		return $safety_revision_id;
	}

	$result = wp_update_post(
		array(
			'ID'          => $page->ID,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'action'             => 'published_page_draft',
		'safety_revision_id' => (int) $safety_revision_id,
		'page'               => samcp_format_page( get_post( $result ) ),
	);
}

/**
 * List restorable revisions for a page.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_list_page_revisions( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( ! current_user_can( 'read_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to read revisions for this page.', 'site-abilities-mcp' ) );
	}

	$per_page = isset( $input['per_page'] ) ? min( 50, max( 1, absint( $input['per_page'] ) ) ) : 10;
	$revisions = wp_get_post_revisions(
		$page->ID,
		array(
			'posts_per_page' => $per_page,
			'orderby'        => 'date ID',
			'order'          => 'DESC',
		)
	);

	$revisions = array_filter(
		$revisions,
		static function ( $revision ) {
			return ! wp_is_post_autosave( $revision );
		}
	);

	return array_values(
		array_map(
			static function ( $revision ) {
				return samcp_format_revision( $revision, false );
			},
			$revisions
		)
	);
}

/**
 * Read one complete page revision.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_get_page_revision( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( ! current_user_can( 'read_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to read revisions for this page.', 'site-abilities-mcp' ) );
	}

	$revision = samcp_find_page_revision( $page->ID, $input['revision_id'] );

	if ( is_wp_error( $revision ) ) {
		return $revision;
	}

	return samcp_format_revision( $revision );
}

/**
 * Restore title, content and excerpt from a page revision.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_restore_page_revision( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( ! current_user_can( 'edit_post', $page->ID ) || ( 'publish' === $page->post_status && ! current_user_can( 'publish_pages' ) ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to restore this page.', 'site-abilities-mcp' ) );
	}

	if ( ! isset( $input['confirmation'] ) || 'RESTORE' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE confirmation is required.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );

	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	$revision = samcp_find_page_revision( $page->ID, $input['revision_id'] );

	if ( is_wp_error( $revision ) ) {
		return $revision;
	}

	$safety_revision_id = samcp_create_safety_revision( $page );

	if ( is_wp_error( $safety_revision_id ) ) {
		return $safety_revision_id;
	}

	$result = wp_restore_post_revision(
		$revision,
		array( 'post_title', 'post_content', 'post_excerpt' )
	);

	if ( ! $result ) {
		return new WP_Error( 'samcp_restore_failed', __( 'WordPress could not restore the selected revision.', 'site-abilities-mcp' ) );
	}

	return array(
		'action'             => 'restored_page_revision',
		'safety_revision_id' => (int) $safety_revision_id,
		'page'               => samcp_format_page( get_post( $page->ID ) ),
	);
}

/**
 * Move a page to trash without permanently deleting it.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_trash_page( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( 'trash' === $page->post_status ) {
		return new WP_Error( 'samcp_page_already_trashed', __( 'The page is already in trash.', 'site-abilities-mcp' ) );
	}

	if ( ! current_user_can( 'delete_post', $page->ID ) || ! current_user_can( 'edit_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to move this page to trash.', 'site-abilities-mcp' ) );
	}

	if ( empty( $input['confirmation'] ) || 'TRASH' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit TRASH confirmation is required.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );
	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	$safety_revision_id = samcp_create_safety_revision( $page );
	if ( is_wp_error( $safety_revision_id ) ) {
		return $safety_revision_id;
	}

	$result = wp_trash_post( $page->ID );
	if ( ! $result ) {
		return new WP_Error( 'samcp_trash_failed', __( 'WordPress could not move the page to trash.', 'site-abilities-mcp' ) );
	}

	return array(
		'action'             => 'trashed_page',
		'safety_revision_id' => (int) $safety_revision_id,
		'page'               => samcp_format_page( get_post( $page->ID ) ),
	);
}

/**
 * Restore a trashed page as a draft.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function samcp_restore_trashed_page( $input ) {
	$page = samcp_find_page( $input['page_id'] );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	if ( 'trash' !== $page->post_status ) {
		return new WP_Error( 'samcp_page_not_trashed', __( 'Only a trashed page can be restored through this ability.', 'site-abilities-mcp' ) );
	}

	if ( ! current_user_can( 'delete_post', $page->ID ) || ! current_user_can( 'edit_post', $page->ID ) ) {
		return new WP_Error( 'samcp_page_forbidden', __( 'You are not allowed to restore this page.', 'site-abilities-mcp' ) );
	}

	if ( empty( $input['confirmation'] ) || 'RESTORE_TRASHED' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_TRASHED confirmation is required.', 'site-abilities-mcp' ) );
	}

	$snapshot_check = samcp_validate_page_snapshot( $page, $input );
	if ( is_wp_error( $snapshot_check ) ) {
		return $snapshot_check;
	}

	$result = wp_untrash_post( $page->ID );
	if ( ! $result ) {
		return new WP_Error( 'samcp_untrash_failed', __( 'WordPress could not restore the page from trash.', 'site-abilities-mcp' ) );
	}

	$result = wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ), true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'action'             => 'restored_trashed_page_as_draft',
		'safety_revision_id' => 0,
		'page'               => samcp_format_page( get_post( $page->ID ) ),
	);
}

/**
 * Show administrators why abilities are unavailable when the required API is missing.
 */
function samcp_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) || function_exists( 'wp_register_ability' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Site Abilities MCP requires WordPress 6.9 or later because it uses the WordPress Abilities API.', 'site-abilities-mcp' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'samcp_dependency_notice' );

// Load independent, auditable ability modules.
$samcp_modules = array(
	'content-abilities.php',
	'media-abilities.php',
	'menu-abilities.php',
	'seo-abilities.php',
	'product-abilities.php',
	'admin-abilities.php',
	'wpbakery-abilities.php',
);

foreach ( $samcp_modules as $samcp_module ) {
	$samcp_module_path = __DIR__ . '/includes/' . $samcp_module;
	if ( file_exists( $samcp_module_path ) ) {
		require_once $samcp_module_path;
	}
}
