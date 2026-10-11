<?php
/**
 * Technical checks, score and notifications.
 *
 * Cheap checks run live on every request that needs them. Checks that need
 * HTTP requests (sitemaps, robots.txt, rendered pages) run once a day, on
 * demand and right after critical settings change, and their results are
 * stored in one option.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects issues and reports them.
 */
class Bearius_SEO_Health {

	/**
	 * Option with the stored results of the HTTP checks.
	 */
	const OPTION = 'bearius_seo_health';

	/**
	 * Singleton instance.
	 *
	 * @var Bearius_SEO_Health|null
	 */
	private static $instance = null;

	/**
	 * Whether an e-mail from this plugin is being sent right now.
	 *
	 * @var bool
	 */
	private $sending = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Bearius_SEO_Health
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks cron, notices and form handlers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'bearius_seo_daily_check', array( $this, 'run_scheduled' ) );
		add_action( 'update_option_blog_public', array( $this, 'notify' ) );
		add_action( 'update_option_permalink_structure', array( $this, 'notify' ) );
		add_action( 'activated_plugin', array( $this, 'notify' ) );
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_post_bearius_seo_run_checks', array( $this, 'handle_run' ) );
		add_action( 'admin_post_bearius_seo_dismiss', array( $this, 'handle_dismiss' ) );
		add_action( 'admin_post_bearius_seo_test_email', array( $this, 'handle_test_email' ) );

		if ( bearius_seo_module_enabled( 'audit' ) ) {
			add_filter( 'site_status_tests', array( $this, 'site_health_tests' ) );
		}

		if ( bearius_seo_get_setting( 'notify_notices' ) ) {
			add_action( 'admin_notices', array( $this, 'render_notices' ) );
		}
	}

	/**
	 * Builds an issue array.
	 *
	 * @param string $id       Issue id.
	 * @param string $severity Severity.
	 * @param string $group    Group.
	 * @param string $title    Title.
	 * @param string $detail   Explanation.
	 * @param string $link     Optional link to fix it.
	 * @return array
	 */
	private function issue( $id, $severity, $group, $title, $detail, $link = '' ) {
		return array(
			'id'       => $id,
			'severity' => $severity,
			'group'    => $group,
			'title'    => $title,
			'detail'   => $detail,
			'link'     => $link,
		);
	}

	/**
	 * Checks that need no HTTP request.
	 *
	 * @return array[]
	 */
	public function quick_issues() {
		$issues = array();

		if ( ! bearius_seo_site_is_public() ) {
			$issues[] = $this->issue(
				'not_public',
				'critical',
				'setup',
				__( 'Search engines are asked not to index this site', 'bearius-seo-light' ),
				__( 'The "Discourage search engines from indexing this site" option is on. Nothing from this site will appear in search results, and the sitemaps are switched off.', 'bearius-seo-light' ),
				admin_url( 'options-reading.php' )
			);
		}

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$issues[] = $this->issue(
				'plain_permalinks',
				'warning',
				'setup',
				__( 'Plain permalinks are in use', 'bearius-seo-light' ),
				__( 'Addresses like ?p=123 say nothing about the content, and some servers will not route the sitemap addresses to WordPress.', 'bearius-seo-light' ),
				admin_url( 'options-permalink.php' )
			);
		}

		if ( 0 !== strpos( (string) get_option( 'home' ), 'https://' ) ) {
			$issues[] = $this->issue(
				'no_https',
				'warning',
				'setup',
				__( 'The site address does not use HTTPS', 'bearius-seo-light' ),
				__( 'Browsers mark plain HTTP pages as not secure and search engines prefer secure addresses.', 'bearius-seo-light' ),
				admin_url( 'options-general.php' )
			);
		}

		if ( '' === trim( (string) get_bloginfo( 'name' ) ) ) {
			$issues[] = $this->issue(
				'no_site_title',
				'warning',
				'setup',
				__( 'The site has no title', 'bearius-seo-light' ),
				__( 'The title appears in browser tabs, search results and when pages are shared.', 'bearius-seo-light' ),
				admin_url( 'options-general.php' )
			);
		}

		$suite = Bearius_SEO_Compat::instance()->suite();
		if ( '' !== $suite ) {
			$issues[] = $this->issue(
				'suite_active',
				'info',
				'setup',
				/* translators: %s: plugin name. */
				sprintf( __( '%s is active', 'bearius-seo-light' ), $suite ),
				__( 'Meta tags, structured data, the XML sitemap, robots.txt and verification are paused here to avoid duplicates.', 'bearius-seo-light' )
			);
		}

		if ( bearius_seo_module_active( 'meta' ) || bearius_seo_module_active( 'schema' ) ) {
			if ( '' === bearius_seo_logo_url() ) {
				$issues[] = $this->issue(
					'no_logo',
					'notice',
					'setup',
					__( 'No publisher logo is set', 'bearius-seo-light' ),
					__( 'The logo is part of the publisher data in structured data and is shown by Google for news articles.', 'bearius-seo-light' ),
					bearius_seo_admin_url( 'publisher' )
				);
			}

			if ( '' === trim( (string) bearius_seo_get_setting( 'default_image' ) ) ) {
				$issues[] = $this->issue(
					'no_default_image',
					'notice',
					'setup',
					__( 'No default social image is set', 'bearius-seo-light' ),
					__( 'Pages without a featured image are shared without a picture.', 'bearius-seo-light' ),
					bearius_seo_admin_url( 'publisher' )
				);
			}
		}

		if ( ! has_site_icon() ) {
			$issues[] = $this->issue(
				'no_site_icon',
				'notice',
				'setup',
				__( 'The site has no site icon', 'bearius-seo-light' ),
				__( 'Google shows the site icon next to your pages in search results; without it a generic globe appears. Once set, WordPress adds it to the site, the dashboard and the login page.', 'bearius-seo-light' ),
				admin_url( 'customize.php?autofocus[section]=title_tagline' )
			);
		}

		if ( bearius_seo_get_setting( 'news_mode' ) && ! bearius_seo_module_enabled( 'news' ) ) {
			$issues[] = $this->issue(
				'news_off',
				'notice',
				'crawl',
				__( 'News mode is on, but the Google News sitemap is off', 'bearius-seo-light' ),
				__( 'A news sitemap helps Google find new articles within minutes.', 'bearius-seo-light' ),
				bearius_seo_admin_url( 'overview' )
			);
		}

		if ( bearius_seo_module_active( 'news' ) && ! Bearius_SEO_Sitemaps::news_post_types() ) {
			$issues[] = $this->issue(
				'news_no_types',
				'warning',
				'crawl',
				__( 'The Google News sitemap has no content types selected', 'bearius-seo-light' ),
				__( 'Choose at least one content type, otherwise the news sitemap stays empty.', 'bearius-seo-light' ),
				bearius_seo_admin_url( 'sitemaps' )
			);
		}

		if ( bearius_seo_module_active( 'robots' ) ) {
			if ( Bearius_SEO_Robots::in_subdirectory() ) {
				$issues[] = $this->issue(
					'robots_subdir',
					'notice',
					'crawl',
					__( 'robots.txt rules have no effect in a subdirectory', 'bearius-seo-light' ),
					__( 'Crawlers only read robots.txt at the root of the domain, so the rules set here are not seen.', 'bearius-seo-light' )
				);
			} elseif ( Bearius_SEO_Robots::physical_file_exists() ) {
				$issues[] = $this->issue(
					'robots_file',
					'warning',
					'crawl',
					__( 'A robots.txt file on the server overrides the rules set here', 'bearius-seo-light' ),
					__( 'Delete the robots.txt file from the site root, or copy the rules you need into it by hand.', 'bearius-seo-light' ),
					bearius_seo_admin_url( 'robots' )
				);
			}
		}

		if ( Bearius_SEO_Sitemaps::sitemap_active() ) {
			if ( ! function_exists( 'get_home_path' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			if ( file_exists( trailingslashit( get_home_path() ) . 'sitemap.xml' ) ) {
				$issues[] = $this->issue(
					'sitemap_file',
					'warning',
					'crawl',
					__( 'A sitemap.xml file on the server hides the generated sitemap', 'bearius-seo-light' ),
					__( 'The file is usually left over from an old plugin. Delete it so the server passes the address to WordPress.', 'bearius-seo-light' )
				);
			}
		}

		if ( Bearius_SEO_IndexNow::active() ) {
			$log = (array) get_option( Bearius_SEO_IndexNow::LOG, array() );
			if ( $log && ! in_array( (int) $log[0]['code'], array( 200, 202 ), true ) ) {
				$issues[] = $this->issue(
					'indexnow_failed',
					'warning',
					'crawl',
					__( 'The last IndexNow submission failed', 'bearius-seo-light' ),
					Bearius_SEO_IndexNow::describe( (int) $log[0]['code'] ),
					bearius_seo_admin_url( 'indexing' )
				);
			}
		}

		if ( bearius_seo_get_setting( 'notify_email' ) && get_option( 'bearius_seo_mail_failed' ) ) {
			$issues[] = $this->issue(
				'mail_failed',
				'warning',
				'setup',
				__( 'The last e-mail notification could not be sent', 'bearius-seo-light' ),
				__( 'WordPress reported an error while sending. Many hosts limit outgoing mail; an SMTP plugin usually fixes it.', 'bearius-seo-light' ),
				bearius_seo_admin_url( 'notifications' )
			);
		}

		return $issues;
	}

	/**
	 * Returns the stored results of the HTTP checks.
	 *
	 * @return array
	 */
	public function stored() {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'time'   => 0,
				'issues' => array(),
				'scans'  => array(),
				'robots' => '',
				'ai'     => array(),
			)
		);
	}

	/**
	 * Returns every current issue, most severe first.
	 *
	 * @return array[]
	 */
	public function issues() {
		/**
		 * Filters the current issues, used by add-ons to add their findings.
		 *
		 * @param array[] $issues Issues.
		 */
		$issues = (array) apply_filters( 'bearius_seo_issues', array_merge( $this->quick_issues(), $this->stored()['issues'] ) );
		$order  = array(
			'critical' => 0,
			'warning'  => 1,
			'notice'   => 2,
			'info'     => 3,
		);

		usort(
			$issues,
			function ( $a, $b ) use ( $order ) {
				return $order[ $a['severity'] ] - $order[ $b['severity'] ];
			}
		);

		return $issues;
	}

	/**
	 * Counts issues per severity.
	 *
	 * @param array[]|null $issues Issues, current ones when null.
	 * @return array
	 */
	public function counts( $issues = null ) {
		$issues = null === $issues ? $this->issues() : $issues;
		$counts = array(
			'critical' => 0,
			'warning'  => 0,
			'notice'   => 0,
			'info'     => 0,
		);

		foreach ( $issues as $issue ) {
			$counts[ $issue['severity'] ]++;
		}

		return $counts;
	}

	/**
	 * Calculates the Bearius score.
	 *
	 * A plain penalty sum: twenty points per critical issue, six per
	 * warning and two per suggestion. It is our own measure, not a
	 * PageSpeed or Google score.
	 *
	 * @param array[]|null $issues Issues, current ones when null.
	 * @return int
	 */
	public function score( $issues = null ) {
		$counts = $this->counts( $issues );

		return max( 0, 100 - 20 * $counts['critical'] - 6 * $counts['warning'] - 2 * $counts['notice'] );
	}

	/**
	 * Returns the URL of the latest indexable entry, used for the page scan.
	 *
	 * @return array|null URL and post ID.
	 */
	private function sample_post() {
		$types = in_array( 'post', bearius_seo_supported_post_types(), true ) ? array( 'post' ) : bearius_seo_supported_post_types();

		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'post__not_in'           => bearius_seo_noindex_post_ids(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return array(
			'url' => get_permalink( $query->posts[0] ),
			'id'  => $query->posts[0]->ID,
		);
	}

	/**
	 * Runs every check, including those that need HTTP requests.
	 *
	 * @return array Stored results.
	 */
	public function run() {
		// Repeated clicks or an overlapping cron run must not start parallel scans.
		if ( get_transient( 'bearius_seo_running' ) ) {
			return $this->stored();
		}

		set_transient( 'bearius_seo_running', 1, 3 * MINUTE_IN_SECONDS );

		$issues = array();
		$scans  = array();

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Sitemaps.
		if ( Bearius_SEO_Sitemaps::sitemap_active() ) {
			$response = bearius_seo_remote_get( Bearius_SEO_Sitemaps::index_url() );
			if ( 200 !== $response['code'] || false === strpos( $response['body'], '<sitemapindex' ) ) {
				$issues[] = $this->issue(
					'sitemap_http',
					'critical',
					'crawl',
					__( 'The XML sitemap cannot be opened', 'bearius-seo-light' ),
					/* translators: %d: HTTP status. */
					sprintf( __( 'sitemap.xml answered with HTTP %d instead of a sitemap. Check for a leftover file on the server or a caching rule.', 'bearius-seo-light' ), (int) $response['code'] ),
					Bearius_SEO_Sitemaps::index_url()
				);
			}
		}

		if ( Bearius_SEO_Sitemaps::news_active() ) {
			$response = bearius_seo_remote_get( Bearius_SEO_Sitemaps::news_url() );
			if ( 200 !== $response['code'] || false === strpos( $response['body'], '<' ) ) {
				$issues[] = $this->issue(
					'news_http',
					'warning',
					'crawl',
					__( 'The Google News sitemap cannot be opened', 'bearius-seo-light' ),
					/* translators: %d: HTTP status. */
					sprintf( __( 'news-sitemap.xml answered with HTTP %d.', 'bearius-seo-light' ), (int) $response['code'] ),
					Bearius_SEO_Sitemaps::news_url()
				);
			}
		}

		// The IndexNow key file proves ownership; some servers answer .txt files themselves.
		if ( Bearius_SEO_IndexNow::active() ) {
			$response = bearius_seo_remote_get( Bearius_SEO_IndexNow::key_url() );
			if ( 200 !== $response['code'] || trim( $response['body'] ) !== Bearius_SEO_IndexNow::key() ) {
				$issues[] = $this->issue(
					'indexnow_key_file',
					'warning',
					'crawl',
					__( 'The IndexNow key file cannot be opened', 'bearius-seo-light' ),
					__( 'Search engines check this file before accepting submissions. Your server probably serves .txt files directly without asking WordPress; create a text file with the key in the site root, or allow WordPress to answer it.', 'bearius-seo-light' ),
					Bearius_SEO_IndexNow::key_url()
				);
			}
		}

		// Robots.txt as crawlers really receive it.
		$robots_url = trailingslashit( (string) get_option( 'home' ) ) . 'robots.txt';
		$response   = bearius_seo_remote_get( $robots_url );
		$robots     = 200 === $response['code'] ? $response['body'] : '';

		if ( '' !== $robots && bearius_seo_site_is_public() ) {
			if ( ! Bearius_SEO_Robots::is_allowed( $robots, 'Googlebot', '/' ) || ! Bearius_SEO_Robots::is_allowed( $robots, 'Bingbot', '/' ) ) {
				$issues[] = $this->issue(
					'robots_blocks_all',
					'critical',
					'crawl',
					__( 'robots.txt blocks search engines from the whole site', 'bearius-seo-light' ),
					__( 'A Disallow: / rule applies to Googlebot or Bingbot. Unless the site is meant to be private, remove it.', 'bearius-seo-light' ),
					$robots_url
				);
			}

			if ( Bearius_SEO_Sitemaps::sitemap_active() && false === stripos( $robots, Bearius_SEO_Sitemaps::index_url() ) ) {
				$issues[] = $this->issue(
					'robots_differs',
					'notice',
					'crawl',
					__( 'The served robots.txt does not list the sitemap', 'bearius-seo-light' ),
					__( 'The file crawlers receive differs from the one built here. A physical robots.txt file, the CDN or another plugin replaces it.', 'bearius-seo-light' ),
					$robots_url
				);
			}
		}

		// AI crawler access.
		$ai = array();
		foreach ( Bearius_SEO_Robots::ai_bots() as $agent => $info ) {
			$ai[ $agent ] = '' === $robots ? true : Bearius_SEO_Robots::is_allowed( $robots, $agent, '/' );
		}

		// Only crawlers blocked by something other than the policy set here are reported.
		$own            = Bearius_SEO_Robots::blocked_bots();
		$search_blocked = array();
		foreach ( Bearius_SEO_Robots::ai_bots() as $agent => $info ) {
			if ( 'search' === $info[1] && ! $ai[ $agent ] && ! in_array( $agent, $own, true ) ) {
				$search_blocked[] = $agent;
			}
		}

		if ( $search_blocked ) {
			$issues[] = $this->issue(
				'ai_search_blocked',
				'notice',
				'ai',
				__( 'AI search crawlers are blocked', 'bearius-seo-light' ),
				/* translators: %s: list of crawlers. */
				sprintf( __( 'Blocked outside the AI visibility settings: %s. Something else, such as a robots.txt file on the server or another plugin, stops them. These crawlers decide whether your pages can be quoted in AI answers.', 'bearius-seo-light' ), implode( ', ', $search_blocked ) ),
				bearius_seo_admin_url( 'ai' )
			);
		}

		// Rendered pages.
		$scans['home'] = Bearius_SEO_Page_Scan::scan( home_url( '/' ), __( 'The home page', 'bearius-seo-light' ), 'home' );
		$sample        = $this->sample_post();

		// Language versions on another domain are outside the audit's reach.
		if ( $sample && $sample['url'] && wp_parse_url( $sample['url'], PHP_URL_HOST ) === wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) ) {
			$scans['post']       = Bearius_SEO_Page_Scan::scan( $sample['url'], __( 'The latest post', 'bearius-seo-light' ), 'post' );
			$scans['post']['id'] = $sample['id'];
		}

		foreach ( $scans as $scan ) {
			$issues = array_merge( $issues, $scan['issues'] );
		}

		// Raw page data is large; keep only what the screens show.
		foreach ( $scans as $key => $scan ) {
			unset( $scans[ $key ]['issues'] );
		}

		$stored = array(
			'time'   => time(),
			'issues' => $issues,
			'scans'  => $scans,
			'robots' => bearius_seo_substr( $robots, 0, 20000 ),
			'ai'     => $ai,
		);

		update_option( self::OPTION, $stored, false );
		delete_transient( 'bearius_seo_running' );

		return $stored;
	}

	/**
	 * Daily cron callback.
	 *
	 * @return void
	 */
	public function run_scheduled() {
		if ( bearius_seo_module_enabled( 'audit' ) ) {
			$this->run();
		}

		$this->notify();
	}

	/**
	 * Sends an e-mail about critical issues that were not reported before.
	 *
	 * @return void
	 */
	public function notify() {
		$critical = array();

		foreach ( $this->issues() as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$critical[ $issue['id'] ] = $issue;
			}
		}

		$known = (array) get_option( 'bearius_seo_notified', array() );
		$new   = array_diff_key( $critical, array_flip( $known ) );

		update_option( 'bearius_seo_notified', array_keys( $critical ), false );

		if ( ! $new || ! bearius_seo_get_setting( 'notify_email' ) ) {
			return;
		}

		$lines = array();
		foreach ( $new as $issue ) {
			$lines[] = '- ' . $issue['title'] . "\n  " . $issue['detail'];
		}

		$this->send(
			/* translators: %s: site name. */
			sprintf( __( '[%s] Critical SEO problem found', 'bearius-seo-light' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			__( 'Bearius SEO Light found a problem that can keep your site out of search results:', 'bearius-seo-light' ) . "\n\n" . implode( "\n\n", $lines ) . "\n\n" . __( 'Open the audit:', 'bearius-seo-light' ) . ' ' . bearius_seo_admin_url( 'audit' )
		);
	}

	/**
	 * Sends a notification e-mail and records failures.
	 *
	 * @param string $subject Subject.
	 * @param string $message Plain text body.
	 * @return bool
	 */
	public function send( $subject, $message ) {
		$to = trim( (string) bearius_seo_get_setting( 'notify_email_to' ) );
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );

		$this->sending = true;
		$sent          = wp_mail( $to, $subject, $message );
		$this->sending = false;

		if ( $sent ) {
			delete_option( 'bearius_seo_mail_failed' );
		} else {
			update_option( 'bearius_seo_mail_failed', time(), false );
		}

		return $sent;
	}

	/**
	 * Records a failed send reported by WordPress.
	 *
	 * @return void
	 */
	public function on_mail_failed() {
		if ( $this->sending ) {
			update_option( 'bearius_seo_mail_failed', time(), false );
		}
	}

	/**
	 * Returns the URL a form handler should go back to.
	 *
	 * @param string $fallback Fallback URL.
	 * @return string
	 */
	private function back( $fallback ) {
		$referer = wp_get_referer();

		return $referer ? $referer : $fallback;
	}

	/**
	 * Handles the "Run checks" button.
	 *
	 * @return void
	 */
	public function handle_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_run_checks' );

		$this->run();
		$this->notify();

		wp_safe_redirect( add_query_arg( 'bearius_checked', 1, $this->back( bearius_seo_admin_url( 'audit' ) ) ) );
		exit;
	}

	/**
	 * Hides a critical notice for the current user.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_dismiss' );

		$id        = isset( $_GET['issue'] ) ? sanitize_key( wp_unslash( $_GET['issue'] ) ) : '';
		$dismissed = (array) get_user_meta( get_current_user_id(), 'bearius_seo_dismissed', true );

		if ( '' !== $id ) {
			$dismissed[] = $id;
			update_user_meta( get_current_user_id(), 'bearius_seo_dismissed', array_values( array_unique( $dismissed ) ) );
		}

		wp_safe_redirect( $this->back( admin_url() ) );
		exit;
	}

	/**
	 * Sends a test e-mail.
	 *
	 * @return void
	 */
	public function handle_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_test_email' );

		$sent = $this->send(
			/* translators: %s: site name. */
			sprintf( __( '[%s] Test notification', 'bearius-seo-light' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			__( 'This is a test message from Bearius SEO Light. If you can read it, critical problems will reach you by e-mail.', 'bearius-seo-light' )
		);

		wp_safe_redirect( add_query_arg( 'bearius_mail', $sent ? 'sent' : 'failed', bearius_seo_admin_url( 'notifications' ) ) );
		exit;
	}

	/**
	 * Shows critical issues as dismissible admin notices.
	 *
	 * @return void
	 */
	public function render_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && bearius_seo_screen_id() === $screen->id ) {
			return;
		}

		$dismissed = (array) get_user_meta( get_current_user_id(), 'bearius_seo_dismissed', true );

		foreach ( $this->issues() as $issue ) {
			if ( 'critical' !== $issue['severity'] || in_array( $issue['id'], $dismissed, true ) ) {
				continue;
			}

			$dismiss = wp_nonce_url( add_query_arg( array( 'action' => 'bearius_seo_dismiss', 'issue' => $issue['id'] ), admin_url( 'admin-post.php' ) ), 'bearius_seo_dismiss' );
			$fix     = Bearius_SEO_Fixes::for_issue( $issue );

			if ( $fix ) {
				$action = '<a class="button button-primary" href="' . esc_url( $fix['url'] ) . '"' . ( $fix['confirm'] ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $fix['confirm'] ) ) . ');"' : '' ) . '>' . esc_html( $fix['label'] ) . '</a>';
			} else {
				$action = '<a class="button button-primary" href="' . esc_url( $issue['link'] ? $issue['link'] : bearius_seo_admin_url( 'audit' ) ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>';
			}

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p><p>%4$s <a class="button-link" href="%5$s">%6$s</a></p></div>',
				esc_html__( 'Bearius SEO:', 'bearius-seo-light' ),
				esc_html( $issue['title'] ),
				esc_html( $issue['detail'] ),
				$action, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
				esc_url( $dismiss ),
				esc_html__( 'Hide this notice', 'bearius-seo-light' )
			);
		}
	}

	/**
	 * Adds a summary test to the Site Health screen.
	 *
	 * @param array $tests Registered tests.
	 * @return array
	 */
	public function site_health_tests( $tests ) {
		$tests['direct']['bearius_seo_audit'] = array(
			'label' => __( 'Bearius SEO audit', 'bearius-seo-light' ),
			'test'  => array( $this, 'site_health_result' ),
		);

		return $tests;
	}

	/**
	 * Returns the Site Health result.
	 *
	 * @return array
	 */
	public function site_health_result() {
		$issues = $this->issues();
		$counts = $this->counts( $issues );
		$status = $counts['critical'] ? 'critical' : ( $counts['warning'] ? 'recommended' : 'good' );
		$list   = '';

		foreach ( array_slice( $issues, 0, 6 ) as $issue ) {
			if ( 'info' !== $issue['severity'] ) {
				$list .= '<li><strong>' . esc_html( bearius_seo_severity_label( $issue['severity'] ) ) . ':</strong> ' . esc_html( $issue['title'] ) . '</li>';
			}
		}

		return array(
			'label'       => 'good' === $status
				? __( 'No SEO problems found', 'bearius-seo-light' )
				/* translators: 1: critical count, 2: warning count. */
				: sprintf( __( 'SEO audit: %1$d critical, %2$d warnings', 'bearius-seo-light' ), $counts['critical'], $counts['warning'] ),
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'SEO', 'bearius-seo-light' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Results of the Bearius SEO Light audit.', 'bearius-seo-light' ) . '</p>' . ( $list ? '<ul>' . $list . '</ul>' : '' ),
			'actions'     => '<p><a href="' . esc_url( bearius_seo_admin_url( 'audit' ) ) . '">' . esc_html__( 'Open the audit', 'bearius-seo-light' ) . '</a></p>',
			'test'        => 'bearius_seo_audit',
		);
	}
}
