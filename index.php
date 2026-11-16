<?php

/*
 * Plugin Name:       Reseller Map
 * Plugin URI:        https://www.gieffeplus.info/
 * Description:       Gestisce l'anagrafica dei rivenditori e mostra una mappa di ricerca "trova il rivenditore più vicino" tramite lo shortcode [rivenditori], compatibile con WPBakery Page Builder e il tema Porto.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Andrea D'Amico
 * Author URI:        https://www.gieffeplus.info/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://www.gieffeplus.info/
 * Text Domain:       gieffeplus
 * Domain Path:       /languages
 */

/*
Reseller Map is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Reseller Map is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Reseller Map. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

defined('ABSPATH') || die('Not allowed');

// Basic environment guard: fail loudly but gracefully instead of a white screen on old hosting.
if (version_compare(PHP_VERSION, '7.4', '<')) {
	add_action('admin_notices', function () {
		echo '<div class="notice notice-error"><p>' .
			esc_html__('Reseller Map richiede PHP 7.4 o superiore. Aggiorna PHP per attivare il plugin.', 'gieffeplus') .
			'</p></div>';
	});
	return;
}

define('GIEFFEPLUS_VERSION', '2.0.0');
define('GIEFFEPLUS_FILE', __FILE__);
define('GIEFFEPLUS_PATH', plugin_dir_path(__FILE__));
define('GIEFFEPLUS_URL', plugin_dir_url(__FILE__));
define('GIEFFEPLUS_ASSET_VERSION', GIEFFEPLUS_VERSION);

/**
 * Central bootstrap. Keeping everything behind one class avoids leaking
 * globals (the old $reseller*_var pattern) and gives a single init point.
 */
final class Gieffeplus_Plugin
{
	/** @var Gieffeplus_Plugin|null */
	private static $instance = null;

	public static function instance(): Gieffeplus_Plugin
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct()
	{
		$this->includes();

		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('init', array('Gieffeplus_Post_Type', 'register'));
		add_action('init', array('Gieffeplus_Shortcode', 'register'));

		Gieffeplus_Custom_Fields::init();
		Gieffeplus_Rest_Api::init();
		Gieffeplus_Assets::init();
		Gieffeplus_Settings::init();

		if (is_admin()) {
			Gieffeplus_Csv_Import::init();
		}

		// WPBakery is only loaded on demand: no fatal errors if the plugin is inactive.
		add_action('vc_before_init', array('Gieffeplus_Vc_Integration', 'map'));

		if (defined('WP_CLI') && WP_CLI) {
			Gieffeplus_Cli_Import::register();
		}

		register_activation_hook(GIEFFEPLUS_FILE, array($this, 'activate'));
		register_deactivation_hook(GIEFFEPLUS_FILE, array($this, 'deactivate'));
	}

	private function includes(): void
	{
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-post-type.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-custom-fields.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-rest-api.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-shortcode.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-assets.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-settings.php';
		require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-vc-integration.php';

		if (is_admin()) {
			require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-csv-import.php';
		}

		if (defined('WP_CLI') && WP_CLI) {
			require_once GIEFFEPLUS_PATH . 'includes/class-gieffeplus-cli-import.php';
		}
	}

	public function load_textdomain(): void
	{
		load_plugin_textdomain('gieffeplus', false, dirname(plugin_basename(GIEFFEPLUS_FILE)) . '/languages');
	}

	public function activate(): void
	{
		Gieffeplus_Post_Type::register();
		flush_rewrite_rules();
	}

	public function deactivate(): void
	{
		flush_rewrite_rules();
	}
}

Gieffeplus_Plugin::instance();
