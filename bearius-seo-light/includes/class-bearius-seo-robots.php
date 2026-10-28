<?php
/**
 * Virtual robots.txt.
 *
 * WordPress already serves a virtual robots.txt. This module keeps those
 * automatic rules, adds the sitemap lines, an optional policy for AI crawlers
 * and the site owner's own rules on top. It never replaces the file
 * entirely, which makes it hard to block the whole site by accident.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the robots.txt output.
 */
class Bearius_SEO_Robots {

	/**
	 * Hooks the output.
	 *
	 * @return void
	 */
	public function register() {
		if ( bearius_seo_module_active( 'robots' ) ) {
			add_filter( 'robots_txt', array( $this, 'filter' ), 100, 2 );
		}
	}

	/**
	 * Appends the AI policy and the custom rules.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function filter( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$blocks = array();
		$ai     = self::ai_block( (string) bearius_seo_get_setting( 'robots_ai', 'none' ) );

		if ( '' !== $ai ) {
			$blocks[] = $ai;
		}

		$custom = trim( (string) bearius_seo_get_setting( 'robots_custom' ) );
		if ( '' !== $custom ) {
			$blocks[] = "# Custom rules\n" . $custom;
		}

		if ( ! $blocks ) {
			return $output;
		}

		return rtrim( $output ) . "\n\n" . implode( "\n\n", $blocks ) . "\n";
	}

	/**
	 * Returns the known AI crawlers with their owner and purpose.
	 *
	 * Purpose is one of training, search or user (fetches made when a
	 * person asks an assistant to open a page).
	 *
	 * @return array[]
	 */
	public static function ai_bots() {
		return array(
			'GPTBot'             => array( 'OpenAI', 'training' ),
			'OAI-SearchBot'      => array( 'OpenAI', 'search' ),
			'ChatGPT-User'       => array( 'OpenAI', 'user' ),
			'ClaudeBot'          => array( 'Anthropic', 'training' ),
			'Claude-SearchBot'   => array( 'Anthropic', 'search' ),
			'Claude-User'        => array( 'Anthropic', 'user' ),
			'PerplexityBot'      => array( 'Perplexity', 'search' ),
			'Perplexity-User'    => array( 'Perplexity', 'user' ),
			'Google-Extended'    => array( 'Google', 'training' ),
			'Applebot-Extended'  => array( 'Apple', 'training' ),
			'Meta-ExternalAgent' => array( 'Meta', 'training' ),
			'CCBot'              => array( 'Common Crawl', 'training' ),
			'Bytespider'         => array( 'ByteDance', 'training' ),
		);
	}

	/**
	 * Returns the AI crawler presets with a plain explanation of each.
	 *
	 * @return array[] Label and description per preset.
	 */
	public static function presets() {
		return array(
			'none'     => array(
				__( 'Allow all', 'bearius-seo-light' ),
				__( 'Nothing changes. Every AI crawler may visit, as before you installed the plugin.', 'bearius-seo-light' ),
			),
			'training' => array(
				__( 'Block training, allow AI search', 'bearius-seo-light' ),
				__( 'Your texts are not collected to train new AI models, but assistants such as ChatGPT, Claude or Perplexity can still find, quote and link your pages.', 'bearius-seo-light' ),
			),
			'all'      => array(
				__( 'Block all AI crawlers', 'bearius-seo-light' ),
				__( 'No AI crawler may visit. Your pages will not be quoted or linked in AI answers. Google and Bing search are not affected.', 'bearius-seo-light' ),
			),
			'custom'   => array(
				__( 'Custom', 'bearius-seo-light' ),
				__( 'Your own choice for each crawler in the table below.', 'bearius-seo-light' ),
			),
		);
	}

	/**
	 * Returns the crawlers blocked right now, according to the saved settings.
	 *
	 * @return string[]
	 */
	public static function blocked_bots() {
		return self::preset_bots( (string) bearius_seo_get_setting( 'robots_ai', 'none' ) );
	}

	/**
	 * Returns the crawlers blocked by a preset.
	 *
	 * @param string $preset none, training, all or custom.
	 * @return string[]
	 */
	public static function preset_bots( $preset ) {
		if ( 'none' === $preset ) {
			return array();
		}

		if ( 'custom' === $preset ) {
			return array_values( array_intersect( array_keys( self::ai_bots() ), (array) bearius_seo_get_setting( 'robots_ai_blocked', array() ) ) );
		}

		$bots = array();
		foreach ( self::ai_bots() as $agent => $info ) {
			if ( 'all' === $preset || 'training' === $info[1] ) {
				$bots[] = $agent;
			}
		}

		return $bots;
	}

