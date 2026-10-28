<?php
/**
 * Per post SEO fields.
 *
 * Values live in native post meta, so nothing here depends on a custom
 * fields plugin and the data stays readable if the plugin is removed.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta box with the per post overrides and live previews.
 */
class Bearius_SEO_Metabox {

	/**
	 * Meta keys handled by the box.
	 *
	 * @var array
	 */
	private $fields = array(
		'_bearius_seo_title'        => 'text',
		'_bearius_seo_desc'         => 'textarea',
		'_bearius_seo_social'       => 'text',
		'_bearius_seo_image'        => 'url',
		'_bearius_seo_noindex'      => 'checkbox',
		'_bearius_seo_news_exclude' => 'checkbox',
	);

	/**
	 * Hooks the meta box and its save handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the meta keys so they behave predictably and stay private.
	 *
	 * @return void
	 */
	public function register_meta() {
		foreach ( bearius_seo_supported_post_types() as $post_type ) {
			foreach ( $this->fields as $key => $type ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => 'checkbox' === $type ? 'boolean' : 'string',
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => 'checkbox' === $type ? 'rest_sanitize_boolean' : 'sanitize_text_field',
						'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Adds the meta box to every supported post type.
	 *
	 * @return void
	 */
	public function add_box() {
		foreach ( bearius_seo_supported_post_types() as $post_type ) {
			add_meta_box(
				'bearius-seo-box',
				$this->box_title(),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Returns the meta box title with the brand mark in front of it.
	 *
	 * @return string
	 */
	private function box_title() {
		$icon = '<svg class="bearius-seo-mark" width="20" height="20" viewBox="-4 -4 56 56" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="11.5" cy="22" rx="5.2" ry="6.6" transform="rotate(-22 11.5 22)"/><ellipse cx="19.8" cy="13.8" rx="5" ry="6.9" transform="rotate(-8 19.8 13.8)"/><ellipse cx="29.2" cy="13.8" rx="5" ry="6.9" transform="rotate(8 29.2 13.8)"/><ellipse cx="37.5" cy="22" rx="5.2" ry="6.6" transform="rotate(22 37.5 22)"/><path d="M24.5 23.5c5.6 0 11.2 4.6 11.2 10.1c0 5.1 -4.6 8.2 -11.2 8.2c-6.6 0 -11.2 -3.1 -11.2 -8.2c0 -5.5 5.6 -10.1 11.2 -10.1z"/></svg>';

		return $icon . '<span>' . esc_html__( 'Bearius SEO', 'bearius-seo-light' ) . '</span>';
	}

	/**
	 * Loads styles and scripts on post edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, bearius_seo_supported_post_types(), true ) ) {
			return;
		}

		$post_id = get_the_ID();

		wp_enqueue_media();

		wp_enqueue_style(
			'bearius-seo-admin',
			BEARIUS_SEO_URL . 'assets/css/admin.css',
			array(),
			BEARIUS_SEO_VERSION
		);

		wp_enqueue_script(
			'bearius-seo-admin',
			BEARIUS_SEO_URL . 'assets/js/admin.js',
			array(),
			BEARIUS_SEO_VERSION,
			true
		);

		wp_localize_script(
			'bearius-seo-admin',
			'beariusSeoL10n',
			array(
				'frameTitle'  => __( 'Select an image', 'bearius-seo-light' ),
				'frameButton' => __( 'Use this image', 'bearius-seo-light' ),
			)
		);

		wp_localize_script(
			'bearius-seo-admin',
			'beariusSeoPreview',
			array(
				'fallbackTitle' => ( $post_id && 'auto-draft' !== get_post_status( $post_id ) ) ? get_the_title( $post_id ) : '',
				'fallbackDesc'  => $post_id ? bearius_seo_post_description( $post_id ) : '',
				'fallbackImage' => $post_id ? $this->preview_fallback_image( $post_id ) : '',
				'untitled'      => __( '(no title yet)', 'bearius-seo-light' ),
				'noDescription' => __( 'No description available. Search engines will pick their own text.', 'bearius-seo-light' ),
				'noImage'       => __( 'No image', 'bearius-seo-light' ),
				'checks'        => array(
					/* translators: %d: number of characters. */
					'titleOk'    => __( 'SEO title fits in search results (%d characters)', 'bearius-seo-light' ),
					/* translators: %d: number of characters. */
					'titleLong'  => __( 'SEO title may be cut off (%d characters, aim for up to 60)', 'bearius-seo-light' ),
					/* translators: %d: number of characters. */
					'titleShort' => __( 'SEO title is short (%d characters)', 'bearius-seo-light' ),
					/* translators: %d: number of characters. */
					'descOk'     => __( 'Description has a good length (%d characters)', 'bearius-seo-light' ),
					/* translators: %d: number of characters. */
					'descLong'   => __( 'Description may be cut off (%d characters, aim for up to 160)', 'bearius-seo-light' ),
					/* translators: %d: number of characters. */
					'descShort'  => __( 'Description is short (%d characters)', 'bearius-seo-light' ),
					'descNone'   => __( 'No description: search engines will pick their own text', 'bearius-seo-light' ),
					'indexOk'    => __( 'Visible to search engines', 'bearius-seo-light' ),
					'indexNo'    => __( 'Hidden from search engines (noindex)', 'bearius-seo-light' ),
					/* translators: 1: checks passed, 2: all checks. */
					'score'      => __( '%1$d of %2$d fine', 'bearius-seo-light' ),
				),
			)
		);
	}

	/**
	 * Builds the checks that depend on the saved post.
	 *
	 * Titles, the description and noindex are checked live in the browser.
	 *
	 * @param WP_Post $post Post.
	 * @return array[] State (ok, warn or info) and text.
	 */
	private function saved_checks( $post ) {
		$checks   = array();
		$thumb_id = (int) get_post_thumbnail_id( $post );

		if ( $thumb_id ) {
			$meta  = wp_get_attachment_metadata( $thumb_id );
			$width = is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;

			if ( $width >= 1200 ) {
				/* translators: %d: width in pixels. */
				$checks['image'] = array( 'ok', sprintf( __( 'Featured image is large enough for Google Discover (%d px)', 'bearius-seo-light' ), $width ) );
			} else {
				/* translators: %d: width in pixels. */
				$checks['image'] = array( 'warn', sprintf( __( 'Featured image is narrower than 1200 px (%d px), too small for large previews in Discover', 'bearius-seo-light' ), $width ) );
			}

			$alt             = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
			$checks['alt'] = '' !== $alt
				? array( 'ok', __( 'Featured image has alternative text', 'bearius-seo-light' ) )
				: array( 'warn', __( 'Featured image has no alternative text', 'bearius-seo-light' ) );
		} else {
			$checks['image'] = '' !== trim( (string) bearius_seo_get_setting( 'default_image' ) )
				? array( 'info', __( 'No featured image: the default social image is used', 'bearius-seo-light' ) )
				: array( 'warn', __( 'No featured image: shares will have no picture', 'bearius-seo-light' ) );
		}

		// Counted by spaces, which also works for words with diacritics.
		$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ), -1, PREG_SPLIT_NO_EMPTY ) );

