<?php
/**
 * Guarded comment reading and moderation abilities.
 *
 * @package SiteAbilitiesMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the comments category. */
function samcp_register_comment_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category(
			'site-abilities-comments',
			array(
				'label'       => __( 'Site Comments', 'site-abilities-mcp' ),
				'description' => __( 'Reads, replies to and moderates comments with recoverable snapshots and no permanent deletion.', 'site-abilities-mcp' ),
			)
		);
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_comment_category' );

/** Comment payload schema. */
function samcp_comment_schema() {
	return array(
		'type'       => 'object',
		'required'   => array( 'id', 'post_id', 'parent', 'status', 'author_name', 'author_url', 'content', 'date_gmt', 'comment_sha256' ),
		'properties' => array(
			'id'             => array( 'type' => 'integer' ),
			'post_id'        => array( 'type' => 'integer' ),
			'parent'         => array( 'type' => 'integer' ),
			'status'         => array( 'type' => 'string' ),
			'author_name'    => array( 'type' => 'string' ),
			'author_url'     => array( 'type' => 'string' ),
			'content'        => array( 'type' => 'string' ),
			'date_gmt'       => array( 'type' => 'string' ),
			'comment_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			'snapshot_id'    => array( 'type' => 'string' ),
		),
	);
}

/** Comment ID schema with optional mutation guards. */
function samcp_comment_id_schema( $mutation = false, $confirmation = '' ) {
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'comment_id' ),
		'properties'           => array( 'comment_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
	);
	if ( $mutation ) {
		$schema['required'][] = 'expected_comment_sha256';
		$schema['properties']['expected_comment_sha256'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
		if ( $confirmation ) {
			$schema['required'][] = 'confirmation';
			$schema['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( $confirmation ) );
		}
	}
	return $schema;
}

/** Register comment abilities. */
function samcp_register_comment_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'site-abilities/list-comments',
		array(
			'label'               => __( 'List comments', 'site-abilities-mcp' ),
			'description'         => __( 'Lists comments visible to the authenticated moderator without changing them.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'post_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'status'   => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash', 'all' ), 'default' => 'all' ),
					'search'   => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				),
			),
			'output_schema'       => array( 'type' => 'array', 'items' => samcp_comment_schema() ),
			'execute_callback'    => 'samcp_list_comments',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/get-comment',
		array(
			'label'               => __( 'Read comment', 'site-abilities-mcp' ),
			'description'         => __( 'Reads one comment and its stale-write checksum.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => samcp_comment_id_schema(),
			'output_schema'       => samcp_comment_schema(),
			'execute_callback'    => 'samcp_get_comment',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	wp_register_ability(
		'site-abilities/reply-to-comment',
		array(
			'label'               => __( 'Reply to comment', 'site-abilities-mcp' ),
			'description'         => __( 'Creates an approved reply from the authenticated WordPress user after explicit confirmation.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'comment_id', 'content', 'expected_comment_sha256', 'confirmation' ),
				'properties'           => array(
					'comment_id'              => array( 'type' => 'integer', 'minimum' => 1 ),
					'content'                 => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 10000 ),
					'expected_comment_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
					'confirmation'            => array( 'type' => 'string', 'enum' => array( 'REPLY_TO_COMMENT' ) ),
				),
			),
			'output_schema'       => samcp_comment_schema(),
			'execute_callback'    => 'samcp_reply_to_comment',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( false, false, false ),
		)
	);

	$moderate = samcp_comment_id_schema( true, 'MODERATE_COMMENT' );
	$moderate['required'][] = 'status';
	$moderate['properties']['status'] = array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash' ) );
	wp_register_ability(
		'site-abilities/moderate-comment',
		array(
			'label'               => __( 'Moderate comment', 'site-abilities-mcp' ),
			'description'         => __( 'Changes comment moderation status after checksum validation and saves a restorable snapshot.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => $moderate,
			'output_schema'       => samcp_comment_schema(),
			'execute_callback'    => 'samcp_moderate_comment',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	$update = samcp_comment_id_schema( true, 'UPDATE_COMMENT' );
	$update['required'][] = 'content';
	$update['properties']['content'] = array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 10000 );
	wp_register_ability(
		'site-abilities/update-comment',
		array(
			'label'               => __( 'Update comment content', 'site-abilities-mcp' ),
			'description'         => __( 'Updates comment text after checksum validation and saves a restorable snapshot.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => $update,
			'output_schema'       => samcp_comment_schema(),
			'execute_callback'    => 'samcp_update_comment',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);

	wp_register_ability(
		'site-abilities/list-comment-snapshots',
		array(
			'label'               => __( 'List comment snapshots', 'site-abilities-mcp' ),
			'description'         => __( 'Lists bounded recovery snapshots created before comment changes.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'comment_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
			'output_schema'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'execute_callback'    => 'samcp_list_comment_snapshots',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( true, false, true ),
		)
	);

	$restore = samcp_comment_id_schema( true, 'RESTORE_COMMENT_SNAPSHOT' );
	$restore['required'][] = 'snapshot_id';
	$restore['properties']['snapshot_id'] = array( 'type' => 'string', 'minLength' => 1 );
	wp_register_ability(
		'site-abilities/restore-comment-snapshot',
		array(
			'label'               => __( 'Restore comment snapshot', 'site-abilities-mcp' ),
			'description'         => __( 'Restores comment content and status after checksum validation while preserving a new recovery point.', 'site-abilities-mcp' ),
			'category'            => 'site-abilities-comments',
			'input_schema'        => $restore,
			'output_schema'       => samcp_comment_schema(),
			'execute_callback'    => 'samcp_restore_comment_snapshot',
			'permission_callback' => 'samcp_can_moderate_comments',
			'meta'                => samcp_ability_meta( false, true, false ),
		)
	);
}
add_action( 'wp_abilities_api_init', 'samcp_register_comment_abilities' );

function samcp_can_moderate_comments() { return current_user_can( 'moderate_comments' ); }

/** Normalize core comment status values. */
function samcp_comment_status( $comment ) {
	if ( '1' === (string) $comment->comment_approved ) { return 'approve'; }
	if ( '0' === (string) $comment->comment_approved ) { return 'hold'; }
	return (string) $comment->comment_approved;
}

function samcp_comment_hash( $comment ) {
	return hash( 'sha256', implode( "\n", array( $comment->comment_ID, $comment->comment_post_ID, $comment->comment_parent, samcp_comment_status( $comment ), $comment->comment_content ) ) );
}

function samcp_format_comment( $comment, $snapshot_id = '' ) {
	return array(
		'id'             => (int) $comment->comment_ID,
		'post_id'        => (int) $comment->comment_post_ID,
		'parent'         => (int) $comment->comment_parent,
		'status'         => samcp_comment_status( $comment ),
		'author_name'    => (string) $comment->comment_author,
		'author_url'     => (string) $comment->comment_author_url,
		'content'        => (string) $comment->comment_content,
		'date_gmt'       => (string) $comment->comment_date_gmt,
		'comment_sha256' => samcp_comment_hash( $comment ),
		'snapshot_id'    => (string) $snapshot_id,
	);
}

function samcp_find_comment( $comment_id ) {
	$comment = get_comment( absint( $comment_id ) );
	return $comment ? $comment : new WP_Error( 'samcp_comment_not_found', __( 'The requested comment was not found.', 'site-abilities-mcp' ) );
}

function samcp_validate_comment_snapshot( $comment, $input ) {
	return hash_equals( samcp_comment_hash( $comment ), strtolower( (string) $input['expected_comment_sha256'] ) )
		? true
		: new WP_Error( 'samcp_comment_changed', __( 'The comment changed after it was read. Read it again before retrying.', 'site-abilities-mcp' ) );
}

function samcp_save_comment_snapshot( $comment, $reason ) {
	$id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'comment-', true );
	$items = (array) get_option( 'samcp_comment_snapshots', array() );
	array_unshift(
		$items,
		array(
			'id'          => $id,
			'comment_id'  => (int) $comment->comment_ID,
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'user_id'     => get_current_user_id(),
			'reason'      => (string) $reason,
			'status'      => samcp_comment_status( $comment ),
			'content'     => (string) $comment->comment_content,
		)
	);
	update_option( 'samcp_comment_snapshots', array_slice( $items, 0, 100 ), false );
	return $id;
}

function samcp_list_comments( $input ) {
	$args = array(
		'status' => isset( $input['status'] ) && 'all' !== $input['status'] ? $input['status'] : 'all',
		'number' => isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 20,
		'orderby'=> 'comment_date_gmt',
		'order'  => 'DESC',
	);
	if ( ! empty( $input['post_id'] ) ) { $args['post_id'] = absint( $input['post_id'] ); }
	if ( ! empty( $input['search'] ) ) { $args['search'] = sanitize_text_field( $input['search'] ); }
	return array_map( 'samcp_format_comment', get_comments( $args ) );
}

function samcp_get_comment( $input ) {
	$comment = samcp_find_comment( $input['comment_id'] );
	return is_wp_error( $comment ) ? $comment : samcp_format_comment( $comment );
}

function samcp_reply_to_comment( $input ) {
	if ( empty( $input['confirmation'] ) || 'REPLY_TO_COMMENT' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit REPLY_TO_COMMENT confirmation is required.', 'site-abilities-mcp' ) );
	}
	$parent = samcp_find_comment( $input['comment_id'] );
	if ( is_wp_error( $parent ) ) { return $parent; }
	$check = samcp_validate_comment_snapshot( $parent, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$user = wp_get_current_user();
	$id = wp_new_comment(
		array(
			'comment_post_ID'      => (int) $parent->comment_post_ID,
			'comment_parent'       => (int) $parent->comment_ID,
			'comment_content'      => wp_kses_post( $input['content'] ),
			'user_id'              => (int) $user->ID,
			'comment_author'       => (string) $user->display_name,
			'comment_author_email' => (string) $user->user_email,
			'comment_approved'     => 1,
		),
		true
	);
	return is_wp_error( $id ) ? $id : samcp_format_comment( get_comment( $id ) );
}

function samcp_moderate_comment( $input ) {
	if ( empty( $input['confirmation'] ) || 'MODERATE_COMMENT' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit MODERATE_COMMENT confirmation is required.', 'site-abilities-mcp' ) );
	}
	$comment = samcp_find_comment( $input['comment_id'] );
	if ( is_wp_error( $comment ) ) { return $comment; }
	$check = samcp_validate_comment_snapshot( $comment, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$snapshot_id = samcp_save_comment_snapshot( $comment, 'before_moderation' );
	$result = wp_set_comment_status( $comment->comment_ID, $input['status'], true );
	if ( is_wp_error( $result ) ) { return $result; }
	if ( ! $result ) { return new WP_Error( 'samcp_comment_update_failed', __( 'WordPress could not change the comment status.', 'site-abilities-mcp' ) ); }
	return samcp_format_comment( get_comment( $comment->comment_ID ), $snapshot_id );
}

function samcp_update_comment( $input ) {
	if ( empty( $input['confirmation'] ) || 'UPDATE_COMMENT' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_COMMENT confirmation is required.', 'site-abilities-mcp' ) );
	}
	$comment = samcp_find_comment( $input['comment_id'] );
	if ( is_wp_error( $comment ) ) { return $comment; }
	$check = samcp_validate_comment_snapshot( $comment, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$snapshot_id = samcp_save_comment_snapshot( $comment, 'before_update' );
	$result = wp_update_comment( array( 'comment_ID' => $comment->comment_ID, 'comment_content' => wp_kses_post( $input['content'] ) ), true );
	return is_wp_error( $result ) ? $result : samcp_format_comment( get_comment( $comment->comment_ID ), $snapshot_id );
}

function samcp_list_comment_snapshots( $input ) {
	$items = (array) get_option( 'samcp_comment_snapshots', array() );
	if ( ! empty( $input['comment_id'] ) ) {
		$items = array_filter( $items, static function ( $item ) use ( $input ) { return absint( $item['comment_id'] ) === absint( $input['comment_id'] ); } );
	}
	return array_values( $items );
}

function samcp_restore_comment_snapshot( $input ) {
	if ( empty( $input['confirmation'] ) || 'RESTORE_COMMENT_SNAPSHOT' !== $input['confirmation'] ) {
		return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_COMMENT_SNAPSHOT confirmation is required.', 'site-abilities-mcp' ) );
	}
	$comment = samcp_find_comment( $input['comment_id'] );
	if ( is_wp_error( $comment ) ) { return $comment; }
	$check = samcp_validate_comment_snapshot( $comment, $input );
	if ( is_wp_error( $check ) ) { return $check; }
	$snapshot = null;
	foreach ( (array) get_option( 'samcp_comment_snapshots', array() ) as $candidate ) {
		if ( hash_equals( (string) $candidate['id'], (string) $input['snapshot_id'] ) && absint( $candidate['comment_id'] ) === $comment->comment_ID ) {
			$snapshot = $candidate;
			break;
		}
	}
	if ( ! $snapshot ) { return new WP_Error( 'samcp_comment_snapshot_not_found', __( 'The requested comment snapshot was not found.', 'site-abilities-mcp' ) ); }
	$safety_id = samcp_save_comment_snapshot( $comment, 'before_restore' );
	$result = wp_update_comment( array( 'comment_ID' => $comment->comment_ID, 'comment_content' => $snapshot['content'] ), true );
	if ( is_wp_error( $result ) ) { return $result; }
	$status = wp_set_comment_status( $comment->comment_ID, $snapshot['status'], true );
	if ( is_wp_error( $status ) ) { return $status; }
	if ( ! $status ) { return new WP_Error( 'samcp_comment_restore_failed', __( 'WordPress could not restore the comment status.', 'site-abilities-mcp' ) ); }
	return samcp_format_comment( get_comment( $comment->comment_ID ), $safety_id );
}
