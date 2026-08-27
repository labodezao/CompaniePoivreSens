<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Campaigns {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cfnl_campaigns';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", absint( $id ) ) );
	}

	public static function all( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
	}

	/* ── Normalise un HTML collé depuis MailPoet (ou ailleurs) ──────
	   - extrait le contenu du <body> si un document complet est collé
	   - convertit les variables MailPoet vers celles du module
	   - retire les liens d'action MailPoet (désinscription, gestion),
	     remplacés automatiquement par ceux du module à l'envoi */
	public static function normalize_html( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return '';
		}
		// Document complet collé : ne garder que l'intérieur du <body>
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $m ) ) {
			$html = $m[1];
		}
		// Blocs <style> et commentaires conditionnels Outlook
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
		$html = preg_replace( '/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html );

		// Variables MailPoet → variables du module
		$html = preg_replace( '/\[subscriber:firstname[^\]]*\]/i', '{{prenom}}', $html );
		$html = preg_replace( '/\[subscriber:lastname[^\]]*\]/i',  '{{nom}}',    $html );
		$html = preg_replace( '/\[subscriber:email[^\]]*\]/i',     '{{email}}',  $html );

		// Liens d'action MailPoet : ancre entière puis jeton résiduel
		$html = preg_replace( '/<a[^>]*\[link:[^\]]+\][^>]*>.*?<\/a>/is', '', $html );
		$html = preg_replace( '/\[link:[^\]]+\]/i', '', $html );
		$html = preg_replace( '/\[date:[^\]]+\]/i', '', $html );

		// Paragraphes vidés par les suppressions ci-dessus
		$html = preg_replace( '/<p[^>]*>\s*(&nbsp;)?\s*<\/p>/i', '', $html );

		return trim( $html );
	}

	public static function save( $data ) {
		global $wpdb;
		$table = self::table();
		$id    = absint( $data['id'] ?? 0 );

		$objet = preg_replace( '/\[subscriber:firstname[^\]]*\]/i', '{{prenom}}', (string) ( $data['objet'] ?? '' ) );

		$row = [
			'titre'      => sanitize_text_field( $data['titre'] ?? '' ),
			'objet'      => sanitize_text_field( $objet ),
			'corps'      => wp_kses_post( self::normalize_html( $data['corps'] ?? '' ) ),
			'cible'      => in_array( $data['cible'] ?? 'both', [ 'both', 'quinzaine', 'mensuel' ], true ) ? $data['cible'] : 'both',
			'segment'    => in_array( $data['segment'] ?? 'all', [ 'all', 'engaged', 'inactive' ], true ) ? $data['segment'] : 'all',
			'objet_b'    => sanitize_text_field( $data['objet_b'] ?? '' ),
			'ab_enabled' => ! empty( $data['ab_enabled'] ) ? 1 : 0,
			'ab_sample'  => min( 90, max( 10, absint( $data['ab_sample'] ?? 30 ) ) ),
			'maj_le'     => current_time( 'mysql' ),
		];

		if ( $id ) {
			$existing = self::get( $id );
			// On ne modifie plus une campagne en cours d'envoi ou envoyée
			if ( $existing && in_array( $existing->statut, [ 'sending', 'sent' ], true ) ) {
				return $id;
			}
			$wpdb->update( $table, $row, [ 'id' => $id ] );
			return $id;
		}

		$row['statut']  = 'draft';
		$row['cree_le'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		$camp = self::get( $id );
		if ( $camp && 'sending' === $camp->statut ) {
			return false; // pas de suppression en plein envoi
		}
		$wpdb->delete( self::table(), [ 'id' => $id ] );
		$wpdb->delete( $wpdb->prefix . 'cfnl_queue', [ 'campaign_id' => $id ] );
		return true;
	}

	/* ── Programme une campagne à une date/heure ──────────────────── */
	public static function schedule( $id, $datetime_local ) {
		global $wpdb;
		$camp = self::get( $id );
		if ( ! $camp || in_array( $camp->statut, [ 'sending', 'sent' ], true ) ) {
			return new WP_Error( 'bad_status', 'Campagne déjà envoyée ou en cours.' );
		}
		// $datetime_local est saisi en heure du site ; on stocke tel quel
		// (comparé plus bas à current_time('mysql'), également en heure du site).
		$ts = strtotime( $datetime_local );
		if ( ! $ts ) {
			return new WP_Error( 'bad_date', 'Date invalide.' );
		}
		$mysql = gmdate( 'Y-m-d H:i:s', $ts );
		$wpdb->update( self::table(), [
			'statut'       => 'scheduled',
			'scheduled_at' => $mysql,
		], [ 'id' => absint( $id ) ] );
		return true;
	}

	/* ── Annule une programmation (retour en brouillon) ───────────── */
	public static function unschedule( $id ) {
		global $wpdb;
		$camp = self::get( $id );
		if ( $camp && 'scheduled' === $camp->statut ) {
			$wpdb->update( self::table(), [ 'statut' => 'draft', 'scheduled_at' => null ], [ 'id' => absint( $id ) ] );
		}
	}

	/* ── Campagnes programmées dont l'heure est arrivée ───────────── */
	public static function due_scheduled() {
		global $wpdb;
		$now = current_time( 'mysql' );
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM " . self::table() . " WHERE statut = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= %s",
			$now
		) );
	}

	/* ── Prépare l'envoi : remplit la file d'attente ──────────────── */
	public static function enqueue( $id ) {
		global $wpdb;
		$camp = self::get( $id );
		if ( ! $camp || in_array( $camp->statut, [ 'sending', 'sent' ], true ) ) {
			return new WP_Error( 'bad_status', 'Campagne déjà envoyée ou en cours.' );
		}

		$segment    = $camp->segment ?? 'all';
		$recipients = CFNL_Subscribers::recipients_for( $camp->cible, $segment );
		if ( empty( $recipients ) ) {
			return new WP_Error( 'no_recipients', 'Aucun abonné confirmé pour cette cible / ce segment.' );
		}

		$queue = $wpdb->prefix . 'cfnl_queue';

		// ── A/B testing : envoie un échantillon (moitié objet A, moitié B),
		//     décision différée du gagnant (voir maybe_decide_ab). ──────────
		$ab = ! empty( $camp->ab_enabled ) && '' !== trim( (string) $camp->objet_b );
		if ( $ab ) {
			$total   = count( $recipients );
			$idx     = [];
			foreach ( $recipients as $i => $r ) { $idx[] = $i; }
			// Ordre pseudo-aléatoire stable (id de campagne comme graine implicite)
			usort( $idx, function ( $x, $y ) use ( $recipients ) {
				return strcmp( md5( $recipients[ $x ]->email ), md5( $recipients[ $y ]->email ) );
			} );
			$sample_pct  = min( 90, max( 10, (int) ( $camp->ab_sample ?? 30 ) ) );
			$sample_size = max( 2, (int) floor( $total * $sample_pct / 100 ) );
			$sample_ids  = array_slice( $idx, 0, $sample_size );

			$n = 0;
			foreach ( $sample_ids as $k => $i ) {
				$r       = $recipients[ $i ];
				$variant = ( $k % 2 === 0 ) ? 'a' : 'b';
				$ok = $wpdb->query( $wpdb->prepare(
					"INSERT IGNORE INTO {$queue} (campaign_id, subscriber_id, email, statut, variant) VALUES (%d, %d, %s, 'pending', %s)",
					$id, (int) $r->id, $r->email, $variant
				) );
				if ( $ok ) $n++;
			}
			// Décision du gagnant après 4 h ; le reste sera mis en file à ce moment.
			$decide = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 4 * HOUR_IN_SECONDS );
			$wpdb->update( self::table(), [
				'statut'       => 'sending',
				'total'        => $total,
				'sent_at'      => current_time( 'mysql' ),
				'ab_decide_at' => $decide,
			], [ 'id' => $id ] );
			return $n;
		}

		// ── Envoi standard ────────────────────────────────────────────
		$n = 0;
		foreach ( $recipients as $r ) {
			$ok = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$queue} (campaign_id, subscriber_id, email, statut) VALUES (%d, %d, %s, 'pending')",
				$id, (int) $r->id, $r->email
			) );
			if ( $ok ) $n++;
		}

		$wpdb->update( self::table(), [
			'statut' => 'sending',
			'total'  => $n,
			'sent_at' => current_time( 'mysql' ),
		], [ 'id' => $id ] );

		return $n;
	}

	/* ── A/B : décide le gagnant et met le reste en file ──────────── */
	public static function maybe_decide_ab() {
		global $wpdb;
		$table = self::table();
		$queue = $wpdb->prefix . 'cfnl_queue';
		$now   = current_time( 'mysql' );

		$due = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE ab_enabled = 1 AND ab_winner = '' AND ab_decide_at IS NOT NULL AND ab_decide_at <= %s AND statut = 'sending'",
			$now
		) );

		foreach ( $due as $camp ) {
			// Taux d'ouverture de chaque variante dans l'échantillon
			$open_a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND variant = 'a' AND opened_at IS NOT NULL", $camp->id ) );
			$sent_a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND variant = 'a'", $camp->id ) );
			$open_b = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND variant = 'b' AND opened_at IS NOT NULL", $camp->id ) );
			$sent_b = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND variant = 'b'", $camp->id ) );

			$rate_a = $sent_a > 0 ? $open_a / $sent_a : 0;
			$rate_b = $sent_b > 0 ? $open_b / $sent_b : 0;
			$winner = ( $rate_b > $rate_a ) ? 'b' : 'a'; // égalité → A

			// Met en file les destinataires restants avec l'objet gagnant
			$segment    = $camp->segment ?? 'all';
			$recipients = CFNL_Subscribers::recipients_for( $camp->cible, $segment );
			$already    = $wpdb->get_col( $wpdb->prepare( "SELECT subscriber_id FROM {$queue} WHERE campaign_id = %d", $camp->id ) );
			$already    = array_map( 'intval', $already );

			foreach ( $recipients as $r ) {
				if ( in_array( (int) $r->id, $already, true ) ) {
					continue;
				}
				$wpdb->query( $wpdb->prepare(
					"INSERT IGNORE INTO {$queue} (campaign_id, subscriber_id, email, statut, variant) VALUES (%d, %d, %s, 'pending', %s)",
					$camp->id, (int) $r->id, $r->email, $winner
				) );
			}
			$wpdb->update( $table, [ 'ab_winner' => $winner ], [ 'id' => $camp->id ] );
		}
	}

	/* ── Renvoi aux non-ouvreurs : duplique et met en file ciblée ─── */
	public static function resend_non_openers( $id, $nouvel_objet = '' ) {
		global $wpdb;
		$src = self::get( $id );
		if ( ! $src || 'sent' !== $src->statut ) {
			return new WP_Error( 'bad_status', 'Le renvoi n\'est possible que sur une campagne déjà envoyée.' );
		}

		$queue = $wpdb->prefix . 'cfnl_queue';
		// Abonnés qui ont bien reçu l'original mais ne l'ont pas ouvert
		$non_openers = $wpdb->get_results( $wpdb->prepare(
			"SELECT q.subscriber_id, q.email
			 FROM {$queue} q
			 INNER JOIN " . CFNL_Subscribers::table() . " s ON s.id = q.subscriber_id
			 WHERE q.campaign_id = %d AND q.statut = 'sent' AND q.opened_at IS NULL AND s.statut = 'subscribed'",
			$id
		) );
		if ( empty( $non_openers ) ) {
			return new WP_Error( 'no_targets', 'Aucun non-ouvreur à recontacter (ou ouvertures non encore enregistrées).' );
		}

		// Objet de base pour la relance : celui réellement gagnant si A/B
		$objet_src = $src->objet;
		if ( ! empty( $src->ab_enabled ) && 'b' === $src->ab_winner && ! empty( $src->objet_b ) ) {
			$objet_src = $src->objet_b;
		}

		// Nouvelle campagne, copie du contenu
		$new_id = self::save( [
			'titre' => 'Relance : ' . $src->titre,
			'objet' => $nouvel_objet ?: ( 'Rappel — ' . $objet_src ),
			'corps' => $src->corps,
			'cible' => $src->cible,
		] );
		if ( ! $new_id ) {
			return new WP_Error( 'save_failed', 'Création de la relance impossible.' );
		}

		$n = 0;
		foreach ( $non_openers as $r ) {
			$ok = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$queue} (campaign_id, subscriber_id, email, statut) VALUES (%d, %d, %s, 'pending')",
				$new_id, (int) $r->subscriber_id, $r->email
			) );
			if ( $ok ) $n++;
		}
		$wpdb->update( self::table(), [
			'statut'  => 'sending',
			'total'   => $n,
			'sent_at' => current_time( 'mysql' ),
		], [ 'id' => $new_id ] );

		return [ 'new_id' => $new_id, 'count' => $n ];
	}
}
