<?php
/**
 * Content audit on live SQL queries.
 *
 * Nothing is scanned in the background and nothing is stored: every list is
 * one query against posts and post meta, which keeps the audit current and
 * works on sites with hundreds of thousands of entries.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds entries with SEO problems.
 */
class Bearius_SEO_Content_Audit {

	/**
	 * How many recent entries the image text check reads.
	 */
	const ALT_WINDOW = 300;

	/**
	 * Returns the issue types.
	 *
	 * @return array[] Label, severity and explanation per type.
	 */
	public static function types() {
		$has_default = '' !== trim( (string) bearius_seo_get_setting( 'default_image' ) );

		return array(
			'dup_title'  => array(
				__( 'Duplicate titles', 'bearius-seo-light' ),
				'warning',
				__( 'Several entries share the same title, so search engines cannot tell them apart.', 'bearius-seo-light' ),
			),
			'dup_desc'   => array(
				__( 'Duplicate descriptions', 'bearius-seo-light' ),
				'warning',
				__( 'The same custom meta description is used on more than one entry.', 'bearius-seo-light' ),
			),
			'no_image'   => array(
				__( 'No image for sharing', 'bearius-seo-light' ),
				$has_default ? 'notice' : 'warning',
				$has_default
					? __( 'No featured or social image; the default image is used when shared.', 'bearius-seo-light' )
					: __( 'No featured or social image and no default image, so shares appear without a picture.', 'bearius-seo-light' ),
			),
			'small_image' => array(
				__( 'Images too small for Discover', 'bearius-seo-light' ),
				bearius_seo_get_setting( 'news_mode' ) ? 'warning' : 'notice',
				/* translators: %d: number of entries checked. */
				sprintf( __( 'The featured image is narrower than 1200 pixels, the size Google Discover asks for to show large previews. Checked in the latest %d entries.', 'bearius-seo-light' ), self::ALT_WINDOW ),
			),
			'long_title' => array(
				__( 'Long titles', 'bearius-seo-light' ),
				'notice',
				__( 'The title is longer than search results usually show and will be cut off.', 'bearius-seo-light' ),
			),
			'thin'       => array(
				__( 'Very short content', 'bearius-seo-light' ),
				'notice',
				__( 'Fewer than about a hundred words. Short pages rarely rank unless they answer something precisely.', 'bearius-seo-light' ),
			),
			'img_alt'    => array(
				__( 'Images without alternative text', 'bearius-seo-light' ),
				'notice',
				/* translators: %d: number of entries checked. */
				sprintf( __( 'Images in the content have no alt text. Checked in the latest %d entries.', 'bearius-seo-light' ), self::ALT_WINDOW ),
			),
			'no_desc'    => array(
				__( 'Automatic description', 'bearius-seo-light' ),
				'notice',
				__( 'No custom description or excerpt; the opening of the content is used. Fine for most entries, worth writing for important ones.', 'bearius-seo-light' ),
			),
			'noindex'    => array(
				__( 'Hidden from search engines', 'bearius-seo-light' ),
				'info',
				__( 'Entries marked noindex. Listed so you can confirm each one is intentional.', 'bearius-seo-light' ),
			),
		);
	}

	/**
	 * Normalises the filter values.
	 *
	 * @param array $filters Raw filters.
	 * @return array
	 */
	public static function filters( $filters ) {
		$types = bearius_seo_supported_post_types();
		$type  = isset( $filters['post_type'] ) && in_array( $filters['post_type'], $types, true ) ? $filters['post_type'] : '';
		$range = isset( $filters['range'] ) ? absint( $filters['range'] ) : 0;

		return array(
			'post_type' => $type,
			'range'     => in_array( $range, array( 0, 30, 90, 365 ), true ) ? $range : 0,
			'indexable' => ! empty( $filters['indexable'] ),
		);
	}

