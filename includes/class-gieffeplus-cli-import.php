<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Cli_Import')) {

	/**
	 * One-time historical data import, now a WP-CLI command instead of a
	 * function that could be wired up to a public REST/AJAX endpoint.
	 * Usage: wp gieffeplus import-resellers [--dry-run]
	 */
	class Gieffeplus_Cli_Import
	{
		public static function register(): void
		{
			WP_CLI::add_command('gieffeplus import-resellers', array(__CLASS__, 'import'));
		}

		/**
		 * Imports the legacy reseller dataset as "rivenditore" posts.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Show what would be imported without creating any post.
		 *
		 * ## EXAMPLES
		 *
		 *     wp gieffeplus import-resellers
		 *     wp gieffeplus import-resellers --dry-run
		 */
		public static function import(array $args, array $assoc_args): void
		{
			$dry_run = isset($assoc_args['dry-run']);
			$file    = GIEFFEPLUS_PATH . 'data/resellers-import.php';

			if (!file_exists($file)) {
				WP_CLI::error('File dati non trovato: ' . $file);
				return;
			}

			$locations = include $file;

			if (!is_array($locations) || empty($locations)) {
				WP_CLI::error('Nessun dato da importare.');
				return;
			}

			$created = 0;
			$skipped = 0;
			$progress = \WP_CLI\Utils\make_progress_bar('Importazione rivenditori', count($locations));

			foreach ($locations as $row) {
				list($link_markup, $lat, $lng, $address) = $row;

				$title = wp_strip_all_tags($link_markup);
				$link  = '';
				if (preg_match('/href=[\'"]([^\'"]+)[\'"]/', $link_markup, $matches)) {
					$link = esc_url_raw($matches[1]);
				}

				if ($dry_run) {
					WP_CLI::log(sprintf('[dry-run] %s (%s, %s) — %s', $title, $lat, $lng, $address));
					$progress->tick();
					continue;
				}

				$post_id = wp_insert_post(array(
					'post_title'  => sanitize_text_field($title),
					'post_status' => 'publish',
					'post_author' => get_current_user_id() ?: 1,
					'post_type'   => 'rivenditore',
				), true);

				if (is_wp_error($post_id) || !$post_id) {
					WP_CLI::warning('Import fallito per: ' . $title);
					$skipped++;
					$progress->tick();
					continue;
				}

				update_post_meta($post_id, 'indirizzo', sanitize_text_field($address));
				update_post_meta($post_id, 'latitudine', Gieffeplus_Custom_Fields::sanitize_latitude($lat));
				update_post_meta($post_id, 'longitudine', Gieffeplus_Custom_Fields::sanitize_longitude($lng));
				update_post_meta($post_id, 'link_google_maps', $link);

				$created++;
				$progress->tick();
			}

			$progress->finish();
			delete_transient('gieffeplus_resellers_cache');

			if ($dry_run) {
				WP_CLI::success(sprintf('%d rivenditori pronti per l\'importazione (dry-run, nessuna modifica salvata).', count($locations)));
				return;
			}

			WP_CLI::success(sprintf('%d rivenditori importati, %d saltati.', $created, $skipped));
		}
	}
}
