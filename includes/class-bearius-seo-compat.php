<?php
/**
 * Compatibility with other SEO plugins.
 *
 * When a full SEO suite is active, only the modules that would print the
 * same tags or serve the same files are switched off. Everything that does
 * not overlap, such as the Google News sitemap or the audit, keeps working.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects overlapping plugins and tells which modules have to step aside.
 */
class Bearius_SEO_Compat {

	/**
	 * Singleton instance.
	 *
	 * @var Bearius_SEO_Compat|null
	 */
	private static $instance = null;

	/**
	 * Detected SEO suite name.
	 *
	 * @var string|null
	 */
	private $suite = null;

	/**
	 * Name of the plugin that already provides a news sitemap.
	 *
	 * @var string|null
	 */
	private $news = null;

	/**
	 * Name of a dedicated sitemap plugin.
	 *
	 * @var string|null
	 */
	private $sitemap = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Bearius_SEO_Compat
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks the admin notice.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Returns the name of the active SEO suite, or an empty string.
	 *
	 * @return string
	 */
	public function suite() {
		if ( null !== $this->suite ) {
			return $this->suite;
		}

		$this->suite = '';

		$constants = array(
			'WPSEO_VERSION'             => 'Yoast SEO',
			'RANK_MATH_VERSION'         => 'Rank Math SEO',
			'SEOPRESS_VERSION'          => 'SEOPress',
			'AIOSEO_VERSION'            => 'All in One SEO',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
			'SLIM_SEO_VER'              => 'Slim SEO',
			'SQ_VERSION'                => 'Squirrly SEO',
			'SEO_BY_SQUIRRLY'           => 'Squirrly SEO',
		);

		foreach ( $constants as $constant => $label ) {
			if ( defined( $constant ) ) {
				$this->suite = $label;
				return $this->suite;
			}
		}

		if ( class_exists( 'WPSEO_Frontend' ) ) {
			$this->suite = 'Yoast SEO';
		} elseif ( function_exists( 'rank_math' ) ) {
			$this->suite = 'Rank Math SEO';
		} elseif ( function_exists( 'the_seo_framework' ) ) {
			$this->suite = 'The SEO Framework';
		}

		return $this->suite;
	}

	/**
	 * Returns the name of a plugin that already serves a Google News sitemap.
	 *
	 * @return string
	 */
	public function news_provider() {
		if ( null !== $this->news ) {
			return $this->news;
		}

		$this->news = '';

		if ( defined( 'WPSEO_NEWS_VERSION' ) || class_exists( 'WPSEO_News' ) ) {
			$this->news = 'Yoast News SEO';
		} elseif ( defined( 'RANK_MATH_PRO_VERSION' ) && in_array( 'news-sitemap', (array) get_option( 'rank_math_modules', array() ), true ) ) {
			$this->news = 'Rank Math PRO';
		} elseif ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$this->news = 'SEOPress PRO';
		} elseif ( defined( 'AIOSEO_NEWS_SITEMAP_VERSION' ) ) {
			$this->news = 'All in One SEO News Sitemap';
		} elseif ( defined( 'XMLSF_VERSION' ) ) {
			$this->news = 'XML Sitemap & Google News';
		}

		return $this->news;
	}

	/**
	 * Returns the name of a dedicated XML sitemap plugin.
	 *
	 * @return string
	 */
	public function sitemap_provider() {
		if ( null !== $this->sitemap ) {
			return $this->sitemap;
		}

		$this->sitemap = '';

		if ( defined( 'XMLSF_VERSION' ) ) {
			$this->sitemap = 'XML Sitemap & Google News';
		} elseif ( class_exists( 'GoogleSitemapGeneratorLoader' ) || defined( 'SM_SUPPORTFEED_URL' ) ) {
			$this->sitemap = 'XML Sitemap Generator for Google';
		}

		return $this->sitemap;
	}

	/**
	 * Returns the plugin that blocks a module, or an empty string.
	 *
	 * @param string $module Module name.
	 * @return string
	 */
	public function blocked_by( $module ) {
		switch ( $module ) {
			case 'meta':
			case 'schema':
			case 'terms':
			case 'robots':
			case 'verification':
				return $this->suite();

			case 'sitemap':
				return '' !== $this->suite() ? $this->suite() : $this->sitemap_provider();

			case 'news':
				return $this->news_provider();
		}

		return '';
	}

	/**
	 * Tells whether any SEO suite is active.
	 *
	 * @return bool
	 */
	public function has_conflict() {
		return '' !== $this->suite();
	}

	/**
	 * Prints a short notice on the plugin screens when modules were switched off.
	 *
	 * @return void
	 */
	public function render_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->has_conflict() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', bearius_seo_screen_id() ), true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: name of the other SEO plugin. */
					__( '<strong>Bearius SEO Light</strong> found %s, so its meta tags, structured data, XML sitemap, robots.txt and verification modules are paused to avoid duplicates. The Google News sitemap, IndexNow, the audit and the importer keep working.', 'bearius-seo-light' ),
					esc_html( $this->suite() )
				),
				array( 'strong' => array() )
			)
		);
	}
}
