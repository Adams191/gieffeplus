<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Settings')) {

	/**
	 * Admin settings screen. Moves the Google Maps API key out of source code
	 * (it was previously hardcoded and publicly visible in the plugin file)
	 * into a normal, capability-gated WordPress option.
	 */
	class Gieffeplus_Settings
	{
		private const OPTION_KEY = 'gieffeplus_gmaps_api_key';
		private const OPTION_GROUP = 'gieffeplus_settings';
		private const PAGE_SLUG = 'gieffeplus-settings';

		public static function init(): void
		{
			add_action('admin_menu', array(__CLASS__, 'add_menu'));
			add_action('admin_init', array(__CLASS__, 'register_settings'));
			add_action('admin_notices', array(__CLASS__, 'maybe_show_missing_key_notice'));

			$plugin_basename = plugin_basename(GIEFFEPLUS_FILE);
			add_filter("plugin_action_links_{$plugin_basename}", array(__CLASS__, 'add_settings_link'));
		}

		public static function get_api_key(): string
		{
			return (string) get_option(self::OPTION_KEY, '');
		}

		public static function add_menu(): void
		{
			add_submenu_page(
				'edit.php?post_type=rivenditore',
				esc_html__('Impostazioni Reseller Map', 'gieffeplus'),
				esc_html__('Impostazioni', 'gieffeplus'),
				'manage_options',
				self::PAGE_SLUG,
				array(__CLASS__, 'render_page')
			);
		}

		public static function add_settings_link(array $links): array
		{
			$url = admin_url('edit.php?post_type=rivenditore&page=' . self::PAGE_SLUG);
			$links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Impostazioni', 'gieffeplus') . '</a>';

			return $links;
		}

		public static function register_settings(): void
		{
			register_setting(self::OPTION_GROUP, self::OPTION_KEY, array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			));
		}

		public static function maybe_show_missing_key_notice(): void
		{
			$screen = get_current_screen();
			if (!$screen || 'rivenditore' !== $screen->post_type) {
				return;
			}

			if (self::get_api_key() || !current_user_can('manage_options')) {
				return;
			}

			$url = admin_url('edit.php?post_type=rivenditore&page=' . self::PAGE_SLUG);
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: settings page link */
						esc_html__('Reseller Map: nessuna chiave API di Google Maps configurata. La mappa non funzionerà finché non la imposti nelle %s.', 'gieffeplus'),
						'<a href="' . esc_url($url) . '">' . esc_html__('impostazioni del plugin', 'gieffeplus') . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}

		public static function render_page(): void
		{
			if (!current_user_can('manage_options')) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php echo esc_html__('Impostazioni Reseller Map', 'gieffeplus'); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields(self::OPTION_GROUP); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="gieffeplus-gmaps-key"><?php echo esc_html__('Google Maps API Key', 'gieffeplus'); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="gieffeplus-gmaps-key"
									name="<?php echo esc_attr(self::OPTION_KEY); ?>"
									value="<?php echo esc_attr(self::get_api_key()); ?>"
									class="regular-text"
									autocomplete="off"
								/>
								<p class="description">
									<?php
									echo esc_html__(
										'Chiave con Maps JavaScript API e Places API abilitate. Consigliato: limita la chiave per referrer HTTP al dominio del sito, nella console Google Cloud.',
										'gieffeplus'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
