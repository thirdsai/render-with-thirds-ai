<?php
/**
 * Open Graph output.
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints og:image for the rendered image, or hands it to Yoast SEO or Rank Math when one of them is active.
 */
class RWTAI_OG {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_tags' ), 5 );
		add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'filter_url' ) );
		add_filter( 'wpseo_twitter_image', array( __CLASS__, 'filter_url' ) );
		add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'filter_url' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( __CLASS__, 'filter_url' ) );
	}

	/**
	 * Whether an SEO plugin that prints its own Open Graph tags is active.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		$active = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
		/**
		 * Filters whether another plugin prints the Open Graph tags. When true, this plugin prints none.
		 *
		 * @param bool $active Whether Yoast SEO or Rank Math was found.
		 */
		return (bool) apply_filters( 'rwtai_seo_plugin_active', $active );
	}

	/**
	 * The rendered image for the current singular post.
	 *
	 * @return array|null Array of url, width, height.
	 */
	public static function current_image() {
		if ( ! RWTAI_Settings::get( 'og' ) || ! is_singular() ) {
			return null;
		}
		$post_id = (int) get_queried_object_id();
		$id      = (int) get_post_meta( $post_id, '_rwtai_image_id', true );
		if ( ! $id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * Prints the tags when no SEO plugin handles Open Graph.
	 */
	public static function print_tags() {
		if ( self::seo_plugin_active() ) {
			return;
		}
		$image = self::current_image();
		if ( ! $image ) {
			return;
		}
		printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image['url'] ) );
		printf( "<meta property=\"og:image:width\" content=\"%d\" />\n", (int) $image['width'] );
		printf( "<meta property=\"og:image:height\" content=\"%d\" />\n", (int) $image['height'] );
		printf( "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n" );
		printf( "<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url( $image['url'] ) );
	}

	/**
	 * Replaces the SEO plugin's image URL with the rendered one.
	 *
	 * @param string $url URL chosen by the SEO plugin.
	 * @return string
	 */
	public static function filter_url( $url ) {
		$image = self::current_image();
		return $image ? $image['url'] : $url;
	}
}
