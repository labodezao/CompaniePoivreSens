<?php
/**
 * RGPD — branche les données du plugin sur les outils WordPress natifs
 * (Outils → Exporter / Effacer les données personnelles) et purge
 * automatiquement les données anciennes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporters' ] );
		add_filter( 'wp_privacy_personal_data_erasers',   [ __CLASS__, 'register_erasers' ] );
		add_action( 'cfeb_privacy_cron', [ __CLASS__, 'retention_cleanup' ] );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'cfeb_privacy_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cfeb_privacy_cron' );
		}
	}

	public static function register_exporters( $exporters ) {
		$exporters['cf-events-booking'] = [
			'exporter_friendly_name' => 'Réservations & newsletter (CF)',
			'callback'               => [ __CLASS__, 'export' ],
		];
		return $exporters;
	}

	public static function register_erasers( $erasers ) {
		$erasers['cf-events-booking'] = [
			'eraser_friendly_name' => 'Réservations & newsletter (CF)',
			'callback'             => [ __CLASS__, 'erase' ],
		];
		return $erasers;
	}

	/* ── Export de toutes les données liées à un email ────────────── */
	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$email = sanitize_email( $email );
		$items = [];

		// Réservations
		$bookings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}" . CFEB_TABLE . ' WHERE email = %s', $email
		) );
		foreach ( $bookings as $b ) {
			$items[] = [
				'group_id'    => 'cf-reservations',
				'group_label' => 'Réservations',
				'item_id'     => 'booking-' . $b->id,
				'data'        => [
					[ 'name' => 'Prénom / Nom', 'value' => $b->prenom . ' ' . $b->nom ],
					[ 'name' => 'Email',        'value' => $b->email ],
					[ 'name' => 'Téléphone',    'value' => $b->telephone ],
					[ 'name' => 'Date',         'value' => $b->slot_debut ?: $b->cree_le ],
					[ 'name' => 'Statut',       'value' => $b->statut ],
				],
			];
		}

		// Abonné newsletter
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			$sub = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . CFNL_Subscribers::table() . ' WHERE email = %s', $email
			) );
			if ( $sub ) {
				$items[] = [
					'group_id'    => 'cf-newsletter',
					'group_label' => 'Abonnement newsletter',
					'item_id'     => 'subscriber-' . $sub->id,
					'data'        => [
						[ 'name' => 'Email',     'value' => $sub->email ],
						[ 'name' => 'Prénom',    'value' => $sub->prenom ],
						[ 'name' => 'Statut',    'value' => $sub->statut ],
						[ 'name' => 'Fréquence', 'value' => $sub->frequence ],
						[ 'name' => 'Inscrit le','value' => $sub->cree_le ],
					],
				];
			}
		}

		// Séquences post-séance
		$seqs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cfps_sequence WHERE email = %s", $email
		) );
		foreach ( $seqs as $s ) {
			$items[] = [
				'group_id'    => 'cf-post-seance',
				'group_label' => 'Suivi post-séance',
				'item_id'     => 'sequence-' . $s->id,
				'data'        => [
					[ 'name' => 'Prénom',        'value' => $s->prenom ],
					[ 'name' => 'Date de séance','value' => $s->date_seance ],
					[ 'name' => 'Statut',        'value' => $s->statut ],
				],
			];
		}

		return [ 'data' => $items, 'done' => true ];
	}

	/* ── Effacement (anonymisation des réservations, suppression du reste) ── */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;
		$email   = sanitize_email( $email );
		$removed = false;

		// Réservations : anonymisées (l'historique comptable des séances est conservé sans identité)
		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}" . CFEB_TABLE . "
			 SET prenom = 'Anonyme', nom = '', email = '', telephone = '', notes = '', champs_perso = ''
			 WHERE email = %s", $email
		) );
		if ( $n ) $removed = true;

		// Newsletter : suppression complète (abonné + file d'envoi)
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			$sub_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . CFNL_Subscribers::table() . ' WHERE email = %s', $email ) );
			if ( $sub_id ) {
				$wpdb->delete( $wpdb->prefix . 'cfnl_queue', [ 'subscriber_id' => (int) $sub_id ] );
				$wpdb->delete( CFNL_Subscribers::table(), [ 'id' => (int) $sub_id ] );
				$removed = true;
			}
		}

		// Post-séance : suppression complète
		$n = $wpdb->delete( $wpdb->prefix . 'cfps_sequence', [ 'email' => $email ] );
		if ( $n ) $removed = true;

		return [
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $removed ? [ 'Données CF anonymisées ou supprimées.' ] : [],
			'done'           => true,
		];
	}

	/* ── Purge quotidienne des données anciennes ──────────────────── */
	public static function retention_cleanup() {
		global $wpdb;
		$months = (int) apply_filters( 'cfeb_retention_months', 36 );
		if ( $months < 6 ) $months = 6; // garde-fou
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months", current_time( 'timestamp' ) ) );

		// Séquences post-séance terminées et anciennes
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}cfps_sequence WHERE date_seance < %s AND statut != 'active'", $cutoff
		) );
		// Abonnés désinscrits depuis longtemps (on ne garde pas d'email inutile)
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			$wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . CFNL_Subscribers::table() . " WHERE statut = 'unsubscribed' AND cree_le < %s", $cutoff
			) );
		}
		// File d'envoi des vieilles campagnes (les stats agrégées restent sur la campagne)
		$wpdb->query( $wpdb->prepare(
			"DELETE q FROM {$wpdb->prefix}cfnl_queue q
			 INNER JOIN {$wpdb->prefix}cfnl_campaigns c ON c.id = q.campaign_id
			 WHERE c.statut = 'sent' AND c.sent_at < %s", $cutoff
		) );
	}
}