		// A new, empty draft has nothing to measure yet.
		if ( $words > 0 ) {
			/* translators: %d: number of words. */
			$checks['words'] = $words >= 300 ? array( 'ok', sprintf( __( '%d words of content', 'bearius-seo-light' ), $words ) ) : array( 'warn', sprintf( __( 'Only %d words of content', 'bearius-seo-light' ), $words ) );

			$host  = preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' );
			$links = preg_match_all( '#<a\s[^>]*href=["\'](?:https?://' . $host . '|/(?!/))[^"\']*["\']#i', $post->post_content );

			/* translators: %d: number of links. */
			$checks['links'] = $links ? array( 'ok', sprintf( _n( '%d link to another page of the site', '%d links to other pages of the site', $links, 'bearius-seo-light' ), $links ) ) : array( 'warn', __( 'No links to other pages of the site', 'bearius-seo-light' ) );
		}

		if ( bearius_seo_module_active( 'news' ) && in_array( $post->post_type, (array) bearius_seo_get_setting( 'news_post_types', array() ), true ) ) {
			$checks['news'] = get_post_meta( $post->ID, '_bearius_seo_news_exclude', true )
				? array( 'info', __( 'Left out of Google News by choice', 'bearius-seo-light' ) )
				: array( 'ok', __( 'Listed in the Google News sitemap for two days after publishing', 'bearius-seo-light' ) );
		}

