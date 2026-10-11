<?php
/**
 * Shared helper functions.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the default settings array.
 *
 * @return array
 */
function bearius_seo_default_settings() {
	return array(
		// Publisher.
		'org_name'           => '',
		'org_logo'           => '',
		'site_description'   => '',
		'default_image'      => '',
		'twitter_site'       => '',
		'news_mode'          => 0,
		'modified_threshold' => 60,

		// Modules. The meta module keeps its 1.0 key for compatibility.
		'output_meta'        => 1,
		'mod_schema'         => 1,
		'mod_terms'          => 1,
		'mod_sitemap'        => 1,
		'mod_news'           => 0,
		'mod_robots'         => 1,
		'mod_indexnow'       => 0,
		'mod_verification'   => 1,
		'mod_breadcrumbs'    => 1,
		'mod_audit'          => 1,
		'mod_widget'         => 1,
		'credit_link'        => 0,
		'ai_reviewed'        => 0,

		// Feeds, tags, comments and archives.
		'rss_disable'        => 'none',
		'rss_image'          => 0,
		'rss_footer'         => 0,
		'rss_delay'          => 0,
		'rss_skip_noindex'   => 0,
		'rss_exclude_cats'   => array(),
		'tags_mode'          => 'on',
		'comments_mode'      => 'on',
		'archive_author'     => 'index',
		'archive_date'       => 'index',

		// Meta tags.
		'title_rewrite'      => 1,
		'meta_description'   => 1,
		'meta_canonical'     => 1,
		'meta_og'            => 1,
		'meta_twitter'       => 1,
		'meta_og_alternate'  => 1,
		'perf_eager_image'   => 0,

		// Structured data.
		'schema_org'         => 1,
		'schema_website'     => 1,
		'schema_article'     => 1,
		'schema_breadcrumbs' => 1,
		'schema_archives'    => 1,

		// Sitemaps.
		'sitemap_post_types' => '__all__',
		'sitemap_taxonomies' => array( 'category' ),
		'sitemap_max_urls'   => 1000,
		'sitemap_home'       => 1,
		'news_post_types'    => array( 'post' ),
		'news_exclude_cats'  => array(),
		'news_names'         => array(),

		// Robots.txt.
		'robots_custom'      => '',
		'robots_ai'          => 'none',
		'robots_ai_blocked'  => array(),

		// IndexNow.
		'indexnow_key'       => '',

		// Verification.
		'verify_google'      => '',
		'verify_bing'        => '',
		'verify_yandex'      => '',
		'verify_seznam'      => '',
		'verify_naver'       => '',
		'verify_baidu'       => '',
		'verify_pinterest'   => '',

		// Notifications.
		'notify_notices'     => 1,
		'notify_email'       => 0,
		'notify_email_to'    => '',
	);
}

/**
 * Returns all settings merged over the defaults.
 *
 * @return array
 */
function bearius_seo_get_settings() {
	$stored = get_option( BEARIUS_SEO_OPTION, array() );

	return wp_parse_args( is_array( $stored ) ? $stored : array(), bearius_seo_default_settings() );
}

/**
 * Returns a single setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value returned when the key is missing.
 * @return mixed
 */
function bearius_seo_get_setting( $key, $default = '' ) {
	$settings = bearius_seo_get_settings();

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Returns the publisher name, falling back to the site title.
 *
 * @return string
 */
function bearius_seo_org_name() {
	$name = trim( (string) bearius_seo_get_setting( 'org_name' ) );
	if ( '' !== $name ) {
		return $name;
	}

	$name = trim( (string) get_bloginfo( 'name' ) );
	if ( '' !== $name ) {
		return $name;
	}

	return bearius_seo_home_label();
}

/**
 * Returns the label used for the home entry in breadcrumbs.
 *
 * Sites with an empty title would otherwise produce a list item without a
 * name, which search engines reject, so the host name is used as a last
 * resort.
 *
 * @return string
 */
function bearius_seo_home_label() {
	$name = trim( (string) get_bloginfo( 'name' ) );
	if ( '' !== $name ) {
		return $name;
	}

	$name = trim( (string) bearius_seo_get_setting( 'org_name' ) );
	if ( '' !== $name ) {
		return $name;
	}

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $host ) {
		return preg_replace( '/^www\./i', '', $host );
	}

	return __( 'Home', 'bearius-seo-light' );
}

