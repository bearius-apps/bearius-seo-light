<?php
/**
 * Shared helper functions.
 *
 * @package BeariusSEOLight
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
		'org_name'           => '',
		'org_logo'           => '',
		'site_description'   => '',
		'default_image'      => '',
		'twitter_site'       => '',
		'news_mode'          => 0,
		'schema_org'         => 1,
		'schema_website'     => 1,
		'schema_article'     => 1,
		'schema_breadcrumbs' => 1,
		'schema_archives'    => 1,
		'output_meta'        => 1,
		'title_rewrite'      => 1,
		'modified_threshold' => 60,
	);
}

/**
 * Returns all settings merged over the defaults.
 *
 * @return array
 */
function bearius_seo_get_settings() {
	static $settings = null;

	if ( null === $settings ) {
		$stored   = get_option( BEARIUS_SEO_OPTION, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), bearius_seo_default_settings() );
	}

	return $settings;
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
