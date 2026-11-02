<?php
/**
 * Plugin Name:       Render with thirds.ai
 * Description:       Renders a branded Open Graph and featured image for each post through thirds.ai when it is published, and adds it to the media library.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            thirds.ai
 * Author URI:        https://thirds.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       render-with-thirds-ai
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

define( 'RWTAI_VERSION', '0.1.0' );
define( 'RWTAI_FILE', __FILE__ );
define( 'RWTAI_DIR', plugin_dir_path( __FILE__ ) );

require_once RWTAI_DIR . 'includes/class-rwtai-settings.php';
require_once RWTAI_DIR . 'includes/class-rwtai-client.php';
require_once RWTAI_DIR . 'includes/class-rwtai-renderer.php';
require_once RWTAI_DIR . 'includes/class-rwtai-og.php';

RWTAI_Renderer::init();
RWTAI_OG::init();

if ( is_admin() ) {
	require_once RWTAI_DIR . 'includes/class-rwtai-admin.php';
	RWTAI_Admin::init();
}
