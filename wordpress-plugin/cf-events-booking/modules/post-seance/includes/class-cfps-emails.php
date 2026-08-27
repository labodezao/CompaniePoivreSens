<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPS_Emails {

	/* ── Internals ───────────────────────────────────────────── */
	private static function settings() {
		return get_option( 'cfps_settings', [] );
	}

	private static function headers( $s ) {
		$name  = $s['from_name']  ?? get_bloginfo( 'name' );
		$email = $s['from_email'] ?? get_option( 'admin_email' );
		return [
			'Content-Type: text/html; charset=UTF-8',
			"From: {$name} <{$email}>",
		];
	}

	/* Signature HMAC permanente (un nonce WP expirerait en 24 h,
	   or le lien de désinscription doit rester valable — RGPD) */
	private static function unsub_signature( $email ) {
		return hash_hmac( 'sha256', 'cfps_unsub|' . strtolower( trim( $email ) ), wp_salt( 'auth' ) );
	}

	private static function unsub_url( $email ) {
		return add_query_arg( [
			'cfps_action' => 'unsubscribe',
			'cfps_email'  => rawurlencode( $email ),
			'cfps_nonce'  => self::unsub_signature( $email ),
		], home_url( '/' ) );
	}

	private static function send( $to, $subject, $html_body, $unsub_email = '' ) {
		$s = self::settings();

		$footer = '';
		if ( $unsub_email ) {
			$footer = '<p style="font-size:12px;color:#aaa;border-top:1px solid #eee;padding-top:12px;margin-top:32px;">'
				. '<a href="' . esc_url( self::unsub_url( $unsub_email ) ) . '" style="color:#aaa;text-decoration:underline;">Me désinscrire de cette séquence</a></p>';
		}

		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
			. '<style>body{font-family:Georgia,serif;color:#2c2c2c;max-width:600px;margin:0 auto;padding:30px 20px;font-size:16px;line-height:1.8}p{margin:0 0 1.2em}a{color:#5a3e6b}</style>'
			. '</head><body>'
			. $html_body
			. $footer
			. '</body></html>';

		return wp_mail( $to, $subject, $html, self::headers( $s ) );
	}

	/* ── Email 1 — J+1 — Le soin ─────────────────────────────── */
	public static function send_step1( $row ) {
		$prenom = esc_html( $row->prenom );

		$body = '<p>Bonjour ' . $prenom . ',</p>'
			. '<p>Hier, quelque chose a bougé. Ça continue de travailler les jours qui viennent, souvent en dehors du mental. Vous n’avez rien à analyser, rien à forcer.</p>'
			. '<p>Prenez soin de vous : de l’eau, du sommeil, de la marche. Si un mot, une image ou une émotion remonte, notez-le sans l’interpréter.</p>'
			. '<p>Je reste là si besoin.</p>'
			. '<p>— Ewen</p>';

		return self::send( $row->email, 'Après hier —', $body, $row->email );
	}

	/* ── Email 2 — J+7 — Les nouvelles ──────────────────────── */
	public static function send_step2( $row, $article_url = '' ) {
		$s      = self::settings();
		$prenom = esc_html( $row->prenom );
		$wa_num = preg_replace( '/[^0-9]/', '', $s['whatsapp_num'] ?? '33783031670' );
		$wa_url = 'https://wa.me/' . $wa_num;

		$article_block = '';
		if ( $article_url ) {
			$article_block = '<p>Pour aller plus loin, j’ai écrit quelque chose qui correspond à ce qu’on a traversé ensemble : '
				. '<a href="' . esc_url( $article_url ) . '">lire l’article →</a></p>';
		}

		$body = '<p>Bonjour ' . $prenom . ',</p>'
			. '<p>Quelques jours ont passé depuis notre séance. Parfois c’est ténu — une réaction différente, un souvenir, un apaisement discret ; parfois c’est plus net.</p>'
			. '<p>Prenez un instant : qu’est-ce qui s’est déposé depuis ? Répondez-moi même en un mot, ça m’aide à vous accompagner.</p>'
			. '<p>Une séance ouvre une porte. Ce qui se tient derrière demande parfois un peu plus de temps — on en reparlera si vous le sentez.</p>'
			. $article_block
			. '<p>Vous pouvez aussi m’écrire directement sur <a href="' . esc_url( $wa_url ) . '" style="color:#25D366;">WhatsApp</a> si c’est plus simple.</p>'
			. '<p>— Ewen</p>';

		return self::send( $row->email, 'Qu’est-ce qui a bougé ?', $body, $row->email );
	}

	/* ── Email 3 — J+14 — S'exprimer, pas « produire un témoignage » ── */
	/*
	 * Principe (revu) : on invite à s'exprimer, on n'exige jamais un
	 * témoignage publiable. Deux portes proposées, sans hiérarchie dans le
	 * texte — Google est nommé en premier car c'est l'option la plus utile à
	 * Ewen et la plus crédible pour un lecteur externe (avis non contrôlé
	 * par le praticien), mais rien n'indique qu'une porte est « mieux »
	 * qu'une autre pour la personne qui reçoit l'email.
	 *
	 * Le CTA vers un formulaire de témoignage écrit sur le site a été retiré
	 * (Ewen préfère renvoyer vers Google puis faire lui-même le copier-coller
	 * des avis sur /temoignages/). $t_url / temoignage_url reste lu depuis
	 * les réglages pour compat arrière mais n'est plus utilisé ici.
	 */
	public static function send_step3( $row ) {
		$prenom  = esc_html( $row->prenom );
		$s       = self::settings();
		$g_url   = ! empty( $s['google_review_url'] ) ? $s['google_review_url'] : CFEB_GOOGLE_REVIEW_URL;
		$wa_num  = preg_replace( '/[^0-9]/', '', $s['whatsapp_num'] ?? '33783031670' );
		$wa_url  = 'https://wa.me/' . $wa_num;

		$cta_google = '<p style="text-align:center;margin:20px 0 8px;"><a href="' . esc_url( $g_url ) . '" style="background:#5a3e6b;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;">★ Laisser un avis Google (1 min)</a></p>';

		$body = '<p>Bonjour ' . $prenom . ',</p>'
			. '<p>Beaucoup de gens hésitent à franchir le pas simplement parce qu’ils ignorent ce que sont les constellations — c’était peut-être votre cas avant de venir.</p>'
			. '<p>Je ne vous demande pas un témoignage — juste, si le cœur vous en dit, de dire ce qui a été vrai pour vous.</p>'
			. $cta_google
			. '<p>Ou plus simplement, un message vocal ou écrit sur <a href="' . esc_url( $wa_url ) . '" style="color:#25D366;">WhatsApp</a> — juste pour me le dire, sans que ce soit publié nulle part.</p>'
			. '<p>Merci du fond du cœur, quel que soit votre choix.</p>'
			. '<p>— Ewen</p>';

		return self::send( $row->email, 'Je peux vous demander quelque chose ?', $body, $row->email );
	}

	/* ── Email 4 — notification admin (GO/NO-GO) ─────────────── */
	public static function notify_admin_step4( $row ) {
		$s           = self::settings();
		$admin_email = $s['admin_email'] ?? get_option( 'admin_email' );
		$prenom      = esc_html( $row->prenom );
		$date_fr     = date_i18n( 'j F Y', strtotime( $row->date_seance ) );
		$theme_label = CFPS_Sequence::theme_labels()[ $row->theme_slug ] ?? ( 'Thème : ' . esc_html( $row->theme_slug ?: 'non renseigné' ) );

		$go_url = add_query_arg( [
			'cfps_action' => 'go',
			'cfps_id'     => (int) $row->id,
			'cfps_token'  => $row->step_4_token,
		], home_url( '/' ) );

		$nogo_url = add_query_arg( [
			'cfps_action' => 'nogo',
			'cfps_id'     => (int) $row->id,
			'cfps_token'  => $row->step_4_token,
		], home_url( '/' ) );

		$draft_text = "Bonjour {$prenom},\n\nJe repense à votre séance du {$date_fr}. Une constellation, c’est une porte : on voit, on ressent, on comprend quelque chose de sa tribu. Mais voir n’est pas encore traverser.\n\nCe qui transforme durablement, c’est d’accompagner ce qui s’est ouvert dans le temps — le corps, les émotions, les liens — jusqu’à ce que ça tienne.\n\nC’est tout le sens de Pleine Vie : un mois pour ne pas seulement entrevoir, mais intégrer. Si vous sentez que c’est votre moment, dites-le moi — on prend 20 minutes ensemble, tranquillement, sans engagement.\n\n— Ewen";

		$subject = '[CF Post-Séance] GO/NO-GO — ' . $prenom . ' (séance du ' . $date_fr . ')';

		$body = '<h2 style="color:#5a3e6b;font-size:18px;">Validation requise : envoyer l’invitation ?</h2>'
			. '<table style="border-collapse:collapse;width:100%;margin-bottom:20px;">'
			. '<tr><td style="padding:6px 12px;font-weight:bold;color:#555;">Personne</td><td style="padding:6px 12px;">' . $prenom . ' &lt;' . esc_html( $row->email ) . '&gt;</td></tr>'
			. '<tr style="background:#f9f6fb;"><td style="padding:6px 12px;font-weight:bold;color:#555;">Séance</td><td style="padding:6px 12px;">' . $date_fr . '</td></tr>'
			. '<tr><td style="padding:6px 12px;font-weight:bold;color:#555;">Thème</td><td style="padding:6px 12px;">' . esc_html( $theme_label ) . '</td></tr>'
			. '<tr style="background:#f9f6fb;"><td style="padding:6px 12px;font-weight:bold;color:#555;">Lien expire</td><td style="padding:6px 12px;">' . esc_html( $row->step_4_expires ) . '</td></tr>'
			. '</table>'
			. '<h3 style="font-size:15px;color:#333;margin-bottom:8px;">Brouillon de l’email :</h3>'
			. '<div style="background:#f9f6fb;border-left:3px solid #5a3e6b;padding:16px 20px;border-radius:4px;margin-bottom:24px;">'
			. nl2br( esc_html( $draft_text ) )
			. '</div>'
			. '<p style="font-size:13px;color:#888;">Chaque lien ouvre une page de confirmation : rien ne part sans un clic volontaire.</p>'
			. '<p style="text-align:center;margin:30px 0;">'
			. '<a href="' . esc_url( $go_url ) . '" style="background:#2d8a4e;color:#fff;padding:13px 30px;border-radius:5px;text-decoration:none;font-size:15px;margin-right:16px;display:inline-block;">✅ GO — Envoyer</a>'
			. '<a href="' . esc_url( $nogo_url ) . '" style="background:#c0392b;color:#fff;padding:13px 30px;border-radius:5px;text-decoration:none;font-size:15px;display:inline-block;">❌ NO-GO — Ignorer</a>'
			. '</p>';

		$headers = self::headers( $s );
		return wp_mail( $admin_email, $subject, '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:Georgia,serif;color:#2c2c2c;max-width:640px;margin:0 auto;padding:30px 20px;font-size:15px;line-height:1.7;">' . $body . '</body></html>', $headers );
	}

	/* ── Récupère et valide la ligne par token (constant-time) ── */
	private static function get_row_by_token( int $id, string $token ) {
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id
		) );

		if ( ! $row || empty( $row->step_4_token ) || ! hash_equals( $row->step_4_token, $token ) ) {
			return null;
		}
		return $row;
	}

	/* ── Lien GO : envoie l’email 4 au client ────────────────── */
	private static function handle_go( int $id, string $token ) {
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		$row = self::get_row_by_token( $id, $token );
		if ( ! $row ) {
			wp_die( 'Lien invalide ou déjà utilisé.', 'CF Post-Séance', [ 'response' => 403 ] );
		}
		// Refuser si NO-GO déjà cliqué (empêche l’envoi malgré un refus)
		if ( in_array( $row->statut, [ 'nogo', 'complete', 'desabonne' ], true ) ) {
			wp_die( 'Cette séquence est déjà clôturée (statut : ' . esc_html( $row->statut ) . ').', 'CF Post-Séance', [ 'response' => 409 ] );
		}
		if ( ! empty( $row->step_4_sent_at ) ) {
			wp_die( 'Cet email a déjà été envoyé le ' . esc_html( $row->step_4_sent_at ) . '.', 'CF Post-Séance' );
		}
		if ( ! empty( $row->step_4_expires ) && strtotime( $row->step_4_expires ) < time() ) {
			wp_die( 'Ce lien a expiré. Générez-en un nouveau depuis l’interface admin.', 'CF Post-Séance', [ 'response' => 410 ] );
		}

		// Revendication atomique : une seule requête peut gagner, même en concurrence
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET statut = 'complete', step_4_sent_at = %s, step_4_token = ''
			 WHERE id = %d AND step_4_token = %s AND statut = 'active' AND step_4_sent_at IS NULL",
			current_time( 'mysql' ), $id, $token
		) );
		if ( ! $claimed ) {
			wp_die( 'Cette action a déjà été traitée.', 'CF Post-Séance', [ 'response' => 409 ] );
		}

		$prenom  = esc_html( $row->prenom );
		$date_fr = date_i18n( 'j F Y', strtotime( $row->date_seance ) );

		$body = '<p>Bonjour ' . $prenom . ',</p>'
			. '<p>Je repense à votre séance du ' . $date_fr . '. Une constellation, c’est une porte : on voit, on ressent, on comprend quelque chose de sa tribu. Mais voir n’est pas encore traverser.</p>'
			. '<p>Ce qui transforme durablement, c’est d’accompagner ce qui s’est ouvert dans le temps — le corps, les émotions, les liens — jusqu’à ce que ça tienne.</p>'
			. '<p>C’est tout le sens de <em>Pleine Vie</em> : un mois pour ne pas seulement entrevoir, mais intégrer. Si vous sentez que c’est votre moment, dites-le moi — on prend 20 minutes ensemble, tranquillement, sans engagement.</p>'
			. '<p>— Ewen</p>';

		$sent = self::send( $row->email, 'Traverser, pas seulement entrevoir', $body );

		if ( $sent ) {
			wp_die( '✅ Email envoyé à ' . esc_html( $row->prenom ) . ' (' . esc_html( $row->email ) . ').', 'CF Post-Séance — GO', [ 'response' => 200 ] );
		} else {
			// Échec d’envoi : restaurer l’état pour permettre une nouvelle tentative
			$wpdb->update( $table, [
				'statut'         => 'active',
				'step_4_sent_at' => null,
				'step_4_token'   => $token,
			], [ 'id' => $id ] );
			wp_die( '❌ Échec d’envoi. Vérifiez la configuration email WordPress (wp_mail), puis recliquez sur le lien.', 'CF Post-Séance', [ 'response' => 500 ] );
		}
	}

	/* ── Lien NO-GO : clôture la séquence ────────────────────── */
	private static function handle_nogo( int $id, string $token ) {
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		$row = self::get_row_by_token( $id, $token );
		if ( ! $row ) {
			wp_die( 'Lien invalide.', 'CF Post-Séance', [ 'response' => 403 ] );
		}
		if ( ! empty( $row->step_4_expires ) && strtotime( $row->step_4_expires ) < time() ) {
			wp_die( 'Ce lien a expiré.', 'CF Post-Séance', [ 'response' => 410 ] );
		}

		// Revendication atomique : brûle le token, une seule requête peut gagner
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET statut = 'nogo', step_4_token = ''
			 WHERE id = %d AND step_4_token = %s AND statut = 'active'",
			$id, $token
		) );
		if ( ! $claimed ) {
			wp_die( 'Cette séquence est déjà clôturée.', 'CF Post-Séance', [ 'response' => 409 ] );
		}

		wp_die( '✅ Séquence clôturée pour ' . esc_html( $row->prenom ) . '. Aucun email envoyé.', 'CF Post-Séance — NO-GO', [ 'response' => 200 ] );
	}

	/* ── Lien de désinscription ───────────────────────────────── */
	private static function handle_unsubscribe( string $email, string $nonce ) {
		if ( ! hash_equals( self::unsub_signature( $email ), $nonce ) ) {
			wp_die( 'Lien de désinscription invalide.', 'CF Post-Séance', [ 'response' => 403 ] );
		}
		CFPS_Sequence::unsubscribe( $email );
		wp_die( 'Vous avez bien été désabonné(e) de la séquence post-séance. Bonne continuation.', 'Désinscription confirmée', [ 'response' => 200 ] );
	}

	/* ── Page de confirmation (anti-prefetch des scanners email) ─
	   Les scanners (Outlook SafeLinks, Gmail, antivirus) préchargent
	   les liens en GET : aucune action ne doit s’exécuter sur GET. */
	private static function render_confirm_page( string $action ) {
		$labels = [
			'go'          => [ 'Envoyer l’invitation ?', 'Vous allez envoyer l’email « Pleine Vie » à cette personne.', '✅ Confirmer l’envoi' ],
			'nogo'        => [ 'Clôturer sans envoyer ?', 'La séquence sera fermée et aucun email ne sera envoyé.', '❌ Confirmer la clôture' ],
			'unsubscribe' => [ 'Se désinscrire ?', 'Vous ne recevrez plus les emails de suivi post-séance.', 'Confirmer ma désinscription' ],
		];
		list( $title, $text, $button ) = $labels[ $action ];

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
			. '<title>' . esc_html( $title ) . '</title></head>'
			. '<body style="font-family:Georgia,serif;background:#f6f3f9;margin:0;padding:60px 20px;">'
			. '<div style="max-width:440px;margin:0 auto;background:#fff;border-radius:10px;padding:36px 40px;box-shadow:0 2px 10px rgba(0,0,0,.08);text-align:center;">'
			. '<h1 style="color:#5a3e6b;font-size:20px;margin:0 0 12px;">' . esc_html( $title ) . '</h1>'
			. '<p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 28px;">' . esc_html( $text ) . '</p>'
			. '<form method="post"><input type="hidden" name="cfps_confirm" value="1">'
			. '<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:13px 32px;border-radius:6px;font-size:15px;cursor:pointer;">' . esc_html( $button ) . '</button>'
			. '</form></div></body></html>';
		exit;
	}

	/* ── Point d’entrée (hook init) ──────────────────────────── */
	public static function handle_action_links() {
		// Ne pas interférer avec REST, cron, AJAX ou l’admin
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$action = sanitize_key( $_GET['cfps_action'] ?? '' );
		if ( ! in_array( $action, [ 'go', 'nogo', 'unsubscribe' ], true ) ) return;

		// GET = page de confirmation ; l’action réelle exige un POST volontaire
		$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		if ( 'POST' !== $method || empty( $_POST['cfps_confirm'] ) ) {
			self::render_confirm_page( $action );
		}

		switch ( $action ) {
			case 'go':
				$id    = absint( $_GET['cfps_id'] ?? 0 );
				$token = sanitize_text_field( $_GET['cfps_token'] ?? '' );
				if ( $id && $token ) self::handle_go( $id, $token );
				break;

			case 'nogo':
				$id    = absint( $_GET['cfps_id'] ?? 0 );
				$token = sanitize_text_field( $_GET['cfps_token'] ?? '' );
				if ( $id && $token ) self::handle_nogo( $id, $token );
				break;

			case 'unsubscribe':
				$email = sanitize_email( $_GET['cfps_email'] ?? '' );
				$nonce = sanitize_text_field( $_GET['cfps_nonce'] ?? '' );
				if ( $email && $nonce ) self::handle_unsubscribe( $email, $nonce );
				break;
		}
	}
}
