<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Admin {

	public static function register_menu() {
		add_menu_page( 'Newsletter', 'Newsletter', 'manage_options', 'cfnl-campaigns', [ __CLASS__, 'page_campaigns' ], 'dashicons-email-alt', 59 );
		add_submenu_page( 'cfnl-campaigns', 'Campagnes', 'Campagnes', 'manage_options', 'cfnl-campaigns', [ __CLASS__, 'page_campaigns' ] );
		add_submenu_page( 'cfnl-campaigns', 'Abonnés', 'Abonnés', 'manage_options', 'cfnl-subscribers', [ __CLASS__, 'page_subscribers' ] );
		add_submenu_page( 'cfnl-campaigns', 'Bibliothèque', 'Bibliothèque', 'manage_options', 'cfnl-library', [ __CLASS__, 'page_library' ] );
		add_submenu_page( 'cfnl-campaigns', 'Automatisations', 'Automatisations', 'manage_options', 'cfnl-automations', [ __CLASS__, 'page_automations' ] );
		add_submenu_page( 'cfnl-campaigns', 'Réglages', 'Réglages', 'manage_options', 'cfnl-settings', [ __CLASS__, 'page_settings' ] );
	}

	/* ── Actions POST (save / send / delete) ──────────────────────── */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['cfnl_admin_action'] ) ) {
			return;
		}
		check_admin_referer( 'cfnl_admin' );
		$action = sanitize_key( $_POST['cfnl_admin_action'] );

		if ( 'save_campaign' === $action ) {
			$id = CFNL_Campaigns::save( [
				'id'         => absint( $_POST['campaign_id'] ?? 0 ),
				'titre'      => wp_unslash( $_POST['titre'] ?? '' ),
				'objet'      => wp_unslash( $_POST['objet'] ?? '' ),
				'corps'      => wp_unslash( $_POST['corps'] ?? '' ),
				'cible'      => sanitize_key( $_POST['cible'] ?? 'both' ),
				'segment'    => sanitize_key( $_POST['segment'] ?? 'all' ),
				'objet_b'    => wp_unslash( $_POST['objet_b'] ?? '' ),
				'ab_enabled' => ! empty( $_POST['ab_enabled'] ) ? 1 : 0,
				'ab_sample'  => absint( $_POST['ab_sample'] ?? 30 ),
			] );
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $id, 'msg' => 'saved' ] );
		}

		if ( 'send_campaign' === $action ) {
			$id  = absint( $_POST['campaign_id'] ?? 0 );
			$res = CFNL_Campaigns::enqueue( $id );
			$msg = is_wp_error( $res ) ? 'send_err' : 'sending';
			self::redirect( [ 'page' => 'cfnl-campaigns', 'msg' => $msg ] );
		}

		if ( 'delete_campaign' === $action ) {
			CFNL_Campaigns::delete( absint( $_POST['campaign_id'] ?? 0 ) );
			self::redirect( [ 'page' => 'cfnl-campaigns', 'msg' => 'deleted' ] );
		}

		if ( 'test_campaign' === $action ) {
			$id  = absint( $_POST['campaign_id'] ?? 0 );
			$res = CFNL_Sender::send_test_campaign( $id, wp_unslash( $_POST['test_to'] ?? get_option( 'admin_email' ) ) );
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $id, 'msg' => is_wp_error( $res ) ? 'test_ko' : 'test_ok' ] );
		}

		if ( 'resend_non_openers' === $action ) {
			$id  = absint( $_POST['campaign_id'] ?? 0 );
			$res = CFNL_Campaigns::resend_non_openers( $id );
			if ( is_wp_error( $res ) ) {
				self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $id, 'msg' => 'resend_ko' ] );
			}
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $res['new_id'], 'msg' => 'resend_ok', 'n' => $res['count'] ] );
		}

		if ( 'schedule_campaign' === $action ) {
			$id  = absint( $_POST['campaign_id'] ?? 0 );
			$res = CFNL_Campaigns::schedule( $id, sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) ) );
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $id, 'msg' => is_wp_error( $res ) ? 'send_err' : 'scheduled' ] );
		}

		if ( 'unschedule_campaign' === $action ) {
			$id = absint( $_POST['campaign_id'] ?? 0 );
			CFNL_Campaigns::unschedule( $id );
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $id, 'msg' => 'saved' ] );
		}

		if ( 'save_automations' === $action ) {
			$prev = get_option( 'cfnl_settings', [] );
			$prev['welcome_enabled']   = ! empty( $_POST['welcome_enabled'] ) ? 1 : 0;
			$prev['welcome_objet']     = sanitize_text_field( wp_unslash( $_POST['welcome_objet'] ?? '' ) );
			$prev['welcome_corps']     = wp_kses_post( wp_unslash( $_POST['welcome_corps'] ?? '' ) );
			$prev['postnotif_enabled'] = ! empty( $_POST['postnotif_enabled'] ) ? 1 : 0;
			$prev['postnotif_mode']    = in_array( $_POST['postnotif_mode'] ?? 'draft', [ 'draft', 'send' ], true ) ? sanitize_key( $_POST['postnotif_mode'] ) : 'draft';
			$prev['postnotif_objet']   = sanitize_text_field( wp_unslash( $_POST['postnotif_objet'] ?? '' ) );
			$prev['postnotif_corps']   = wp_kses_post( wp_unslash( $_POST['postnotif_corps'] ?? '' ) );
			$prev['libnotif_enabled'] = ! empty( $_POST['libnotif_enabled'] ) ? 1 : 0;
			$prev['libnotif_mode']    = in_array( $_POST['libnotif_mode'] ?? 'draft', [ 'draft', 'send' ], true ) ? sanitize_key( $_POST['libnotif_mode'] ) : 'draft';
			foreach ( [ 2, 3 ] as $step ) {
				$prev[ "welcome{$step}_enabled" ] = ! empty( $_POST[ "welcome{$step}_enabled" ] ) ? 1 : 0;
				$prev[ "welcome{$step}_days" ]    = max( 1, absint( $_POST[ "welcome{$step}_days" ] ?? 3 ) );
				$prev[ "welcome{$step}_objet" ]   = sanitize_text_field( wp_unslash( $_POST[ "welcome{$step}_objet" ] ?? '' ) );
				$prev[ "welcome{$step}_corps" ]   = wp_kses_post( wp_unslash( $_POST[ "welcome{$step}_corps" ] ?? '' ) );
			}
			update_option( 'cfnl_settings', $prev );
			self::redirect( [ 'page' => 'cfnl-automations', 'msg' => 'saved' ] );
		}

		if ( 'lib_mailpoet' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'annuel' ) );
			$key = sanitize_file_name( wp_unslash( $_POST['key'] ?? '' ) );
			$res = CFNL_Library::create_mailpoet_draft( $key, $col );
			if ( is_wp_error( $res ) ) {
				self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'msg' => 'lib_mp_ko' ] );
			}
			// Redirige directement vers l'édition du brouillon dans MailPoet
			wp_safe_redirect( $res['edit_url'] );
			exit;
		}

		if ( 'lib_schedule_year' === $action ) {
			$n = CFNL_Library::schedule_full_year();
			self::redirect( [ 'page' => 'cfnl-campaigns', 'msg' => 'year_ok', 'n' => (int) $n ] );
		}

		if ( 'lib_native' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'annuel' ) );
			$key = sanitize_file_name( wp_unslash( $_POST['key'] ?? '' ) );
			$res = CFNL_Library::create_native_draft( $key, $col );
			if ( is_wp_error( $res ) ) {
				self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'msg' => 'lib_ko' ] );
			}
			self::redirect( [ 'page' => 'cfnl-campaigns', 'edit' => $res['id'], 'msg' => 'lib_native_ok' ] );
		}

		if ( 'lib_save' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'annuel' ) );
			$key = sanitize_file_name( wp_unslash( $_POST['key'] ?? '' ) );
			$res = CFNL_Library::save_custom(
				$col,
				$key,
				wp_unslash( $_POST['ed_titre'] ?? '' ),
				wp_unslash( $_POST['ed_objet'] ?? '' ),
				wp_unslash( $_POST['ed_corps'] ?? '' )
			);
			self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'edit' => $key, 'msg' => is_wp_error( $res ) ? 'lib_ko' : 'lib_saved' ] );
		}

		if ( 'lib_new' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'articles' ) );
			$res = CFNL_Library::create_entry(
				$col,
				wp_unslash( $_POST['ed_titre'] ?? '' ),
				wp_unslash( $_POST['ed_objet'] ?? '' ),
				wp_unslash( $_POST['ed_corps'] ?? '' ),
				wp_unslash( $_POST['ed_cadeau'] ?? '' )
			);
			if ( is_wp_error( $res ) ) {
				self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'msg' => 'lib_new_ko' ] );
			}
			// Le texte vient d'apparaître : l'automatisation le traite tout de
			// suite plutôt que d'attendre le prochain passage du cron.
			$n = CFNL_Automations::check_new_library();
			self::redirect( [
				'page' => 'cfnl-library',
				'col'  => $col,
				'edit' => $res,
				'msg'  => $n ? 'lib_new_auto' : 'lib_new_ok',
			] );
		}

		if ( 'lib_delete' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'articles' ) );
			$key = sanitize_file_name( wp_unslash( $_POST['key'] ?? '' ) );
			$res = CFNL_Library::delete_entry( $col, $key );
			self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'msg' => is_wp_error( $res ) ? 'lib_ko' : 'lib_deleted' ] );
		}

		if ( 'lib_reset' === $action ) {
			$col = sanitize_key( wp_unslash( $_POST['col'] ?? 'annuel' ) );
			$key = sanitize_file_name( wp_unslash( $_POST['key'] ?? '' ) );
			CFNL_Library::reset_custom( $col, $key );
			self::redirect( [ 'page' => 'cfnl-library', 'col' => $col, 'edit' => $key, 'msg' => 'lib_reset' ] );
		}

		if ( 'export_csv' === $action ) {
			self::export_csv();
		}

		if ( 'import_csv' === $action && ! empty( $_FILES['csv']['tmp_name'] ) ) {
			$added = self::import_csv( $_FILES['csv']['tmp_name'] );
			self::redirect( [ 'page' => 'cfnl-subscribers', 'msg' => 'imported', 'n' => (int) $added ] );
		}

		if ( 'save_settings' === $action ) {
			$prev = get_option( 'cfnl_settings', [] );
			// Ne pas écraser le mot de passe SMTP si le champ est laissé vide
			$smtp_pass = wp_unslash( $_POST['smtp_pass'] ?? '' );
			if ( '' === $smtp_pass ) {
				$smtp_pass = $prev['smtp_pass'] ?? '';
			}
			// Fusion dans $prev pour préserver les réglages d'automatisation
			$prev['from_name']    = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
			$prev['from_email']   = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
			$prev['double_optin'] = ! empty( $_POST['double_optin'] ) ? 1 : 0;
			$prev['batch_size']   = max( 1, absint( $_POST['batch_size'] ?? 30 ) );
			$prev['daily_cap']    = max( 1, absint( $_POST['daily_cap'] ?? 250 ) );
			$prev['smtp_enabled'] = ! empty( $_POST['smtp_enabled'] ) ? 1 : 0;
			$prev['smtp_host']    = sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) );
			$prev['smtp_port']    = max( 1, absint( $_POST['smtp_port'] ?? 587 ) );
			$prev['smtp_secure']  = in_array( $_POST['smtp_secure'] ?? 'tls', [ 'tls', 'ssl', 'none' ], true ) ? sanitize_key( $_POST['smtp_secure'] ) : 'tls';
			$prev['smtp_user']    = sanitize_text_field( wp_unslash( $_POST['smtp_user'] ?? '' ) );
			$prev['smtp_pass']    = $smtp_pass;
			$prev['click_tracking'] = ! empty( $_POST['click_tracking'] ) ? 1 : 0;
			$prev['track_exclude']  = sanitize_textarea_field( wp_unslash( $_POST['track_exclude'] ?? '' ) );
			$prev['utm_enabled']  = ! empty( $_POST['utm_enabled'] ) ? 1 : 0;
			$prev['utm_source']   = sanitize_title( wp_unslash( $_POST['utm_source'] ?? 'newsletter' ) );
			$prev['utm_medium']   = sanitize_title( wp_unslash( $_POST['utm_medium'] ?? 'email' ) );
			update_option( 'cfnl_settings', $prev );
			self::redirect( [ 'page' => 'cfnl-settings', 'msg' => 'saved' ] );
		}

		if ( 'test_smtp' === $action ) {
			$res = CFNL_SMTP::send_test( wp_unslash( $_POST['test_email'] ?? get_option( 'admin_email' ) ) );
			self::redirect( [ 'page' => 'cfnl-settings', 'msg' => is_wp_error( $res ) ? 'smtp_ko' : 'smtp_ok' ] );
		}

		if ( 'import_subscriber' === $action ) {
			CFNL_Subscribers::add(
				sanitize_email( wp_unslash( $_POST['imp_email'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['imp_prenom'] ?? '' ) ),
				'',
				'quinzaine',
				'manuel'
			);
			self::redirect( [ 'page' => 'cfnl-subscribers', 'msg' => 'added' ] );
		}
	}

	private static function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ── Page Campagnes (liste + éditeur) ─────────────────────────── */
	public static function page_campaigns() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$new     = isset( $_GET['new'] );

		echo '<div class="wrap"><h1>Newsletter — Campagnes ';
		echo '<a href="' . esc_url( add_query_arg( [ 'page' => 'cfnl-campaigns', 'new' => 1 ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">Nouvelle campagne</a></h1>';
		self::notice();

		if ( $new || $edit_id ) {
			self::render_editor( $edit_id );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_list() {
		$camps = CFNL_Campaigns::all();
		$statuts = [ 'draft' => '📝 Brouillon', 'scheduled' => '📅 Programmée', 'sending' => '📤 Envoi en cours', 'sent' => '✅ Envoyée' ];
		echo '<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;"><thead><tr>'
			. '<th>Titre</th><th>Objet</th><th>Statut</th><th>Envoyés</th><th>Ouverts</th><th>Clics</th><th>Actions</th></tr></thead><tbody>';
		if ( ! $camps ) {
			echo '<tr><td colspan="7" style="text-align:center;padding:20px;color:#888;">Aucune campagne. Créez-en une pour commencer.</td></tr>';
		}
		foreach ( $camps as $c ) {
			$edit_url = add_query_arg( [ 'page' => 'cfnl-campaigns', 'edit' => $c->id ], admin_url( 'admin.php' ) );
			$taux = $c->envoyes > 0 ? round( $c->ouverts / $c->envoyes * 100 ) : 0;
			echo '<tr class="cfeb-booking-row">';
			echo '<td><strong class="cfeb-row-name"><a href="' . esc_url( $edit_url ) . '">' . esc_html( $c->titre ?: '(sans titre)' ) . '</a></strong></td>';
			echo '<td data-label="Objet">' . esc_html( $c->objet ) . '</td>';
			echo '<td data-label="Statut">' . esc_html( $statuts[ $c->statut ] ?? $c->statut ) . '</td>';
			echo '<td data-label="Envoyés">' . (int) $c->envoyes . ' / ' . (int) $c->total . '</td>';
			echo '<td data-label="Ouverts">' . (int) $c->ouverts . ' (' . (int) $taux . '%)</td>';
			echo '<td data-label="Clics">' . (int) $c->clics . '</td>';
			echo '<td data-label="Actions"><a href="' . esc_url( $edit_url ) . '" class="button button-small">' . ( 'draft' === $c->statut ? 'Éditer' : 'Voir' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_editor( $id ) {
		$c = $id ? CFNL_Campaigns::get( $id ) : null;
		$titre = $c->titre ?? '';
		$objet = $c->objet ?? '';
		$corps = $c->corps ?? "Bonjour {{prenom}},\n\n\n\n— Ewen";
		$segment = $c->segment ?? 'all';
		$objet_b = $c->objet_b ?? '';
		$ab_on   = ! empty( $c->ab_enabled );
		$ab_samp = (int) ( $c->ab_sample ?? 30 );
		$is_draft = ! $c || 'draft' === $c->statut;

		if ( $c && ! $is_draft ) {
			$counts = CFNL_Subscribers::counts();
			$non_ouvreurs = max( 0, (int) $c->envoyes - (int) $c->ouverts );
			echo '<div class="notice notice-info inline"><p>Cette campagne est <strong>' . esc_html( $c->statut ) . '</strong> — elle n\'est plus modifiable. Envoyés : ' . (int) $c->envoyes . ' · Ouverts : ' . (int) $c->ouverts . ' · Clics : ' . (int) $c->clics;
			if ( ! empty( $c->ab_enabled ) && '' !== (string) $c->ab_winner ) {
				echo ' · 🔬 A/B gagnant : objet <strong>' . esc_html( strtoupper( $c->ab_winner ) ) . '</strong>';
			} elseif ( ! empty( $c->ab_enabled ) ) {
				echo ' · 🔬 A/B en cours (décision du gagnant en attente)';
			}
			echo '</p></div>';

			// Renvoi aux non-ouvreurs (campagne envoyée uniquement)
			if ( 'sent' === $c->statut && $non_ouvreurs > 0 ) {
				echo '<div style="background:#eef6ff;border:1px solid #90b8e0;border-radius:8px;padding:16px 20px;max-width:760px;margin:12px 0;">';
				echo '<h3 style="margin-top:0;">🔁 Renvoyer aux non-ouvreurs</h3>';
				echo '<p>Environ <strong>' . (int) $non_ouvreurs . '</strong> personne(s) n\'ont pas ouvert cette campagne. Tu peux la leur renvoyer (une nouvelle campagne « Relance » est créée et lancée), souvent avec un bon gain d\'ouvertures.</p>';
				echo '<form method="post" onsubmit="return confirm(\'Créer et envoyer une relance aux non-ouvreurs ?\');">';
				wp_nonce_field( 'cfnl_admin' );
				echo '<input type="hidden" name="cfnl_admin_action" value="resend_non_openers">';
				echo '<input type="hidden" name="campaign_id" value="' . (int) $c->id . '">';
				echo '<button class="button button-primary">🔁 Renvoyer aux non-ouvreurs</button>';
				echo '</form></div>';
			}
		}

		echo '<form method="post" style="max-width:760px;margin-top:16px;">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="save_campaign">';
		echo '<input type="hidden" name="campaign_id" value="' . (int) $id . '">';
		$ro = $is_draft ? '' : 'disabled';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label>Titre interne</label></th><td><input type="text" name="titre" value="' . esc_attr( $titre ) . '" class="regular-text" ' . $ro . '></td></tr>';
		echo '<tr><th><label>Objet de l\'email</label></th><td><input type="text" name="objet" value="' . esc_attr( $objet ) . '" class="large-text" ' . $ro . '></td></tr>';
		// Segment comportemental
		echo '<tr><th><label>Segment</label></th><td><select name="segment" ' . $ro . '>';
		foreach ( [ 'all' => 'Tous les abonnés de la cible', 'engaged' => 'Engagés (actifs sur les 90 derniers jours)', 'inactive' => 'Inactifs (réengagement)' ] as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $segment, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select><p class="description">Affine la cible selon l\'engagement récent (ouvertures / clics).</p></td></tr>';
		echo '<tr><th><label>Corps</label></th><td><textarea name="corps" rows="16" class="large-text" ' . $ro . ' style="font-family:Georgia,serif;">' . esc_textarea( $corps ) . '</textarea>';
		echo '<p class="description">Variables : <code>{{prenom}}</code> <code>{{nom}}</code> <code>{{email}}</code>. Les liens de suivi et la désinscription sont ajoutés automatiquement.<br>💡 Tu peux <strong>coller du HTML copié depuis MailPoet</strong> (aperçu navigateur → code source) : le contenu est extrait, les variables MailPoet converties et son pied de désinscription retiré automatiquement.</p></td></tr>';
		echo '</tbody></table>';

		// ── A/B testing d'objet ──────────────────────────────────────
		echo '<div style="background:#f6f3f9;border:1px solid #d9c9e8;border-radius:8px;padding:14px 18px;margin-bottom:16px;">';
		echo '<h3 style="margin-top:0;">🔬 A/B testing de l\'objet</h3>';
		echo '<p class="description" style="margin-top:0;">Teste deux objets sur un échantillon ; après 4 h, l\'objet le plus ouvert est envoyé automatiquement au reste des abonnés.</p>';
		echo '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="ab_enabled" value="1" ' . checked( $ab_on, true, false ) . ' ' . $ro . '> Activer l\'A/B testing pour cette campagne</label>';
		echo '<p><label>Objet B (variante) : </label><br><input type="text" name="objet_b" value="' . esc_attr( $objet_b ) . '" class="large-text" ' . $ro . ' placeholder="Un second objet à tester"></p>';
		echo '<p><label>Taille de l\'échantillon test : </label><input type="number" name="ab_sample" value="' . (int) $ab_samp . '" class="small-text" min="10" max="90" ' . $ro . '> % des abonnés (réparti 50/50 entre A et B ; le reste reçoit le gagnant)</p>';
		echo '</div>';

		if ( $is_draft ) {
			submit_button( 'Enregistrer le brouillon', 'primary', 'submit', false );
			echo ' ';
		}
		echo '</form>';

		// Bloc envoi (séparé pour ne pas envoyer par erreur)
		if ( $c && $is_draft ) {
			$counts      = CFNL_Subscribers::counts();
			$cible_count = $counts['subscribed'];
			echo '<div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:8px;padding:18px 22px;max-width:760px;margin-top:8px;">';
			echo '<h3 style="margin-top:0;">Envoyer cette campagne</h3>';
			echo '<p>Destinataires estimés : <strong>' . (int) $cible_count . '</strong> abonné(s) confirmé(s). L\'envoi se fait par lots automatiques (toutes les 5 min) pour préserver la délivrabilité — rien ne part tant que tu ne cliques pas.</p>';
			echo '<form method="post" onsubmit="return confirm(\'Lancer l\\\'envoi de cette campagne à ' . (int) $cible_count . ' abonnés ?\');">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="send_campaign">';
			echo '<input type="hidden" name="campaign_id" value="' . (int) $id . '">';
			echo '<button type="submit" class="button button-primary" ' . ( $id ? '' : 'disabled title="Enregistre d\'abord le brouillon"' ) . '>📤 Lancer l\'envoi maintenant</button>';
			echo '</form>';

			// Envoi de test
			echo '<hr style="margin:16px 0;border-color:#f0d090;">';
			echo '<form method="post" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="test_campaign">';
			echo '<input type="hidden" name="campaign_id" value="' . (int) $id . '">';
			echo '<label>Envoyer un test à : </label>';
			echo '<input type="email" name="test_to" value="' . esc_attr( get_option( 'admin_email' ) ) . '" ' . ( $id ? '' : 'disabled' ) . '>';
			echo '<button type="submit" class="button" ' . ( $id ? '' : 'disabled' ) . '>✉️ M\'envoyer un test</button>';
			echo '</form>';

			// Programmation
			echo '<hr style="margin:16px 0;border-color:#f0d090;">';
			echo '<form method="post" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="schedule_campaign">';
			echo '<input type="hidden" name="campaign_id" value="' . (int) $id . '">';
			echo '<label>Ou programmer pour le : </label>';
			$default = date_i18n( 'Y-m-d\TH:i', current_time( 'timestamp' ) + DAY_IN_SECONDS );
			echo '<input type="datetime-local" name="scheduled_at" value="' . esc_attr( $default ) . '" ' . ( $id ? '' : 'disabled' ) . '>';
			echo '<button type="submit" class="button" ' . ( $id ? '' : 'disabled' ) . '>🕑 Programmer</button>';
			echo '</form></div>';
		}

		// Campagne programmée : afficher et permettre l'annulation
		if ( $c && 'scheduled' === $c->statut ) {
			echo '<div class="notice notice-warning inline"><p>📅 Programmée pour le <strong>' . esc_html( date_i18n( 'd/m/Y à H:i', strtotime( $c->scheduled_at ) ) ) . '</strong>. '
				. 'L\'envoi partira automatiquement à cette heure.</p>'
				. '<form method="post" style="margin-bottom:10px;">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="unschedule_campaign">';
			echo '<input type="hidden" name="campaign_id" value="' . (int) $c->id . '">';
			echo '<button class="button">Annuler la programmation (repasser en brouillon)</button></form></div>';
		}
	}

	/* ── Page Abonnés ─────────────────────────────────────────────── */
	public static function page_subscribers() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$counts = CFNL_Subscribers::counts();
		echo '<div class="wrap"><h1>Newsletter — Abonnés</h1>';
		self::notice();

		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;">';
		foreach ( [
			'Confirmés' => $counts['subscribed'], 'Engagés (90j)' => $counts['engaged'] ?? 0, 'En attente' => $counts['pending'],
			'Quinzaine' => $counts['quinzaine'], 'Mensuel' => $counts['mensuel'], 'Désinscrits' => $counts['unsub'],
		] as $lbl => $val ) {
			echo '<div style="flex:1;min-width:120px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:14px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05);">'
				. '<div style="font-size:24px;font-weight:700;color:#5a3e6b;">' . (int) $val . '</div>'
				. '<div style="font-size:12px;color:#646970;">' . esc_html( $lbl ) . '</div></div>';
		}
		echo '</div>';

		// Ajout manuel
		echo '<h2>Ajouter un abonné</h2><form method="post" style="margin-bottom:20px;">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="import_subscriber">';
		echo '<input type="text" name="imp_prenom" placeholder="Prénom" style="margin-right:6px;"> ';
		echo '<input type="email" name="imp_email" placeholder="email@exemple.fr" required style="margin-right:6px;"> ';
		echo '<button class="button">Ajouter</button>';
		echo '<p class="description">Ajout direct sans double opt-in (import manuel d\'un contact que tu connais).</p></form>';

		// Import / export CSV
		echo '<div style="display:flex;flex-wrap:wrap;gap:24px;margin-bottom:20px;">';
		echo '<form method="post" enctype="multipart/form-data" style="background:#f6f7f7;padding:14px 18px;border-radius:8px;">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="import_csv">';
		echo '<strong>Importer un CSV</strong><br><input type="file" name="csv" accept=".csv" required style="margin:8px 0;"><br>';
		echo '<button class="button">Importer</button>';
		echo '<p class="description" style="margin:6px 0 0;">Colonnes : email, prénom, fréquence (mensuel/quinzaine).</p></form>';
		echo '<form method="post" style="background:#f6f7f7;padding:14px 18px;border-radius:8px;">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="export_csv">';
		echo '<strong>Exporter</strong><br><p class="description" style="margin:8px 0;">Télécharge tous les abonnés au format CSV.</p>';
		echo '<button class="button">Exporter le CSV</button></form>';
		echo '</div>';

		$t = CFNL_Subscribers::table();
		$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 200" );
		echo '<table class="wp-list-table widefat striped cfeb-table"><thead><tr><th>Email</th><th>Prénom</th><th>Fréquence</th><th>Statut</th><th>Inscrit le</th></tr></thead><tbody>';
		$smap = [ 'subscribed' => '✅ Confirmé', 'pending' => '⏳ En attente', 'unsubscribed' => '🚫 Désinscrit' ];
		foreach ( $rows as $r ) {
			echo '<tr class="cfeb-booking-row">';
			echo '<td><strong class="cfeb-row-name">' . esc_html( $r->email ) . '</strong></td>';
			echo '<td data-label="Prénom">' . esc_html( $r->prenom ) . '</td>';
			echo '<td data-label="Fréquence">' . esc_html( ucfirst( $r->frequence ) ) . '</td>';
			echo '<td data-label="Statut">' . esc_html( $smap[ $r->statut ] ?? $r->statut ) . '</td>';
			echo '<td data-label="Inscrit le">' . esc_html( date_i18n( 'd/m/Y', strtotime( $r->cree_le ) ) ) . '</td>';
			echo '</tr>';
		}
		if ( ! $rows ) echo '<tr><td colspan="5" style="text-align:center;padding:20px;color:#888;">Aucun abonné pour l\'instant.</td></tr>';
		echo '</tbody></table></div>';
	}

	/* ── Page Réglages ────────────────────────────────────────────── */
	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = get_option( 'cfnl_settings', CFNL_Install::default_settings() );
		echo '<div class="wrap"><h1>Newsletter — Réglages</h1>';
		self::notice();

		echo '<form method="post"><table class="form-table"><tbody>';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="save_settings">';
		echo '<tr><th>Nom expéditeur</th><td><input type="text" name="from_name" value="' . esc_attr( $s['from_name'] ?? '' ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Email expéditeur</th><td><input type="email" name="from_email" value="' . esc_attr( $s['from_email'] ?? '' ) . '" class="regular-text"><p class="description">Doit correspondre au compte SMTP ci-dessous (sinon rejet SPF/DMARC).</p></td></tr>';
		echo '<tr><th>Double opt-in</th><td><label><input type="checkbox" name="double_optin" value="1" ' . checked( ! empty( $s['double_optin'] ), true, false ) . '> Exiger une confirmation par email avant d\'inscrire (recommandé, RGPD)</label></td></tr>';
		echo '<tr><th>Emails par lot</th><td><input type="number" name="batch_size" value="' . (int) ( $s['batch_size'] ?? 30 ) . '" class="small-text" min="1" max="200"> <span class="description">envoyés toutes les 5 minutes</span></td></tr>';
		echo '<tr><th>Plafond quotidien</th><td><input type="number" name="daily_cap" value="' . (int) ( $s['daily_cap'] ?? 250 ) . '" class="small-text" min="1"> <span class="description">limite/jour (OVH mutualisé plafonne l\'envoi ~250-500/jour selon l\'offre)</span></td></tr>';

		echo '</tbody></table>';

		echo '<hr><h2>📤 SMTP intégré — envoi via ton compte email (aucun plugin tiers)</h2>';
		echo '<div class="notice notice-info inline"><p>Renseigne ici les identifiants d\'un compte email de ton hébergement OVH (ex. <code>contact@soins.ewendaviau.com</code>). Tous les emails du site (réservations, post-séance, newsletters) partiront via ce serveur authentifié — bien plus fiable que l\'envoi PHP brut, et <strong>sans installer d\'extension supplémentaire</strong>.</p></div>';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Activer le SMTP intégré</th><td><label><input type="checkbox" name="smtp_enabled" value="1" ' . checked( ! empty( $s['smtp_enabled'] ), true, false ) . '> Router tous les emails du site via ce SMTP</label></td></tr>';
		echo '<tr><th>Serveur SMTP</th><td><input type="text" name="smtp_host" value="' . esc_attr( $s['smtp_host'] ?? 'ssl0.ovh.net' ) . '" class="regular-text"><p class="description">OVH mutualisé : <code>ssl0.ovh.net</code></p></td></tr>';
		echo '<tr><th>Port &amp; sécurité</th><td><input type="number" name="smtp_port" value="' . (int) ( $s['smtp_port'] ?? 587 ) . '" class="small-text"> ';
		echo '<select name="smtp_secure">';
		foreach ( [ 'tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Aucune' ] as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['smtp_secure'] ?? 'tls', $k, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>Identifiant (email complet)</th><td><input type="text" name="smtp_user" value="' . esc_attr( $s['smtp_user'] ?? '' ) . '" class="regular-text" autocomplete="off" placeholder="contact@soins.ewendaviau.com"></td></tr>';
		echo '<tr><th>Mot de passe</th><td><input type="password" name="smtp_pass" value="" class="regular-text" autocomplete="new-password" placeholder="' . ( ! empty( $s['smtp_pass'] ) ? '•••••••• (inchangé si vide)' : '' ) . '"><p class="description">Le mot de passe du compte email OVH. Laisse vide pour conserver l\'actuel.</p></td></tr>';
		echo '</tbody></table>';

		echo '<hr><h2>📈 Suivi analytics (UTM)</h2>';
		echo '<p class="description">Ajoute automatiquement des balises UTM aux liens de tes newsletters pour repérer le trafic « newsletter » dans Google Analytics / Matomo.</p>';
		echo '<table class="form-table"><tbody>';
		$click_on = ! array_key_exists( 'click_tracking', $s ) || ! empty( $s['click_tracking'] );
		echo '<tr><th>Suivi des clics</th><td><label><input type="checkbox" name="click_tracking" value="1" ' . checked( $click_on, true, false ) . '> '
			. 'Compter les clics sur les liens</label>';
		echo '<p class="description">Pour compter les clics, chaque lien est réécrit avec l\'identifiant du destinataire : '
			. '<strong>chacun reçoit donc une adresse différente</strong>. Sans effet pour un lien qu\'on ouvre seul, mais cela casse '
			. 'tout lien destiné à être partagé — un lien de visio recollé dans le chat de la réunion, par exemple.</p></td></tr>';
		echo '<tr><th>Liens jamais réécrits</th><td>';
		echo '<textarea name="track_exclude" rows="4" class="large-text code" placeholder="mondomaine-visio.fr">' . esc_textarea( $s['track_exclude'] ?? '' ) . '</textarea>';
		echo '<p class="description">Un domaine par ligne. Les services de visioconférence courants (Google Meet, Zoom, Jitsi, Teams, Whereby, Framatalk…) '
			. 'sont <strong>déjà exclus d\'office</strong> : ce champ sert à en ajouter d\'autres. Ce que tu écris ici s\'ajoute à la liste de base, '
			. 'il ne la remplace pas — vider le champ ne peut donc pas réintroduire le problème.</p></td></tr>';
		echo '<tr><th>Activer les UTM</th><td><label><input type="checkbox" name="utm_enabled" value="1" ' . checked( ! empty( $s['utm_enabled'] ), true, false ) . '> Baliser les liens vers le site</label></td></tr>';
		echo '<tr><th>utm_source</th><td><input type="text" name="utm_source" value="' . esc_attr( $s['utm_source'] ?? 'newsletter' ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>utm_medium</th><td><input type="text" name="utm_medium" value="' . esc_attr( $s['utm_medium'] ?? 'email' ) . '" class="regular-text"><p class="description">utm_campaign est renseigné automatiquement avec le titre de chaque campagne.</p></td></tr>';
		echo '</tbody></table>';

		submit_button( 'Enregistrer' );
		echo '</form>';

		// Test SMTP
		echo '<hr><h3>Tester l\'envoi</h3><form method="post" style="margin-bottom:20px;">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="test_smtp">';
		echo '<input type="email" name="test_email" value="' . esc_attr( get_option( 'admin_email' ) ) . '" class="regular-text" style="margin-right:6px;"> ';
		echo '<button class="button">Envoyer un email de test</button>';
		echo '<p class="description">Enregistre d\'abord tes réglages SMTP, puis envoie-toi un test pour vérifier.</p></form>';

		echo '<hr><h2>Formulaire d\'inscription</h2><p>Ajoute le formulaire sur n\'importe quelle page avec le code court :</p><p><code>[cf_newsletter]</code></p>';
		echo '</div>';
	}

	/* ── Page Bibliothèque (modèles annuels + un mail par article) ──
	   Tous les textes sont modifiables ici : la version enregistrée dans
	   l'admin remplace celle du fichier du dépôt, et « Rétablir » revient
	   au texte d'origine. */
	public static function page_library() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$cols = CFNL_Library::collections();
		$col  = isset( $_GET['col'] ) ? sanitize_key( wp_unslash( $_GET['col'] ) ) : 'annuel';
		if ( ! CFNL_Library::is_collection( $col ) ) {
			$col = 'annuel';
		}
		$edit_key = isset( $_GET['edit'] ) ? sanitize_file_name( wp_unslash( $_GET['edit'] ) ) : '';
		$creating = isset( $_GET['nouveau'] );

		echo '<div class="wrap"><h1>Newsletter — Bibliothèque ';
		echo '<a href="' . esc_url( add_query_arg( [ 'page' => 'cfnl-library', 'col' => $col, 'nouveau' => 1 ], admin_url( 'admin.php' ) ) )
			. '" class="page-title-action">Nouveau cadeau</a></h1>';
		self::notice();

		// Onglets des deux collections
		echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
		foreach ( $cols as $slug => $meta ) {
			$url = add_query_arg( [ 'page' => 'cfnl-library', 'col' => $slug ], admin_url( 'admin.php' ) );
			$n   = count( CFNL_Library::editions( $slug ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $slug === $col ? ' nav-tab-active' : '' ) . '">'
				. esc_html( $meta['label'] ) . ' <span style="opacity:.6;">(' . (int) $n . ')</span></a>';
		}
		echo '</h2>';

		if ( $creating ) {
			self::render_library_new( $col );
			echo '</div>';
			return;
		}

		if ( $edit_key ) {
			self::render_library_editor( $col, $edit_key );
			echo '</div>';
			return;
		}

		self::render_library_list( $col );
		echo '</div>';
	}

	/* ── Écrire un nouveau cadeau ──────────────────────────────────────
	   Ces textes n'ont pas de fichier derrière eux : ils vivent en base.
	   Leur apparition déclenche l'automatisation si elle est activée. */
	private static function render_library_new( $col ) {
		$s    = get_option( 'cfnl_settings', [] );
		$auto = ! empty( $s['libnotif_enabled'] );
		$mode = $s['libnotif_mode'] ?? 'draft';
		$back = add_query_arg( [ 'page' => 'cfnl-library', 'col' => $col ], admin_url( 'admin.php' ) );

		echo '<p><a href="' . esc_url( $back ) . '">← Retour à la liste</a></p>';

		if ( $auto && 'send' === $mode ) {
			echo '<div class="notice notice-warning inline"><p><strong>Attention :</strong> l\'automatisation est réglée sur '
				. '<em>envoi immédiat</em>. Ce texte partira aux abonnés dès l\'enregistrement, sans relecture. '
				. '<a href="' . esc_url( add_query_arg( [ 'page' => 'cfnl-automations' ], admin_url( 'admin.php' ) ) ) . '">Changer ce réglage</a></p></div>';
		} elseif ( $auto ) {
			echo '<div class="notice notice-info inline"><p>À l\'enregistrement, une campagne sera créée automatiquement '
				. 'en brouillon — tu la reliras avant de l\'envoyer.</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="lib_new">';
		echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="ed_titre">Titre interne</label></th><td>'
			. '<input type="text" id="ed_titre" name="ed_titre" class="large-text" required>'
			. '<p class="description">Sert à retrouver la campagne dans la liste. N\'apparaît pas dans l\'email.</p></td></tr>';
		echo '<tr><th><label for="ed_objet">Objet de l\'email</label></th><td>'
			. '<input type="text" id="ed_objet" name="ed_objet" class="large-text"></td></tr>';
		echo '<tr><th><label for="ed_cadeau">Ce que le lecteur en retire</label></th><td>'
			. '<input type="text" id="ed_cadeau" name="ed_cadeau" class="large-text">'
			. '<p class="description">Une phrase, pour toi : le geste, la question ou l\'idée que ce mail donne concrètement. '
			. 'N\'apparaît pas dans l\'email.</p></td></tr>';
		echo '<tr><th><label for="ed_corps">Corps</label></th><td>';
		wp_editor( '', 'ed_corps', [
			'textarea_name' => 'ed_corps',
			'textarea_rows' => 18,
			'media_buttons' => true,
		] );
		echo '<p class="description">Variable disponible : <code>{{prenom}}</code>.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Créer le cadeau' );
		echo '</form>';
	}

	/* ── Éditeur d'une édition ────────────────────────────────────── */
	private static function render_library_editor( $col, $key ) {
		$ed = CFNL_Library::get( $col, $key );
		if ( ! $ed ) {
			echo '<div class="notice notice-error"><p>Édition introuvable.</p></div>';
			return;
		}
		$back = add_query_arg( [ 'page' => 'cfnl-library', 'col' => $col ], admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $back ) . '">← Retour à la liste</a></p>';

		if ( $ed['modifie'] ) {
			echo '<div class="notice notice-info inline"><p>Ce texte a été modifié ici : c\'est cette version qui est utilisée, pas celle du fichier d\'origine.</p></div>';
		}
		if ( ! empty( $ed['cadeau'] ) ) {
			echo '<p class="description" style="margin:12px 0;"><strong>Ce que le lecteur en retire :</strong> ' . esc_html( $ed['cadeau'] ) . '</p>';
		}

		echo '<form method="post">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="lib_save">';
		echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
		echo '<input type="hidden" name="key" value="' . esc_attr( $ed['key'] ) . '">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="ed_titre">Titre interne</label></th><td>'
			. '<input type="text" id="ed_titre" name="ed_titre" value="' . esc_attr( $ed['titre'] ) . '" class="large-text">'
			. '<p class="description">Sert à retrouver la campagne dans la liste. N\'apparaît pas dans l\'email.</p></td></tr>';
		echo '<tr><th><label for="ed_objet">Objet de l\'email</label></th><td>'
			. '<input type="text" id="ed_objet" name="ed_objet" value="' . esc_attr( $ed['objet'] ) . '" class="large-text"></td></tr>';
		echo '<tr><th><label for="ed_corps">Corps</label></th><td>';
		wp_editor( $ed['corps'], 'ed_corps', [
			'textarea_name' => 'ed_corps',
			'textarea_rows' => 18,
			'media_buttons' => true,
		] );
		echo '<p class="description">Variable disponible : <code>{{prenom}}</code>.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Enregistrer ce texte' );
		echo '</form>';

		if ( ! empty( $ed['ajoute'] ) ) {
			echo '<form method="post" style="margin-top:-12px;" onsubmit="return confirm(\'Supprimer ce cadeau ? Cette action est définitive.\');">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="lib_delete">';
			echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
			echo '<input type="hidden" name="key" value="' . esc_attr( $ed['key'] ) . '">';
			echo '<button class="button button-link-delete">Supprimer ce cadeau</button>';
			echo '</form>';
		}

		if ( $ed['modifie'] ) {
			echo '<form method="post" style="margin-top:-12px;" onsubmit="return confirm(\'Rétablir le texte d\\\'origine ? La version modifiée sera perdue.\');">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="lib_reset">';
			echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
			echo '<input type="hidden" name="key" value="' . esc_attr( $ed['key'] ) . '">';
			echo '<button class="button">↩ Rétablir le texte d\'origine</button>';
			echo '</form>';
		}
	}

	/* ── Liste d'une collection ───────────────────────────────────── */
	private static function render_library_list( $col ) {
		$editions = CFNL_Library::editions( $col );
		$mp_ready = CFNL_Library::mailpoet_ready();

		if ( 'annuel' === $col ) {
			echo '<p>Les 12 éditions mensuelles, prêtes à l\'emploi. Tu peux modifier chaque texte, puis créer la campagne et l\'envoyer toi-même.</p>';
			// Programmer toute l'année en un clic
			echo '<div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:8px;padding:16px 20px;margin:16px 0;">';
			echo '<h3 style="margin-top:0;">Programmer toute l\'année</h3>';
			echo '<p>Crée et programme les 12 éditions au <strong>1ᵉʳ de chaque mois à 9 h</strong> dans le module intégré (les mois déjà passés basculent sur l\'an prochain). Chaque édition reste modifiable ou annulable avant sa date d\'envoi.</p>';
			echo '<form method="post" onsubmit="return confirm(\'Programmer les 12 newsletters de l\\\'année ?\');">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="lib_schedule_year">';
			echo '<button class="button button-primary">Programmer les 12 éditions</button>';
			echo '</form></div>';
		} else {
			echo '<p>Un brouillon de newsletter par article du blog, à piocher au fil de l\'eau — pas de calendrier imposé. Chaque texte est modifiable ici.</p>';
		}

		if ( ! $mp_ready ) {
			echo '<div class="notice notice-warning inline"><p>MailPoet n\'est pas détecté : le bouton « Brouillon MailPoet » est masqué. Utilise « Copier » ou « Module intégré ».</p></div>';
		}
		if ( ! $editions ) {
			echo '<p>Aucune édition dans cette collection pour l\'instant.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat striped" style="margin-top:12px;"><thead><tr>'
			. '<th style="width:26%;">Titre</th><th>Objet</th><th style="width:38%;">Actions</th></tr></thead><tbody>';

		foreach ( $editions as $ed ) {
			$edit_url = add_query_arg(
				[ 'page' => 'cfnl-library', 'col' => $col, 'edit' => $ed['key'] ],
				admin_url( 'admin.php' )
			);
			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $ed['titre'] ) . '</a></strong>';
			if ( ! empty( $ed['ajoute'] ) ) {
				echo ' <span style="font-size:11px;background:#eef2ff;color:#3730a3;border-radius:3px;padding:1px 6px;">écrit ici</span>';
			} elseif ( $ed['modifie'] ) {
				echo ' <span style="font-size:11px;background:#e7f5ea;color:#1e6b33;border-radius:3px;padding:1px 6px;">modifié</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $ed['objet'] ) . '</td>';
			echo '<td><div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';

			echo '<a href="' . esc_url( $edit_url ) . '" class="button">Modifier</a>';

			if ( $mp_ready ) {
				echo '<form method="post" style="margin:0;">';
				wp_nonce_field( 'cfnl_admin' );
				echo '<input type="hidden" name="cfnl_admin_action" value="lib_mailpoet">';
				echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
				echo '<input type="hidden" name="key" value="' . esc_attr( $ed['key'] ) . '">';
				echo '<button class="button">Brouillon MailPoet</button>';
				echo '</form>';
			}

			echo '<form method="post" style="margin:0;">';
			wp_nonce_field( 'cfnl_admin' );
			echo '<input type="hidden" name="cfnl_admin_action" value="lib_native">';
			echo '<input type="hidden" name="col" value="' . esc_attr( $col ) . '">';
			echo '<input type="hidden" name="key" value="' . esc_attr( $ed['key'] ) . '">';
			echo '<button class="button button-primary">Créer la campagne</button>';
			echo '</form>';

			// Copie presse-papier (contenu HTML dans un textarea caché)
			$tid = 'cfnl-lib-' . md5( $col . '|' . $ed['key'] );
			echo '<button type="button" class="button cfnl-copy" data-target="' . esc_attr( $tid ) . '">Copier</button>';
			echo '<textarea id="' . esc_attr( $tid ) . '" style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">' . esc_textarea( $ed['corps'] ) . '</textarea>';

			echo '</div></td></tr>';
		}
		echo '</tbody></table>';

		// Petit script de copie
		echo '<script>
		document.querySelectorAll(".cfnl-copy").forEach(function(btn){
			btn.addEventListener("click",function(){
				var ta=document.getElementById(this.dataset.target);
				if(!ta)return;
				var txt=ta.value;
				var done=this;
				var ok=function(){var o=done.textContent;done.textContent="Copié";setTimeout(function(){done.textContent=o;},1500);};
				if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt).then(ok,function(){ta.select();document.execCommand("copy");ok();});}
				else{ta.select();document.execCommand("copy");ok();}
			});
		});
		</script>';
	}

	/* ── Page Automatisations ─────────────────────────────────────── */
	public static function page_automations() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = get_option( 'cfnl_settings', CFNL_Install::default_settings() );
		echo '<div class="wrap"><h1>Newsletter — Automatisations</h1>';
		self::notice();

		echo '<form method="post">';
		wp_nonce_field( 'cfnl_admin' );
		echo '<input type="hidden" name="cfnl_admin_action" value="save_automations">';

		// Email de bienvenue
		echo '<h2>👋 Email de bienvenue</h2>';
		echo '<p class="description">Envoyé automatiquement dès qu\'un abonné confirme son inscription.</p>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Activer</th><td><label><input type="checkbox" name="welcome_enabled" value="1" ' . checked( ! empty( $s['welcome_enabled'] ), true, false ) . '> Envoyer un email de bienvenue</label></td></tr>';
		echo '<tr><th>Objet</th><td><input type="text" name="welcome_objet" value="' . esc_attr( $s['welcome_objet'] ?? '' ) . '" class="large-text"></td></tr>';
		echo '<tr><th>Corps</th><td><textarea name="welcome_corps" rows="6" class="large-text" style="font-family:Georgia,serif;">' . esc_textarea( $s['welcome_corps'] ?? '' ) . '</textarea><p class="description">Variable : <code>{{prenom}}</code></p></td></tr>';
		echo '</tbody></table>';

		// Séquence multi-étapes
		echo '<hr><h2>🌱 Suite de la bienvenue (séquence multi-étapes)</h2>';
		echo '<p class="description">Deux emails optionnels envoyés après l\'email de bienvenue, chacun N jours après le précédent. La personne sort de la séquence si elle se désinscrit.</p>';
		foreach ( [ 2, 3 ] as $step ) {
			echo '<table class="form-table"><tbody>';
			echo '<tr><th>Email ' . (int) $step . '</th><td><label><input type="checkbox" name="welcome' . (int) $step . '_enabled" value="1" ' . checked( ! empty( $s[ "welcome{$step}_enabled" ] ), true, false ) . '> Activer</label> — envoyé '
				. '<input type="number" name="welcome' . (int) $step . '_days" value="' . (int) ( $s[ "welcome{$step}_days" ] ?? 3 ) . '" class="small-text" min="1"> jour(s) après l\'email précédent</td></tr>';
			echo '<tr><th>Objet</th><td><input type="text" name="welcome' . (int) $step . '_objet" value="' . esc_attr( $s[ "welcome{$step}_objet" ] ?? '' ) . '" class="large-text"></td></tr>';
			echo '<tr><th>Corps</th><td><textarea name="welcome' . (int) $step . '_corps" rows="5" class="large-text" style="font-family:Georgia,serif;">' . esc_textarea( $s[ "welcome{$step}_corps" ] ?? '' ) . '</textarea></td></tr>';
			echo '</tbody></table>';
		}

		// Nouveau cadeau dans la bibliothèque
		$nb_lib = 0;
		if ( class_exists( 'CFNL_Library' ) ) {
			foreach ( array_keys( CFNL_Library::collections() ) as $c ) {
				$nb_lib += count( CFNL_Library::editions( $c ) );
			}
		}
		echo '<hr><h2>Nouveau cadeau dans la bibliothèque</h2>';
		echo '<p class="description">Le déclencheur principal du rythme de la newsletter : dès qu\'un texte apparaît dans la '
			. '<a href="' . esc_url( add_query_arg( [ 'page' => 'cfnl-library' ], admin_url( 'admin.php' ) ) ) . '">Bibliothèque</a>, '
			. 'sa campagne est créée. Tu écris un cadeau, il part — sans dépendre de la publication d\'un article.</p>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Activer</th><td><label><input type="checkbox" name="libnotif_enabled" value="1" ' . checked( ! empty( $s['libnotif_enabled'] ), true, false ) . '> '
			. 'Créer une campagne à chaque nouveau texte</label>';
		echo '<p class="description">Vaut pour les textes écrits ici comme pour ceux livrés avec une mise à jour du plugin. '
			. 'Les <strong>' . (int) $nb_lib . '</strong> textes déjà présents ne sont pas concernés : seul ce qui apparaît '
			. 'après l\'activation déclenche quelque chose.</p></td></tr>';
		echo '<tr><th>Mode</th><td><select name="libnotif_mode">';
		echo '<option value="draft" ' . selected( $s['libnotif_mode'] ?? 'draft', 'draft', false ) . '>Créer un brouillon (tu relis et envoies toi-même) — recommandé</option>';
		echo '<option value="send" ' . selected( $s['libnotif_mode'] ?? 'draft', 'send', false ) . '>Envoyer immédiatement (sans relecture)</option>';
		echo '</select></td></tr>';
		echo '</tbody></table>';

		// Notification d'article
		echo '<hr><h2>Notification d\'article</h2>';
		if ( ! empty( $s['libnotif_enabled'] ) && ! empty( $s['postnotif_enabled'] ) ) {
			echo '<div class="notice notice-warning inline"><p>Les deux automatisations sont actives en même temps. '
				. 'Si un article et son cadeau arrivent ensemble, tes abonnés recevront deux mails sur le même sujet.</p></div>';
		}
		echo '<p class="description">L\'ancien fonctionnement : quand tu publies un article, une newsletter est générée à partir de son titre, '
			. 'son extrait et son image. Redondant avec l\'automatisation ci-dessus — à n\'activer que si tu veux revenir à ce mode.</p>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Activer</th><td><label><input type="checkbox" name="postnotif_enabled" value="1" ' . checked( ! empty( $s['postnotif_enabled'] ), true, false ) . '> Créer une newsletter à chaque nouvel article</label></td></tr>';
		echo '<tr><th>Mode</th><td><select name="postnotif_mode">';
		echo '<option value="draft" ' . selected( $s['postnotif_mode'] ?? 'draft', 'draft', false ) . '>Créer un brouillon (tu valides et envoies toi-même) — recommandé</option>';
		echo '<option value="send" ' . selected( $s['postnotif_mode'] ?? 'draft', 'send', false ) . '>Envoyer automatiquement (sans validation)</option>';
		echo '</select></td></tr>';
		echo '<tr><th>Objet</th><td><input type="text" name="postnotif_objet" value="' . esc_attr( $s['postnotif_objet'] ?? '' ) . '" class="large-text"><p class="description">Variable : <code>{{post_title}}</code></p></td></tr>';
		$pn_corps = (string) ( $s['postnotif_corps'] ?? '' );
		if ( '' === trim( $pn_corps ) ) {
			$defaults = CFNL_Install::default_settings();
			$pn_corps = (string) $defaults['postnotif_corps'];
		}
		echo '<tr><th>Corps</th><td><textarea name="postnotif_corps" rows="12" class="large-text code" style="font-family:Georgia,serif;">' . esc_textarea( $pn_corps ) . '</textarea>';
		echo '<p class="description">Le texte du mail généré à chaque publication. Les balises sont remplacées automatiquement :<br>'
			. '<code>{{prenom}}</code> prénom de l\'abonné · '
			. '<code>{{post_title}}</code> titre de l\'article · '
			. '<code>{{post_excerpt}}</code> extrait · '
			. '<code>{{post_url}}</code> adresse de l\'article<br>'
			. '<code>{{post_image}}</code> image à la une, déjà mise en forme (ne laisse rien si l\'article n\'en a pas) · '
			. '<code>{{post_bouton}}</code> bouton « Lire l\'article » déjà mis en forme</p></td></tr>';
		echo '</tbody></table>';

		submit_button( 'Enregistrer les automatisations' );
		echo '</form></div>';
	}

	/* ── Export CSV des abonnés ───────────────────────────────────── */
	private static function export_csv() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT email, prenom, nom, frequence, statut, cree_le FROM " . CFNL_Subscribers::table() . " ORDER BY id ASC", ARRAY_A );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="abonnes-newsletter.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'email', 'prenom', 'nom', 'frequence', 'statut', 'inscrit_le' ] );
		$neutralize = function ( $v ) {
			// Anti-injection de formule (Excel/Sheets) : préfixe les cellules à risque
			return preg_match( '/^[=+\-@\t\r]/', (string) $v ) ? "'" . $v : $v;
		};
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( $neutralize, $r ) );
		}
		fclose( $out );
		exit;
	}

	/* ── Import CSV (colonnes : email, prenom, frequence) ─────────── */
	private static function import_csv( $tmp_file ) {
		$fh = fopen( $tmp_file, 'r' );
		if ( ! $fh ) return 0;
		$added  = 0;
		$header = null;
		while ( ( $line = fgetcsv( $fh ) ) !== false ) {
			if ( null === $header ) {
				// Détecte si la 1re ligne est un en-tête
				$header = array_map( 'strtolower', array_map( 'trim', $line ) );
				if ( in_array( 'email', $header, true ) ) {
					continue; // c'était un en-tête, on passe
				}
				$header = [ 'email', 'prenom', 'frequence' ]; // pas d'en-tête : ordre supposé
			}
			$email = sanitize_email( $line[0] ?? '' );
			if ( ! is_email( $email ) ) continue;
			$prenom = sanitize_text_field( $line[1] ?? '' );
			$freq   = sanitize_key( $line[2] ?? 'mensuel' );
			$res = CFNL_Subscribers::add( $email, $prenom, '', $freq, 'import' );
			if ( ! is_wp_error( $res ) ) $added++;
		}
		fclose( $fh );
		return $added;
	}

	private static function notice() {
		if ( empty( $_GET['msg'] ) ) return;
		$n = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0;
		$map = [
			'saved'    => [ 'success', 'Enregistré.' ],
			'sending'  => [ 'success', 'Envoi lancé ! Les emails partent par lots toutes les 5 minutes.' ],
			'scheduled'=> [ 'success', 'Campagne programmée. Elle partira automatiquement à l\'heure prévue.' ],
			'send_err' => [ 'error', 'Impossible (aucun destinataire, date invalide, ou campagne déjà envoyée).' ],
			'deleted'  => [ 'success', 'Campagne supprimée.' ],
			'added'    => [ 'success', 'Abonné ajouté.' ],
			'imported' => [ 'success', $n . ' abonné(s) importé(s).' ],
			'smtp_ok'  => [ 'success', 'Email de test envoyé ! Vérifie ta boîte de réception.' ],
			'smtp_ko'  => [ 'error', 'Le test a échoué. Vérifie le serveur, le port, l\'identifiant et le mot de passe SMTP.' ],
			'test_ok'  => [ 'success', 'Email de test envoyé ! Vérifie ta boîte de réception (objet préfixé [TEST]).' ],
			'test_ko'  => [ 'error', 'L\'envoi du test a échoué. Enregistre la campagne et vérifie le SMTP.' ],
			'resend_ok'=> [ 'success', 'Relance créée et lancée vers ' . $n . ' non-ouvreur(s).' ],
			'resend_ko'=> [ 'error', 'Renvoi impossible (campagne non envoyée, ou aucun non-ouvreur).' ],
			'lib_native_ok' => [ 'success', 'Campagne créée dans le module intégré. Relis-la puis lance l\'envoi.' ],
			'year_ok'  => [ 'success', $n . ' édition(s) programmée(s) au 1er de chaque mois à 9 h. Chacune reste modifiable avant sa date.' ],
			'lib_mp_ko'=> [ 'error', 'Création du brouillon MailPoet impossible (format MailPoet différent). Utilise « Copier le contenu ».' ],
			'lib_ko'   => [ 'error', 'Création impossible.' ],
			'lib_saved'=> [ 'success', 'Texte enregistré. C\'est cette version qui sera utilisée désormais.' ],
			'lib_reset'=> [ 'success', 'Texte d\'origine rétabli.' ],
			'lib_new_ok'   => [ 'success', 'Cadeau créé. Utilise « Créer la campagne » quand tu veux l\'envoyer.' ],
			'lib_new_auto' => [ 'success', 'Cadeau créé, et la campagne correspondante a été générée automatiquement — elle t\'attend dans Campagnes.' ],
			'lib_new_ko'   => [ 'error', 'Création impossible : il faut au moins un titre.' ],
			'lib_deleted'  => [ 'success', 'Cadeau supprimé.' ],
		];
		$msg = sanitize_key( $_GET['msg'] );
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $msg ][1] ) . '</p></div>';
		}
	}
}
