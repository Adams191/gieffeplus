<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Csv_Import')) {

	/**
	 * Admin-only bulk import/update of "rivenditore" posts from a CSV file.
	 * Complements the WP-CLI importer for admins without server access.
	 */
	class Gieffeplus_Csv_Import
	{
		private const PAGE_SLUG = 'gieffeplus-csv-import';
		private const NONCE_ACTION = 'gieffeplus_csv_import';
		private const TEMPLATE_ACTION = 'gieffeplus_csv_template';
		private const MAX_FILE_SIZE = 2 * MB_IN_BYTES;

		public static function init(): void
		{
			add_action('admin_menu', array(__CLASS__, 'add_menu'));
			add_action('admin_post_gieffeplus_csv_import', array(__CLASS__, 'handle_import'));
			add_action('admin_post_gieffeplus_csv_template', array(__CLASS__, 'handle_template_download'));
		}

		public static function add_menu(): void
		{
			add_submenu_page(
				'edit.php?post_type=rivenditore',
				esc_html__('Importa CSV Rivenditori', 'gieffeplus'),
				esc_html__('Importa CSV', 'gieffeplus'),
				'manage_options',
				self::PAGE_SLUG,
				array(__CLASS__, 'render_page')
			);
		}

		/** @return array<string,array<string>> canonical field => accepted header labels */
		private static function header_aliases(): array
		{
			return array(
				'title'            => array('title', 'titolo', 'nome', 'ragione sociale', 'ragione_sociale'),
				'indirizzo'        => array('indirizzo', 'address'),
				'latitudine'       => array('latitudine', 'lat'),
				'longitudine'      => array('longitudine', 'lng', 'lon', 'long'),
				'link_google_maps' => array('link_google_maps', 'link', 'maps', 'google maps', 'link maps'),
			);
		}

		private static function map_headers(array $header_row): array
		{
			$aliases = self::header_aliases();
			$map = array();

			foreach ($header_row as $index => $label) {
				$normalized = strtolower(trim((string) $label));
				$normalized = ltrim($normalized, "\xEF\xBB\xBF");

				foreach ($aliases as $key => $accepted) {
					if (!isset($map[$key]) && in_array($normalized, $accepted, true)) {
						$map[$key] = $index;
					}
				}
			}

			return $map;
		}

		public static function render_page(): void
		{
			if (!current_user_can('manage_options')) {
				return;
			}

			$result = get_transient('gieffeplus_csv_import_result_' . get_current_user_id());
			if ($result) {
				delete_transient('gieffeplus_csv_import_result_' . get_current_user_id());
			}

			$template_url = wp_nonce_url(
				admin_url('admin-post.php?action=gieffeplus_csv_template'),
				self::TEMPLATE_ACTION
			);
			?>
			<div class="wrap">
				<h1><?php echo esc_html__('Importa Rivenditori da CSV', 'gieffeplus'); ?></h1>

				<?php if (is_array($result)) : ?>
					<div class="notice notice-<?php echo $result['errors'] ? 'warning' : 'success'; ?> is-dismissible">
						<p>
							<?php
							printf(
								/* translators: 1: created count, 2: updated count, 3: skipped count */
								esc_html__('Import completato: %1$d creati, %2$d aggiornati, %3$d ignorati.', 'gieffeplus'),
								(int) $result['created'],
								(int) $result['updated'],
								(int) $result['skipped']
							);
							?>
						</p>
						<?php if (!empty($result['errors'])) : ?>
							<ul style="margin-left:20px;list-style:disc;">
								<?php foreach (array_slice($result['errors'], 0, 30) as $error) : ?>
									<li><?php echo esc_html($error); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<p>
					<?php echo esc_html__('Carica un file CSV con le colonne: titolo, indirizzo, latitudine, longitudine, link Google Maps (opzionale).', 'gieffeplus'); ?>
					<a href="<?php echo esc_url($template_url); ?>"><?php echo esc_html__('Scarica un modello di esempio.', 'gieffeplus'); ?></a>
				</p>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field(self::NONCE_ACTION); ?>
					<input type="hidden" name="action" value="gieffeplus_csv_import" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gieffeplus-csv-file"><?php echo esc_html__('File CSV', 'gieffeplus'); ?></label></th>
							<td><input type="file" id="gieffeplus-csv-file" name="gieffeplus_csv" accept=".csv,text/csv" required /></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__('Separatore colonne', 'gieffeplus'); ?></th>
							<td>
								<label><input type="radio" name="delimiter" value="," checked /> <?php echo esc_html__('Virgola (,)', 'gieffeplus'); ?></label>
								&nbsp;&nbsp;
								<label><input type="radio" name="delimiter" value=";" /> <?php echo esc_html__('Punto e virgola (;) — tipico export Excel italiano', 'gieffeplus'); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__('Rivenditori esistenti', 'gieffeplus'); ?></th>
							<td>
								<label>
									<input type="checkbox" name="update_existing" value="1" checked />
									<?php echo esc_html__('Aggiorna i rivenditori esistenti con lo stesso titolo, invece di duplicarli', 'gieffeplus'); ?>
								</label>
							</td>
						</tr>
					</table>
					<?php submit_button(__('Importa', 'gieffeplus')); ?>
				</form>
			</div>
			<?php
		}

		public static function handle_template_download(): void
		{
			if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(wp_unslash($_GET['_wpnonce']), self::TEMPLATE_ACTION)) {
				wp_die(esc_html__('Richiesta non autorizzata.', 'gieffeplus'), 403);
			}

			nocache_headers();
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="rivenditori-modello.csv"');

			$rows = array(
				array('titolo', 'indirizzo', 'latitudine', 'longitudine', 'link_google_maps'),
				array('Esempio Sport SRL', 'Via Roma 1, 00100 Roma (RM)', '41.9028', '12.4964', 'https://maps.google.com/?q=41.9028,12.4964'),
			);

			$out = fopen('php://output', 'w');
			foreach ($rows as $row) {
				fputcsv($out, $row);
			}
			fclose($out);
			exit;
		}

		public static function handle_import(): void
		{
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('Permessi insufficienti.', 'gieffeplus'), 403);
			}

			check_admin_referer(self::NONCE_ACTION);

			$redirect = admin_url('edit.php?post_type=rivenditore&page=' . self::PAGE_SLUG);
			$user_id = get_current_user_id();

			$summary = self::process_upload();

			set_transient('gieffeplus_csv_import_result_' . $user_id, $summary, 60);
			delete_transient('gieffeplus_resellers_cache');

			wp_safe_redirect($redirect);
			exit;
		}

		private static function process_upload(): array
		{
			$errors  = array();
			$created = 0;
			$updated = 0;
			$skipped = 0;

			$file = $_FILES['gieffeplus_csv'] ?? null;

			if (!$file || !is_array($file) || UPLOAD_ERR_OK !== $file['error']) {
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('Nessun file valido caricato.', 'gieffeplus')));
			}

			if ($file['size'] > self::MAX_FILE_SIZE) {
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('Il file supera la dimensione massima consentita (2MB).', 'gieffeplus')));
			}

			if (!is_uploaded_file($file['tmp_name'])) {
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('Upload non valido.', 'gieffeplus')));
			}

			$extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
			if ('csv' !== $extension) {
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('Il file deve avere estensione .csv.', 'gieffeplus')));
			}

			$delimiter = isset($_POST['delimiter']) && ';' === $_POST['delimiter'] ? ';' : ',';
			$update_existing = !empty($_POST['update_existing']);

			$handle = fopen($file['tmp_name'], 'r');
			if (!$handle) {
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('Impossibile leggere il file caricato.', 'gieffeplus')));
			}

			$header_row = fgetcsv($handle, 0, $delimiter);
			if (!$header_row) {
				fclose($handle);
				return array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array(__('File CSV vuoto o non leggibile.', 'gieffeplus')));
			}

			$map = self::map_headers($header_row);
			$required = array('title', 'indirizzo', 'latitudine', 'longitudine');
			$missing = array_diff($required, array_keys($map));

			if (!empty($missing)) {
				fclose($handle);
				return array(
					'created' => 0,
					'updated' => 0,
					'skipped' => 0,
					'errors'  => array(
						sprintf(
							/* translators: %s: comma separated list of missing column names */
							__('Colonne obbligatorie mancanti nel CSV: %s', 'gieffeplus'),
							implode(', ', $missing)
						),
					),
				);
			}

			$line = 1;
			while (false !== ($row = fgetcsv($handle, 0, $delimiter))) {
				$line++;

				$title = isset($row[$map['title']]) ? sanitize_text_field(trim((string) $row[$map['title']])) : '';
				if ('' === $title) {
					$skipped++;
					$errors[] = sprintf(__('Riga %d: titolo mancante, ignorata.', 'gieffeplus'), $line);
					continue;
				}

				$lat = isset($row[$map['latitudine']]) ? Gieffeplus_Custom_Fields::sanitize_latitude($row[$map['latitudine']]) : 0.0;
				$lng = isset($row[$map['longitudine']]) ? Gieffeplus_Custom_Fields::sanitize_longitude($row[$map['longitudine']]) : 0.0;

				if (0.0 === $lat && 0.0 === $lng) {
					$errors[] = sprintf(__('Riga %d ("%s"): coordinate mancanti o non valide, verificale manualmente.', 'gieffeplus'), $line, $title);
				}

				$address = isset($row[$map['indirizzo']]) ? sanitize_text_field(trim((string) $row[$map['indirizzo']])) : '';
				$link = '';
				if (isset($map['link_google_maps'], $row[$map['link_google_maps']])) {
					$link = esc_url_raw(trim((string) $row[$map['link_google_maps']]));
				}

				$existing_id = 0;
				if ($update_existing) {
					$existing = get_posts(array(
						'post_type'      => 'rivenditore',
						'title'          => $title,
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'post_status'    => 'any',
					));
					if (!empty($existing)) {
						$existing_id = (int) $existing[0];
					}
				}

				if ($existing_id) {
					wp_update_post(array('ID' => $existing_id, 'post_title' => $title));
					$post_id = $existing_id;
					$updated++;
				} else {
					$post_id = wp_insert_post(array(
						'post_title'  => $title,
						'post_status' => 'publish',
						'post_author' => get_current_user_id() ?: 1,
						'post_type'   => 'rivenditore',
					), true);

					if (is_wp_error($post_id) || !$post_id) {
						$skipped++;
						$errors[] = sprintf(__('Riga %d ("%s"): creazione fallita.', 'gieffeplus'), $line, $title);
						continue;
					}
					$created++;
				}

				update_post_meta($post_id, 'indirizzo', $address);
				update_post_meta($post_id, 'latitudine', $lat);
				update_post_meta($post_id, 'longitudine', $lng);
				update_post_meta($post_id, 'link_google_maps', $link);
			}

			fclose($handle);

			return array(
				'created' => $created,
				'updated' => $updated,
				'skipped' => $skipped,
				'errors'  => $errors,
			);
		}
	}
}
