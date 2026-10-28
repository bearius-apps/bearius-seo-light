<?php
/**
 * Front end meta tags.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints description, canonical, Open Graph, X Card and verification tags.
 */
class Bearius_SEO_Frontend {

	/**
	 * Whether only the verification tags should be printed.
	 *
	 * @var bool
	 */
	private $verification_only;

	/**
	 * Sets the output mode.
	 *
	 * @param bool $verification_only Print only the verification tags.
	 */
	public function __construct( $verification_only = false ) {
		$this->verification_only = (bool) $verification_only;
	}

	/**
	 * Hooks the output.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->verification_only ) {
			add_action( 'wp_head', array( $this, 'render_verification' ), 1 );
			return;
		}

		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );

		if ( bearius_seo_get_setting( 'title_rewrite' ) ) {
			add_filter( 'document_title_parts', array( $this, 'filter_title' ), 20 );
		}

		if ( bearius_seo_get_setting( 'meta_canonical' ) ) {
			// WordPress prints its own canonical tag, which would duplicate ours.
			remove_action( 'wp_head', 'rel_canonical' );
		}

		add_action( 'wp_head', array( $this, 'render' ), 2 );
	}

	/**
	 * Adds noindex for content and terms flagged in the editor.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		$flagged = false;

		if ( is_singular() ) {
			$flagged = (bool) get_post_meta( get_queried_object_id(), '_bearius_seo_noindex', true );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$flagged = (bool) get_term_meta( get_queried_object_id(), '_bearius_seo_noindex', true );
		}

		if ( $flagged ) {
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
		$custom = '';

		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_bearius_seo_title', true );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$custom = bearius_seo_term_title( $term );
			}
		}

		if ( $custom ) {
			$parts['title'] = $custom;
		}

		return $parts;
	}

	/**
	 * Prints the search engine verification tags on the front page.
	 *
	 * @return void
	 */
	public function render_verification() {
		if ( ! is_front_page() || ! bearius_seo_module_active( 'verification' ) ) {
			return;
		}

		$tags = array(
			'verify_google'    => 'google-site-verification',
			'verify_bing'      => 'msvalidate.01',
			'verify_yandex'    => 'yandex-verification',
			'verify_seznam'    => 'seznam-wmt',
			'verify_naver'     => 'naver-site-verification',
			'verify_baidu'     => 'baidu-site-verification',
			'verify_pinterest' => 'p:domain_verify',
		);

		foreach ( $tags as $key => $name ) {
			$code = trim( (string) bearius_seo_get_setting( $key ) );

			if ( '' !== $code ) {
				printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $name ), esc_attr( $code ) );
			}
		}
	}

	/**
	 * Prints the tags.
	 *
	 * @return void
	 */
	public function render() {
		if ( bearius_seo_module_active( 'verification' ) ) {
			$this->render_verification();
		}

		if ( is_404() || is_search() ) {
			return;
		}

		$data = $this->collect();

		if ( empty( $data ) ) {
			return;
		}

		echo "\n<!-- Bearius SEO Light -->\n";

		if ( ! empty( $data['description'] ) && bearius_seo_get_setting( 'meta_description' ) ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		}

		if ( ! empty( $data['canonical'] ) && bearius_seo_get_setting( 'meta_canonical' ) ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $data['canonical'] ) );
		}

		if ( bearius_seo_get_setting( 'meta_og' ) ) {
			$this->render_open_graph( $data );
		}

		if ( bearius_seo_get_setting( 'meta_twitter' ) ) {
			$card = empty( $data['image']['url'] ) ? 'summary' : 'summary_large_image';
			printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $card ) );

			$handle = bearius_seo_get_setting( 'twitter_site' );
			if ( $handle ) {
				printf( '<meta name="twitter:site" content="@%s" />' . "\n", esc_attr( $handle ) );
			}
		}

		echo "\n";
	}

	/**
	 * Prints the Open Graph block.
	 *
	 * @param array $data Collected values.
	 * @return void
	 */
	private function render_open_graph( $data ) {
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( bearius_seo_org_name() ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( bearius_seo_og_locale() ) );

		if ( bearius_seo_get_setting( 'meta_og_alternate' ) ) {
			foreach ( Bearius_SEO_Languages::instance()->alternate_locales() as $locale ) {
				printf( '<meta property="og:locale:alternate" content="%s" />' . "\n", esc_attr( $locale ) );
			}
		}

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

		if ( is_home() ) {
			return $this->collect_posts_page();
		}

		return array();
	}

	/**
	 * Returns the default image array.
	 *
	 * @return array
	 */
	private function default_image() {
		$default = bearius_seo_get_setting( 'default_image' );

		if ( $default ) {
			return bearius_seo_image_by_url( $default );
		}

		return array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'alt'    => '',
		);
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

		return array(
			'type'        => 'website',
			'title'       => bearius_seo_org_name(),
			'description' => $description,
			'canonical'   => bearius_seo_current_canonical(),
			'image'       => $this->default_image(),
		);
	}

	/**
	 * Values for the page that lists the latest posts.
	 *
	 * @return array
	 */
	private function collect_posts_page() {
		$page_id = (int) get_option( 'page_for_posts' );

		return array(
			'type'        => 'website',
			'title'       => $page_id ? get_the_title( $page_id ) . ' - ' . bearius_seo_org_name() : bearius_seo_org_name(),
			'description' => $page_id ? bearius_seo_post_description( $page_id ) : '',
			'canonical'   => bearius_seo_current_canonical(),
			'image'       => $this->default_image(),
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
			'canonical'   => bearius_seo_current_canonical(),
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

		$title = bearius_seo_term_title( $term );

		return array(
			'type'        => 'website',
			'title'       => $title ? $title : $term->name . ' - ' . bearius_seo_org_name(),
			'description' => bearius_seo_term_description( $term ),
			'canonical'   => bearius_seo_current_canonical(),
			'image'       => $this->default_image(),
		);
	}
}
