<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Public {

	/* ── Construction des URLs d'action ───────────────────────────── */
	public static function action_url( $action, $token, $extra = [] ) {
		return add_query_arg( array_merge( [
			'cfnl_action' => $action,
			'cfnl_token'  => $token,
		], $extra ), home_url( '/' ) );
	}

	public static function open_url( $camp_id, $sub_id, $token ) {
		return add_query_arg( [
			'cfnl_action' => 'open',
			'cfnl_c'      => (int) $camp_id,
			'cfnl_s'      => (int) $sub_id,
			'cfnl_token'  => $token,
		], home_url( '/' ) );
	}

	public static function click_url( $camp_id, $sub_id, $token, $url ) {
		return add_query_arg( [
			'cfnl_action' => 'click',
			'cfnl_c'      => (int) $camp_id,
			'cfnl_s'      => (int) $sub_id,
			'cfnl_token'  => $token,
			'cfnl_u'      => rawurlencode( $url ),
		], home_url( '/' ) );
	}

	/* ── Shortcode [cf_newsletter] : formulaire d'inscription ─────── */
	public static function form_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'titre' => 'Recevoir la newsletter' ], $atts, 'cf_newsletter' );

		ob_start();
		$done = isset( $_GET['cfnl_ok'] ) ? sanitize_key( $_GET['cfnl_ok'] ) : '';
		?>
		<div class="cfnl-form-wrap" style="max-width:460px;">
			<?php if ( 'pending' === $done ) : ?>
				<p class="cfnl-notice" style="background:#f0fdf4;border:1px solid #86efac;padding:12px 16px;border-radius:8px;">Merci ! Un email de confirmation vient de t'être envoyé — clique sur le lien pour valider ton inscription.</p>
			<?php elseif ( 'sub' === $done ) : ?>
				<p class="cfnl-notice" style="background:#f0fdf4;border:1px solid #86efac;padding:12px 16px;border-radius:8px;">C'est fait, tu es bien inscrit·e. À très vite !</p>
			<?php else : ?>
				<h3><?php echo esc_html( $atts['titre'] ); ?></h3>
				<form method="post" class="cfnl-form">
					<?php wp_nonce_field( 'cfnl_subscribe', 'cfnl_nonce' ); ?>
					<p><input type="text" name="cfnl_prenom" placeholder="Prénom" style="width:100%;padding:10px;margin-bottom:8px;"></p>
					<p><input type="email" name="cfnl_email" placeholder="Ton email" required style="width:100%;padding:10px;margin-bottom:8px;"></p>
					<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:12px 24px;border-radius:6px;cursor:pointer;">Je m'inscris</button>
					<p style="font-size:12px;color:#888;margin-top:10px;">Inscription à double confirmation. Désinscription en un clic à tout moment.</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Traitement du formulaire (POST) ──────────────────────────── */
	public static function handle_form() {
		if ( empty( $_POST['cfnl_email'] ) || empty( $_POST['cfnl_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfnl_nonce'] ) ), 'cfnl_subscribe' ) ) {
			return;
		}
		$email  = sanitize_email( wp_unslash( $_POST['cfnl_email'] ) );
		$prenom = sanitize_text_field( wp_unslash( $_POST['cfnl_prenom'] ?? '' ) );

		// Un seul rythme de newsletter désormais : tous les 15 jours.
		$res = CFNL_Subscribers::add( $email, $prenom, '', 'quinzaine', 'formulaire' );
		if ( is_wp_error( $res ) ) {
			return;
		}

		$flag = 'sub';
		if ( in_array( $res['status'], [ 'new_pending' ], true ) || 'pending' === ( $res['statut'] ?? '' ) ) {
			self::send_confirmation( $email, $prenom, $res['token'] );
			$flag = 'pending';
		}

		wp_safe_redirect( add_query_arg( 'cfnl_ok', $flag, wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	/* ── Email de confirmation double opt-in ──────────────────────── */
	public static function send_confirmation( $email, $prenom, $token ) {
		$settings = get_option( 'cfnl_settings', [] );
		$from_name  = $settings['from_name']  ?? get_bloginfo( 'name' );
		$from_email = $settings['from_email'] ?? get_option( 'admin_email' );
		$url = self::action_url( 'confirm', $token );

		$body = '<p>Bonjour ' . esc_html( $prenom ) . ',</p>'
			. '<p>Merci de vouloir me lire ! Il ne reste qu\'une étape : confirme ton inscription en cliquant ci-dessous.</p>'
			. '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $url ) . '" style="background:#5a3e6b;color:#fff;padding:13px 30px;border-radius:6px;text-decoration:none;">Confirmer mon inscription</a></p>'
			. '<p style="font-size:13px;color:#888;">Si tu n\'es pas à l\'origine de cette demande, ignore simplement cet email.</p>';

		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:Georgia,serif;color:#2c2c2c;max-width:560px;margin:0 auto;padding:24px;font-size:16px;line-height:1.7;">' . $body . '</body></html>';

		wp_mail( $email, 'Confirme ton inscription', $html, [
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
		] );
	}

	/* ── Routeur des liens d'action (hook init) ───────────────────── */
	public static function handle_actions() {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$action = isset( $_GET['cfnl_action'] ) ? sanitize_key( $_GET['cfnl_action'] ) : '';
		if ( ! $action ) return;

		$token = isset( $_GET['cfnl_token'] ) ? sanitize_text_field( wp_unslash( $_GET['cfnl_token'] ) ) : '';

		switch ( $action ) {
			case 'open':
				self::track_open();
				break;
			case 'click':
				self::track_click( $token );
				break;
			case 'confirm':
				CFNL_Subscribers::confirm( $token );
				self::render_page( 'Inscription confirmée', 'Merci ! Ton inscription est bien confirmée. À très vite dans ta boîte mail.' );
				break;
			case 'unsub':
				self::handle_unsub( $token );
				break;
		}
	}

	/* ── Vérifie que le token correspond bien à l'abonné (anti-forge) ── */
	private static function valid_subscriber( $s, $token ) {
		if ( ! $s || ! $token ) return false;
		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare(
			"SELECT token FROM " . CFNL_Subscribers::table() . " WHERE id = %d", $s
		) );
		return $stored && hash_equals( $stored, $token );
	}

	/* ── Pixel d'ouverture (GIF 1x1 transparent) ──────────────────── */
	private static function track_open() {
		global $wpdb;
		$c     = absint( $_GET['cfnl_c'] ?? 0 );
		$s     = absint( $_GET['cfnl_s'] ?? 0 );
		$token = isset( $_GET['cfnl_token'] ) ? sanitize_text_field( wp_unslash( $_GET['cfnl_token'] ) ) : '';
		if ( $c && $s && self::valid_subscriber( $s, $token ) ) {
			$queue = $wpdb->prefix . 'cfnl_queue';
			$already = $wpdb->get_var( $wpdb->prepare(
				"SELECT opened_at FROM {$queue} WHERE campaign_id = %d AND subscriber_id = %d", $c, $s
			) );
			if ( null === $already || '' === $already ) {
				$wpdb->update( $queue, [ 'opened_at' => current_time( 'mysql' ) ], [ 'campaign_id' => $c, 'subscriber_id' => $s ] );
				$wpdb->query( $wpdb->prepare( "UPDATE " . CFNL_Campaigns::table() . " SET ouverts = ouverts + 1 WHERE id = %d", $c ) );
			}
			// Engagement : dernière activité de l'abonné
			$wpdb->update( CFNL_Subscribers::table(), [ 'last_activity' => current_time( 'mysql' ) ], [ 'id' => $s ] );
		}
		nocache_headers();
		header( 'Content-Type: image/gif' );
		// GIF transparent 1x1
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
	}

	/* ── Redirection avec suivi de clic ───────────────────────────── */
	private static function track_click( $token ) {
		global $wpdb;
		$c   = absint( $_GET['cfnl_c'] ?? 0 );
		$s   = absint( $_GET['cfnl_s'] ?? 0 );
		$url = isset( $_GET['cfnl_u'] ) ? esc_url_raw( urldecode( wp_unslash( $_GET['cfnl_u'] ) ) ) : '';

		// Ne compter le clic + rediriger que si le token correspond à l'abonné
		// (empêche l'open-redirect et le gonflage des stats par un tiers).
		$ok = ( $c && $s && self::valid_subscriber( $s, $token ) );
		if ( $ok ) {
			$queue = $wpdb->prefix . 'cfnl_queue';
			$wpdb->query( $wpdb->prepare( "UPDATE {$queue} SET clics = clics + 1 WHERE campaign_id = %d AND subscriber_id = %d", $c, $s ) );
			$wpdb->query( $wpdb->prepare( "UPDATE " . CFNL_Campaigns::table() . " SET clics = clics + 1 WHERE id = %d", $c ) );
			$wpdb->update( CFNL_Subscribers::table(), [ 'last_activity' => current_time( 'mysql' ) ], [ 'id' => $s ] );
		}
		// Redirection : uniquement vers du http(s) ET seulement si le lien est authentifié
		if ( $ok && $url && preg_match( '#^https?://#i', $url ) ) {
			wp_redirect( $url );
		} else {
			wp_safe_redirect( home_url( '/' ) );
		}
		exit;
	}

	/* ── Désinscription (page de confirmation anti-prefetch) ──────── */
	private static function handle_unsub( $token ) {
		$sub = CFNL_Subscribers::get_by_token( $token );
		if ( ! $sub ) {
			self::render_page( 'Lien invalide', 'Ce lien de désinscription n\'est pas valide.' );
		}
		$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		if ( 'POST' !== $method || empty( $_POST['cfnl_confirm'] ) ) {
			self::render_confirm_form( 'unsub', $token, 'Se désinscrire ?', 'Tu ne recevras plus la newsletter.', 'Confirmer ma désinscription' );
		}
		CFNL_Subscribers::unsubscribe( $token );
		self::render_page( 'Désinscription confirmée', 'Tu es bien désinscrit·e. Tu peux revenir quand tu veux.' );
	}

	/* ── Gabarits de page ─────────────────────────────────────────── */
	private static function render_confirm_form( $action, $token, $titre, $texte, $bouton ) {
		self::render_shell( $titre,
			'<p style="margin-bottom:22px;">' . esc_html( $texte ) . '</p>'
			. '<form method="post"><input type="hidden" name="cfnl_confirm" value="1">'
			. '<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:12px 28px;border-radius:6px;cursor:pointer;">' . esc_html( $bouton ) . '</button></form>'
		);
	}

	private static function render_page( $titre, $texte ) {
		self::render_shell( $titre, '<p>' . esc_html( $texte ) . '</p>' );
	}

	private static function render_shell( $titre, $html_inner ) {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $titre ) . '</title></head>'
			. '<body style="font-family:Georgia,serif;background:#f6f3f9;margin:0;padding:60px 20px;">'
			. '<div style="max-width:460px;margin:0 auto;background:#fff;border-radius:10px;padding:36px 40px;box-shadow:0 2px 10px rgba(0,0,0,.08);text-align:center;">'
			. '<h1 style="color:#5a3e6b;font-size:20px;margin:0 0 14px;">' . esc_html( $titre ) . '</h1>'
			. $html_inner
			. '</div></body></html>';
		exit;
	}
}