/**
 * Returns the logo URL, falling back to the custom logo and then the site icon.
 *
 * @return string
 */
function bearius_seo_logo_url() {
	$logo = trim( (string) bearius_seo_get_setting( 'org_logo' ) );
	if ( '' !== $logo ) {
		return $logo;
	}

	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}

	return (string) get_site_icon_url();
}

/**
 * Returns the string length, falling back when mbstring is unavailable.
 *
 * @param string $text Source text.
 * @return int
 */
function bearius_seo_strlen( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( $text, 'UTF-8' );
	}

	return (int) strlen( $text );
}

/**
 * Returns a substring, falling back when mbstring is unavailable.
 *
 * @param string   $text   Source text.
 * @param int      $start  Start offset.
 * @param int|null $length Length, null for the remainder.
 * @return string
 */
function bearius_seo_substr( $text, $start, $length = null ) {
	if ( function_exists( 'mb_substr' ) ) {
		return (string) mb_substr( $text, $start, $length, 'UTF-8' );
	}

	return null === $length ? (string) substr( $text, $start ) : (string) substr( $text, $start, $length );
}

/**
 * Finds the last occurrence of a needle, falling back when mbstring is unavailable.
 *
 * @param string $haystack Source text.
 * @param string $needle   Text to find.
 * @return int|false
 */
function bearius_seo_strrpos( $haystack, $needle ) {
	if ( function_exists( 'mb_strrpos' ) ) {
		return mb_strrpos( $haystack, $needle, 0, 'UTF-8' );
	}

	return strrpos( $haystack, $needle );
}

/**
 * Trims a string to a character limit, cutting on a word boundary only.
 *
 * @param string $text  Source text.
 * @param int    $limit Maximum length including the ellipsis.
 * @return string
 */
function bearius_seo_trim_on_word( $text, $limit ) {
	$text = trim( wp_strip_all_tags( $text ) );

	if ( bearius_seo_strlen( $text ) <= $limit ) {
		return $text;
	}

	$cut   = bearius_seo_substr( $text, 0, $limit - 1 );
	$space = bearius_seo_strrpos( $cut, ' ' );

	if ( false !== $space && $space >= (int) ( $limit * 0.6 ) ) {
		$cut = bearius_seo_substr( $cut, 0, $space );
	}

	$cut = rtrim( $cut, " \t\n\r\0\x0B,;:.!?-" );

	return $cut . '...';
}

/**
 * Counts words in a UTF-8 safe way.
 *
 * str_word_count() breaks on non-ASCII alphabets, so a Unicode aware
 * pattern is used instead.
 *
 * @param string $text Source text.
 * @return int
 */
function bearius_seo_count_words( $text ) {
	return (int) preg_match_all( '/[\p{L}\p{N}]+/u', wp_strip_all_tags( $text ) );
}

/**
 * Returns the meta description for a single post.
 *
 * Order: custom field, manual excerpt, generated excerpt.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function bearius_seo_post_description( $post_id ) {
	$custom = get_post_meta( $post_id, '_bearius_seo_desc', true );
	if ( $custom ) {
		return $custom;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	if ( has_excerpt( $post ) ) {
		return bearius_seo_trim_on_word( get_the_excerpt( $post ), 160 );
	}

	$content = strip_shortcodes( $post->post_content );
	$content = wp_strip_all_tags( $content );

	return bearius_seo_trim_on_word( $content, 160 );
}

/**
 * Returns the social image for a post: custom field, featured image, then the site default.
 *
 * @param int $post_id Post ID.
 * @return array {
 *     @type string $url    Image URL, empty when nothing is available.
 *     @type int    $width  Width in pixels, 0 when unknown.
 *     @type int    $height Height in pixels, 0 when unknown.
 *     @type string $alt    Alternative text.
 * }
 */
