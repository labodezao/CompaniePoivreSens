<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPS_Admin {

	/* ── Menu ─────────────────────────────────────────────────── */
	public static function register_menu() {
		add_menu_page(
			'CF Post-Séance',
			'Post-Séance',
			'manage_options',
			'cfps-sequences',
			[ __CLASS__, 'page_sequences' ],
			'dashicons-email-alt2',
			58
		);
		add_submenu_page(
			'cfps-sequences',
			'Séquences — CF Post-Séance',
			'Séquences',
			'manage_options',
			'cfps-sequences',
			[ __CLASS__, 'page_sequences' ]
		);
		add_submenu_page(
			'cfps-sequences',
			'Paramètres — CF Post-Séance',
			'Paramètres',
			'manage_options',
			'cfps-settings',
			[ __CLASS__, 'page_settings' ]
		);
	}

	/* ── Settings API ─────────────────────────────────────────── */
	public static function register_settings() {
		register_setting( 'cfps_settings_group', 'cfps_settings', [
			'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
		] );
	}

	public static function sanitize_settings( $raw ) {
		$clean = [];
		$clean['admin_email']    = sanitize_email( $raw['admin_email'] ?? '' );
		$clean['from_name']      = sanitize_text_field( $raw['from_name'] ?? '' );
		$clean['from_email']     = sanitize_email( $raw['from_email'] ?? '' );
		$clean['whatsapp_num']   = preg_replace( '/[^0-9]/', '', $raw['whatsapp_num'] ?? '' );
		$clean['appt_type_slug']   = sanitize_title( $raw['appt_type_slug'] ?? 'constellation-individuelle' );
		$clean['temoignage_url']   = esc_url_raw( $raw['temoignage_url'] ?? '' );
		$clean['google_review_url'] = esc_url_raw( $raw['google_review_url'] ?? '' );

		$clean['articles'] = [];
		foreach ( array_keys( CFPS_Sequence::theme_labels() ) as $key ) {
			$url = esc_url_raw( $raw['articles'][ $key ] ?? '' );
			$clean['articles'][ $key ] = $url;
		}

		return $clean;
	}

	/* ── Page : Séquences ─────────────────────────────────────── */
	public static function page_sequences() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		// Manual cron trigger
		if ( isset( $_POST['cfps_run_now'] ) && check_admin_referer( 'cfps_run_now' ) ) {
			CFPS_Sequence::run_daily();
			echo '<div class="notice notice-success"><p>Exécution manuelle terminée.</p></div>';
		}

		// Filter
		$filter = sanitize_key( $_GET['cfps_filter'] ?? 'active' );
		$where  = $filter === 'all' ? '' : $wpdb->prepare( 'WHERE statut = %s', $filter );

		$rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY date_seance DESC LIMIT 100" );

		$theme_labels = CFPS_Sequence::theme_labels();
		$statuts = [ 'active' => 'Active', 'complete' => 'Terminée', 'nogo' => 'No-go', 'desabonne' => 'Désabonné' ];
		?>
		<div class="wrap">
			<h1>CF Post-Séance — Séquences</h1>

			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'cfps_run_now' ); ?>
				<button type="submit" name="cfps_run_now" class="button">▶ Exécuter le cron maintenant</button>
			</form>

			<div style="margin:12px 0;">
				<?php foreach ( array_merge( ['all' => 'Toutes'], $statuts ) as $key => $label ) : ?>
					<a href="?page=cfps-sequences&cfps_filter=<?= esc_attr( $key ) ?>"
					   class="button <?= $filter === $key ? 'button-primary' : '' ?>"
					   style="margin-right:4px;"><?= esc_html( $label ) ?></a>
				<?php endforeach; ?>
			</div>

			<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th>Prénom</th>
						<th>Email</th>
						<th>Date séance</th>
						<th>Thème</th>
						<th>J+1</th>
						<th>J+7</th>
						<th>J+14</th>
						<th>J+28 notif.</th>
						<th>J+28 envoi</th>
						<th>Statut</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="10" style="text-align:center;padding:20px;color:#888;">Aucune séquence trouvée.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
					<tr id="cfps-row-<?= (int) $row->id ?>" class="cfeb-booking-row">
						<td><strong class="cfeb-row-name"><?= esc_html( $row->prenom ) ?></strong></td>
						<td data-label="Email"><?= esc_html( $row->email ) ?></td>
						<td data-label="Séance"><?= esc_html( date_i18n( 'd/m/Y', strtotime( $row->date_seance ) ) ) ?></td>
						<td data-label="Thème">
							<select class="cfps-theme-select" data-id="<?= (int) $row->id ?>" style="max-width:160px;">
								<option value="">— non défini —</option>
								<?php foreach ( $theme_labels as $slug => $label ) : ?>
									<option value="<?= esc_attr( $slug ) ?>" <?= selected( $row->theme_slug, $slug, false ) ?>><?= esc_html( $label ) ?></option>
								<?php endforeach; ?>
							</select>
							<span class="cfps-save-status" style="font-size:11px;margin-left:4px;"></span>
						</td>
						<td data-label="J+1"><?= $row->step_1_sent_at ? '<span style="color:#2d8a4e;">✓</span>' : '—' ?></td>
						<td data-label="J+7"><?= $row->step_2_sent_at ? '<span style="color:#2d8a4e;">✓</span>' : '—' ?></td>
						<td data-label="J+14"><?= $row->step_3_sent_at ? '<span style="color:#2d8a4e;">✓</span>' : '—' ?></td>
						<td data-label="J+28 notif."><?= $row->step_4_notif_at ? '<span style="color:#2d8a4e;">✓</span>' : '—' ?></td>
						<td data-label="J+28 envoi"><?= $row->step_4_sent_at ? '<span style="color:#2d8a4e;">✓</span>' : ( $row->statut === 'nogo' ? '<span style="color:#c0392b;">✗</span>' : '—' ) ?></td>
						<td data-label="Statut">
							<?php
							$colors = [ 'active' => '#2d8a4e', 'complete' => '#1a6ea8', 'nogo' => '#c0392b', 'desabonne' => '#888' ];
							$color  = $colors[ $row->statut ] ?? '#555';
							echo '<span style="color:' . $color . ';font-weight:500;">' . esc_html( $statuts[ $row->statut ] ?? $row->statut ) . '</span>';
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<script>
		document.querySelectorAll('.cfps-theme-select').forEach(function(sel) {
			sel.addEventListener('change', function() {
				var id     = this.dataset.id;
				var theme  = this.value;
				var status = this.closest('tr').querySelector('.cfps-save-status');
				status.textContent = '...';
				var data = new FormData();
				data.append('action',   'cfps_save_theme');
				data.append('cfps_id',  id);
				data.append('theme',    theme);
				data.append('nonce',    '<?= wp_create_nonce( 'cfps_save_theme' ) ?>');
				fetch(ajaxurl, { method: 'POST', body: data })
					.then(r => r.json())
					.then(function(r) {
						status.textContent = r.success ? '✓ sauvegardé' : '⚠ erreur';
						setTimeout(function() { status.textContent = ''; }, 2000);
					})
					.catch(function() { status.textContent = '⚠ erreur'; });
			});
		});
		</script>
		<?php
	}

	/* ── Page : Paramètres ────────────────────────────────────── */
	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s      = get_option( 'cfps_settings', [] );
		$themes = CFPS_Sequence::theme_labels();
		?>
		<div class="wrap">
			<h1>CF Post-Séance — Paramètres</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cfps_settings_group' ); ?>

				<h2 class="title">Expéditeur &amp; notifications</h2>
				<table class="form-table">
					<tr>
						<th>Email admin (notifications GO/NO-GO)</th>
						<td><input type="email" name="cfps_settings[admin_email]" value="<?= esc_attr( $s['admin_email'] ?? '' ) ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>Nom expéditeur</th>
						<td><input type="text" name="cfps_settings[from_name]" value="<?= esc_attr( $s['from_name'] ?? '' ) ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>Email expéditeur</th>
						<td><input type="email" name="cfps_settings[from_email]" value="<?= esc_attr( $s['from_email'] ?? '' ) ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>Numéro WhatsApp (sans +, ex: 33783031670)</th>
						<td><input type="text" name="cfps_settings[whatsapp_num]" value="<?= esc_attr( $s['whatsapp_num'] ?? '' ) ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>URL de la page témoignage</th>
						<td>
							<input type="url" name="cfps_settings[temoignage_url]" value="<?= esc_attr( $s['temoignage_url'] ?? '' ) ?>" class="large-text" placeholder="https://soins.ewendaviau.com/temoignage/">
							<p class="description">Page contenant le code court <code>[cf_temoignage_form]</code>. Non utilisée actuellement par l'email 3 (J+14, qui ne renvoie plus que vers Google et WhatsApp) — conservée au cas où ce CTA serait réactivé plus tard.</p>
						</td>
					</tr>
					<tr>
						<th>URL de l'avis Google</th>
						<td>
							<input type="url" name="cfps_settings[google_review_url]" value="<?= esc_attr( $s['google_review_url'] ?? '' ) ?>" class="large-text" placeholder="<?= esc_attr( CFEB_GOOGLE_REVIEW_URL ) ?>">
							<p class="description">Lien direct vers le formulaire d'avis Google (fiche « Constellations systémiques et familiales St Nazaire »). Laissez vide pour utiliser la valeur par défaut du plugin. Utilisé dans l'email 3 (J+14).</p>
						</td>
					</tr>
					<tr>
						<th>Slug du type de RDV individuel</th>
						<td>
							<input type="text" name="cfps_settings[appt_type_slug]" value="<?= esc_attr( $s['appt_type_slug'] ?? 'constellation-individuelle' ) ?>" class="regular-text">
							<p class="description">Le <code>post_name</code> du type de rendez-vous individuel dans votre plugin de réservation.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Articles piliers (Email 2 — J+7)</h2>
				<p>Saisissez l'URL de l'article pilier pour chaque thème. Laissez vide pour utiliser la valeur par défaut (thème J).</p>
				<table class="form-table">
					<?php foreach ( $themes as $key => $label ) : ?>
					<tr>
						<th><?= esc_html( $label ) ?></th>
						<td>
							<input type="url" name="cfps_settings[articles][<?= esc_attr( $key ) ?>]"
								   value="<?= esc_attr( $s['articles'][ $key ] ?? '' ) ?>"
								   class="large-text"
								   placeholder="https://soins.ewendaviau.com/article-pilier-<?= esc_attr( strtolower( $key ) ) ?>/">
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button( 'Enregistrer les paramètres' ); ?>
			</form>
		</div>
		<?php
	}

	/* ── AJAX : sauvegarder le thème d'une ligne ─────────────── */
	public static function ajax_save_theme() {
		check_ajax_referer( 'cfps_save_theme', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$id    = absint( $_POST['cfps_id'] ?? 0 );
		$theme = sanitize_text_field( $_POST['theme'] ?? '' );

		if ( ! $id ) wp_send_json_error( 'Missing ID' );

		// Validate theme key
		$allowed = array_keys( CFPS_Sequence::theme_labels() );
		if ( $theme !== '' && ! in_array( $theme, $allowed, true ) ) {
			wp_send_json_error( 'Invalid theme' );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . CFPS_TABLE,
			[ 'theme_slug' => $theme ],
			[ 'id' => $id ]
		);

		wp_send_json_success();
	}

	/* ── admin_post : fallback sans JS ───────────────────────── */
	public static function save_theme() {
		check_admin_referer( 'cfps_save_theme_post' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

		$id    = absint( $_POST['cfps_id'] ?? 0 );
		$theme = sanitize_text_field( $_POST['theme'] ?? '' );
		$allowed = array_keys( CFPS_Sequence::theme_labels() );

		if ( $id && ( $theme === '' || in_array( $theme, $allowed, true ) ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . CFPS_TABLE,
				[ 'theme_slug' => $theme ],
				[ 'id' => $id ]
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cfps-sequences&updated=1' ) );
		exit;
	}
}
