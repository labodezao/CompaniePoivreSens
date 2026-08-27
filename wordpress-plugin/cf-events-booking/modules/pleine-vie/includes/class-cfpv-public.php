<?php
/**
 * Programme Pleine Vie — formulaire public d'inscription.
 * Shortcode : [cf_pleine_vie_inscription]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Public {

	/* ── Formulaire [cf_pleine_vie_inscription] ───────────────────── */
	public static function form_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'titre'  => 'S\'inscrire au Programme Pleine Vie',
			'bouton' => 'Envoyer ma demande',
		], $atts, 'cf_pleine_vie_inscription' );

		ob_start();
		if ( isset( $_GET['cfpv_ok'] ) ) {
			echo '<div class="cfpv-notice" style="background:#f0fdf4;border:1px solid #86efac;padding:16px 18px;border-radius:8px;max-width:520px;">'
				. 'Merci ! Ta demande est bien enregistrée. Tu reçois un email de confirmation, et je reviens vers toi très vite. 🌿</div>';
			return ob_get_clean();
		}
		?>
		<form method="post" class="cfpv-form" style="max-width:520px;">
			<?php wp_nonce_field( 'cfpv_inscription', 'cfpv_nonce' ); ?>
			<h3 style="margin-top:0;"><?php echo esc_html( $atts['titre'] ); ?></h3>
			<div style="display:flex;gap:10px;flex-wrap:wrap;">
				<p style="flex:1;min-width:180px;"><label>Prénom *<br><input type="text" name="cfpv_prenom" required style="width:100%;padding:10px;"></label></p>
				<p style="flex:1;min-width:180px;"><label>Nom<br><input type="text" name="cfpv_nom" style="width:100%;padding:10px;"></label></p>
			</div>
			<p><label>Email *<br><input type="email" name="cfpv_email" required style="width:100%;padding:10px;"></label></p>
			<p><label>Téléphone<br><input type="tel" name="cfpv_tel" style="width:100%;padding:10px;"></label></p>
			<p><label>Ton intention / ta situation en quelques mots<br><textarea name="cfpv_message" rows="4" style="width:100%;padding:10px;"></textarea></label></p>
			<?php /* Pot de miel anti-spam : masqué aux humains, rempli par les bots */ ?>
			<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1">
				<label>Ne pas remplir<input type="text" name="cfpv_website" tabindex="-1" autocomplete="off"></label>
			</div>
			<input type="hidden" name="cfpv_ts" value="<?php echo (int) time(); ?>">
			<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:13px 28px;border-radius:6px;cursor:pointer;"><?php echo esc_html( $atts['bouton'] ); ?></button>
			<p style="font-size:12px;color:#888;margin-top:10px;">Tes informations restent confidentielles et servent uniquement à traiter ta demande.</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ── Traitement du formulaire (hook init) ─────────────────────── */
	public static function handle_form() {
		if ( empty( $_POST['cfpv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfpv_nonce'] ) ), 'cfpv_inscription' ) ) {
			return;
		}

		// Anti-spam : pot de miel rempli, ou formulaire soumis en < 2 s → bot.
		if ( ! empty( $_POST['cfpv_website'] ) ) {
			return;
		}
		$ts = isset( $_POST['cfpv_ts'] ) ? absint( $_POST['cfpv_ts'] ) : 0;
		if ( $ts && ( time() - $ts ) < 2 ) {
			return;
		}

		// Anti-flood : une soumission par IP toutes les 30 s max.
		$ip_key = 'cfpv_throttle_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( get_transient( $ip_key ) ) {
			wp_safe_redirect( add_query_arg( 'cfpv_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}
		set_transient( $ip_key, 1, 30 );

		$id = CFPV_Registrations::add( [
			'prenom'    => wp_unslash( $_POST['cfpv_prenom'] ?? '' ),
			'nom'       => wp_unslash( $_POST['cfpv_nom'] ?? '' ),
			'email'     => wp_unslash( $_POST['cfpv_email'] ?? '' ),
			'telephone' => wp_unslash( $_POST['cfpv_tel'] ?? '' ),
			'message'   => wp_unslash( $_POST['cfpv_message'] ?? '' ),
			'source'    => 'formulaire',
		] );
		if ( is_wp_error( $id ) || ! $id ) {
			return; // email invalide : on ne redirige pas, le champ required rejouera
		}
		$row = CFPV_Registrations::get( $id );
		if ( $row ) {
			CFPV_Emails::send_ack( $row );
			CFPV_Emails::notify_admin( $row );
		}
		wp_safe_redirect( add_query_arg( 'cfpv_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}
}