function bearius_seo_post_image( $post_id ) {
	$empty = array(
		'url'    => '',
		'width'  => 0,
		'height' => 0,
		'alt'    => '',
	);

	$custom = get_post_meta( $post_id, '_bearius_seo_image', true );
	if ( $custom ) {
		return bearius_seo_image_by_url( $custom );
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		$src      = wp_get_attachment_image_src( $thumb_id, 'full' );

		if ( $src ) {
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			);
		}
	}

	$default = bearius_seo_get_setting( 'default_image' );
	if ( $default ) {
		return bearius_seo_image_by_url( $default );
	}

	$icon = get_site_icon_url();
	if ( $icon ) {
		return array(
			'url'    => $icon,
			'width'  => 512,
			'height' => 512,
			'alt'    => '',
		);
	}

	return $empty;
}

/**
 * Builds an image array from a URL, resolving dimensions for local attachments.
 *
 * External URLs keep zeroed dimensions and those tags are simply skipped.
 *
 * @param string $url Image URL.
 * @return array
 */
function bearius_seo_image_by_url( $url ) {
	$data = array(
		'url'    => $url,
		'width'  => 0,
		'height' => 0,
		'alt'    => '',
	);

	$attachment_id = attachment_url_to_postid( $url );
	if ( $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$data['width']  = (int) $meta['width'];
			$data['height'] = (int) $meta['height'];
		}

		$data['alt'] = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	}

	return $data;
}

/**
 * Returns the primary term of a post, preferring a single child over its parent.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 * @return WP_Term|null
 */
function bearius_seo_primary_term( $post_id, $taxonomy = 'category' ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	if ( 1 === count( $terms ) ) {
		return $terms[0];
	}

	$top_level = array();
	$children  = array();

	foreach ( $terms as $term ) {
		if ( 0 === (int) $term->parent ) {
			$top_level[] = $term;
		} else {
			$children[] = $term;
		}
	}

	if ( 1 === count( $children ) ) {
		return $children[0];
	}

	if ( ! empty( $top_level ) ) {
		return $top_level[0];
	}

	return $terms[0];
}

/**
 * Returns the modification date in ISO 8601, but only once it differs
 * meaningfully from publication.
 *
 * Small edits should not send a false freshness signal, so anything below
 * the configured threshold reports the original publication date.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function bearius_seo_modified_date( $post ) {
	$published = (int) get_post_time( 'U', true, $post );
	$modified  = (int) get_post_modified_time( 'U', true, $post );
	$minutes   = max( 1, (int) bearius_seo_get_setting( 'modified_threshold', 60 ) );

	if ( ( $modified - $published ) < ( $minutes * MINUTE_IN_SECONDS ) ) {
		return (string) get_the_date( 'c', $post );
	}

	return (string) get_the_modified_date( 'c', $post );
}

/**
 * Returns the site language as an IETF tag, for example pl-PL.
 *
 * @return string
 */
function bearius_seo_language() {
	return str_replace( '_', '-', get_locale() );
}

/**
 * Returns the Open Graph locale, for example pl_PL.
 *
 * @return string
 */
function bearius_seo_og_locale() {
	return get_locale();
}

/**
 * Returns the post types the plugin adds its meta box to.
 *
 * @return string[]
 */
function bearius_seo_supported_post_types() {
	$types = get_post_types(
		array(
			'public' => true,
		),
		'names'
	);

	unset( $types['attachment'] );

	/**
	 * Filters the post types that receive the SEO meta box.
	 *
	 * @param string[] $types Post type names.
	 */
	return (array) apply_filters( 'bearius_seo_post_types', array_values( $types ) );
}

/**
 * Prints a JSON-LD script block.
 *
 * Angle brackets, ampersands and quotes are encoded as escape sequences so
 * that content containing a closing script tag cannot break out of the
 * block. The result stays valid JSON-LD, since consumers decode those
 * sequences back before parsing.
 *
 * @param array $data Structured data.
 * @return void
 */
function bearius_seo_print_jsonld( $data ) {
	if ( empty( $data ) ) {
		return;
	}

	echo '<script type="application/ld+json">';
	echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	echo '</script>' . "\n";
}

/**
 * Maps a module name to the setting key that switches it on.
 *
 * @param string $module Module name.
 * @return string
 */
function bearius_seo_module_key( $module ) {
	return 'meta' === $module ? 'output_meta' : 'mod_' . $module;
}

