<?php
/**
 * Settings page, meta box, row action and the Re-render handler.
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
class RWTAI_Admin {

	const PAGE = 'render-with-thirds-ai';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_post_rwtai_rerender', array( __CLASS__, 'handle_rerender' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RWTAI_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds Settings > thirds.ai Render.
	 */
	public static function menu() {
		add_options_page(
			__( 'Render with thirds.ai', 'render-with-thirds-ai' ),
			__( 'thirds.ai Render', 'render-with-thirds-ai' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ), esc_html__( 'Settings', 'render-with-thirds-ai' ) );
		return $links;
	}

	/**
	 * Registers the two options.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			RWTAI_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => RWTAI_Settings::defaults(),
			)
		);
		register_setting(
			self::PAGE,
			RWTAI_Settings::KEY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_key_field' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitises the settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = RWTAI_Settings::defaults();

		$out['enabled'] = ! empty( $input['enabled'] );
		$out['source']  = ( isset( $input['source'] ) && 'template' === $input['source'] ) ? 'template' : 'layout';

		$template           = isset( $input['template_id'] ) ? strtolower( trim( sanitize_text_field( $input['template_id'] ) ) ) : '';
		$out['template_id'] = preg_match( '/^tpl_[a-f0-9]{32}$/', $template ) ? $template : '';
		if ( '' !== $template && '' === $out['template_id'] ) {
			add_settings_error( self::PAGE, 'rwtai_template', __( 'The template id should look like tpl_ followed by 32 letters and digits. It was not saved.', 'render-with-thirds-ai' ) );
		}
		$out['template_version'] = isset( $input['template_version'] ) ? absint( $input['template_version'] ) : 0;

		$public            = get_post_types( array( 'public' => true ) );
		$types             = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array();
		$out['post_types'] = array_values( array_intersect( $types, $public ) );

		$featured        = isset( $input['featured'] ) ? sanitize_key( $input['featured'] ) : 'empty';
		$out['featured'] = in_array( $featured, array( 'empty', 'always', 'never' ), true ) ? $featured : 'empty';
		$out['og']       = ! empty( $input['og'] );

		foreach ( array( 'background', 'accent', 'text' ) as $colour ) {
			$value          = isset( $input[ $colour ] ) ? sanitize_hex_color( $input[ $colour ] ) : '';
			$out[ $colour ] = $value ? $value : RWTAI_Settings::defaults()[ $colour ];
		}
		return $out;
	}

	/**
	 * Keeps the saved key when the field is left empty, clears it when asked.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public static function sanitize_key_field( $input ) {
		// Nonce is checked by options.php before sanitising.
		if ( ! empty( $_POST['rwtai_clear_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		$input = is_string( $input ) ? trim( sanitize_text_field( $input ) ) : '';
		if ( '' === $input ) {
			$old = get_option( RWTAI_Settings::KEY_OPTION, '' );
			return is_string( $old ) ? $old : '';
		}
		return $input;
	}

	/**
	 * The settings page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = RWTAI_Settings::all();
		$name      = RWTAI_Settings::OPTION;
		$has_saved = '' !== (string) get_option( RWTAI_Settings::KEY_OPTION, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Render with thirds.ai', 'render-with-thirds-ai' ); ?></h1>
			<p><?php esc_html_e( 'When a post is published, this plugin sends its title, excerpt, author, date, first category, site name and site address to thirds.ai, which renders a 1200 by 630 PNG from a template. The PNG is added to the media library, used as the Open Graph image and, if you choose, set as the featured image. thirds.ai is a paid service and needs an account and API key.', 'render-with-thirds-ai' ); ?></p>
			<p>
				<a href="https://thirds.ai/terms" target="_blank" rel="noopener"><?php esc_html_e( 'thirds.ai terms', 'render-with-thirds-ai' ); ?></a>
				|
				<a href="https://thirds.ai/privacy" target="_blank" rel="noopener"><?php esc_html_e( 'thirds.ai privacy policy', 'render-with-thirds-ai' ); ?></a>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Send posts to thirds.ai', 'render-with-thirds-ai' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?> />
							<?php esc_html_e( 'I agree that the fields above are sent to thirds.ai when a post is published or re-rendered.', 'render-with-thirds-ai' ); ?></label>
							<p class="description"><?php esc_html_e( 'Off by default. Nothing is sent until this is ticked and a key is set.', 'render-with-thirds-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwtai-key"><?php esc_html_e( 'API key', 'render-with-thirds-ai' ); ?></label></th>
						<td>
							<?php if ( RWTAI_Settings::key_from_constant() ) : ?>
								<p><?php esc_html_e( 'The key is set by the THIRDS_API_KEY constant in wp-config.php.', 'render-with-thirds-ai' ); ?></p>
							<?php else : ?>
								<input type="password" id="rwtai-key" class="regular-text" name="<?php echo esc_attr( RWTAI_Settings::KEY_OPTION ); ?>" value="" autocomplete="off" placeholder="<?php echo esc_attr( $has_saved ? __( 'Saved. Leave empty to keep it.', 'render-with-thirds-ai' ) : '' ); ?>" />
								<?php if ( $has_saved ) : ?>
									<label><input type="checkbox" name="rwtai_clear_key" value="1" /> <?php esc_html_e( 'Remove the saved key', 'render-with-thirds-ai' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Or define THIRDS_API_KEY in wp-config.php to keep the key out of the database.', 'render-with-thirds-ai' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Design', 'render-with-thirds-ai' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[source]" value="layout" <?php checked( 'layout', $s['source'] ); ?> /> <?php esc_html_e( 'Bundled layout (uses the colours below)', 'render-with-thirds-ai' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[source]" value="template" <?php checked( 'template', $s['source'] ); ?> /> <?php esc_html_e( 'My saved thirds.ai template', 'render-with-thirds-ai' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwtai-template"><?php esc_html_e( 'Saved template id', 'render-with-thirds-ai' ); ?></label></th>
						<td>
							<input type="text" id="rwtai-template" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[template_id]" value="<?php echo esc_attr( $s['template_id'] ); ?>" placeholder="tpl_..." />
							<label><?php esc_html_e( 'Version', 'render-with-thirds-ai' ); ?> <input type="number" min="0" class="small-text" name="<?php echo esc_attr( $name ); ?>[template_version]" value="<?php echo esc_attr( (string) $s['template_version'] ); ?>" /></label>
							<p class="description"><?php esc_html_e( 'Version 0 uses the latest. The template receives title, excerpt, site_name, site_host, author, date, category, background, accent and text.', 'render-with-thirds-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Layout colours', 'render-with-thirds-ai' ); ?></th>
						<td>
							<?php
							$labels = array(
								'background' => __( 'Background', 'render-with-thirds-ai' ),
								'accent'     => __( 'Accent', 'render-with-thirds-ai' ),
								'text'       => __( 'Text', 'render-with-thirds-ai' ),
							);
							foreach ( $labels as $field => $label ) :
								?>
								<label><?php echo esc_html( $label ); ?> <input type="text" class="code" size="8" name="<?php echo esc_attr( $name . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $s[ $field ] ); ?>" /></label>&nbsp;
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'render-with-thirds-ai' ); ?></th>
						<td>
							<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) : ?>
								<?php
								if ( 'attachment' === $type->name ) {
									continue;
								}
								?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) $s['post_types'], true ) ); ?> /> <?php echo esc_html( $type->labels->singular_name ); ?></label><br />
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Featured image', 'render-with-thirds-ai' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $name ); ?>[featured]">
								<option value="empty" <?php selected( 'empty', $s['featured'] ); ?>><?php esc_html_e( 'Set it when the post has none', 'render-with-thirds-ai' ); ?></option>
								<option value="always" <?php selected( 'always', $s['featured'] ); ?>><?php esc_html_e( 'Always replace it', 'render-with-thirds-ai' ); ?></option>
								<option value="never" <?php selected( 'never', $s['featured'] ); ?>><?php esc_html_e( 'Never set it (Open Graph only)', 'render-with-thirds-ai' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Open Graph', 'render-with-thirds-ai' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[og]" value="1" <?php checked( $s['og'] ); ?> /> <?php esc_html_e( 'Use the rendered image as og:image', 'render-with-thirds-ai' ); ?></label>
							<p class="description"><?php esc_html_e( 'If Yoast SEO or Rank Math is active, the image is passed to it instead of printing a second set of tags.', 'render-with-thirds-ai' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds the side meta box on handled post types.
	 *
	 * @param string $post_type Post type.
	 */
	public static function meta_box( $post_type ) {
		if ( ! RWTAI_Settings::handles( $post_type ) ) {
			return;
		}
		add_meta_box( 'rwtai-image', __( 'thirds.ai image', 'render-with-thirds-ai' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'side', 'low' );
	}

	/**
	 * The meta box body.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$id    = (int) get_post_meta( $post->ID, '_rwtai_image_id', true );
		$when  = (int) get_post_meta( $post->ID, '_rwtai_rendered_at', true );
		$error = (string) get_post_meta( $post->ID, '_rwtai_last_error', true );
		if ( $id && wp_attachment_is_image( $id ) ) {
			echo wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'max-width:100%;height:auto' ) );
		}
		if ( $when ) {
			/* translators: %s: date and time. */
			echo '<p>' . esc_html( sprintf( __( 'Rendered %s', 'render-with-thirds-ai' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $when ) ) ) . '</p>';
		}
		if ( '' !== $error ) {
			echo '<p style="color:#b32d2e">' . esc_html( $error ) . '</p>';
		}
		if ( ! RWTAI_Settings::is_active() ) {
			echo '<p>' . esc_html__( 'Turn on sending and set a key on the settings page first.', 'render-with-thirds-ai' ) . '</p>';
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'The image is rendered when the post is published.', 'render-with-thirds-ai' ) . '</p>';
			return;
		}
		printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( self::rerender_url( $post->ID ) ), esc_html__( 'Re-render image', 'render-with-thirds-ai' ) );
	}

	/**
	 * Row action on the posts list.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( RWTAI_Settings::is_active() && 'publish' === $post->post_status && RWTAI_Settings::handles( $post->post_type ) && current_user_can( 'edit_post', $post->ID ) && current_user_can( 'upload_files' ) ) {
			$actions['rwtai_rerender'] = sprintf( '<a href="%s">%s</a>', esc_url( self::rerender_url( $post->ID ) ), esc_html__( 'Re-render image', 'render-with-thirds-ai' ) );
		}
		return $actions;
	}

	/**
	 * Nonced link to the Re-render handler.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function rerender_url( $post_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=rwtai_rerender&post=' . (int) $post_id ), 'rwtai_rerender_' . (int) $post_id );
	}

	/**
	 * Re-renders one post now, then goes back.
	 */
	public static function handle_rerender() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'rwtai_rerender_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to re-render this image.', 'render-with-thirds-ai' ), 403 );
		}
		$result = RWTAI_Renderer::render_post( $post_id );
		$back   = wp_get_referer();
		$back   = $back ? $back : get_edit_post_link( $post_id, 'url' );
		wp_safe_redirect( add_query_arg( 'rwtai_done', is_wp_error( $result ) ? 'error' : 'ok', remove_query_arg( 'rwtai_done', $back ) ) );
		exit;
	}

	/**
	 * Result notice after a Re-render.
	 */
	public static function notices() {
		if ( ! isset( $_GET['rwtai_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			return;
		}
		$ok = 'ok' === sanitize_key( wp_unslash( $_GET['rwtai_done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $ok ? 'success' : 'error' ),
			esc_html( $ok ? __( 'The thirds.ai image was rendered.', 'render-with-thirds-ai' ) : __( 'The thirds.ai image could not be rendered. See the thirds.ai image box on the post for the reason.', 'render-with-thirds-ai' ) )
		);
	}
}
