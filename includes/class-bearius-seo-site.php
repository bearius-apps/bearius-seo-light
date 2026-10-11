<?php
/**
 * Feeds, tags, comments, archives and the Bearius link.
 *
 * Every feature here is off until the site owner picks it, and none of
 * them deletes data: tags, comments and feeds come back as soon as the
 * option is switched back.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the content and feed settings.
 */
class Bearius_SEO_Site {

	/**
	 * Hooks the features that are switched on.
	 *
	 * @return void
	 */
	public function register() {
		$feeds    = (string) bearius_seo_get_setting( 'rss_disable', 'none' );
		$comments = (string) bearius_seo_get_setting( 'comments_mode', 'on' );
		$tags     = (string) bearius_seo_get_setting( 'tags_mode', 'on' );

		// Feeds.
		if ( 'none' !== $feeds || 'off' === $comments || 'on' !== $tags ) {
			add_action( 'template_redirect', array( $this, 'redirect_feeds' ), 1 );
		}

		if ( in_array( $feeds, array( 'comments', 'extra', 'all' ), true ) || 'off' === $comments ) {
			add_filter( 'feed_links_show_comments_feed', '__return_false' );
		}

		if ( in_array( $feeds, array( 'extra', 'all' ), true ) ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}

		if ( 'all' === $feeds ) {
			remove_action( 'wp_head', 'feed_links', 2 );
		}

		if ( bearius_seo_get_setting( 'rss_image' ) ) {
			add_action( 'rss2_ns', array( $this, 'media_namespace' ) );
			add_action( 'rss2_item', array( $this, 'media_item' ) );
		}

		if ( bearius_seo_get_setting( 'rss_image' ) || bearius_seo_get_setting( 'rss_footer' ) ) {
			add_filter( 'the_content_feed', array( $this, 'feed_content' ), 20 );
			add_filter( 'the_excerpt_rss', array( $this, 'feed_content' ), 20 );
		}

		if ( (int) bearius_seo_get_setting( 'rss_delay' ) > 0 ) {
			add_filter( 'posts_where', array( $this, 'feed_delay' ), 10, 2 );
		}

		if ( bearius_seo_get_setting( 'rss_skip_noindex' ) || bearius_seo_get_setting( 'rss_exclude_cats' ) ) {
			add_action( 'pre_get_posts', array( $this, 'feed_exclusions' ) );
		}

		// Tags.
		if ( 'on' !== $tags ) {
			add_action( 'template_redirect', array( $this, 'gone_tags' ), 2 );
			add_filter( 'get_the_terms', array( $this, 'hide_tag_terms' ), 10, 3 );
			add_filter( 'wp_tag_cloud', array( $this, 'hide_tag_cloud' ), 10, 2 );
		}

		if ( 'off' === $tags ) {
			add_action( 'init', array( $this, 'detach_tags' ), 20 );
		}

		// Comments.
		if ( in_array( $comments, array( 'pings', 'closed', 'off' ), true ) ) {
			add_filter( 'pings_open', '__return_false', 20 );
			add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'remove_pingback_methods' ) );
		}

		if ( in_array( $comments, array( 'closed', 'off' ), true ) ) {
			add_filter( 'comments_open', '__return_false', 20 );
		}

		if ( 'off' === $comments ) {
			$this->switch_off_comments();
		}

		// Archives.
		if ( 'index' !== bearius_seo_get_setting( 'archive_author', 'index' ) || 'index' !== bearius_seo_get_setting( 'archive_date', 'index' ) ) {
			add_filter( 'wp_robots', array( $this, 'archive_robots' ) );
			add_action( 'template_redirect', array( $this, 'redirect_archives' ), 1 );
		}

		// Bearius link.
		if ( bearius_seo_get_setting( 'credit_link' ) ) {
			add_action( 'wp_footer', array( $this, 'credit' ), 100 );
		}
	}

	/**
	 * Redirects feeds that are switched off to the page they belong to.
	 *
	 * @return void
	 */
	public function redirect_feeds() {
		if ( ! is_feed() ) {
			return;
		}

		$level    = (string) bearius_seo_get_setting( 'rss_disable', 'none' );
		$comments = 'off' === bearius_seo_get_setting( 'comments_mode', 'on' );
		$extra    = is_category() || is_tag() || is_tax() || is_author() || is_search() || is_post_type_archive() || is_date();
		$target   = '';

		if ( is_comment_feed() && ( $comments || in_array( $level, array( 'comments', 'extra', 'all' ), true ) ) ) {
			$target = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' );
		} elseif ( $extra && in_array( $level, array( 'extra', 'all' ), true ) ) {
			$target = $this->archive_url();
		} elseif ( 'all' === $level ) {
			$target = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' );
		}

		if ( $target ) {
			wp_safe_redirect( $target, 301, 'Bearius SEO Light' );
			exit;
		}
	}

	/**
	 * Returns the address of the archive a feed belongs to.
	 *
	 * @return string
	 */
	private function archive_url() {
		$object = get_queried_object();

		if ( $object instanceof WP_Term ) {
			$link = get_term_link( $object );
			return is_wp_error( $link ) ? home_url( '/' ) : $link;
		}

		if ( $object instanceof WP_User ) {
			return get_author_posts_url( $object->ID );
		}

		if ( $object instanceof WP_Post_Type ) {
			return (string) get_post_type_archive_link( $object->name );
		}

		if ( is_search() ) {
			return get_search_link();
		}

		return home_url( '/' );
	}

	/**
	 * Declares the Media RSS namespace.
	 *
	 * @return void
	 */
	public function media_namespace() {
		echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
	}

	/**
	 * Adds the featured image as media:content.
	 *
	 * @return void
	 */
	public function media_item() {
		$image = $this->feed_image( get_the_ID() );

		if ( ! $image ) {
			return;
		}

		printf(
			"\t\t<media:content url=\"%s\" medium=\"image\"%s%s%s />\n",
			esc_url( $image['url'] ),
			$image['width'] ? ' width="' . (int) $image['width'] . '"' : '',
			$image['height'] ? ' height="' . (int) $image['height'] . '"' : '',
			$image['type'] ? ' type="' . esc_attr( $image['type'] ) . '"' : ''
		);
	}

	/**
	 * Returns the featured image of a post for feeds.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function feed_image( $post_id ) {
		$id = (int) get_post_thumbnail_id( $post_id );

		if ( ! $id ) {
			return null;
		}

		$src = wp_get_attachment_image_src( $id, 'large' );

		if ( ! $src ) {
			return null;
		}

		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'type'   => (string) get_post_mime_type( $id ),
			'alt'    => trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ),
		);
	}

	/**
	 * Adds the image before and the source line after the feed content.
	 *
	 * @param string $content Feed content.
	 * @return string
	 */
	public function feed_content( $content ) {
		if ( ! is_feed() ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( bearius_seo_get_setting( 'rss_image' ) ) {
			$image = $this->feed_image( $post_id );

			if ( $image && false === strpos( $content, $image['url'] ) ) {
				$content = '<p><img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $image['alt'] ) . '"' . ( $image['width'] ? ' width="' . (int) $image['width'] . '" height="' . (int) $image['height'] . '"' : '' ) . ' /></p>' . $content;
			}
		}

		if ( bearius_seo_get_setting( 'rss_footer' ) ) {
			$content .= '<p>' . sprintf(
				/* translators: 1: link to the post, 2: link to the site. */
				esc_html__( '%1$s appeared first on %2$s.', 'bearius-seo-light' ),
				'<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $post_id ) ) ) . '</a>',
				'<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( bearius_seo_org_name() ) . '</a>'
			) . '</p>';
		}

		return $content;
	}

	/**
	 * Holds new posts back from the main feed for the chosen time.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function feed_delay( $where, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_feed() || $query->is_comment_feed() ) {
			return $where;
		}

		global $wpdb;
		$minutes = min( 1440, max( 0, (int) bearius_seo_get_setting( 'rss_delay' ) ) );

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_date_gmt <= %s", gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Leaves noindex posts and chosen categories out of feeds.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function feed_exclusions( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_feed() || $query->is_comment_feed() ) {
			return;
		}

		if ( bearius_seo_get_setting( 'rss_skip_noindex' ) ) {
			$noindex = bearius_seo_noindex_post_ids();
			if ( $noindex ) {
				$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $noindex ) );
			}
		}

		$languages  = Bearius_SEO_Languages::instance();
		$categories = array();
		foreach ( array_filter( array_map( 'intval', (array) bearius_seo_get_setting( 'rss_exclude_cats', array() ) ) ) as $term_id ) {
			$categories = array_merge( $categories, array_values( $languages->term_translations( $term_id, 'category' ) ) );
		}

		if ( $categories ) {
			$query->set( 'category__not_in', array_merge( (array) $query->get( 'category__not_in' ), $categories ) );
		}
	}

	/**
	 * Answers tag archives with 410 Gone, so search engines drop them quickly.
	 *
	 * @return void
	 */
	public function gone_tags() {
		if ( ! is_tag() ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 410 );
		nocache_headers();
	}

	/**
	 * Hides tag lists in themes and blocks.
	 *
	 * @param WP_Term[]|false|WP_Error $terms    Terms.
	 * @param int                      $post_id  Post ID.
	 * @param string                   $taxonomy Taxonomy.
	 * @return WP_Term[]|false|WP_Error
	 */
	public function hide_tag_terms( $terms, $post_id, $taxonomy ) {
		if ( 'post_tag' === $taxonomy && ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Hides tag clouds.
	 *
	 * @param string|array $output Cloud markup.
	 * @param array        $args   Arguments.
	 * @return string|array
	 */
	public function hide_tag_cloud( $output, $args ) {
		$taxonomy = isset( $args['taxonomy'] ) ? (array) $args['taxonomy'] : array( 'post_tag' );

		return in_array( 'post_tag', $taxonomy, true ) ? '' : $output;
	}

	/**
	 * Removes tags from posts in the editor and the menu.
	 *
	 * @return void
	 */
	public function detach_tags() {
		unregister_taxonomy_for_object_type( 'post_tag', 'post' );
	}

	/**
	 * Removes the X-Pingback header.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Removes the pingback methods from XML-RPC.
	 *
	 * @param array $methods Methods.
	 * @return array
	 */
	public function remove_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );

		return $methods;
	}

	/**
	 * Switches comments off everywhere, without deleting them.
	 *
	 * @return void
	 */
	private function switch_off_comments() {
		add_filter( 'comments_array', array( $this, 'no_comments' ), 20 );
		add_filter( 'get_comments_number', '__return_zero', 20 );
		add_filter( 'comments_template', array( $this, 'empty_template' ), 20 );
		add_filter( 'render_block', array( $this, 'hide_comment_blocks' ), 10, 2 );
		add_action( 'init', array( $this, 'remove_comment_support' ), 100 );
		add_action( 'admin_menu', array( $this, 'remove_comment_menu' ), 100 );
		add_action( 'admin_init', array( $this, 'redirect_comment_screens' ) );
		add_action( 'admin_bar_menu', array( $this, 'remove_comment_bar' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_comment_widget' ), 100 );
	}

	/**
	 * Returns no comments on the front end.
	 *
	 * @param array $comments Comments.
	 * @return array
	 */
	public function no_comments( $comments ) {
		return is_admin() ? $comments : array();
	}

	/**
	 * Replaces the theme comments template with an empty one.
	 *
	 * @return string
	 */
	public function empty_template() {
		return BEARIUS_SEO_PATH . 'includes/comments-empty.php';
	}

	/**
	 * Removes comment blocks from block themes.
	 *
	 * @param string $content Block markup.
	 * @param array  $block   Block.
	 * @return string
	 */
	public function hide_comment_blocks( $content, $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( 0 === strpos( $name, 'core/comment' ) || 0 === strpos( $name, 'core/post-comments' ) || 'core/latest-comments' === $name ) {
			return '';
		}

		return $content;
	}

	/**
	 * Removes comment support from every post type.
	 *
	 * @return void
	 */
	public function remove_comment_support() {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Removes the Comments menu.
	 *
	 * @return void
	 */
	public function remove_comment_menu() {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Sends visits to the comment screen to the dashboard.
	 *
	 * @return void
	 */
	public function redirect_comment_screens() {
		global $pagenow;

		if ( 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Removes the comments item from the admin bar.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public function remove_comment_bar( $bar ) {
		$bar->remove_node( 'comments' );
	}

	/**
	 * Removes the recent comments dashboard box.
	 *
	 * @return void
	 */
	public function remove_comment_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Adds noindex to author and date archives when chosen.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function archive_robots( $robots ) {
		if ( ( is_author() && 'noindex' === bearius_seo_get_setting( 'archive_author' ) ) || ( is_date() && 'noindex' === bearius_seo_get_setting( 'archive_date' ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * Redirects author and date archives that are switched off.
	 *
	 * Runs before WordPress turns ?author=1 into a pretty address, so
	 * login names are not revealed either.
	 *
	 * @return void
	 */
	public function redirect_archives() {
		if ( is_feed() ) {
			return;
		}

		if ( ( is_author() && 'off' === bearius_seo_get_setting( 'archive_author' ) ) || ( is_date() && 'off' === bearius_seo_get_setting( 'archive_date' ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301, 'Bearius SEO Light' );
			exit;
		}
	}

	/**
	 * Prints the Bearius link once, however many Bearius plugins ask for it.
	 *
	 * All Bearius plugins fire the same action after printing their link
	 * and skip it when the action already ran.
	 *
	 * @return void
	 */
	public function credit() {
		if ( did_action( 'bearius_credit_printed' ) ) {
			return;
		}

		do_action( 'bearius_credit_printed' );

		printf(
			'<p class="bearius-credit" style="margin:1em 0;font-size:12px;text-align:center;opacity:.7">%s</p>',
			sprintf(
				/* translators: %s: link to Bearius. */
				esc_html__( 'Powered by %s', 'bearius-seo-light' ),
				'<a href="https://bearius.org" rel="nofollow">Bearius</a>'
			)
		);
	}
}
