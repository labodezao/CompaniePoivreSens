<?php
/**
 * Automatisations :
 *  - Email de bienvenue (autorépondeur à la confirmation d'inscription),
 *    puis séquence optionnelle en 2 étapes.
 *  - Nouveau texte dans la bibliothèque → campagne. C'est le déclencheur
 *    principal du rythme de la newsletter : on écrit un cadeau, il part.
 *  - Notification d'article (nouvel article publié → newsletter auto),
 *    laissée en place mais désactivée par défaut : l'activer en même temps
 *    que la précédente enverrait deux fois le même contenu.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Automations {

	/* ══ Email de bienvenue ═══════════════════════════════════════ */
	public static function send_welcome( $subscriber_row ) {
		$s = get_option( 'cfnl_settings', [] );
		if ( empty( $s['welcome_enabled'] ) || ! $subscriber_row ) {
			return;
		}
		// Démarre la séquence multi-étapes
		global $wpdb;
		$wpdb->update( CFNL_Subscribers::table(), [
			'drip_step'    => 1,
			'drip_last_at' => current_time( 'mysql' ),
		], [ 'id' => (int) $subscriber_row->id ] );
		$from_name  = $s['from_name']  ?? get_bloginfo( 'name' );
		$from_email = $s['from_email'] ?? get_option( 'admin_email' );

		$objet = str_replace( '{{prenom}}', $subscriber_row->prenom, ( $s['welcome_objet'] ?? 'Bienvenue !' ) );
		$corps = str_replace( '{{prenom}}', esc_html( $subscriber_row->prenom ), ( $s['welcome_corps'] ?? '' ) );

		$unsub  = CFNL_Public::action_url( 'unsub', $subscriber_row->token );
		$footer = '<hr style="border:none;border-top:1px solid #eee;margin:32px 0 12px;">'
			. '<p style="font-size:12px;color:#aaa;text-align:center;">'
			. '<a href="' . esc_url( $unsub ) . '" style="color:#aaa;">Me désinscrire</a></p>';

		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
			. '<body style="font-family:Georgia,serif;color:#2c2c2c;max-width:600px;margin:0 auto;padding:24px 18px;font-size:16px;line-height:1.75;">'
			. CFNL_Sender::format_body( $corps ) . $footer . '</body></html>';

		wp_mail( $subscriber_row->email, $objet, $html, [
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
			'List-Unsubscribe: <' . $unsub . '>',
		] );
	}

	/* ══ Séquence de bienvenue multi-étapes (drip) ═════════════════
	   Étape 1 = bienvenue (envoyée à la confirmation). Étapes 2 et 3
	   optionnelles, envoyées N jours après la précédente, via le cron. */
	public static function process_drip() {
		global $wpdb;
		$s = get_option( 'cfnl_settings', [] );

		foreach ( [ 2, 3 ] as $step ) {
			if ( empty( $s[ "welcome{$step}_enabled" ] ) ) {
				continue;
			}
			$days   = max( 1, (int) ( $s[ "welcome{$step}_days" ] ?? 3 ) );
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
			$rows   = $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . CFNL_Subscribers::table() . "
				 WHERE statut = 'subscribed' AND drip_step = %d AND drip_last_at IS NOT NULL AND drip_last_at <= %s
				 LIMIT 20",
				$step - 1, $cutoff
			) );
			foreach ( $rows as $sub ) {
				// Revendication atomique : un seul passage cron peut avancer l'étape
				$claimed = $wpdb->query( $wpdb->prepare(
					'UPDATE ' . CFNL_Subscribers::table() . ' SET drip_step = %d, drip_last_at = %s WHERE id = %d AND drip_step = %d',
					$step, current_time( 'mysql' ), (int) $sub->id, $step - 1
				) );
				if ( ! $claimed ) {
					continue;
				}
				self::send_drip_step( $sub, $s[ "welcome{$step}_objet" ] ?? '', $s[ "welcome{$step}_corps" ] ?? '' );
			}
		}
	}

	private static function send_drip_step( $sub, $objet, $corps ) {
		if ( '' === trim( $objet ) || '' === trim( $corps ) ) {
			return;
		}
		$s = get_option( 'cfnl_settings', [] );
		$from_name  = $s['from_name']  ?? get_bloginfo( 'name' );
		$from_email = $s['from_email'] ?? get_option( 'admin_email' );
		$corps  = str_replace( '{{prenom}}', esc_html( $sub->prenom ), $corps );
		$objet  = str_replace( '{{prenom}}', $sub->prenom, $objet );
		$unsub  = CFNL_Public::action_url( 'unsub', $sub->token );
		$footer = '<hr style="border:none;border-top:1px solid #eee;margin:32px 0 12px;">'
			. '<p style="font-size:12px;color:#aaa;text-align:center;">'
			. '<a href="' . esc_url( $unsub ) . '" style="color:#aaa;">Me désinscrire</a></p>';
		$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
			. '<body style="font-family:Georgia,serif;color:#2c2c2c;max-width:600px;margin:0 auto;padding:24px 18px;font-size:16px;line-height:1.75;">'
			. CFNL_Sender::format_body( $corps ) . $footer . '</body></html>';
		wp_mail( $sub->email, $objet, $html, [
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
			'List-Unsubscribe: <' . $unsub . '>',
		] );
	}

	/* ══ Nouveau texte dans la bibliothèque ═══════════════════════════
	   Déclencheur principal du rythme de la newsletter : dès qu'un texte
	   apparaît dans la Bibliothèque — écrit dans l'admin, ou déployé comme
	   fichier avec le plugin — une campagne est créée pour lui.

	   Le suivi se fait par une liste de clés déjà vues. Au tout premier
	   passage, cette liste n'existe pas : on l'initialise avec l'existant
	   SANS rien envoyer, sinon les textes déjà en place partiraient tous
	   d'un coup. C'est le point délicat de ce mécanisme. */
	const OPTION_SEEN = 'cfnl_library_seen';

	public static function seen_keys() {
		$v = get_option( self::OPTION_SEEN, null );
		return is_array( $v ) ? $v : null;
	}

	/* Toutes les clés actuellement présentes, toutes collections confondues. */
	private static function current_keys() {
		$keys = [];
		if ( ! class_exists( 'CFNL_Library' ) ) {
			return $keys;
		}
		foreach ( array_keys( CFNL_Library::collections() ) as $col ) {
			foreach ( CFNL_Library::editions( $col ) as $ed ) {
				$keys[] = $col . '|' . $ed['key'];
			}
		}
		return $keys;
	}

	/* Prend acte de l'existant sans rien envoyer. Appelé au premier passage
	   et à l'activation du module. */
	public static function seed_seen() {
		update_option( self::OPTION_SEEN, self::current_keys() );
	}

	/* Passe en revue la bibliothèque et traite ce qui est nouveau.
	   Appelée par le cron, et juste après une création dans l'admin pour
	   que la campagne apparaisse tout de suite. */
	public static function check_new_library() {
		if ( ! class_exists( 'CFNL_Library' ) || ! class_exists( 'CFNL_Campaigns' ) ) {
			return 0;
		}
		$seen = self::seen_keys();
		if ( null === $seen ) {
			// Premier passage : on enregistre l'existant, sans envoyer.
			self::seed_seen();
			return 0;
		}

		$s = get_option( 'cfnl_settings', [] );
		$current = self::current_keys();
		$new     = array_values( array_diff( $current, $seen ) );

		// On mémorise AVANT de créer les campagnes : si la création échoue
		// pour l'une d'elles, on préfère un envoi manqué à un envoi répété.
		update_option( self::OPTION_SEEN, $current );

		if ( empty( $s['libnotif_enabled'] ) || ! $new ) {
			return 0;
		}

		$fait = 0;
		foreach ( $new as $ref ) {
			list( $col, $key ) = array_pad( explode( '|', $ref, 2 ), 2, '' );
			$ed = CFNL_Library::get( $col, $key );
			if ( ! $ed || '' === trim( (string) $ed['corps'] ) ) {
				continue;
			}
			$id = CFNL_Campaigns::save( [
				'titre' => $ed['titre'],
				'objet' => $ed['objet'],
				'corps' => $ed['corps'],
				'cible' => 'both',
			] );
			if ( ! $id ) {
				continue;
			}
			if ( 'send' === ( $s['libnotif_mode'] ?? 'draft' ) ) {
				CFNL_Campaigns::enqueue( $id );
			}
			$fait++;
		}
		return $fait;
	}

	/* ══ Notification d'article ═══════════════════════════════════ */
	public static function on_post_published( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return; // seulement au passage en « publié »
		}
		if ( 'post' !== $post->post_type ) {
			return; // articles de blog uniquement
		}
		$s = get_option( 'cfnl_settings', [] );
		if ( empty( $s['postnotif_enabled'] ) ) {
			return;
		}
		// Évite les doublons si le hook se déclenche plusieurs fois
		if ( get_post_meta( $post->ID, '_cfnl_notified', true ) ) {
			return;
		}
		update_post_meta( $post->ID, '_cfnl_notified', 1 );

		$objet = str_replace( '{{post_title}}', $post->post_title, ( $s['postnotif_objet'] ?? 'Nouvel article : {{post_title}}' ) );
		$corps = self::build_post_body( $post );

		// Un seul rythme de newsletter (tous les 15 jours) : chaque article
		// publié part à tous les abonnés confirmés, sans ciblage.
		$id = CFNL_Campaigns::save( [
			'titre' => 'Article : ' . $post->post_title,
			'objet' => $objet,
			'corps' => $corps,
			'cible' => 'both',
		] );

		// Mode « envoi auto » : on lance directement ; sinon on laisse en brouillon
		if ( 'send' === ( $s['postnotif_mode'] ?? 'draft' ) && $id ) {
			CFNL_Campaigns::enqueue( $id );
		}
	}

	/* Le texte du mail vient du modèle modifiable dans
	   Newsletter → Automatisations (réglage `postnotif_corps`). Les balises
	   {{…}} y sont remplacées par les éléments de l'article publié. */
	private static function build_post_body( $post ) {
		$s        = get_option( 'cfnl_settings', [] );
		$defaults = CFNL_Install::default_settings();
		$tpl      = (string) ( $s['postnotif_corps'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			$tpl = (string) $defaults['postnotif_corps'];
		}

		$url     = get_permalink( $post );
		$title   = get_the_title( $post );
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '…' );
		$img     = get_the_post_thumbnail_url( $post, 'large' );

		// Blocs tout prêts : l'image disparaît d'elle-même si l'article n'en a pas
		$img_block = $img
			? '<p><img src="' . esc_url( $img ) . '" alt="" style="max-width:100%;border-radius:8px;"></p>'
			: '';
		$btn_block = '<p><a href="' . esc_url( $url ) . '" style="background:#5a3e6b;color:#fff;padding:12px 26px;border-radius:6px;text-decoration:none;display:inline-block;">Lire l\'article →</a></p>';

		$body = strtr( $tpl, [
			'{{post_title}}'   => esc_html( $title ),
			'{{post_excerpt}}' => esc_html( $excerpt ),
			'{{post_url}}'     => esc_url( $url ),
			'{{post_image}}'   => $img_block,
			'{{post_bouton}}'  => $btn_block,
		] );

		// Ligne vide résiduelle quand l'article n'a pas d'image à la une
		return preg_replace( "/\n{3,}/", "\n\n", $body );
	}
}
