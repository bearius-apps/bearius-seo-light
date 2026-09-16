<?php
/**
 * Detects other SEO plugins and steps aside when one is active.
 *
 * Two plugins printing meta tags produce duplicates, which is worse than
 * having no plugin at all. When a conflict is found the whole front end
 * output is skipped and the site owner gets a notice explaining why.
 *
 * @package BeariusSEOLight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility guard.
 */
class Bearius_SEO_Compat {

	/**
	 * Name of the conflicting plugin, empty when there is none.
	 *
	 * @var string
	 */
	private $conflict = '';

	/**
	 * Hooks the admin notice.
	 *
	 * @return void
	 */
	public function register() {
		$this->detect();

		if ( '' !== $this->conflict ) {
			add_action( 'admin_notices', array( $this, 'render_notice' ) );
		}
	}

	/**
	 * Whether another SEO plugin is running.
	 *
	 * @return bool
	 */
	public function has_conflict() {
		return '' !== $this->conflict;
	}

	/**
	 * Returns the name of the conflicting plugin.
	 *
	 * @return string
	 */
	public function get_conflict() {
		return $this->conflict;
	}

	/**
	 * Looks for constants and classes exposed by the common SEO plugins.
	 *
	 * @return void
	 */
	private function detect() {
		$constants = array(
			'WPSEO_VERSION'             => 'Yoast SEO',
			'RANK_MATH_VERSION'         => 'Rank Math SEO',
			'SEOPRESS_VERSION'          => 'SEOPress',
			'AIOSEO_VERSION'            => 'All in One SEO',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
			'SLIM_SEO_VER'              => 'Slim SEO',
			'SQ_VERSION'                => 'Squirrly SEO',
			'SEO_BY_SQUIRRLY'           => 'Squirrly SEO',
		);

		foreach ( $constants as $constant => $label ) {
			if ( defined( $constant ) ) {
				$this->conflict = $label;
				return;
			}
		}

		if ( class_exists( 'WPSEO_Frontend' ) ) {
			$this->conflict = 'Yoast SEO';
			return;
		}

		if ( function_exists( 'rank_math' ) ) {
			$this->conflict = 'Rank Math SEO';
			return;
		}

		if ( function_exists( 'the_seo_framework' ) ) {
			$this->conflict = 'The SEO Framework';
		}
	}

	/**
	 * Prints the admin notice.
	 *
	 * @return void
	 */
	public function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'plugins' !== $screen->id && 'settings_page_bearius-seo-light' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: name of the detected SEO plugin. */
			esc_html__( 'Bearius SEO Light is standing by because %s is active. Running both would output duplicate meta tags, so no tags are being added. Deactivate the other plugin to switch over.', 'bearius-seo-light' ),
			'<strong>' . esc_html( $this->conflict ) . '</strong>'
		);
		echo '</p></div>';
	}
}
