<?php
/**
 * One click fixes for audit findings.
 *
 * Only findings the plugin controls itself get a fix: its own settings and
 * a few WordPress options. Nothing runs on its own; every fix is a button
 * that says what it changes. Theme templates and files on the server are
 * never touched.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps findings to fixes and applies them.
 */
class Bearius_SEO_Fixes {

	/**
	 * Hooks the form handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_bearius_seo_fix', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Returns the fixes with their label, explanation and confirmation text.
	 *
	 * @return array[]
	 */
	public static function catalogue() {
		return array(
			'enable_canonical'        => array(
				__( 'Turn on the canonical link', 'bearius-seo-light' ),
				__( 'Switches on the meta tags module and its canonical link.', 'bearius-seo-light' ),
				'',
			),
			'enable_description'      => array(
				__( 'Turn on the meta description', 'bearius-seo-light' ),
				__( 'Switches on the meta tags module and its meta description.', 'bearius-seo-light' ),
				'',
			),
			'disable_own_canonical'   => array(
				__( 'Stop printing our canonical link', 'bearius-seo-light' ),
				__( 'Keeps the canonical tag of your theme or other plugin and switches off ours.', 'bearius-seo-light' ),
				'',
			),
			'disable_own_description' => array(
				__( 'Stop printing our meta description', 'bearius-seo-light' ),
				__( 'Keeps the description of your theme or other plugin and switches off ours.', 'bearius-seo-light' ),
				'',
			),
			'disable_own_og'          => array(
				__( 'Stop printing our Open Graph tags', 'bearius-seo-light' ),
				__( 'Keeps the Open Graph tags of your theme or other plugin and switches off ours.', 'bearius-seo-light' ),
				'',
			),
			'disable_own_schema'      => array(
				__( 'Switch off our duplicated structured data', 'bearius-seo-light' ),
				__( 'Switches off only the structured data nodes that appear twice. If the theme repeats them by itself, this will not help.', 'bearius-seo-light' ),
				'',
			),
			'eager_first_image'       => array(
				__( 'Load the featured image right away', 'bearius-seo-light' ),
				__( 'Removes lazy loading from the featured image of posts and pages and marks it as high priority. Other images are not changed.', 'bearius-seo-light' ),
				'',
			),
			'make_public'             => array(
				__( 'Allow search engines', 'bearius-seo-light' ),
				__( 'Switches off "Discourage search engines from indexing this site" in Settings, Reading.', 'bearius-seo-light' ),
				__( 'Search engines will start indexing the whole site. Continue?', 'bearius-seo-light' ),
			),
			'enable_news'             => array(
				__( 'Turn on the Google News sitemap', 'bearius-seo-light' ),
				__( 'Switches on the Google News sitemap module for posts.', 'bearius-seo-light' ),
				'',
			),
			'news_types'              => array(
				__( 'Use posts for Google News', 'bearius-seo-light' ),
				__( 'Selects posts as the content type of the Google News sitemap.', 'bearius-seo-light' ),
				'',
			),
			'purge_cache'             => array(
				__( 'Clear the page cache', 'bearius-seo-light' ),
				__( 'Clears the whole page cache, so visitors get pages built with the current settings.', 'bearius-seo-light' ),
				__( 'The whole page cache will be cleared. On a busy site the server works harder for a few minutes. Continue?', 'bearius-seo-light' ),
			),
		);
	}

	/**
	 * Returns the issue id without the page prefix.
	 *
	 * @param string $id Issue id.
	 * @return string
	 */
	private static function base_id( $id ) {
		return (string) preg_replace( '/^(home|post)_/', '', (string) $id );
	}

