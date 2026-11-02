<?php
/**
 * Removes the plugin settings and post meta. Images already in the media library are kept.
 *
 * @package RenderWithThirdsAi
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rwtai_settings' );
delete_option( 'rwtai_api_key' );
foreach ( array( '_rwtai_image_id', '_rwtai_rendered_at', '_rwtai_job_id', '_rwtai_last_error' ) as $rwtai_meta_key ) {
	delete_post_meta_by_key( $rwtai_meta_key );
}
