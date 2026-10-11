<?php
/**
 * Settings screen.
 *
 * Every tab posts only its own fields. The sanitize callback merges them
 * over the stored values, so saving one tab never resets another.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the option, the page and the settings tabs.
 */
class Bearius_SEO_Settings {

	/**
	 * Keys handled by each settings tab.
	 *
	 * @return array
	 */
	public static function tab_keys() {
		return array(
			'overview'      => array( 'output_meta', 'mod_schema', 'mod_terms', 'mod_sitemap', 'mod_news', 'mod_robots', 'mod_verification', 'mod_breadcrumbs', 'mod_audit', 'mod_widget', 'credit_link' ),
			'publisher'     => array( 'org_name', 'org_logo', 'site_description', 'default_image', 'twitter_site', 'news_mode', 'modified_threshold' ),
			'meta'          => array( 'title_rewrite', 'meta_description', 'meta_canonical', 'meta_og', 'meta_twitter', 'meta_og_alternate', 'perf_eager_image', 'schema_org', 'schema_website', 'schema_article', 'schema_breadcrumbs', 'schema_archives' ),
			'sitemaps'      => array( 'sitemap_post_types', 'sitemap_taxonomies', 'sitemap_max_urls', 'sitemap_home', 'news_post_types', 'news_exclude_cats', 'news_names' ),
			'robots'        => array( 'robots_custom' ),
			'ai'            => array( 'robots_ai', 'robots_ai_blocked', 'ai_reviewed' ),
			'site'          => array( 'rss_disable', 'rss_image', 'rss_footer', 'rss_delay', 'rss_skip_noindex', 'rss_exclude_cats', 'tags_mode', 'comments_mode', 'archive_author', 'archive_date' ),
			'indexing'      => array( 'mod_indexnow', 'indexnow_key', 'verify_google', 'verify_bing', 'verify_yandex', 'verify_seznam', 'verify_naver', 'verify_baidu', 'verify_pinterest' ),
			'notifications' => array( 'mod_widget', 'notify_notices', 'notify_email', 'notify_email_to' ),
		);
	}

	/**
	 * Hooks the settings screen.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_address' ) );
		add_action( 'admin_post_bearius_seo_ui_language', array( $this, 'save_ui_language' ) );
		add_filter( 'submenu_file', array( $this, 'current_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BEARIUS_SEO_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the settings link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( bearius_seo_admin_url() ) . '">' . esc_html__( 'Settings', 'bearius-seo-light' ) . '</a>' );

		return $links;
	}

	/**
	 * Returns the menu icon as a data URI, coloured by WordPress like core icons.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 56 56" fill="black"><ellipse cx="11.5" cy="22" rx="5.2" ry="6.6" transform="rotate(-22 11.5 22)"/><ellipse cx="19.8" cy="13.8" rx="5" ry="6.9" transform="rotate(-8 19.8 13.8)"/><ellipse cx="29.2" cy="13.8" rx="5" ry="6.9" transform="rotate(8 29.2 13.8)"/><ellipse cx="37.5" cy="22" rx="5.2" ry="6.6" transform="rotate(22 37.5 22)"/><path d="M24.5 23.5c5.6 0 11.2 4.6 11.2 10.1c0 5.1 -4.6 8.2 -11.2 8.2c-6.6 0 -11.2 -3.1 -11.2 -8.2c0 -5.5 5.6 -10.1 11.2 -10.1z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon.
	}

	/**
	 * Returns the quick links shown under the menu item.
	 *
	 * @return array Tab key => label.
	 */
	public static function menu_items() {
		/**
		 * Filters the quick links under the Bearius SEO menu item.
		 *
		 * @param array $items Tab key => label.
		 */
		return (array) apply_filters(
			'bearius_seo_menu_items',
			array(
				'audit' => __( 'Audit', 'bearius-seo-light' ),
			)
		);
	}

	/**
	 * Adds the top level menu item with quick links.
	 *
	 * @return void
	 */
	public function add_page() {
		add_menu_page(
			__( 'Bearius SEO Light', 'bearius-seo-light' ),
			__( 'Bearius SEO', 'bearius-seo-light' ),
			'manage_options',
			'bearius-seo-light',
			array( $this, 'render' ),
			$this->menu_icon(),
			81
		);

		add_submenu_page( 'bearius-seo-light', __( 'Bearius SEO Light', 'bearius-seo-light' ), __( 'Overview', 'bearius-seo-light' ), 'manage_options', 'bearius-seo-light', array( $this, 'render' ) );

		foreach ( self::menu_items() as $tab => $label ) {
			add_submenu_page( 'bearius-seo-light', $label, $label, 'manage_options', 'admin.php?page=bearius-seo-light&tab=' . sanitize_key( $tab ) );
		}
	}

