<?php
/**
 * Front end meta tags.
 *
 * @package BeariusSEOLight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints description, canonical, Open Graph and X Card tags.
 */
class Bearius_SEO_Frontend {

	/**
	 * Hooks the output.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );

		if ( bearius_seo_get_setting( 'title_rewrite' ) ) {
			add_filter( 'document_title_parts', array( $this, 'filter_title' ), 20 );
		}

		if ( ! bearius_seo_get_setting( 'output_meta' ) ) {
			return;
		}

		// WordPress prints its own canonical tag, which would duplicate ours.
		remove_action( 'wp_head', 'rel_canonical' );

		add_action( 'wp_head', array( $this, 'render' ), 2 );
	}

	/**
	 * Adds noindex for content flagged in the meta box.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		if ( ! is_singular() ) {
			return $robots;
		}

		if ( get_post_meta( get_queried_object_id(), '_bearius_seo_noindex', true ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;

			unset( $robots['max-image-preview'] );
		}

		return $robots;
	}

	/**
	 * Replaces the browser title with the SEO title when one is set.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_title( $parts ) {
		if ( ! is_singular() ) {
			return $parts;
		}

		$custom = get_post_meta( get_queried_object_id(), '_bearius_seo_title', true );

		if ( $custom ) {
			$parts['title'] = $custom;
		}

		return $parts;
	}

	/**
	 * Prints the tags.
	 *
	 * @return void
	 */
	public function render() {
		if ( is_404() || is_search() ) {
			return;
		}

		$data = $this->collect();

		if ( empty( $data ) ) {
			return;
		}

		echo "\n<!-- Bearius SEO Light -->\n";

		if ( ! empty( $data['description'] ) ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		}

		if ( ! empty( $data['canonical'] ) ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $data['canonical'] ) );
		}

		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( bearius_seo_org_name() ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( bearius_seo_og_locale() ) );
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $data['type'] ) );

		if ( ! empty( $data['title'] ) ) {
			printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $data['title'] ) );
		}

		if ( ! empty( $data['description'] ) ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		}

		if ( ! empty( $data['canonical'] ) ) {
			printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $data['canonical'] ) );
		}

		if ( ! empty( $data['image']['url'] ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $data['image']['url'] ) );

			if ( ! empty( $data['image']['width'] ) && ! empty( $data['image']['height'] ) ) {
				printf( '<meta property="og:image:width" content="%s" />' . "\n", esc_attr( (int) $data['image']['width'] ) );
				printf( '<meta property="og:image:height" content="%s" />' . "\n", esc_attr( (int) $data['image']['height'] ) );
			}

			if ( ! empty( $data['image']['alt'] ) ) {
				printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $data['image']['alt'] ) );
			}
		}

		if ( ! empty( $data['published'] ) ) {
			printf( '<meta property="article:published_time" content="%s" />' . "\n", esc_attr( $data['published'] ) );
		}

		if ( ! empty( $data['modified'] ) ) {
			printf( '<meta property="article:modified_time" content="%s" />' . "\n", esc_attr( $data['modified'] ) );
		}

		if ( ! empty( $data['section'] ) ) {
			printf( '<meta property="article:section" content="%s" />' . "\n", esc_attr( $data['section'] ) );
		}

		$card = empty( $data['image']['url'] ) ? 'summary' : 'summary_large_image';
		printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $card ) );

		$handle = bearius_seo_get_setting( 'twitter_site' );
		if ( $handle ) {
			printf( '<meta name="twitter:site" content="@%s" />' . "\n", esc_attr( $handle ) );
		}

		echo "\n";
	}

	/**
	 * Gathers the values for the current request.
	 *
	 * @return array
	 */
	private function collect() {
		if ( is_front_page() ) {
			return $this->collect_front_page();
		}

		if ( is_singular() ) {
			return $this->collect_singular();
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return $this->collect_term();
		}

		return array();
	}

	/**
	 * Values for the front page.
	 *
	 * @return array
	 */
	private function collect_front_page() {
		$description = bearius_seo_get_setting( 'site_description' );

		if ( ! $description ) {
			$description = get_bloginfo( 'description' );
		}

		$image = array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'alt'    => '',
		);

		$default = bearius_seo_get_setting( 'default_image' );
		if ( $default ) {
			$image = bearius_seo_image_by_url( $default );
		}

		return array(
			'type'        => 'website',
			'title'       => bearius_seo_org_name(),
			'description' => $description,
			'canonical'   => home_url( '/' ),
			'image'       => $image,
		);
	}

	/**
	 * Values for a single post, page or custom post type entry.
	 *
	 * @return array
	 */
	private function collect_singular() {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$social = get_post_meta( $post->ID, '_bearius_seo_social', true );
		$title  = get_post_meta( $post->ID, '_bearius_seo_title', true );

		if ( ! $social ) {
			$social = $title ? $title : get_the_title( $post );
		}

		$is_post = 'post' === $post->post_type;

		$data = array(
			'type'        => $is_post ? 'article' : 'website',
			'title'       => $social,
			'description' => bearius_seo_post_description( $post->ID ),
			'canonical'   => get_permalink( $post ),
			'image'       => bearius_seo_post_image( $post->ID ),
		);

		if ( $is_post ) {
			$data['published'] = (string) get_the_date( 'c', $post );
			$data['modified']  = bearius_seo_modified_date( $post );

			$term = bearius_seo_primary_term( $post->ID );
			if ( $term ) {
				$data['section'] = $term->name;
			}
		}

		return $data;
	}

	/**
	 * Values for a term archive.
	 *
	 * @return array
	 */
	private function collect_term() {
		$term = get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		$description = wp_strip_all_tags( term_description( $term ) );

		if ( $description ) {
			$description = bearius_seo_trim_on_word( $description, 160 );
		}

		$image = array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'alt'    => '',
		);

		$default = bearius_seo_get_setting( 'default_image' );
		if ( $default ) {
			$image = bearius_seo_image_by_url( $default );
		}

		$link = get_term_link( $term );

		return array(
			'type'        => 'website',
			'title'       => $term->name . ' - ' . bearius_seo_org_name(),
			'description' => $description,
			'canonical'   => is_wp_error( $link ) ? '' : $link,
			'image'       => $image,
		);
	}
}