/**
 * Tells whether a module is switched on in the settings.
 *
 * @param string $module Module name.
 * @return bool
 */
function bearius_seo_module_enabled( $module ) {
	return (bool) bearius_seo_get_setting( bearius_seo_module_key( $module ), 0 );
}

/**
 * Tells whether a module is switched on and not blocked by another plugin.
 *
 * @param string $module Module name.
 * @return bool
 */
function bearius_seo_module_active( $module ) {
	if ( ! bearius_seo_module_enabled( $module ) ) {
		return false;
	}

	return '' === Bearius_SEO_Compat::instance()->blocked_by( $module );
}

/**
 * Returns the post types included in the XML sitemap.
 *
 * @return string[]
 */
function bearius_seo_sitemap_post_types() {
	// Types that cannot be viewed on the front end would only list dead addresses.
	$all   = array_values( array_filter( bearius_seo_supported_post_types(), 'is_post_type_viewable' ) );
	$saved = bearius_seo_get_setting( 'sitemap_post_types', '__all__' );

	if ( ! is_array( $saved ) ) {
		return $all;
	}

	return array_values( array_intersect( $all, $saved ) );
}

/**
 * Returns the public taxonomies that can appear in the sitemap.
 *
 * @return WP_Taxonomy[]
 */
function bearius_seo_public_taxonomies() {
	$taxonomies = get_taxonomies(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'objects'
	);

	unset( $taxonomies['post_format'] );

	return $taxonomies;
}

/**
 * Returns the taxonomies included in the XML sitemap.
 *
 * @return string[]
 */
function bearius_seo_sitemap_taxonomies() {
	$saved = bearius_seo_get_setting( 'sitemap_taxonomies', array() );

	if ( ! is_array( $saved ) ) {
		return array();
	}

	$list = array_filter( array_intersect( array_keys( bearius_seo_public_taxonomies() ), $saved ), 'is_taxonomy_viewable' );

	// Tags hidden from visitors have no public archives to list.
	if ( 'on' !== bearius_seo_get_setting( 'tags_mode', 'on' ) ) {
		$list = array_diff( $list, array( 'post_tag' ) );
	}

	return array_values( $list );
}

/**
 * Returns the IDs of posts marked noindex.
 *
 * The list is usually short, so a single query with a static cache is
 * cheaper than joining post meta into every sitemap query.
 *
 * @return int[]
 */
