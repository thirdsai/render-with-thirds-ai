<?php
/**
 * Settings access.
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the plugin settings and the API key.
 */
class RWTAI_Settings {

	const OPTION     = 'rwtai_settings';
	const KEY_OPTION = 'rwtai_api_key';

	/**
	 * Default settings. Nothing is sent anywhere until "enabled" is turned on and a key is set.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => false,
			'source'           => 'layout',
			'template_id'      => '',
			'template_version' => 0,
			'post_types'       => array( 'post' ),
			'featured'         => 'empty',
			'og'               => true,
			'background'       => '#111827',
			'accent'           => '#f59e0b',
			'text'             => '#ffffff',
		);
	}

	/**
	 * All settings merged with the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * One setting.
	 *
	 * @param string $name Setting name.
	 * @return mixed
	 */
	public static function get( $name ) {
		$all = self::all();
		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * Whether the key comes from the THIRDS_API_KEY constant in wp-config.php.
	 *
	 * @return bool
	 */
	public static function key_from_constant() {
		return defined( 'THIRDS_API_KEY' ) && is_string( THIRDS_API_KEY ) && '' !== THIRDS_API_KEY;
	}

	/**
	 * The API key: the wp-config.php constant first, then the saved option. Never logged or printed.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( self::key_from_constant() ) {
			return THIRDS_API_KEY;
		}
		$key = get_option( self::KEY_OPTION, '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * True only when the site owner opted in and a key is present.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) self::get( 'enabled' ) && '' !== self::api_key();
	}

	/**
	 * Whether a post type is handled.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function handles( $post_type ) {
		$types = (array) self::get( 'post_types' );
		return in_array( $post_type, $types, true );
	}
}
