<?php
/**
 * IndexNow notifications.
 *
 * Sends the address of new, updated and removed content to the shared
 * IndexNow endpoint, which passes it to Bing, Yandex, Seznam, Naver and Yep.
 * Off by default: WordPress.org asks plugins to contact outside services
 * only after the site owner opts in.
 *
 * @package BeariusSEOLight
 * @author  Bearius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues changed URLs and submits them in small batches.
 */
class Bearius_SEO_IndexNow {

	/**
	 * Shared endpoint that forwards submissions to every participating engine.
	 */
	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Option holding the queue.
	 */
	const QUEUE = 'bearius_seo_indexnow_queue';

	/**
	 * Option holding the submission log.
	 */
	const LOG = 'bearius_seo_indexnow_log';

	/**
	 * Hooks the listeners.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! self::active() ) {
			return;
		}

		add_action( 'init', array( $this, 'serve_key' ), 1 );
		add_action( 'wp_after_insert_post', array( $this, 'on_saved' ), 20, 2 );
		add_action( 'wp_trash_post', array( $this, 'on_trash' ) );
		add_action( 'pre_post_update', array( $this, 'on_unpublish' ), 10, 2 );
		add_action( 'bearius_seo_indexnow_flush', array( $this, 'flush' ) );
	}

	/**
	 * Tells whether submissions are enabled and possible.
	 *
	 * @return bool
	 */
	public static function active() {
		return bearius_seo_module_enabled( 'indexnow' ) && '' !== self::key() && bearius_seo_site_is_public();
	}

	/**
	 * Returns the verification key.
	 *
	 * @return string
	 */
	public static function key() {
		return preg_replace( '/[^a-zA-Z0-9-]/', '', (string) bearius_seo_get_setting( 'indexnow_key' ) );
	}

	/**
	 * Generates a new key.
	 *
	 * @return string
	 */
	public static function generate_key() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Returns the public URL of the key file.
	 *
	 * @return string
	 */
	public static function key_url() {
		return trailingslashit( (string) get_option( 'home' ) ) . self::key() . '.txt';
	}

	/**
	 * Answers requests for the key file, which proves the site owns the key.
	 *
	 * @return void
	 */
	public function serve_key() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		if ( (string) wp_parse_url( self::key_url(), PHP_URL_PATH ) !== $path ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( self::key() );
		exit;
	}

	/**
	 * Queues content that was published or updated.
	 *
	 * Runs after the post and its meta are saved, so a noindex flag set in
	 * the same save is already known.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function on_saved( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, bearius_seo_supported_post_types(), true ) || ! empty( $post->post_password ) ) {
			return;
		}

		if ( get_post_meta( $post_id, '_bearius_seo_noindex', true ) ) {
			return;
		}

		$this->queue( Bearius_SEO_Languages::instance()->post_urls( $post_id ) );
	}

	/**
	 * Queues the address of a published post before it goes to the trash,
	 * while its permalink still points at the live URL.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_trash( $post_id ) {
		$post = get_post( $post_id );

		if ( $post && 'publish' === $post->post_status && in_array( $post->post_type, bearius_seo_supported_post_types(), true ) ) {
			$this->queue( Bearius_SEO_Languages::instance()->post_urls( $post_id ) );
		}
	}

	/**
	 * Queues a published post that is about to become a draft or private,
	 * so search engines learn the address is gone.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Data about to be saved.
	 * @return void
	 */
	public function on_unpublish( $post_id, $data ) {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status || empty( $data['post_status'] ) || 'publish' === $data['post_status'] || 'trash' === $data['post_status'] ) {
			return;
		}

