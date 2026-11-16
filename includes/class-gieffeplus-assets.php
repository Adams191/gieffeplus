<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Assets')) {

	/**
	 * Registers (but does not enqueue) all front-end assets. Enqueuing happens
	 * lazily from the shortcode, so pages without [rivenditori] never load
	 * jQuery-free custom JS/CSS or the Google Maps SDK.
	 */
	class Gieffeplus_Assets
	{
		public static function init(): void
		{
			add_action('wp_enqueue_scripts', array(__CLASS__, 'register'));
			add_filter('script_loader_tag', array(__CLASS__, 'add_async_attribute'), 10, 2);
		}

		public static function register(): void
		{
			wp_register_style(
				'gieffeplus-reseller-map',
				GIEFFEPLUS_URL . 'css/style.css',
				array(),
				GIEFFEPLUS_ASSET_VERSION
			);

			wp_register_script(
				'gieffeplus-reseller-map',
				GIEFFEPLUS_URL . 'js/script.js',
				array('jquery'),
				GIEFFEPLUS_ASSET_VERSION,
				array('in_footer' => true)
			);

			wp_localize_script('gieffeplus-reseller-map', 'gieffeplusSettings', array(
				'restUrl' => esc_url_raw(rest_url('gieffeplus/v1/reseller')),
			));

			$api_key = Gieffeplus_Settings::get_api_key();

			if ($api_key) {
				$maps_src = add_query_arg(
					array(
						'key'       => rawurlencode($api_key),
						'libraries' => 'places',
						'loading'   => 'async',
						'callback'  => 'gieffeplusInitMap',
					),
					'https://maps.googleapis.com/maps/api/js'
				);

				wp_register_script(
					'gieffeplus-google-maps',
					$maps_src,
					array('gieffeplus-reseller-map'),
					null,
					array('in_footer' => true)
				);
			}

			// Scripts registered with in_footer=true are safe to enqueue later
			// from the shortcode callback (they print during wp_footer, which
			// always fires after the_content). Styles are not: WordPress prints
			// enqueued <link> tags during wp_head, before post content — and
			// therefore any shortcode inside it — has even run. So for styles we
			// pre-scan the current post content here, while we're still early
			// enough for wp_head to pick them up.
			if ($api_key && self::current_content_has_shortcode()) {
				wp_enqueue_style('gieffeplus-reseller-map');
			}
		}

		private static function current_content_has_shortcode(): bool
		{
			if (!is_singular()) {
				return false;
			}

			$post = get_post();

			return $post instanceof WP_Post && has_shortcode((string) $post->post_content, 'rivenditori');
		}

		/**
		 * Google explicitly recommends loading the Maps JS SDK with `async`;
		 * the `callback` query arg makes execution order irrelevant.
		 */
		public static function add_async_attribute(string $tag, string $handle): string
		{
			if ('gieffeplus-google-maps' !== $handle) {
				return $tag;
			}

			if (false !== strpos($tag, ' async')) {
				return $tag;
			}

			return str_replace(' src=', ' async src=', $tag);
		}
	}
}
