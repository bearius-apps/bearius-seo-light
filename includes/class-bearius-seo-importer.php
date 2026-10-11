<?php
/**
 * Importer for data saved by Yoast SEO, Rank Math and All in One SEO.
 *
 * Source data is only read, never deleted, so the import can be repeated
 * and the previous plugin keeps working until it is deactivated.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies titles, descriptions, noindex flags and social data in batches.
 */
class Bearius_SEO_Importer {

	/**
	 * Entries per batch.
	 */
	const BATCH = 100;

	/**
	 * Hooks the AJAX handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_bearius_seo_import', array( $this, 'ajax' ) );
	}

	/**
	 * Returns the meta keys each source uses.
	 *
	 * @return array
	 */
	private static function keys() {
		return array(
			'yoast'    => array(
				'title'   => '_yoast_wpseo_title',
				'desc'    => '_yoast_wpseo_metadesc',
				'noindex' => '_yoast_wpseo_meta-robots-noindex',
				'social'  => '_yoast_wpseo_opengraph-title',
				'image'   => '_yoast_wpseo_opengraph-image',
			),
			'rankmath' => array(
				'title'   => 'rank_math_title',
				'desc'    => 'rank_math_description',
				'noindex' => 'rank_math_robots',
				'social'  => 'rank_math_facebook_title',
				'image'   => 'rank_math_facebook_image',
			),
			'aioseo'   => array(
				'title'   => '_aioseop_title',
				'desc'    => '_aioseop_description',
				'noindex' => '_aioseop_noindex',
				'social'  => '',
				'image'   => '',
			),
		);
	}

	/**
	 * Returns the All in One SEO table when it exists.
	 *
	 * @return string
	 */
	private static function aioseo_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ? $table : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Returns the sources found on this site with the number of entries.
	 *
	 * @return array Source key => array( label, count ).
	 */
	public static function sources() {
		global $wpdb;

		$labels = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
		);
		$out    = array();

		foreach ( self::keys() as $source => $keys ) {
			$keys = array_values( array_filter( $keys ) );
			$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT post_id ) FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND meta_value <> ''", $keys ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( 'aioseo' === $source && self::aioseo_table() ) {
				$table  = self::aioseo_table();
				$count += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE ( title IS NOT NULL AND title <> '' ) OR ( description IS NOT NULL AND description <> '' ) OR robots_noindex = 1 OR ( og_title IS NOT NULL AND og_title <> '' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			if ( $count > 0 ) {
				$out[ $source ] = array( $labels[ $source ], $count );
			}
		}

