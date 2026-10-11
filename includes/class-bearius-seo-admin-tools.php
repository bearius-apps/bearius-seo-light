<?php
/**
 * Audit, AI visibility, import and report screens.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the tool tabs of the settings screen.
 */
class Bearius_SEO_Admin_Tools {

	/**
	 * Hooks nothing yet; kept for symmetry with the other admin classes.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_post_bearius_seo_checklist', array( $this, 'handle_checklist' ) );
	}

	/**
	 * Returns the checklist state.
	 *
	 * @return array Hidden flag and skipped items.
	 */
	private static function checklist_state() {
		$state = get_option( 'bearius_seo_checklist', array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'hidden'  => 0,
				'skipped' => array(),
			)
		);
	}

	/**
	 * Handles the checklist buttons: enable IndexNow, skip an item, hide the list.
	 *
	 * @return void
	 */
	public function handle_checklist() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bearius-seo-light' ) );
		}

		check_admin_referer( 'bearius_seo_checklist' );

		$do    = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$item  = isset( $_GET['item'] ) ? sanitize_key( wp_unslash( $_GET['item'] ) ) : '';
		$state = self::checklist_state();

		if ( 'hide' === $do ) {
			$state['hidden'] = 1;
		} elseif ( 'skip' === $do && '' !== $item ) {
			$state['skipped'][] = $item;
			$state['skipped']   = array_values( array_unique( $state['skipped'] ) );
		} elseif ( 'indexnow' === $do ) {
			// The click on a button that explains the service is the consent.
			$changes = array( 'mod_indexnow' => 1 );
			if ( '' === Bearius_SEO_IndexNow::key() ) {
				$changes['indexnow_key'] = Bearius_SEO_IndexNow::generate_key();
			}
			bearius_seo_update_settings( $changes );
		}

		update_option( 'bearius_seo_checklist', $state, false );

		wp_safe_redirect( bearius_seo_admin_url( 'overview' ) );
		exit;
	}

	/**
	 * Prints the getting started checklist on the Overview screen.
	 *
	 * @return void
	 */
	public static function render_checklist() {
		$state = self::checklist_state();

		if ( $state['hidden'] ) {
			return;
		}

		$url = function ( $do, $item = '' ) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'bearius_seo_checklist',
						'do'     => $do,
						'item'   => $item,
					),
					admin_url( 'admin-post.php' )
				),
				'bearius_seo_checklist'
			);
		};

		$items = array(
			'logo'     => array(
				'' !== bearius_seo_logo_url(),
				__( 'Add the publisher logo', 'bearius-seo-light' ),
				__( 'Shown as the publisher in structured data and by Google next to news articles.', 'bearius-seo-light' ),
				'<a class="button button-small" href="' . esc_url( bearius_seo_admin_url( 'publisher' ) ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>',
			),
			'image'    => array(
				'' !== trim( (string) bearius_seo_get_setting( 'default_image' ) ),
				__( 'Choose a default social image', 'bearius-seo-light' ),
				__( 'Used when a post has no featured image, so shared links always have a picture.', 'bearius-seo-light' ),
				'<a class="button button-small" href="' . esc_url( bearius_seo_admin_url( 'publisher' ) ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>',
			),
			'icon'     => array(
				has_site_icon(),
				__( 'Set the site icon', 'bearius-seo-light' ),
				__( 'Google shows it next to your pages in search results. WordPress adds it to the site, the dashboard and the login page.', 'bearius-seo-light' ),
				'<a class="button button-small" href="' . esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>',
			),
			'ai'       => array(
				(bool) bearius_seo_get_setting( 'ai_reviewed' ),
				__( 'Decide how AI companies may use your site', 'bearius-seo-light' ),
				__( 'Nothing is blocked until you choose. The AI visibility screen explains each option in plain words.', 'bearius-seo-light' ),
				'<a class="button button-small" href="' . esc_url( bearius_seo_admin_url( 'ai' ) ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>',
			),
			'indexnow' => array(
				Bearius_SEO_IndexNow::active() || in_array( 'indexnow', $state['skipped'], true ),
				__( 'Tell Bing and other engines about new posts right away', 'bearius-seo-light' ),
				__( 'IndexNow sends the address of each published or updated post to api.indexnow.org, which passes it to Bing, Yandex, Seznam, Naver and Yep. Only the address is sent. Google does not use it.', 'bearius-seo-light' ),
				'<a class="button button-small button-primary" href="' . esc_url( $url( 'indexnow' ) ) . '">' . esc_html__( 'Switch on', 'bearius-seo-light' ) . '</a> <a class="button-link" href="' . esc_url( $url( 'skip', 'indexnow' ) ) . '">' . esc_html__( 'Not now', 'bearius-seo-light' ) . '</a>',
			),
			'audit'    => array(
				Bearius_SEO_Health::instance()->stored()['time'] > 0,
				__( 'Run the first full check', 'bearius-seo-light' ),
				__( 'Scans your sitemaps, robots.txt and pages, and lists what to fix.', 'bearius-seo-light' ),
				'<a class="button button-small" href="' . esc_url( self::run_url() ) . '">' . esc_html__( 'Run checks', 'bearius-seo-light' ) . '</a>',
			),
		);

		$done = count( array_filter( wp_list_pluck( $items, 0 ) ) );

		if ( count( $items ) === $done ) {
			return;
		}

		echo '<section class="bs-checklist"><div class="bs-checklist__head"><div><h2>' . esc_html__( 'Getting started', 'bearius-seo-light' ) . '</h2>';
		/* translators: 1: finished steps, 2: all steps. */
		echo '<p>' . esc_html( sprintf( __( '%1$d of %2$d done', 'bearius-seo-light' ), $done, count( $items ) ) ) . '</p></div>';
		echo '<a class="bs-checklist__hide" href="' . esc_url( $url( 'hide' ) ) . '">' . esc_html__( 'Hide this list', 'bearius-seo-light' ) . '</a></div>';
		echo '<div class="bs-progress bs-checklist__bar"><span style="width:' . (int) round( 100 * $done / count( $items ) ) . '%"></span></div><ul>';

		foreach ( $items as $key => $item ) {
			echo '<li class="bs-check' . ( $item[0] ? ' is-done' : '' ) . '"><span class="bs-check__mark" aria-hidden="true">' . ( $item[0] ? Bearius_SEO_UI::icon( 'check' ) : '' ) . '</span><div class="bs-check__body"><p class="bs-check__title">' . esc_html( $item[1] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			if ( ! $item[0] ) {
				echo '<p class="bs-check__detail">' . esc_html( $item[2] ) . '</p>';
			}
			echo '</div>';
			if ( ! $item[0] ) {
				echo '<div class="bs-check__action">' . $item[3] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
			}
			echo '</li>';
		}

		echo '</ul></section>';
	}

	/**
	 * Marks the report screen so print styles can hide the admin chrome.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only screen detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable

		if ( 'bearius-seo-light' === $page && 'report' === $tab ) {
			$classes .= ' bs-report-screen';
		}

		return $classes;
	}

	/**
	 * Returns the URL of the "Run checks" action.
	 *
	 * @return string
	 */
	public static function run_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=bearius_seo_run_checks' ), 'bearius_seo_run_checks' );
	}

	/**
	 * Prints the score block with the most important issues.
	 *
	 * @param int $limit Number of issues to list.
	 * @return void
	 */
	public static function render_hero( $limit = 5 ) {
		$health = Bearius_SEO_Health::instance();
		$issues = $health->issues();
		$counts = $health->counts( $issues );
		$score  = $health->score( $issues );
		$stored = $health->stored();

		if ( isset( $_GET['bearius_checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			Bearius_SEO_UI::callout( __( 'Checks finished. The results below are current.', 'bearius-seo-light' ), 'ok' );
		}

		Bearius_SEO_Fixes::render_result();

		$visible = array_values(
			array_filter(
				$issues,
				function ( $issue ) {
					return 'info' !== $issue['severity'];
				}
			)
		);

		if ( $counts['critical'] ) {
			$headline = _n( 'One problem can keep you out of search results', 'Some problems can keep you out of search results', $counts['critical'], 'bearius-seo-light' );
		} elseif ( $counts['warning'] ) {
			$headline = __( 'Nothing blocks indexing, but a few things need attention', 'bearius-seo-light' );
		} elseif ( $counts['notice'] ) {
			$headline = __( 'In good shape, with a few suggestions', 'bearius-seo-light' );
		} else {
			$headline = __( 'No problems found', 'bearius-seo-light' );
		}

		echo '<section class="bs-hero">';
		echo '<div class="bs-hero__score">';
		Bearius_SEO_UI::score_ring( $score, 132 );
		echo '<p class="bs-hero__label">' . esc_html__( 'Bearius score', 'bearius-seo-light' ) . '</p></div>';

		echo '<div class="bs-hero__body"><h2 class="bs-hero__title">' . esc_html( $headline ) . '</h2>';
		echo '<ul class="bs-tally">';
		foreach ( array( 'critical', 'warning', 'notice' ) as $severity ) {
			echo '<li class="bs-tally__item bs-tally__item--' . esc_attr( $severity ) . '"><b>' . (int) $counts[ $severity ] . '</b><span>' . esc_html( bearius_seo_severity_label( $severity ) ) . '</span></li>';
		}
		echo '</ul>';

		echo '<p class="bs-hero__meta">';
		if ( $stored['time'] ) {
			/* translators: %s: time since the last full check. */
			echo esc_html( sprintf( __( 'Full check ran %s ago. Settings are checked live.', 'bearius-seo-light' ), human_time_diff( (int) $stored['time'] ) ) );
		} else {
			esc_html_e( 'Settings are checked live. Run the full check to scan sitemaps, robots.txt and your pages.', 'bearius-seo-light' );
		}
		echo ' ' . esc_html__( 'The score is our own measure of the problems found, not a Google or PageSpeed score.', 'bearius-seo-light' ) . '</p>';

		echo '<div class="bs-hero__actions"><a class="button button-primary bs-button" href="' . esc_url( self::run_url() ) . '" data-bs-busy="' . esc_attr__( 'Checking...', 'bearius-seo-light' ) . '">' . Bearius_SEO_UI::icon( 'refresh' ) . '<span>' . esc_html__( 'Run checks', 'bearius-seo-light' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		echo '<a class="button bs-button" href="' . esc_url( bearius_seo_admin_url( 'report' ) ) . '">' . Bearius_SEO_UI::icon( 'print' ) . '<span>' . esc_html__( 'Report', 'bearius-seo-light' ) . '</span></a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		echo '</div></section>';

		if ( $limit && $visible ) {
			echo '<div class="bs-top">';
			Bearius_SEO_UI::issue_list( array_slice( $visible, 0, $limit ) );
			if ( count( $visible ) > $limit ) {
				/* translators: %d: number of further issues. */
				echo '<p class="bs-top__more"><a href="' . esc_url( bearius_seo_admin_url( 'audit' ) ) . '">' . esc_html( sprintf( __( 'See all %d findings in the audit', 'bearius-seo-light' ), count( $visible ) ) ) . '</a></p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Filters issues by group.
	 *
	 * @param array[]  $issues Issues.
	 * @param string[] $groups Groups.
	 * @return array[]
	 */
	private static function by_group( $issues, $groups ) {
		return array_values(
			array_filter(
				$issues,
				function ( $issue ) use ( $groups ) {
					return in_array( $issue['group'], $groups, true );
				}
			)
		);
	}

	/**
	 * Audit tab.
	 *
	 * @return void
	 */
	public static function render_audit() {
		if ( ! bearius_seo_module_enabled( 'audit' ) ) {
			Bearius_SEO_UI::callout( __( 'The audit module is off, so the daily check and the page scans do not run. Switch it on in the Overview.', 'bearius-seo-light' ), 'info' );
		}

		self::render_hero( 0 );

		$health = Bearius_SEO_Health::instance();
		$issues = $health->issues();
		$stored = $health->stored();

		Bearius_SEO_UI::section( __( 'Setup and crawling', 'bearius-seo-light' ), __( 'Settings, sitemaps and robots.txt: what decides whether search engines can reach your content.', 'bearius-seo-light' ), true );
		Bearius_SEO_UI::issue_list( self::by_group( $issues, array( 'setup', 'crawl' ) ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Pages', 'bearius-seo-light' ), __( 'The home page and the latest post, fetched as a logged out visitor on desktop and on mobile. This is where duplicates from the theme or snippets show up.', 'bearius-seo-light' ), true );
		if ( $stored['scans'] ) {
			self::scan_table( $stored['scans'] );
		}
		Bearius_SEO_UI::issue_list( self::by_group( $issues, array( 'page' ) ) );
		Bearius_SEO_UI::section_end();

		Bearius_SEO_UI::section( __( 'Performance', 'bearius-seo-light' ), __( 'Read from the HTML of the mobile version. These are hints, not lab measurements: the page is not rendered in a browser.', 'bearius-seo-light' ), true );
		if ( $stored['scans'] ) {
			self::performance_table( $stored['scans'] );
		}
		Bearius_SEO_UI::issue_list( self::by_group( $issues, array( 'performance' ) ) );
		Bearius_SEO_UI::section_end();

		self::content_audit();
	}

	/**
	 * Prints the page scan comparison.
	 *
	 * @param array $scans Stored scans.
	 * @return void
	 */
	private static function scan_table( $scans ) {
		echo '<div class="bs-table-wrap"><table class="bs-table bs-table--scan"><thead><tr><th>' . esc_html__( 'Page', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Device', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Status', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Canonical', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Descriptions', 'bearius-seo-light' ) . '</th><th>H1</th><th>' . esc_html__( 'Structured data', 'bearius-seo-light' ) . '</th></tr></thead><tbody>';

		foreach ( $scans as $scan ) {
			foreach ( array( 'desktop', 'mobile' ) as $device ) {
				$page = $scan['devices'][ $device ];
				echo '<tr>';
				if ( 'desktop' === $device ) {
					echo '<td rowspan="2"><a href="' . esc_url( $scan['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $scan['label'] ) . '</a></td>';
				}
				echo '<td>' . ( 'desktop' === $device ? esc_html__( 'Desktop', 'bearius-seo-light' ) : esc_html__( 'Mobile', 'bearius-seo-light' ) ) . '</td>';
				echo '<td>' . ( $page['code'] ? (int) $page['code'] : '&mdash;' ) . '</td>';
				echo '<td>' . (int) count( $page['canonical'] ) . '</td>';
				echo '<td>' . (int) count( $page['description'] ) . '</td>';
				echo '<td>' . (int) count( $page['h1'] ) . '</td>';
				echo '<td>' . esc_html( $page['types'] ? implode( ', ', array_unique( $page['types'] ) ) : '-' ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Prints the performance figures.
	 *
	 * @param array $scans Stored scans.
	 * @return void
	 */
	private static function performance_table( $scans ) {
		echo '<div class="bs-table-wrap"><table class="bs-table"><thead><tr><th>' . esc_html__( 'Page', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'HTML time', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Cache', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'HTML size', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Blocking scripts', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Images without size', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Other domains', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Elements', 'bearius-seo-light' ) . '</th></tr></thead><tbody>';

		foreach ( $scans as $scan ) {
			$page = $scan['devices']['mobile'];
			echo '<tr><td>' . esc_html( $scan['label'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $page['time'], 2 ) ) . ' s</td>';
			echo '<td>' . esc_html( $page['cache'] ? $page['cache'] : '-' ) . '</td>';
			echo '<td>' . esc_html( size_format( (int) $page['size'] ) ) . '</td>';
			echo '<td>' . (int) $page['head_scripts'] . '</td>';
			echo '<td>' . (int) $page['img_no_dims'] . '</td>';
			echo '<td>' . (int) count( $page['domains'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $page['dom_nodes'] ) ) . '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Content audit with filters and a paged list.
	 *
	 * @return void
	 */
	private static function content_audit() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only filters.
		$filters = Bearius_SEO_Content_Audit::filters(
			array(
				'post_type' => isset( $_GET['ca_type'] ) ? sanitize_key( wp_unslash( $_GET['ca_type'] ) ) : '',
				'range'     => isset( $_GET['ca_range'] ) ? absint( $_GET['ca_range'] ) : 0,
				'indexable' => isset( $_GET['ca_indexable'] ) ? 1 : ( isset( $_GET['ca_filtered'] ) ? 0 : 1 ),
			)
		);
		$current  = isset( $_GET['ca_issue'] ) ? sanitize_key( wp_unslash( $_GET['ca_issue'] ) ) : '';
		$severity = isset( $_GET['ca_severity'] ) ? sanitize_key( wp_unslash( $_GET['ca_severity'] ) ) : '';
		$paged    = isset( $_GET['ca_paged'] ) ? max( 1, absint( $_GET['ca_paged'] ) ) : 1;
		// phpcs:enable

		$types   = Bearius_SEO_Content_Audit::types();
		$summary = Bearius_SEO_Content_Audit::summary( $filters );
		$order   = array( 'critical', 'warning', 'notice', 'info' );

		if ( ! isset( $types[ $current ] ) ) {
			$current = '';
			foreach ( $order as $level ) {
				foreach ( $types as $key => $type ) {
					if ( '' === $current && $type[1] === $level && $summary[ $key ] > 0 && ( '' === $severity || $severity === $level ) ) {
						$current = $key;
					}
				}
			}
		}

		Bearius_SEO_UI::section( __( 'Content', 'bearius-seo-light' ), __( 'Live queries on your published entries. Pick a finding to see the entries; the most important ones come first.', 'bearius-seo-light' ), true );

		echo '<form method="get" class="bs-filters"><input type="hidden" name="page" value="bearius-seo-light" /><input type="hidden" name="tab" value="audit" /><input type="hidden" name="ca_filtered" value="1" />';

		echo '<label><span>' . esc_html__( 'Importance', 'bearius-seo-light' ) . '</span><select name="ca_severity"><option value="">' . esc_html__( 'All', 'bearius-seo-light' ) . '</option>';
		foreach ( array( 'warning', 'notice', 'info' ) as $level ) {
			echo '<option value="' . esc_attr( $level ) . '" ' . selected( $severity, $level, false ) . '>' . esc_html( bearius_seo_severity_label( $level ) ) . '</option>';
		}
		echo '</select></label>';

		echo '<label><span>' . esc_html__( 'Content type', 'bearius-seo-light' ) . '</span><select name="ca_type"><option value="">' . esc_html__( 'All', 'bearius-seo-light' ) . '</option>';
		foreach ( bearius_seo_supported_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			echo '<option value="' . esc_attr( $post_type ) . '" ' . selected( $filters['post_type'], $post_type, false ) . '>' . esc_html( $object ? $object->labels->name : $post_type ) . '</option>';
		}
		echo '</select></label>';

		echo '<label><span>' . esc_html__( 'Published', 'bearius-seo-light' ) . '</span><select name="ca_range">';
		foreach ( array(
			0   => __( 'Any time', 'bearius-seo-light' ),
			30  => __( 'Last 30 days', 'bearius-seo-light' ),
			90  => __( 'Last 90 days', 'bearius-seo-light' ),
			365 => __( 'Last year', 'bearius-seo-light' ),
		) as $value => $label ) {
			echo '<option value="' . (int) $value . '" ' . selected( $filters['range'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';

		echo '<label class="bs-filters__check"><input type="checkbox" name="ca_indexable" value="1" ' . checked( $filters['indexable'], true, false ) . ' /> ' . esc_html__( 'Only indexed entries', 'bearius-seo-light' ) . '</label>';
		echo '<button type="submit" class="button">' . esc_html__( 'Apply', 'bearius-seo-light' ) . '</button></form>';

		$base = array(
			'ca_filtered' => 1,
			'ca_type'     => $filters['post_type'],
			'ca_range'    => $filters['range'],
			'ca_severity' => $severity,
		);
		if ( $filters['indexable'] ) {
			$base['ca_indexable'] = 1;
		}

		echo '<div class="bs-cards">';
		foreach ( $order as $level ) {
			foreach ( $types as $key => $type ) {
				if ( $type[1] !== $level || ( '' !== $severity && $severity !== $level ) ) {
					continue;
				}

				$classes = 'bs-card bs-card--' . $type[1] . ( $key === $current ? ' is-active' : '' ) . ( 0 === $summary[ $key ] ? ' is-empty' : '' );
				echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( bearius_seo_admin_url( 'audit', array_merge( $base, array( 'ca_issue' => $key ) ) ) . '#bs-content-list' ) . '"><b>' . esc_html( number_format_i18n( $summary[ $key ] ) ) . '</b><span>' . esc_html( $type[0] ) . '</span></a>';
			}
		}
		echo '</div>';

		if ( '' === $current ) {
			echo '<div class="bs-empty">' . Bearius_SEO_UI::icon( 'check' ) . '<p>' . esc_html__( 'No content findings for these filters.', 'bearius-seo-light' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			Bearius_SEO_UI::section_end();
			return;
		}

		$per_page  = 50;
		$total     = $summary[ $current ];
		$ids       = Bearius_SEO_Content_Audit::ids( $current, $filters, $per_page, ( $paged - 1 ) * $per_page );
		$languages = Bearius_SEO_Languages::instance();

		echo '<div id="bs-content-list" class="bs-list-head"><h3>' . esc_html( $types[ $current ][0] ) . '</h3><p>' . esc_html( $types[ $current ][2] ) . '</p></div>';

		/**
		 * Prints add-on actions for the selected content finding.
		 *
		 * @param string $current Finding type.
		 * @param array  $filters Normalised filters.
		 * @param int    $total   Number of entries.
		 */
		do_action( 'bearius_seo_content_audit_actions', $current, $filters, $total );

		if ( ! $ids ) {
			echo '<div class="bs-empty">' . Bearius_SEO_UI::icon( 'check' ) . '<p>' . esc_html__( 'Nothing to fix here.', 'bearius-seo-light' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			Bearius_SEO_UI::section_end();
			return;
		}

		_prime_post_caches( $ids, false, false );

		echo '<div class="bs-table-wrap"><table class="bs-table"><thead><tr><th>' . esc_html__( 'Title', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Type', 'bearius-seo-light' ) . '</th>';
		if ( $languages->is_multilingual() && ! $languages->shares_posts() ) {
			echo '<th>' . esc_html__( 'Language', 'bearius-seo-light' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Published', 'bearius-seo-light' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$object = get_post_type_object( $post->post_type );
			$title  = get_the_title( $post );

			echo '<tr><td class="bs-table__title">' . esc_html( '' !== $title ? $title : __( '(no title)', 'bearius-seo-light' ) ) . '</td>';
			echo '<td>' . esc_html( $object ? $object->labels->singular_name : $post->post_type ) . '</td>';
			if ( $languages->is_multilingual() && ! $languages->shares_posts() ) {
				echo '<td><span class="bs-tag">' . esc_html( strtoupper( $languages->post_language( $post_id ) ) ) . '</span></td>';
			}
			echo '<td>' . esc_html( get_the_date( '', $post ) ) . '</td>';
			echo '<td class="bs-table__actions"><a href="' . esc_url( (string) get_edit_post_link( $post_id ) ) . '">' . esc_html__( 'Edit', 'bearius-seo-light' ) . '</a> <a href="' . esc_url( (string) get_permalink( $post_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'bearius-seo-light' ) . '</a></td></tr>';
		}

		echo '</tbody></table></div>';

		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="bs-pager">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'ca_paged', '%#%', bearius_seo_admin_url( 'audit', array_merge( $base, array( 'ca_issue' => $current ) ) ) ) . '#bs-content-list',
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => __( 'Previous', 'bearius-seo-light' ),
						'next_text' => __( 'Next', 'bearius-seo-light' ),
					)
				)
			);
			echo '</div>';
		}

		Bearius_SEO_UI::section_end();
	}

	/**
	 * AI visibility tab.
	 *
	 * @return void
	 */
	public static function render_ai() {
		$stored  = Bearius_SEO_Health::instance()->stored();
		$served  = (array) $stored['ai'];
		$presets = Bearius_SEO_Robots::presets();
		$preset  = (string) bearius_seo_get_setting( 'robots_ai', 'none' );
		$preset  = isset( $presets[ $preset ] ) ? $preset : 'none';
		$blocked = Bearius_SEO_Robots::blocked_bots();

		$purposes = array(
			'training' => __( 'Model training', 'bearius-seo-light' ),
			'search'   => __( 'AI search and citations', 'bearius-seo-light' ),
			'user'     => __( 'Pages opened on request', 'bearius-seo-light' ),
		);

		Bearius_SEO_UI::section( __( 'Who may crawl', 'bearius-seo-light' ), __( 'Choose how AI companies may use your site. Read the explanation first: this is your decision, and the plugin changes nothing until you make it.', 'bearius-seo-light' ), true );

		echo '<div class="bs-explain">';
		echo '<p>' . esc_html__( 'AI companies send crawlers to your site for three different jobs:', 'bearius-seo-light' ) . '</p><ul>';
		echo '<li><b>' . esc_html__( 'Model training.', 'bearius-seo-light' ) . '</b> ' . esc_html__( 'Your texts are copied and used to teach new AI models. Later the model may use what it learned in its answers, but it does not show where it came from and does not send visitors to you.', 'bearius-seo-light' ) . '</li>';
		echo '<li><b>' . esc_html__( 'AI search and citations.', 'bearius-seo-light' ) . '</b> ' . esc_html__( 'When someone asks ChatGPT, Claude or Perplexity a question, these crawlers look for current pages, and the answer can quote your article and link to it as the source. This works much like a search engine and can bring you visitors.', 'bearius-seo-light' ) . '</li>';
		echo '<li><b>' . esc_html__( 'Pages opened on request.', 'bearius-seo-light' ) . '</b> ' . esc_html__( 'A person pastes your link into a chat and asks the assistant to read it. The page is fetched once, for that person only.', 'bearius-seo-light' ) . '</li>';
		echo '</ul>';
		echo '<p>' . esc_html__( 'Blocking training does not remove you from Google or Bing and does not stop AI assistants from quoting you. Blocking AI search means assistants cannot quote or link your pages. Google-Extended only decides whether Google may use your pages for Gemini; Google Search, including its AI Overviews, is not affected.', 'bearius-seo-light' ) . '</p>';
		echo '<p class="bs-muted">' . esc_html__( 'The rules go into robots.txt. It is a request, not a lock: the companies listed here say they follow it, but crawlers that ignore the rules cannot be stopped this way.', 'bearius-seo-light' ) . '</p>';
		echo '</div>';

		Bearius_SEO_UI::form_open( 'ai' );

		echo '<fieldset class="bs-presets" data-bs-presets><legend class="screen-reader-text">' . esc_html__( 'Policy', 'bearius-seo-light' ) . '</legend>';
		foreach ( $presets as $key => $info ) {
			echo '<label class="bs-preset"><input type="radio" name="' . esc_attr( Bearius_SEO_UI::name( 'robots_ai' ) ) . '" value="' . esc_attr( $key ) . '" ' . checked( $preset, $key, false ) . ' /><span><b>' . esc_html( $info[0] ) . ( 'none' === $key ? ' <em>' . esc_html__( 'default', 'bearius-seo-light' ) . '</em>' : '' ) . '</b><small>' . esc_html( $info[1] ) . '</small></span></label>';
		}
		echo '</fieldset>';

		echo '<input type="hidden" name="' . esc_attr( Bearius_SEO_UI::name( 'robots_ai_blocked' ) ) . '" value="" />';
		echo '<div class="bs-table-wrap"><table class="bs-table bs-table--bots"><thead><tr><th>' . esc_html__( 'Crawler', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Company', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Purpose', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Allowed', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'On the server', 'bearius-seo-light' ) . '</th></tr></thead><tbody>';

		foreach ( Bearius_SEO_Robots::ai_bots() as $agent => $info ) {
			$allowed = ! in_array( $agent, $blocked, true );
			$id      = 'bs-bot-' . sanitize_html_class( strtolower( $agent ) );

			if ( ! isset( $served[ $agent ] ) ) {
				$state = '<span class="bs-badge">' . esc_html__( 'Not checked yet', 'bearius-seo-light' ) . '</span>';
			} elseif ( (bool) $served[ $agent ] === $allowed ) {
				$state = '<span class="bs-badge bs-badge--' . ( $allowed ? 'ok' : 'neutral' ) . '">' . ( $allowed ? esc_html__( 'Allowed', 'bearius-seo-light' ) : esc_html__( 'Blocked', 'bearius-seo-light' ) ) . '</span>';
			} else {
				$state = '<span class="bs-badge bs-badge--warning">' . ( $served[ $agent ] ? esc_html__( 'Still allowed', 'bearius-seo-light' ) : esc_html__( 'Blocked elsewhere', 'bearius-seo-light' ) ) . '</span>';
			}

			echo '<tr><td><label for="' . esc_attr( $id ) . '"><code>' . esc_html( $agent ) . '</code></label></td><td>' . esc_html( $info[0] ) . '</td><td><span class="bs-purpose bs-purpose--' . esc_attr( $info[1] ) . '">' . esc_html( $purposes[ $info[1] ] ) . '</span></td>';
			echo '<td><label class="bs-switch"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( Bearius_SEO_UI::name( 'robots_ai_allow', '[]' ) ) . '" value="' . esc_attr( $agent ) . '" data-purpose="' . esc_attr( $info[1] ) . '" ' . checked( $allowed, true, false ) . ' /><span class="bs-switch__track"></span></label></td>';
			echo '<td>' . $state . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		}

		echo '</tbody></table></div>';

		$note = $served
			/* translators: %s: time since the check. */
			? sprintf( __( '"On the server" shows the robots.txt crawlers received during the check %s ago. Run the checks again after saving.', 'bearius-seo-light' ), human_time_diff( (int) $stored['time'] ) )
			: __( '"On the server" fills in after the first full check on the Audit screen.', 'bearius-seo-light' );
		echo '<p class="bs-muted bs-note">' . esc_html( $note ) . '</p>';

		Bearius_SEO_UI::form_close();

		Bearius_SEO_UI::callout( __( 'Firewalls and CDNs such as Cloudflare can block AI crawlers before robots.txt is even read. The plugin cannot see those settings, so check them in your CDN panel too.', 'bearius-seo-light' ), 'info' );
		Bearius_SEO_UI::section_end();

		self::bot_view();

		Bearius_SEO_UI::section( __( 'Findings', 'bearius-seo-light' ), __( 'From the last full check of the home page and the latest post.', 'bearius-seo-light' ), true );
		Bearius_SEO_UI::issue_list( self::by_group( Bearius_SEO_Health::instance()->issues(), array( 'ai' ) ) );
		Bearius_SEO_UI::section_end();
	}

	/**
	 * Clickable preview of the sitemaps on the Sitemaps screen.
	 *
	 * @return void
	 */
	public static function render_sitemap_preview() {
		$sitemaps  = new Bearius_SEO_Sitemaps();
		$languages = Bearius_SEO_Languages::instance();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only preview.
		$view = isset( $_GET['sm_view'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_view'] ) ) : '';

		$lang_label = function ( $key ) use ( $languages ) {
			$lang = '' !== $key ? $languages->language( $key ) : null;
			return $lang ? $lang['name'] . ' (' . strtoupper( $key ) . ')' : __( 'All languages', 'bearius-seo-light' );
		};

		if ( Bearius_SEO_Sitemaps::sitemap_active() ) {
			$files = $sitemaps->files();

			Bearius_SEO_UI::section( __( 'Sitemap preview', 'bearius-seo-light' ), __( 'Every file of your sitemap, built from the same data search engines receive. Open a file to see its addresses as clickable links.', 'bearius-seo-light' ), true );

			if ( ! $files ) {
				Bearius_SEO_UI::callout( __( 'The sitemap is empty. Check the content types and archives selected above.', 'bearius-seo-light' ), 'warning' );
			} else {
				$total = array_sum( wp_list_pluck( $files, 'count' ) );

				echo '<div class="bs-stats bs-stats--compact">';
				echo '<div class="bs-stats__item"><b>' . esc_html( number_format_i18n( count( $files ) ) ) . '</b><span>' . esc_html__( 'files', 'bearius-seo-light' ) . '</span></div>';
				echo '<div class="bs-stats__item"><b>' . esc_html( number_format_i18n( $total ) ) . '</b><span>' . esc_html__( 'addresses', 'bearius-seo-light' ) . '</span></div>';
				echo '<div class="bs-stats__item"><b>' . esc_html( number_format_i18n( $languages->is_multilingual() ? count( $languages->languages() ) : 1 ) ) . '</b><span>' . esc_html__( 'languages', 'bearius-seo-light' ) . '</span></div>';
				echo '</div>';

				echo '<div class="bs-table-wrap"><table class="bs-table"><thead><tr><th>' . esc_html__( 'Language', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Content', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Addresses', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Last change', 'bearius-seo-light' ) . '</th><th></th></tr></thead><tbody>';

				foreach ( $files as $file ) {
					if ( 'home' === $file['kind'] ) {
						$content = __( 'Home pages', 'bearius-seo-light' );
					} elseif ( 'posts' === $file['kind'] ) {
						$object  = get_post_type_object( $file['subtype'] );
						$content = $object ? $object->labels->name : $file['subtype'];
					} else {
						$object  = get_taxonomy( $file['subtype'] );
						$content = $object ? $object->labels->name : $file['subtype'];
					}

					if ( $file['page'] > 1 ) {
						/* translators: 1: content name, 2: file number. */
						$content = sprintf( __( '%1$s, part %2$d', 'bearius-seo-light' ), $content, $file['page'] );
					}

					$name    = basename( (string) wp_parse_url( $file['url'], PHP_URL_PATH ) );
					$current = $name === $view;

					echo '<tr' . ( $current ? ' class="is-current"' : '' ) . '><td>' . esc_html( 'home' === $file['kind'] ? __( 'All languages', 'bearius-seo-light' ) : $lang_label( $file['lang'] ) ) . '</td>';
					echo '<td class="bs-table__title">' . esc_html( $content ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( (int) $file['count'] ) ) . '</td>';
					echo '<td>' . esc_html( $file['lastmod'] ? wp_date( get_option( 'date_format' ), strtotime( $file['lastmod'] . ' UTC' ) ) : '-' ) . '</td>';
					echo '<td class="bs-table__actions"><a href="' . esc_url( bearius_seo_admin_url( 'sitemaps', array( 'sm_view' => $name ) ) . '#bs-sm-view' ) . '">' . esc_html__( 'Show addresses', 'bearius-seo-light' ) . '</a> <a href="' . esc_url( $file['url'] ) . '" target="_blank" rel="noopener">XML</a></td></tr>';
				}

				echo '</tbody></table></div>';

				if ( '' !== $view ) {
					$rows = null;

					if ( 'sitemap-home.xml' === $view ) {
						$rows = $sitemaps->home_rows();
					} else {
						$part = $sitemaps->parse_part( $view );
						if ( $part ) {
							$rows = $sitemaps->part_rows( $part['kind'], $part['subtype'], $part['lang'], $part['page'] );
						}
					}

					echo '<div id="bs-sm-view" class="bs-list-head"><h3>' . esc_html( $view ) . '</h3>';

					if ( null === $rows ) {
						echo '<p>' . esc_html__( 'This file does not exist.', 'bearius-seo-light' ) . '</p></div>';
					} else {
						/* translators: %s: number of addresses. */
						echo '<p>' . esc_html( sprintf( _n( '%s address', '%s addresses', count( $rows ), 'bearius-seo-light' ), number_format_i18n( count( $rows ) ) ) ) . '</p></div>';
						echo '<ol class="bs-urls">';
						foreach ( $rows as $row ) {
							echo '<li><a href="' . esc_url( $row[0] ) . '" target="_blank" rel="noopener">' . esc_html( urldecode( $row[0] ) ) . '</a>' . ( $row[1] ? '<span>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $row[1] ) ) ) . '</span>' : '' ) . '</li>';
						}
						echo '</ol>';
					}
				}
			}

			Bearius_SEO_UI::section_end();
		}

		if ( Bearius_SEO_Sitemaps::news_active() ) {
			$keys = $languages->is_multilingual() ? array_keys( $languages->languages() ) : array( '' );

			Bearius_SEO_UI::section( __( 'Google News preview', 'bearius-seo-light' ), __( 'Articles from the last two days in each language file, newest first. The list refreshes within five minutes of publishing.', 'bearius-seo-light' ), true );

			foreach ( $keys as $key ) {
				$items = $sitemaps->news_items( $key );
				$url   = '' === $key ? Bearius_SEO_Sitemaps::news_url() : Bearius_SEO_Sitemaps::base_url() . 'news-sitemap-' . $key . '.xml';

				/* translators: %s: number of articles. */
				echo '<div class="bs-news-lang"><div class="bs-news-lang__head"><b>' . esc_html( $lang_label( $key ) ) . '</b><span class="bs-badge' . ( $items ? ' bs-badge--ok' : '' ) . '">' . esc_html( sprintf( _n( '%s article', '%s articles', count( $items ), 'bearius-seo-light' ), number_format_i18n( count( $items ) ) ) ) . '</span><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">XML</a></div>';

				if ( $items ) {
					echo '<ol class="bs-urls">';
					foreach ( array_slice( $items, 0, 10 ) as $item ) {
						echo '<li><a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $item['title'] ) . '</a><span>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['date'] ) ) ) . '</span></li>';
					}
					echo '</ol>';
					if ( count( $items ) > 10 ) {
						/* translators: %d: number of further articles. */
						echo '<p class="bs-muted">' . esc_html( sprintf( __( 'And %d more in the file.', 'bearius-seo-light' ), count( $items ) - 10 ) ) . '</p>';
					}
				} else {
					echo '<p class="bs-muted">' . esc_html__( 'No articles from the last two days.', 'bearius-seo-light' ) . '</p>';
				}

				echo '</div>';
			}

			Bearius_SEO_UI::section_end();
		}
	}

	/**
	 * Shows the text a crawler without JavaScript receives.
	 *
	 * @return void
	 */
	private static function bot_view() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only view of a page of this site.
		$choice = isset( $_GET['bv'] ) ? sanitize_key( wp_unslash( $_GET['bv'] ) ) : '';
		$custom = isset( $_GET['bv_url'] ) ? esc_url_raw( wp_unslash( $_GET['bv_url'] ) ) : '';
		// phpcs:enable

		$latest = get_posts(
			array(
				'post_type'      => in_array( 'post', bearius_seo_supported_post_types(), true ) ? 'post' : bearius_seo_supported_post_types(),
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'has_password'   => false,
			)
		);

		Bearius_SEO_UI::section( __( 'What AI crawlers read', 'bearius-seo-light' ), __( 'Most AI crawlers download the HTML and do not run JavaScript. This is the text they get, with menus and footers counted separately.', 'bearius-seo-light' ), true );

		echo '<form method="get" class="bs-filters"><input type="hidden" name="page" value="bearius-seo-light" /><input type="hidden" name="tab" value="ai" />';
		echo '<label><span>' . esc_html__( 'Page', 'bearius-seo-light' ) . '</span><select name="bv"><option value="home" ' . selected( $choice, 'home', false ) . '>' . esc_html__( 'Home page', 'bearius-seo-light' ) . '</option>';
		if ( $latest ) {
			echo '<option value="latest" ' . selected( $choice, 'latest', false ) . '>' . esc_html__( 'Latest post', 'bearius-seo-light' ) . '</option>';
		}
		echo '<option value="url" ' . selected( $choice, 'url', false ) . '>' . esc_html__( 'Address below', 'bearius-seo-light' ) . '</option></select></label>';
		echo '<label class="bs-filters__grow"><span>' . esc_html__( 'Address on this site', 'bearius-seo-light' ) . '</span><input class="bs-input" type="url" name="bv_url" value="' . esc_attr( $custom ) . '" placeholder="' . esc_attr( home_url( '/' ) ) . '" /></label>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Show', 'bearius-seo-light' ) . '</button></form>';

		if ( '' === $choice ) {
			echo '<p class="bs-muted">' . esc_html__( 'Pick a page to fetch it now.', 'bearius-seo-light' ) . '</p>';
			Bearius_SEO_UI::section_end();
			return;
		}

		$url = home_url( '/' );
		if ( 'latest' === $choice && $latest ) {
			$url = get_permalink( $latest[0] );
		} elseif ( 'url' === $choice && $custom ) {
			$url = $custom;
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( wp_parse_url( $url, PHP_URL_HOST ) !== $home_host ) {
			Bearius_SEO_UI::callout( __( 'Only addresses on this site can be checked.', 'bearius-seo-light' ), 'warning' );
			Bearius_SEO_UI::section_end();
			return;
		}

		$page = Bearius_SEO_Page_Scan::analyse( bearius_seo_remote_get( $url ), $url );

		if ( 200 !== $page['code'] || ! $page['parsed'] ) {
			/* translators: %d: HTTP status. */
			Bearius_SEO_UI::callout( sprintf( __( 'The page could not be read (HTTP %d). Some hosts block requests from the server to itself.', 'bearius-seo-light' ), (int) $page['code'] ), 'warning' );
			Bearius_SEO_UI::section_end();
			return;
		}

		$share = $page['text_total'] ? (int) round( 100 * $page['text_main'] / $page['text_total'] ) : 0;

		echo '<div class="bs-stats">';
		echo '<div class="bs-stats__item"><b>' . esc_html( number_format_i18n( $page['text_main'] ) ) . '</b><span>' . esc_html__( 'characters of content', 'bearius-seo-light' ) . '</span></div>';
		echo '<div class="bs-stats__item"><b>' . (int) $share . '%</b><span>' . esc_html__( 'of all text is content', 'bearius-seo-light' ) . '</span></div>';
		echo '<div class="bs-stats__item"><b>' . (int) count( $page['outline'] ) . '</b><span>' . esc_html__( 'headings', 'bearius-seo-light' ) . '</span></div>';
		echo '<div class="bs-stats__item"><b>' . esc_html( $page['types'] ? (string) count( array_unique( $page['types'] ) ) : '0' ) . '</b><span>' . esc_html__( 'schema types', 'bearius-seo-light' ) . '</span></div>';
		echo '</div>';

		echo '<div class="bs-meter" aria-hidden="true"><span style="width:' . (int) $share . '%"></span></div>';

		if ( $page['js_signals'] ) {
			Bearius_SEO_UI::callout( implode( ' ', array_map( 'esc_html', $page['js_signals'] ) ), 'warning' );
		}

		echo '<div class="bs-botview">';
		echo '<div class="bs-botview__col"><h3>' . esc_html__( 'Structure', 'bearius-seo-light' ) . '</h3><ul class="bs-outline">';
		foreach ( $page['outline'] as $heading ) {
			echo '<li class="bs-outline__' . esc_attr( $heading[0] ) . '"><span>' . esc_html( strtoupper( $heading[0] ) ) . '</span>' . esc_html( $heading[1] ) . '</li>';
		}
		if ( ! $page['outline'] ) {
			echo '<li class="bs-muted">' . esc_html__( 'No headings found.', 'bearius-seo-light' ) . '</li>';
		}
		echo '</ul>';
		echo '<p class="bs-muted">' . esc_html__( 'Publication date:', 'bearius-seo-light' ) . ' ' . esc_html( $page['published'] ? $page['published'] : __( 'not found', 'bearius-seo-light' ) ) . '<br />' . esc_html__( 'Schema:', 'bearius-seo-light' ) . ' ' . esc_html( $page['types'] ? implode( ', ', array_unique( $page['types'] ) ) : __( 'none', 'bearius-seo-light' ) ) . '</p></div>';
		echo '<div class="bs-botview__col bs-botview__col--text"><h3>' . esc_html__( 'Text without JavaScript', 'bearius-seo-light' ) . '</h3><div class="bs-botview__text">' . esc_html( $page['preview'] ) . ( bearius_seo_strlen( $page['preview'] ) >= 1600 ? '&hellip;' : '' ) . '</div></div>';
		echo '</div>';

		/**
		 * Prints add-on tools below the crawler view of a page.
		 *
		 * @param string $url  Checked address.
		 * @param array  $page Page analysis.
		 */
		do_action( 'bearius_seo_bot_view_after', $url, $page );

		Bearius_SEO_UI::section_end();
	}

	/**
	 * Import tab.
	 *
	 * @return void
	 */
	public static function render_import() {
		$sources = Bearius_SEO_Importer::sources();

		Bearius_SEO_UI::section( __( 'Import from another plugin', 'bearius-seo-light' ), __( 'Copies SEO titles, descriptions, noindex flags and social titles and images. Template variables are turned into plain text, and titles that only repeat the default are skipped. Nothing is deleted from the other plugin.', 'bearius-seo-light' ), true );

		if ( ! $sources ) {
			echo '<div class="bs-empty">' . Bearius_SEO_UI::icon( 'import' ) . '<p>' . esc_html__( 'No data from Yoast SEO, Rank Math or All in One SEO was found on this site.', 'bearius-seo-light' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			Bearius_SEO_UI::section_end();
			return;
		}

		echo '<div class="bs-import" data-bs-import>';
		echo '<fieldset class="bs-sources"><legend class="screen-reader-text">' . esc_html__( 'Source', 'bearius-seo-light' ) . '</legend>';
		$first = true;
		foreach ( $sources as $key => $source ) {
			/* translators: %s: number of entries. */
			echo '<label class="bs-source"><input type="radio" name="bs_import_source" value="' . esc_attr( $key ) . '" data-total="' . (int) $source[1] . '" ' . checked( $first, true, false ) . ' /><span><b>' . esc_html( $source[0] ) . '</b><small>' . esc_html( sprintf( _n( '%s entry with data', '%s entries with data', $source[1], 'bearius-seo-light' ), number_format_i18n( $source[1] ) ) ) . '</small></span></label>';
			$first = false;
		}
		echo '</fieldset>';

		echo '<label class="bs-inline-check"><input type="checkbox" name="bs_import_overwrite" value="1" /> ' . esc_html__( 'Replace values already set in Bearius SEO', 'bearius-seo-light' ) . '</label>';
		echo '<p class="bs-muted">' . esc_html__( 'Without this option only empty fields are filled, so running the import twice is safe.', 'bearius-seo-light' ) . '</p>';
		echo '<p><button type="button" class="button button-primary bs-button" data-bs-import-start>' . esc_html__( 'Start import', 'bearius-seo-light' ) . '</button></p>';
		echo '<div class="bs-progress" hidden><span></span></div><p class="bs-import__status" aria-live="polite"></p><ul class="bs-import__result"></ul>';
		echo '</div>';

		if ( Bearius_SEO_Compat::instance()->has_conflict() ) {
			Bearius_SEO_UI::callout( __( 'After the import, deactivate the other SEO plugin. The paused modules start working on their own.', 'bearius-seo-light' ), 'info' );
		}

		Bearius_SEO_UI::section_end();
	}

	/**
	 * Printable report.
	 *
	 * @return void
	 */
	public static function render_report() {
		$health  = Bearius_SEO_Health::instance();
		$issues  = $health->issues();
		$counts  = $health->counts( $issues );
		$stored  = $health->stored();
		$filters = Bearius_SEO_Content_Audit::filters( array( 'indexable' => 1 ) );
		$summary = Bearius_SEO_Content_Audit::summary( $filters );
		$types   = Bearius_SEO_Content_Audit::types();
		$groups  = array(
			array( __( 'Setup and crawling', 'bearius-seo-light' ), array( 'setup', 'crawl' ) ),
			array( __( 'Pages', 'bearius-seo-light' ), array( 'page' ) ),
			array( __( 'Performance', 'bearius-seo-light' ), array( 'performance' ) ),
			array( __( 'AI visibility', 'bearius-seo-light' ), array( 'ai' ) ),
		);

		/**
		 * Filters the report branding, used for white-label reports.
		 *
		 * @param array $branding Name, logo, color and whether to hide Bearius.
		 */
		$brand = wp_parse_args(
			(array) apply_filters( 'bearius_seo_report_branding', array() ),
			array(
				'name'  => '',
				'logo'  => '',
				'color' => '',
				'hide'  => false,
			)
		);

		$brand['color'] = (string) sanitize_hex_color( (string) $brand['color'] );

		echo '<div class="wrap bs-admin bs-report"' . ( $brand['color'] ? ' style="--bs-accent:' . esc_attr( $brand['color'] ) . ';--bs-accent-strong:' . esc_attr( $brand['color'] ) . '"' : '' ) . '>';
		echo '<div class="bs-report__bar"><a class="button" href="' . esc_url( bearius_seo_admin_url( 'audit' ) ) . '">' . esc_html__( 'Back to the audit', 'bearius-seo-light' ) . '</a> <button type="button" class="button button-primary" data-bs-print>' . esc_html__( 'Print or save as PDF', 'bearius-seo-light' ) . '</button></div>';

		echo '<header class="bs-report__head"><div>';
		if ( $brand['logo'] ) {
			echo '<img class="bs-report__logo" src="' . esc_url( $brand['logo'] ) . '" alt="' . esc_attr( $brand['name'] ) . '" />';
		}
		if ( $brand['name'] ) {
			/* translators: %s: agency name. */
			echo '<p class="bs-report__agency">' . esc_html( sprintf( __( 'Prepared by %s', 'bearius-seo-light' ), $brand['name'] ) ) . '</p>';
		}
		echo '<p class="bs-report__site">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</p><h1>' . esc_html__( 'SEO audit report', 'bearius-seo-light' ) . '</h1>';
		/* translators: %s: date. */
		echo '<p class="bs-muted">' . esc_html( sprintf( __( 'Prepared on %s', 'bearius-seo-light' ), wp_date( get_option( 'date_format' ) ) ) );
		if ( $stored['time'] ) {
			/* translators: %s: date and time. */
			echo ' ' . esc_html( sprintf( __( 'Pages scanned on %s.', 'bearius-seo-light' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $stored['time'] ) ) );
		}
		echo '</p></div>';
		Bearius_SEO_UI::score_ring( $health->score( $issues ), 110 );
		echo '</header>';

		echo '<ul class="bs-tally bs-tally--report">';
		foreach ( array( 'critical', 'warning', 'notice' ) as $severity ) {
			echo '<li class="bs-tally__item bs-tally__item--' . esc_attr( $severity ) . '"><b>' . (int) $counts[ $severity ] . '</b><span>' . esc_html( bearius_seo_severity_label( $severity ) ) . '</span></li>';
		}
		echo '</ul>';

		foreach ( $groups as $group ) {
			$list = self::by_group(
				array_values(
					array_filter(
						$issues,
						function ( $issue ) {
							return 'info' !== $issue['severity'];
						}
					)
				),
				$group[1]
			);
			echo '<section class="bs-report__sec"><h2>' . esc_html( $group[0] ) . '</h2>';
			Bearius_SEO_UI::issue_list( $list );
			echo '</section>';
		}

		echo '<section class="bs-report__sec"><h2>' . esc_html__( 'Content', 'bearius-seo-light' ) . '</h2><div class="bs-table-wrap"><table class="bs-table"><thead><tr><th>' . esc_html__( 'Finding', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Importance', 'bearius-seo-light' ) . '</th><th>' . esc_html__( 'Entries', 'bearius-seo-light' ) . '</th></tr></thead><tbody>';
		foreach ( $types as $key => $type ) {
			echo '<tr><td><b>' . esc_html( $type[0] ) . '</b><br /><span class="bs-muted">' . esc_html( $type[2] ) . '</span></td><td><span class="bs-badge bs-badge--' . esc_attr( $type[1] ) . '">' . esc_html( bearius_seo_severity_label( $type[1] ) ) . '</span></td><td>' . esc_html( number_format_i18n( $summary[ $key ] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';

		$foot = $brand['hide']
			? __( 'The score subtracts twenty points per critical problem, six per warning and two per suggestion. It is not a Google or PageSpeed score.', 'bearius-seo-light' )
			: __( 'The Bearius score adds twenty points of penalty per critical problem, six per warning and two per suggestion. It is our own measure, not a Google or PageSpeed score.', 'bearius-seo-light' );
		echo '<p class="bs-report__foot">' . esc_html( $foot ) . '</p>';
		echo '</div>';
	}
}