	/**
	 * Builds the shared WHERE clause.
	 *
	 * @param array  $filters    Normalised filters.
	 * @param string $alias      Posts table alias.
	 * @param array  $only_types Restrict to these post types.
	 * @param bool   $indexable  Whether to drop noindex entries.
	 * @return string
	 */
	private static function where( $filters, $alias = 'p', $only_types = null, $indexable = null ) {
		global $wpdb;

		$types = $filters['post_type'] ? array( $filters['post_type'] ) : bearius_seo_supported_post_types();

		if ( null !== $only_types ) {
			$types = array_values( array_intersect( $types, $only_types ) );
		}

		if ( ! $types ) {
			return '1 = 0';
		}

		$in  = implode( ',', array_map( array( $wpdb, 'prepare' ), array_fill( 0, count( $types ), '%s' ), $types ) );
		$sql = "{$alias}.post_status = 'publish' AND {$alias}.post_type IN ($in)";

		if ( $filters['range'] ) {
			$sql .= $wpdb->prepare( " AND {$alias}.post_date >= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $filters['range'] * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		}

		$indexable = null === $indexable ? $filters['indexable'] : $indexable;
		if ( $indexable ) {
			$sql .= $wpdb->prepare( " AND {$alias}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1' )", '_bearius_seo_noindex' );
		}

		return $sql;
	}

	/**
	 * Returns the FROM and WHERE part for an issue type.
	 *
	 * @param string $type    Issue type.
	 * @param array  $filters Normalised filters.
	 * @return string|null
	 */
	private static function from_where( $type, $filters ) {
		global $wpdb;

		$posts = $wpdb->posts;
		$meta  = $wpdb->postmeta;

		switch ( $type ) {
			case 'dup_title':
				$inner = self::where( $filters, 'p2' );
				return "FROM {$posts} p WHERE " . self::where( $filters ) . " AND p.post_title <> '' AND p.post_title IN ( SELECT t.post_title FROM ( SELECT p2.post_title FROM {$posts} p2 WHERE {$inner} GROUP BY p2.post_title HAVING COUNT(*) > 1 ) t )";

			case 'dup_desc':
				$inner = self::where( $filters, 'p2' );
				return $wpdb->prepare( "FROM {$posts} p INNER JOIN {$meta} d ON d.post_id = p.ID AND d.meta_key = %s WHERE ", '_bearius_seo_desc' ) . self::where( $filters ) . $wpdb->prepare( " AND d.meta_value IN ( SELECT v.meta_value FROM ( SELECT d2.meta_value FROM {$meta} d2 INNER JOIN {$posts} p2 ON p2.ID = d2.post_id WHERE d2.meta_key = %s AND {$inner} GROUP BY d2.meta_value HAVING COUNT(*) > 1 ) v )", '_bearius_seo_desc' );

			case 'long_title':
				return $wpdb->prepare( "FROM {$posts} p LEFT JOIN {$meta} t ON t.post_id = p.ID AND t.meta_key = %s WHERE ", '_bearius_seo_title' ) . self::where( $filters ) . ' AND ( ( t.meta_value IS NOT NULL AND CHAR_LENGTH( t.meta_value ) > 60 ) OR ( t.meta_value IS NULL AND CHAR_LENGTH( p.post_title ) > 65 ) )';

			case 'no_desc':
				return $wpdb->prepare( "FROM {$posts} p LEFT JOIN {$meta} d ON d.post_id = p.ID AND d.meta_key = %s WHERE ", '_bearius_seo_desc' ) . self::where( $filters ) . " AND d.meta_id IS NULL AND p.post_excerpt = ''";

			case 'no_image':
				$types = array_filter(
					bearius_seo_supported_post_types(),
					function ( $post_type ) {
						return post_type_supports( $post_type, 'thumbnail' );
					}
				);
				return $wpdb->prepare( "FROM {$posts} p LEFT JOIN {$meta} th ON th.post_id = p.ID AND th.meta_key = %s LEFT JOIN {$meta} si ON si.post_id = p.ID AND si.meta_key = %s WHERE ", '_thumbnail_id', '_bearius_seo_image' ) . self::where( $filters, 'p', $types ) . ' AND th.meta_id IS NULL AND si.meta_id IS NULL';

			case 'thin':
				$types = array_filter(
					bearius_seo_supported_post_types(),
					function ( $post_type ) {
						return ! is_post_type_hierarchical( $post_type );
					}
				);
				return "FROM {$posts} p WHERE " . self::where( $filters, 'p', $types ) . ' AND CHAR_LENGTH( p.post_content ) < 700';

			case 'noindex':
				return $wpdb->prepare( "FROM {$posts} p INNER JOIN {$meta} n ON n.post_id = p.ID AND n.meta_key = %s AND n.meta_value = '1' WHERE ", '_bearius_seo_noindex' ) . self::where( $filters, 'p', null, false );
		}

		return null;
	}

	/**
	 * Finds entries with images missing alt text among the latest entries.
	 *
	 * @param array $filters Normalised filters.
	 * @return int[]
	 */
	private static function alt_ids( $filters ) {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT p.ID, p.post_content FROM ' . $wpdb->posts . ' p WHERE ' . self::where( $filters ) . " AND p.post_content LIKE '%<img%' ORDER BY p.post_date DESC LIMIT " . (int) self::ALT_WINDOW ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepared parts.

		$ids = array();
		foreach ( (array) $rows as $row ) {
			if ( preg_match( '/<img(?![^>]*\balt=)[^>]*>|<img[^>]*\balt=(""|\'\')[^>]*>/i', $row->post_content ) ) {
				$ids[] = (int) $row->ID;
			}
		}

		return $ids;
	}

	/**
	 * Finds entries whose featured image is narrower than 1200 pixels among the latest entries.
	 *
	 * @param array $filters Normalised filters.
	 * @return int[]
	 */
	private static function small_image_ids( $filters ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.ID, th.meta_value AS thumb FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' th ON th.post_id = p.ID AND th.meta_key = %s WHERE ' . self::where( $filters ) . ' ORDER BY p.post_date DESC LIMIT %d', '_thumbnail_id', self::ALT_WINDOW ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepared parts.

		if ( ! $rows ) {
			return array();
		}

		update_meta_cache( 'post', array_map( 'intval', wp_list_pluck( $rows, 'thumb' ) ) );

		$ids = array();
		foreach ( $rows as $row ) {
			$meta = wp_get_attachment_metadata( (int) $row->thumb );

			if ( is_array( $meta ) && ! empty( $meta['width'] ) && (int) $meta['width'] < 1200 ) {
				$ids[] = (int) $row->ID;
			}
		}

		return $ids;
	}

	/**
	 * Counts entries for an issue type, cached for ten minutes.
	 *
	 * @param string $type    Issue type.
	 * @param array  $filters Normalised filters.
	 * @return int
	 */
	public static function count( $type, $filters ) {
		$cache_key = 'bearius_seo_ca_' . md5( $type . wp_json_encode( $filters ) . get_option( Bearius_SEO_Sitemaps::VERSION_OPTION, 0 ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		if ( 'img_alt' === $type ) {
			$count = count( self::alt_ids( $filters ) );
		} elseif ( 'small_image' === $type ) {
			$count = count( self::small_image_ids( $filters ) );
		} else {
			$from  = self::from_where( $type, $filters );
			$count = $from ? (int) $wpdb->get_var( 'SELECT COUNT( DISTINCT p.ID ) ' . $from ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepared parts.
		}

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Returns entry IDs for an issue type, newest first.
	 *
	 * @param string $type    Issue type.
	 * @param array  $filters Normalised filters.
	 * @param int    $limit   Page size.
	 * @param int    $offset  Offset.
	 * @return int[]
	 */
	public static function ids( $type, $filters, $limit = 50, $offset = 0 ) {
		global $wpdb;

		if ( 'img_alt' === $type ) {
			return array_slice( self::alt_ids( $filters ), $offset, $limit );
		}

		if ( 'small_image' === $type ) {
			return array_slice( self::small_image_ids( $filters ), $offset, $limit );
		}

		$from = self::from_where( $type, $filters );

		if ( ! $from ) {
			return array();
		}

		return array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT p.ID, p.post_date ' . $from . ' ORDER BY p.post_date DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepared parts.
	}

	/**
	 * Returns counts for every type.
	 *
	 * @param array $filters Normalised filters.
	 * @return int[]
	 */
	public static function summary( $filters ) {
		$out = array();

		foreach ( array_keys( self::types() ) as $type ) {
			$out[ $type ] = self::count( $type, $filters );
		}

		return $out;
	}
}
