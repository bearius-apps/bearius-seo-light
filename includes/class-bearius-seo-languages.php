<?php
/**
 * Adapter for multilingual plugins.
 *
 * Bearius Multilingual, Polylang and WPML keep every translation as its own
 * post, while TranslatePress keeps one post and translates it on output.
 * This class hides those differences behind one small interface used by the
 * sitemaps, IndexNow and the Open Graph tags.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the active multilingual plugin and exposes its languages.
 */
class Bearius_SEO_Languages {

	/**
	 * Singleton instance.
	 *
	 * @var Bearius_SEO_Languages|null
	 */
	private static $instance = null;

	/**
	 * Detected provider key.
	 *
	 * @var string|null
	 */
	private $provider = null;

	/**
	 * Cached language list.
	 *
	 * @var array|null
	 */
	private $languages = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Bearius_SEO_Languages
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the provider key: bml, polylang, wpml, translatepress or an empty string.
	 *
	 * @return string
	 */
	public function provider() {
		if ( null !== $this->provider ) {
			return $this->provider;
		}

		$this->provider = '';

		if ( class_exists( 'BML_Settings' ) && method_exists( 'BML_Settings', 'routing_enabled' ) && BML_Settings::routing_enabled() ) {
			$this->provider = 'bml';
		} elseif ( function_exists( 'pll_languages_list' ) && function_exists( 'PLL' ) ) {
			$this->provider = 'polylang';
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) && has_filter( 'wpml_active_languages' ) ) {
			$this->provider = 'wpml';
		} elseif ( class_exists( 'TRP_Translate_Press' ) ) {
			$this->provider = 'translatepress';
		}

