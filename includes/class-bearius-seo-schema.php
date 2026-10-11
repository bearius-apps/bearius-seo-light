<?php
/**
 * Schema.org structured data.
 *
 * Everything is emitted as a single connected graph rather than several
 * separate blocks. Nodes reference each other by @id, which is what search
 * engines expect and keeps the markup free of repeated publisher data.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and prints the JSON-LD graph.
 */
class Bearius_SEO_Schema {

	/**
	 * Hooks the output.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'render' ), 3 );
	}

	/**
	 * Prints the graph.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_404() || is_search() ) {
			return;
		}

		$nodes = array();

		if ( bearius_seo_get_setting( 'schema_org' ) ) {
			$nodes[] = $this->organization();
		}

		if ( is_front_page() && bearius_seo_get_setting( 'schema_website' ) ) {
			$nodes[] = $this->website();
		}

		if ( is_singular() && bearius_seo_get_setting( 'schema_article' ) ) {
			$article = $this->article();
			if ( $article ) {
				$nodes[] = $article;
			}
		}

		if ( ( is_singular() || is_category() || is_tag() || is_tax() ) && bearius_seo_get_setting( 'schema_breadcrumbs' ) ) {
			$crumbs = $this->breadcrumbs();
			if ( $crumbs ) {
				$nodes[] = $crumbs;
			}
		}

		if ( ( is_category() || is_tag() || is_tax() ) && bearius_seo_get_setting( 'schema_archives' ) ) {
			$collection = $this->collection();
			if ( $collection ) {
				$nodes[] = $collection;
			}
		}

		if ( empty( $nodes ) ) {
			return;
		}

		/**
		 * Filters the structured data graph before output.
		 *
		 * @param array $nodes Graph nodes.
		 */
		$nodes = apply_filters( 'bearius_seo_schema_graph', $nodes );

		bearius_seo_print_jsonld(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( array_filter( $nodes ) ),
			)
		);
	}

	/**
	 * Returns the canonical id of the publisher node.
	 *
	 * @return string
	 */
	private function organization_id() {
		return home_url( '/' ) . '#organization';
	}

	/**
	 * Publisher node.
	 *
	 * @return array
	 */
	private function organization() {
		$node = array(
			'@type' => 'Organization',
			'@id'   => $this->organization_id(),
			'name'  => bearius_seo_org_name(),
			'url'   => home_url( '/' ),
		);

		$logo = bearius_seo_logo_url();
		if ( $logo ) {
			$node['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		return $node;
	}

	/**
	 * Site node with the internal search action.
	 *
	 * @return array
	 */
	private function website() {
		$home        = home_url( '/' );
		$description = bearius_seo_get_setting( 'site_description' );

		if ( ! $description ) {
			$description = get_bloginfo( 'description' );
		}

		$node = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => bearius_seo_org_name(),
			'inLanguage'      => bearius_seo_language(),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		if ( $description ) {
			$node['description'] = $description;
		}

		if ( bearius_seo_get_setting( 'schema_org' ) ) {
			$node['publisher'] = array( '@id' => $this->organization_id() );
		}

		return $node;
	}

	/**
	 * Article node for single entries.
	 *
	 * @return array|null
	 */
	private function article() {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( 'post' === $post->post_type ) {
			$type = bearius_seo_get_setting( 'news_mode' ) ? 'NewsArticle' : 'Article';
		} else {
			$type = 'WebPage';
		}

		$permalink = get_permalink( $post );
		$headline  = get_post_meta( $post->ID, '_bearius_seo_title', true );

		if ( ! $headline ) {
			$headline = get_the_title( $post );
		}

		// Google truncates headlines beyond 110 characters.
		$headline = bearius_seo_trim_on_word( $headline, 110 );

		$node = array(
			'@type'            => $type,
			'@id'              => $permalink . '#article',
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $permalink,
			),
			'headline'         => $headline,
			'datePublished'    => (string) get_the_date( 'c', $post ),
			'dateModified'     => bearius_seo_modified_date( $post ),
			'inLanguage'       => bearius_seo_language(),
		);

		$description = bearius_seo_post_description( $post->ID );
		if ( $description ) {
			$node['description'] = $description;
		}

		if ( bearius_seo_get_setting( 'schema_org' ) ) {
			$node['publisher'] = array( '@id' => $this->organization_id() );
			$node['author']    = array( '@id' => $this->organization_id() );
		}

		$image = bearius_seo_post_image( $post->ID );
		if ( ! empty( $image['url'] ) ) {
			$image_node = array(
				'@type' => 'ImageObject',
				'url'   => $image['url'],
			);

			if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
				$image_node['width']  = (int) $image['width'];
				$image_node['height'] = (int) $image['height'];
			}

			$node['image'] = $image_node;
		}

		if ( 'post' === $post->post_type ) {
			$node['wordCount'] = bearius_seo_count_words( $post->post_content );

			$term = bearius_seo_primary_term( $post->ID );
			if ( $term ) {
				$node['articleSection'] = $term->name;
			}
		}

		return $node;
	}

	/**
	 * Breadcrumb trail for single entries and term archives.
	 *
	 * @return array|null
	 */
	private function breadcrumbs() {
		$items = bearius_seo_breadcrumb_items();

		if ( count( $items ) < 2 ) {
			return null;
		}

		$elements = array();

		foreach ( $items as $index => $item ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		$last = end( $items );

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $last['url'] . '#breadcrumb',
			'itemListElement' => $elements,
		);
	}

	/**
	 * Item list for term archives.
	 *
	 * Cached briefly because it runs a secondary query on every archive view.
	 *
	 * @return array|null
	 */
	private function collection() {
		$term = get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$cache_key = 'bearius_seo_coll_' . $term->term_taxonomy_id;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'any',
				'posts_per_page'         => 20,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Term archive listing, cached in a transient.
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		$elements = array();

		$position = 0;

		foreach ( $query->posts as $post_id ) {
			$name = trim( (string) get_the_title( $post_id ) );
			$url  = get_permalink( $post_id );

			if ( '' === $name || ! $url ) {
				continue;
			}

			$position++;

			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $name,
				'url'      => $url,
			);
		}

		if ( empty( $elements ) ) {
			return null;
		}

		$node = array(
			'@type'      => 'CollectionPage',
			'@id'        => $link . '#collection',
			'url'        => $link,
			'name'       => $term->name . ' - ' . bearius_seo_org_name(),
			'inLanguage' => bearius_seo_language(),
			'mainEntity' => array(
				'@type'           => 'ItemList',
				'itemListElement' => $elements,
			),
		);

		$description = bearius_seo_term_description( $term );
		if ( $description ) {
			$node['description'] = $description;
		}

		if ( bearius_seo_get_setting( 'schema_org' ) ) {
			$node['publisher'] = array( '@id' => $this->organization_id() );
		}

		set_transient( $cache_key, $node, 10 * MINUTE_IN_SECONDS );

		return $node;
	}
}
