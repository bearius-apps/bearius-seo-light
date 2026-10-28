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
	 * Parts the active theme declared as its own.
	 *
	 * @var string[]|null
	 */
	private $theme_parts = null;

	/**
	 * Parts whose editor fields the theme reads.
	 *
	 * @var string[]|null
	 */
	private $field_parts = null;

	/**
	 * Parts a theme can take over with add_theme_support( 'bearius-seo', ... ).
	 *
	 * The first eight are modules, the last four are groups of options on
	 * the Feeds and archives screen.
	 *
	 * @return string[]
	 */
	public static function theme_areas() {
		return array( 'meta', 'schema', 'terms', 'breadcrumbs', 'verification', 'sitemap', 'news', 'robots', 'feeds', 'tags', 'comments', 'archives' );
	}

	/**
	 * Returns the parts the active theme handles itself.
	 *
	 * A theme declares them in after_setup_theme:
	 *
	 *     add_theme_support( 'bearius-seo', array( 'meta', 'schema' ) );
	 *
	 * Those parts stay off whatever the settings say, so the theme and the
	 * plugin never print the same tags. Plugins can extend the list through
	 * the bearius_seo_theme_modules filter.
	 *
	 * @return string[]
	 */
	public function theme_parts() {
		if ( null !== $this->theme_parts ) {
			return $this->theme_parts;
		}

		$parts   = array();
		$support = get_theme_support( 'bearius-seo' );

		if ( is_array( $support ) ) {
			foreach ( $support as $item ) {
				foreach ( (array) $item as $part ) {
					if ( is_string( $part ) ) {
						$parts[] = sanitize_key( $part );
					}
				}
			}
		}

		/**
		 * Filters the parts handled by the theme instead of the plugin.
		 *
		 * @param string[] $parts Part names, see Bearius_SEO_Compat::theme_areas().
		 */
		$parts = (array) apply_filters( 'bearius_seo_theme_modules', array_values( array_unique( $parts ) ) );
		$parts = array_values( array_intersect( self::theme_areas(), array_map( 'sanitize_key', array_filter( $parts, 'is_string' ) ) ) );

		// The theme is loaded only once after_setup_theme starts. Earlier
		// answers are not kept, so a later call sees the declaration.
		if ( did_action( 'after_setup_theme' ) ) {
			$this->theme_parts = $parts;
		}

		return $parts;
	}

	/**
	 * Tells whether the active theme handles a part.
	 *
	 * @param string $part Part name.
	 * @return bool
	 */
	public function theme_handles( $part ) {
		return in_array( $part, $this->theme_parts(), true );
	}

	/**
	 * Returns the parts whose editor fields the theme reads.
	 *
	 * A theme that prints the tags itself can still leave the editing to
	 * the plugin. It declares that next to the main declaration:
	 *
	 *     add_theme_support( 'bearius-seo-fields' );
	 *
	 * Without arguments both parts are meant: 'meta' for the SEO box in the
	 * post editor and 'terms' for the fields on category, tag and other
	 * archive screens. A list such as array( 'meta' ) limits it. Only parts
	 * the theme also handles count, see theme_reads_fields().
	 *
	 * @return string[]
	 */
	public function theme_field_parts() {
		if ( null !== $this->field_parts ) {
			return $this->field_parts;
		}

		$all     = array( 'meta', 'terms' );
		$parts   = array();
		$support = get_theme_support( 'bearius-seo-fields' );

		if ( true === $support ) {
			$parts = $all;
		} elseif ( is_array( $support ) ) {
			foreach ( $support as $item ) {
				foreach ( (array) $item as $part ) {
					if ( is_string( $part ) ) {
						$parts[] = sanitize_key( $part );
					}
				}
			}

			// add_theme_support( 'bearius-seo-fields' ) with no list at all.
			if ( ! $parts ) {
				$parts = $all;
			}
		}

		/**
		 * Filters the parts whose fields the theme reads from the plugin.
		 *
		 * @param string[] $parts 'meta', 'terms' or both.
		 */
		$parts = (array) apply_filters( 'bearius_seo_theme_fields', array_values( array_unique( $parts ) ) );
		$parts = array_values( array_intersect( $all, array_map( 'sanitize_key', array_filter( $parts, 'is_string' ) ) ) );

		if ( did_action( 'after_setup_theme' ) ) {
			$this->field_parts = $parts;
		}

		return $parts;
	}

	/**
	 * Tells whether the plugin keeps the editor fields of a part the theme prints.
	 *
	 * True when the theme handles the part, declared that it reads the
	 * plugin fields and no SEO suite is active. The plugin then shows the
	 * fields and saves them, but prints nothing on the front end.
	 *
	 * @param string $part 'meta' or 'terms'.
	 * @return bool
	 */
	public function theme_reads_fields( $part ) {
		return $this->theme_handles( $part ) && in_array( $part, $this->theme_field_parts(), true ) && '' === $this->suite();
	}

	/**
	 * Returns the note for a module switch, with the fields sentence when it applies.
	 *
	 * @param string $module Module name.
	 * @return string
	 */
	public function module_note( $module ) {
		$note = $this->note_for( $this->blocked_by( $module ) );

		if ( '' === $note || ! $this->theme_reads_fields( $module ) ) {
			return $note;
		}

		$extra = 'terms' === $module
			? __( 'The SEO fields on category and tag screens stay on, and the theme reads them.', 'bearius-seo-light' )
			: __( 'The SEO box in the editor stays on, and the theme reads its fields.', 'bearius-seo-light' );

		return $note . ' ' . $extra;
	}

	/**
	 * Returns the name of the active theme.
	 *
	 * @return string
	 */
	public function theme_name() {
		$name = (string) wp_get_theme()->get( 'Name' );

		return '' !== $name ? $name : (string) get_stylesheet();
	}

	/**
	 * Returns the sentence shown next to an option that is paused.
	 *
	 * @param string $blocker Name returned by blocked_by().
	 * @return string
	 */
	public function note_for( $blocker ) {
		if ( '' === (string) $blocker ) {
			return '';
		}

		if ( $this->theme_parts() && $blocker === $this->theme_name() ) {
			/* translators: %s: theme name. */
			return sprintf( __( 'Handled by the %s theme.', 'bearius-seo-light' ), $blocker );
		}

		/* translators: %s: plugin name. */
		return sprintf( __( 'Paused while %s is active.', 'bearius-seo-light' ), $blocker );
	}

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
		if ( $this->theme_handles( $module ) ) {
			return $this->theme_name();
		}

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
