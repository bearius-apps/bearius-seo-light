<?php
/**
 * Admin interface building blocks.
 *
 * Shares tokens, sections, switches and the save bar with the other Bearius
 * plugins, so the settings screens look like one family.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small rendering helpers for the settings screen.
 */
class Bearius_SEO_UI {

	/**
	 * Returns an outline icon.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function icon( $name ) {
		$paths = array(
			'overview'      => '<path d="M4.5 15.5a7.5 7.5 0 1 1 15 0"/><path d="M12 15.5l3.5-4.5"/><circle cx="12" cy="15.5" r="1.2"/>',
			'publisher'     => '<path d="M4.5 20.5v-12l7.5-4 7.5 4v12"/><path d="M9 20.5v-6h6v6M3 20.5h18"/>',
			'meta'          => '<path d="M4 5.5h9.5l6.5 6.5-7.5 7.5L4 13z"/><circle cx="8.5" cy="9.5" r="1.4"/>',
			'sitemaps'      => '<rect x="9" y="3.5" width="6" height="4.5" rx="1"/><rect x="3.5" y="16" width="6" height="4.5" rx="1"/><rect x="14.5" y="16" width="6" height="4.5" rx="1"/><path d="M12 8v4M6.5 16v-4h11v4"/>',
			'robots'        => '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4.5V8M9.5 13h.01M14.5 13h.01M9.5 16.5h5"/>',
			'indexing'      => '<path d="M13 3.5L6 13.5h5l-1 7 7-10h-5z"/>',
			'site'          => '<path d="M5 18.5a1.5 1.5 0 1 0 0 .01"/><path d="M4.5 11.5a8 8 0 0 1 8 8"/><path d="M4.5 4.5a15 15 0 0 1 15 15"/>',
			'audit'         => '<path d="M9 4.5h6M9 4.5a1.5 1.5 0 0 0 0 3h6a1.5 1.5 0 0 0 0-3"/><path d="M15 6h2.5a1 1 0 0 1 1 1v12.5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1H9"/><path d="M9 14l2 2 4-4.5"/>',
			'ai'            => '<path d="M12 3.5l1.9 4.6 4.6 1.9-4.6 1.9L12 16.5l-1.9-4.6L5.5 10l4.6-1.9z"/><path d="M18.5 15.5l.8 1.9 1.9.8-1.9.8-.8 1.9-.8-1.9-1.9-.8 1.9-.8z"/>',
			'import'        => '<path d="M12 3.5v11M7.5 10l4.5 4.5 4.5-4.5"/><path d="M4 15.5v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
			'notifications' => '<path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 2h-14z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
			'check'         => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			'alert'         => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
			'info'          => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/>',
			'refresh'       => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5v4h-4"/>',
			'external'      => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'print'         => '<path d="M7 9V4.5h10V9"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 14h10v6H7z"/>',
			'lock'          => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
			'globe'         => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.6 3.6 5.4 3.6 8.5s-1.2 5.9-3.6 8.5c-2.4-2.6-3.6-5.4-3.6-8.5S9.6 6.1 12 3.5z"/>',
			'breadcrumbs'   => '<path d="M4 12h4M10 12h4M16 12h4"/><path d="M7 9l3 3-3 3M13 9l3 3-3 3"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			/**
			 * Filters the markup of an icon unknown to the plugin, used by add-ons for their tabs.
			 *
			 * @param string $svg  Icon markup.
			 * @param string $name Icon name.
			 */
			return (string) apply_filters( 'bearius_seo_icon', '', $name );
		}

		return '<svg class="bs-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Returns the brand mark.
	 *
	 * @return string
	 */
	public static function mark() {
		return '<span class="bs-mark" aria-hidden="true"><svg width="30" height="30" viewBox="-4 -4 56 56" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" focusable="false"><ellipse cx="11.5" cy="22" rx="5.2" ry="6.6" transform="rotate(-22 11.5 22)"/><ellipse cx="19.8" cy="13.8" rx="5" ry="6.9" transform="rotate(-8 19.8 13.8)"/><ellipse cx="29.2" cy="13.8" rx="5" ry="6.9" transform="rotate(8 29.2 13.8)"/><ellipse cx="37.5" cy="22" rx="5.2" ry="6.6" transform="rotate(22 37.5 22)"/><path d="M24.5 23.5c5.6 0 11.2 4.6 11.2 10.1c0 5.1 -4.6 8.2 -11.2 8.2c-6.6 0 -11.2 -3.1 -11.2 -8.2c0 -5.5 5.6 -10.1 11.2 -10.1z"/></svg></span>';
	}

	/**
	 * Returns the navigation items.
	 *
	 * @return array
	 */
	public static function nav_items() {
		$items = array(
			'overview'      => __( 'Overview', 'bearius-seo-light' ),
			'publisher'     => __( 'Publisher', 'bearius-seo-light' ),
			'meta'          => __( 'Meta and schema', 'bearius-seo-light' ),
			'sitemaps'      => __( 'Sitemaps', 'bearius-seo-light' ),
			'robots'        => __( 'Robots.txt', 'bearius-seo-light' ),
			'site'          => __( 'Feeds and comments', 'bearius-seo-light' ),
			'indexing'      => __( 'Indexing', 'bearius-seo-light' ),
			'audit'         => __( 'Audit', 'bearius-seo-light' ),
			'ai'            => __( 'AI visibility', 'bearius-seo-light' ),
			'import'        => __( 'Import', 'bearius-seo-light' ),
			'notifications' => __( 'Notifications', 'bearius-seo-light' ),
		);

		/**
		 * Filters the settings screen navigation, used by add-ons to add their tabs.
		 *
		 * @param array $items Tab key => label.
		 */
		return (array) apply_filters( 'bearius_seo_nav_items', $items );
	}

	/**
	 * Prints a score ring.
	 *
	 * @param int $score Score from 0 to 100.
	 * @param int $size  Diameter in pixels.
	 * @return void
	 */
	public static function score_ring( $score, $size = 120 ) {
		$score  = max( 0, min( 100, (int) $score ) );
		$tone   = $score >= 90 ? 'ok' : ( $score >= 60 ? 'warning' : 'critical' );
		$radius = 42;
		$length = 2 * M_PI * $radius;
		$offset = $length * ( 1 - $score / 100 );

		printf(
			'<span class="bs-ring bs-tone--%1$s" style="width:%2$dpx;height:%2$dpx" role="img" aria-label="%3$s"><svg viewBox="0 0 100 100" focusable="false"><circle class="bs-ring__track" cx="50" cy="50" r="%4$d"/><circle class="bs-ring__value" cx="50" cy="50" r="%4$d" stroke-dasharray="%5$s" stroke-dashoffset="%6$s"/></svg><span class="bs-ring__num">%7$d</span></span>',
			esc_attr( $tone ),
			(int) $size,
			/* translators: %d: score. */
			esc_attr( sprintf( __( 'Bearius score %d out of 100', 'bearius-seo-light' ), $score ) ),
			(int) $radius,
			esc_attr( number_format( $length, 2, '.', '' ) ),
			esc_attr( number_format( $offset, 2, '.', '' ) ),
			(int) $score
		);
	}

	/**
	 * Opens the page shell: header, navigation and main column.
	 *
	 * @param string $current Current tab.
	 * @param string $sub     Subtitle.
	 * @return void
	 */
	public static function open( $current, $sub ) {
		$health = Bearius_SEO_Health::instance();
		$counts = $health->counts();

		echo '<div class="wrap bs-admin bs-admin--' . esc_attr( $current ) . '">';
		echo '<header class="bs-head">' . self::mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup.
		echo '<div class="bs-head__titles"><h1 class="bs-head__title">Bearius SEO Light</h1><p class="bs-head__sub">' . esc_html( $sub ) . '</p></div>';
		echo '<div class="bs-head__tools">';

		$languages = Bearius_SEO_Languages::instance();
		if ( $languages->is_multilingual() ) {
			$count = count( $languages->languages() );
			/* translators: 1: multilingual plugin name, 2: number of languages. */
			echo '<span class="bs-pill">' . self::icon( 'globe' ) . esc_html( sprintf( _n( '%1$s, %2$d language', '%1$s, %2$d languages', $count, 'bearius-seo-light' ), $languages->provider_label(), $count ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		}

		if ( $counts['critical'] ) {
			/* translators: %d: number of problems. */
			echo '<a class="bs-pill bs-pill--critical" href="' . esc_url( bearius_seo_admin_url( 'audit' ) ) . '">' . esc_html( sprintf( _n( '%d critical', '%d critical', $counts['critical'], 'bearius-seo-light' ), $counts['critical'] ) ) . '</a>';
		}

		echo '<span class="bs-head__ver">' . esc_html( BEARIUS_SEO_VERSION ) . '</span></div></header>';
		echo '<hr class="wp-header-end">';

		// Top level pages do not get the automatic "Settings saved" notice of the Settings menu.
		settings_errors();

		echo '<div class="bs-layout"><nav class="bs-nav" aria-label="' . esc_attr__( 'Settings sections', 'bearius-seo-light' ) . '">';

		foreach ( self::nav_items() as $key => $label ) {
			$badge = '';
			if ( 'audit' === $key && ( $counts['critical'] + $counts['warning'] ) > 0 ) {
				$badge = '<span class="bs-count' . ( $counts['critical'] ? '' : ' bs-count--warning' ) . '">' . (int) ( $counts['critical'] + $counts['warning'] ) . '</span>';
			}

			printf(
				'<a class="bs-nav__item%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span>%6$s</a>',
				$key === $current ? ' is-active' : '',
				esc_url( bearius_seo_admin_url( $key ) ),
				$key === $current ? ' aria-current="page"' : '',
				self::icon( $key ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
				esc_html( $label ),
				$badge // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from an integer.
			);
		}

		echo '</nav><div class="bs-main">';
	}

	/**
	 * Closes the page shell.
	 *
	 * @return void
	 */
	public static function close() {
		echo '</div></div></div>';
	}

	/**
	 * Opens a form that saves one tab of settings.
	 *
	 * @param string $tab Tab key.
	 * @return void
	 */
	public static function form_open( $tab ) {
		echo '<form method="post" action="options.php" class="bs-form" data-bs-form>';
		settings_fields( 'bearius_seo_settings_group' );
		echo '<input type="hidden" name="' . esc_attr( BEARIUS_SEO_OPTION ) . '[_tab]" value="' . esc_attr( $tab ) . '" />';
	}

	/**
	 * Closes a settings form with the save bar.
	 *
	 * @return void
	 */
	public static function form_close() {
		echo '<div class="bs-savebar"><span class="bs-savebar__state" data-bs-state data-dirty="' . esc_attr__( 'Unsaved changes', 'bearius-seo-light' ) . '">' . esc_html__( 'All changes saved', 'bearius-seo-light' ) . '</span>';
		submit_button( __( 'Save changes', 'bearius-seo-light' ), 'primary bs-button', 'submit', false );
		echo '</div></form>';
	}

	/**
	 * Opens a section with a title column.
	 *
	 * @param string $title Title.
	 * @param string $desc  Description.
	 * @param bool   $pad   Whether the panel gets inner padding.
	 * @return void
	 */
	public static function section( $title, $desc = '', $pad = false ) {
		echo '<section class="bs-sec"><div class="bs-sec__head"><h2 class="bs-sec__title">' . esc_html( $title ) . '</h2>';
		if ( $desc ) {
			echo '<p class="bs-sec__desc">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</div><div class="bs-sec__panel' . ( $pad ? ' bs-sec__panel--pad' : '' ) . '">';
	}

	/**
	 * Closes a section.
	 *
	 * @return void
	 */
	public static function section_end() {
		echo '</div></section>';
	}

	/**
	 * Returns the form field name for a setting.
	 *
	 * @param string $key    Setting key.
	 * @param string $suffix Extra brackets, for example [] for lists.
	 * @return string
	 */
	public static function name( $key, $suffix = '' ) {
		return BEARIUS_SEO_OPTION . '[' . $key . ']' . $suffix;
	}

	/**
	 * Prints a switch row.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param string $help    Help text.
	 * @param string $blocked Name of a plugin that blocks this option.
	 * @param string $note    Note to show instead of the default one for $blocked.
	 * @return void
	 */
	public static function switch_row( $key, $label, $help = '', $blocked = '', $note = '' ) {
		$id = 'bs-' . $key;
		echo '<div class="bs-field bs-field--switch"><div class="bs-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		if ( $blocked ) {
			echo '<p class="bs-field__note">' . esc_html( '' !== $note ? $note : Bearius_SEO_Compat::instance()->note_for( $blocked ) ) . '</p>';
		}
		echo '</div><div class="bs-field__control">';
		echo '<input type="hidden" name="' . esc_attr( self::name( $key ) ) . '" value="0" />';
		echo '<label class="bs-switch"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( (bool) bearius_seo_get_setting( $key ), true, false ) . ' /><span class="bs-switch__track"></span></label>';
		echo '</div></div>';
	}

	/**
	 * Prints a text input row.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param string $help        Help text.
	 * @param string $type        Input type.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	public static function text_row( $key, $label, $help = '', $type = 'text', $placeholder = '' ) {
		$id = 'bs-' . $key;
		echo '<div class="bs-field"><div class="bs-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><input class="bs-input" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) bearius_seo_get_setting( $key ) ) . '" placeholder="' . esc_attr( $placeholder ) . '" /></div></div>';
	}

	/**
	 * Prints a textarea row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @param int    $rows  Rows.
	 * @param string $class Extra class.
	 * @return void
	 */
	public static function textarea_row( $key, $label, $help = '', $rows = 3, $class = '' ) {
		$id = 'bs-' . $key;
		echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><textarea class="bs-input ' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" rows="' . (int) $rows . '">' . esc_textarea( (string) bearius_seo_get_setting( $key ) ) . '</textarea></div></div>';
	}

	/**
	 * Prints a number row.
	 *
	 * @param string $key    Setting key.
	 * @param string $label  Label.
	 * @param string $help   Help text.
	 * @param int    $min    Minimum.
	 * @param int    $max    Maximum.
	 * @param string $suffix Unit shown after the input.
	 * @return void
	 */
	public static function number_row( $key, $label, $help, $min, $max, $suffix = '' ) {
		$id = 'bs-' . $key;
		echo '<div class="bs-field"><div class="bs-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><span class="bs-affix"><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) (int) bearius_seo_get_setting( $key ) ) . '" min="' . (int) $min . '" max="' . (int) $max . '" />';
		if ( $suffix ) {
			echo '<span class="bs-affix__txt">' . esc_html( $suffix ) . '</span>';
		}
		echo '</span></div></div>';
	}

	/**
	 * Prints an image picker row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @return void
	 */
	public static function image_row( $key, $label, $help = '' ) {
		$id    = 'bs-' . $key;
		$value = (string) bearius_seo_get_setting( $key );
		echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><div class="bearius-seo-image-row"><span class="bearius-seo-thumb"><img alt="" decoding="async" data-bs-true-color' . ( $value ? ' src="' . esc_url( $value ) . '"' : ' hidden' ) . ' /></span><span class="bearius-seo-image-controls">';
		echo '<input type="url" class="bs-input bearius-seo-image-field" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_url( $value ) . '" />';
		echo '<span class="bearius-seo-image-buttons"><button type="button" class="button bearius-seo-image-pick">' . esc_html__( 'Select image', 'bearius-seo-light' ) . '</button> <button type="button" class="button-link bearius-seo-image-clear">' . esc_html__( 'Clear', 'bearius-seo-light' ) . '</button></span></span></div></div></div>';
	}

	/**
	 * Prints a row of checkable chips.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 * @param string $help     Help text.
	 * @param array  $options  Value => label.
	 * @param array  $selected Selected values.
	 * @return void
	 */
	public static function chips_row( $key, $label, $help, $options, $selected ) {
		echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><span class="bs-field__name">' . esc_html( $label ) . '</span>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><input type="hidden" name="' . esc_attr( self::name( $key, '[]' ) ) . '" value="" /><div class="bs-chips">';
		foreach ( $options as $value => $text ) {
			echo '<label class="bs-chip"><input type="checkbox" name="' . esc_attr( self::name( $key, '[]' ) ) . '" value="' . esc_attr( $value ) . '" ' . checked( in_array( (string) $value, array_map( 'strval', (array) $selected ), true ), true, false ) . ' /><span>' . esc_html( $text ) . '</span></label>';
		}
		echo '</div></div></div>';
	}

	/**
	 * Prints a segmented choice row.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param string $help    Help text.
	 * @param array  $options Value => label.
	 * @return void
	 */
	public static function segmented_row( $key, $label, $help, $options ) {
		$current = (string) bearius_seo_get_setting( $key );
		echo '<div class="bs-field bs-field--stack"><div class="bs-field__label"><span class="bs-field__name">' . esc_html( $label ) . '</span>';
		if ( $help ) {
			echo '<p class="bs-field__help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div><div class="bs-field__control"><div class="bs-seg" role="radiogroup">';
		foreach ( $options as $value => $text ) {
			echo '<label class="bs-seg__opt"><input type="radio" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $value ) . '" ' . checked( $current, (string) $value, false ) . ' /><span>' . esc_html( $text ) . '</span></label>';
		}
		echo '</div></div></div>';
	}

	/**
	 * Prints a callout.
	 *
	 * @param string $text Text, may contain basic markup.
	 * @param string $tone info, warning, critical or ok.
	 * @return void
	 */
	public static function callout( $text, $tone = 'info' ) {
		$icon = 'ok' === $tone ? 'check' : ( 'info' === $tone ? 'info' : 'alert' );
		echo '<div class="bs-callout bs-callout--' . esc_attr( $tone ) . '">' . self::icon( $icon ) . '<p>' . wp_kses_post( $text ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	}

	/**
	 * Prints an issue list.
	 *
	 * @param array[] $issues Issues.
	 * @return void
	 */
	public static function issue_list( $issues ) {
		if ( ! $issues ) {
			echo '<div class="bs-empty">' . self::icon( 'check' ) . '<p>' . esc_html__( 'Nothing to fix here.', 'bearius-seo-light' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			return;
		}

		echo '<ul class="bs-issues">';
		foreach ( $issues as $issue ) {
			echo '<li class="bs-issue bs-issue--' . esc_attr( $issue['severity'] ) . '">';
			echo '<span class="bs-badge bs-badge--' . esc_attr( $issue['severity'] ) . '">' . esc_html( bearius_seo_severity_label( $issue['severity'] ) ) . '</span>';
			echo '<div class="bs-issue__body"><p class="bs-issue__title">' . esc_html( $issue['title'] ) . '</p>';
			if ( $issue['detail'] ) {
				echo '<p class="bs-issue__detail">' . esc_html( $issue['detail'] ) . '</p>';
			}
			$fix = Bearius_SEO_Fixes::for_issue( $issue );
			if ( $fix ) {
				echo '<p class="bs-fix"><a class="button button-small bs-fix__button" href="' . esc_url( $fix['url'] ) . '"' . ( $fix['confirm'] ? ' data-bs-confirm="' . esc_attr( $fix['confirm'] ) . '"' : '' ) . '>' . self::icon( 'check' ) . '<span>' . esc_html__( 'Fix', 'bearius-seo-light' ) . ': ' . esc_html( $fix['label'] ) . '</span></a><span class="bs-fix__detail">' . esc_html( $fix['detail'] ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			}
			echo '</div>';
			if ( $issue['link'] ) {
				echo '<a class="bs-issue__link" href="' . esc_url( $issue['link'] ) . '">' . esc_html__( 'Open', 'bearius-seo-light' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