	/**
	 * Returns the fix available for an issue right now, or null.
	 *
	 * Availability is decided when the finding is shown, from the current
	 * settings, so a fix that was already applied is not offered again.
	 *
	 * @param array $issue Issue.
	 * @return array|null Fix id, label, explanation and confirmation.
	 */
	public static function for_issue( $issue ) {
		$compat = Bearius_SEO_Compat::instance();
		$meta   = bearius_seo_module_active( 'meta' );
		$fix    = '';

		switch ( self::base_id( $issue['id'] ) ) {
			case 'canonical_none':
				if ( '' === $compat->blocked_by( 'meta' ) && ( ! $meta || ! bearius_seo_get_setting( 'meta_canonical' ) ) ) {
					$fix = 'enable_canonical';
				}
				break;

			case 'desc_none':
				if ( '' === $compat->blocked_by( 'meta' ) && ( ! $meta || ! bearius_seo_get_setting( 'meta_description' ) ) ) {
					$fix = 'enable_description';
				}
				break;

			case 'canonical_dup':
				if ( $meta && bearius_seo_get_setting( 'meta_canonical' ) ) {
					$fix = 'disable_own_canonical';
				}
				break;

			case 'desc_dup':
				if ( $meta && bearius_seo_get_setting( 'meta_description' ) ) {
					$fix = 'disable_own_description';
				}
				break;

			case 'og_dup':
				if ( $meta && bearius_seo_get_setting( 'meta_og' ) ) {
					$fix = 'disable_own_og';
				}
				break;

			case 'schema_dup':
				if ( bearius_seo_module_active( 'schema' ) && self::schema_keys() ) {
					$fix = 'disable_own_schema';
				}
				break;

			case 'perf_lazy_first':
				if ( ! bearius_seo_get_setting( 'perf_eager_image' ) ) {
					$fix = 'eager_first_image';
				}
				break;

			case 'not_public':
				$fix = 'make_public';
				break;

			case 'news_off':
				if ( '' === $compat->blocked_by( 'news' ) ) {
					$fix = 'enable_news';
				}
				break;

			case 'news_no_types':
				if ( in_array( 'post', bearius_seo_supported_post_types(), true ) ) {
					$fix = 'news_types';
				}
				break;

			case 'foreign':
				if ( bearius_seo_page_cache_purgers() ) {
					$fix = 'purge_cache';
				}
				break;
		}

		if ( '' === $fix ) {
			return null;
		}

		$catalogue = self::catalogue();

		return array(
			'id'      => $fix,
			'label'   => $catalogue[ $fix ][0],
			'detail'  => $catalogue[ $fix ][1],
			'confirm' => $catalogue[ $fix ][2],
			'url'     => wp_nonce_url( add_query_arg( array( 'action' => 'bearius_seo_fix', 'fix' => $fix ), admin_url( 'admin-post.php' ) ), 'bearius_seo_fix_' . $fix ),
		);
	}

