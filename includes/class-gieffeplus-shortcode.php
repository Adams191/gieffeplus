<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Shortcode')) {

	class Gieffeplus_Shortcode
	{
		private const TAG = 'rivenditori';

		/** @var int */
		private static $instance_count = 0;

		public static function register(): void
		{
			add_shortcode(self::TAG, array(__CLASS__, 'render'));
		}

		public static function render($atts): string
		{
			$atts = shortcode_atts(array(
				'title'          => __('Trova il rivenditore più vicino a te', 'gieffeplus'),
				'subtitle'       => __('Inserisci il tuo indirizzo per iniziare la ricerca', 'gieffeplus'),
				'placeholder'    => __('CAP, città o indirizzo', 'gieffeplus'),
				'button_text'    => __('Cerca', 'gieffeplus'),
				'results_title'  => __('Gli store nelle tue vicinanze', 'gieffeplus'),
				'results_count'  => 6,
				'map_height'     => 420,
				'zoom'           => 10,
				'center_lat'     => 41.8719,
				'center_lng'     => 12.5674,
			), $atts, self::TAG);

			$api_key = Gieffeplus_Settings::get_api_key();

			if (empty($api_key)) {
				if (current_user_can('manage_options')) {
					return '<p class="gieffeplus-admin-notice">' .
						esc_html__('Reseller Map: configura la chiave API di Google Maps in Rivenditori → Impostazioni per attivare la mappa.', 'gieffeplus') .
						'</p>';
				}
				return '';
			}

			wp_enqueue_style('gieffeplus-reseller-map');
			wp_enqueue_script('gieffeplus-reseller-map');
			wp_enqueue_script('gieffeplus-google-maps');

			self::$instance_count++;
			$uid = 'gieffeplus-' . self::$instance_count . '-' . wp_generate_password(4, false, false);

			$zoom          = absint($atts['zoom']);
			$results_count = max(1, min(20, absint($atts['results_count'])));
			$map_height    = max(200, absint($atts['map_height']));
			$center_lat    = (float) $atts['center_lat'];
			$center_lng    = (float) $atts['center_lng'];

			ob_start();
			?>
			<div
				class="gieffeplus-reseller-map"
				data-uid="<?php echo esc_attr($uid); ?>"
				data-zoom="<?php echo esc_attr($zoom); ?>"
				data-center-lat="<?php echo esc_attr($center_lat); ?>"
				data-center-lng="<?php echo esc_attr($center_lng); ?>"
				data-results="<?php echo esc_attr($results_count); ?>"
				data-msg-searching="<?php echo esc_attr__('Ricerca in corso…', 'gieffeplus'); ?>"
				data-msg-not-found="<?php echo esc_attr__('Indirizzo non trovato. Prova con un altro indirizzo.', 'gieffeplus'); ?>"
				data-msg-error="<?php echo esc_attr__('Si è verificato un errore. Riprova più tardi.', 'gieffeplus'); ?>"
				data-msg-empty="<?php echo esc_attr__('Nessun rivenditore trovato nelle vicinanze.', 'gieffeplus'); ?>"
				data-msg-locating="<?php echo esc_attr__('Rilevamento della posizione…', 'gieffeplus'); ?>"
				data-msg-geo-error="<?php echo esc_attr__('Impossibile rilevare la tua posizione.', 'gieffeplus'); ?>"
				data-msg-geo-denied="<?php echo esc_attr__('Permesso di geolocalizzazione negato.', 'gieffeplus'); ?>"
			>
				<?php if ($atts['title'] || $atts['subtitle']) : ?>
					<div class="gieffeplus-header">
						<?php if ($atts['title']) : ?>
							<h3 class="gieffeplus-title porto-sicon-title"><?php echo esc_html($atts['title']); ?></h3>
						<?php endif; ?>
						<?php if ($atts['subtitle']) : ?>
							<p class="gieffeplus-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<form class="gieffeplus-search-form" data-role="form">
					<label class="screen-reader-text" for="<?php echo esc_attr($uid); ?>-address">
						<?php echo esc_html($atts['placeholder']); ?>
					</label>
					<div class="gieffeplus-input-group">
						<input
							type="text"
							id="<?php echo esc_attr($uid); ?>-address"
							data-role="address"
							placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
							autocomplete="off"
						/>
						<button
							type="button"
							class="gieffeplus-geo-btn"
							data-role="geolocate"
							title="<?php echo esc_attr__('Usa la mia posizione', 'gieffeplus'); ?>"
							aria-label="<?php echo esc_attr__('Usa la mia posizione', 'gieffeplus'); ?>"
						>
							<i class="fas fa-location-arrow" aria-hidden="true"></i>
						</button>
						<button type="submit" class="gieffeplus-search-btn">
							<span class="gieffeplus-btn-text"><?php echo esc_html($atts['button_text']); ?></span>
							<span class="gieffeplus-spinner" aria-hidden="true"></span>
						</button>
					</div>
					<p class="gieffeplus-status" data-role="status" aria-live="polite"></p>
				</form>

				<div
					class="gieffeplus-map"
					data-role="map"
					style="height: <?php echo esc_attr($map_height); ?>px;"
					role="application"
					aria-label="<?php echo esc_attr__('Mappa rivenditori', 'gieffeplus'); ?>"
				></div>

				<?php if ($atts['results_title']) : ?>
					<h3 class="gieffeplus-results-title porto-sicon-title"><?php echo esc_html($atts['results_title']); ?></h3>
				<?php endif; ?>

				<ul class="gieffeplus-results-list" data-role="results"></ul>
			</div>
			<?php

			return (string) ob_get_clean();
		}
	}
}
