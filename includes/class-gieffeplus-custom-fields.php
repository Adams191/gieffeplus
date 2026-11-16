<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Custom_Fields')) {

	/**
	 * Meta box + field registration for the "rivenditore" post type.
	 * Replaces the old free-text meta fields with typed, validated inputs
	 * (numeric lat/lng, URL link) and registers each as REST-visible post meta.
	 */
	class Gieffeplus_Custom_Fields
	{
		private const POST_TYPE = 'rivenditore';
		private const NONCE_ACTION = 'gieffeplus_reseller_fields';
		private const NONCE_NAME = 'gieffeplus_reseller_fields_nonce';

		/** @var array<string,array> */
		private static $fields = array();

		public static function init(): void
		{
			self::$fields = array(
				'indirizzo' => array(
					'label'       => __('Indirizzo', 'gieffeplus'),
					'description' => __("Indirizzo completo del rivenditore, mostrato nell'elenco risultati.", 'gieffeplus'),
					'type'        => 'text',
					'sanitize'    => 'sanitize_text_field',
				),
				'latitudine' => array(
					'label'       => __('Latitudine', 'gieffeplus'),
					'description' => __('Coordinata decimale, es. 41.902782', 'gieffeplus'),
					'type'        => 'number',
					'sanitize'    => array(__CLASS__, 'sanitize_latitude'),
				),
				'longitudine' => array(
					'label'       => __('Longitudine', 'gieffeplus'),
					'description' => __('Coordinata decimale, es. 12.496366', 'gieffeplus'),
					'type'        => 'number',
					'sanitize'    => array(__CLASS__, 'sanitize_longitude'),
				),
				'link_google_maps' => array(
					'label'       => __('Link Google Maps', 'gieffeplus'),
					'description' => __("URL della scheda dell'attività su Google Maps.", 'gieffeplus'),
					'type'        => 'url',
					'sanitize'    => 'esc_url_raw',
				),
			);

			add_action('init', array(__CLASS__, 'register_meta'));
			add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
			add_action('save_post_' . self::POST_TYPE, array(__CLASS__, 'save'), 10, 2);
			add_action('do_meta_boxes', array(__CLASS__, 'remove_default_custom_fields_box'));
		}

		public static function sanitize_latitude($value)
		{
			$value = (float) $value;
			return max(-90, min(90, $value));
		}

		public static function sanitize_longitude($value)
		{
			$value = (float) $value;
			return max(-180, min(180, $value));
		}

		public static function register_meta(): void
		{
			foreach (self::$fields as $key => $field) {
				register_post_meta(self::POST_TYPE, $key, array(
					'type'              => in_array($field['type'], array('number'), true) ? 'number' : 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $field['sanitize'],
					'auth_callback'     => function () {
						return current_user_can('edit_posts');
					},
				));
			}
		}

		public static function remove_default_custom_fields_box(): void
		{
			foreach (array('normal', 'advanced', 'side') as $context) {
				remove_meta_box('postcustom', self::POST_TYPE, $context);
			}
		}

		public static function add_meta_box(): void
		{
			add_meta_box(
				'gieffeplus-reseller-fields',
				esc_html__('Dati Rivenditore', 'gieffeplus'),
				array(__CLASS__, 'render'),
				self::POST_TYPE,
				'normal',
				'high'
			);
		}

		public static function render(WP_Post $post): void
		{
			wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
			?>
			<div class="gieffeplus-field-grid">
				<?php foreach (self::$fields as $key => $field) :
					$value = get_post_meta($post->ID, $key, true);
					$input_type = $field['type'] === 'number' ? 'text' : $field['type']; // free text so partial coords can be typed
					?>
					<p class="gieffeplus-field">
						<label for="gieffeplus-<?php echo esc_attr($key); ?>">
							<strong><?php echo esc_html($field['label']); ?></strong>
						</label>
						<input
							type="<?php echo esc_attr($input_type); ?>"
							inputmode="<?php echo esc_attr($field['type'] === 'number' ? 'decimal' : 'text'); ?>"
							id="gieffeplus-<?php echo esc_attr($key); ?>"
							name="gieffeplus_meta[<?php echo esc_attr($key); ?>]"
							value="<?php echo esc_attr($value); ?>"
							class="widefat"
						/>
						<?php if (!empty($field['description'])) : ?>
							<span class="description"><?php echo esc_html($field['description']); ?></span>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<style>
				.gieffeplus-field-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
				.gieffeplus-field .description { display: block; margin-top: 4px; color: #646970; font-size: 12px; }
			</style>
			<?php
		}

		public static function save(int $post_id, WP_Post $post): void
		{
			if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(wp_unslash($_POST[self::NONCE_NAME]), self::NONCE_ACTION)) {
				return;
			}

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (!current_user_can('edit_post', $post_id)) {
				return;
			}

			$submitted = isset($_POST['gieffeplus_meta']) && is_array($_POST['gieffeplus_meta'])
				? wp_unslash($_POST['gieffeplus_meta'])
				: array();

			foreach (self::$fields as $key => $field) {
				if (isset($submitted[$key]) && trim((string) $submitted[$key]) !== '') {
					$sanitize = $field['sanitize'];
					$value = is_callable($sanitize) ? call_user_func($sanitize, $submitted[$key]) : sanitize_text_field($submitted[$key]);
					update_post_meta($post_id, $key, $value);
				} else {
					delete_post_meta($post_id, $key);
				}
			}

			// The public reseller list is cached; invalidate it whenever data changes.
			delete_transient('gieffeplus_resellers_cache');
		}
	}
}
