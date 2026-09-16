<?php
/**
 * Settings screen under Settings > Bearius SEO.
 *
 * @package BeariusSEOLight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page and option sanitization.
 */
class Bearius_SEO_Settings {

	/**
	 * Menu and option slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bearius-seo-light';

	/**
	 * Hooks the admin screens.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BEARIUS_SEO_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds a settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'bearius-seo-light' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Registers the settings subpage.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'Bearius SEO Light', 'bearius-seo-light' ),
			__( 'Bearius SEO', 'bearius-seo-light' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the single option holding every setting.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'bearius_seo_group',
			BEARIUS_SEO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => bearius_seo_default_settings(),
			)
		);
	}

	/**
	 * Loads the media picker on the settings screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'bearius-seo-admin',
			BEARIUS_SEO_URL . 'assets/css/admin.css',
			array(),
			BEARIUS_SEO_VERSION
		);

		wp_enqueue_script(
			'bearius-seo-admin',
			BEARIUS_SEO_URL . 'assets/js/admin.js',
			array(),
			BEARIUS_SEO_VERSION,
			true
		);

		wp_localize_script(
			'bearius-seo-admin',
			'beariusSeoL10n',
			array(
				'frameTitle'  => __( 'Select an image', 'bearius-seo-light' ),
				'frameButton' => __( 'Use this image', 'bearius-seo-light' ),
			)
		);
	}

	/**
	 * Sanitizes every field before it is stored.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = bearius_seo_default_settings();
		$clean    = array();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$clean['org_name']         = sanitize_text_field( isset( $input['org_name'] ) ? $input['org_name'] : '' );
		$clean['org_logo']         = esc_url_raw( isset( $input['org_logo'] ) ? $input['org_logo'] : '' );
		$clean['site_description'] = sanitize_textarea_field( isset( $input['site_description'] ) ? $input['site_description'] : '' );
		$clean['default_image']    = esc_url_raw( isset( $input['default_image'] ) ? $input['default_image'] : '' );
		$clean['twitter_site']     = sanitize_text_field( ltrim( isset( $input['twitter_site'] ) ? $input['twitter_site'] : '', '@' ) );

		$checkboxes = array(
			'news_mode',
			'schema_org',
			'schema_website',
			'schema_article',
			'schema_breadcrumbs',
			'schema_archives',
			'output_meta',
			'title_rewrite',
		);

		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$threshold                   = isset( $input['modified_threshold'] ) ? absint( $input['modified_threshold'] ) : $defaults['modified_threshold'];
		$clean['modified_threshold'] = min( 10080, max( 1, $threshold ) );

		return $clean;
	}

	/**
	 * Renders a checkbox row.
	 *
	 * @param array  $settings    Current settings.
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $description Help text.
	 * @return void
	 */
	private function checkbox_row( $settings, $key, $label, $description ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox"
						name="<?php echo esc_attr( BEARIUS_SEO_OPTION . '[' . $key . ']' ); ?>"
						value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
					<?php echo esc_html( $description ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders an image field with a media picker button.
	 *
	 * @param array  $settings    Current settings.
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $description Help text.
	 * @return void
	 */
	private function image_row( $settings, $key, $label, $description ) {
		?>
		<tr>
			<th scope="row">
				<label for="bearius-seo-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<div class="bearius-seo-image-row">
					<span class="bearius-seo-thumb"
						<?php if ( $settings[ $key ] ) : ?>
							style="background-image:url('<?php echo esc_url( $settings[ $key ] ); ?>')"
						<?php endif; ?>></span>
					<span class="bearius-seo-image-controls">
						<input type="url" class="regular-text bearius-seo-image-field"
							id="bearius-seo-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( BEARIUS_SEO_OPTION . '[' . $key . ']' ); ?>"
							value="<?php echo esc_url( $settings[ $key ] ); ?>" />
						<span class="bearius-seo-image-buttons">
							<button type="button" class="button bearius-seo-image-pick">
								<?php esc_html_e( 'Select image', 'bearius-seo-light' ); ?>
							</button>
							<button type="button" class="button-link bearius-seo-image-clear">
								<?php esc_html_e( 'Clear', 'bearius-seo-light' ); ?>
							</button>
						</span>
						<span class="bearius-seo-help"><?php echo esc_html( $description ); ?></span>
					</span>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = bearius_seo_get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bearius SEO Light', 'bearius-seo-light' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'bearius_seo_group' ); ?>

				<h2><?php esc_html_e( 'Publisher', 'bearius-seo-light' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bearius-seo-org-name"><?php esc_html_e( 'Publisher name', 'bearius-seo-light' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="bearius-seo-org-name"
								name="<?php echo esc_attr( BEARIUS_SEO_OPTION ); ?>[org_name]"
								value="<?php echo esc_attr( $settings['org_name'] ); ?>"
								placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used as the Organization name in structured data. Leave empty to use the site title.', 'bearius-seo-light' ); ?></p>
						</td>
					</tr>

					<?php
					$this->image_row(
						$settings,
						'org_logo',
						__( 'Publisher logo', 'bearius-seo-light' ),
						__( 'Falls back to your theme logo, then the site icon.', 'bearius-seo-light' )
					);
					?>

					<tr>
						<th scope="row">
							<label for="bearius-seo-site-desc"><?php esc_html_e( 'Home page description', 'bearius-seo-light' ); ?></label>
						</th>
						<td>
							<textarea class="large-text" rows="2" id="bearius-seo-site-desc"
								name="<?php echo esc_attr( BEARIUS_SEO_OPTION ); ?>[site_description]"><?php echo esc_textarea( $settings['site_description'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Meta description for the front page. Leave empty to use the site tagline.', 'bearius-seo-light' ); ?></p>
						</td>
					</tr>

					<?php
					$this->image_row(
						$settings,
						'default_image',
						__( 'Default social image', 'bearius-seo-light' ),
						__( 'Used when a post has no featured image. Recommended size 1200x630 pixels.', 'bearius-seo-light' )
					);
					?>

					<tr>
						<th scope="row">
							<label for="bearius-seo-twitter"><?php esc_html_e( 'X / Twitter handle', 'bearius-seo-light' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="bearius-seo-twitter"
								name="<?php echo esc_attr( BEARIUS_SEO_OPTION ); ?>[twitter_site]"
								value="<?php echo esc_attr( $settings['twitter_site'] ); ?>"
								placeholder="yoursite" />
							<p class="description"><?php esc_html_e( 'Without the @ sign. Optional.', 'bearius-seo-light' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Content type', 'bearius-seo-light' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row(
						$settings,
						'news_mode',
						__( 'News publisher', 'bearius-seo-light' ),
						__( 'Mark posts as NewsArticle instead of Article. Enable this only if you publish news.', 'bearius-seo-light' )
					);
					?>

					<tr>
						<th scope="row">
							<label for="bearius-seo-threshold"><?php esc_html_e( 'Modified date threshold', 'bearius-seo-light' ); ?></label>
						</th>
						<td>
							<input type="number" class="small-text" id="bearius-seo-threshold" min="1" max="10080" step="1"
								name="<?php echo esc_attr( BEARIUS_SEO_OPTION ); ?>[modified_threshold]"
								value="<?php echo esc_attr( $settings['modified_threshold'] ); ?>" />
							<?php esc_html_e( 'minutes', 'bearius-seo-light' ); ?>
							<p class="description"><?php esc_html_e( 'Edits made sooner than this after publishing do not update the modified date. Prevents typo fixes from looking like real updates.', 'bearius-seo-light' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Output', 'bearius-seo-light' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row(
						$settings,
						'output_meta',
						__( 'Meta tags', 'bearius-seo-light' ),
						__( 'Description, canonical, Open Graph and X Card tags.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'title_rewrite',
						__( 'Custom titles', 'bearius-seo-light' ),
						__( 'Let the SEO title field override the browser title.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'schema_org',
						__( 'Organization', 'bearius-seo-light' ),
						__( 'Publisher data on every page.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'schema_website',
						__( 'WebSite', 'bearius-seo-light' ),
						__( 'Site data and search action on the front page.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'schema_article',
						__( 'Article', 'bearius-seo-light' ),
						__( 'Structured data on single posts and pages.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'schema_breadcrumbs',
						__( 'Breadcrumbs', 'bearius-seo-light' ),
						__( 'Breadcrumb trail data on single posts.', 'bearius-seo-light' )
					);

					$this->checkbox_row(
						$settings,
						'schema_archives',
						__( 'Archives', 'bearius-seo-light' ),
						__( 'Item lists on category and tag archives.', 'bearius-seo-light' )
					);
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
