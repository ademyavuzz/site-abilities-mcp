<?php
/** Controlled media-library abilities. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SAMCP_MEDIA_MAX_BYTES', 10 * MB_IN_BYTES );

function samcp_register_media_category() {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category( 'site-abilities-media', array( 'label' => __( 'Site Media', 'site-abilities-mcp' ), 'description' => __( 'Controlled image and PDF management.', 'site-abilities-mcp' ) ) );
	}
}
add_action( 'wp_abilities_api_categories_init', 'samcp_register_media_category' );

function samcp_media_schema() {
	return array(
		'type' => 'object',
		'required' => array( 'id', 'status', 'title', 'filename', 'mime_type', 'url', 'modified_gmt', 'metadata_sha256' ),
		'properties' => array(
			'id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ),
			'filename' => array( 'type' => 'string' ), 'mime_type' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ),
			'caption' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ), 'alt_text' => array( 'type' => 'string' ),
			'modified_gmt' => array( 'type' => 'string' ), 'metadata_sha256' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
		),
	);
}

function samcp_media_id_schema( $mutation = false, $confirmation = '' ) {
	$schema = array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'media_id' ), 'properties' => array( 'media_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) );
	if ( $mutation ) {
		$schema['required'][] = 'expected_modified_gmt';
		$schema['required'][] = 'expected_metadata_sha256';
		$schema['properties']['expected_modified_gmt'] = samcp_expected_modified_schema();
		$schema['properties']['expected_metadata_sha256'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
		if ( $confirmation ) { $schema['required'][] = 'confirmation'; $schema['properties']['confirmation'] = array( 'type' => 'string', 'enum' => array( $confirmation ) ); }
	}
	return $schema;
}

function samcp_register_media_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) { return; }
	wp_register_ability( 'site-abilities/list-media', array(
		'label' => __( 'List media', 'site-abilities-mcp' ), 'description' => __( 'Lists permitted images and PDFs in the media library.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'search' => array( 'type' => 'string' ), 'mime_group' => array( 'type' => 'string', 'enum' => array( 'all', 'image', 'pdf' ), 'default' => 'all' ), 'status' => array( 'type' => 'string', 'enum' => array( 'inherit', 'trash', 'any' ), 'default' => 'inherit' ), 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ) ) ),
		'output_schema' => array( 'type' => 'array', 'items' => samcp_media_schema() ), 'execute_callback' => 'samcp_list_media', 'permission_callback' => 'samcp_can_upload_media', 'meta' => samcp_ability_meta( true, false, true ),
	) );
	wp_register_ability( 'site-abilities/get-media', array(
		'label' => __( 'Read media metadata', 'site-abilities-mcp' ), 'description' => __( 'Reads one image or PDF attachment and its editable metadata.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
		'input_schema' => samcp_media_id_schema(), 'output_schema' => samcp_media_schema(), 'execute_callback' => 'samcp_get_media', 'permission_callback' => 'samcp_can_read_media', 'meta' => samcp_ability_meta( true, false, true ),
	) );
	wp_register_ability( 'site-abilities/upload-media-base64', array(
		'label' => __( 'Upload image or PDF', 'site-abilities-mcp' ), 'description' => __( 'Uploads a JPG, PNG, GIF, WebP or PDF from base64 data. Maximum decoded size is 10 MB.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'filename', 'base64_data', 'confirmation' ), 'properties' => array( 'filename' => array( 'type' => 'string', 'minLength' => 3, 'maxLength' => 255 ), 'base64_data' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string', 'maxLength' => 500 ), 'alt_text' => array( 'type' => 'string', 'maxLength' => 1000 ), 'confirmation' => array( 'type' => 'string', 'enum' => array( 'UPLOAD_MEDIA' ) ) ) ),
		'output_schema' => samcp_media_schema(), 'execute_callback' => 'samcp_upload_media_base64', 'permission_callback' => 'samcp_can_upload_media', 'meta' => samcp_ability_meta( false, false, false ),
	) );
	wp_register_ability( 'site-abilities/import-media-url', array(
		'label' => __( 'Import image or PDF from URL', 'site-abilities-mcp' ), 'description' => __( 'Safely downloads an HTTPS image or PDF into the media library. Private-network URLs and files over 10 MB are rejected.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
		'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'url', 'confirmation' ), 'properties' => array( 'url' => array( 'type' => 'string', 'format' => 'uri' ), 'title' => array( 'type' => 'string', 'maxLength' => 500 ), 'alt_text' => array( 'type' => 'string', 'maxLength' => 1000 ), 'confirmation' => array( 'type' => 'string', 'enum' => array( 'IMPORT_MEDIA' ) ) ) ),
		'output_schema' => samcp_media_schema(), 'execute_callback' => 'samcp_import_media_url', 'permission_callback' => 'samcp_can_upload_media', 'meta' => samcp_ability_meta( false, false, false, true ),
	) );
	$update = samcp_media_id_schema( true, 'UPDATE_MEDIA' );
	foreach ( array( 'title', 'caption', 'description', 'alt_text' ) as $field ) { $update['properties'][ $field ] = array( 'type' => 'string', 'maxLength' => 'title' === $field ? 500 : 5000 ); }
	wp_register_ability( 'site-abilities/update-media', array(
		'label' => __( 'Update media metadata', 'site-abilities-mcp' ), 'description' => __( 'Updates title, caption, description or alternative text after a stale-write check.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
		'input_schema' => $update, 'output_schema' => samcp_media_schema(), 'execute_callback' => 'samcp_update_media', 'permission_callback' => 'samcp_can_edit_media', 'meta' => samcp_ability_meta( false, true, false ),
	) );
	foreach ( array( 'trash-media' => array( 'TRASH_MEDIA', 'samcp_trash_media' ), 'restore-trashed-media' => array( 'RESTORE_TRASHED_MEDIA', 'samcp_restore_trashed_media' ) ) as $name => $config ) {
		wp_register_ability( 'site-abilities/' . $name, array(
			'label' => 'trash-media' === $name ? __( 'Move media to trash', 'site-abilities-mcp' ) : __( 'Restore trashed media', 'site-abilities-mcp' ),
			'description' => __( 'Changes media trash status after a stale-write check. Permanent deletion is unavailable.', 'site-abilities-mcp' ), 'category' => 'site-abilities-media',
			'input_schema' => samcp_media_id_schema( true, $config[0] ), 'output_schema' => samcp_media_schema(), 'execute_callback' => $config[1], 'permission_callback' => 'samcp_can_delete_media', 'meta' => samcp_ability_meta( false, true, false ),
		) );
	}
}
add_action( 'wp_abilities_api_init', 'samcp_register_media_abilities' );

function samcp_can_upload_media() { return current_user_can( 'upload_files' ); }
function samcp_can_read_media( $input ) { return ! empty( $input['media_id'] ) && current_user_can( 'read_post', absint( $input['media_id'] ) ); }
function samcp_can_edit_media( $input ) { return ! empty( $input['media_id'] ) && current_user_can( 'edit_post', absint( $input['media_id'] ) ); }
function samcp_can_delete_media( $input ) { return ! empty( $input['media_id'] ) && current_user_can( 'delete_post', absint( $input['media_id'] ) ); }

function samcp_allowed_media_mimes() { return array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf' ); }
function samcp_find_media( $id ) {
	$post = get_post( absint( $id ) );
	if ( ! $post || 'attachment' !== $post->post_type || ! in_array( (string) $post->post_mime_type, array_values( samcp_allowed_media_mimes() ), true ) ) { return new WP_Error( 'samcp_media_not_found', __( 'The requested permitted media item was not found.', 'site-abilities-mcp' ) ); }
	return $post;
}
function samcp_media_hash( $post ) { return hash( 'sha256', implode( "\n", array( $post->post_title, $post->post_excerpt, $post->post_content, (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ) ) ); }
function samcp_format_media( $post ) {
	return array( 'id' => (int) $post->ID, 'status' => (string) $post->post_status, 'title' => (string) $post->post_title, 'filename' => (string) basename( get_attached_file( $post->ID ) ), 'mime_type' => (string) $post->post_mime_type, 'url' => (string) wp_get_attachment_url( $post->ID ), 'caption' => (string) $post->post_excerpt, 'description' => (string) $post->post_content, 'alt_text' => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ), 'modified_gmt' => (string) $post->post_modified_gmt, 'metadata_sha256' => samcp_media_hash( $post ) );
}
function samcp_validate_media_snapshot( $post, $input ) {
	if ( (string) $post->post_modified_gmt !== (string) $input['expected_modified_gmt'] || ! hash_equals( samcp_media_hash( $post ), strtolower( (string) $input['expected_metadata_sha256'] ) ) ) { return new WP_Error( 'samcp_media_changed', __( 'The media metadata changed after it was read. Read it again before retrying.', 'site-abilities-mcp' ) ); }
	return true;
}
function samcp_list_media( $input ) {
	$group = isset( $input['mime_group'] ) ? $input['mime_group'] : 'all';
	$args = array( 'post_type' => 'attachment', 'post_status' => isset( $input['status'] ) ? $input['status'] : 'inherit', 'posts_per_page' => isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : 20, 'orderby' => 'modified', 'order' => 'DESC' );
	if ( 'image' === $group ) { $args['post_mime_type'] = 'image'; } elseif ( 'pdf' === $group ) { $args['post_mime_type'] = 'application/pdf'; }
	if ( ! empty( $input['search'] ) ) { $args['s'] = sanitize_text_field( $input['search'] ); }
	$items = array_filter( get_posts( $args ), static function ( $post ) { return in_array( $post->post_mime_type, array_values( samcp_allowed_media_mimes() ), true ) && current_user_can( 'read_post', $post->ID ); } );
	return array_values( array_map( 'samcp_format_media', $items ) );
}
function samcp_get_media( $input ) { $post = samcp_find_media( $input['media_id'] ); return is_wp_error( $post ) ? $post : samcp_format_media( $post ); }

function samcp_finish_media_upload( $file, $title, $alt ) {
	require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
	if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) { return new WP_Error( 'samcp_media_temp_missing', __( 'The temporary upload file is missing.', 'site-abilities-mcp' ) ); }
	$data = file_get_contents( $file['tmp_name'] );
	wp_delete_file( $file['tmp_name'] );
	if ( false === $data ) { return new WP_Error( 'samcp_media_read_failed', __( 'The temporary upload file could not be read.', 'site-abilities-mcp' ) ); }
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) { unset( $data ); return new WP_Error( 'samcp_media_write_failed', (string) $uploads['error'] ); }
	if ( ! wp_mkdir_p( $uploads['path'] ) ) { unset( $data ); return new WP_Error( 'samcp_media_write_failed', __( 'WordPress could not prepare the uploads directory.', 'site-abilities-mcp' ) ); }
	$filename = wp_unique_filename( $uploads['path'], sanitize_file_name( $file['name'] ) );
	$target = trailingslashit( $uploads['path'] ) . $filename;
	$expected_bytes = strlen( $data );
	$written = file_put_contents( $target, $data );
	unset( $data );
	if ( false === $written || $expected_bytes !== $written ) { if ( file_exists( $target ) ) { wp_delete_file( $target ); } return new WP_Error( 'samcp_media_write_failed', __( 'WordPress could not write the uploaded file.', 'site-abilities-mcp' ) ); }
	$actual = wp_check_filetype( $filename, samcp_allowed_media_mimes() );
	if ( empty( $actual['type'] ) ) { wp_delete_file( $target ); return new WP_Error( 'samcp_media_type_forbidden', __( 'The uploaded file is not an allowed image or PDF.', 'site-abilities-mcp' ) ); }
	$attachment_title = $title ? sanitize_text_field( $title ) : sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) );
	$id = wp_insert_attachment( array( 'post_mime_type' => $actual['type'], 'post_title' => $attachment_title, 'post_content' => '', 'post_status' => 'inherit' ), $target, 0, true );
	if ( is_wp_error( $id ) ) { wp_delete_file( $target ); return $id; }
	$metadata = wp_generate_attachment_metadata( $id, $target );
	if ( is_array( $metadata ) ) { wp_update_attachment_metadata( $id, $metadata ); }
	$post = get_post( $id );
	if ( ! $post || ! get_attached_file( $id ) || ! in_array( (string) $post->post_mime_type, array_values( samcp_allowed_media_mimes() ), true ) ) {
		wp_delete_attachment( $id, true );
		return new WP_Error( 'samcp_media_upload_incomplete', __( 'WordPress did not create a valid media attachment.', 'site-abilities-mcp' ) );
	}
	if ( '' !== $alt ) { update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) ); }
	return samcp_format_media( $post );
}
function samcp_upload_media_base64( $input ) {
	if ( empty( $input['confirmation'] ) || 'UPLOAD_MEDIA' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPLOAD_MEDIA confirmation is required.', 'site-abilities-mcp' ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$filename = sanitize_file_name( $input['filename'] );
	$check = wp_check_filetype( $filename, samcp_allowed_media_mimes() );
	if ( empty( $check['type'] ) ) { return new WP_Error( 'samcp_media_type_forbidden', __( 'Only JPG, PNG, GIF, WebP and PDF files are allowed.', 'site-abilities-mcp' ) ); }
	if ( strlen( (string) $input['base64_data'] ) > (int) ceil( SAMCP_MEDIA_MAX_BYTES * 4 / 3 ) + 1024 ) { return new WP_Error( 'samcp_media_size_invalid', __( 'The encoded file is larger than the 10 MB upload limit.', 'site-abilities-mcp' ) ); }
	$data = base64_decode( preg_replace( '#^data:[^;]+;base64,#', '', (string) $input['base64_data'] ), true );
	if ( false === $data || 0 === strlen( $data ) || strlen( $data ) > SAMCP_MEDIA_MAX_BYTES ) { return new WP_Error( 'samcp_media_size_invalid', __( 'The decoded file is empty, invalid or larger than 10 MB.', 'site-abilities-mcp' ) ); }
	$tmp = wp_tempnam( $filename );
	if ( ! $tmp || strlen( $data ) !== file_put_contents( $tmp, $data ) ) { if ( $tmp && file_exists( $tmp ) ) { wp_delete_file( $tmp ); } return new WP_Error( 'samcp_media_write_failed', __( 'The temporary upload file could not be written.', 'site-abilities-mcp' ) ); }
	$actual = wp_check_filetype_and_ext( $tmp, $filename, samcp_allowed_media_mimes() );
	if ( empty( $actual['type'] ) ) { wp_delete_file( $tmp ); return new WP_Error( 'samcp_media_type_forbidden', __( 'The file contents do not match an allowed image or PDF type.', 'site-abilities-mcp' ) ); }
	return samcp_finish_media_upload( array( 'name' => $filename, 'tmp_name' => $tmp ), isset( $input['title'] ) ? $input['title'] : '', isset( $input['alt_text'] ) ? $input['alt_text'] : '' );
}
function samcp_import_media_url( $input ) {
	if ( empty( $input['confirmation'] ) || 'IMPORT_MEDIA' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit IMPORT_MEDIA confirmation is required.', 'site-abilities-mcp' ) ); }
	$url = esc_url_raw( $input['url'] );
	if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) { return new WP_Error( 'samcp_media_url_invalid', __( 'A valid public HTTPS URL is required.', 'site-abilities-mcp' ) ); }
	$head = wp_safe_remote_head( $url, array( 'timeout' => 15, 'redirection' => 3 ) );
	if ( is_wp_error( $head ) ) { return $head; }
	$length = absint( wp_remote_retrieve_header( $head, 'content-length' ) );
	if ( $length > SAMCP_MEDIA_MAX_BYTES ) { return new WP_Error( 'samcp_media_too_large', __( 'The remote file is larger than 10 MB.', 'site-abilities-mcp' ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = wp_tempnam( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
	if ( ! $tmp ) { return new WP_Error( 'samcp_media_temp_failed', __( 'WordPress could not create a temporary download file.', 'site-abilities-mcp' ) ); }
	$response = wp_safe_remote_get( $url, array( 'timeout' => 60, 'redirection' => 3, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => SAMCP_MEDIA_MAX_BYTES + 1 ) );
	if ( is_wp_error( $response ) ) { wp_delete_file( $tmp ); return $response; }
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) { wp_delete_file( $tmp ); return new WP_Error( 'samcp_media_download_failed', __( 'The remote server did not return a successful file response.', 'site-abilities-mcp' ) ); }
	if ( filesize( $tmp ) > SAMCP_MEDIA_MAX_BYTES ) { wp_delete_file( $tmp ); return new WP_Error( 'samcp_media_too_large', __( 'The downloaded file is larger than 10 MB.', 'site-abilities-mcp' ) ); }
	$filename = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
	$check = wp_check_filetype_and_ext( $tmp, $filename, samcp_allowed_media_mimes() );
	if ( empty( $check['type'] ) ) { wp_delete_file( $tmp ); return new WP_Error( 'samcp_media_type_forbidden', __( 'The remote file is not an allowed image or PDF.', 'site-abilities-mcp' ) ); }
	return samcp_finish_media_upload( array( 'name' => $filename, 'tmp_name' => $tmp ), isset( $input['title'] ) ? $input['title'] : '', isset( $input['alt_text'] ) ? $input['alt_text'] : '' );
}
function samcp_update_media( $input ) {
	$post = samcp_find_media( $input['media_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	if ( empty( $input['confirmation'] ) || 'UPDATE_MEDIA' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit UPDATE_MEDIA confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_media_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$data = array( 'ID' => $post->ID ); foreach ( array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' ) as $key => $field ) { if ( array_key_exists( $key, $input ) ) { $data[ $field ] = 'title' === $key ? sanitize_text_field( $input[ $key ] ) : (string) $input[ $key ]; } }
	if ( 1 === count( $data ) && ! array_key_exists( 'alt_text', $input ) ) { return new WP_Error( 'samcp_no_changes', __( 'No media metadata changes were supplied.', 'site-abilities-mcp' ) ); }
	$id = wp_update_post( wp_slash( $data ), true ); if ( is_wp_error( $id ) ) { return $id; }
	if ( array_key_exists( 'alt_text', $input ) ) { update_post_meta( $post->ID, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) ); }
	return samcp_format_media( get_post( $post->ID ) );
}
function samcp_trash_media( $input ) {
	$post = samcp_find_media( $input['media_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	if ( empty( $input['confirmation'] ) || 'TRASH_MEDIA' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit TRASH_MEDIA confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_media_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$result = wp_trash_post( $post->ID ); return $result ? samcp_format_media( get_post( $post->ID ) ) : new WP_Error( 'samcp_trash_failed', __( 'WordPress could not move the media item to trash.', 'site-abilities-mcp' ) );
}
function samcp_restore_trashed_media( $input ) {
	$post = samcp_find_media( $input['media_id'] ); if ( is_wp_error( $post ) ) { return $post; }
	if ( 'trash' !== $post->post_status ) { return new WP_Error( 'samcp_media_not_trashed', __( 'Only trashed media can be restored.', 'site-abilities-mcp' ) ); }
	if ( empty( $input['confirmation'] ) || 'RESTORE_TRASHED_MEDIA' !== $input['confirmation'] ) { return new WP_Error( 'samcp_confirmation_required', __( 'Explicit RESTORE_TRASHED_MEDIA confirmation is required.', 'site-abilities-mcp' ) ); }
	$check = samcp_validate_media_snapshot( $post, $input ); if ( is_wp_error( $check ) ) { return $check; }
	$result = wp_untrash_post( $post->ID ); return $result ? samcp_format_media( get_post( $post->ID ) ) : new WP_Error( 'samcp_untrash_failed', __( 'WordPress could not restore the media item.', 'site-abilities-mcp' ) );
}