	/**
	 * Builds the robots.txt block for a preset.
	 *
	 * Only disallow groups are written. An explicit allow group for a
	 * crawler would make it ignore the general rules, wp-admin included.
	 *
	 * @param string $preset Preset key.
	 * @return string
	 */
	public static function ai_block( $preset ) {
		$bots = self::preset_bots( $preset );

		if ( ! $bots ) {
			return '';
		}

		$headings = array(
			'all'      => '# AI crawlers: blocked',
			'training' => '# AI crawlers: training blocked, search and user requests allowed',
			'custom'   => '# AI crawlers: custom policy',
		);
		$heading  = isset( $headings[ $preset ] ) ? $headings[ $preset ] : '# AI crawlers';
		$lines   = array( $heading );

		foreach ( $bots as $agent ) {
			$lines[] = 'User-agent: ' . $agent;
		}

		$lines[] = 'Disallow: /';

		return implode( "\n", $lines );
	}

	/**
	 * Removes rules that would block the whole site for every crawler or for Google.
	 *
	 * @param string $text Custom rules.
	 * @return array {
	 *     @type string $clean   Cleaned rules.
	 *     @type int    $removed Number of removed lines.
	 * }
	 */
	public static function sanitize_custom( $text ) {
		$text    = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$lines   = explode( "\n", $text );
		$out     = array();
		$agents  = array();
		$removed = 0;
		$in_rule = false;

		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );

			if ( preg_match( '/^user-agent\s*:\s*(.+)$/i', $line, $match ) ) {
				if ( $in_rule ) {
					$agents  = array();
					$in_rule = false;
				}
				$agents[] = strtolower( trim( $match[1] ) );
				$out[]    = $line;
				continue;
			}

			if ( preg_match( '/^disallow\s*:\s*\/\s*$/i', $line ) ) {
				$in_rule = true;
				if ( array_intersect( $agents, array( '*', 'googlebot', 'bingbot' ) ) ) {
					$removed++;
					continue;
				}
			} elseif ( preg_match( '/^(allow|disallow|crawl-delay|sitemap)\s*:/i', $line ) ) {
				$in_rule = true;
			}

			$out[] = $line;
		}

		return array(
			'clean'   => trim( implode( "\n", $out ) ),
			'removed' => $removed,
		);
	}

	/**
	 * Builds the robots.txt exactly as WordPress would serve it.
	 *
	 * @return string
	 */
	public static function preview() {
		$public = bearius_seo_site_is_public();
		$output = "User-agent: *\n";

		if ( ! $public ) {
			$output .= "Disallow: /\n";
		} else {
			$site_url = wp_parse_url( site_url() );
			$path     = ! empty( $site_url['path'] ) ? $site_url['path'] : '';
			$output  .= "Disallow: $path/wp-admin/\n";
			$output  .= "Allow: $path/wp-admin/admin-ajax.php\n";
		}

		return (string) apply_filters( 'robots_txt', $output, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter.
	}

	/**
	 * Tells whether a physical robots.txt file shadows the virtual one.
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return file_exists( trailingslashit( get_home_path() ) . 'robots.txt' );
	}

	/**
	 * Tells whether the site lives in a subdirectory, where robots.txt has no effect.
	 *
	 * @return bool
	 */
	public static function in_subdirectory() {
		$path = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );

		return '' !== trim( $path, '/' );
	}

	/**
	 * Evaluates whether a crawler may fetch a path, following the robots.txt standard.
	 *
	 * The most specific group wins; inside it the longest matching rule
	 * decides and allow wins a tie.
	 *
	 * @param string $robots Robots.txt content.
	 * @param string $agent  Crawler product token.
	 * @param string $path   Path to test.
	 * @return bool
	 */
	public static function is_allowed( $robots, $agent, $path = '/' ) {
		$groups  = array();
		$current = array();
		$rules   = array();
		$last    = '';

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $robots ) as $line ) {
			$line = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $field, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$field                 = strtolower( $field );

			if ( 'user-agent' === $field ) {
				if ( 'rule' === $last ) {
					foreach ( $current as $name ) {
						$groups[ $name ] = array_merge( isset( $groups[ $name ] ) ? $groups[ $name ] : array(), $rules );
					}
					$current = array();
					$rules   = array();
				}
				$current[] = strtolower( $value );
				$last      = 'agent';
			} elseif ( 'allow' === $field || 'disallow' === $field ) {
				$rules[] = array( $field, $value );
				$last    = 'rule';
			}
		}

		foreach ( $current as $name ) {
			$groups[ $name ] = array_merge( isset( $groups[ $name ] ) ? $groups[ $name ] : array(), $rules );
		}

		$agent = strtolower( $agent );
		$match = isset( $groups[ $agent ] ) ? $groups[ $agent ] : ( isset( $groups['*'] ) ? $groups['*'] : array() );

		$best_length = -1;
		$allowed     = true;

		foreach ( $match as $rule ) {
			list( $type, $pattern ) = $rule;

			if ( '' === $pattern ) {
				continue;
			}

			$regex = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $pattern, '#' ) ) . '#';

			if ( preg_match( $regex, $path ) ) {
				$length = strlen( $pattern );

				if ( $length > $best_length || ( $length === $best_length && 'allow' === $type ) ) {
					$best_length = $length;
					$allowed     = 'allow' === $type;
				}
			}
		}

		return $allowed;
	}
}