	/**
	 * Returns the settings of our structured data nodes that the last scan found twice.
	 *
	 * @return string[]
	 */
	private static function schema_keys() {
		$map  = array(
			'Organization'   => 'schema_org',
			'WebSite'        => 'schema_website',
			'BreadcrumbList' => 'schema_breadcrumbs',
			'Article'        => 'schema_article',
			'NewsArticle'    => 'schema_article',
		);
		$keys = array();

		foreach ( (array) Bearius_SEO_Health::instance()->stored()['scans'] as $scan ) {
			$types = isset( $scan['devices']['desktop']['types'] ) ? (array) $scan['devices']['desktop']['types'] : array();

			foreach ( array_count_values( $types ) as $type => $count ) {
				if ( $count > 1 && isset( $map[ $type ] ) && bearius_seo_get_setting( $map[ $type ] ) ) {
					$keys[] = $map[ $type ];
				}
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Returns the issue ids a fix resolves.
	 *
	 * @param string $fix Fix id.
	 * @return string[]
	 */
	private static function resolves( $fix ) {
		$map = array(
			'enable_canonical'        => array( 'canonical_none' ),
			'enable_description'      => array( 'desc_none' ),
			'disable_own_canonical'   => array( 'canonical_dup' ),
			'disable_own_description' => array( 'desc_dup' ),
			'disable_own_og'          => array( 'og_dup' ),
			'disable_own_schema'      => array( 'schema_dup' ),
			'eager_first_image'       => array( 'perf_lazy_first' ),
			'make_public'             => array( 'not_public' ),
			'enable_news'             => array( 'news_off' ),
			'news_types'              => array( 'news_no_types' ),
			'purge_cache'             => array( 'foreign' ),
		);

		return isset( $map[ $fix ] ) ? $map[ $fix ] : array();
	}

	/**
	 * Applies a fix.
	 *
	 * @param string $fix Fix id.
	 * @return bool
	 */
	public static function apply( $fix ) {
		switch ( $fix ) {
			case 'enable_canonical':
				bearius_seo_update_settings(
					array(
						'output_meta'    => 1,
						'meta_canonical' => 1,
					)
				);
				return true;

			case 'enable_description':
				bearius_seo_update_settings(
					array(
						'output_meta'      => 1,
						'meta_description' => 1,
					)
				);
				return true;

			case 'disable_own_canonical':
				bearius_seo_update_settings( array( 'meta_canonical' => 0 ) );
				return true;

			case 'disable_own_description':
				bearius_seo_update_settings( array( 'meta_description' => 0 ) );
				return true;

			case 'disable_own_og':
				bearius_seo_update_settings( array( 'meta_og' => 0 ) );
				return true;

			case 'disable_own_schema':
				$keys = self::schema_keys();
				if ( ! $keys ) {
					return false;
				}
				bearius_seo_update_settings( array_fill_keys( $keys, 0 ) );
				return true;

			case 'eager_first_image':
				bearius_seo_update_settings( array( 'perf_eager_image' => 1 ) );
				return true;

			case 'make_public':
				update_option( 'blog_public', '1' );
				return true;

			case 'enable_news':
				bearius_seo_update_settings( array( 'mod_news' => 1 ) );
				return true;

			case 'news_types':
				bearius_seo_update_settings( array( 'news_post_types' => array( 'post' ) ) );
				return true;

			case 'purge_cache':
				$purgers = bearius_seo_page_cache_purgers();
				if ( ! $purgers ) {
					return false;
				}
				foreach ( $purgers as $callback ) {
					call_user_func( $callback );
				}
				return true;
		}

		return false;
	}

	/**
	 * Removes findings a fix resolved from the stored results, until the next check confirms it.
	 *
	 * @param string $fix Fix id.
	 * @return void
	 */
	private static function forget( $fix ) {
		$ids    = self::resolves( $fix );
		$stored = get_option( Bearius_SEO_Health::OPTION, array() );

		if ( ! $ids || empty( $stored['issues'] ) ) {
			return;
		}

		$stored['issues'] = array_values(
			array_filter(
				(array) $stored['issues'],
				function ( $issue ) use ( $ids ) {
					return ! in_array( self::base_id( $issue['id'] ), $ids, true );
				}
			)
		);

		update_option( Bearius_SEO_Health::OPTION, $stored, false );
	}

	/**
	 * Handles the fix button.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		$fix = isset( $_GET['fix'] ) ? sanitize_key( wp_unslash( $_GET['fix'] ) ) : '';

		if ( ! isset( self::catalogue()[ $fix ] ) ) {
			wp_die( esc_html__( 'Unknown fix.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_fix_' . $fix );

		$done = self::apply( $fix );

		if ( $done ) {
			self::forget( $fix );
		}

		$referer = wp_get_referer();
		$target  = $referer ? $referer : bearius_seo_admin_url( 'audit' );

		wp_safe_redirect( add_query_arg( 'bearius_fixed', $done ? $fix : 'failed', remove_query_arg( array( 'bearius_fixed', 'bearius_checked' ), $target ) ) );
		exit;
	}

	/**
	 * Shows the result of a fix on the plugin screens and the dashboard.
	 *
	 * @return void
	 */
	public static function render_result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$fix = isset( $_GET['bearius_fixed'] ) ? sanitize_key( wp_unslash( $_GET['bearius_fixed'] ) ) : '';

		if ( '' === $fix ) {
			return;
		}

		if ( 'failed' === $fix || ! isset( self::catalogue()[ $fix ] ) ) {
			Bearius_SEO_UI::callout( __( 'The fix could not be applied. Nothing was changed.', 'bearius-seo-light' ), 'warning' );
			return;
		}

		$text = sprintf(
			/* translators: %s: name of the fix. */
			__( 'Done: %s.', 'bearius-seo-light' ),
			self::catalogue()[ $fix ][0]
		);

		Bearius_SEO_UI::callout( $text . ' ' . self::follow_up( $fix ), 'ok' );
	}

	/**
	 * Returns what to do after a fix.
	 *
	 * @param string $fix Fix id.
	 * @return string
	 */
	private static function follow_up( $fix ) {
		if ( in_array( $fix, array( 'make_public', 'enable_news', 'news_types', 'purge_cache' ), true ) ) {
			return __( 'Run the checks to confirm.', 'bearius-seo-light' );
		}

		return __( 'If your site uses a page cache, clear it, then run the checks to confirm.', 'bearius-seo-light' );
	}

	/**
	 * Shows the result as a standard notice outside the plugin screen.
	 *
	 * @return void
	 */
	public function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$fix = isset( $_GET['bearius_fixed'] ) ? sanitize_key( wp_unslash( $_GET['bearius_fixed'] ) ) : '';

		if ( '' === $fix || ( $screen && bearius_seo_screen_id() === $screen->id ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'failed' === $fix || ! isset( self::catalogue()[ $fix ] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Bearius SEO: the fix could not be applied. Nothing was changed.', 'bearius-seo-light' ) . '</p></div>';
			return;
		}

		/* translators: %s: name of the fix. */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Bearius SEO: done, %s.', 'bearius-seo-light' ), self::catalogue()[ $fix ][0] ) . ' ' . self::follow_up( $fix ) ) . '</p></div>';
	}
}
