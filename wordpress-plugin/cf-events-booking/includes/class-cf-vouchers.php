<?php
/**
 * Bons cadeaux — achat via SumUp, code unique par email, utilisable à la réservation.
 * Shortcode : [cf_bon_cadeau]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Vouchers {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cf_vouchers';
	}

	public static function create_table() {
		global $wpdb;
		$t = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$t} (
			id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
			code           VARCHAR(20)  NOT NULL,
			montant        DECIMAL(8,2) NOT NULL DEFAULT 0,
			acheteur_nom   VARCHAR(150) NOT NULL DEFAULT '',
			acheteur_email VARCHAR(200) NOT NULL DEFAULT '',
			beneficiaire   VARCHAR(150) NOT NULL DEFAULT '',
			message        TEXT         NOT NULL,
			checkout_id    VARCHAR(80)  NOT NULL DEFAULT '',
			statut         VARCHAR(20)  NOT NULL DEFAULT 'attente',
			booking_id     INT UNSIGNED NOT NULL DEFAULT 0,
			cree_le        DATETIME     NOT NULL,
			active_le      DATETIME     DEFAULT NULL,
			utilise_le     DATETIME     DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY statut (statut)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	private static function generate_code() {
		// Code lisible au téléphone : CF-XXXX-XXXX sans caractères ambigus
		$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$c = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$c .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			if ( 3 === $i ) $c .= '-';
		}
		return 'CF-' . $c;
	}

	/* ── Shortcode [cf_bon_cadeau] : formulaire d'achat ───────────── */
	public static function shortcode() {
		$montants = apply_filters( 'cfeb_voucher_amounts', [ 80, 90, 120 ] );
		ob_start();
		if ( isset( $_GET['cfv_ok'] ) ) {
			echo '<p style="background:#f0fdf4;border:1px solid #86efac;padding:14px 18px;border-radius:8px;">Merci ! Une fois le paiement confirmé, le bon cadeau et son code seront envoyés par email. 🎁</p>';
			return ob_get_clean();
		}
		?>
		<form method="post" class="cfv-form" style="max-width:480px;">
			<?php wp_nonce_field( 'cfv_buy', 'cfv_nonce' ); ?>
			<p><label>Votre nom *<br><input type="text" name="cfv_nom" required style="width:100%;padding:10px;"></label></p>
			<p><label>Votre email *<br><input type="email" name="cfv_email" required style="width:100%;padding:10px;"></label></p>
			<p><label>Prénom de la personne à qui vous l'offrez<br><input type="text" name="cfv_benef" style="width:100%;padding:10px;"></label></p>
			<p><label>Montant *<br><select name="cfv_montant" required style="width:100%;padding:10px;">
				<?php foreach ( $montants as $m ) : ?>
					<option value="<?php echo (int) $m; ?>"><?php echo (int) $m; ?> € — <?php echo 80 === (int) $m ? 'une séance individuelle' : ( 90 === (int) $m ? 'une séance individuelle longue' : 'séance + suivi' ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
			<p><label>Un mot à joindre au bon (optionnel)<br><textarea name="cfv_message" rows="3" style="width:100%;padding:10px;"></textarea></label></p>
			<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:13px 28px;border-radius:6px;cursor:pointer;">Offrir ce bon → paiement sécurisé</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ── Traitement du formulaire d'achat (hook init) ─────────────── */
	public static function handle_purchase() {
		if ( empty( $_POST['cfv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfv_nonce'] ) ), 'cfv_buy' ) ) {
			return;
		}
		$nom     = sanitize_text_field( wp_unslash( $_POST['cfv_nom'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['cfv_email'] ?? '' ) );
		$benef   = sanitize_text_field( wp_unslash( $_POST['cfv_benef'] ?? '' ) );
		$montant = (float) ( $_POST['cfv_montant'] ?? 0 );
		$msg     = sanitize_textarea_field( wp_unslash( $_POST['cfv_message'] ?? '' ) );
		if ( ! $nom || ! is_email( $email ) || $montant < 1 || $montant > 500 ) {
			return;
		}

		global $wpdb;
		$code = self::generate_code();
		$wpdb->insert( self::table(), [
			'code'           => $code,
			'montant'        => $montant,
			'acheteur_nom'   => $nom,
			'acheteur_email' => $email,
			'beneficiaire'   => $benef,
			'message'        => $msg,
			'statut'         => 'attente',
			'cree_le'        => current_time( 'mysql' ),
		] );
		$id = (int) $wpdb->insert_id;

		// Lien de paiement SumUp
		if ( class_exists( 'CF_SumUp' ) && CF_SumUp::is_configured() ) {
			$url = CF_SumUp::create_checkout( $id, $nom, 'Bon cadeau', $montant, 'bon' );
			if ( ! is_wp_error( $url ) && $url ) {
				if ( preg_match( '#/b2c/([^/?]+)#', $url, $m ) ) {
					$wpdb->update( self::table(), [ 'checkout_id' => $m[1] ], [ 'id' => $id ] );
				}
				wp_redirect( $url );
				exit;
			}
		}
		// SumUp non configuré : bon en attente, paiement à organiser en direct
		wp_safe_redirect( add_query_arg( 'cfv_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	/* ── Vérifie le paiement SumUp puis active + envoie le bon ────── */
	public static function verify_and_activate( int $id ) {
		global $wpdb;
		$v = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
		if ( ! $v || 'attente' !== $v->statut ) {
			return new WP_Error( 'bad', 'Bon introuvable ou déjà traité.' );
		}
		if ( $v->checkout_id && class_exists( 'CF_SumUp' ) ) {
			$status = CF_SumUp::get_checkout_status( $v->checkout_id );
			if ( is_wp_error( $status ) ) return $status;
			if ( 'PAID' !== $status ) {
				return new WP_Error( 'unpaid', 'Paiement non confirmé côté SumUp (statut : ' . $status . ').' );
			}
		}
		return self::activate( $id );
	}

	/* ── Active (manuel ou après paiement) et envoie le code ──────── */
	public static function activate( int $id ) {
		global $wpdb;
		$v = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
		if ( ! $v || 'attente' !== $v->statut ) {
			return new WP_Error( 'bad', 'Bon introuvable ou déjà traité.' );
		}
		$wpdb->update( self::table(), [ 'statut' => 'actif', 'active_le' => current_time( 'mysql' ) ], [ 'id' => $id ] );

		$benef = $v->beneficiaire ?: 'la personne de ton choix';
		$body  = '<p>Bonjour ' . esc_html( $v->acheteur_nom ) . ',</p>'
			. '<p>Merci pour ce cadeau. 🎁 Voici le bon à offrir à ' . esc_html( $benef ) . ' :</p>'
			. '<div style="background:#f6f3f9;border:2px dashed #5a3e6b;border-radius:10px;padding:24px;text-align:center;margin:20px 0;">'
			. '<div style="font-size:13px;color:#666;">Bon cadeau — Constellation familiale</div>'
			. '<div style="font-size:28px;font-weight:700;letter-spacing:2px;color:#5a3e6b;margin:8px 0;">' . esc_html( $v->code ) . '</div>'
			. '<div style="font-size:15px;">Valeur : <strong>' . number_format( (float) $v->montant, 0, ',', ' ' ) . ' €</strong></div>'
			. ( $v->message ? '<p style="font-style:italic;color:#555;margin-top:14px;">« ' . esc_html( $v->message ) . ' »</p>' : '' )
			. '</div>'
			. '<p>Pour l\'utiliser : réserver une séance sur le site et saisir ce code dans le champ « Bon cadeau » du formulaire.</p>'
			. '<p>— Ewen</p>';
		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:Georgia,serif;color:#2c2c2c;max-width:560px;margin:0 auto;padding:24px;font-size:16px;line-height:1.7;">' . $body . '</body></html>';
		wp_mail( $v->acheteur_email, '🎁 Votre bon cadeau (' . $v->code . ')', $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		return true;
	}

	/* ── Valide un code à la réservation ; retourne la ligne ou WP_Error ── */
	public static function check_code( string $code ) {
		global $wpdb;
		$code = strtoupper( trim( $code ) );
		if ( '' === $code ) return null;
		$v = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE code = %s', $code ) );
		if ( ! $v ) return new WP_Error( 'invalid', 'Ce code de bon cadeau est inconnu.' );
		if ( 'actif' !== $v->statut ) return new WP_Error( 'used', 'Ce bon cadeau a déjà été utilisé ou n\'est pas encore actif.' );
		return $v;
	}

	/* ── Consomme le bon après une réservation réussie ────────────── */
	public static function redeem( $voucher, int $booking_id ) {
		global $wpdb;
		// Revendication atomique (empêche la double utilisation simultanée)
		$claimed = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . " SET statut = 'utilise', booking_id = %d, utilise_le = %s WHERE id = %d AND statut = 'actif'",
			$booking_id, current_time( 'mysql' ), (int) $voucher->id
		) );
		if ( $claimed ) {
			$wpdb->update( $wpdb->prefix . CFEB_TABLE, [ 'paye' => 1, 'mode_paiement' => 'Bon cadeau ' . $voucher->code ], [ 'id' => $booking_id ] );
		}
		return (bool) $claimed;
	}

	/* ── Activer/désactiver le champ dans les formulaires ─────────── */
	public static function is_enabled(): bool {
		return (bool) get_option( 'cfeb_vouchers_enabled', 1 );
	}

	/**
	 * Champ « bon cadeau » des formulaires de réservation. $prix permet
	 * d'appeler cette méthode systématiquement depuis les gabarits : un
	 * événement/type de RDV gratuit (0 €) n'a pas de solde à régler avec un
	 * bon, le champ n'a donc aucun sens et ne s'affiche jamais dans ce cas —
	 * indépendamment du réglage général ci-dessus.
	 */
	public static function render_field( $prix = null ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( null !== $prix && (float) $prix <= 0 ) {
			return;
		}
		echo '<p class="cfeb-field cfeb-field-voucher"><label>Bon cadeau <span style="font-weight:normal;color:#888;">(optionnel)</span><br>'
			. '<input type="text" name="cfeb_bon_code" placeholder="CF-XXXX-XXXX" style="text-transform:uppercase;"></label></p>';
	}

	/* ── Page admin : liste + actions ─────────────────────────────── */
	public static function register_menu() {
		add_submenu_page( 'edit.php?post_type=' . CFEB_SLUG, 'Bons cadeaux', '🎁 Bons cadeaux', 'manage_options', 'cfeb-vouchers', [ __CLASS__, 'page' ] );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;

		if ( ! empty( $_POST['cfv_settings_save'] ) && check_admin_referer( 'cfv_settings_save' ) ) {
			update_option( 'cfeb_vouchers_enabled', ! empty( $_POST['cfeb_vouchers_enabled'] ) ? 1 : 0 );
			echo '<div class="notice notice-success"><p>Réglage enregistré.</p></div>';
		}

		if ( ! empty( $_POST['cfv_admin'] ) && check_admin_referer( 'cfv_admin' ) ) {
			$id  = absint( $_POST['vid'] ?? 0 );
			$act = sanitize_key( $_POST['cfv_admin'] );
			if ( 'verify' === $act ) {
				$res = self::verify_and_activate( $id );
				echo is_wp_error( $res )
					? '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>Paiement confirmé — bon activé et envoyé par email.</p></div>';
			} elseif ( 'activate' === $act ) {
				$res = self::activate( $id );
				echo is_wp_error( $res )
					? '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>Bon activé et envoyé par email.</p></div>';
			} elseif ( 'cancel' === $act ) {
				$wpdb->update( self::table(), [ 'statut' => 'annule' ], [ 'id' => $id, 'statut' => 'attente' ] );
				echo '<div class="notice notice-success"><p>Bon annulé.</p></div>';
			}
		}

		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT 200' );
		$smap = [ 'attente' => '⏳ Attente paiement', 'actif' => '✅ Actif', 'utilise' => '🎫 Utilisé', 'annule' => '🚫 Annulé' ];
		echo '<div class="wrap"><h1>🎁 Bons cadeaux</h1>';
		echo '<p>Affiche le formulaire d\'achat avec le code court <code>[cf_bon_cadeau]</code>. Après un achat, vérifie le paiement en un clic — le bon part alors par email.</p>';

		echo '<form method="post" style="margin:14px 0;padding:12px 16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;max-width:640px;">';
		wp_nonce_field( 'cfv_settings_save' );
		echo '<label><input type="checkbox" name="cfeb_vouchers_enabled" value="1" ' . checked( self::is_enabled(), true, false ) . '> '
			. 'Proposer le champ « Bon cadeau » dans les formulaires de réservation</label>';
		echo '<p class="description" style="margin:4px 0 8px;">Décoché : les clients ne peuvent plus saisir de code à la réservation — les bons déjà émis restent consultables ci-dessous.</p>';
		echo '<button type="submit" name="cfv_settings_save" value="1" class="button">Enregistrer</button>';
		echo '</form>';
		echo '<table class="wp-list-table widefat striped cfeb-table"><thead><tr><th>Code</th><th>Montant</th><th>Acheteur</th><th>Pour</th><th>Statut</th><th>Créé le</th><th>Actions</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="7" style="text-align:center;padding:20px;color:#888;">Aucun bon cadeau pour l\'instant.</td></tr>';
		foreach ( $rows as $r ) {
			echo '<tr class="cfeb-booking-row">';
			echo '<td><strong class="cfeb-row-name">' . esc_html( $r->code ) . '</strong></td>';
			echo '<td data-label="Montant">' . number_format( (float) $r->montant, 0, ',', ' ' ) . ' €</td>';
			echo '<td data-label="Acheteur">' . esc_html( $r->acheteur_nom ) . '<br><small>' . esc_html( $r->acheteur_email ) . '</small></td>';
			echo '<td data-label="Pour">' . esc_html( $r->beneficiaire ?: '—' ) . '</td>';
			echo '<td data-label="Statut">' . esc_html( $smap[ $r->statut ] ?? $r->statut ) . '</td>';
			echo '<td data-label="Créé le">' . esc_html( date_i18n( 'd/m/Y', strtotime( $r->cree_le ) ) ) . '</td>';
			echo '<td data-label="Actions">';
			if ( 'attente' === $r->statut ) {
				echo '<form method="post" style="display:inline-block;margin-right:4px;">';
				wp_nonce_field( 'cfv_admin' );
				echo '<input type="hidden" name="vid" value="' . (int) $r->id . '">';
				if ( $r->checkout_id ) {
					echo '<button name="cfv_admin" value="verify" class="button button-primary button-small">Vérifier le paiement</button> ';
				}
				echo '<button name="cfv_admin" value="activate" class="button button-small" onclick="return confirm(\'Activer sans vérification (paiement reçu autrement) ?\');">Activer</button> ';
				echo '<button name="cfv_admin" value="cancel" class="button button-small">Annuler</button>';
				echo '</form>';
			} else {
				echo '—';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
