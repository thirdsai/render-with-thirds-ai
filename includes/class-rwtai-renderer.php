<?php
/**
 * Builds the render request from a post and stores the result.
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publish hook, background event and the render itself.
 */
class RWTAI_Renderer {

	const EVENT  = 'rwtai_render_post';
	const WIDTH  = 1200;
	const HEIGHT = 630;

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( self::EVENT, array( __CLASS__, 'render_post' ) );
	}

	/**
	 * Queues a background render when a handled post is first published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! RWTAI_Settings::is_active() || ! RWTAI_Settings::handles( $post->post_type ) ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::EVENT, array( (int) $post->ID ) ) ) {
			wp_schedule_single_event( time(), self::EVENT, array( (int) $post->ID ) );
		}
	}

	/**
	 * The data sent to thirds.ai for one post. Only these fields leave the site.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function data_for( WP_Post $post ) {
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( strip_shortcodes( $post->post_content ), 24, '...' );
		$terms   = get_the_category( $post->ID );
		$author  = get_the_author_meta( 'display_name', (int) $post->post_author );
		$data    = array(
			'title'      => self::plain( get_the_title( $post ) ),
			'excerpt'    => self::plain( $excerpt ),
			'site_name'  => self::plain( get_bloginfo( 'name' ) ),
			'site_host'  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'author'     => self::plain( (string) $author ),
			'date'       => (string) get_the_date( '', $post ),
			'category'   => ! empty( $terms ) ? self::plain( $terms[0]->name ) : '',
			'background' => (string) RWTAI_Settings::get( 'background' ),
			'accent'     => (string) RWTAI_Settings::get( 'accent' ),
			'text'       => (string) RWTAI_Settings::get( 'text' ),
		);
		/**
		 * Filters the template data sent to thirds.ai for a post.
		 *
		 * @param array   $data Data.
		 * @param WP_Post $post Post.
		 */
		return (array) apply_filters( 'rwtai_template_data', $data, $post );
	}

	/**
	 * Plain text without tags or HTML entities. The template engine escapes it again.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * The full request body for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return array|WP_Error
	 */
	public static function body_for( WP_Post $post ) {
		$body = array(
			'data'      => (object) self::data_for( $post ),
			'image'     => array(
				'format' => 'png',
				'width'  => self::WIDTH,
				'height' => self::HEIGHT,
			),
			'filename'  => self::file_base( $post ),
			'reference' => 'wordpress post ' . (int) $post->ID,
		);
		if ( 'template' === RWTAI_Settings::get( 'source' ) ) {
			$template = (string) RWTAI_Settings::get( 'template_id' );
			if ( ! preg_match( '/^tpl_[a-f0-9]{32}$/', $template ) ) {
				return new WP_Error( 'rwtai_no_template', __( 'Set a saved template id on the settings page.', 'render-with-thirds-ai' ) );
			}
			$body['template_id'] = $template;
			$version             = (int) RWTAI_Settings::get( 'template_version' );
			if ( $version > 0 ) {
				$body['version'] = $version;
			}
		} else {
			$layout = self::layout();
			if ( '' === $layout ) {
				return new WP_Error( 'rwtai_no_layout', __( 'The bundled layout file is missing.', 'render-with-thirds-ai' ) );
			}
			$body['html'] = $layout;
		}
		return $body;
	}

	/**
	 * The bundled layout. thirds.ai fills and escapes the {{ }} fields from the data.
	 *
	 * @return string
	 */
	public static function layout() {
		$file = RWTAI_DIR . 'layouts/og-card.html';
		/**
		 * Filters the path of the HTML layout used when no saved template is set.
		 *
		 * @param string $file Absolute path.
		 */
		$file = (string) apply_filters( 'rwtai_layout_file', $file );
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$html = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		return is_string( $html ) ? $html : '';
	}

	/**
	 * A safe file name base for the post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function file_base( WP_Post $post ) {
		$slug = sanitize_title( $post->post_name ? $post->post_name : 'post-' . $post->ID );
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug );
		$slug = substr( $slug ? $slug : 'post', 0, 100 );
		return 'og-' . $slug;
	}

	/**
	 * Renders the image for a post, adds it to the media library and applies it.
	 *
	 * @param int $post_id Post id.
	 * @return int|WP_Error Attachment id.
	 */
	public static function render_post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! RWTAI_Settings::handles( $post->post_type ) ) {
			return new WP_Error( 'rwtai_skip', __( 'This post is not published or its type is not handled.', 'render-with-thirds-ai' ) );
		}
		$result = self::run( $post );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $post->ID, '_rwtai_last_error', $result->get_error_message() );
		} else {
			delete_post_meta( $post->ID, '_rwtai_last_error' );
		}
		return $result;
	}

	/**
	 * Does the work for render_post().
	 *
	 * @param WP_Post $post Post.
	 * @return int|WP_Error
	 */
	private static function run( WP_Post $post ) {
		$body = self::body_for( $post );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$file = RWTAI_Client::render( $body, 'wp-render-' . wp_generate_uuid4() );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$title = self::plain( get_the_title( $post ) );
		$id    = media_handle_sideload(
			array(
				'name'     => $body['filename'] . '.png',
				'tmp_name' => $file['file'],
			),
			$post->ID,
			/* translators: %s: post title. */
			sprintf( __( 'Social image: %s', 'render-with-thirds-ai' ), $title )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $file['file'] );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
		update_post_meta( $id, '_rwtai_generated', 1 );

		$previous = (int) get_post_meta( $post->ID, '_rwtai_image_id', true );
		$current  = (int) get_post_thumbnail_id( $post );
		$mode     = (string) RWTAI_Settings::get( 'featured' );
		if ( 'always' === $mode || ( 'empty' === $mode && ( ! $current || $current === $previous ) ) ) {
			set_post_thumbnail( $post, $id );
		}

		update_post_meta( $post->ID, '_rwtai_image_id', $id );
		update_post_meta( $post->ID, '_rwtai_rendered_at', time() );
		update_post_meta( $post->ID, '_rwtai_job_id', sanitize_key( $file['job_id'] ) );

		// Remove the image this plugin made for the post last time, unless it is still in use as the featured image.
		if ( $previous && $previous !== $id && get_post_meta( $previous, '_rwtai_generated', true ) && (int) get_post_thumbnail_id( $post ) !== $previous ) {
			wp_delete_attachment( $previous, true );
		}

		/**
		 * Fires after a post image was rendered and saved.
		 *
		 * @param int     $id   Attachment id.
		 * @param WP_Post $post Post.
		 */
		do_action( 'rwtai_rendered', $id, $post );
		return $id;
	}
}