		return $this->provider;
	}

	/**
	 * Returns the name of the multilingual plugin.
	 *
	 * @return string
	 */
	public function provider_label() {
		$labels = array(
			'bml'            => 'Bearius Multilingual',
			'polylang'       => 'Polylang',
			'wpml'           => 'WPML',
			'translatepress' => 'TranslatePress',
		);

		$provider = $this->provider();

		return isset( $labels[ $provider ] ) ? $labels[ $provider ] : '';
	}

	/**
	 * Tells whether the site runs in more than one language.
	 *
	 * @return bool
	 */
	public function is_multilingual() {
		return count( $this->languages() ) > 1;
	}

	/**
	 * Tells whether every language shares the same posts.
	 *
	 * @return bool
	 */
	public function shares_posts() {
		return 'translatepress' === $this->provider();
	}

	/**
	 * Returns the languages keyed by a short URL safe key.
	 *
	 * @return array[] Each item has key, locale, name and default.
	 */
	public function languages() {
		if ( null !== $this->languages ) {
			return $this->languages;
		}

		$list = array();

		switch ( $this->provider() ) {
			case 'bml':
				$default = BML_Settings::default_lang();
				foreach ( BML_Settings::languages() as $code => $lang ) {
					$list[ $code ] = array(
						'code'    => $code,
						'locale'  => $lang['locale'],
						'name'    => $lang['name'],
						'default' => $code === $default,
					);
				}
				break;

			case 'polylang':
				$default = (string) pll_default_language( 'slug' );
				$model   = PLL()->model;
				if ( is_object( $model ) && method_exists( $model, 'get_languages_list' ) ) {
					foreach ( (array) $model->get_languages_list() as $lang ) {
						$list[ $lang->slug ] = array(
							'code'    => $lang->slug,
							'locale'  => $lang->locale,
							'name'    => $lang->name,
							'default' => $lang->slug === $default,
						);
					}
				}
				break;

			case 'wpml':
				$default = (string) apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
				$active  = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
				foreach ( (array) $active as $code => $lang ) {
					$list[ $code ] = array(
						'code'    => $code,
						'locale'  => ! empty( $lang['default_locale'] ) ? $lang['default_locale'] : $code,
						'name'    => ! empty( $lang['native_name'] ) ? $lang['native_name'] : $code,
						'default' => $code === $default,
					);
				}
				break;

			case 'translatepress':
				$settings  = get_option( 'trp_settings', array() );
				$default   = isset( $settings['default-language'] ) ? $settings['default-language'] : get_locale();
				$published = ! empty( $settings['publish-languages'] ) ? (array) $settings['publish-languages'] : array( $default );
				$slugs     = isset( $settings['url-slugs'] ) ? (array) $settings['url-slugs'] : array();
				$names     = $this->translatepress_names( $published );

				foreach ( $published as $locale ) {
					$code          = isset( $slugs[ $locale ] ) ? $slugs[ $locale ] : strtolower( substr( $locale, 0, 2 ) );
					$list[ $code ] = array(
						'code'    => $code,
						'locale'  => $locale,
						'name'    => isset( $names[ $locale ] ) ? $names[ $locale ] : $locale,
						'default' => $locale === $default,
					);
				}
				break;
		}

		$clean = array();
		foreach ( $list as $code => $lang ) {
			$key = $this->key( $code );
			if ( '' !== $key ) {
				$lang['key']   = $key;
				$clean[ $key ] = $lang;
			}
		}

		if ( count( $clean ) < 2 ) {
			$locale = get_locale();
			$clean  = array(
				'' => array(
					'key'     => '',
					'code'    => '',
					'locale'  => $locale,
					'name'    => $locale,
					'default' => true,
				),
			);
		}

		$this->languages = $clean;

		return $this->languages;
	}

	/**
	 * Normalises a language code into a key safe for file names.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public function key( $code ) {
		return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $code ) ), '-' );
	}

	/**
	 * Returns the key of the default language.
	 *
	 * @return string
	 */
	public function default_key() {
		foreach ( $this->languages() as $key => $lang ) {
			if ( $lang['default'] ) {
				return $key;
			}
		}

		$keys = array_keys( $this->languages() );

		return (string) reset( $keys );
	}

	/**
	 * Returns one language by key.
	 *
	 * @param string $key Language key.
	 * @return array|null
	 */
	public function language( $key ) {
		$languages = $this->languages();

		return isset( $languages[ $key ] ) ? $languages[ $key ] : null;
	}

	/**
	 * Tells whether a post type has its own posts per language.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function translatable_post_type( $post_type ) {
		switch ( $this->provider() ) {
			case 'bml':
				return BML_Settings::is_translatable_post_type( $post_type );
			case 'polylang':
				return function_exists( 'pll_is_translated_post_type' ) && pll_is_translated_post_type( $post_type );
			case 'wpml':
				return (bool) apply_filters( 'wpml_is_translated_post_type', null, $post_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
		}

		return false;
	}

	/**
	 * Tells whether a taxonomy has its own terms per language.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public function translatable_taxonomy( $taxonomy ) {
		switch ( $this->provider() ) {
			case 'bml':
				return BML_Settings::is_translatable_taxonomy( $taxonomy );
			case 'polylang':
				return function_exists( 'pll_is_translated_taxonomy' ) && pll_is_translated_taxonomy( $taxonomy );
			case 'wpml':
				return (bool) apply_filters( 'wpml_is_translated_taxonomy', null, $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
		}

		return false;
	}

	/**
	 * Returns WP_Query arguments limiting a query to one language.
	 *
	 * Content types that are not translated belong to the default language.
	 * When the type is not translated and the key is not the default, the
	 * returned arguments match nothing.
	 *
	 * @param string $key       Language key.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function post_query_args( $key, $post_type ) {
		if ( ! $this->is_multilingual() || $this->shares_posts() ) {
			return array();
		}

		if ( ! $this->translatable_post_type( $post_type ) ) {
			return $key === $this->default_key() ? array( 'bml_skip' => true ) : array( 'post__in' => array( 0 ) );
		}

		$lang = $this->language( $key );
		$code = $lang ? $lang['code'] : $key;

		switch ( $this->provider() ) {
			case 'bml':
				return array(
					'bml_skip'  => true,
					'tax_query' => array( BML_Model::tax_clause( $code ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Language filter, cached by the caller.
				);
			case 'polylang':
				return array( 'lang' => $code );
			case 'wpml':
				return array( 'suppress_filters' => false );
		}

		return array();
	}

	/**
	 * Returns get_terms() arguments limiting a query to one language.
	 *
	 * @param string $key      Language key.
	 * @param string $taxonomy Taxonomy.
	 * @return array|null Arguments, or null when the taxonomy has nothing in that language.
	 */
	public function term_query_args( $key, $taxonomy ) {
		if ( ! $this->is_multilingual() || $this->shares_posts() ) {
			return array();
		}

		if ( ! $this->translatable_taxonomy( $taxonomy ) ) {
			return $key === $this->default_key() ? array( 'bml_skip' => true ) : null;
		}

		$lang = $this->language( $key );
		$code = $lang ? $lang['code'] : $key;

		switch ( $this->provider() ) {
			case 'bml':
				return array( 'lang' => $code );
			case 'polylang':
				return array( 'lang' => $code );
			case 'wpml':
				return array( 'suppress_filters' => false );
		}

		return array();
	}

	/**
	 * Runs a callback with the given language active.
	 *
	 * Only WPML needs the switch; the other providers accept the language
	 * as a query argument.
	 *
	 * @param string   $key      Language key.
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public function with_language( $key, $callback ) {
		if ( 'wpml' !== $this->provider() || ! $this->is_multilingual() ) {
			return call_user_func( $callback );
		}

		$lang     = $this->language( $key );
		$previous = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.

		do_action( 'wpml_switch_language', $lang ? $lang['code'] : $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML action.
		$result = call_user_func( $callback );
		do_action( 'wpml_switch_language', $previous ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML action.

		return $result;
	}

	/**
	 * Converts a URL into its version for a language.
	 *
	 * Needed only for TranslatePress, where one post serves every language.
	 *
	 * @param string $url URL in the default language.
	 * @param string $key Language key.
	 * @return string
	 */
	public function url_for( $url, $key ) {
		if ( ! $this->shares_posts() || '' === $key ) {
			return $url;
		}

		$lang = $this->language( $key );
		if ( ! $lang || $lang['default'] ) {
			return $url;
		}

		$trp = TRP_Translate_Press::get_trp_instance();
		if ( ! is_object( $trp ) || ! method_exists( $trp, 'get_component' ) ) {
			return $url;
		}

		$converter = $trp->get_component( 'url_converter' );
		if ( ! is_object( $converter ) || ! method_exists( $converter, 'get_url_for_language' ) ) {
			return $url;
		}

		$converted = $converter->get_url_for_language( $lang['locale'], $url, '' );

		return $converted ? (string) $converted : $url;
	}

	/**
	 * Returns the home page URL of a language.
	 *
	 * @param string $key Language key.
	 * @return string
	 */
	public function home_url( $key ) {
		$home = home_url( '/' );

		if ( ! $this->is_multilingual() ) {
			return $home;
		}

		$lang = $this->language( $key );
		$code = $lang ? $lang['code'] : $key;

		switch ( $this->provider() ) {
			case 'bml':
				return function_exists( 'bml_home_url' ) ? (string) bml_home_url( $code ) : $home;
			case 'polylang':
				return function_exists( 'pll_home_url' ) ? (string) pll_home_url( $code ) : $home;
			case 'wpml':
				return (string) apply_filters( 'wpml_permalink', $home, $code, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
			case 'translatepress':
				return $this->url_for( $home, $key );
		}

		return $home;
	}

	/**
	 * Returns the language key of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_language( $post_id ) {
		if ( ! $this->is_multilingual() || $this->shares_posts() ) {
			return '';
		}

		$code = '';

		switch ( $this->provider() ) {
			case 'bml':
				$code = function_exists( 'bml_get_post_language' ) ? (string) bml_get_post_language( $post_id ) : '';
				break;
			case 'polylang':
				$code = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id, 'slug' ) : '';
				break;
			case 'wpml':
				$details = apply_filters( 'wpml_post_language_details', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
				$code    = is_array( $details ) && ! empty( $details['language_code'] ) ? $details['language_code'] : '';
				break;
		}

		return '' === $code ? $this->default_key() : $this->key( $code );
	}

	/**
	 * Returns every URL of a post: one per language for TranslatePress,
	 * otherwise just its own permalink.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function post_urls( $post_id ) {
		$url = get_permalink( $post_id );

		if ( ! $url ) {
			return array();
		}

		if ( ! $this->shares_posts() || ! $this->is_multilingual() ) {
			return array( $url );
		}

		$urls = array();
		foreach ( array_keys( $this->languages() ) as $key ) {
			$urls[] = $this->url_for( $url, $key );
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Returns the Open Graph locales of the other language versions of the current page.
	 *
	 * @return string[]
	 */
	public function alternate_locales() {
		if ( ! $this->is_multilingual() ) {
			return array();
		}

		$current = get_locale();
		$locales = array();

		switch ( $this->provider() ) {
			case 'bml':
				if ( class_exists( 'BML_SEO' ) && method_exists( 'BML_SEO', 'alternates' ) ) {
					foreach ( array_keys( BML_SEO::alternates() ) as $code ) {
						$lang = $this->language( $this->key( $code ) );
						if ( $lang ) {
							$locales[] = $lang['locale'];
						}
					}
				}
				break;

			case 'polylang':
				if ( is_singular() && function_exists( 'pll_get_post_translations' ) ) {
					foreach ( array_keys( (array) pll_get_post_translations( get_queried_object_id() ) ) as $code ) {
						$lang = $this->language( $this->key( $code ) );
						if ( $lang ) {
							$locales[] = $lang['locale'];
						}
					}
				} else {
					$locales = wp_list_pluck( $this->languages(), 'locale' );
				}
				break;

			case 'wpml':
				$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
				foreach ( (array) $active as $lang ) {
					if ( ! empty( $lang['default_locale'] ) ) {
						$locales[] = $lang['default_locale'];
					}
				}
				break;

			case 'translatepress':
				$locales = wp_list_pluck( $this->languages(), 'locale' );
				break;
		}

		$locales = array_unique(
			array_filter(
				$locales,
				function ( $locale ) use ( $current ) {
					return $locale && $locale !== $current;
				}
			)
		);

		return array_values( $locales );
	}

	/**
	 * Returns the language key matching the locale of the current admin user.
	 *
	 * @return string Empty when no language matches.
	 */
	public function user_language_key() {
		if ( ! $this->is_multilingual() ) {
			return '';
		}

		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$prefix = strtolower( substr( $locale, 0, 2 ) );

		foreach ( $this->languages() as $key => $lang ) {
			if ( $lang['locale'] === $locale ) {
				return $key;
			}
		}

		foreach ( $this->languages() as $key => $lang ) {
			if ( strtolower( substr( $lang['locale'], 0, 2 ) ) === $prefix ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Returns the translations of a term, keyed by language key, the term itself included.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	public function term_translations( $term_id, $taxonomy ) {
		$term_id = (int) $term_id;
		$out     = array();

		if ( ! $this->is_multilingual() || $this->shares_posts() || ! $this->translatable_taxonomy( $taxonomy ) ) {
			return array( $this->default_key() => $term_id );
		}

		switch ( $this->provider() ) {
			case 'bml':
				foreach ( (array) BML_Model::translations( 'term', $term_id ) as $code => $id ) {
					$out[ $this->key( $code ) ] = (int) $id;
				}
				break;

			case 'polylang':
				if ( function_exists( 'pll_get_term_translations' ) ) {
					foreach ( (array) pll_get_term_translations( $term_id ) as $code => $id ) {
						$out[ $this->key( $code ) ] = (int) $id;
					}
				}
				break;

			case 'wpml':
				foreach ( $this->languages() as $key => $lang ) {
					$id = (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, false, $lang['code'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
					if ( $id ) {
						$out[ $key ] = $id;
					}
				}
				break;
		}

		if ( ! in_array( $term_id, $out, true ) ) {
			$out[ $this->term_language( $term_id ) ] = $term_id;
		}

		return array_filter( $out );
	}

	/**
	 * Returns the language key of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_language( $term_id ) {
		if ( ! $this->is_multilingual() || $this->shares_posts() ) {
			return $this->default_key();
		}

		$code = '';

		switch ( $this->provider() ) {
			case 'bml':
				$code = (string) BML_Model::get_term_lang( (int) $term_id );
				break;
			case 'polylang':
				$code = function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( (int) $term_id, 'slug' ) : '';
				break;
			case 'wpml':
				$term = get_term( (int) $term_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$code = (string) apply_filters( 'wpml_element_language_code', null, array( 'element_id' => (int) $term->term_taxonomy_id, 'element_type' => $term->taxonomy ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WPML filter.
				}
				break;
		}

		return '' === $code ? $this->default_key() : $this->key( $code );
	}

	/**
	 * Returns the terms of the default language, or all terms when the
	 * taxonomy is not translated.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $number   Maximum number of terms.
	 * @return WP_Term[]
	 */
	public function default_terms( $taxonomy, $number = 300 ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $number,
			'orderby'    => 'name',
			'bml_skip'   => true,
		);

		if ( ! $this->is_multilingual() || $this->shares_posts() || ! $this->translatable_taxonomy( $taxonomy ) ) {
			$terms = get_terms( $args );
			return is_wp_error( $terms ) ? array() : $terms;
		}

		$key  = $this->default_key();
		$lang = $this->language( $key );

		switch ( $this->provider() ) {
			case 'polylang':
				unset( $args['bml_skip'] );
				$args['lang'] = $lang['code'];
				$terms        = get_terms( $args );
				break;

			case 'wpml':
				unset( $args['bml_skip'] );
				$args['suppress_filters'] = false;
				$terms                    = $this->with_language(
					$key,
					function () use ( $args ) {
						return get_terms( $args );
					}
				);
				break;

			default:
				$terms = get_terms( $args );
				if ( ! is_wp_error( $terms ) ) {
					$terms = array_values(
						array_filter(
							$terms,
							function ( $term ) use ( $key ) {
								return $this->term_language( $term->term_id ) === $key;
							}
						)
					);
				}
		}

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Returns a term name for the admin: in the user's language with the
	 * original in brackets, for example "Finanse (original: Finance)".
	 *
	 * @param WP_Term $term Term in the default language.
	 * @return string
	 */
	public function term_label( $term ) {
		$user_key = $this->user_language_key();

		if ( '' === $user_key || $user_key === $this->default_key() ) {
			return $term->name;
		}

		$translations = $this->term_translations( $term->term_id, $term->taxonomy );

		if ( empty( $translations[ $user_key ] ) ) {
			return $term->name;
		}

		$translated = get_term( $translations[ $user_key ] );

		if ( ! $translated || is_wp_error( $translated ) || $translated->name === $term->name ) {
			return $term->name;
		}

		/* translators: 1: term name in the admin language, 2: original term name. */
		return sprintf( __( '%1$s (original: %2$s)', 'bearius-seo-light' ), $translated->name, $term->name );
	}

	/**
	 * Translates a short text into a language when the provider can do it.
	 *
	 * Used for Google News titles on TranslatePress sites.
	 *
	 * @param string $text Text in the default language.
	 * @param string $key  Language key.
	 * @return string
	 */
	public function translate_text( $text, $key ) {
		if ( ! $this->shares_posts() || ! function_exists( 'trp_translate' ) ) {
			return $text;
		}

		$lang = $this->language( $key );
		if ( ! $lang || $lang['default'] ) {
			return $text;
		}

		$translated = trp_translate( $text, $lang['locale'], false );

		return is_string( $translated ) && '' !== trim( $translated ) ? wp_strip_all_tags( $translated ) : $text;
	}

	/**
	 * Reads TranslatePress language names when its component is available.
	 *
	 * @param string[] $locales Locales.
	 * @return array
	 */
	private function translatepress_names( $locales ) {
		$trp = TRP_Translate_Press::get_trp_instance();

		if ( ! is_object( $trp ) || ! method_exists( $trp, 'get_component' ) ) {
			return array();
		}

		$component = $trp->get_component( 'languages' );

		if ( ! is_object( $component ) || ! method_exists( $component, 'get_language_names' ) ) {
			return array();
		}

		return (array) $component->get_language_names( $locales );
	}
}