	/**
	 * Highlights the quick link of the open tab.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null
	 */
	public function current_submenu( $submenu_file ) {
		global $plugin_page;

		if ( 'bearius-seo-light' !== $plugin_page ) {
			return $submenu_file;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return isset( self::menu_items()[ $tab ] ) ? 'admin.php?page=bearius-seo-light&tab=' . $tab : 'bearius-seo-light';
	}

	/**
	 * Sends bookmarks of the old Settings page to the new address.
	 *
	 * @return void
	 */
	public function redirect_old_address() {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only redirect.
		if ( 'options-general.php' !== $pagenow || ! isset( $_GET['page'] ) || 'bearius-seo-light' !== $_GET['page'] ) {
			return;
		}

		$args = array();
		foreach ( array( 'tab', 'settings-updated' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable

		$tab = isset( $args['tab'] ) ? $args['tab'] : 'overview';
		unset( $args['tab'] );

		wp_safe_redirect( bearius_seo_admin_url( $tab, $args ) );
		exit;
	}

	/**
	 * Registers the option.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'bearius_seo_settings_group',
			BEARIUS_SEO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => bearius_seo_default_settings(),
			)
		);
	}

	/**
	 * Loads the screen assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( bearius_seo_screen_id() !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'bearius-seo-admin', BEARIUS_SEO_URL . 'assets/css/admin.css', array(), BEARIUS_SEO_VERSION );
		wp_enqueue_style( 'bearius-seo-settings', BEARIUS_SEO_URL . 'assets/css/settings.css', array( 'bearius-seo-admin' ), BEARIUS_SEO_VERSION );
		wp_enqueue_script( 'bearius-seo-admin', BEARIUS_SEO_URL . 'assets/js/admin.js', array(), BEARIUS_SEO_VERSION, true );
		wp_enqueue_script( 'bearius-seo-settings', BEARIUS_SEO_URL . 'assets/js/settings.js', array(), BEARIUS_SEO_VERSION, true );

		wp_localize_script(
			'bearius-seo-admin',
			'beariusSeoL10n',
			array(
				'frameTitle'  => __( 'Select an image', 'bearius-seo-light' ),
				'frameButton' => __( 'Use this image', 'bearius-seo-light' ),
			)
		);

		wp_localize_script(
			'bearius-seo-settings',
			'beariusSeoSettings',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'importNonce' => wp_create_nonce( 'bearius_seo_import' ),
				'i18n'        => array(
					'importing' => __( 'Importing, keep this page open...', 'bearius-seo-light' ),
					'terms'     => __( 'Importing categories and tags...', 'bearius-seo-light' ),
					'done'      => __( 'Import finished.', 'bearius-seo-light' ),
					'failed'    => __( 'The import stopped because of an error. Try again; finished entries are skipped.', 'bearius-seo-light' ),
					'processed' => __( 'entries read', 'bearius-seo-light' ),
					'title'     => __( 'SEO titles', 'bearius-seo-light' ),
					'desc'      => __( 'descriptions', 'bearius-seo-light' ),
					'noindex'   => __( 'noindex flags', 'bearius-seo-light' ),
					'social'    => __( 'social titles', 'bearius-seo-light' ),
					'image'     => __( 'social images', 'bearius-seo-light' ),
					'confirm'   => __( 'Leave without saving? Your changes will be lost.', 'bearius-seo-light' ),
					'copied'    => __( 'Copied', 'bearius-seo-light' ),
				),
			)
		);
	}

	/**
	 * Extracts a verification code from a pasted meta tag or plain code.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function verification_code( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', $value, $match ) ) {
			$value = $match[1];
		}

		return preg_replace( '/[^a-zA-Z0-9_\-\.=]/', '', $value );
	}

	/**
	 * Sanitizes the posted tab and merges it over the stored settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$stored = bearius_seo_get_settings();

		if ( ! is_array( $input ) ) {
			return $stored;
		}

		// Without a tab marker (a second pass by add_option() or a hand made
		// request) every known key that was sent is sanitized; nothing is
		// ever stored as received.
		$full = ! isset( $input['_tab'] );
		$keys = self::tab_keys();

		if ( $full ) {
			$list = array_unique( call_user_func_array( 'array_merge', array_values( $keys ) ) );
		} else {
			$tab = sanitize_key( $input['_tab'] );
			if ( ! isset( $keys[ $tab ] ) ) {
				return $stored;
			}
			$list = $keys[ $tab ];
		}

		$booleans = array( 'output_meta', 'mod_schema', 'mod_terms', 'mod_sitemap', 'mod_news', 'mod_robots', 'mod_indexnow', 'mod_verification', 'mod_breadcrumbs', 'mod_audit', 'mod_widget', 'news_mode', 'title_rewrite', 'meta_description', 'meta_canonical', 'meta_og', 'meta_twitter', 'meta_og_alternate', 'perf_eager_image', 'schema_org', 'schema_website', 'schema_article', 'schema_breadcrumbs', 'schema_archives', 'sitemap_home', 'notify_notices', 'notify_email', 'credit_link', 'rss_image', 'rss_footer', 'rss_skip_noindex' );

		foreach ( $list as $key ) {
			if ( $full && ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;

			if ( in_array( $key, $booleans, true ) ) {
				$stored[ $key ] = empty( $value ) ? 0 : 1;
				continue;
			}

			switch ( $key ) {
				case 'org_logo':
				case 'default_image':
					$stored[ $key ] = esc_url_raw( (string) $value );
					break;

				case 'site_description':
					$stored[ $key ] = sanitize_textarea_field( (string) $value );
					break;

				case 'twitter_site':
					$stored[ $key ] = preg_replace( '/[^A-Za-z0-9_]/', '', ltrim( trim( (string) $value ), '@' ) );
					break;

				case 'modified_threshold':
					$stored[ $key ] = min( 1440, max( 1, absint( $value ) ) );
					break;

				case 'sitemap_max_urls':
					$stored[ $key ] = min( 10000, max( 100, absint( $value ) ) );
					break;

				case 'sitemap_post_types':
					// "All" is kept as a marker, so content types added later are included automatically.
					if ( '__all__' === $value ) {
						$stored[ $key ] = '__all__';
						break;
					}
					$viewable = array_values( array_filter( bearius_seo_supported_post_types(), 'is_post_type_viewable' ) );
					$chosen   = array_values( array_intersect( $viewable, array_map( 'sanitize_key', (array) $value ) ) );
					$stored[ $key ] = ( $viewable && count( $chosen ) === count( $viewable ) ) ? '__all__' : $chosen;
					break;

				case 'news_post_types':
					$stored[ $key ] = array_values( array_intersect( bearius_seo_supported_post_types(), array_map( 'sanitize_key', (array) $value ) ) );
					break;

				case 'sitemap_taxonomies':
					$stored[ $key ] = array_values( array_intersect( array_keys( bearius_seo_public_taxonomies() ), array_map( 'sanitize_key', (array) $value ) ) );
					break;

				case 'ai_reviewed':
					// Saving the AI screen once counts as a conscious choice for the checklist.
					$stored[ $key ] = 1;
					break;

				case 'rss_disable':
					$stored[ $key ] = in_array( $value, array( 'none', 'comments', 'extra', 'all' ), true ) ? $value : 'none';
					break;

				case 'rss_delay':
					$stored[ $key ] = min( 1440, absint( $value ) );
					break;

				case 'tags_mode':
					$stored[ $key ] = in_array( $value, array( 'on', 'hide', 'off' ), true ) ? $value : 'on';
					break;

				case 'comments_mode':
					$stored[ $key ] = in_array( $value, array( 'on', 'pings', 'closed', 'off' ), true ) ? $value : 'on';
					break;

				case 'archive_author':
				case 'archive_date':
					$stored[ $key ] = in_array( $value, array( 'index', 'noindex', 'off' ), true ) ? $value : 'index';
					break;

				case 'rss_exclude_cats':
				case 'news_exclude_cats':
					$stored[ $key ] = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
					break;

				case 'news_names':
					$names = array();
					foreach ( (array) $value as $lang => $name ) {
						$name = sanitize_text_field( (string) $name );
						if ( '' !== $name ) {
							$names[ sanitize_key( $lang ) ] = $name;
						}
					}
					$stored[ $key ] = $names;
					break;

				case 'robots_ai':
					$stored[ $key ] = in_array( $value, array( 'none', 'training', 'all', 'custom' ), true ) ? $value : 'none';
					break;

				case 'robots_ai_blocked':
					$agents = array_keys( Bearius_SEO_Robots::ai_bots() );
					if ( $full ) {
						$stored[ $key ] = array_values( array_intersect( $agents, (array) $value ) );
					} else {
						// The switches post the crawlers that stay allowed.
						$allowed        = isset( $input['robots_ai_allow'] ) ? array_map( 'sanitize_text_field', (array) $input['robots_ai_allow'] ) : array();
						$stored[ $key ] = array_values( array_diff( $agents, $allowed ) );
					}
					break;

				case 'robots_custom':
					$result         = Bearius_SEO_Robots::sanitize_custom( (string) $value );
					$stored[ $key ] = $result['clean'];
					if ( $result['removed'] && ! $full ) {
						add_settings_error(
							'bearius_seo',
							'robots_removed',
							__( 'A rule that would block the whole site for all crawlers or for Google was removed. If you really need it, put it in a physical robots.txt file.', 'bearius-seo-light' ),
							'warning'
						);
					}
					break;

				case 'indexnow_key':
					if ( $full ) {
						$stored[ $key ] = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $value );
					} elseif ( ! empty( $input['indexnow_regenerate'] ) || ( ! empty( $input['mod_indexnow'] ) && '' === (string) $stored['indexnow_key'] ) ) {
						$stored[ $key ] = Bearius_SEO_IndexNow::generate_key();
					}
					break;

				case 'notify_email_to':
					$stored[ $key ] = sanitize_email( (string) $value );
					break;

				default:
					if ( 0 === strpos( $key, 'verify_' ) ) {
						$stored[ $key ] = $this->verification_code( $value );
					} else {
						$stored[ $key ] = sanitize_text_field( (string) $value );
					}
			}
		}

		unset( $stored['_tab'] );

		return $stored;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$items = Bearius_SEO_UI::nav_items();

		if ( 'report' === $tab ) {
			Bearius_SEO_Admin_Tools::render_report();
			return;
		}

		if ( ! isset( $items[ $tab ] ) ) {
			$tab = 'overview';
		}

		$subtitles = array(
			'overview'      => __( 'Status of your site and the modules in use.', 'bearius-seo-light' ),
			'publisher'     => __( 'Who publishes the site, used in search results and when content is shared.', 'bearius-seo-light' ),
			'meta'          => __( 'Choose exactly which tags and structured data the plugin prints.', 'bearius-seo-light' ),
			'sitemaps'      => __( 'XML sitemap and Google News sitemap, in every language of the site.', 'bearius-seo-light' ),
			'robots'        => __( 'Rules for crawlers, built on top of the WordPress defaults.', 'bearius-seo-light' ),
			'site'          => __( 'RSS feeds, tags, comments and archives: what visitors and search engines get.', 'bearius-seo-light' ),
			'indexing'      => __( 'Notify search engines about changes and verify site ownership.', 'bearius-seo-light' ),
			'audit'         => __( 'Facts about your site that affect search, without guesswork.', 'bearius-seo-light' ),
			'ai'            => __( 'What AI crawlers may fetch and what they actually read.', 'bearius-seo-light' ),
			'import'        => __( 'Bring over titles and descriptions from another SEO plugin.', 'bearius-seo-light' ),
			'notifications' => __( 'How you hear about problems.', 'bearius-seo-light' ),
		);

		/**
		 * Filters the subtitles of the settings tabs.
		 *
		 * @param array $subtitles Tab key => subtitle.
		 */
		$subtitles = (array) apply_filters( 'bearius_seo_tab_subtitles', $subtitles );

		Bearius_SEO_UI::open( $tab, isset( $subtitles[ $tab ] ) ? $subtitles[ $tab ] : '' );

		switch ( $tab ) {
			case 'publisher':
				$this->tab_publisher();
				break;
			case 'meta':
				$this->tab_meta();
				break;
			case 'sitemaps':
				$this->tab_sitemaps();
				break;
			case 'robots':
				$this->tab_robots();
				break;
			case 'site':
				$this->tab_site();
				break;
			case 'indexing':
				$this->tab_indexing();
				break;
			case 'notifications':
				$this->tab_notifications();
				break;
			case 'audit':
				Bearius_SEO_Admin_Tools::render_audit();
				break;
			case 'ai':
				Bearius_SEO_Admin_Tools::render_ai();
				break;
			case 'import':
				Bearius_SEO_Admin_Tools::render_import();
				break;
			default:
				if ( has_action( 'bearius_seo_render_tab_' . $tab ) ) {
					/**
					 * Renders a tab added by an add-on.
					 */
					do_action( 'bearius_seo_render_tab_' . $tab );
				} else {
					$this->tab_overview();
				}
		}

		Bearius_SEO_UI::close();
	}

	/**
	 * Overview: score, top issues and modules.
	 *
	 * @return void
	 */
	private function tab_overview() {
		Bearius_SEO_Admin_Tools::render_checklist();
		Bearius_SEO_Admin_Tools::render_hero( 5 );

		$compat  = Bearius_SEO_Compat::instance();
		$modules = array(
			'meta'         => array( __( 'Meta tags', 'bearius-seo-light' ), __( 'Description, canonical, Open Graph and X Card tags, plus the SEO box in the editor.', 'bearius-seo-light' ), 'meta' ),
			'schema'       => array( __( 'Structured data', 'bearius-seo-light' ), __( 'One connected Schema.org graph: publisher, site, articles, breadcrumbs and archives.', 'bearius-seo-light' ), 'meta' ),
			'terms'        => array( __( 'Category and tag SEO', 'bearius-seo-light' ), __( 'SEO title, description and noindex on category, tag and other archive screens.', 'bearius-seo-light' ), '' ),
			'sitemap'      => array( __( 'XML sitemap', 'bearius-seo-light' ), __( 'Replaces the WordPress sitemap with one that lists every language and leaves out noindex content.', 'bearius-seo-light' ), 'sitemaps' ),
			'news'         => array( __( 'Google News sitemap', 'bearius-seo-light' ), __( 'Articles from the last two days, one file per language.', 'bearius-seo-light' ), 'sitemaps' ),
			'robots'       => array( __( 'Robots.txt', 'bearius-seo-light' ), __( 'Adds sitemap lines, an AI crawler policy and your own rules to the WordPress robots.txt.', 'bearius-seo-light' ), 'robots' ),
			'verification' => array( __( 'Site verification', 'bearius-seo-light' ), __( 'Verification tags for Google Search Console, Bing and other webmaster tools.', 'bearius-seo-light' ), 'indexing' ),
			'breadcrumbs'  => array( __( 'Breadcrumbs', 'bearius-seo-light' ), __( 'A block, a shortcode and a template function that show the same trail as the structured data.', 'bearius-seo-light' ), '' ),
			'audit'        => array( __( 'Audit', 'bearius-seo-light' ), __( 'Daily technical checks, page scans and the content audit.', 'bearius-seo-light' ), 'audit' ),
		);

		Bearius_SEO_UI::form_open( 'overview' );
		Bearius_SEO_UI::section( __( 'Modules', 'bearius-seo-light' ), __( 'Switch off what your theme or another plugin already does. A module that is off loads no code.', 'bearius-seo-light' ) );

		foreach ( $modules as $module => $info ) {
			$help = $info[1];
			if ( $info[2] && bearius_seo_module_enabled( $module ) ) {
				$help .= ' <a href="' . esc_url( bearius_seo_admin_url( $info[2] ) ) . '">' . esc_html__( 'Settings', 'bearius-seo-light' ) . '</a>';
			}
			Bearius_SEO_UI::switch_row( bearius_seo_module_key( $module ), $info[0], $help, $compat->blocked_by( $module ) );
		}

		echo '<input type="hidden" name="' . esc_attr( Bearius_SEO_UI::name( 'mod_widget' ) ) . '" value="' . esc_attr( (string) (int) bearius_seo_get_setting( 'mod_widget' ) ) . '" />';

		echo '<div class="bs-field"><div class="bs-field__label"><span class="bs-field__name">' . esc_html__( 'IndexNow', 'bearius-seo-light' ) . '</span><p class="bs-field__help">' . esc_html__( 'Tells Bing and other engines about changes right away. Needs your consent first.', 'bearius-seo-light' ) . '</p></div><div class="bs-field__control"><a class="button" href="' . esc_url( bearius_seo_admin_url( 'indexing' ) ) . '">' . ( Bearius_SEO_IndexNow::active() ? esc_html__( 'On, manage', 'bearius-seo-light' ) : esc_html__( 'Set up', 'bearius-seo-light' ) ) . '</a></div></div>';

		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Bearius link', 'bearius-seo-light' ), __( 'Optional. Off unless you switch it on.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'credit_link', __( 'Show a "Powered by Bearius" link in the footer', 'bearius-seo-light' ), __( 'A small link that helps others find the plugin. It is marked nofollow, so it passes no ranking signals. If several Bearius plugins have this switched on, the link still appears only once.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();

		$this->render_ui_language();
	}

	/**
	 * Prints the personal choice of the interface language.
	 *
	 * @return void
	 */
	private function render_ui_language() {
		$choice    = (string) get_user_meta( get_current_user_id(), 'bearius_seo_ui_locale', true );
		$profile   = get_user_locale();
		$languages = bearius_seo_bundled_languages();

		Bearius_SEO_UI::section( __( 'Language of these screens', 'bearius-seo-light' ), __( 'Follows the language of your user profile by default, not the language of the site, so you can work in your language on a site in any language. Only you see this choice.', 'bearius-seo-light' ), true );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bs-inline-language">';
		wp_nonce_field( 'bearius_seo_ui_language' );
		echo '<input type="hidden" name="action" value="bearius_seo_ui_language" /><label for="bs-ui-language" class="screen-reader-text">' . esc_html__( 'Language', 'bearius-seo-light' ) . '</label><select id="bs-ui-language" name="locale">';
		/* translators: %s: locale of the user profile. */
		echo '<option value="">' . esc_html( sprintf( __( 'Automatic, from your profile (%s)', 'bearius-seo-light' ), $profile ) ) . '</option>';
		foreach ( $languages as $locale => $name ) {
			echo '<option value="' . esc_attr( $locale ) . '" ' . selected( $choice, $locale, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select> <button type="submit" class="button">' . esc_html__( 'Save', 'bearius-seo-light' ) . '</button></form>';

		Bearius_SEO_UI::section_end();
	}

	/**
	 * Saves the personal interface language.
	 *
	 * @return void
	 */
	public function save_ui_language() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_ui_language' );

		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';

		if ( '' === $locale || ! isset( bearius_seo_bundled_languages()[ $locale ] ) ) {
			delete_user_meta( get_current_user_id(), 'bearius_seo_ui_locale' );
		} else {
			update_user_meta( get_current_user_id(), 'bearius_seo_ui_locale', $locale );
		}

		wp_safe_redirect( bearius_seo_admin_url( 'overview' ) );
		exit;
	}

	/**
	 * Feeds, tags, comments and archives.
	 *
	 * @return void
	 */
	private function tab_site() {
		$languages = Bearius_SEO_Languages::instance();

		Bearius_SEO_UI::form_open( 'site' );

		Bearius_SEO_UI::section( __( 'RSS feeds', 'bearius-seo-light' ), __( 'Feeds are read by feed readers such as Feedly, by aggregators and news apps, by tools that post to social media and by RSS newsletters. Search engines can use them to find new posts.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::segmented_row(
			'rss_disable',
			__( 'Available feeds', 'bearius-seo-light' ),
			__( 'Switched off feeds redirect to the page they belong to. Comment feeds are rarely used and safe to switch off. Category, tag and author feeds add many extra addresses for crawlers with little use. Switching off all feeds stops feed readers, aggregators and automatic posting to social media and newsletters, so check your tools first.', 'bearius-seo-light' ),
			array(
				'none'     => __( 'All feeds', 'bearius-seo-light' ),
				'comments' => __( 'Without comment feeds', 'bearius-seo-light' ),
				'extra'    => __( 'Main feed only', 'bearius-seo-light' ),
				'all'      => __( 'No feeds', 'bearius-seo-light' ),
			)
		);
		Bearius_SEO_UI::switch_row( 'rss_image', __( 'Add the featured image', 'bearius-seo-light' ), __( 'Adds the image as media:content and at the top of each item. Feedly, Flipboard, MSN and RSS newsletters use it to show a picture next to the title.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'rss_footer', __( 'Add a source line', 'bearius-seo-light' ), __( 'Adds "Title appeared first on Site" with links to every item. When someone republishes your feed automatically, the links back to the original stay in place.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::number_row( 'rss_delay', __( 'Delay new posts in the feed', 'bearius-seo-light' ), __( 'New posts appear in the feed only after this time, so search engines can see the original on your site before copies made from the feed appear elsewhere. 0 publishes right away.', 'bearius-seo-light' ), 0, 1440, __( 'minutes', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'rss_skip_noindex', __( 'Leave out noindex entries', 'bearius-seo-light' ), __( 'Entries hidden from search engines are not sent to feeds either.', 'bearius-seo-light' ) );

		$cats = array();
		foreach ( $languages->default_terms( 'category', 300 ) as $category ) {
			$cats[ $category->term_id ] = $languages->term_label( $category );
		}
		asort( $cats );

		if ( $cats ) {
			Bearius_SEO_UI::chips_row( 'rss_exclude_cats', __( 'Leave out categories', 'bearius-seo-light' ), __( 'Posts in these categories do not appear in the main feed. Translations of the chosen categories are left out too.', 'bearius-seo-light' ), $cats, (array) bearius_seo_get_setting( 'rss_exclude_cats', array() ) );
		}

		$excerpt = (bool) get_option( 'rss_use_excerpt' );
		echo '<div class="bs-field"><div class="bs-field__label"><span class="bs-field__name">' . esc_html__( 'Full text or excerpt', 'bearius-seo-light' ) . '</span><p class="bs-field__help">' . esc_html__( 'A WordPress setting. Full text is convenient for readers but easy to copy; an excerpt brings readers to your site. Changed in Settings, Reading.', 'bearius-seo-light' ) . '</p></div><div class="bs-field__control"><span class="bs-badge">' . ( $excerpt ? esc_html__( 'Excerpt', 'bearius-seo-light' ) : esc_html__( 'Full text', 'bearius-seo-light' ) ) . '</span> <a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Change', 'bearius-seo-light' ) . '</a></div></div>';
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Tags', 'bearius-seo-light' ), __( 'Tag archives often repeat what categories already show and add thin pages for search engines. Nothing is deleted: switch back and everything returns.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::segmented_row(
			'tags_mode',
			__( 'Tags', 'bearius-seo-light' ),
			__( 'Hidden from visitors: tag archives answer 410 Gone, so search engines drop them quickly, tag lists disappear from the theme, and tags leave the sitemap and feeds. Editors can still use tags, for example for internal sorting. Switched off: in addition the tag box and the Tags menu disappear for posts.', 'bearius-seo-light' ),
			array(
				'on'   => __( 'In use', 'bearius-seo-light' ),
				'hide' => __( 'Hidden from visitors', 'bearius-seo-light' ),
				'off'  => __( 'Switched off', 'bearius-seo-light' ),
			)
		);
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Comments', 'bearius-seo-light' ), __( 'Comments bring spam links, pingbacks, extra feeds and duplicate addresses when they are split into pages. Nothing is deleted.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::segmented_row(
			'comments_mode',
			__( 'Comments', 'bearius-seo-light' ),
			__( 'Pingbacks and trackbacks are automatic notices between sites and today are almost only spam; switching them off is safe for everyone. Closing comments keeps the existing ones visible. Switching off hides comments, forms, the Comments menu and comment feeds everywhere.', 'bearius-seo-light' ),
			array(
				'on'     => __( 'On', 'bearius-seo-light' ),
				'pings'  => __( 'Without pingbacks', 'bearius-seo-light' ),
				'closed' => __( 'Closed for new', 'bearius-seo-light' ),
				'off'    => __( 'Switched off', 'bearius-seo-light' ),
			)
		);
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Archives', 'bearius-seo-light' ), __( 'Lists of posts by author or by date rarely help readers or search engines.', 'bearius-seo-light' ) );
		$archive = array(
			'index'   => __( 'Indexed', 'bearius-seo-light' ),
			'noindex' => __( 'Not indexed', 'bearius-seo-light' ),
			'off'     => __( 'Switched off', 'bearius-seo-light' ),
		);
		Bearius_SEO_UI::segmented_row( 'archive_author', __( 'Author archives', 'bearius-seo-light' ), __( 'On a site with one author the author archive repeats the home page. Switched off, it redirects to the home page, which also stops login names from being found through ?author=1.', 'bearius-seo-light' ), $archive );
		Bearius_SEO_UI::segmented_row( 'archive_date', __( 'Date archives', 'bearius-seo-light' ), __( 'Lists by year, month and day. Not indexed keeps them for visitors; switched off redirects them to the home page.', 'bearius-seo-light' ), $archive );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();
	}

	/**
	 * Publisher settings.
	 *
	 * @return void
	 */
	private function tab_publisher() {
		Bearius_SEO_UI::form_open( 'publisher' );

		Bearius_SEO_UI::section( __( 'Publisher', 'bearius-seo-light' ), __( 'Shown as the publisher of every article in structured data.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'org_name', __( 'Publisher name', 'bearius-seo-light' ), __( 'Leave empty to use the site title.', 'bearius-seo-light' ), 'text', get_bloginfo( 'name' ) );
		Bearius_SEO_UI::image_row( 'org_logo', __( 'Logo', 'bearius-seo-light' ), __( 'Square or wide, at least 112 pixels. Leave empty to use the theme logo or the site icon.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::textarea_row( 'site_description', __( 'Home page description', 'bearius-seo-light' ), __( 'Used as the meta description of the home page. Leave empty to use the tagline.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Sharing', 'bearius-seo-light' ), __( 'How links to the site look on social networks and in messengers.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::image_row( 'default_image', __( 'Default social image', 'bearius-seo-light' ), __( 'Used when an entry has no featured image. Recommended size 1200x630 pixels.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'twitter_site', __( 'X account', 'bearius-seo-light' ), __( 'Without the @ sign.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Publishing', 'bearius-seo-light' ), __( 'Settings for sites that publish news.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'news_mode', __( 'News publisher mode', 'bearius-seo-light' ), __( 'Posts are marked as NewsArticle instead of Article. Use it only if you report on current events.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::number_row( 'modified_threshold', __( 'Modified date threshold', 'bearius-seo-light' ), __( 'Edits made within this time after publishing keep the original date, so fixing a typo does not look like an update.', 'bearius-seo-light' ), 1, 1440, __( 'minutes', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();
	}

	/**
	 * Meta tag and structured data switches.
	 *
	 * @return void
	 */
	private function tab_meta() {
		$blocked = Bearius_SEO_Compat::instance()->blocked_by( 'meta' );

		if ( ! bearius_seo_module_enabled( 'meta' ) ) {
			Bearius_SEO_UI::callout( __( 'The meta tags module is off, so the options below have no effect. Switch it on in the Overview.', 'bearius-seo-light' ), 'warning' );
		}

		Bearius_SEO_UI::form_open( 'meta' );

		Bearius_SEO_UI::section( __( 'Meta tags', 'bearius-seo-light' ), __( 'Turn off any tag your theme already prints, so nothing appears twice.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'title_rewrite', __( 'Custom titles', 'bearius-seo-light' ), __( 'Use the SEO title from the editor in the browser title.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'meta_description', __( 'Meta description', 'bearius-seo-light' ), '', $blocked );
		Bearius_SEO_UI::switch_row( 'meta_canonical', __( 'Canonical link', 'bearius-seo-light' ), __( 'Replaces the canonical tag WordPress prints, and adds it to archives and paginated pages.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'meta_og', __( 'Open Graph', 'bearius-seo-light' ), __( 'Title, description and image for Facebook, LinkedIn and messengers.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'meta_og_alternate', __( 'Other language versions', 'bearius-seo-light' ), __( 'Adds og:locale:alternate for each translation. Hreflang links come from your multilingual plugin.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'meta_twitter', __( 'X Card', 'bearius-seo-light' ), '', $blocked );
		Bearius_SEO_UI::section_end();

		$blocked = Bearius_SEO_Compat::instance()->blocked_by( 'schema' );

		Bearius_SEO_UI::section( __( 'Structured data', 'bearius-seo-light' ), __( 'Printed as one connected graph. Turn off a node your theme already outputs.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'schema_org', __( 'Organization', 'bearius-seo-light' ), __( 'The publisher with name and logo.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'schema_website', __( 'WebSite', 'bearius-seo-light' ), __( 'Site name and search action, on the home page.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'schema_article', __( 'Article', 'bearius-seo-light' ), __( 'Article or NewsArticle for posts, WebPage for other content.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::switch_row( 'schema_breadcrumbs', __( 'BreadcrumbList', 'bearius-seo-light' ), '', $blocked );
		Bearius_SEO_UI::switch_row( 'schema_archives', __( 'CollectionPage', 'bearius-seo-light' ), __( 'List of entries on category and tag archives.', 'bearius-seo-light' ), $blocked );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Loading', 'bearius-seo-light' ), __( 'Small changes that help pages look ready sooner.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'perf_eager_image', __( 'Load the featured image right away', 'bearius-seo-light' ), __( 'Removes lazy loading from the featured image of posts and pages and marks it as high priority. Turn it on if the audit reports a lazy loaded first image and your theme shows the featured image at the top.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();
	}

	/**
	 * Sitemap settings.
	 *
	 * @return void
	 */
	private function tab_sitemaps() {
		$languages = Bearius_SEO_Languages::instance();
		$compat    = Bearius_SEO_Compat::instance();

		Bearius_SEO_UI::form_open( 'sitemaps' );

		Bearius_SEO_UI::section( __( 'XML sitemap', 'bearius-seo-light' ), __( 'Replaces the sitemap built into WordPress. The old address and the one used by Yoast and Rank Math redirect here.', 'bearius-seo-light' ) );

		if ( ! bearius_seo_module_enabled( 'sitemap' ) ) {
			Bearius_SEO_UI::callout( __( 'The XML sitemap module is off, so WordPress serves its own sitemap. Noindex content is still left out of it.', 'bearius-seo-light' ), 'info' );
		} elseif ( $compat->blocked_by( 'sitemap' ) ) {
			/* translators: %s: plugin name. */
			Bearius_SEO_UI::callout( sprintf( __( 'Paused while %s is active.', 'bearius-seo-light' ), $compat->blocked_by( 'sitemap' ) ), 'warning' );
		} elseif ( ! bearius_seo_site_is_public() ) {
			Bearius_SEO_UI::callout( __( 'The site asks search engines not to index it, so no sitemap is served.', 'bearius-seo-light' ), 'warning' );
		} else {
			$text = sprintf(
				/* translators: %s: sitemap URL. */
				__( 'Your sitemap: %s', 'bearius-seo-light' ),
				'<a href="' . esc_url( Bearius_SEO_Sitemaps::index_url() ) . '" target="_blank" rel="noopener">' . esc_html( Bearius_SEO_Sitemaps::index_url() ) . '</a>'
			);
			if ( $languages->is_multilingual() ) {
				/* translators: %s: multilingual plugin name. */
				$text .= ' ' . sprintf( esc_html__( 'Languages are read from %s, with separate files per language.', 'bearius-seo-light' ), esc_html( $languages->provider_label() ) );
			}
			Bearius_SEO_UI::callout( $text, 'ok' );
		}

		$types = array();
		foreach ( bearius_seo_supported_post_types() as $post_type ) {
			$object              = get_post_type_object( $post_type );
			$types[ $post_type ] = $object ? $object->labels->name : $post_type;
		}

		$taxonomies = array();
		foreach ( bearius_seo_public_taxonomies() as $taxonomy => $object ) {
			$taxonomies[ $taxonomy ] = $object->labels->name;
		}

		Bearius_SEO_UI::chips_row( 'sitemap_post_types', __( 'Content types', 'bearius-seo-light' ), '', $types, bearius_seo_sitemap_post_types() );
		Bearius_SEO_UI::chips_row( 'sitemap_taxonomies', __( 'Archives', 'bearius-seo-light' ), __( 'Empty archives and archives marked noindex are left out automatically.', 'bearius-seo-light' ), $taxonomies, bearius_seo_sitemap_taxonomies() );
		Bearius_SEO_UI::switch_row( 'sitemap_home', __( 'Home page entry', 'bearius-seo-light' ), __( 'A small file with the home page of every language.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::number_row( 'sitemap_max_urls', __( 'Addresses per file', 'bearius-seo-light' ), __( 'Smaller files are generated faster on large sites.', 'bearius-seo-light' ), 100, 10000 );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Google News sitemap', 'bearius-seo-light' ), __( 'Lists articles from the last two days, at most 1000. On multilingual sites each language gets its own file under one index.', 'bearius-seo-light' ) );

		if ( ! bearius_seo_module_enabled( 'news' ) ) {
			Bearius_SEO_UI::callout( __( 'The Google News sitemap is off. Switch it on in the Overview if you publish news.', 'bearius-seo-light' ), 'info' );
		} elseif ( $compat->blocked_by( 'news' ) ) {
			/* translators: %s: plugin name. */
			Bearius_SEO_UI::callout( sprintf( __( 'Paused while %s is active.', 'bearius-seo-light' ), $compat->blocked_by( 'news' ) ), 'warning' );
		} else {
			Bearius_SEO_UI::callout(
				sprintf(
					/* translators: %s: news sitemap URL. */
					__( 'Your news sitemap: %s. Submit it in Google Search Console.', 'bearius-seo-light' ),
					'<a href="' . esc_url( Bearius_SEO_Sitemaps::news_url() ) . '" target="_blank" rel="noopener">' . esc_html( Bearius_SEO_Sitemaps::news_url() ) . '</a>'
				),
				'ok'
			);
		}

		Bearius_SEO_UI::chips_row( 'news_post_types', __( 'Content types', 'bearius-seo-light' ), '', $types, Bearius_SEO_Sitemaps::news_post_types() );

		$cats = array();
		foreach ( $languages->default_terms( 'category', 300 ) as $category ) {
			$cats[ $category->term_id ] = $languages->term_label( $category );
		}
		asort( $cats );

		if ( $cats ) {
			$help = __( 'Posts in these categories never go to Google News, for example sponsored content or announcements.', 'bearius-seo-light' );
			if ( $languages->is_multilingual() && ! $languages->shares_posts() ) {
				$help .= ' ' . __( 'Only categories of the default language are listed; their translations are left out automatically.', 'bearius-seo-light' );
			}
			Bearius_SEO_UI::chips_row( 'news_exclude_cats', __( 'Leave out categories', 'bearius-seo-light' ), $help, $cats, (array) bearius_seo_get_setting( 'news_exclude_cats', array() ) );
		}

		$names = (array) bearius_seo_get_setting( 'news_names', array() );
		echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><span class="bs-field__name">' . esc_html__( 'Publication name', 'bearius-seo-light' ) . '</span><p class="bs-field__help">' . esc_html__( 'Must match the name in Google Publisher Center. Leave empty to use the publisher name.', 'bearius-seo-light' ) . '</p></div><div class="bs-field__control bs-names">';

		$lang_list = $languages->is_multilingual() ? $languages->languages() : array( '_default' => array( 'name' => __( 'All content', 'bearius-seo-light' ), 'locale' => get_locale() ) );

		foreach ( $lang_list as $key => $lang ) {
			$id = 'bs-news-name-' . $key;
			echo '<label class="bs-names__row" for="' . esc_attr( $id ) . '"><span class="bs-names__lang">' . esc_html( $lang['name'] ) . ' <code>' . esc_html( bearius_seo_news_language( $lang['locale'] ) ) . '</code></span><input class="bs-input" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( Bearius_SEO_UI::name( 'news_names', '[' . $key . ']' ) ) . '" value="' . esc_attr( isset( $names[ $key ] ) ? $names[ $key ] : '' ) . '" placeholder="' . esc_attr( bearius_seo_org_name() ) . '" /></label>';
		}

		echo '</div></div>';
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();

		Bearius_SEO_Admin_Tools::render_sitemap_preview();
	}

	/**
	 * Robots.txt settings.
	 *
	 * @return void
	 */
	private function tab_robots() {
		if ( ! bearius_seo_module_enabled( 'robots' ) ) {
			Bearius_SEO_UI::callout( __( 'The robots.txt module is off. WordPress serves its default rules; the sitemap lines are still added.', 'bearius-seo-light' ), 'info' );
		} elseif ( Bearius_SEO_Compat::instance()->blocked_by( 'robots' ) ) {
			/* translators: %s: plugin name. */
			Bearius_SEO_UI::callout( sprintf( __( 'Paused while %s is active.', 'bearius-seo-light' ), Bearius_SEO_Compat::instance()->blocked_by( 'robots' ) ), 'warning' );
		}

		if ( Bearius_SEO_Robots::in_subdirectory() ) {
			Bearius_SEO_UI::callout( __( 'WordPress runs in a subdirectory. Crawlers only read robots.txt at the root of the domain, so these rules are not seen.', 'bearius-seo-light' ), 'warning' );
		} elseif ( Bearius_SEO_Robots::physical_file_exists() ) {
			Bearius_SEO_UI::callout( __( 'A robots.txt file exists on the server, and crawlers receive it instead of the rules below. Delete the file to use these settings.', 'bearius-seo-light' ), 'warning' );
		}

		if ( ! bearius_seo_site_is_public() ) {
			Bearius_SEO_UI::callout( __( 'The site asks search engines not to index it, so robots.txt blocks everything regardless of the rules below.', 'bearius-seo-light' ), 'critical' );
		}

		$presets = Bearius_SEO_Robots::presets();
		$preset  = (string) bearius_seo_get_setting( 'robots_ai', 'none' );
		$preset  = isset( $presets[ $preset ] ) ? $preset : 'none';

		Bearius_SEO_UI::section( __( 'AI crawlers', 'bearius-seo-light' ), __( 'Set in the AI visibility screen, where each choice is explained.', 'bearius-seo-light' ), true );
		echo '<div class="bs-summary"><div><p class="bs-summary__label">' . esc_html__( 'Current policy', 'bearius-seo-light' ) . '</p><p class="bs-summary__value">' . esc_html( $presets[ $preset ][0] ) . '</p><p class="bs-muted">' . esc_html( $presets[ $preset ][1] ) . '</p></div><a class="button" href="' . esc_url( bearius_seo_admin_url( 'ai' ) ) . '">' . esc_html__( 'Change', 'bearius-seo-light' ) . '</a></div>';
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_open( 'robots' );

		Bearius_SEO_UI::section( __( 'Your rules', 'bearius-seo-light' ), __( 'Added below the automatic rules. A rule that would block the whole site for everyone or for Google is removed on save.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::textarea_row( 'robots_custom', __( 'Extra rules', 'bearius-seo-light' ), __( 'One directive per line, for example "Disallow: /cart/".', 'bearius-seo-light' ), 6, 'bs-code' );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();

		Bearius_SEO_UI::section( __( 'Preview', 'bearius-seo-light' ), __( 'Saved settings, exactly as WordPress serves them.', 'bearius-seo-light' ) . ' <a href="' . esc_url( trailingslashit( (string) get_option( 'home' ) ) . 'robots.txt' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open robots.txt', 'bearius-seo-light' ) . '</a>', true );
		echo '<pre class="bs-pre">' . esc_html( Bearius_SEO_Robots::preview() ) . '</pre>';
		Bearius_SEO_UI::section_end();
	}

	/**
	 * IndexNow and verification settings.
	 *
	 * @return void
	 */
	private function tab_indexing() {
		Bearius_SEO_UI::form_open( 'indexing' );

		Bearius_SEO_UI::section(
			__( 'IndexNow', 'bearius-seo-light' ),
			__( 'Instant notifications for search engines. When you publish or update an entry, its address goes straight to engines that support IndexNow: Bing (which also feeds Copilot and DuckDuckGo), Yandex, Seznam, Naver and Yep. One submission reaches all of them. Only the address is sent, nothing else. Noindex entries and drafts are skipped. Google does not use IndexNow; it finds changes through your sitemaps.', 'bearius-seo-light' )
		);

		Bearius_SEO_UI::switch_row( 'mod_indexnow', __( 'Send changes with IndexNow', 'bearius-seo-light' ), __( 'Switching this on is your consent for the site to contact api.indexnow.org.', 'bearius-seo-light' ) );
		echo '<input type="hidden" name="' . esc_attr( Bearius_SEO_UI::name( 'indexnow_key' ) ) . '" value="" />';

		if ( '' !== Bearius_SEO_IndexNow::key() ) {
			echo '<div class="bs-field"><div class="bs-field__label"><span class="bs-field__name">' . esc_html__( 'Key file', 'bearius-seo-light' ) . '</span><p class="bs-field__help">' . esc_html__( 'Proves to search engines that the submissions come from this site.', 'bearius-seo-light' ) . '</p></div><div class="bs-field__control"><div class="bs-snippet"><code>' . esc_html( Bearius_SEO_IndexNow::key_url() ) . '</code><a class="button button-small" href="' . esc_url( Bearius_SEO_IndexNow::key_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a></div><label class="bs-inline-check"><input type="checkbox" name="' . esc_attr( Bearius_SEO_UI::name( 'indexnow_regenerate' ) ) . '" value="1" /> ' . esc_html__( 'Generate a new key on save', 'bearius-seo-light' ) . '</label></div></div>';
		}

		$log = (array) get_option( Bearius_SEO_IndexNow::LOG, array() );
		if ( $log ) {
			echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><span class="bs-field__name">' . esc_html__( 'Recent submissions', 'bearius-seo-light' ) . '</span></div><div class="bs-field__control"><div class="bs-table-wrap"><table class="bs-table"><thead><tr><th>' . esc_html__( 'When', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Addresses', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Result', 'bearius-seo-light' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $log, 0, 10 ) as $entry ) {
				$ok = in_array( (int) $entry['code'], array( 200, 202 ), true );
				/* translators: %s: time difference. */
				echo '<tr><td>' . esc_html( sprintf( __( '%s ago', 'bearius-seo-light' ), human_time_diff( (int) $entry['time'] ) ) ) . '</td><td title="' . esc_attr( $entry['first'] ) . '">' . (int) $entry['count'] . '</td><td><span class="bs-badge bs-badge--' . ( $ok ? 'ok' : 'warning' ) . '">' . esc_html( Bearius_SEO_IndexNow::describe( (int) $entry['code'] ) ) . '</span></td></tr>';
			}
			echo '</tbody></table></div></div></div>';
		}

		Bearius_SEO_UI::section_end();

		$blocked = Bearius_SEO_Compat::instance()->blocked_by( 'verification' );

		Bearius_SEO_UI::section( __( 'Site verification', 'bearius-seo-light' ), __( 'Paste the code or the whole meta tag from each tool. The tag is printed on the home page only.', 'bearius-seo-light' ) );

		if ( $blocked ) {
			/* translators: %s: plugin name. */
			Bearius_SEO_UI::callout( sprintf( __( 'Paused while %s is active.', 'bearius-seo-light' ), $blocked ), 'warning' );
		} elseif ( ! bearius_seo_module_enabled( 'verification' ) ) {
			Bearius_SEO_UI::callout( __( 'The verification module is off. Switch it on in the Overview.', 'bearius-seo-light' ), 'info' );
		}

		Bearius_SEO_UI::text_row( 'verify_google', __( 'Google Search Console', 'bearius-seo-light' ), __( 'Needed only for a URL prefix property. Domain properties are verified through DNS.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'verify_bing', __( 'Bing Webmaster Tools', 'bearius-seo-light' ), __( 'Bing can also import a site verified in Search Console.', 'bearius-seo-light' ) );

		echo '<details class="bs-more"' . ( bearius_seo_get_setting( 'verify_yandex' ) . bearius_seo_get_setting( 'verify_seznam' ) . bearius_seo_get_setting( 'verify_naver' ) . bearius_seo_get_setting( 'verify_baidu' ) . bearius_seo_get_setting( 'verify_pinterest' ) ? ' open' : '' ) . '><summary>' . esc_html__( 'Other tools', 'bearius-seo-light' ) . '</summary>';
		Bearius_SEO_UI::text_row( 'verify_seznam', __( 'Seznam', 'bearius-seo-light' ), __( 'The main search engine in the Czech Republic.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'verify_yandex', __( 'Yandex', 'bearius-seo-light' ), __( 'For Russian speaking markets.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'verify_naver', __( 'Naver', 'bearius-seo-light' ), __( 'The main search engine in South Korea.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'verify_baidu', __( 'Baidu', 'bearius-seo-light' ), __( 'The main search engine in China.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'verify_pinterest', __( 'Pinterest', 'bearius-seo-light' ), __( 'Claims the domain for Rich Pins and statistics. Useful for recipes, DIY and shops.', 'bearius-seo-light' ) );
		echo '</details>';

		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();
	}

	/**
	 * Notification settings.
	 *
	 * @return void
	 */
	private function tab_notifications() {
		$mail = isset( $_GET['bearius_mail'] ) ? sanitize_key( wp_unslash( $_GET['bearius_mail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'sent' === $mail ) {
			Bearius_SEO_UI::callout( __( 'WordPress accepted the test message. Check the inbox and the spam folder; acceptance does not guarantee delivery.', 'bearius-seo-light' ), 'ok' );
		} elseif ( 'failed' === $mail ) {
			Bearius_SEO_UI::callout( __( 'WordPress could not send the test message. Your host probably blocks outgoing mail; an SMTP plugin usually solves it.', 'bearius-seo-light' ), 'critical' );
		}

		Bearius_SEO_UI::form_open( 'notifications' );

		Bearius_SEO_UI::section( __( 'In the dashboard', 'bearius-seo-light' ), __( 'Problems are checked once a day, and right away when a setting that affects indexing changes.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'mod_widget', __( 'Dashboard widget', 'bearius-seo-light' ), __( 'Score, status and the three most important problems on the Dashboard.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::switch_row( 'notify_notices', __( 'Notices for critical problems', 'bearius-seo-light' ), __( 'A notice on every admin screen until the problem is fixed or you hide it. Only critical problems, only for administrators.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'By e-mail', 'bearius-seo-light' ), __( 'One message when a new critical problem appears, never a digest of small things.', 'bearius-seo-light' ) );
		Bearius_SEO_UI::callout( __( 'E-mail uses the mail function of your server. Many hosts limit or block it, and messages may land in spam. Send a test e-mail before relying on it; an SMTP plugin makes delivery reliable. The plugin cannot guarantee delivery, so critical problems are always shown in the dashboard too.', 'bearius-seo-light' ), 'warning' );
		Bearius_SEO_UI::switch_row( 'notify_email', __( 'E-mail critical problems', 'bearius-seo-light' ) );
		Bearius_SEO_UI::text_row( 'notify_email_to', __( 'Recipient', 'bearius-seo-light' ), __( 'Leave empty to use the administration e-mail address.', 'bearius-seo-light' ), 'email', get_option( 'admin_email' ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::form_close();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bs-inline-form">';
		wp_nonce_field( 'bearius_seo_test_email' );
		echo '<input type="hidden" name="action" value="bearius_seo_test_email" /><button type="submit" class="button">' . esc_html__( 'Send a test e-mail', 'bearius-seo-light' ) . '</button></form>';
	}
}
