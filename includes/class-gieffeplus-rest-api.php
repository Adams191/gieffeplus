<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Rest_Api')) {

	class Gieffeplus_Rest_Api
	{
		private const NAMESPACE = 'gieffeplus/v1';
		private const CACHE_KEY = 'gieffeplus_resellers_cache';
		private const CACHE_TTL = 12 * HOUR_IN_SECONDS;
		private const EARTH_RADIUS_KM = 6371;

		public static function init(): void
		{
			add_action('rest_api_init', array(__CLASS__, 'register_routes'));
			// Keep the cache in sync whenever reseller data changes.
			add_action('save_post_rivenditore', array(__CLASS__, 'flush_cache'));
			add_action('deleted_post', array(__CLASS__, 'flush_cache_on_delete'));
		}

		public static function flush_cache_on_delete(int $post_id): void
		{
			if (get_post_type($post_id) === 'rivenditore') {
				self::flush_cache();
			}
		}

		public static function flush_cache(): void
		{
			delete_transient(self::CACHE_KEY);
		}

		public static function register_routes(): void
		{
			register_rest_route(self::NAMESPACE, '/reseller', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array(__CLASS__, 'get_nearby_resellers'),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lat' => array(
						'required'          => true,
						'type'              => 'number',
						// Bare 'floatval' fatals on PHP 8+: WP always calls sanitize_callback
						// with 3 args ($value, $request, $key), but floatval() only accepts 1
						// and PHP 8 throws ArgumentCountError instead of ignoring the extras.
						'sanitize_callback' => function ($value) {
							return (float) $value;
						},
						'validate_callback' => function ($value) {
							return is_numeric($value) && $value >= -90 && $value <= 90;
						},
					),
					'lng' => array(
						'required'          => true,
						'type'              => 'number',
						'sanitize_callback' => function ($value) {
							return (float) $value;
						},
						'validate_callback' => function ($value) {
							return is_numeric($value) && $value >= -180 && $value <= 180;
						},
					),
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => function ($value) {
							return max(1, min(20, (int) $value));
						},
					),
				),
			));
		}

		public static function get_nearby_resellers(WP_REST_Request $request)
		{
			try {
				$lat   = (float) $request->get_param('lat');
				$lng   = (float) $request->get_param('lng');
				$limit = (int) $request->get_param('limit');

				$resellers = self::get_resellers();

				if (empty($resellers)) {
					return rest_ensure_response(array());
				}

				foreach ($resellers as &$reseller) {
					$reseller['distance'] = self::haversine_distance($lat, $lng, $reseller['lat'], $reseller['lng']);
				}
				unset($reseller);

				usort($resellers, static function ($a, $b) {
					return $a['distance'] <=> $b['distance'];
				});

				$nearest = array_slice($resellers, 0, $limit);

				$response = array();
				foreach (array_values($nearest) as $index => $reseller) {
					$response[] = array(
						'title'    => $reseller['title'],
						'address'  => $reseller['address'],
						'link'     => $reseller['link'],
						'lat'      => $reseller['lat'],
						'lng'      => $reseller['lng'],
						'distance' => round($reseller['distance'], 1),
						'position' => $index + 1,
					);
				}

				$rest_response = rest_ensure_response($response);
				// Public, non-personalised data: safe to let a page/CDN cache layer store it briefly.
				$rest_response->header('Cache-Control', 'public, max-age=60');

				return $rest_response;
			} catch (\Throwable $e) {
				// Never let an edge case (e.g. malformed legacy postmeta) bubble up as a bare 500 HTML page.
				error_log('[Reseller Map] REST /reseller failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

				return new WP_Error(
					'gieffeplus_rest_error',
					__('Errore interno durante il calcolo dei rivenditori più vicini.', 'gieffeplus'),
					array('status' => 500)
				);
			}
		}

		/**
		 * Fetch + cache the full reseller directory. Avoids re-querying and
		 * re-reading postmeta for every single search request.
		 */
		private static function get_resellers(): array
		{
			$cached = get_transient(self::CACHE_KEY);
			if (is_array($cached)) {
				return $cached;
			}

			$posts = get_posts(array(
				'post_type'      => 'rivenditore',
				'post_status'    => 'publish',
				'numberposts'    => -1,
				'no_found_rows'  => true,
			));

			$resellers = array();

			foreach ($posts as $post) {
				$lat_raw = get_post_meta($post->ID, 'latitudine', true);
				$lng_raw = get_post_meta($post->ID, 'longitudine', true);
				$address_raw = get_post_meta($post->ID, 'indirizzo', true);
				$link_raw = get_post_meta($post->ID, 'link_google_maps', true);

				// Legacy/malformed postmeta (e.g. serialized arrays from an older
				// version of this plugin) must never reach a float/string cast unguarded.
				$lat = is_scalar($lat_raw) ? (float) $lat_raw : 0.0;
				$lng = is_scalar($lng_raw) ? (float) $lng_raw : 0.0;

				if (0.0 === $lat && 0.0 === $lng) {
					continue; // Skip incomplete entries rather than plotting them at (0,0).
				}

				$resellers[] = array(
					'title'   => wp_strip_all_tags(get_the_title($post)),
					'address' => sanitize_text_field(is_scalar($address_raw) ? (string) $address_raw : ''),
					'link'    => esc_url_raw(is_scalar($link_raw) ? (string) $link_raw : ''),
					'lat'     => $lat,
					'lng'     => $lng,
				);
			}

			set_transient(self::CACHE_KEY, $resellers, self::CACHE_TTL);

			return $resellers;
		}

		/**
		 * Great-circle distance in kilometres between two coordinates.
		 */
		private static function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
		{
			$lat1 = deg2rad($lat1);
			$lon1 = deg2rad($lon1);
			$lat2 = deg2rad($lat2);
			$lon2 = deg2rad($lon2);

			$dlat = $lat2 - $lat1;
			$dlon = $lon2 - $lon1;

			$a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
			$a = max(0.0, min(1.0, $a)); // Guard against tiny negative values from floating-point rounding.
			$c = 2 * asin(sqrt($a));

			return self::EARTH_RADIUS_KM * $c;
		}
	}
}
