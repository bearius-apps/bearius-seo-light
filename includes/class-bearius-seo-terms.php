<?php
/**
 * SEO fields for categories, tags and other public taxonomies.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds SEO title, meta description and noindex to term screens.
 */
class Bearius_SEO_Terms {

	/**
	 * Hooks the fields.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'admin_init', array( $this, 'hook_taxonomies' ) );
	}

	/**
	 * Registers the term meta keys.
	 *
	 * @return void
	 */
	public function register_meta() {
		$args = array(
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => function ( $allowed, $meta_key, $term_id ) {
				return current_user_can( 'edit_term', $term_id );
			},
		);

		register_term_meta( '', '_bearius_seo_title', array_merge( $args, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) );
		register_term_meta( '', '_bearius_seo_desc', array_merge( $args, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ) ) );
		register_term_meta( '', '_bearius_seo_noindex', array_merge( $args, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ) ) );
	}

	/**
	 * Adds the form fields to every public taxonomy.
	 *
	 * @return void
	 */
	public function hook_taxonomies() {
		foreach ( array_keys( bearius_seo_public_taxonomies() ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render_add' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_edit' ) );
			add_action( 'created_' . $taxonomy, array( $this, 'save' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'save' ) );
		}
	}

	/**
	 * Fields on the "Add new" form.
	 *
	 * @return void
	 */
	public function render_add() {
		wp_nonce_field( 'bearius_seo_term', 'bearius_seo_term_nonce' );
		?>
		<div class="form-field">
			<label for="bearius-seo-term-title"><?php esc_html_e( 'SEO title', 'bearius-seo-light' ); ?></label>
			<input type="text" id="bearius-seo-term-title" name="bearius_seo_term_title" value="" />
			<p><?php esc_html_e( 'Overrides the archive title in the browser and when shared. Leave empty to use the name.', 'bearius-seo-light' ); ?></p>
		</div>
		<div class="form-field">
			<label for="bearius-seo-term-desc"><?php esc_html_e( 'Meta description', 'bearius-seo-light' ); ?></label>
			<textarea id="bearius-seo-term-desc" name="bearius_seo_term_desc" rows="3"></textarea>
			<p><?php esc_html_e( 'Leave empty to use the description above.', 'bearius-seo-light' ); ?></p>
		</div>
		<div class="form-field">
			<label><input type="checkbox" name="bearius_seo_term_noindex" value="1" /> <?php esc_html_e( 'Ask search engines not to index this archive', 'bearius-seo-light' ); ?></label>
		</div>
		<?php
	}

	/**
	 * Fields on the edit screen.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public function render_edit( $term ) {
		$title   = get_term_meta( $term->term_id, '_bearius_seo_title', true );
		$desc    = get_term_meta( $term->term_id, '_bearius_seo_desc', true );
		$noindex = get_term_meta( $term->term_id, '_bearius_seo_noindex', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="bearius-seo-term-title"><?php esc_html_e( 'SEO title', 'bearius-seo-light' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'bearius_seo_term', 'bearius_seo_term_nonce' ); ?>
				<input type="text" id="bearius-seo-term-title" name="bearius_seo_term_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $term->name ); ?>" />
				<p class="description"><?php esc_html_e( 'Overrides the archive title in the browser and when shared. Leave empty to use the name.', 'bearius-seo-light' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="bearius-seo-term-desc"><?php esc_html_e( 'Meta description', 'bearius-seo-light' ); ?></label></th>
			<td>
				<textarea id="bearius-seo-term-desc" name="bearius_seo_term_desc" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Leave empty to use the description above.', 'bearius-seo-light' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Indexing', 'bearius-seo-light' ); ?></th>
			<td>
				<label><input type="checkbox" name="bearius_seo_term_noindex" value="1" <?php checked( $noindex, 1 ); ?> /> <?php esc_html_e( 'Ask search engines not to index this archive', 'bearius-seo-light' ); ?></label>
				<p class="description"><?php esc_html_e( 'The archive also disappears from the XML sitemap.', 'bearius-seo-light' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the fields.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save( $term_id ) {
		if ( ! isset( $_POST['bearius_seo_term_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bearius_seo_term_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bearius_seo_term' ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$title = isset( $_POST['bearius_seo_term_title'] ) ? sanitize_text_field( wp_unslash( $_POST['bearius_seo_term_title'] ) ) : '';
		$desc  = isset( $_POST['bearius_seo_term_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bearius_seo_term_desc'] ) ) : '';

		foreach ( array(
			'_bearius_seo_title' => $title,
			'_bearius_seo_desc'  => $desc,
		) as $key => $value ) {
			if ( '' === $value ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $value );
			}
		}

		if ( empty( $_POST['bearius_seo_term_noindex'] ) ) {
			delete_term_meta( $term_id, '_bearius_seo_noindex' );
		} else {
			update_term_meta( $term_id, '_bearius_seo_noindex', 1 );
		}
	}
}
