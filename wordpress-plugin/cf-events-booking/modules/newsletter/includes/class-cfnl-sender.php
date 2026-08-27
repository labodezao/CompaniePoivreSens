<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Sender {

	/* ── Cron : draine la file d'attente par lots ─────────────────── */
	public static function run_cron() {
		global $wpdb;

		// 1) Déclenche les campagnes programmées dont l'heure est arrivée
		if ( class_exists( 'CFNL_Campaigns' ) ) {
			foreach ( CFNL_Campaigns::due_scheduled() as $cid ) {
				CFNL_Campaigns::enqueue( (int) $cid );
			}
			// 1bis) A/B : décide le gagnant et met le reste en file
			CFNL_Campaigns::maybe_decide_ab();
		}
		// 1ter) Séquence de bienvenue multi-étapes
		if ( class_exists( 'CFNL_Automations' ) ) {
			CFNL_Automations::process_drip();
		}

		$settings = get_option( 'cfnl_settings', [] );
		$batch    = max( 1, (int) ( $settings['batch_size'] ?? 30 ) );
		$cap      = max( 1, (int) ( $settings['daily_cap'] ?? 280 ) );

		// Plafond quotidien (anti-blocage OVH / quota relais gratuit)
		$sent_today_key = 'cfnl_sent_' . gmdate( 'Y-m-d' );
		$sent_today     = (int) get_option( $sent_today_key, 0 );
		if ( $sent_today >= $cap ) {
			return;
		}
		$batch = min( $batch, $cap - $sent_today );

		$queue = $wpdb->prefix . 'cfnl_queue';
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$queue} WHERE statut = 'pending' ORDER BY id ASC LIMIT %d", $batch
		) );
		if ( empty( $rows ) ) {
			self::finalize_completed_campaigns();
			return;
		}

		$sent = 0;
		foreach ( $rows as $q ) {
			$camp = CFNL_Campaigns::get( $q->campaign_id );
			$sub  = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM " . CFNL_Subscribers::table() . " WHERE id = %d", (int) $q->subscriber_id
			) );
			if ( ! $camp || ! $sub || 'subscribed' !== $sub->statut ) {
				$wpdb->update( $queue, [ 'statut' => 'skipped', 'sent_at' => current_time( 'mysql' ) ], [ 'id' => (int) $q->id ] );
				continue;
			}

			$ok = self::send_one( $camp, $sub, $q->variant ?? '' );
			$wpdb->update( $queue, [
				'statut'  => $ok ? 'sent' : 'failed',
				'sent_at' => current_time( 'mysql' ),
			], [ 'id' => (int) $q->id ] );

			if ( $ok ) {
				$sent++;
				$wpdb->query( $wpdb->prepare(
					"UPDATE " . CFNL_Campaigns::table() . " SET envoyes = envoyes + 1 WHERE id = %d", (int) $camp->id
				) );
			}
		}

		if ( $sent > 0 ) {
			update_option( $sent_today_key, $sent_today + $sent, false );
		}
		self::finalize_completed_campaigns();
	}

	/* ── Marque « sent » les campagnes dont la file est vide ──────── */
	private static function finalize_completed_campaigns() {
		global $wpdb;
		$queue = $wpdb->prefix . 'cfnl_queue';
		$camps = $wpdb->get_results( "SELECT id, ab_enabled, ab_winner FROM " . CFNL_Campaigns::table() . " WHERE statut = 'sending'" );
		foreach ( $camps as $c ) {
			// Campagne A/B réelle : ne pas clôturer tant que le gagnant n'a pas
			// été décidé. On ne bloque que si une décision est effectivement en
			// attente (ab_decide_at renseigné) — sinon (ex. objet B vide, envoi
			// standard) la campagne peut se clôturer normalement.
			if ( ! empty( $c->ab_enabled ) && '' === (string) $c->ab_winner && ! empty( $c->ab_decide_at ) ) {
				continue;
			}
			$remaining = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND statut = 'pending'", (int) $c->id
			) );
			if ( 0 === $remaining ) {
				$wpdb->update( CFNL_Campaigns::table(), [ 'statut' => 'sent' ], [ 'id' => (int) $c->id ] );
			}
		}
	}

	/* ── Envoi d'un email de test (rendu réel, à soi-même) ────────── */
	public static function send_test_campaign( $campaign_id, $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'bad_email', 'Adresse invalide.' );
		}
		$camp = CFNL_Campaigns::get( $campaign_id );
		if ( ! $camp ) {
			return new WP_Error( 'no_camp', 'Campagne introuvable.' );
		}
		// Abonné fictif pour le rendu (les liens de tracking pointeront dans le vide, sans effet)
		$fake = (object) [ 'id' => 0, 'email' => $email, 'prenom' => 'Test', 'token' => 'test-preview' ];
		$camp = clone $camp;
		$camp->objet = '[TEST] ' . $camp->objet;
		return self::send_one( $camp, $fake ) ? true : new WP_Error( 'send_failed', 'Échec de l\'envoi (vérifie le SMTP).' );
	}

	/* ── Envoi d'un email individuel (tracking inclus) ────────────── */
	private static function send_one( $camp, $sub, $variant = '' ) {
		$settings = get_option( 'cfnl_settings', [] );
		$from_name  = $settings['from_name']  ?? get_bloginfo( 'name' );
		$from_email = $settings['from_email'] ?? get_option( 'admin_email' );

		// A/B : objet selon la variante attribuée à ce destinataire
		$objet = $camp->objet;
		if ( 'b' === $variant && ! empty( $camp->objet_b ) ) {
			$objet = $camp->objet_b;
		}

		$prenom = $sub->prenom ?: '';
		$nom    = isset( $sub->nom ) ? (string) $sub->nom : '';
		$vars   = [
			'{{prenom}}'                 => esc_html( $prenom ),
			'{prenom}'                   => esc_html( $prenom ),
			'{{subscriber:firstname}}'   => esc_html( $prenom ),
			'{{nom}}'                    => esc_html( $nom ),
			'{{email}}'                  => esc_html( $sub->email ),
		];
		$objet = strtr( $objet, [ '{{prenom}}' => $prenom, '{prenom}' => $prenom, '{{nom}}' => $nom ] );
		$body  = strtr( $camp->corps, $vars );

		// Réécriture des liens pour le suivi des clics
		$body = self::rewrite_links( $body, $camp->id, $sub->id, $sub->token, $camp );

		// Lien de désinscription
		$unsub  = CFNL_Public::action_url( 'unsub', $sub->token );
		$footer = '<hr style="border:none;border-top:1px solid #eee;margin:32px 0 12px;">'
			. '<p style="font-size:12px;color:#aaa;text-align:center;">'
			. '<a href="' . esc_url( $unsub ) . '" style="color:#aaa;">Me désinscrire</a>'
			. '</p>';

		// Pixel de suivi d'ouverture
		$pixel = '<img src="' . esc_url( CFNL_Public::open_url( $camp->id, $sub->id, $sub->token ) )
			. '" width="1" height="1" alt="" style="display:none;">';

		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="font-family:Georgia,serif;color:#2c2c2c;max-width:600px;margin:0 auto;padding:24px 18px;font-size:16px;line-height:1.75;">'
			. self::format_body( $body ) . $footer . $pixel
			. '</body></html>';

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
			'List-Unsubscribe: <' . $unsub . '>',
		];

		return wp_mail( $sub->email, $objet, $html, $headers );
	}

	/* ── Formatage : paragraphes auto SEULEMENT pour du texte brut ──
	   Un HTML déjà structuré (collé depuis MailPoet : tableaux, divs…)
	   est laissé tel quel — wpautop casserait sa mise en page. */
	public static function format_body( $body ) {
		if ( preg_match( '/<(p|table|div|ul|ol|h[1-6]|blockquote)[\s>]/i', $body ) ) {
			return $body;
		}
		return wpautop( $body );
	}

	/* ── Domaines dont les liens ne sont JAMAIS réécrits ───────────────
	   Le suivi des clics fabrique une adresse différente par destinataire
	   (elle contient son identifiant et son jeton). C'est sans conséquence
	   pour un lien qu'on ouvre seul, mais cela casse tout lien destiné à
	   être PARTAGÉ ou identique pour tout le monde — typiquement un lien
	   de visioconférence recollé dans le chat de la réunion.

	   Cette liste de base est toujours appliquée ; les domaines saisis
	   dans les réglages s'y ajoutent, ils ne la remplacent pas. On ne peut
	   donc pas rouvrir le problème en vidant le champ par mégarde. */
	const NEVER_TRACK = [
		'meet.google.com',
		'meet.jit.si',
		'jitsi.org',
		'zoom.us',
		'teams.microsoft.com',
		'teams.live.com',
		'whereby.com',
		'framatalk.org',
		'visio.numerique.gouv.fr',
		'livestorm.co',
		'bbb.',
		'webinaire',
		'calendly.com',
	];

	private static function never_track_domains() {
		$s     = get_option( 'cfnl_settings', [] );
		$extra = preg_split( '/[\r\n,]+/', (string) ( $s['track_exclude'] ?? '' ) );
		$extra = array_filter( array_map( 'trim', is_array( $extra ) ? $extra : [] ) );
		return array_merge( self::NEVER_TRACK, $extra );
	}

	/* Le lien doit-il rester tel quel pour tout le monde ? */
	private static function is_shareable_link( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		foreach ( self::never_track_domains() as $d ) {
			$d = strtolower( ltrim( trim( $d ), '.' ) );
			if ( '' === $d ) {
				continue;
			}
			// Correspondance sur le domaine ou l'un de ses sous-domaines
			if ( $host === $d || substr( $host, -strlen( '.' . $d ) ) === '.' . $d || false !== strpos( $host, $d ) ) {
				return true;
			}
		}
		return false;
	}

	/* ── Réécrit les href : UTM + tracker de clics ────────────────── */
	private static function rewrite_links( $html, $camp_id, $sub_id, $token, $camp = null ) {
		$s        = get_option( 'cfnl_settings', [] );
		$utm_on   = ! empty( $s['utm_enabled'] );
		$utm_src  = $s['utm_source'] ?? 'newsletter';
		$utm_med  = $s['utm_medium'] ?? 'email';
		$utm_camp = $camp ? sanitize_title( $camp->titre ?: ( 'campagne-' . $camp->id ) ) : 'campagne';
		// Suivi des clics : activé par défaut, mais débrayable en entier.
		$click_on = ! array_key_exists( 'click_tracking', $s ) || ! empty( $s['click_tracking'] );

		return preg_replace_callback(
			'/href="(https?:\/\/[^"]+)"/i',
			function ( $m ) use ( $camp_id, $sub_id, $token, $utm_on, $utm_src, $utm_med, $utm_camp, $click_on ) {
				$url = $m[1];
				// On ne réécrit pas les liens d'action internes (désinscription…)
				if ( false !== strpos( $url, 'cfnl_action=' ) ) {
					return $m[0];
				}
				// Balises UTM sur les liens internes du site (suivi analytics)
				if ( $utm_on && false !== strpos( $url, home_url() ) ) {
					$url = add_query_arg( [
						'utm_source'   => $utm_src,
						'utm_medium'   => $utm_med,
						'utm_campaign' => $utm_camp,
					], $url );
				}
				// Lien de visio ou lien à partager : il doit rester identique
				// pour tous les destinataires, donc pas de suivi de clic.
				if ( ! $click_on || self::is_shareable_link( $url ) ) {
					return 'href="' . esc_url( $url ) . '"';
				}
				return 'href="' . esc_url( CFNL_Public::click_url( $camp_id, $sub_id, $token, $url ) ) . '"';
			},
			$html
		);
	}
}
