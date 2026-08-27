<?php
/**
 * Programme Pleine Vie — administration (inscriptions + modèles d'emails).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Admin {

	public static function register_menu() {
		add_menu_page( 'Pleine Vie', 'Pleine Vie', 'manage_options', 'cfpv-inscriptions', [ __CLASS__, 'page_list' ], 'dashicons-heart', 57 );
		add_submenu_page( 'cfpv-inscriptions', 'Inscriptions', 'Inscriptions', 'manage_options', 'cfpv-inscriptions', [ __CLASS__, 'page_list' ] );
		add_submenu_page( 'cfpv-inscriptions', 'Modèles d\'emails', 'Modèles d\'emails', 'manage_options', 'cfpv-emails', [ __CLASS__, 'page_emails' ] );
		add_submenu_page( 'cfpv-inscriptions', 'Séquence d\'accompagnement', 'Séquence', 'manage_options', 'cfpv-sequence', [ __CLASS__, 'page_sequence' ] );
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['cfpv_action'] ) ) {
			return;
		}
		check_admin_referer( 'cfpv_admin' );
		$action = sanitize_key( $_POST['cfpv_action'] );

		if ( 'set_statut' === $action ) {
			$id     = absint( $_POST['id'] ?? 0 );
			$statut = sanitize_key( $_POST['statut'] ?? '' );
			CFPV_Registrations::set_statut( $id, $statut );
			$row = CFPV_Registrations::get( $id );
			if ( $row ) {
				if ( 'confirme' === $statut ) {
					CFPV_Emails::send_validation( $row );
				} elseif ( 'annule' === $statut ) {
					CFPV_Emails::send_cancel( $row );
				}
			}
			self::redirect( [ 'page' => 'cfpv-inscriptions', 'msg' => 'statut' ] );
		}

		if ( 'resend_ack' === $action ) {
			$row = CFPV_Registrations::get( absint( $_POST['id'] ?? 0 ) );
			if ( $row ) {
				CFPV_Emails::send_ack( $row );
			}
			self::redirect( [ 'page' => 'cfpv-inscriptions', 'msg' => 'resent' ] );
		}

		if ( 'save_emails' === $action ) {
			$prev = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
			$prev['from_name']    = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
			$prev['from_email']   = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
			$prev['notif_email']  = sanitize_email( wp_unslash( $_POST['notif_email'] ?? '' ) );
			$prev['session']      = sanitize_text_field( wp_unslash( $_POST['session'] ?? '' ) );
			// Coordonnées de virement
			$prev['vir_titulaire']    = sanitize_text_field( wp_unslash( $_POST['vir_titulaire'] ?? '' ) );
			$prev['vir_iban']         = sanitize_text_field( wp_unslash( $_POST['vir_iban'] ?? '' ) );
			$prev['vir_bic']          = sanitize_text_field( wp_unslash( $_POST['vir_bic'] ?? '' ) );
			$prev['vir_montant']      = sanitize_text_field( wp_unslash( $_POST['vir_montant'] ?? '' ) );
			$prev['vir_instructions'] = sanitize_textarea_field( wp_unslash( $_POST['vir_instructions'] ?? '' ) );
			foreach ( [ 'ack', 'valid', 'cancel' ] as $k ) {
				$prev[ "{$k}_objet" ] = sanitize_text_field( wp_unslash( $_POST[ "{$k}_objet" ] ?? '' ) );
				$prev[ "{$k}_corps" ] = wp_kses_post( wp_unslash( $_POST[ "{$k}_corps" ] ?? '' ) );
			}
			update_option( 'cfpv_settings', $prev );
			self::redirect( [ 'page' => 'cfpv-emails', 'msg' => 'saved' ] );
		}

		if ( 'save_sequence' === $action ) {
			$prev = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
			$prev['seq_anchor'] = ( 'demarrage' === ( $_POST['seq_anchor'] ?? '' ) ) ? 'demarrage' : 'confirmation';
			$prev['demarrage']  = sanitize_text_field( wp_unslash( $_POST['demarrage'] ?? '' ) );
			for ( $i = 1; $i <= CFPV_Sequence::NB_STEPS; $i++ ) {
				$prev[ "seq_{$i}_enabled" ] = ! empty( $_POST[ "seq_{$i}_enabled" ] ) ? 1 : 0;
				$prev[ "seq_{$i}_day" ]     = max( 0, absint( $_POST[ "seq_{$i}_day" ] ?? 0 ) );
				$prev[ "seq_{$i}_objet" ]   = sanitize_text_field( wp_unslash( $_POST[ "seq_{$i}_objet" ] ?? '' ) );
				$prev[ "seq_{$i}_corps" ]   = wp_kses_post( wp_unslash( $_POST[ "seq_{$i}_corps" ] ?? '' ) );
			}
			update_option( 'cfpv_settings', $prev );
			self::redirect( [ 'page' => 'cfpv-sequence', 'msg' => 'saved' ] );
		}
	}

	private static function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notice() {
		if ( empty( $_GET['msg'] ) ) return;
		$map = [
			'statut' => 'Statut mis à jour (email envoyé si validation/annulation).',
			'resent' => 'Accusé de réception renvoyé.',
			'saved'  => 'Modèles enregistrés.',
		];
		$m = sanitize_key( $_GET['msg'] );
		if ( isset( $map[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $m ] ) . '</p></div>';
		}
	}

	/* ── Liste des inscriptions ───────────────────────────────────── */
	public static function page_list() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$c    = CFPV_Registrations::counts();
		$rows = CFPV_Registrations::all();
		$smap = [ 'attente' => '⏳ En attente', 'confirme' => '✅ Confirmée', 'annule' => '🚫 Annulée', 'termine' => '🎓 Terminée' ];

		echo '<div class="wrap"><h1>Programme Pleine Vie — Inscriptions</h1>';
		self::notice();
		echo '<p>Formulaire d\'inscription à poser sur la page : <code>[cf_pleine_vie_inscription]</code></p>';

		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;">';
		foreach ( [ 'En attente' => $c['attente'], 'Confirmées' => $c['confirme'], 'Annulées' => $c['annule'], 'Terminées' => $c['termine'] ] as $lbl => $val ) {
			echo '<div style="flex:1;min-width:130px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:14px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05);">'
				. '<div style="font-size:24px;font-weight:700;color:#5a3e6b;">' . (int) $val . '</div>'
				. '<div style="font-size:12px;color:#646970;">' . esc_html( $lbl ) . '</div></div>';
		}
		echo '</div>';

		echo '<table class="wp-list-table widefat striped cfeb-table"><thead><tr>'
			. '<th>Candidat</th><th>Contact</th><th>Message</th><th>Statut</th><th>Reçu le</th><th>Actions</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6" style="text-align:center;padding:20px;color:#888;">Aucune inscription pour l\'instant.</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr class="cfeb-booking-row">';
			echo '<td><strong class="cfeb-row-name">' . esc_html( trim( $r->prenom . ' ' . $r->nom ) ) . '</strong></td>';
			echo '<td data-label="Contact">' . esc_html( $r->email ) . ( $r->telephone ? '<br><small>' . esc_html( $r->telephone ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="Message" style="max-width:260px;font-size:13px;color:#555;">' . esc_html( wp_trim_words( $r->message, 22 ) ) . '</td>';
			echo '<td data-label="Statut">' . esc_html( $smap[ $r->statut ] ?? $r->statut ) . '</td>';
			echo '<td data-label="Reçu le">' . esc_html( date_i18n( 'd/m/Y', strtotime( $r->cree_le ) ) ) . '</td>';
			echo '<td data-label="Actions">';
			if ( 'attente' === $r->statut ) {
				echo self::action_button( $r->id, 'confirme', '✅ Valider', 'button-primary', 'Valider cette inscription et envoyer l\'email de bienvenue ?' );
				echo ' ' . self::action_button( $r->id, 'annule', '🚫 Refuser', '', 'Refuser et envoyer l\'email d\'annulation ?' );
			} elseif ( 'confirme' === $r->statut ) {
				echo self::action_button( $r->id, 'termine', '🎓 Marquer terminé', '', '' );
			}
			echo ' ' . self::resend_button( $r->id );
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function action_button( $id, $statut, $label, $class, $confirm ) {
		$onsubmit = $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : '';
		return '<form method="post" style="display:inline;"' . $onsubmit . '>'
			. wp_nonce_field( 'cfpv_admin', '_wpnonce', true, false )
			. '<input type="hidden" name="cfpv_action" value="set_statut">'
			. '<input type="hidden" name="id" value="' . (int) $id . '">'
			. '<input type="hidden" name="statut" value="' . esc_attr( $statut ) . '">'
			. '<button class="button button-small ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function resend_button( $id ) {
		return '<form method="post" style="display:inline;">'
			. wp_nonce_field( 'cfpv_admin', '_wpnonce', true, false )
			. '<input type="hidden" name="cfpv_action" value="resend_ack">'
			. '<input type="hidden" name="id" value="' . (int) $id . '">'
			. '<button class="button button-small" title="Renvoyer l\'accusé de réception">✉️</button></form>';
	}

	/* ── Modèles d'emails ─────────────────────────────────────────── */
	public static function page_emails() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
		echo '<div class="wrap"><h1>Programme Pleine Vie — Modèles d\'emails</h1>';
		self::notice();
		echo '<p>Variables disponibles : <code>{{prenom}}</code> <code>{{nom}}</code> <code>{{email}}</code> <code>{{session}}</code>.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'cfpv_admin' );
		echo '<input type="hidden" name="cfpv_action" value="save_emails">';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Nom expéditeur</th><td><input type="text" name="from_name" value="' . esc_attr( $s['from_name'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Email expéditeur</th><td><input type="email" name="from_email" value="' . esc_attr( $s['from_email'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Recevoir les notifications à</th><td><input type="email" name="notif_email" value="' . esc_attr( $s['notif_email'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Libellé de session</th><td><input type="text" name="session" value="' . esc_attr( $s['session'] ) . '" class="regular-text" placeholder="ex. Session d\'octobre"></td></tr>';
		echo '</tbody></table>';

		// Coordonnées de virement (insérées via {{virement}})
		echo '<hr><h2>🏦 Paiement par virement</h2>';
		echo '<p class="description">Insère ces coordonnées dans un email avec la variable <code>{{virement}}</code> (déjà présente dans l\'email de validation).</p>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Titulaire du compte</th><td><input type="text" name="vir_titulaire" value="' . esc_attr( $s['vir_titulaire'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>IBAN</th><td><input type="text" name="vir_iban" value="' . esc_attr( $s['vir_iban'] ) . '" class="regular-text" placeholder="FR76 ...."></td></tr>';
		echo '<tr><th>BIC</th><td><input type="text" name="vir_bic" value="' . esc_attr( $s['vir_bic'] ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Montant</th><td><input type="text" name="vir_montant" value="' . esc_attr( $s['vir_montant'] ) . '" class="regular-text" placeholder="ex. 490 €"></td></tr>';
		echo '<tr><th>Instructions</th><td><textarea name="vir_instructions" rows="2" class="large-text">' . esc_textarea( $s['vir_instructions'] ) . '</textarea></td></tr>';
		echo '</tbody></table>';

		self::email_block( 'Accusé de réception (à l\'inscription)', 'ack', $s );
		self::email_block( 'Email de validation (quand tu valides)', 'valid', $s );
		self::email_block( 'Email d\'annulation (si tu refuses)', 'cancel', $s );

		submit_button( 'Enregistrer les modèles' );
		echo '</form></div>';
	}

	private static function email_block( $titre, $key, $s ) {
		echo '<hr><h2>' . esc_html( $titre ) . '</h2><table class="form-table"><tbody>';
		echo '<tr><th>Objet</th><td><input type="text" name="' . esc_attr( $key ) . '_objet" value="' . esc_attr( $s[ "{$key}_objet" ] ) . '" class="large-text"></td></tr>';
		echo '<tr><th>Corps</th><td><textarea name="' . esc_attr( $key ) . '_corps" rows="8" class="large-text" style="font-family:Georgia,serif;">' . esc_textarea( $s[ "{$key}_corps" ] ) . '</textarea></td></tr>';
		echo '</tbody></table>';
	}

	/* ── Page Séquence d'accompagnement ───────────────────────────── */
	public static function page_sequence() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
		echo '<div class="wrap"><h1>Programme Pleine Vie — Séquence d\'accompagnement</h1>';
		self::notice();
		echo '<p>Emails automatiques envoyés aux inscrits <strong>confirmés</strong>, au fil du mois. Variables : <code>{{prenom}}</code> <code>{{nom}}</code> <code>{{virement}}</code>. Le cron tourne chaque jour à 8h.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'cfpv_admin' );
		echo '<input type="hidden" name="cfpv_action" value="save_sequence">';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Point de départ (jour 0)</th><td><select name="seq_anchor">';
		echo '<option value="confirmation" ' . selected( $s['seq_anchor'], 'confirmation', false ) . '>La date de validation de chaque inscription</option>';
		echo '<option value="demarrage" ' . selected( $s['seq_anchor'], 'demarrage', false ) . '>Une date de démarrage commune (ci-dessous)</option>';
		echo '</select></td></tr>';
		echo '<tr><th>Date de démarrage commune</th><td><input type="date" name="demarrage" value="' . esc_attr( $s['demarrage'] ) . '"> <span class="description">utilisée seulement si l\'option ci-dessus est « date commune »</span></td></tr>';
		echo '</tbody></table>';

		for ( $i = 1; $i <= CFPV_Sequence::NB_STEPS; $i++ ) {
			echo '<hr><h2>Étape ' . (int) $i . '</h2><table class="form-table"><tbody>';
			echo '<tr><th>Activer</th><td><label><input type="checkbox" name="seq_' . $i . '_enabled" value="1" ' . checked( ! empty( $s[ "seq_{$i}_enabled" ] ), true, false ) . '> Envoyer cette étape</label> — '
				. 'jour <input type="number" name="seq_' . $i . '_day" value="' . (int) ( $s[ "seq_{$i}_day" ] ?? 0 ) . '" class="small-text" min="0"> après le point de départ</td></tr>';
			echo '<tr><th>Objet</th><td><input type="text" name="seq_' . $i . '_objet" value="' . esc_attr( $s[ "seq_{$i}_objet" ] ?? '' ) . '" class="large-text"></td></tr>';
			echo '<tr><th>Corps</th><td><textarea name="seq_' . $i . '_corps" rows="6" class="large-text" style="font-family:Georgia,serif;">' . esc_textarea( $s[ "seq_{$i}_corps" ] ?? '' ) . '</textarea></td></tr>';
			echo '</tbody></table>';
		}

		submit_button( 'Enregistrer la séquence' );
		echo '</form></div>';
	}
}
