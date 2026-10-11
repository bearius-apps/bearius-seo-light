<?php
/**
 * Dashboard widget with the audit status at a glance.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the widget.
 */
class Bearius_SEO_Dashboard {

	/**
	 * Hooks the widget.
	 *
	 * @return void
	 */
	public function register() {
		if ( bearius_seo_module_enabled( 'widget' ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		}
	}

	/**
	 * Adds the widget for administrators.
	 *
	 * @return void
	 */
	public function add() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget( 'bearius_seo_status', __( 'Bearius SEO', 'bearius-seo-light' ), array( $this, 'render' ) );

		add_action(
			'admin_print_styles-index.php',
			function () {
				wp_enqueue_style( 'bearius-seo-settings', BEARIUS_SEO_URL . 'assets/css/settings.css', array(), BEARIUS_SEO_VERSION );
			}
		);
	}

	/**
	 * Renders the widget.
	 *
	 * @return void
	 */
	public function render() {
		$health = Bearius_SEO_Health::instance();
		$issues = $health->issues();
		$counts = $health->counts( $issues );
		$score  = $health->score( $issues );
		$stored = $health->stored();
		$top    = array_slice(
			array_values(
				array_filter(
					$issues,
					function ( $issue ) {
						return 'info' !== $issue['severity'];
					}
				)
			),
			0,
			3
		);

		$run = wp_nonce_url( admin_url( 'admin-post.php?action=bearius_seo_run_checks' ), 'bearius_seo_run_checks' );
		?>
		<div class="bs-widget">
			<div class="bs-widget__head">
				<?php Bearius_SEO_UI::score_ring( $score, 64 ); ?>
				<div>
					<p class="bs-widget__status bs-tone--<?php echo esc_attr( $counts['critical'] ? 'critical' : ( $counts['warning'] ? 'warning' : 'ok' ) ); ?>">
						<?php
						if ( $counts['critical'] ) {
							/* translators: %d: number of critical problems. */
							echo esc_html( sprintf( _n( '%d critical problem', '%d critical problems', $counts['critical'], 'bearius-seo-light' ), $counts['critical'] ) );
						} elseif ( $counts['warning'] ) {
							/* translators: %d: number of warnings. */
							echo esc_html( sprintf( _n( '%d warning', '%d warnings', $counts['warning'], 'bearius-seo-light' ), $counts['warning'] ) );
						} else {
							esc_html_e( 'No problems', 'bearius-seo-light' );
						}
						?>
					</p>
					<p class="bs-widget__meta">
						<?php
						if ( $stored['time'] ) {
							/* translators: %s: time since the last check. */
							echo esc_html( sprintf( __( 'Checked %s ago', 'bearius-seo-light' ), human_time_diff( $stored['time'] ) ) );
						} else {
							esc_html_e( 'The full check has not run yet', 'bearius-seo-light' );
						}
						?>
					</p>
				</div>
			</div>

			<?php if ( $top ) : ?>
				<ul class="bs-widget__list">
					<?php foreach ( $top as $issue ) : ?>
						<li>
							<span class="bs-dot bs-dot--<?php echo esc_attr( $issue['severity'] ); ?>" aria-hidden="true"></span>
							<a href="<?php echo esc_url( $issue['link'] ? $issue['link'] : bearius_seo_admin_url( 'audit' ) ); ?>"><?php echo esc_html( $issue['title'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="bs-widget__actions">
				<a class="button" href="<?php echo esc_url( $run ); ?>"><?php esc_html_e( 'Run checks', 'bearius-seo-light' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( bearius_seo_admin_url( 'audit' ) ); ?>"><?php esc_html_e( 'Open the audit', 'bearius-seo-light' ); ?></a>
			</p>
		</div>
		<?php
	}
}
