<?php
/**
 * Rendered page analysis.
 *
 * Fetches a page of the site as a logged out visitor, once with a desktop
 * and once with a mobile user agent, and reads what actually ends up in the
 * HTML: duplicated tags from themes or snippets, structured data, headings,
 * performance hints and the text an AI crawler gets without JavaScript.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses fetched HTML and turns findings into issues.
 */
class Bearius_SEO_Page_Scan {

	/**
	 * Scans a URL on both devices.
	 *
	 * @param string $url   URL of this site.
	 * @param string $label Human readable page label used in issue titles.
	 * @param string $kind  Either home or post.
	 * @return array
	 */
	public static function scan( $url, $label, $kind = 'post' ) {
		$result = array(
			'url'     => $url,
			'label'   => $label,
			'kind'    => $kind,
			'devices' => array(),
			'issues'  => array(),
		);

		foreach ( array( 'desktop', 'mobile' ) as $device ) {
			$response                   = bearius_seo_remote_get( $url, $device );
			$result['devices'][ $device ] = self::analyse( $response, $url );
		}

		$result['issues'] = self::issues( $result );

		return $result;
	}

	/**
	 * Reads one response.
	 *
	 * @param array  $response Response from bearius_seo_remote_get().
	 * @param string $url      Requested URL.
	 * @return array
	 */
	public static function analyse( $response, $url ) {
		$headers = $response['headers'];
		$body    = $response['body'];

		$data = array(
			'code'         => $response['code'],
			'error'        => $response['error'],
			'time'         => round( $response['time'], 3 ),
			'size'         => strlen( $body ),
			'encoding'     => isset( $headers['content-encoding'] ) ? $headers['content-encoding'] : '',
			'cache'        => self::cache_status( $headers ),
			'x_robots'     => isset( $headers['x-robots-tag'] ) ? $headers['x-robots-tag'] : '',
			'parsed'       => false,
			'title'        => array(),
			'canonical'    => array(),
			'description'  => array(),
			'og_title'     => 0,
			'robots'       => array(),
			'viewport'     => false,
			'jsonld'       => 0,
			'jsonld_bad'   => 0,
			'types'        => array(),
			'h1'           => array(),
			'outline'      => array(),
			'images'       => 0,
			'img_no_dims'  => 0,
			'img_no_alt'   => 0,
			'first_lazy'   => false,
			'scripts'      => 0,
			'head_scripts' => 0,
			'head_styles'  => 0,
			'domains'      => array(),
			'preconnect'   => 0,
			'preload'      => 0,
			'dom_nodes'    => 0,
			'text_total'   => 0,
			'text_main'    => 0,
			'text_noise'   => 0,
			'has_main'     => false,
			'preview'      => '',
			'js_signals'   => array(),
			'published'    => '',
			'og_type'      => '',
			'hreflang'     => array(),
			'marker'       => false !== strpos( $body, '<!-- Bearius SEO Light -->' ),
			'maintenance'  => false,
		);

		if ( '' === $body || ! class_exists( 'DOMDocument' ) ) {
			$data['maintenance'] = self::detect_maintenance( $body, $data );
			return $data;
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $body, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $data;
		}

		$data['parsed'] = true;
		$xpath          = new DOMXPath( $dom );
		$home_host      = wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );

		foreach ( $xpath->query( '//title' ) as $node ) {
			$data['title'][] = trim( $node->textContent );
		}

		foreach ( $xpath->query( '//link[@rel]' ) as $node ) {
			$rel  = strtolower( (string) $node->getAttribute( 'rel' ) );
			$href = (string) $node->getAttribute( 'href' );

			if ( 'alternate' === $rel && $node->hasAttribute( 'hreflang' ) ) {
				$data['hreflang'][] = array( strtolower( (string) $node->getAttribute( 'hreflang' ) ), $href );
			} elseif ( 'canonical' === $rel ) {
				$data['canonical'][] = $href;
			} elseif ( false !== strpos( $rel, 'preconnect' ) || false !== strpos( $rel, 'dns-prefetch' ) ) {
				$data['preconnect']++;
			} elseif ( false !== strpos( $rel, 'preload' ) ) {
				$data['preload']++;
			}
		}