		return $out;
	}

	/**
	 * Handles one AJAX step.
	 *
	 * @return void
	 */
	public function ajax() {
		check_ajax_referer( 'bearius_seo_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'bearius-seo-light' ) ), 403 );
		}

		$source    = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$stage     = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : 'posts';
		$last_id   = isset( $_POST['last_id'] ) ? absint( $_POST['last_id'] ) : 0;
		$overwrite = ! empty( $_POST['overwrite'] );

		if ( ! isset( self::keys()[ $source ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown source.', 'bearius-seo-light' ) ), 400 );
		}

		if ( 'terms' === $stage ) {
			wp_send_json_success( $this->import_terms( $source, $overwrite ) );
		}

		wp_send_json_success( $this->import_posts( $source, $last_id, $overwrite ) );
	}

	/**
	 * Imports one batch of posts.
	 *
	 * @param string $source    Source key.
	 * @param int    $last_id   Last processed post ID.
	 * @param bool   $overwrite Replace values that already exist.
	 * @return array
	 */
	private function import_posts( $source, $last_id, $overwrite ) {
		global $wpdb;

		$keys = array_values( array_filter( self::keys()[ $source ] ) );
		$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$args = array_merge( $keys, array( $last_id, self::BATCH ) );

		$sql = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND post_id > %d";

		if ( 'aioseo' === $source && self::aioseo_table() ) {
			$table = self::aioseo_table();
			$sql   = "SELECT post_id FROM ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($in) UNION SELECT post_id FROM {$table} ) ids WHERE post_id > %d";
		}

		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql . ' ORDER BY post_id ASC LIMIT %d', $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		$counts = array(
			'title'   => 0,
			'desc'    => 0,
			'noindex' => 0,
			'social'  => 0,
			'image'   => 0,
		);

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || 'revision' === $post->post_type ) {
				continue;
			}

			$data = $this->read_post( $source, $post );

			foreach ( $data as $field => $value ) {
				if ( '' === $value || false === $value || null === $value ) {
					continue;
				}

				$meta_key = 'title' === $field ? '_bearius_seo_title' : ( 'desc' === $field ? '_bearius_seo_desc' : ( 'social' === $field ? '_bearius_seo_social' : ( 'image' === $field ? '_bearius_seo_image' : '_bearius_seo_noindex' ) ) );

				if ( ! $overwrite && '' !== (string) get_post_meta( $post_id, $meta_key, true ) ) {
					continue;
				}

				if ( 'image' === $field ) {
					$value = esc_url_raw( $value );
				} elseif ( 'noindex' === $field ) {
					$value = 1;
				} else {
					$value = sanitize_text_field( $value );
				}

				update_post_meta( $post_id, $meta_key, $value );
				$counts[ $field ]++;
			}
		}

		return array(
			'done'      => count( $ids ) < self::BATCH,
			'last_id'   => $ids ? (int) end( $ids ) : $last_id,
			'processed' => count( $ids ),
			'counts'    => $counts,
		);
	}

	/**
	 * Reads the values of one post from the source.
	 *
	 * @param string  $source Source key.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	private function read_post( $source, $post ) {
		$keys = self::keys()[ $source ];
		$raw  = array();

		foreach ( $keys as $field => $key ) {
			$value         = $key ? get_post_meta( $post->ID, $key, true ) : '';
			$raw[ $field ] = is_scalar( $value ) ? (string) $value : '';
		}

		// Rank Math stores robots directives as an array, which WordPress
		// already unserializes. Nothing is unserialized here a second time.
		$robots = 'rankmath' === $source ? get_post_meta( $post->ID, 'rank_math_robots', true ) : null;

		if ( 'aioseo' === $source && self::aioseo_table() ) {
			global $wpdb;
			$table = self::aioseo_table();
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT title, description, robots_default, robots_noindex, og_title, og_image_custom_url FROM {$table} WHERE post_id = %d", $post->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $row ) {
				$raw['title']   = $raw['title'] ? $raw['title'] : (string) $row['title'];
				$raw['desc']    = $raw['desc'] ? $raw['desc'] : (string) $row['description'];
				$raw['social']  = (string) $row['og_title'];
				$raw['image']   = (string) $row['og_image_custom_url'];
				$raw['noindex'] = ( empty( $row['robots_default'] ) && ! empty( $row['robots_noindex'] ) ) ? '1' : $raw['noindex'];
			}
		}

		$noindex = false;
		if ( 'rankmath' === $source ) {
			$noindex = is_array( $robots ) && in_array( 'noindex', $robots, true );
		} else {
			$noindex = in_array( strtolower( $raw['noindex'] ), array( '1', 'on', 'noindex' ), true );
		}

		return array(
			'title'   => $this->clean_title( $this->replace_vars( $raw['title'], $post, $source ), $post->post_title ),
			'desc'    => $this->clean_desc( $raw['desc'], $post, $source ),
			'noindex' => $noindex,
			'social'  => $this->clean_title( $this->replace_vars( $raw['social'], $post, $source ), $post->post_title ),
			'image'   => $raw['image'],
		);
	}

	/**
	 * Replaces template variables with the values they stand for.
	 *
	 * @param string       $text   Template.
	 * @param WP_Post|null $post   Post, null for terms.
	 * @param string       $source Source key.
	 * @param WP_Term|null $term   Term for archive templates.
	 * @return string
	 */
	private function replace_vars( $text, $post, $source, $term = null ) {
		$text = (string) $text;

		if ( '' === trim( $text ) ) {
			return '';
		}

		$primary = $post ? bearius_seo_primary_term( $post->ID ) : null;
		$values  = array(
			'title'            => $post ? $post->post_title : ( $term ? $term->name : '' ),
			'sitename'         => '',
			'sep'              => '',
			'excerpt'          => '',
			'excerpt_only'     => '',
			'category'         => $primary ? $primary->name : ( $term ? $term->name : '' ),
			'primary_category' => $primary ? $primary->name : '',
			'term_title'       => $term ? $term->name : '',
			'currentyear'      => gmdate( 'Y' ),
			'page'             => '',
			'sitedesc'         => get_bloginfo( 'description' ),
		);

		if ( 'yoast' === $source ) {
			$text = preg_replace_callback(
				'/%%([a-z_]+)%%/i',
				function ( $match ) use ( $values ) {
					$name = strtolower( $match[1] );
					return isset( $values[ $name ] ) ? $values[ $name ] : '';
				},
				$text
			);
		} elseif ( 'rankmath' === $source ) {
			$text = preg_replace_callback(
				'/%([a-z_]+)%/i',
				function ( $match ) use ( $values ) {
					$name = strtolower( $match[1] );
					return isset( $values[ $name ] ) ? $values[ $name ] : '';
				},
				$text
			);
		} else {
			$map  = array(
				'post_title'     => $values['title'],
				'taxonomy_title' => $values['category'],
				'site_title'     => '',
				'separator_sa'   => '',
				'post_excerpt'   => '',
				'current_year'   => $values['currentyear'],
				'tagline'        => $values['sitedesc'],
				'page_number'    => '',
			);
			$text = preg_replace_callback(
				'/#([a-z_]+)/i',
				function ( $match ) use ( $map ) {
					$name = strtolower( $match[1] );
					return isset( $map[ $name ] ) ? $map[ $name ] : '';
				},
				$text
			);
		}

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Drops separators left by removed variables and titles that equal the default.
	 *
	 * The site name is appended by WordPress, so keeping it would print it twice.
	 *
	 * @param string $title    Converted title.
	 * @param string $original Post or term title.
	 * @return string
	 */
	private function clean_title( $title, $original ) {
		$site  = trim( (string) get_bloginfo( 'name' ) );
		$title = (string) $title;

		if ( '' !== $site ) {
			$title = str_ireplace( $site, '', $title );
		}

		$title = trim( preg_replace( '/^[\s\-|–—·•:]+|[\s\-|–—·•:]+$/u', '', $title ) );

		if ( '' === $title || 0 === strcasecmp( $title, trim( wp_strip_all_tags( $original ) ) ) ) {
			return '';
		}

		return $title;
	}

	/**
	 * Converts a description, skipping pure excerpt templates.
	 *
	 * @param string  $text   Template.
	 * @param WP_Post $post   Post.
	 * @param string  $source Source key.
	 * @return string
	 */
	private function clean_desc( $text, $post, $source ) {
		if ( preg_match( '/^\s*(%%excerpt(_only)?%%|%excerpt%|#post_excerpt)\s*$/i', (string) $text ) ) {
			return '';
		}

		return $this->replace_vars( $text, $post, $source );
	}

	/**
	 * Imports term titles, descriptions and noindex flags.
	 *
	 * @param string $source    Source key.
	 * @param bool   $overwrite Replace values that already exist.
	 * @return array
	 */
	private function import_terms( $source, $overwrite ) {
		$counts = array(
			'title'   => 0,
			'desc'    => 0,
			'noindex' => 0,
			'social'  => 0,
			'image'   => 0,
		);
		$rows   = array();

		if ( 'yoast' === $source ) {
			foreach ( (array) get_option( 'wpseo_taxonomy_meta', array() ) as $terms ) {
				foreach ( (array) $terms as $term_id => $meta ) {
					$rows[ (int) $term_id ] = array(
						'title'   => isset( $meta['wpseo_title'] ) ? $meta['wpseo_title'] : '',
						'desc'    => isset( $meta['wpseo_desc'] ) ? $meta['wpseo_desc'] : '',
						'noindex' => isset( $meta['wpseo_noindex'] ) && 'noindex' === $meta['wpseo_noindex'],
					);
				}
			}
		} elseif ( 'rankmath' === $source ) {
			global $wpdb;
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE meta_key IN ( %s, %s, %s )", 'rank_math_title', 'rank_math_description', 'rank_math_robots' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $ids as $term_id ) {
				$robots                 = get_term_meta( (int) $term_id, 'rank_math_robots', true );
				$rows[ (int) $term_id ] = array(
					'title'   => (string) get_term_meta( (int) $term_id, 'rank_math_title', true ),
					'desc'    => (string) get_term_meta( (int) $term_id, 'rank_math_description', true ),
					'noindex' => is_array( $robots ) && in_array( 'noindex', $robots, true ),
				);
			}
		}

		foreach ( $rows as $term_id => $data ) {
			$term = get_term( $term_id );

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$values = array(
				'_bearius_seo_title'   => $this->clean_title( $this->replace_vars( $data['title'], null, $source, $term ), $term->name ),
				'_bearius_seo_desc'    => preg_match( '/^\s*(%%term_description%%|%term_description%)\s*$/i', (string) $data['desc'] ) ? '' : $this->replace_vars( $data['desc'], null, $source, $term ),
				'_bearius_seo_noindex' => $data['noindex'] ? 1 : '',
			);

			foreach ( $values as $key => $value ) {
				if ( '' === $value || ( ! $overwrite && '' !== (string) get_term_meta( $term_id, $key, true ) ) ) {
					continue;
				}

				update_term_meta( $term_id, $key, '_bearius_seo_noindex' === $key ? 1 : sanitize_text_field( $value ) );
				$counts[ '_bearius_seo_title' === $key ? 'title' : ( '_bearius_seo_desc' === $key ? 'desc' : 'noindex' ) ]++;
			}
		}

		return array(
			'done'      => true,
			'last_id'   => 0,
			'processed' => count( $rows ),
			'counts'    => $counts,
		);
	}
}