		if ( in_array( $post->post_type, bearius_seo_supported_post_types(), true ) ) {
			$this->queue( Bearius_SEO_Languages::instance()->post_urls( $post_id ) );
		}
	}

	/**
	 * Adds URLs to the queue and schedules a submission.
	 *
	 * The same address is not sent again within ten minutes, which keeps
	 * quick successive edits from flooding the endpoint.
	 *
	 * @param string[] $urls URLs.
	 * @return void
	 */
	private function queue( $urls ) {
		$host   = wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
		$recent = get_transient( 'bearius_seo_indexnow_recent' );
		$recent = is_array( $recent ) ? $recent : array();
		$queue  = (array) get_option( self::QUEUE, array() );
		$now    = time();

		foreach ( $recent as $url => $time ) {
			if ( $now - (int) $time > 10 * MINUTE_IN_SECONDS ) {
				unset( $recent[ $url ] );
			}
		}

		foreach ( $urls as $url ) {
			if ( wp_parse_url( $url, PHP_URL_HOST ) !== $host || isset( $recent[ $url ] ) ) {
				continue;
			}

			$queue[ $url ] = $now;
		}

		// A site without working cron must not grow the option forever.
		if ( count( $queue ) > 10000 ) {
			$queue = array_slice( $queue, -10000, null, true );
		}

		update_option( self::QUEUE, $queue, false );
		set_transient( 'bearius_seo_indexnow_recent', $recent, HOUR_IN_SECONDS );

		if ( $queue && ! wp_next_scheduled( 'bearius_seo_indexnow_flush' ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'bearius_seo_indexnow_flush' );
		}
	}

	/**
	 * Sends the queued URLs.
	 *
	 * @return void
	 */
	public function flush() {
		$queue = (array) get_option( self::QUEUE, array() );

		if ( ! $queue || ! self::active() ) {
			return;
		}

		update_option( self::QUEUE, array(), false );

		$urls = array_slice( array_keys( $queue ), 0, 10000 );
		$code = self::submit( $urls );

		$recent = get_transient( 'bearius_seo_indexnow_recent' );
		$recent = is_array( $recent ) ? $recent : array();
		foreach ( $urls as $url ) {
			$recent[ $url ] = time();
		}
		set_transient( 'bearius_seo_indexnow_recent', $recent, HOUR_IN_SECONDS );

		self::log( count( $urls ), $code, $urls[0] );
	}

	/**
	 * Submits URLs to the endpoint.
	 *
	 * @param string[] $urls URLs.
	 * @return int HTTP status, 0 on a transport error.
	 */
	public static function submit( $urls ) {
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode(
					array(
						'host'        => wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ),
						'key'         => self::key(),
						'keyLocation' => self::key_url(),
						'urlList'     => array_values( $urls ),
					)
				),
			)
		);

		return is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Stores a log entry, keeping the last twenty.
	 *
	 * @param int    $count Number of URLs.
	 * @param int    $code  HTTP status.
	 * @param string $first First URL of the batch.
	 * @return void
	 */
	public static function log( $count, $code, $first ) {
		$log = (array) get_option( self::LOG, array() );

		array_unshift(
			$log,
			array(
				'time'  => time(),
				'count' => (int) $count,
				'code'  => (int) $code,
				'first' => (string) $first,
			)
		);

		update_option( self::LOG, array_slice( $log, 0, 20 ), false );
	}

	/**
	 * Explains an HTTP status returned by the endpoint.
	 *
	 * @param int $code HTTP status.
	 * @return string
	 */
	public static function describe( $code ) {
		switch ( (int) $code ) {
			case 200:
			case 202:
				return __( 'Accepted', 'bearius-seo-light' );
			case 400:
				return __( 'Rejected: invalid request', 'bearius-seo-light' );
			case 403:
				return __( 'Rejected: the key file could not be verified', 'bearius-seo-light' );
			case 422:
				return __( 'Rejected: addresses do not match the site', 'bearius-seo-light' );
			case 429:
				return __( 'Too many requests, will retry with the next change', 'bearius-seo-light' );
			case 0:
				return __( 'Connection failed', 'bearius-seo-light' );
		}

		/* translators: %d: HTTP status code. */
		return sprintf( __( 'HTTP status %d', 'bearius-seo-light' ), (int) $code );
	}
}