		foreach ( $xpath->query( '//meta' ) as $node ) {
			$name     = strtolower( (string) $node->getAttribute( 'name' ) );
			$property = strtolower( (string) $node->getAttribute( 'property' ) );
			$content  = (string) $node->getAttribute( 'content' );

			if ( 'description' === $name ) {
				$data['description'][] = $content;
			} elseif ( 'robots' === $name || 'googlebot' === $name ) {
				$data['robots'][] = strtolower( $content );
			} elseif ( 'viewport' === $name ) {
				$data['viewport'] = true;
			} elseif ( 'og:title' === $property ) {
				$data['og_title']++;
			} elseif ( 'article:published_time' === $property ) {
				$data['published'] = $content;
			} elseif ( 'og:type' === $property ) {
				$data['og_type'] = strtolower( $content );
			}
		}

		foreach ( $xpath->query( '//script[@type="application/ld+json"]' ) as $node ) {
			$data['jsonld']++;
			$json = json_decode( trim( $node->textContent ), true );

			if ( null === $json ) {
				$data['jsonld_bad']++;
				continue;
			}

			$data['types'] = array_merge( $data['types'], self::schema_types( $json ) );
		}

		foreach ( $xpath->query( '//h1|//h2|//h3' ) as $node ) {
			$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );

			if ( 'h1' === strtolower( $node->nodeName ) ) {
				$data['h1'][] = $text;
			}