function bearius_seo_noindex_post_ids() {
	static $ids = null;

	if ( null === $ids ) {
		global $wpdb;
		$ids = array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'", '_bearius_seo_noindex' ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	return $ids;
}

/**
 * Returns the IDs of posts excluded from the Google News sitemap.
 *
 * @return int[]
 */
function bearius_seo_news_excluded_ids() {
	global $wpdb;

	return array_map(
		'intval',
		$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'", '_bearius_seo_news_exclude' ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);
}

/**
 * Returns the IDs of terms marked noindex.
 *
 * @return int[]
 */
function bearius_seo_noindex_term_ids() {
	static $ids = null;

	if ( null === $ids ) {
		global $wpdb;
		$ids = array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = '1'", '_bearius_seo_noindex' ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	return $ids;
}

/**
 * Returns the custom SEO title of a term.
 *
 * @param WP_Term $term Term.
 * @return string
 */
function bearius_seo_term_title( $term ) {
	return trim( (string) get_term_meta( $term->term_id, '_bearius_seo_title', true ) );
}

/**
 * Returns the meta description of a term: custom field, then its description.
 *
 * @param WP_Term $term Term.
 * @return string
 */
function bearius_seo_term_description( $term ) {
	$custom = trim( (string) get_term_meta( $term->term_id, '_bearius_seo_desc', true ) );

	if ( '' !== $custom ) {
		return $custom;
	}

	$description = wp_strip_all_tags( term_description( $term ) );

	return $description ? bearius_seo_trim_on_word( $description, 160 ) : '';
}

/**
 * Returns the canonical URL of the current request, pagination included.
 *
 * Page two of an archive is its own document, so it points to itself
 * rather than to page one.
 *
 * @return string
 */
function bearius_seo_current_canonical() {
	$paged = max( 1, (int) get_query_var( 'paged' ) );

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$url  = get_permalink( $post );
		$page = max( 1, (int) get_query_var( 'page' ) );

		if ( $page > 1 && $url ) {
			$url = '' === (string) get_option( 'permalink_structure' ) || in_array( $post->post_status, array( 'draft', 'pending' ), true )
				? add_query_arg( 'page', $page, $url )
				: trailingslashit( $url ) . user_trailingslashit( (string) $page, 'single_paged' );
		}

		return (string) $url;
	}

	if ( is_front_page() || is_home() || is_category() || is_tag() || is_tax() || is_post_type_archive() || is_author() || is_date() ) {
		if ( $paged > 1 ) {
			return (string) get_pagenum_link( $paged, false );
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : $link;
		}

		return (string) get_pagenum_link( 1, false );
	}

	return '';
}

/**
 * Returns the breadcrumb trail for the current request.
 *
 * Shared by the structured data and the visible breadcrumbs, so both always
 * describe the same path.
 *
 * @return array[] Items with name and url keys.
 */
function bearius_seo_breadcrumb_items() {
	$items = array(
		array(
			'name' => bearius_seo_home_label(),
			'url'  => home_url( '/' ),
		),
	);

	$object = get_queried_object();

	$term_chain = function ( $term ) use ( &$items ) {
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );

			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$link = get_term_link( $ancestor );

				if ( ! is_wp_error( $link ) ) {
					$items[] = array(
						'name' => $ancestor->name,
						'url'  => $link,
					);
				}
			}
		}

		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$items[] = array(
				'name' => $term->name,
				'url'  => $link,
			);
		}
	};

	if ( is_singular() && $object instanceof WP_Post ) {
		if ( 'post' === $object->post_type ) {
			$term = bearius_seo_primary_term( $object->ID );
			if ( $term ) {
				$term_chain( $term );
			}
		} elseif ( $object->post_parent ) {
			foreach ( array_reverse( get_post_ancestors( $object ) ) as $ancestor_id ) {
				$items[] = array(
					'name' => get_the_title( $ancestor_id ),
					'url'  => get_permalink( $ancestor_id ),
				);
			}
		}

		$items[] = array(
			'name' => get_the_title( $object ),
			'url'  => get_permalink( $object ),
		);
	} elseif ( ( is_category() || is_tag() || is_tax() ) && $object instanceof WP_Term ) {
		$term_chain( $object );
	} elseif ( is_post_type_archive() && $object instanceof WP_Post_Type ) {
		$items[] = array(
			'name' => $object->labels->name,
			'url'  => (string) get_post_type_archive_link( $object->name ),
		);
	} elseif ( is_author() && $object instanceof WP_User ) {
		$items[] = array(
			'name' => $object->display_name,
			'url'  => get_author_posts_url( $object->ID ),
		);
	} elseif ( is_search() ) {
		$items[] = array(
			/* translators: %s: search phrase. */
			'name' => sprintf( __( 'Search results for "%s"', 'bearius-seo-light' ), get_search_query( false ) ),
			'url'  => get_search_link(),
		);
	}

	// A ListItem without a name is invalid, so incomplete entries are dropped
	// rather than emitted and rejected by search engines.
	return array_values(
		array_filter(
			$items,
			function ( $item ) {
				return '' !== trim( wp_strip_all_tags( (string) $item['name'] ) ) && '' !== trim( (string) $item['url'] );
			}
		)
	);
}

/**
 * Converts a WordPress locale to the code Google News expects.
 *
 * Google wants ISO 639 codes, with the two Chinese scripts as the only
 * exception.
 *
 * @param string $locale Locale such as pl_PL.
 * @return string
 */
function bearius_seo_news_language( $locale ) {
	$locale = strtolower( str_replace( '-', '_', (string) $locale ) );

	if ( in_array( $locale, array( 'zh_tw', 'zh_hk', 'zh_hant' ), true ) ) {
		return 'zh-tw';
	}

	if ( 0 === strpos( $locale, 'zh' ) ) {
		return 'zh-cn';
	}

	$parts = explode( '_', $locale );

	return $parts[0] ? $parts[0] : 'en';
}

