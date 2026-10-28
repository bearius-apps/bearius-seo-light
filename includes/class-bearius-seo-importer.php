<?php
/**
 * Importer for data saved by Yoast SEO, Rank Math, All in One SEO and by
 * themes or plugins that register their own source.
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
		$keys = array(
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

		foreach ( self::custom_sources() as $source => $info ) {
			$keys[ $source ] = $info['post'];
		}

		return $keys;
	}

	/**
	 * Returns the sources registered by themes and plugins.
	 *
	 * A theme or plugin that kept SEO data in its own custom fields adds
	 * itself to the importer through the bearius_seo_import_sources filter:
	 *
	 *     add_filter( 'bearius_seo_import_sources', function ( $sources ) {
	 *         $sources['my-theme'] = array(
	 *             'label' => 'My Theme',
	 *             'post'  => array( 'title' => 'seo_title', 'desc' => 'seo_desc', 'social' => 'og_title', 'image' => 'og_image' ),
	 *             'term'  => array( 'title' => 'my_term_title' ),
	 *         );
	 *         return $sources;
	 *     } );
	 *
	 * Keys are post meta and term meta keys. Missing keys are skipped. An
	 * image may be stored as a URL or as an attachment ID. Values are taken
	 * as plain text, without template variables.
	 *
	 * @return array Source key => array( label, post, term ).
	 */
	private static function custom_sources() {
		static $sources = null;

		if ( null !== $sources ) {
			return $sources;
		}

		$sources  = array();
		$fields   = array( 'title', 'desc', 'noindex', 'social', 'image' );
		$builtin  = array( 'yoast', 'rankmath', 'aioseo' );

		/**
		 * Filters the extra sources offered by the importer.
		 *
		 * @param array $sources Source key => array( 'label' => string, 'post' => array, 'term' => array ).
		 */
		$registered = (array) apply_filters( 'bearius_seo_import_sources', array() );

		foreach ( $registered as $key => $info ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || in_array( $key, $builtin, true ) || ! is_array( $info ) ) {
				continue;
			}

			$post = array();
			$term = array();

			foreach ( $fields as $field ) {
				$post[ $field ] = isset( $info['post'][ $field ] ) && is_string( $info['post'][ $field ] ) ? trim( $info['post'][ $field ] ) : '';
			}

			foreach ( array( 'title', 'desc', 'noindex' ) as $field ) {
				$term[ $field ] = isset( $info['term'][ $field ] ) && is_string( $info['term'][ $field ] ) ? trim( $info['term'][ $field ] ) : '';
			}

			if ( ! array_filter( $post ) && ! array_filter( $term ) ) {
				continue;
			}

			$sources[ $key ] = array(
				'label' => isset( $info['label'] ) && '' !== trim( (string) $info['label'] ) ? trim( (string) $info['label'] ) : $key,
				'post'  => $post,
				'term'  => $term,
			);
		}

		return $sources;
	}

	/**
	 * Tells whether a source was registered by a theme or plugin.
	 *
	 * @param string $source Source key.
	 * @return bool
	 */
	private static function is_custom( $source ) {
		return isset( self::custom_sources()[ $source ] );
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

		foreach ( self::custom_sources() as $source => $info ) {
			$labels[ $source ] = $info['label'];
		}

		foreach ( self::keys() as $source => $keys ) {
			$keys  = array_values( array_filter( $keys ) );
			$count = 0;

			if ( $keys ) {
				$in    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT pm.post_id ) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ($in) AND pm.meta_value <> '' AND p.post_type <> 'revision'", $keys ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			if ( self::is_custom( $source ) ) {
				$term_keys = array_values( array_filter( self::custom_sources()[ $source ]['term'] ) );

				if ( $term_keys ) {
					$in     = implode( ',', array_fill( 0, count( $term_keys ), '%s' ) );
					$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT term_id ) FROM {$wpdb->termmeta} WHERE meta_key IN ($in) AND meta_value <> ''", $term_keys ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}

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

		$counts = array(
			'title'   => 0,
			'desc'    => 0,
			'noindex' => 0,
			'social'  => 0,
			'image'   => 0,
		);

		// A source with term fields only has no posts to go through.
		if ( ! $keys ) {
			return array(
				'done'      => true,
				'last_id'   => $last_id,
				'processed' => 0,
				'counts'    => $counts,
			);
		}

		$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$args = array_merge( $keys, array( $last_id, self::BATCH ) );

		// Empty values are left out, so posts that only have empty custom
		// fields, as some field plugins save for every post, are not walked.
		// Revisions are skipped too: field plugins copy their values to every
		// revision, which would multiply the work without changing anything.
		$sql = "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ($in) AND pm.meta_value <> '' AND p.post_type <> 'revision' AND pm.post_id > %d";

		if ( 'aioseo' === $source && self::aioseo_table() ) {
			$table = self::aioseo_table();
			$sql   = "SELECT post_id FROM ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND meta_value <> '' UNION SELECT post_id FROM {$table} ) ids WHERE post_id > %d";
		}

		$order = 'aioseo' === $source && self::aioseo_table() ? 'post_id' : 'pm.post_id';
		$ids   = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql . " ORDER BY {$order} ASC LIMIT %d", $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		if ( self::is_custom( $source ) ) {
			return $this->read_custom_post( $keys, $post );
		}

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
	 * Reads the values of one post from a source registered by a theme or plugin.
	 *
	 * Values are plain text. Titles equal to the post title are skipped,
	 * since they change nothing. An image stored as an attachment ID is
	 * turned into its URL, the form the plugin keeps.
	 *
	 * @param array   $keys Field => meta key.
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private function read_custom_post( $keys, $post ) {
		$read = function ( $field ) use ( $keys, $post ) {
			if ( empty( $keys[ $field ] ) ) {
				return '';
			}

			$value = get_post_meta( $post->ID, $keys[ $field ], true );

			return is_scalar( $value ) ? trim( (string) $value ) : $value;
		};

		$title  = $read( 'title' );
		$social = $read( 'social' );
		$desc   = $read( 'desc' );
		$flag   = $read( 'noindex' );

		return array(
			'title'   => is_string( $title ) ? $this->same_as_title( $title, $post->post_title ) : '',
			'desc'    => is_string( $desc ) ? $desc : '',
			'noindex' => is_string( $flag ) && in_array( strtolower( $flag ), array( '1', 'on', 'yes', 'true', 'noindex' ), true ),
			'social'  => is_string( $social ) ? $this->same_as_title( $social, $post->post_title ) : '',
			'image'   => $this->image_url( $read( 'image' ) ),
		);
	}

	/**
	 * Returns an empty string when a title only repeats the original.
	 *
	 * @param string $title    Title from the source.
	 * @param string $original Post or term title.
	 * @return string
	 */
	private function same_as_title( $title, $original ) {
		$title = trim( preg_replace( '/\s+/u', ' ', (string) $title ) );

		return 0 === strcasecmp( $title, trim( wp_strip_all_tags( (string) $original ) ) ) ? '' : $title;
	}

	/**
	 * Turns a stored image into a URL.
	 *
	 * Accepts a URL, an attachment ID or an array with a url or ID key, the
	 * forms custom field plugins use.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function image_url( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
				return $value['url'];
			}

			$value = isset( $value['ID'] ) ? $value['ID'] : ( isset( $value['id'] ) ? $value['id'] : '' );
		}

		if ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );

			return $url ? (string) $url : '';
		}

		return is_string( $value ) && preg_match( '#^(https?:)?//#i', trim( $value ) ) ? trim( $value ) : '';
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
		} elseif ( self::is_custom( $source ) ) {
			global $wpdb;
			$term_keys = self::custom_sources()[ $source ]['term'];
			$list      = array_values( array_filter( $term_keys ) );

			if ( $list ) {
				$in  = implode( ',', array_fill( 0, count( $list ), '%s' ) );
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE meta_key IN ($in) AND meta_value <> ''", $list ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				foreach ( $ids as $term_id ) {
					$row = array();

					foreach ( array( 'title', 'desc', 'noindex' ) as $field ) {
						$value         = '' !== $term_keys[ $field ] ? get_term_meta( (int) $term_id, $term_keys[ $field ], true ) : '';
						$row[ $field ] = is_scalar( $value ) ? trim( (string) $value ) : '';
					}

					$row['noindex']         = in_array( strtolower( $row['noindex'] ), array( '1', 'on', 'yes', 'true', 'noindex' ), true );
					$rows[ (int) $term_id ] = $row;
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

			if ( self::is_custom( $source ) ) {
				$values = array(
					'_bearius_seo_title'   => $this->same_as_title( $data['title'], $term->name ),
					'_bearius_seo_desc'    => (string) $data['desc'],
					'_bearius_seo_noindex' => $data['noindex'] ? 1 : '',
				);
			} else {
				$values = array(
					'_bearius_seo_title'   => $this->clean_title( $this->replace_vars( $data['title'], null, $source, $term ), $term->name ),
					'_bearius_seo_desc'    => preg_match( '/^\s*(%%term_description%%|%term_description%)\s*$/i', (string) $data['desc'] ) ? '' : $this->replace_vars( $data['desc'], null, $source, $term ),
					'_bearius_seo_noindex' => $data['noindex'] ? 1 : '',
				);
			}

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
