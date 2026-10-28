<?php
/**
 * Visible breadcrumbs: shortcode, block and template function.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the breadcrumb trail shared with the structured data.
 */
class Bearius_SEO_Breadcrumbs {

	/**
	 * Hooks the shortcode and the block.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'bearius_breadcrumbs', array( __CLASS__, 'render' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the block with a server side render callback.
	 *
	 * The editor shows a static placeholder instead of a live preview, so
	 * the block works even where the REST API is blocked.
	 *
	 * @return void
	 */
	public function register_block() {
		wp_register_script(
			'bearius-seo-breadcrumbs-block',
			BEARIUS_SEO_URL . 'assets/js/breadcrumbs-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
			BEARIUS_SEO_VERSION,
			true
		);

		wp_set_script_translations( 'bearius-seo-breadcrumbs-block', 'bearius-seo-light', BEARIUS_SEO_PATH . 'languages' );

		register_block_type(
			'bearius-seo/breadcrumbs',
			array(
				'api_version'     => 2,
				'editor_script'   => 'bearius-seo-breadcrumbs-block',
				'render_callback' => array( __CLASS__, 'render_block' ),
				'supports'        => array(
					'align'      => array( 'wide', 'full' ),
					'typography' => array( 'fontSize' => true ),
					'color'      => array(
						'text'       => true,
						'link'       => true,
						'background' => false,
					),
					'spacing'    => array( 'margin' => true ),
				),
			)
		);
	}

	/**
	 * Block render callback, which adds the block wrapper attributes.
	 *
	 * @return string
	 */
	public static function render_block() {
		return self::render( array( '__block' => true ) );
	}

	/**
	 * Returns the breadcrumb markup.
	 *
	 * @param array|string $atts Shortcode attributes or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		if ( is_front_page() || is_404() ) {
			return '';
		}

		$items = bearius_seo_breadcrumb_items();

		if ( count( $items ) < 2 ) {
			return '';
		}

		self::enqueue_style();

		$last  = count( $items ) - 1;
		$parts = array();

		foreach ( $items as $index => $item ) {
			$name = esc_html( wp_strip_all_tags( $item['name'] ) );

			if ( $index === $last ) {
				$parts[] = '<li aria-current="page"><span>' . $name . '</span></li>';
			} else {
				$parts[] = '<li><a href="' . esc_url( $item['url'] ) . '">' . $name . '</a></li>';
			}
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) && is_array( $atts ) && ! empty( $atts['__block'] )
			? get_block_wrapper_attributes( array( 'class' => 'bearius-breadcrumbs' ) )
			: 'class="bearius-breadcrumbs"';

		return '<nav ' . $wrapper . ' aria-label="' . esc_attr__( 'Breadcrumbs', 'bearius-seo-light' ) . '"><ol>' . implode( '', $parts ) . '</ol></nav>';
	}

	/**
	 * Adds the few rules the trail needs, once per page.
	 *
	 * @return void
	 */
	private static function enqueue_style() {
		if ( wp_style_is( 'bearius-seo-breadcrumbs', 'enqueued' ) ) {
			return;
		}

		wp_register_style( 'bearius-seo-breadcrumbs', false, array(), BEARIUS_SEO_VERSION );
		wp_add_inline_style(
			'bearius-seo-breadcrumbs',
			'.bearius-breadcrumbs ol{display:flex;flex-wrap:wrap;gap:.35em;margin:0;padding:0;list-style:none}.bearius-breadcrumbs li{margin:0}.bearius-breadcrumbs li+li::before{content:"/";margin-right:.35em;opacity:.45}.bearius-breadcrumbs [aria-current]{opacity:.75}'
		);
		wp_enqueue_style( 'bearius-seo-breadcrumbs' );
	}
}

/**
 * Prints the breadcrumbs from a theme template.
 *
 * @return void
 */
function bearius_seo_breadcrumbs() {
	if ( class_exists( 'Bearius_SEO_Breadcrumbs' ) && bearius_seo_module_active( 'breadcrumbs' ) ) {
		echo Bearius_SEO_Breadcrumbs::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render().
	}
}