/**
 * Fetches a URL of this site the way a logged out visitor would see it.
 *
 * @param string $url    URL to fetch.
 * @param string $device Either desktop or mobile.
 * @return array {
 *     @type int    $code    HTTP status, 0 on a transport error.
 *     @type string $body    Response body.
 *     @type array  $headers Lower cased response headers.
 *     @type float  $time    Seconds until the response was complete.
 *     @type string $error   Transport error message.
 * }
 */
function bearius_seo_remote_get( $url, $device = 'desktop' ) {
	$home_host = wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
	$scheme    = wp_parse_url( (string) $url, PHP_URL_SCHEME );

	// The audit reads this site only; anything else is refused before a request is made.
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) !== strtolower( (string) $home_host ) ) {
		return array(
			'code'    => 0,
			'body'    => '',
			'headers' => array(),
			'time'    => 0,
			'error'   => __( 'Only addresses on this site can be fetched.', 'bearius-seo-light' ),
		);
	}

	$agents = array(
		'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 BeariusSEO/' . BEARIUS_SEO_VERSION,
		'mobile'  => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36 BeariusSEO/' . BEARIUS_SEO_VERSION,
	);

	$start    = microtime( true );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'             => 20,
			'redirection'         => 3,
			// Only this site may be fetched, also after redirects, and the
			// body is capped so a huge page cannot exhaust memory.
			'reject_unsafe_urls'  => true,
			'limit_response_size' => 5 * MB_IN_BYTES,
			'user-agent'          => isset( $agents[ $device ] ) ? $agents[ $device ] : $agents['desktop'],
			'sslverify'           => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter.
			'headers'             => array(
				'Cache-Control' => 'no-cache',
			),
		)
	);
	$time     = microtime( true ) - $start;

	if ( is_wp_error( $response ) ) {
		return array(
			'code'    => 0,
			'body'    => '',
			'headers' => array(),
			'time'    => $time,
			'error'   => $response->get_error_message(),
		);
	}

	$raw     = wp_remote_retrieve_headers( $response );
	$raw     = is_object( $raw ) && method_exists( $raw, 'getAll' ) ? $raw->getAll() : (array) $raw;
	$headers = array();
	foreach ( $raw as $name => $value ) {
		$headers[ strtolower( $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $response ),
		'body'    => (string) wp_remote_retrieve_body( $response ),
		'headers' => $headers,
		'time'    => $time,
		'error'   => '',
	);
}

/**
 * Returns the human readable label of an issue severity.
 *
 * @param string $severity Severity key.
 * @return string
 */
function bearius_seo_severity_label( $severity ) {
	$labels = array(
		'critical' => __( 'Critical', 'bearius-seo-light' ),
		'warning'  => __( 'Warning', 'bearius-seo-light' ),
		'notice'   => __( 'Suggestion', 'bearius-seo-light' ),
		'info'     => __( 'Information', 'bearius-seo-light' ),
	);

	return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
}

/**
 * Returns the URL of a tab on the settings screen.
 *
 * @param string $tab  Tab key.
 * @param array  $args Extra query arguments.
 * @return string
 */
function bearius_seo_admin_url( $tab = 'overview', $args = array() ) {
	return add_query_arg(
		array_merge(
			array(
				'page' => 'bearius-seo-light',
				'tab'  => $tab,
			),
			$args
		),
		admin_url( 'admin.php' )
	);
}

/**
 * Returns the screen id of the plugin screen.
 *
 * @return string
 */
function bearius_seo_screen_id() {
	return 'toplevel_page_bearius-seo-light';
}

/**
 * Tells whether search engines are allowed to index the site.
 *
 * @return bool
 */
function bearius_seo_site_is_public() {
	return '0' !== (string) get_option( 'blog_public' );
}

/**
 * Saves a few settings at once, keeping all the others.
 *
 * @param array $changes Setting key => value.
 * @return void
 */
function bearius_seo_update_settings( $changes ) {
	update_option( BEARIUS_SEO_OPTION, array_merge( bearius_seo_get_settings(), (array) $changes ) );
}