			if ( count( $data['outline'] ) < 40 && '' !== $text ) {
				$data['outline'][] = array( strtolower( $node->nodeName ), bearius_seo_trim_on_word( $text, 120 ) );
			}
		}

		$first = true;
		foreach ( $xpath->query( '//body//img' ) as $node ) {
			$data['images']++;

			if ( ! $node->hasAttribute( 'width' ) || ! $node->hasAttribute( 'height' ) ) {
				$data['img_no_dims']++;
			}

			if ( ! $node->hasAttribute( 'alt' ) ) {
				$data['img_no_alt']++;
			}

			if ( $first ) {
				$data['first_lazy'] = 'lazy' === strtolower( (string) $node->getAttribute( 'loading' ) );
				$first              = false;
			}

			self::add_domain( $data['domains'], (string) $node->getAttribute( 'src' ), $home_host );
		}

		foreach ( $xpath->query( '//script[@src]' ) as $node ) {
			$data['scripts']++;
			self::add_domain( $data['domains'], (string) $node->getAttribute( 'src' ), $home_host );

			$in_head = 'head' === strtolower( $node->parentNode->nodeName );
			$type    = strtolower( (string) $node->getAttribute( 'type' ) );

			if ( $in_head && ! $node->hasAttribute( 'async' ) && ! $node->hasAttribute( 'defer' ) && 'module' !== $type ) {
				$data['head_scripts']++;
			}
		}

		foreach ( $xpath->query( '//head/link[@rel="stylesheet"]' ) as $node ) {
			$media = strtolower( (string) $node->getAttribute( 'media' ) );
			self::add_domain( $data['domains'], (string) $node->getAttribute( 'href' ), $home_host );

			if ( '' === $media || 'all' === $media || 'screen' === $media ) {
				$data['head_styles']++;
			}
		}

		foreach ( $xpath->query( '//iframe[@src]' ) as $node ) {
			self::add_domain( $data['domains'], (string) $node->getAttribute( 'src' ), $home_host );
		}

		$data['dom_nodes'] = $dom->getElementsByTagName( '*' )->length;

		self::read_text( $dom, $xpath, $data );

		if ( '' === $data['published'] ) {
			$time = $xpath->query( '//time[@datetime]' )->item( 0 );
			if ( $time ) {
				$data['published'] = (string) $time->getAttribute( 'datetime' );
			}
		}

		$data['domains']     = array_values( array_unique( $data['domains'] ) );
		$data['types']       = array_values( $data['types'] );
		$data['maintenance'] = self::detect_maintenance( $body, $data );

		return $data;
	}

	/**
	 * Tells whether the response is a maintenance or coming soon page.
	 *
	 * A title alone is not enough, because a normal article can be about
	 * maintenance. It has to come with a maintenance marker in the markup,
	 * an Open Graph type of the plugin, very little text or a 503 status.
	 *
	 * @param string $body Response body.
	 * @param array  $data Analysis so far.
	 * @return bool
	 */
	private static function detect_maintenance( $body, $data ) {
		$title  = strtolower( implode( ' ', (array) $data['title'] ) );
		$titled = (bool) preg_match( '/\b(maintenance|coming soon|under construction|briefly unavailable|wartungsmodus|konserwacj|mantenimiento)/u', $title );
		$marked = (bool) preg_match( '/(?:class|id)\s*=\s*["\'][^"\']*(wp-maintenance-mode|seedprod|seed-csp|mtnc|elementor-maintenance-mode|cmp-coming-soon|maintenance-mode|coming-soon-page|under-construction)[^"\']*["\']/i', (string) $body );
		$typed  = in_array( $data['og_type'], array( 'maintenance', 'coming-soon' ), true );

		if ( $marked || $typed ) {
			return true;
		}

		return $titled && ( 503 === (int) $data['code'] || (int) $data['text_main'] < 600 );
	}

	/**
	 * Extracts the text a crawler without JavaScript receives.
	 *
	 * @param DOMDocument $dom   Document.
	 * @param DOMXPath    $xpath XPath helper.
	 * @param array       $data  Analysis data, updated in place.
	 * @return void
	 */
	private static function read_text( $dom, $xpath, &$data ) {
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );

		if ( ! $body ) {
			return;
		}

		// Signals that the real content is rendered in the browser.
		foreach ( array( 'root', 'app', '__next', '__nuxt' ) as $id ) {
			$node = $xpath->query( '//*[@id="' . $id . '"]' )->item( 0 );
			if ( $node && '' === trim( $node->textContent ) ) {
				/* translators: %s: element id. */
				$data['js_signals'][] = sprintf( __( 'An empty #%s container, typical for pages rendered by JavaScript.', 'bearius-seo-light' ), $id );
			}
		}

		foreach ( $xpath->query( '//noscript' ) as $node ) {
			if ( preg_match( '/javascript/i', $node->textContent ) ) {
				$data['js_signals'][] = __( 'A notice asking visitors to enable JavaScript.', 'bearius-seo-light' );
				break;
			}
		}

		foreach ( $xpath->query( '//body//script|//body//style|//body//noscript|//body//template|//body//svg|//body//iframe' ) as $node ) {
			$node->parentNode->removeChild( $node );
		}

		$clean = function ( $text ) {
			return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		};

		$total               = $clean( $body->textContent );
		$data['text_total'] = bearius_seo_strlen( $total );

		$noise = 0;
		foreach ( $xpath->query( '//body//nav|//body//header|//body//footer|//body//aside|//body//*[@role="navigation"]' ) as $node ) {
			$noise += bearius_seo_strlen( $clean( $node->textContent ) );
		}
		$data['text_noise'] = min( $noise, $data['text_total'] );

		$main = $xpath->query( '//body//main|//body//article|//body//*[@role="main"]' )->item( 0 );

		if ( $main ) {
			$data['has_main']  = true;
			$main_text         = $clean( $main->textContent );
			$data['text_main'] = bearius_seo_strlen( $main_text );
			$data['preview']   = bearius_seo_substr( $main_text, 0, 1600 );
		} else {
			$data['text_main'] = max( 0, $data['text_total'] - $data['text_noise'] );
			$data['preview']   = bearius_seo_substr( $total, 0, 1600 );
		}

		if ( $data['text_main'] < 250 && $data['scripts'] > 6 ) {
			$data['js_signals'][] = __( 'Very little text in the HTML while many scripts are loaded. The content may only appear after JavaScript runs.', 'bearius-seo-light' );
		}
	}

	/**
	 * Collects @type values from a JSON-LD document.
	 *
	 * @param mixed $json Decoded JSON.
	 * @return string[]
	 */
	private static function schema_types( $json ) {
		$types = array();

		if ( ! is_array( $json ) ) {
			return $types;
		}

		if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
			foreach ( $json['@graph'] as $node ) {
				$types = array_merge( $types, self::schema_types( $node ) );
			}
			return $types;
		}

		if ( isset( $json[0] ) ) {
			foreach ( $json as $node ) {
				$types = array_merge( $types, self::schema_types( $node ) );
			}
			return $types;
		}

		if ( isset( $json['@type'] ) ) {
			foreach ( (array) $json['@type'] as $type ) {
				$types[] = (string) $type;
			}
		}

		return $types;
	}

	/**
	 * Records the host of an external resource.
	 *
	 * @param array  $domains   Collected hosts.
	 * @param string $src       Resource URL.
	 * @param string $home_host Host of this site.
	 * @return void
	 */
	private static function add_domain( &$domains, $src, $home_host ) {
		if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
			return;
		}

		if ( 0 === strpos( $src, '//' ) ) {
			$src = 'https:' . $src;
		}

		$host = wp_parse_url( $src, PHP_URL_HOST );

		if ( $host && strtolower( $host ) !== strtolower( (string) $home_host ) ) {
			$domains[] = strtolower( $host );
		}
	}

	/**
	 * Reads the page cache status from common headers.
	 *
	 * @param array $headers Lower cased headers.
	 * @return string
	 */
	private static function cache_status( $headers ) {
		foreach ( array( 'cf-cache-status', 'x-cache', 'x-litespeed-cache', 'x-proxy-cache', 'x-nginx-cache', 'x-cache-status', 'x-wp-super-cache' ) as $name ) {
			if ( ! empty( $headers[ $name ] ) ) {
				return strtoupper( strtok( $headers[ $name ], ' ,' ) );
			}
		}

		return '';
	}

	/**
	 * Normalises a URL for comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function normalise( $url ) {
		return untrailingslashit( strtolower( preg_replace( '#^https?://(www\.)?#i', '', (string) $url ) ) );
	}

	/**
	 * Builds an issue array.
	 *
	 * @param string $id       Issue id.
	 * @param string $severity Severity.
	 * @param string $group    Group: page, performance or ai.
	 * @param string $title    Title.
	 * @param string $detail   Explanation.
	 * @return array
	 */
	private static function issue( $id, $severity, $group, $title, $detail ) {
		return array(
			'id'       => $id,
			'severity' => $severity,
			'group'    => $group,
			'title'    => $title,
			'detail'   => $detail,
			'link'     => '',
		);
	}

	/**
	 * Turns a scan into issues.
	 *
	 * @param array $scan Scan result.
	 * @return array[]
	 */
	public static function issues( $scan ) {
		$issues  = array();
		$label   = $scan['label'];
		$kind    = $scan['kind'];
		$prefix  = $kind . '_';
		$desktop = $scan['devices']['desktop'];
		$mobile  = $scan['devices']['mobile'];

		if ( $desktop['maintenance'] ) {
			if ( 503 === $desktop['code'] ) {
				$issues[] = self::issue(
					$prefix . 'maintenance',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s shows a maintenance page', 'bearius-seo-light' ), $label ),
					__( 'Visitors and search engines get a maintenance page with status 503, which is the right signal for a short break. If it lasts more than a few days, search engines may start dropping pages. The other checks for this page are skipped until it is back.', 'bearius-seo-light' )
				);
			} else {
				$issues[] = self::issue(
					$prefix . 'maintenance',
					'critical',
					'page',
					/* translators: 1: page label, 2: HTTP status. */
					sprintf( __( '%1$s shows a maintenance page with status %2$d', 'bearius-seo-light' ), $label, (int) $desktop['code'] ),
					__( 'Visitors and search engines get a maintenance page instead of your content, and the status code says everything is fine. Search engines may index the maintenance page in place of your articles. Set your maintenance plugin to send status 503 with a Retry-After header, or switch maintenance off. The other checks for this page are skipped until it is back.', 'bearius-seo-light' )
				);
			}

			return $issues;
		}

		if ( 200 !== $desktop['code'] ) {
			if ( 0 === $desktop['code'] ) {
				$issues[] = self::issue(
					$prefix . 'fetch',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s could not be fetched for the audit', 'bearius-seo-light' ), $label ),
					__( 'The server refused a request to its own address. Some hosts and firewalls block these loopback requests. Visitors are not affected, but the page checks cannot run.', 'bearius-seo-light' ) . ( $desktop['error'] ? ' (' . $desktop['error'] . ')' : '' )
				);
			} else {
				$issues[] = self::issue(
					$prefix . 'status',
					'critical',
					'page',
					/* translators: 1: page label, 2: HTTP status. */
					sprintf( __( '%1$s answers with HTTP %2$d', 'bearius-seo-light' ), $label, $desktop['code'] ),
					__( 'Search engines only index pages that answer with status 200.', 'bearius-seo-light' )
				);
			}

			return $issues;
		}

		if ( ! $desktop['parsed'] ) {
			return $issues;
		}

		$robots = implode( ',', $desktop['robots'] ) . ',' . strtolower( $desktop['x_robots'] );
		if ( false !== strpos( $robots, 'noindex' ) ) {
			$issues[] = self::issue(
				$prefix . 'noindex',
				'home' === $kind ? 'critical' : 'warning',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s tells search engines not to index it', 'bearius-seo-light' ), $label ),
				__( 'A noindex directive was found in a meta tag or the X-Robots-Tag header. If this is not intentional, look for another plugin, the theme or the server configuration adding it.', 'bearius-seo-light' )
			);
		}

		$canonicals = count( $desktop['canonical'] );

		// Without the plugin's tags and marker the page was not built by
		// WordPress with this plugin active: an old cached copy, a coming
		// soon plugin or a theme without wp_head(). Missing tags are then a
		// symptom, so one finding replaces them.
		$foreign = bearius_seo_module_active( 'meta' ) && bearius_seo_get_setting( 'meta_canonical' ) && 0 === $canonicals && ! $desktop['marker'];

		if ( $foreign ) {
			$issues[] = self::issue(
				$prefix . 'foreign',
				'warning',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s does not contain the tags of this plugin', 'bearius-seo-light' ), $label ),
				__( 'The page visitors receive was not built with Bearius SEO Light active. It usually comes from a page cache created before the plugin was set up, from a coming soon plugin, or the theme does not call wp_head(). Clear the page cache and run the checks again.', 'bearius-seo-light' )
			);
		}

		if ( $canonicals > 1 ) {
			$issues[] = self::issue(
				$prefix . 'canonical_dup',
				'critical',
				'page',
				/* translators: 1: page label, 2: number of tags. */
				sprintf( __( '%1$s has %2$d canonical tags', 'bearius-seo-light' ), $label, $canonicals ),
				__( 'With more than one canonical tag search engines may ignore all of them. Another plugin, the theme or a code snippet prints its own tag.', 'bearius-seo-light' )
			);
		} elseif ( 0 === $canonicals && ! $foreign ) {
			$issues[] = self::issue(
				$prefix . 'canonical_none',
				'warning',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s has no canonical tag', 'bearius-seo-light' ), $label ),
				__( 'A canonical tag tells search engines which address is the main one when the same content is reachable under several URLs.', 'bearius-seo-light' )
			);
		} elseif ( self::normalise( $desktop['canonical'][0] ) !== self::normalise( $scan['url'] ) ) {
			$issues[] = self::issue(
				$prefix . 'canonical_other',
				'notice',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s points its canonical tag to another address', 'bearius-seo-light' ), $label ),
				/* translators: %s: canonical URL. */
				sprintf( __( 'Canonical: %s. This is fine if intentional; otherwise the page will not rank on its own.', 'bearius-seo-light' ), $desktop['canonical'][0] )
			);
		}

		$descriptions = count( $desktop['description'] );
		if ( $descriptions > 1 ) {
			$issues[] = self::issue(
				$prefix . 'desc_dup',
				'warning',
				'page',
				/* translators: 1: page label, 2: number of tags. */
				sprintf( __( '%1$s has %2$d meta descriptions', 'bearius-seo-light' ), $label, $descriptions ),
				__( 'Something else prints a description too. Search engines pick one at random or write their own.', 'bearius-seo-light' )
			);
		} elseif ( 0 === $descriptions && ! $foreign ) {
			$issues[] = self::issue(
				$prefix . 'desc_none',
				'notice',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s has no meta description', 'bearius-seo-light' ), $label ),
				__( 'Without it search engines build the snippet from page text, which is often less inviting.', 'bearius-seo-light' )
			);
		}

		if ( $desktop['og_title'] > 1 ) {
			$issues[] = self::issue(
				$prefix . 'og_dup',
				'warning',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s has duplicated Open Graph tags', 'bearius-seo-light' ), $label ),
				__( 'Social networks may show the wrong title or image when shared.', 'bearius-seo-light' )
			);
		}

		if ( 1 !== count( $desktop['title'] ) ) {
			$issues[] = self::issue(
				$prefix . 'title_count',
				'warning',
				'page',
				/* translators: 1: page label, 2: number of title tags. */
				sprintf( __( '%1$s has %2$d title tags', 'bearius-seo-light' ), $label, count( $desktop['title'] ) ),
				__( 'A page needs exactly one title tag. Check the theme for a hard coded title.', 'bearius-seo-light' )
			);
		}

		$repeated = array_intersect( array( 'Organization', 'WebSite', 'BreadcrumbList', 'Article', 'NewsArticle' ), array_keys( array_filter( array_count_values( $desktop['types'] ), function ( $n ) {
			return $n > 1;
		} ) ) );

		if ( $repeated ) {
			$issues[] = self::issue(
				$prefix . 'schema_dup',
				'warning',
				'page',
				/* translators: 1: page label, 2: list of types. */
				sprintf( __( '%1$s repeats structured data: %2$s', 'bearius-seo-light' ), $label, implode( ', ', $repeated ) ),
				__( 'Two sources describe the same thing. Switch off the duplicate in the theme or in the Meta and schema settings.', 'bearius-seo-light' )
			);
		}

		if ( $desktop['jsonld_bad'] > 0 ) {
			$issues[] = self::issue(
				$prefix . 'schema_bad',
				'warning',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s contains invalid structured data', 'bearius-seo-light' ), $label ),
				__( 'At least one JSON-LD block cannot be parsed and is ignored by search engines.', 'bearius-seo-light' )
			);
		}

		$h1 = count( $desktop['h1'] );
		if ( 0 === $h1 ) {
			$issues[] = self::issue(
				$prefix . 'h1_none',
				'post' === $kind ? 'warning' : 'notice',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s has no main heading', 'bearius-seo-light' ), $label ),
				__( 'An H1 heading tells readers and crawlers what the page is about.', 'bearius-seo-light' )
			);
		} elseif ( $h1 > 1 ) {
			$issues[] = self::issue(
				$prefix . 'h1_many',
				'notice',
				'page',
				/* translators: 1: page label, 2: number of headings. */
				sprintf( __( '%1$s has %2$d main headings', 'bearius-seo-light' ), $label, $h1 ),
				__( 'Several H1 headings are allowed, but one clear heading makes the topic easier to read.', 'bearius-seo-light' )
			);
		}

		// Mobile compared with desktop.
		if ( 200 === $mobile['code'] && $mobile['parsed'] ) {
			if ( ! $mobile['viewport'] ) {
				$issues[] = self::issue(
					$prefix . 'viewport',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s has no viewport tag on mobile', 'bearius-seo-light' ), $label ),
					__( 'Without it phones render the desktop layout zoomed out. Google indexes the mobile version first.', 'bearius-seo-light' )
				);
			}

			if ( $mobile['canonical'] !== $desktop['canonical'] ) {
				$issues[] = self::issue(
					$prefix . 'mobile_canonical',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s has a different canonical tag on mobile', 'bearius-seo-light' ), $label ),
					__( 'The mobile and desktop versions should point to the same main address.', 'bearius-seo-light' )
				);
			}

			if ( $mobile['jsonld'] !== $desktop['jsonld'] ) {
				$issues[] = self::issue(
					$prefix . 'mobile_schema',
					'notice',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s has different structured data on mobile', 'bearius-seo-light' ), $label ),
					__( 'Google indexes the mobile version, so anything missing there is missing for Google.', 'bearius-seo-light' )
				);
			}

			$base = max( 1, $desktop['text_main'] );
			if ( $desktop['text_main'] > 400 && abs( $mobile['text_main'] - $desktop['text_main'] ) / $base > 0.3 ) {
				$issues[] = self::issue(
					$prefix . 'mobile_text',
					'notice',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s shows noticeably different content on mobile', 'bearius-seo-light' ), $label ),
					/* translators: 1: characters on desktop, 2: characters on mobile. */
					sprintf( __( 'Main text: %1$s characters on desktop, %2$s on mobile.', 'bearius-seo-light' ), number_format_i18n( $desktop['text_main'] ), number_format_i18n( $mobile['text_main'] ) )
				);
			}
		}

		// Language versions.
		if ( Bearius_SEO_Languages::instance()->is_multilingual() ) {
			$hrefs = wp_list_pluck( $desktop['hreflang'], 1 );
			$codes = wp_list_pluck( $desktop['hreflang'], 0 );
			$self  = false;

			foreach ( $hrefs as $href ) {
				if ( self::normalise( $href ) === self::normalise( $scan['url'] ) ) {
					$self = true;
				}
			}

			if ( ! $desktop['hreflang'] ) {
				$issues[] = self::issue(
					$prefix . 'hreflang_none',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s has no hreflang links', 'bearius-seo-light' ), $label ),
					__( 'The site runs in several languages, but this page does not point to its other versions. Search engines may then show the wrong language. Hreflang links come from the multilingual plugin; check its settings.', 'bearius-seo-light' )
				);
			} elseif ( ! $self ) {
				$issues[] = self::issue(
					$prefix . 'hreflang_self',
					'warning',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s does not list itself among its language versions', 'bearius-seo-light' ), $label ),
					__( 'Each page has to include a hreflang link to itself, otherwise search engines may ignore the whole set.', 'bearius-seo-light' )
				);
			} elseif ( ! in_array( 'x-default', $codes, true ) ) {
				$issues[] = self::issue(
					$prefix . 'hreflang_default',
					'notice',
					'page',
					/* translators: %s: page label. */
					sprintf( __( '%s has no x-default language version', 'bearius-seo-light' ), $label ),
					__( 'x-default tells search engines which version to show visitors whose language is not on the list.', 'bearius-seo-light' )
				);
			}
		}

		// Large image previews in Google Discover.
		if ( 'post' === $kind && false === strpos( $robots, 'noindex' ) && false === strpos( $robots, 'max-image-preview:large' ) ) {
			$issues[] = self::issue(
				$prefix . 'discover_preview',
				bearius_seo_get_setting( 'news_mode' ) ? 'warning' : 'notice',
				'page',
				/* translators: %s: page label. */
				sprintf( __( '%s does not allow large image previews', 'bearius-seo-light' ), $label ),
				__( 'Google Discover shows large images only when the page allows them with max-image-preview:large. WordPress adds it by default, so a theme or another plugin probably removes it.', 'bearius-seo-light' )
			);
		}

		$issues = array_merge( $issues, self::performance_issues( $scan ), self::ai_issues( $scan ) );

		return $issues;
	}

	/**
	 * Performance hints read from the mobile HTML.
	 *
	 * @param array $scan Scan result.
	 * @return array[]
	 */
	private static function performance_issues( $scan ) {
		$issues = array();
		$label  = $scan['label'];
		$prefix = $scan['kind'] . '_perf_';
		$page   = 200 === $scan['devices']['mobile']['code'] && $scan['devices']['mobile']['parsed'] ? $scan['devices']['mobile'] : $scan['devices']['desktop'];

		if ( $page['time'] > 1.5 ) {
			$issues[] = self::issue(
				$prefix . 'time',
				'warning',
				'performance',
				/* translators: 1: page label, 2: seconds. */
				sprintf( __( '%1$s took %2$s s to load its HTML', 'bearius-seo-light' ), $label, number_format_i18n( $page['time'], 2 ) ),
				__( 'Measured from the server itself, so real visitors wait longer. A page cache usually brings this well under half a second.', 'bearius-seo-light' )
			);
		} elseif ( $page['time'] > 0.8 ) {
			$issues[] = self::issue(
				$prefix . 'time',
				'notice',
				'performance',
				/* translators: 1: page label, 2: seconds. */
				sprintf( __( '%1$s took %2$s s to load its HTML', 'bearius-seo-light' ), $label, number_format_i18n( $page['time'], 2 ) ),
				__( 'Acceptable, but a page cache would make it faster.', 'bearius-seo-light' )
			);
		}

		$kb = (int) round( $page['size'] / 1024 );
		if ( $kb > 300 ) {
			$issues[] = self::issue(
				$prefix . 'size',
				$kb > 800 ? 'warning' : 'notice',
				'performance',
				/* translators: 1: page label, 2: size in kilobytes. */
				sprintf( __( '%1$s weighs %2$d KB of HTML', 'bearius-seo-light' ), $label, $kb ),
				__( 'Large HTML usually comes from inlined styles, scripts or very long lists of links.', 'bearius-seo-light' )
			);
		}

		if ( $page['head_scripts'] > 3 ) {
			$issues[] = self::issue(
				$prefix . 'blocking_js',
				$page['head_scripts'] > 8 ? 'warning' : 'notice',
				'performance',
				/* translators: 1: page label, 2: number of scripts. */
				sprintf( __( '%1$s loads %2$d render blocking scripts in the head', 'bearius-seo-light' ), $label, $page['head_scripts'] ),
				__( 'Scripts without async or defer stop the browser from drawing the page until they download.', 'bearius-seo-light' )
			);
		}

		if ( $page['head_styles'] > 6 ) {
			$issues[] = self::issue(
				$prefix . 'blocking_css',
				'notice',
				'performance',
				/* translators: 1: page label, 2: number of stylesheets. */
				sprintf( __( '%1$s loads %2$d stylesheets before showing anything', 'bearius-seo-light' ), $label, $page['head_styles'] ),
				__( 'Each stylesheet in the head delays the first paint. Combining or trimming them helps.', 'bearius-seo-light' )
			);
		}

		if ( $page['first_lazy'] ) {
			$issues[] = self::issue(
				$prefix . 'lazy_first',
				'warning',
				'performance',
				/* translators: %s: page label. */
				sprintf( __( '%s lazy loads its first image', 'bearius-seo-light' ), $label ),
				__( 'The first image is often the largest element on screen. Lazy loading it delays the moment the page looks ready.', 'bearius-seo-light' )
			);
		}

		if ( $page['img_no_dims'] > 0 ) {
			$issues[] = self::issue(
				$prefix . 'img_dims',
				$page['img_no_dims'] > 3 ? 'warning' : 'notice',
				'performance',
				/* translators: 1: page label, 2: number of images. */
				sprintf( __( '%1$s has %2$d images without width and height', 'bearius-seo-light' ), $label, $page['img_no_dims'] ),
				__( 'Without dimensions the layout jumps while images load, which hurts the layout stability score.', 'bearius-seo-light' )
			);
		}

		$domains = count( $page['domains'] );
		if ( $domains > 10 ) {
			$issues[] = self::issue(
				$prefix . 'domains',
				'notice',
				'performance',
				/* translators: 1: page label, 2: number of domains. */
				sprintf( __( '%1$s loads files from %2$d other domains', 'bearius-seo-light' ), $label, $domains ),
				__( 'Every new domain costs a connection. Remove unused embeds and trackers.', 'bearius-seo-light' )
			);
		} elseif ( $domains >= 3 && 0 === $page['preconnect'] ) {
			$issues[] = self::issue(
				$prefix . 'preconnect',
				'notice',
				'performance',
				/* translators: %s: page label. */
				sprintf( __( '%s uses other domains without preconnect hints', 'bearius-seo-light' ), $label ),
				__( 'A preconnect hint lets the browser open important connections earlier.', 'bearius-seo-light' )
			);
		}

		if ( $page['dom_nodes'] > 1500 ) {
			$issues[] = self::issue(
				$prefix . 'dom',
				$page['dom_nodes'] > 3000 ? 'warning' : 'notice',
				'performance',
				/* translators: 1: page label, 2: number of elements. */
				sprintf( __( '%1$s has %2$s HTML elements', 'bearius-seo-light' ), $label, number_format_i18n( $page['dom_nodes'] ) ),
				__( 'A very large document is slower to render and to update. Page builders and long menus are the usual cause.', 'bearius-seo-light' )
			);
		}

		return $issues;
	}

	/**
	 * Findings about what AI crawlers can read.
	 *
	 * @param array $scan Scan result.
	 * @return array[]
	 */
	private static function ai_issues( $scan ) {
		$issues = array();
		$page   = $scan['devices']['desktop'];
		$label  = $scan['label'];
		$prefix = $scan['kind'] . '_ai_';

		if ( $page['js_signals'] ) {
			$issues[] = self::issue(
				$prefix . 'js',
				'warning',
				'ai',
				/* translators: %s: page label. */
				sprintf( __( '%s may hide content from AI crawlers', 'bearius-seo-light' ), $label ),
				__( 'Most AI crawlers do not run JavaScript. Content loaded by scripts is invisible to them.', 'bearius-seo-light' ) . ' ' . implode( ' ', $page['js_signals'] )
			);
		}

		if ( 'post' === $scan['kind'] && ! $page['has_main'] ) {
			$issues[] = self::issue(
				$prefix . 'main',
				'notice',
				'ai',
				/* translators: %s: page label. */
				sprintf( __( '%s has no main or article element', 'bearius-seo-light' ), $label ),
				__( 'A main or article element marks where the content starts, which helps crawlers separate it from menus and widgets.', 'bearius-seo-light' )
			);
		}

		if ( $page['text_total'] > 0 && 'post' === $scan['kind'] ) {
			$share = $page['text_main'] / $page['text_total'];

			if ( $share < 0.35 ) {
				$issues[] = self::issue(
					$prefix . 'noise',
					'notice',
					'ai',
					/* translators: 1: page label, 2: percentage. */
					sprintf( __( 'Only %2$d%% of the text on %1$s is the article itself', 'bearius-seo-light' ), $label, (int) round( $share * 100 ) ),
					__( 'Menus, sidebars and footers make up most of the text. Crawlers usually cope, but a cleaner page is easier to summarise correctly.', 'bearius-seo-light' )
				);
			}
		}

		return $issues;
	}
}
