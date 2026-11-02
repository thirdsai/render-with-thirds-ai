<?php
/**
 * thirds.ai API client (image renders only).
 *
 * @package RenderWithThirdsAi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starts a render, waits for it and downloads the PNG. Uses the WordPress HTTP API only.
 */
class RWTAI_Client {

	const MAX_BYTES = 20971520;

	/**
	 * API base URL. Filterable for staging or tests.
	 *
	 * @return string
	 */
	public static function base() {
		/**
		 * Filters the thirds.ai API base URL.
		 *
		 * @param string $base Base URL without a trailing slash.
		 */
		return untrailingslashit( (string) apply_filters( 'rwtai_api_base', 'https://thirds.ai' ) );
	}

	/**
	 * Renders one image and saves it to a temporary file.
	 *
	 * @param array  $body            Request body for POST /v1/image.
	 * @param string $idempotency_key Idempotency key for this render.
	 * @return array|WP_Error Array with "file" (temporary path, caller deletes it) and "job_id".
	 */
	public static function render( array $body, $idempotency_key ) {
		if ( ! RWTAI_Settings::is_active() ) {
			return new WP_Error( 'rwtai_inactive', __( 'Rendering is turned off or no API key is set.', 'render-with-thirds-ai' ) );
		}

		$base    = self::base();
		$headers = array(
			'Authorization' => 'Bearer ' . RWTAI_Settings::api_key(),
			'Accept'        => 'application/json',
			'User-Agent'    => 'render-with-thirds-ai/' . RWTAI_VERSION . '; WordPress',
		);

		$response = wp_safe_remote_post(
			$base . '/v1/image',
			array(
				'timeout' => 60,
				'headers' => array_merge(
					$headers,
					array(
						'Content-Type'    => 'application/json',
						'Idempotency-Key' => $idempotency_key,
					)
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$job      = self::job_from( $response );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$deadline = time() + 90;
		while ( in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
			if ( time() > $deadline ) {
				return new WP_Error( 'rwtai_timeout', __( 'thirds.ai did not finish the render in time.', 'render-with-thirds-ai' ) );
			}
			sleep( 2 );
			$job = self::job_from(
				wp_safe_remote_get(
					$base . '/v1/image/' . rawurlencode( $job['id'] ),
					array(
						'timeout' => 30,
						'headers' => $headers,
					)
				)
			);
			if ( is_wp_error( $job ) ) {
				return $job;
			}
		}

		if ( 'succeeded' !== $job['status'] ) {
			$code = isset( $job['error']['code'] ) ? sanitize_key( $job['error']['code'] ) : sanitize_key( $job['status'] );
			/* translators: %s: error code from thirds.ai. */
			return new WP_Error( 'rwtai_render_failed', sprintf( __( 'The render did not succeed (%s).', 'render-with-thirds-ai' ), $code ) );
		}

		$url = isset( $job['download']['url'] ) ? (string) $job['download']['url'] : '';
		if ( '' === $url ) {
			return new WP_Error( 'rwtai_no_download', __( 'thirds.ai returned no download link.', 'render-with-thirds-ai' ) );
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = $base . '/' . ltrim( $url, '/' );
		}

		return self::download( $url, $job['id'] );
	}

	/**
	 * Downloads the finished file. The signed link needs no key, so none is sent, and the link is not stored.
	 *
	 * @param string $url    Signed download link.
	 * @param string $job_id Job id.
	 * @return array|WP_Error
	 */
	private static function download( $url, $job_id ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp      = wp_tempnam( 'rwtai-' . $job_id . '.png' );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 60,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => self::MAX_BYTES,
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$type     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( 200 !== $code || 0 !== strpos( $type, 'image/png' ) || ! filesize( $tmp ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'rwtai_download_failed', __( 'The rendered image could not be downloaded.', 'render-with-thirds-ai' ) );
		}
		return array(
			'file'   => $tmp,
			'job_id' => $job_id,
		);
	}

	/**
	 * Turns an API response into a job array or an error.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return array|WP_Error
	 */
	private static function job_from( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'rwtai_http', __( 'Could not reach thirds.ai.', 'render-with-thirds-ai' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['id'] ) || empty( $data['status'] ) ) {
			$api_code = is_array( $data ) && isset( $data['error']['code'] ) ? sanitize_key( $data['error']['code'] ) : 'http_' . $code;
			$message  = is_array( $data ) && isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : '';
			/* translators: 1: error code, 2: error message from thirds.ai. */
			return new WP_Error( 'rwtai_api', trim( sprintf( __( 'thirds.ai refused the render (%1$s). %2$s', 'render-with-thirds-ai' ), $api_code, $message ) ) );
		}
		return $data;
	}
}
