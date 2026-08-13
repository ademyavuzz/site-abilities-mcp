<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'SITE_ABILITIES_MCP_PROFILE', 'full_access' );

$GLOBALS['samcp_test_actions']   = array();
$GLOBALS['samcp_test_abilities'] = array();
$GLOBALS['samcp_test_posts']     = array();
$GLOBALS['samcp_test_revisions'] = array();
$GLOBALS['samcp_test_options']   = array();
$GLOBALS['samcp_test_revisions_disabled'] = array();
$GLOBALS['samcp_test_next_id']   = 2000;
$GLOBALS['samcp_test_clock']     = 1;

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message, $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function __( $text ) {
	return $text;
}

function esc_html__( $text ) {
	return $text;
}

function add_action( $hook, $callback ) {
	$GLOBALS['samcp_test_actions'][ $hook ][] = $callback;
}

function add_filter( $hook, $callback ) {
	$GLOBALS['samcp_test_actions'][ $hook ][] = $callback;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function wp_register_ability_category() {
	return true;
}

function wp_register_ability( $name, $args ) {
	$GLOBALS['samcp_test_abilities'][ $name ] = $args;
	return true;
}

function current_user_can() {
	return true;
}

function get_current_user_id() {
	return 7;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['samcp_test_options'] ) ? $GLOBALS['samcp_test_options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['samcp_test_options'][ $key ] = $value;
	return true;
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_slash( $value ) {
	return $value;
}

function get_post( $post_id ) {
	$post_id = (int) $post_id;
	return isset( $GLOBALS['samcp_test_posts'][ $post_id ] ) ? clone $GLOBALS['samcp_test_posts'][ $post_id ] : null;
}

function wp_insert_post( $postarr ) {
	$postarr['ID'] = ++$GLOBALS['samcp_test_next_id'];
	$postarr['post_name'] = '';
	$postarr['post_parent'] = 0;
	$postarr['post_excerpt'] = isset( $postarr['post_excerpt'] ) ? $postarr['post_excerpt'] : '';
	$postarr['post_content'] = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
	$postarr['post_modified_gmt'] = '2026-08-11 10:00:00';
	$GLOBALS['samcp_test_posts'][ $postarr['ID'] ] = (object) $postarr;
	return $postarr['ID'];
}

function wp_update_post( $postarr ) {
	$id = (int) $postarr['ID'];
	if ( ! isset( $GLOBALS['samcp_test_posts'][ $id ] ) ) {
		return new WP_Error( 'invalid_post', 'Invalid post.' );
	}

	$post = $GLOBALS['samcp_test_posts'][ $id ];
	foreach ( $postarr as $key => $value ) {
		if ( 'ID' !== $key ) {
			$post->{$key} = $value;
		}
	}
	$post->post_modified_gmt = '2026-08-11 10:00:' . str_pad( (string) ++$GLOBALS['samcp_test_clock'], 2, '0', STR_PAD_LEFT );
	$GLOBALS['samcp_test_posts'][ $id ] = $post;
	return $id;
}

function wp_trash_post( $post_id ) {
	if ( ! isset( $GLOBALS['samcp_test_posts'][ $post_id ] ) ) {
		return false;
	}
	$GLOBALS['samcp_test_posts'][ $post_id ]->post_status = 'trash';
	$GLOBALS['samcp_test_posts'][ $post_id ]->post_modified_gmt = '2026-08-11 10:00:' . str_pad( (string) ++$GLOBALS['samcp_test_clock'], 2, '0', STR_PAD_LEFT );
	return clone $GLOBALS['samcp_test_posts'][ $post_id ];
}

function wp_untrash_post( $post_id ) {
	if ( ! isset( $GLOBALS['samcp_test_posts'][ $post_id ] ) || 'trash' !== $GLOBALS['samcp_test_posts'][ $post_id ]->post_status ) {
		return false;
	}
	$GLOBALS['samcp_test_posts'][ $post_id ]->post_status = 'draft';
	$GLOBALS['samcp_test_posts'][ $post_id ]->post_modified_gmt = '2026-08-11 10:00:' . str_pad( (string) ++$GLOBALS['samcp_test_clock'], 2, '0', STR_PAD_LEFT );
	return clone $GLOBALS['samcp_test_posts'][ $post_id ];
}

function wp_revisions_enabled( $post ) {
	return empty( $GLOBALS['samcp_test_revisions_disabled'][ $post->ID ] );
}

function wp_save_post_revision( $post_id ) {
	$post = get_post( $post_id );
	$id   = ++$GLOBALS['samcp_test_next_id'];
	$revision = clone $post;
	$revision->ID = $id;
	$revision->post_parent = $post_id;
	$revision->post_type = 'revision';
	$revision->post_author = 7;
	$revision->post_date_gmt = $post->post_modified_gmt;
	$GLOBALS['samcp_test_revisions'][ $id ] = $revision;
	$GLOBALS['samcp_test_posts'][ $id ] = $revision;
	return $id;
}

function wp_get_post_revisions( $post_id, $args = array() ) {
	return array_filter(
		$GLOBALS['samcp_test_revisions'],
		static function ( $revision ) use ( $post_id ) {
			return (int) $revision->post_parent === (int) $post_id;
		}
	);
}

function wp_get_post_revision( $revision_id ) {
	$revision_id = (int) $revision_id;
	return isset( $GLOBALS['samcp_test_revisions'][ $revision_id ] ) ? clone $GLOBALS['samcp_test_revisions'][ $revision_id ] : null;
}

function wp_is_post_autosave() {
	return false;
}

function wp_restore_post_revision( $revision, $fields ) {
	$page = $GLOBALS['samcp_test_posts'][ $revision->post_parent ];
	$map  = array(
		'post_title'   => 'post_title',
		'post_content' => 'post_content',
		'post_excerpt' => 'post_excerpt',
	);
	foreach ( $fields as $field ) {
		$page->{$map[ $field ]} = $revision->{$field};
	}
	$GLOBALS['samcp_test_posts'][ $page->ID ] = $page;
	return wp_update_post( array( 'ID' => $page->ID ) );
}

function samcp_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function samcp_test_validate_schema( $schema, $path ) {
	samcp_test_assert( is_array( $schema ), $path . ' must be an array schema.' );
	if ( isset( $schema['required'] ) ) {
		samcp_test_assert( isset( $schema['properties'] ) && is_array( $schema['properties'] ), $path . ' has required fields but no properties.' );
		foreach ( $schema['required'] as $required ) {
			samcp_test_assert( array_key_exists( $required, $schema['properties'] ), $path . ' requires undefined property ' . $required . '.' );
		}
	}
	if ( isset( $schema['properties'] ) ) {
		foreach ( $schema['properties'] as $name => $property ) {
			samcp_test_validate_schema( $property, $path . '.properties.' . $name );
		}
	}
	if ( isset( $schema['items'] ) ) {
		samcp_test_validate_schema( $schema['items'], $path . '.items' );
	}
}

function samcp_test_page( $id, $status, $content, $modified = '2026-08-11 10:00:00' ) {
	return (object) array(
		'ID'                => $id,
		'post_type'         => 'page',
		'post_status'       => $status,
		'post_title'        => 'Test page',
		'post_content'      => $content,
		'post_excerpt'      => '',
		'post_name'         => 'test-page',
		'post_modified_gmt' => $modified,
		'post_parent'       => 0,
		'post_author'       => 7,
		'post_date_gmt'     => $modified,
	);
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
samcp_register_taxonomy_abilities();
samcp_register_comment_abilities();
samcp_register_block_abilities();
samcp_register_discovery_abilities();
samcp_test_assert( 85 === count( $GLOBALS['samcp_test_abilities'] ), 'Expected 85 registered abilities.' );
foreach ( $GLOBALS['samcp_test_abilities'] as $name => $ability ) {
	samcp_test_assert( 1 === preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ), $name . ' is not a valid namespaced ability name.' );
	samcp_test_assert( true === $ability['meta']['mcp']['public'], $name . ' is not MCP-public.' );
	samcp_test_assert( is_callable( $ability['execute_callback'] ), $name . ' execute callback is not callable.' );
	samcp_test_assert( is_callable( $ability['permission_callback'] ), $name . ' permission callback is not callable.' );
	samcp_test_assert( isset( $ability['input_schema']['type'] ) && 'object' === $ability['input_schema']['type'], $name . ' input must be an object schema.' );
	samcp_test_assert( isset( $ability['input_schema']['additionalProperties'] ) && false === $ability['input_schema']['additionalProperties'], $name . ' input must reject unknown properties.' );
	samcp_test_validate_schema( $ability['input_schema'], $name . '.input' );
	samcp_test_validate_schema( $ability['output_schema'], $name . '.output' );
	if ( ! $ability['meta']['annotations']['readonly'] && $ability['meta']['annotations']['destructive'] ) {
		$required = isset( $ability['input_schema']['required'] ) ? $ability['input_schema']['required'] : array();
		$has_guard = in_array( 'confirmation', $required, true );
		foreach ( $required as $field ) {
			if ( 0 === strpos( $field, 'expected_' ) ) {
				$has_guard = true;
			}
		}
		samcp_test_assert( $has_guard, $name . ' is destructive but has no confirmation or expected-state guard.' );
	}
}
foreach ( array( 'site-abilities/delete-page-permanently', 'site-abilities/delete-media-permanently', 'site-abilities/update-option', 'site-abilities/switch-theme', 'site-abilities/edit-plugin-file' ) as $forbidden_ability ) {
	samcp_test_assert( ! isset( $GLOBALS['samcp_test_abilities'][ $forbidden_ability ] ), $forbidden_ability . ' must not be exposed.' );
}

foreach ( array(
	'site-abilities/list-taxonomies',
	'site-abilities/assign-content-terms',
	'site-abilities/list-comments',
	'site-abilities/moderate-comment',
	'site-abilities/list-block-types',
	'site-abilities/create-synced-pattern-draft',
	'site-abilities/get-site-overview',
	'site-abilities/list-ability-activity',
) as $required_ability ) {
	samcp_test_assert( isset( $GLOBALS['samcp_test_abilities'][ $required_ability ] ), $required_ability . ' was not registered.' );
}

foreach ( array(
	'site-abilities/assign-content-terms' => 'ASSIGN_CONTENT_TERMS',
	'site-abilities/update-term' => 'UPDATE_TERM',
	'site-abilities/moderate-comment' => 'MODERATE_COMMENT',
	'site-abilities/update-comment' => 'UPDATE_COMMENT',
	'site-abilities/update-synced-pattern' => 'UPDATE_PATTERN',
	'site-abilities/trash-synced-pattern' => 'TRASH_PATTERN',
) as $ability_name => $confirmation ) {
	$ability = $GLOBALS['samcp_test_abilities'][ $ability_name ];
	samcp_test_assert( in_array( 'confirmation', $ability['input_schema']['required'], true ), $ability_name . ' must require confirmation.' );
	samcp_test_assert( array( $confirmation ) === $ability['input_schema']['properties']['confirmation']['enum'], $ability_name . ' has an incorrect confirmation token.' );
}

samcp_record_ability_activity( 'site-abilities/list-pages', array( 'secret' => 'must-not-be-stored' ), array( 'content' => 'must-not-be-stored' ) );
$activity = get_option( 'samcp_ability_activity', array() );
samcp_test_assert( 1 === count( $activity ), 'Ability activity metadata was not recorded.' );
samcp_test_assert( array( 'ability', 'executed_gmt', 'user_id' ) === array_keys( $activity[0] ), 'Ability activity must not store input or output data.' );
samcp_record_ability_activity( 'other-plugin/private-operation', array( 'secret' => 'ignored' ), null );
samcp_test_assert( 1 === count( get_option( 'samcp_ability_activity', array() ) ), 'Activity logger captured another plugin namespace.' );

$GLOBALS['samcp_test_posts'][100] = samcp_test_page( 100, 'publish', 'Original live content.' );
$stale = samcp_update_published_page(
	array(
		'page_id'                => 100,
		'expected_modified_gmt'   => '2026-08-11 09:59:59',
		'expected_content_sha256' => hash( 'sha256', 'Original live content.' ),
		'confirmation'            => 'UPDATE_PUBLISHED',
		'content'                 => 'New live content.',
	)
);
samcp_test_assert( is_wp_error( $stale ) && 'samcp_page_changed' === $stale->get_error_code(), 'Stale live update was not rejected.' );
samcp_test_assert( 'Original live content.' === get_post( 100 )->post_content, 'Stale update changed live content.' );

$updated = samcp_update_published_page(
	array(
		'page_id'                => 100,
		'expected_modified_gmt'   => '2026-08-11 10:00:00',
		'expected_content_sha256' => hash( 'sha256', 'Original live content.' ),
		'confirmation'            => 'UPDATE_PUBLISHED',
		'content'                 => 'New live content.',
	)
);
samcp_test_assert( ! is_wp_error( $updated ), 'Valid live update failed.' );
samcp_test_assert( 'publish' === $updated['page']['status'], 'Live update did not preserve published status.' );
samcp_test_assert( $updated['safety_revision_id'] > 0, 'Live update did not return a safety revision.' );
samcp_test_assert( 'Original live content.' === wp_get_post_revision( $updated['safety_revision_id'] )->post_content, 'Safety revision does not contain original content.' );

$GLOBALS['samcp_test_posts'][101] = samcp_test_page( 101, 'draft', 'Draft content.' );
$published = samcp_publish_page_draft(
	array(
		'page_id'                => 101,
		'expected_modified_gmt'   => '2026-08-11 10:00:00',
		'expected_content_sha256' => hash( 'sha256', 'Draft content.' ),
		'confirmation'            => 'PUBLISH',
	)
);
samcp_test_assert( ! is_wp_error( $published ), 'Valid draft publication failed.' );
samcp_test_assert( 'publish' === $published['page']['status'], 'Draft was not published.' );

$restored = samcp_restore_page_revision(
	array(
		'page_id'                => 100,
		'revision_id'            => $updated['safety_revision_id'],
		'expected_modified_gmt'   => $updated['page']['modified_gmt'],
		'expected_content_sha256' => $updated['page']['content_sha256'],
		'confirmation'            => 'RESTORE',
	)
);
samcp_test_assert( ! is_wp_error( $restored ), 'Revision restoration failed.' );
samcp_test_assert( 'Original live content.' === $restored['page']['content'], 'Revision restoration returned incorrect content.' );
samcp_test_assert( 'publish' === $restored['page']['status'], 'Revision restoration changed page status.' );

$trashed = samcp_trash_page(
	array(
		'page_id'                => 100,
		'expected_modified_gmt'   => $restored['page']['modified_gmt'],
		'expected_content_sha256' => $restored['page']['content_sha256'],
		'confirmation'            => 'TRASH',
	)
);
samcp_test_assert( ! is_wp_error( $trashed ), 'Recoverable page trash operation failed.' );
samcp_test_assert( 'trash' === $trashed['page']['status'], 'Page was not moved to trash.' );

$untrashed = samcp_restore_trashed_page(
	array(
		'page_id'                => 100,
		'expected_modified_gmt'   => $trashed['page']['modified_gmt'],
		'expected_content_sha256' => $trashed['page']['content_sha256'],
		'confirmation'            => 'RESTORE_TRASHED',
	)
);
samcp_test_assert( ! is_wp_error( $untrashed ), 'Recoverable page restore operation failed.' );
samcp_test_assert( 'draft' === $untrashed['page']['status'], 'Restored page was not kept as a draft.' );

$GLOBALS['samcp_test_posts'][102] = samcp_test_page( 102, 'publish', 'Original post content.' );
$GLOBALS['samcp_test_posts'][102]->post_type = 'post';
$GLOBALS['samcp_test_revisions_disabled'][102] = true;
$event_updated = samcp_update_published_content(
	array(
		'content_id'             => 102,
		'expected_modified_gmt'   => '2026-08-11 10:00:00',
		'expected_content_sha256' => hash( 'sha256', 'Original post content.' ),
		'confirmation'            => 'UPDATE_LIVE_CONTENT',
		'content'                 => 'Updated post content.',
	)
);
samcp_test_assert( ! is_wp_error( $event_updated ), 'Revision-disabled live content update failed.' );
samcp_test_assert( 0 === $event_updated['safety_revision_id'], 'Revision-disabled content unexpectedly used a revision.' );
samcp_test_assert( '' !== $event_updated['safety_snapshot_id'], 'Revision-disabled content did not create a fallback snapshot.' );

$event_restored = samcp_restore_content_snapshot(
	array(
		'snapshot_id'            => $event_updated['safety_snapshot_id'],
		'expected_modified_gmt'   => $event_updated['content']['modified_gmt'],
		'expected_content_sha256' => $event_updated['content']['content_sha256'],
		'confirmation'            => 'RESTORE_CONTENT_SNAPSHOT',
	)
);
samcp_test_assert( ! is_wp_error( $event_restored ), 'Fallback content snapshot restoration failed.' );
samcp_test_assert( 'Original post content.' === $event_restored['content']['content'], 'Fallback snapshot restored incorrect content.' );
samcp_test_assert( 'publish' === $event_restored['content']['status'], 'Fallback snapshot changed publication status.' );

echo "Site Abilities MCP smoke tests passed.\n";
