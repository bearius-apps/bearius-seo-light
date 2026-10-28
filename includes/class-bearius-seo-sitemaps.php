<?php
/**
 * XML sitemaps: the main sitemap and the Google News sitemap.
 *
 * The main sitemap replaces the one built into WordPress, so that it looks
 * the same as the news sitemap and always lists every language version.
 * Requests are matched on the raw path during init, which keeps the
 * sitemaps independent of rewrite rules and permalink plugins.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the sitemap files and keeps their cache up to date.
 */
class Bearius_SEO_Sitemaps {

	/**
	 * Option holding the content version used to invalidate cached page maps.
	 */
	const VERSION_OPTION = 'bearius_seo_sm_ver';

	/**
	 * Hooks routing, cache invalidation and robots.txt lines.
	 *
	 * @return void
	 */
	public function register() {
		if ( self::sitemap_active() ) {
			add_filter( 'wp_sitemaps_enabled', '__return_false' );
		} elseif ( bearius_seo_fields_active( 'meta' ) ) {
			// The WordPress sitemap stays in use, but noindex content is kept out of it.
			add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_core_posts' ) );
			add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'filter_core_terms' ) );
		}

		if ( self::sitemap_active() || self::news_active() ) {
			add_action( 'init', array( $this, 'route' ), 99 );
			add_filter( 'robots_txt', array( $this, 'robots_lines' ), 20, 2 );
			add_action( 'transition_post_status', array( $this, 'on_post_change' ), 10, 3 );
			add_action( 'deleted_post', array( $this, 'bump' ) );
			add_action( 'created_term', array( $this, 'bump' ) );
			add_action( 'edited_term', array( $this, 'bump' ) );
			add_action( 'delete_term', array( $this, 'bump' ) );
			add_action( 'bearius_seo_flush_sitemaps', array( $this, 'bump' ) );
			add_filter( 'posts_where', array( $this, 'filter_id_range' ), 10, 2 );
		}
	}

	/**
	 * Tells whether the main sitemap is served by this plugin.
	 *
	 * @return bool
	 */
	public static function sitemap_active() {
		return bearius_seo_module_active( 'sitemap' ) && bearius_seo_site_is_public();
	}

	/**
	 * Tells whether the Google News sitemap is served.
	 *
	 * @return bool
	 */
	public static function news_active() {
		return bearius_seo_module_active( 'news' ) && bearius_seo_site_is_public();
	}

	/**
	 * Returns the unfiltered home URL with a trailing slash.
	 *
	 * Multilingual plugins localise home_url(), but sitemap files live at
	 * the root of the site regardless of the current language.
	 *
	 * @return string
	 */
	public static function base_url() {
		return trailingslashit( (string) get_option( 'home' ) );
	}

	/**
	 * Returns the URL of the sitemap index.
	 *
	 * @return string
	 */
	public static function index_url() {
		return self::base_url() . 'sitemap.xml';
	}

	/**
	 * Returns the URL of the Google News sitemap.
	 *
	 * @return string
	 */
	public static function news_url() {
		return self::base_url() . 'news-sitemap.xml';
	}

	/**
	 * Excludes noindex posts from the WordPress sitemap.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function filter_core_posts( $args ) {
		$ids = bearius_seo_noindex_post_ids();

		if ( $ids ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		return $args;
	}

	/**
	 * Excludes noindex terms from the WordPress sitemap.
	 *
	 * @param array $args Term query arguments.
	 * @return array
	 */
	public function filter_core_terms( $args ) {
		$ids = bearius_seo_noindex_term_ids();

		if ( $ids ) {
			$args['exclude'] = array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $ids );
		}

		return $args;
	}

	/**
	 * Adds the sitemap addresses to robots.txt.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function robots_lines( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$lines = array();

		if ( self::sitemap_active() ) {
			$lines[] = 'Sitemap: ' . self::index_url();
		}

		if ( self::news_active() ) {
			$lines[] = 'Sitemap: ' . self::news_url();
		}

		if ( ! $lines ) {
			return $output;
		}

		return rtrim( $output ) . "\n\n" . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Invalidates cached page maps when published content changes.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public function on_post_change( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->bump();
	}

	/**
	 * Increments the content version.
	 *
	 * @return void
	 */
	public function bump() {
		update_option( self::VERSION_OPTION, (int) get_option( self::VERSION_OPTION, 0 ) + 1 );
	}

	/**
	 * Limits a query to an ID range, used for sitemap pages.
	 *
	 * Ranges replace OFFSET, which gets slow on large sites.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function filter_id_range( $where, $query ) {
		$range = $query->get( 'bearius_id_range' );

		if ( is_array( $range ) && 2 === count( $range ) ) {
			global $wpdb;
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", (int) $range[0], (int) $range[1] );
		}

		return $where;
	}

	/**
	 * Matches the request path and serves the right file.
	 *
	 * @return void
	 */
	public function route() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$relative = $this->relative_path();

		if ( '' === $relative ) {
			return;
		}

		if ( preg_match( '#^bearius-sm-style/(index|urls|news-index|news)(?:--([a-z0-9-]+))?/?$#', $relative, $match ) || preg_match( '/^bearius-sm-(index|urls|news-index|news)(?:--([a-z0-9-]+))?\.css$/', $relative, $match ) ) {
			$this->serve_css( $match[1], isset( $match[2] ) ? $match[2] : '' );
		}

		if ( self::sitemap_active() ) {
			if ( 'sitemap.xml' === $relative ) {
				$this->serve_index();
			}

			if ( 'sitemap-home.xml' === $relative ) {
				$this->serve_rows( $this->home_rows(), 'urls', '' );
			}

			if ( preg_match( '/^wp-sitemap(?:-[a-z0-9_-]+)?\.xml$/', $relative ) || 'sitemap_index.xml' === $relative ) {
				wp_safe_redirect( self::index_url(), 301, 'Bearius SEO Light' );
				exit;
			}

			$part = $this->parse_part( $relative );
			if ( $part ) {
				$rows = $this->part_rows( $part['kind'], $part['subtype'], $part['lang'], $part['page'] );
				if ( null !== $rows ) {
					$this->serve_rows( $rows, 'urls', $part['lang'] );
				}
			}
		}

		if ( self::news_active() ) {
			if ( 'news-sitemap.xml' === $relative ) {
				$this->serve_news();
			}

			if ( preg_match( '/^news-sitemap-([a-z0-9-]+)\.xml$/', $relative, $match ) ) {
				$this->serve_news( $match[1] );
			}
		}
	}

	/**
	 * Parses the file name of a sitemap part.
	 *
	 * @param string $file File name relative to the site root.
	 * @return array|null Kind, subtype, lang and page, or null.
	 */
	public function parse_part( $file ) {
		$keys    = array_filter( array_keys( Bearius_SEO_Languages::instance()->languages() ) );
		$pattern = $keys ? '(?:(' . implode( '|', array_map( 'preg_quote', $keys ) ) . ')-)?' : '()';

		if ( ! preg_match( '/^sitemap-' . $pattern . '(posts|terms)-([a-z0-9_-]+)-(\d+)\.xml$/', (string) $file, $match ) ) {
			return null;
		}

		return array(
			'lang'    => (string) $match[1],
			'kind'    => $match[2],
			'subtype' => $match[3],
			'page'    => (int) $match[4],
		);
	}

	/**
	 * Returns the request path relative to the site root.
	 *
	 * @return string
	 */
	private function relative_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return '';
		}

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( self::base_url(), PHP_URL_PATH );
		$home = '' === $home ? '/' : trailingslashit( $home );

		if ( 0 !== strpos( $path, $home ) ) {
			return '';
		}

		return ltrim( substr( $path, strlen( $home ) ), '/' );
	}

	/**
	 * Sends the XML headers.
	 *
	 * @param int $max_age Browser and proxy cache lifetime in seconds.
	 * @return void
	 */
	private function headers( $max_age = 3600 ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Cache-Control: public, max-age=' . (int) $max_age );
	}

	/**
	 * Prints the XML prolog with a CSS stylesheet for people opening the file.
	 *
	 * Browsers are removing XSLT, so the files are styled with CSS, which
	 * keeps working everywhere and changes nothing for crawlers.
	 *
	 * @param string $view View kind: index, urls, news-index or news.
	 * @param string $key  Language key shown in the heading.
	 * @return void
	 */
	private function prolog( $view, $key = '' ) {
		// No file extension: many servers answer .css requests themselves and never reach WordPress.
		$file = 'bearius-sm-style/' . $view . ( '' !== $key ? '--' . $key : '' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<?xml-stylesheet type="text/css" href="' . esc_url( self::base_url() . $file ) . '"?>' . "\n";
	}

	/**
	 * Escapes a URL for use inside an XML element.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function xml_url( $url ) {
		return esc_xml( esc_url_raw( $url ) );
	}

	/**
	 * Formats a GMT MySQL date for the sitemap.
	 *
	 * @param string $gmt Date in GMT.
	 * @return string
	 */
	private function w3c( $gmt ) {
		if ( ! $gmt || '0000-00-00 00:00:00' === $gmt ) {
			return '';
		}

		return gmdate( 'c', strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * Returns the date of the latest change to published content.
	 *
	 * @return string GMT MySQL date.
	 */
	private function latest_modified() {
		global $wpdb;

		$types = bearius_seo_sitemap_post_types();
		if ( ! $types ) {
			return '';
		}

		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in)", $types ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the language keys to build files for, or one empty key on single language sites.
	 *
	 * @return string[]
	 */
	private function language_keys() {
		$languages = Bearius_SEO_Languages::instance();

		return $languages->is_multilingual() ? array_keys( $languages->languages() ) : array( '' );
	}

	/**
	 * Builds the base query arguments for a post type.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Language key.
	 * @return array|null Null when the type has no posts in that language.
	 */
	private function post_args( $post_type, $key ) {
		$languages = Bearius_SEO_Languages::instance();

		if ( '' !== $key && ! $languages->shares_posts() && ! $languages->translatable_post_type( $post_type ) && $key !== $languages->default_key() ) {
			return null;
		}

		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'has_password'           => false,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		);

		$noindex = bearius_seo_noindex_post_ids();
		if ( $noindex ) {
			$args['post__not_in'] = $noindex; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		if ( '' !== $key ) {
			$args = array_merge( $args, $languages->post_query_args( $key, $post_type ) );
		}

		/**
		 * Filters the query of entries listed in the XML sitemap.
		 *
		 * Used both to split the sitemap into files and to list each file,
		 * so an exclusion added here keeps the file sizes right. After a
		 * change that affects the result, fire the bearius_seo_flush_sitemaps
		 * action so the cached file map is rebuilt.
		 *
		 * @param array  $args      WP_Query arguments.
		 * @param string $post_type Post type.
		 * @param string $key       Language key, empty on single language sites.
		 */
		return (array) apply_filters( 'bearius_seo_sitemap_post_args', $args, $post_type, $key );
	}

	/**
	 * Returns the cache context: content version plus the relevant settings.
	 *
	 * @return string
	 */
	private function cache_context() {
		return md5(
			wp_json_encode(
				array(
					(int) get_option( self::VERSION_OPTION, 0 ),
					(int) bearius_seo_get_setting( 'sitemap_max_urls', 1000 ),
					bearius_seo_sitemap_post_types(),
					bearius_seo_sitemap_taxonomies(),
					BEARIUS_SEO_VERSION,
				)
			)
		);
	}

	/**
	 * Returns the number of URLs per sitemap file.
	 *
	 * @return int
	 */
	private function per_page() {
		return min( 10000, max( 100, (int) bearius_seo_get_setting( 'sitemap_max_urls', 1000 ) ) );
	}

	/**
	 * Returns the page map of a post type: ID range, size and last change per file.
	 *
	 * Stored in one option per type and language, overwritten in place, so
	 * frequent publishing does not leave stale transients behind.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Language key.
	 * @return array[]
	 */
	private function post_pages( $post_type, $key ) {
		$option  = 'bearius_seo_smp_' . md5( 'posts|' . $post_type . '|' . $key );
		$context = $this->cache_context();
		$cached  = get_option( $option );

		if ( is_array( $cached ) && isset( $cached['context'] ) && $cached['context'] === $context ) {
			return $cached['pages'];
		}

		$pages = array();
		$args  = $this->post_args( $post_type, $key );

		if ( null !== $args ) {
			$args['fields']                 = 'ids';
			$args['posts_per_page']         = -1;
			$args['update_post_term_cache'] = false;
			$args['cache_results']          = false;

			$ids = Bearius_SEO_Languages::instance()->with_language(
				$key,
				function () use ( $args ) {
					$query = new WP_Query( $args );
					return array_map( 'intval', $query->posts );
				}
			);

			global $wpdb;

			foreach ( array_chunk( $ids, $this->per_page() ) as $chunk ) {
				$in      = implode( ',', array_map( 'intval', $chunk ) );
				$pages[] = array(
					'start'   => (int) reset( $chunk ),
					'end'     => (int) end( $chunk ),
					'count'   => count( $chunk ),
					'lastmod' => (string) $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE ID IN ($in)" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integers only.
				);
			}
		}

		update_option(
			$option,
			array(
				'context' => $context,
				'pages'   => $pages,
			),
			false
		);

		return $pages;
	}

	/**
	 * Returns the term query arguments for a taxonomy and language.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $key      Language key.
	 * @return array|null
	 */
	private function term_args( $taxonomy, $key ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		);

		$noindex = bearius_seo_noindex_term_ids();
		if ( $noindex ) {
			$args['exclude'] = $noindex;
		}

		if ( '' !== $key ) {
			$extra = Bearius_SEO_Languages::instance()->term_query_args( $key, $taxonomy );
			if ( null === $extra ) {
				return null;
			}
			$args = array_merge( $args, $extra );
		}

		/**
		 * Filters the query of terms listed in the XML sitemap.
		 *
		 * @param array  $args     get_terms() arguments.
		 * @param string $taxonomy Taxonomy.
		 * @param string $key      Language key, empty on single language sites.
		 */
		return (array) apply_filters( 'bearius_seo_sitemap_term_args', $args, $taxonomy, $key );
	}

	/**
	 * Returns the number of indexable terms of a taxonomy in a language.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $key      Language key.
	 * @return int
	 */
	private function term_total( $taxonomy, $key ) {
		$option  = 'bearius_seo_smp_' . md5( 'terms|' . $taxonomy . '|' . $key );
		$context = $this->cache_context();
		$cached  = get_option( $option );

		if ( is_array( $cached ) && isset( $cached['context'], $cached['total'] ) && $cached['context'] === $context ) {
			return (int) $cached['total'];
		}

		$total = 0;
		$args  = $this->term_args( $taxonomy, $key );

		if ( null !== $args ) {
			$args['fields'] = 'ids';
			$ids            = Bearius_SEO_Languages::instance()->with_language(
				$key,
				function () use ( $args ) {
					$terms = get_terms( $args );
					return is_wp_error( $terms ) ? array() : $terms;
				}
			);
			$total          = count( $ids );
		}

		update_option(
			$option,
			array(
				'context' => $context,
				'total'   => $total,
			),
			false
		);

		return $total;
	}

	/**
	 * Builds a file URL for a sitemap part.
	 *
	 * @param string $kind    Either posts or terms.
	 * @param string $subtype Post type or taxonomy.
	 * @param string $key     Language key.
	 * @param int    $page    Page number.
	 * @return string
	 */
	private function part_url( $kind, $subtype, $key, $page ) {
		return self::base_url() . 'sitemap-' . ( '' !== $key ? $key . '-' : '' ) . $kind . '-' . $subtype . '-' . (int) $page . '.xml';
	}

	/**
	 * Lists every file of the sitemap with what it contains.
	 *
	 * Used by the index and by the preview in the settings.
	 *
	 * @return array[] Each with url, lastmod, lang, kind, subtype, page and count.
	 */
	public function files() {
		$latest = $this->latest_modified();
		$files  = array();

		if ( bearius_seo_get_setting( 'sitemap_home' ) ) {
			$files[] = array(
				'url'     => self::base_url() . 'sitemap-home.xml',
				'lastmod' => $latest,
				'lang'    => '',
				'kind'    => 'home',
				'subtype' => '',
				'page'    => 1,
				'count'   => count( $this->language_keys() ),
			);
		}

		foreach ( $this->language_keys() as $key ) {
			foreach ( bearius_seo_sitemap_post_types() as $post_type ) {
				foreach ( $this->post_pages( $post_type, $key ) as $index => $page ) {
					$files[] = array(
						'url'     => $this->part_url( 'posts', $post_type, $key, $index + 1 ),
						'lastmod' => $page['lastmod'],
						'lang'    => $key,
						'kind'    => 'posts',
						'subtype' => $post_type,
						'page'    => $index + 1,
						'count'   => isset( $page['count'] ) ? (int) $page['count'] : 0,
					);
				}
			}

			foreach ( bearius_seo_sitemap_taxonomies() as $taxonomy ) {
				$total = $this->term_total( $taxonomy, $key );
				$pages = (int) ceil( $total / $this->per_page() );

				for ( $page = 1; $page <= $pages; $page++ ) {
					$files[] = array(
						'url'     => $this->part_url( 'terms', $taxonomy, $key, $page ),
						'lastmod' => $latest,
						'lang'    => $key,
						'kind'    => 'terms',
						'subtype' => $taxonomy,
						'page'    => $page,
						'count'   => min( $this->per_page(), $total - ( $page - 1 ) * $this->per_page() ),
					);
				}
			}
		}

		return $files;
	}

	/**
	 * Returns the home page of every language.
	 *
	 * @return array[] Rows of URL and last change.
	 */
	public function home_rows() {
		$languages = Bearius_SEO_Languages::instance();
		$latest    = $this->w3c( $this->latest_modified() );
		$rows      = array();

		foreach ( $this->language_keys() as $key ) {
			$url          = '' === $key ? home_url( '/' ) : $languages->home_url( $key );
			$rows[ $url ] = array( $url, $latest );
		}

		return array_values( $rows );
	}

	/**
	 * Returns the rows of one sitemap part.
	 *
	 * @param string $kind    Either posts or terms.
	 * @param string $subtype Post type or taxonomy.
	 * @param string $key     Language key.
	 * @param int    $page    Page number.
	 * @return array[]|null Rows of URL and last change, or null when the file does not exist.
	 */
	public function part_rows( $kind, $subtype, $key, $page ) {
		$languages = Bearius_SEO_Languages::instance();

		if ( '' !== $key && ! $languages->language( $key ) ) {
			return null;
		}

		if ( 'posts' === $kind ) {
			if ( ! in_array( $subtype, bearius_seo_sitemap_post_types(), true ) ) {
				return null;
			}

			$pages = $this->post_pages( $subtype, $key );
			if ( ! isset( $pages[ $page - 1 ] ) ) {
				return null;
			}

			$args                     = $this->post_args( $subtype, $key );
			$args['posts_per_page']   = $this->per_page();
			$args['bearius_id_range'] = array( $pages[ $page - 1 ]['start'], $pages[ $page - 1 ]['end'] );

			return $languages->with_language(
				$key,
				function () use ( $args, $languages, $key ) {
					$out   = array();
					$query = new WP_Query( $args );

					foreach ( $query->posts as $post ) {
						$url = get_permalink( $post );
						if ( ! $url ) {
							continue;
						}

						$url = $languages->url_for( $url, $key );

						// An entry turned into a link to another site keeps
						// that address as its permalink. Search engines reject
						// sitemaps that list another host, so it is left out.
						if ( bearius_seo_is_own_url( $url ) ) {
							$out[] = array( $url, $this->w3c( $post->post_modified_gmt ) );
						}
					}

					return $out;
				}
			);
		}

		if ( 'terms' !== $kind || ! in_array( $subtype, bearius_seo_sitemap_taxonomies(), true ) ) {
			return null;
		}

		if ( $page < 1 || ( $page - 1 ) * $this->per_page() >= $this->term_total( $subtype, $key ) ) {
			return null;
		}

		$args = $this->term_args( $subtype, $key );
		if ( null === $args ) {
			return null;
		}

		$args['number'] = $this->per_page();
		$args['offset'] = ( $page - 1 ) * $this->per_page();

		return $languages->with_language(
			$key,
			function () use ( $args, $languages, $key ) {
				$out   = array();
				$terms = get_terms( $args );

				if ( is_wp_error( $terms ) ) {
					return $out;
				}

				foreach ( $terms as $term ) {
					$url = get_term_link( $term );
					if ( is_wp_error( $url ) ) {
						continue;
					}

					$url = $languages->url_for( $url, $key );
					if ( bearius_seo_is_own_url( $url ) ) {
						$out[] = array( $url, '' );
					}
				}

				return $out;
			}
		);
	}

	/**
	 * Serves the sitemap index.
	 *
	 * @return void
	 */
	private function serve_index() {
		$files = $this->files();

		$this->headers();
		$this->prolog( 'index' );
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $files as $file ) {
			echo "\t<sitemap>\n\t\t<loc>" . $this->xml_url( $file['url'] ) . "</loc>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in xml_url().
			$lastmod = $this->w3c( $file['lastmod'] );
			if ( $lastmod ) {
				echo "\t\t<lastmod>" . esc_xml( $lastmod ) . "</lastmod>\n";
			}
			echo "\t</sitemap>\n";
		}

		echo '</sitemapindex>' . "\n";
		exit;
	}

	/**
	 * Serves a list of addresses.
	 *
	 * @param array[] $rows Rows of URL and last change.
	 * @param string  $view View kind for the stylesheet.
	 * @param string  $key  Language key for the heading.
	 * @return void
	 */
	private function serve_rows( $rows, $view, $key ) {
		$this->headers();
		$this->prolog( $view, $key );
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $rows as $row ) {
			echo "\t<url>\n\t\t<loc>" . $this->xml_url( $row[0] ) . "</loc>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in xml_url().
			if ( $row[1] ) {
				echo "\t\t<lastmod>" . esc_xml( $row[1] ) . "</lastmod>\n";
			}
			echo "\t</url>\n";
		}

		echo '</urlset>' . "\n";
		exit;
	}

	/**
	 * Returns the news post types that still exist.
	 *
	 * @return string[]
	 */
	public static function news_post_types() {
		$types = array_intersect( bearius_seo_supported_post_types(), (array) bearius_seo_get_setting( 'news_post_types', array( 'post' ) ) );

		return array_values( array_filter( $types, 'is_post_type_viewable' ) );
	}

	/**
	 * Returns the publication name for a language.
	 *
	 * @param string $key Language key.
	 * @return string
	 */
	public static function publication_name( $key ) {
		$names = (array) bearius_seo_get_setting( 'news_names', array() );
		$index = '' === $key ? '_default' : $key;

		if ( ! empty( $names[ $index ] ) ) {
			return (string) $names[ $index ];
		}

		return bearius_seo_org_name();
	}

	/**
	 * Returns the excluded categories together with all their translations.
	 *
	 * Only categories of the default language are chosen in the settings;
	 * their translations are excluded automatically.
	 *
	 * @return int[]
	 */
	public static function news_excluded_categories() {
		$languages = Bearius_SEO_Languages::instance();
		$ids       = array();

		foreach ( array_filter( array_map( 'intval', (array) bearius_seo_get_setting( 'news_exclude_cats', array() ) ) ) as $term_id ) {
			$ids = array_merge( $ids, array_values( $languages->term_translations( $term_id, 'category' ) ) );
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Returns the articles of one language for the news sitemap.
	 *
	 * Cached for five minutes, so the public file cannot be used to put
	 * load on the database.
	 *
	 * @param string $key Language key, empty on single language sites.
	 * @return array[] Each with url, date and title.
	 */
	public function news_items( $key ) {
		$languages  = Bearius_SEO_Languages::instance();
		$types      = self::news_post_types();
		$excluded   = array_unique( array_merge( bearius_seo_noindex_post_ids(), bearius_seo_news_excluded_ids() ) );
		$categories = self::news_excluded_categories();
		$cache      = 'bearius_seo_news_' . md5( $key . '|' . get_option( self::VERSION_OPTION, 0 ) . '|' . wp_json_encode( array( $types, $excluded, $categories ) ) );
		$cached     = get_transient( $cache );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$items = array();

		if ( $types ) {
			$args = array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => 1000,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'date_query'             => array(
					array(
						'column' => 'post_date_gmt',
						'after'  => gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ),
					),
				),
			);

			if ( $excluded ) {
				$args['post__not_in'] = $excluded; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			}

			if ( $categories ) {
				$args['category__not_in'] = $categories;
			}

			if ( '' !== $key && ! $languages->shares_posts() ) {
				$translatable = array_values( array_filter( $types, array( $languages, 'translatable_post_type' ) ) );

				// Types without translations belong to the default language only.
				if ( $key !== $languages->default_key() ) {
					if ( $translatable ) {
						$args['post_type'] = $translatable;
					} else {
						$args['post__in'] = array( 0 );
					}
				}

				if ( $translatable ) {
					$args = array_merge( $args, $languages->post_query_args( $key, $translatable[0] ) );
				}
			}

			/**
			 * Filters the query of articles listed in the Google News sitemap.
			 *
			 * @param array  $args WP_Query arguments.
			 * @param string $key  Language key, empty on single language sites.
			 */
			$args = (array) apply_filters( 'bearius_seo_news_query_args', $args, $key );

			$items = $languages->with_language(
				$key,
				function () use ( $args, $languages, $key ) {
					$out   = array();
					$query = new WP_Query( $args );

					foreach ( $query->posts as $post ) {
						$url = get_permalink( $post );
						if ( ! $url ) {
							continue;
						}

						$url = $languages->url_for( $url, $key );

						// Google News accepts only addresses of the publication itself.
						if ( ! bearius_seo_is_own_url( $url ) ) {
							continue;
						}

						$title = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );

						$out[] = array(
							'url'   => $url,
							'date'  => (string) get_post_time( 'c', false, $post ),
							'title' => $languages->translate_text( $title, $key ),
						);
					}

					return $out;
				}
			);
		}

		set_transient( $cache, $items, 5 * MINUTE_IN_SECONDS );

		return $items;
	}

	/**
	 * Serves the Google News sitemap, or its index on multilingual sites.
	 *
	 * @param string|null $key Language key for one language file.
	 * @return void
	 */
	private function serve_news( $key = null ) {
		$languages = Bearius_SEO_Languages::instance();

		if ( null === $key && $languages->is_multilingual() ) {
			$this->headers( 300 );
			$this->prolog( 'news-index' );
			echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

			foreach ( array_keys( $languages->languages() ) as $lang_key ) {
				echo "\t<sitemap>\n\t\t<loc>" . $this->xml_url( self::base_url() . 'news-sitemap-' . $lang_key . '.xml' ) . "</loc>\n\t</sitemap>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in xml_url().
			}

			echo '</sitemapindex>' . "\n";
			exit;
		}

		if ( null === $key ) {
			$key = '';
		} elseif ( ! $languages->is_multilingual() || ! $languages->language( $key ) ) {
			return;
		}

		$lang  = $languages->language( $key );
		$code  = bearius_seo_news_language( $lang ? $lang['locale'] : get_locale() );
		$name  = self::publication_name( $key );
		$items = $this->news_items( $key );

		$this->headers( 300 );
		$this->prolog( 'news', $key );
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

		foreach ( $items as $item ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . $this->xml_url( $item['url'] ) . "</loc>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in xml_url().
			echo "\t\t<news:news>\n";
			echo "\t\t\t<news:publication>\n";
			echo "\t\t\t\t<news:name>" . esc_xml( $name ) . "</news:name>\n";
			echo "\t\t\t\t<news:language>" . esc_xml( $code ) . "</news:language>\n";
			echo "\t\t\t</news:publication>\n";
			echo "\t\t\t<news:publication_date>" . esc_xml( $item['date'] ) . "</news:publication_date>\n";
			echo "\t\t\t<news:title>" . esc_xml( $item['title'] ) . "</news:title>\n";
			echo "\t\t</news:news>\n";
			echo "\t</url>\n";
		}

		echo '</urlset>' . "\n";
		exit;
	}

	/**
	 * Escapes text for a CSS string, quotes included.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function css_string( $text ) {
		$text = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', wp_strip_all_tags( (string) $text ) );

		return '"' . str_replace( array( '\\', '"', '<', '>' ), array( '\\\\', '\\"', '\\3C ', '\\3E ' ), $text ) . '"';
	}

	/**
	 * Serves the stylesheet that makes a sitemap readable in a browser.
	 *
	 * Served from the site root because browsers only apply stylesheets
	 * from the same origin as the XML file.
	 *
	 * @param string $view View kind: index, urls, news-index or news.
	 * @param string $key  Language key for the heading.
	 * @return void
	 */
	private function serve_css( $view, $key ) {
		$file = BEARIUS_SEO_PATH . 'assets/css/sitemap-view.css';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$title = in_array( $view, array( 'news', 'news-index' ), true ) ? __( 'Google News sitemap', 'bearius-seo-light' ) : __( 'XML sitemap', 'bearius-seo-light' );
		$lang  = '' !== $key ? Bearius_SEO_Languages::instance()->language( $key ) : null;

		if ( $lang ) {
			$title .= ' - ' . $lang['name'];
		}

		$intros = array(
			'index'      => __( 'The list of sitemap files of this site. Search engines open each file to find your content.', 'bearius-seo-light' ),
			'urls'       => __( 'Addresses search engines are invited to crawl, with the date of the last change.', 'bearius-seo-light' ),
			'news-index' => __( 'One file per language. Each file lists the articles published in the last two days.', 'bearius-seo-light' ),
			'news'       => __( 'Articles published in the last two days, newest first, in the format Google News expects.', 'bearius-seo-light' ),
		);

		$css = strtr(
			(string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local static file.
			array(
				'{{title}}'       => $this->css_string( $title . ' - ' . bearius_seo_org_name() ),
				'{{intro}}'       => $this->css_string( $intros[ $view ] ),
				'{{footer}}'      => $this->css_string( __( 'Generated by Bearius SEO Light. A list with clickable links is available in the plugin settings.', 'bearius-seo-light' ) ),
				'{{last_change}}' => $this->css_string( __( 'Last change', 'bearius-seo-light' ) . ': ' ),
				'{{published}}'   => $this->css_string( __( 'Published', 'bearius-seo-light' ) . ': ' ),
			)
		);

		if ( 'news' !== $view ) {
			$css = preg_replace( '#/\* news:start \*/.*?/\* news:end \*/#s', '', $css );
		}

		status_header( 200 );
		header( 'Content-Type: text/css; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=86400' );

		echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static stylesheet with escaped strings.
		exit;
	}
}