		if ( bearius_seo_module_active( 'schema' ) && bearius_seo_get_setting( 'schema_article' ) ) {
			$type = 'post' === $post->post_type ? ( bearius_seo_get_setting( 'news_mode' ) ? 'NewsArticle' : 'Article' ) : 'WebPage';
			/* translators: %s: Schema.org type. */
			$checks['schema'] = array( 'info', sprintf( __( 'Structured data: %s', 'bearius-seo-light' ), $type ) );
		}

		return $checks;
	}

	/**
	 * Returns the image the preview falls back to when no override is set.
	 *
	 * The custom field is skipped on purpose, because the live preview reads
	 * that value straight from the input.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function preview_fallback_image( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			$url = get_the_post_thumbnail_url( $post_id, 'large' );

			if ( $url ) {
				return $url;
			}
		}

		return (string) bearius_seo_get_setting( 'default_image' );
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( 'bearius_seo_save', 'bearius_seo_nonce' );

		$values = array(
			'title'        => (string) get_post_meta( $post->ID, '_bearius_seo_title', true ),
			'desc'         => (string) get_post_meta( $post->ID, '_bearius_seo_desc', true ),
			'social'       => (string) get_post_meta( $post->ID, '_bearius_seo_social', true ),
			'image'        => (string) get_post_meta( $post->ID, '_bearius_seo_image', true ),
			'noindex'      => (int) get_post_meta( $post->ID, '_bearius_seo_noindex', true ),
			'news_exclude' => (int) get_post_meta( $post->ID, '_bearius_seo_news_exclude', true ),
		);

		/**
		 * Filters the values shown in the SEO box.
		 *
		 * Lets a theme or plugin show values it stored itself, for example
		 * in custom fields from before the switch, so the box shows what the
		 * site really uses. Whatever the box shows is saved into the plugin
		 * fields on the next save. Only the display is filtered, the values
		 * stored by the plugin are never changed here.
		 *
		 * @param array   $values Keys title, desc, social, image (URL), noindex and news_exclude.
		 * @param WP_Post $post   Post being edited.
		 */
		$values = array_merge( $values, (array) apply_filters( 'bearius_seo_field_values', $values, $post ) );

		$title   = (string) $values['title'];
		$desc    = (string) $values['desc'];
		$social  = (string) $values['social'];
		$image   = (string) $values['image'];
		$noindex = (int) $values['noindex'];
		$no_news = (int) $values['news_exclude'];
		$in_news = bearius_seo_module_active( 'news' ) && in_array( $post->post_type, (array) bearius_seo_get_setting( 'news_post_types', array() ), true );

		$permalink    = get_permalink( $post );
		$domain       = wp_parse_url( home_url(), PHP_URL_HOST );
		$thumb        = $image ? $image : $this->preview_fallback_image( $post->ID );
		$auto_desc    = bearius_seo_post_description( $post->ID );
		$auto_title   = get_the_title( $post );
		?>
		<div class="bearius-seo-panel" id="bearius-seo-panel">

			<div class="bearius-seo-previews">
				<div class="bearius-seo-preview bearius-seo-preview--checks" data-bs-checks>
					<div class="bearius-seo-checks__head">
						<span class="bearius-seo-preview__label"><?php esc_html_e( 'Checks', 'bearius-seo-light' ); ?></span>
						<span class="bearius-seo-checks__score" data-bs-checks-score></span>
					</div>
					<div class="bearius-seo-checks">
						<ul class="bearius-seo-checks__list">
							<li data-check="title"><span class="bearius-seo-checks__dot" aria-hidden="true"></span><span data-check-text></span></li>
							<li data-check="desc"><span class="bearius-seo-checks__dot" aria-hidden="true"></span><span data-check-text></span></li>
							<li data-check="index"><span class="bearius-seo-checks__dot" aria-hidden="true"></span><span data-check-text></span></li>
							<?php foreach ( $this->saved_checks( $post ) as $key => $check ) : ?>
								<li data-check="<?php echo esc_attr( $key ); ?>" class="is-<?php echo esc_attr( $check[0] ); ?>"><span class="bearius-seo-checks__dot" aria-hidden="true"></span><span><?php echo esc_html( $check[1] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
						<p class="bearius-seo-checks__note"><?php esc_html_e( 'Image, length and links are checked for the saved version.', 'bearius-seo-light' ); ?></p>
					</div>
				</div>

				<div class="bearius-seo-preview bearius-seo-preview--looks">
					<div class="bearius-seo-look bearius-seo-look--serp">
						<span class="bearius-seo-preview__label"><?php esc_html_e( 'Search result', 'bearius-seo-light' ); ?></span>
						<div class="bearius-seo-preview__card">
							<span class="bearius-seo-serp__url"><?php echo esc_html( urldecode( (string) $permalink ) ); ?></span>
							<span class="bearius-seo-serp__title"></span>
							<span class="bearius-seo-serp__desc"></span>
						</div>
					</div>

					<div class="bearius-seo-look bearius-seo-look--social">
						<span class="bearius-seo-preview__label"><?php esc_html_e( 'When shared', 'bearius-seo-light' ); ?></span>
						<div class="bearius-seo-preview__card bearius-seo-social__card">
							<?php
							// A real image, not a CSS background: dark modes of the
							// dashboard that invert the screen turn images back to
							// normal colours, but not backgrounds. admin.js then
							// cancels such a filter exactly, see trueColor().
							?>
							<span class="bearius-seo-social__image<?php echo esc_attr( $thumb ? '' : ' is-empty' ); ?>">
								<img class="bearius-seo-social__img" alt="" decoding="async" data-bs-true-color<?php echo $thumb ? ' src="' . esc_url( $thumb ) . '"' : ' hidden'; ?> />
								<span class="bearius-seo-social__none"><?php esc_html_e( 'No image', 'bearius-seo-light' ); ?></span>
							</span>
							<span class="bearius-seo-social__body">
								<span class="bearius-seo-social__domain"><?php echo esc_html( (string) $domain ); ?></span>
								<span class="bearius-seo-social__title"></span>
								<span class="bearius-seo-social__desc"></span>
							</span>
						</div>
					</div>
				</div>
			</div>

			<div class="bearius-seo-fields">
				<?php
				/**
				 * Prints add-on tools at the top of the SEO fields.
				 *
				 * @param WP_Post $post Post being edited.
				 */
				do_action( 'bearius_seo_metabox_tools', $post );
				?>

				<div class="bearius-seo-fields__grid">
				<div class="bearius-seo-fields__col">
				<div class="bearius-seo-field">
					<div class="bearius-seo-field__head">
						<label class="bearius-seo-field__label" for="bearius-seo-title"><?php esc_html_e( 'SEO title', 'bearius-seo-light' ); ?></label>
						<span class="bearius-seo-counter" data-counter="title">0 / 60</span>
					</div>
					<input type="text" id="bearius-seo-title" name="bearius_seo_title"
						value="<?php echo esc_attr( $title ); ?>"
						placeholder="<?php echo esc_attr( $auto_title ); ?>" />
					<span class="bearius-seo-meter"><span class="bearius-seo-meter__bar" data-meter="title"></span></span>
					<span class="bearius-seo-help"><?php esc_html_e( 'Overrides the browser title and the headline in structured data. Leave empty and the post title is used, exactly as shown in the preview above.', 'bearius-seo-light' ); ?></span>
				</div>

				<div class="bearius-seo-field">
					<div class="bearius-seo-field__head">
						<label class="bearius-seo-field__label" for="bearius-seo-desc"><?php esc_html_e( 'Meta description', 'bearius-seo-light' ); ?></label>
						<span class="bearius-seo-counter" data-counter="desc">0 / 155</span>
					</div>
					<textarea id="bearius-seo-desc" name="bearius_seo_desc" rows="3"
						placeholder="<?php echo esc_attr( $auto_desc ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
					<span class="bearius-seo-meter"><span class="bearius-seo-meter__bar" data-meter="desc"></span></span>
					<span class="bearius-seo-help"><?php esc_html_e( 'Leave empty and the excerpt is used, then the opening of the content. The grey text shows what will be sent.', 'bearius-seo-light' ); ?></span>
				</div>
				</div>

				<div class="bearius-seo-fields__col">
				<div class="bearius-seo-field">
					<div class="bearius-seo-field__head">
						<label class="bearius-seo-field__label" for="bearius-seo-social"><?php esc_html_e( 'Social title', 'bearius-seo-light' ); ?></label>
					</div>
					<input type="text" id="bearius-seo-social" name="bearius_seo_social"
						value="<?php echo esc_attr( $social ); ?>"
						placeholder="<?php echo esc_attr( $title ? $title : $auto_title ); ?>" />
					<span class="bearius-seo-help"><?php esc_html_e( 'Used when the post is shared. Leave empty to reuse the SEO title.', 'bearius-seo-light' ); ?></span>
				</div>

				<div class="bearius-seo-field">
					<div class="bearius-seo-field__head">
						<label class="bearius-seo-field__label" for="bearius-seo-image"><?php esc_html_e( 'Social image', 'bearius-seo-light' ); ?></label>
					</div>
					<div class="bearius-seo-image-row">
						<span class="bearius-seo-thumb"><img alt="" decoding="async" data-bs-true-color<?php echo $image ? ' src="' . esc_url( $image ) . '"' : ' hidden'; ?> /></span>
						<span class="bearius-seo-image-controls">
							<input type="url" class="bearius-seo-image-field" id="bearius-seo-image"
								name="bearius_seo_image" value="<?php echo esc_url( $image ); ?>" />
							<span class="bearius-seo-image-buttons">
								<button type="button" class="button bearius-seo-image-pick"><?php esc_html_e( 'Select image', 'bearius-seo-light' ); ?></button>
								<button type="button" class="button-link bearius-seo-image-clear"><?php esc_html_e( 'Clear', 'bearius-seo-light' ); ?></button>
							</span>
							<span class="bearius-seo-help"><?php esc_html_e( 'Leave empty to use the featured image. Recommended size 1200x630 pixels.', 'bearius-seo-light' ); ?></span>
						</span>
					</div>
				</div>
				</div>
				</div>

				<div class="bearius-seo-toggles">
					<label class="bearius-seo-toggle">
						<input type="checkbox" name="bearius_seo_noindex" value="1" <?php checked( $noindex, 1 ); ?> />
						<span><?php esc_html_e( 'Ask search engines not to index this content', 'bearius-seo-light' ); ?></span>
					</label>

					<?php if ( $in_news ) : ?>
					<label class="bearius-seo-toggle">
						<input type="checkbox" name="bearius_seo_news_exclude" value="1" <?php checked( $no_news, 1 ); ?> />
						<span><?php esc_html_e( 'Leave this entry out of the Google News sitemap', 'bearius-seo-light' ); ?></span>
					</label>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Saves the fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['bearius_seo_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bearius_seo_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bearius_seo_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, bearius_seo_supported_post_types(), true ) ) {
			return;
		}

		$text_fields = array(
			'_bearius_seo_title'  => 'bearius_seo_title',
			'_bearius_seo_desc'   => 'bearius_seo_desc',
			'_bearius_seo_social' => 'bearius_seo_social',
		);

		foreach ( $text_fields as $meta_key => $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$image = isset( $_POST['bearius_seo_image'] ) ? esc_url_raw( wp_unslash( $_POST['bearius_seo_image'] ) ) : '';
		if ( '' === $image ) {
			delete_post_meta( $post_id, '_bearius_seo_image' );
		} else {
			update_post_meta( $post_id, '_bearius_seo_image', $image );
		}

		if ( empty( $_POST['bearius_seo_noindex'] ) ) {
			delete_post_meta( $post_id, '_bearius_seo_noindex' );
		} else {
			update_post_meta( $post_id, '_bearius_seo_noindex', 1 );
		}

		if ( isset( $_POST['bearius_seo_news_exclude'] ) ) {
			update_post_meta( $post_id, '_bearius_seo_news_exclude', 1 );
		} elseif ( isset( $_POST['bearius_seo_title'] ) && bearius_seo_module_active( 'news' ) ) {
			delete_post_meta( $post_id, '_bearius_seo_news_exclude' );
		}
	}
}