/**
 * Loads the featured image of the current entry right away instead of lazily.
 *
 * The featured image is usually the largest element on screen, so lazy
 * loading it delays the moment the page looks ready.
 *
 * @param array   $attr       Image attributes.
 * @param WP_Post $attachment Attachment.
 * @return array
 */
function bearius_seo_eager_featured_image( $attr, $attachment ) {
	static $done = false;

	if ( $done || is_admin() || ! is_singular() || ! $attachment instanceof WP_Post ) {
		return $attr;
	}

	if ( (int) get_post_thumbnail_id( get_queried_object_id() ) !== (int) $attachment->ID ) {
		return $attr;
	}

	$done = true;

	unset( $attr['loading'] );
	$attr['fetchpriority'] = 'high';

	return $attr;
}

/**
 * Returns the page cache plugins this site uses that can be cleared from here.
 *
 * Only plugins with a documented way to clear everything are listed.
 *
 * @return callable[] Plugin name => callback.
 */
function bearius_seo_page_cache_purgers() {
	$purgers = array();

	if ( function_exists( 'rocket_clean_domain' ) ) {
		$purgers['WP Rocket'] = 'rocket_clean_domain';
	}

	if ( function_exists( 'w3tc_flush_all' ) ) {
		$purgers['W3 Total Cache'] = 'w3tc_flush_all';
	}

	if ( defined( 'LSCWP_V' ) ) {
		$purgers['LiteSpeed Cache'] = function () {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- LiteSpeed action.
		};
	}

	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		$purgers['WP Super Cache'] = 'wp_cache_clear_cache';
	}

	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		$purgers['SiteGround Optimizer'] = 'sg_cachepress_purge_cache';
	}

	return $purgers;
}

/**
 * Returns the languages the plugin ships translations for.
 *
 * @return array Locale => native name.
 */
function bearius_seo_bundled_languages() {
	return array(
		'en_US' => 'English',
		'pl_PL' => 'Polski',
		'de_DE' => 'Deutsch',
		'es_ES' => 'Español',
		'fr_FR' => 'Français',
	);
}

/**
 * Returns the locale for the plugin interface.
 *
 * In the dashboard it follows the language of the user's profile, not the
 * language of the site, so someone working in English on a Japanese site
 * sees English. A personal choice on the Overview screen overrides it.
 *
 * @return string
 */
function bearius_seo_ui_locale() {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	if ( is_admin() && function_exists( 'get_current_user_id' ) && get_current_user_id() ) {
		$choice = (string) get_user_meta( get_current_user_id(), 'bearius_seo_ui_locale', true );
		if ( '' !== $choice && isset( bearius_seo_bundled_languages()[ $choice ] ) ) {
			$locale = $choice;
		}
	}

	return $locale;
}

/**
 * Loads the bundled translation of a Bearius text domain.
 *
 * Bundled files are complete from day one; they replace a language pack
 * from WordPress.org for this domain. A personal choice of English keeps
 * the interface in English even when the profile language differs.
 *
 * @param string $domain Text domain.
 * @param string $dir    Directory with the .mo files.
 * @return void
 */
function bearius_seo_load_translation( $domain, $dir ) {
	$locale = bearius_seo_ui_locale();
	$file   = trailingslashit( $dir ) . $domain . '-' . $locale . '.mo';

	// de_AT, es_MX or fr_CA use the closest bundled translation.
	if ( ! is_readable( $file ) ) {
		foreach ( array_keys( bearius_seo_bundled_languages() ) as $bundled ) {
			if ( strtolower( substr( $bundled, 0, 2 ) ) === strtolower( substr( $locale, 0, 2 ) ) ) {
				$file = trailingslashit( $dir ) . $domain . '-' . $bundled . '.mo';
				break;
			}
		}
	}

	if ( 'en_US' === $locale ) {
		unload_textdomain( $domain );
		return;
	}

	if ( is_readable( $file ) ) {
		unload_textdomain( $domain );

		// Since WordPress 6.5 translations are stored per locale and looked
		// up for the locale of the request. A personal choice that differs
		// from the profile language is therefore stored under the request
		// locale, otherwise WordPress would never read it.
		load_textdomain( $domain, $file, function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
	}
}
