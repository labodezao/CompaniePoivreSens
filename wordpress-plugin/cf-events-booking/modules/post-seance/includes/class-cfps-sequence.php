<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPS_Sequence {

	/* ── Point d'entrée cron quotidien ───────────────────────── */
	public static function run_daily() {
		self::detect_new_sessions();
		self::process_pending_steps();
	}

	/* ── Détecte les séances individuelles d'hier ────────────── */
	public static function detect_new_sessions() {
		global $wpdb;

		$settings  = get_option( 'cfps_settings', [] );
		$slug      = $settings['appt_type_slug'] ?? 'constellation-individuelle';

		// Récupère l'ID du type de RDV via son slug
		$type_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'cf_appt_type'
				   AND post_name  = %s
				   AND post_status = 'publish'
				 LIMIT 1",
				$slug
			)
		);

		if ( ! $type_id ) return;

		$tz        = wp_timezone_string() ?: 'UTC';
		$tz_obj    = new DateTimeZone( $tz );
		$yesterday = new DateTime( 'yesterday', $tz_obj );
		$date_from = $yesterday->format( 'Y-m-d 00:00:00' );
		$date_to   = $yesterday->format( 'Y-m-d 23:59:59' );

		$bookings_table  = $wpdb->prefix . 'cf_bookings';
		$sequence_table  = $wpdb->prefix . CFPS_TABLE;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.email, b.prenom, b.slot_debut
				 FROM {$bookings_table} b
				 WHERE b.appt_type_id = %d
				   AND b.slot_debut BETWEEN %s AND %s
				   AND b.statut IN ('confirme', 'present')
				   AND NOT EXISTS (
				       SELECT 1 FROM {$sequence_table} s WHERE s.booking_id = b.id
				   )",
				(int) $type_id,
				$date_from,
				$date_to
			)
		);

		foreach ( $bookings as $b ) {
			self::add_to_sequence( $b );
		}
	}

	/* ── Ajoute un booking à la séquence ─────────────────────── */
	public static function add_to_sequence( $booking ) {
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		$wpdb->insert( $table, [
			'booking_id'  => (int) $booking->id,
			'email'       => sanitize_email( $booking->email ),
			'prenom'      => sanitize_text_field( $booking->prenom ),
			'date_seance' => $booking->slot_debut,
			'statut'      => 'active',
			'cree_le'     => current_time( 'mysql' ),
		] );
	}

	/* ── Traite les étapes en attente ────────────────────────── */
	public static function process_pending_steps() {
		global $wpdb;
		$table = $wpdb->prefix . CFPS_TABLE;

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE statut = 'active' ORDER BY date_seance ASC"
		);

		foreach ( $rows as $row ) {
			$seance_ts = strtotime( $row->date_seance );
			$now_ts    = current_time( 'timestamp' );
			$days      = (int) floor( ( $now_ts - $seance_ts ) / DAY_IN_SECONDS );

			// Email 1 — J+1
			if ( $days >= 1 && ! $row->step_1_sent_at ) {
				if ( CFPS_Emails::send_step1( $row ) ) {
					self::mark_step( $row->id, 'step_1_sent_at' );
				}
			}

			// Email 2 — J+7
			if ( $days >= 7 && ! $row->step_2_sent_at ) {
				$url = self::get_article_url( $row->theme_slug );
				if ( CFPS_Emails::send_step2( $row, $url ) ) {
					self::mark_step( $row->id, 'step_2_sent_at' );
				}
			}

			// Email 3 — J+14
			if ( $days >= 14 && ! $row->step_3_sent_at ) {
				if ( CFPS_Emails::send_step3( $row ) ) {
					self::mark_step( $row->id, 'step_3_sent_at' );
				}
			}

			// Email 4 — J+28 → notification admin uniquement
			if ( $days >= 28 && ! $row->step_4_notif_at ) {
				$token   = wp_generate_password( 48, false );
				$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
				$wpdb->update(
					$table,
					[ 'step_4_token' => $token, 'step_4_expires' => $expires ],
					[ 'id' => (int) $row->id ]
				);
				$row->step_4_token   = $token;
				$row->step_4_expires = $expires;
				if ( CFPS_Emails::notify_admin_step4( $row ) ) {
					self::mark_step( $row->id, 'step_4_notif_at' );
				}
			}
		}
	}

	/* ── Marque une étape comme envoyée ──────────────────────── */
	private static function mark_step( $id, $column ) {
		global $wpdb;
		$allowed = [ 'step_1_sent_at', 'step_2_sent_at', 'step_3_sent_at', 'step_4_notif_at' ];
		if ( ! in_array( $column, $allowed, true ) ) return;
		$wpdb->update(
			$wpdb->prefix . CFPS_TABLE,
			[ $column => current_time( 'mysql' ) ],
			[ 'id'    => absint( $id ) ]
		);
	}

	/* ── URL de l'article pilier selon thème ─────────────────── */
	public static function get_article_url( $theme ) {
		$settings = get_option( 'cfps_settings', [] );
		$articles = $settings['articles'] ?? [];
		$slug     = strtoupper( trim( $theme ) );

		// Sécurité : le pilier I (Addictions & désir d'enfant) ne doit
		// JAMAIS être poussé en communication automatique (infertilité et
		// addictions = risque juridique — allégation de santé / provocation
		// à l'abandon de soins). Il n'apparaît plus dans theme_labels() donc
		// ne peut plus être sélectionné pour une nouvelle séance, mais cette
		// garde couvre aussi les lignes déjà enregistrées en base avec
		// theme_slug = 'I' avant ce correctif : elles retombent sur J.
		if ( 'I' === $slug ) {
			$slug = 'J';
		}

		// Passe-partout si thème non renseigné ou URL manquante
		if ( $slug && ! empty( $articles[ $slug ] ) ) {
			return esc_url( $articles[ $slug ] );
		}
		// Défaut : Génosociogramme (thème J)
		return ! empty( $articles['J'] ) ? esc_url( $articles['J'] ) : home_url( '/genogramme/' );
	}

	/* ── Désinscription ──────────────────────────────────────── */
	public static function unsubscribe( $email ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . CFPS_TABLE,
			[ 'statut' => 'desabonne' ],
			[ 'email'  => sanitize_email( $email ), 'statut' => 'active' ]
		);
	}

	/* ── Labels des thèmes ───────────────────────────────────── */
	/*
	 * Le pilier I (« Addictions & désir d'enfant ») a été retiré
	 * intentionnellement : infertilité et addictions ne doivent jamais être
	 * poussées en communication automatique (risque juridique — allégation
	 * de santé, provocation à l'abandon de soins, loi du 10 mai 2024). Il
	 * n'est donc plus sélectionnable pour une séance ; get_article_url()
	 * redirige aussi vers J toute ligne déjà enregistrée avec 'I' en base.
	 */
	public static function theme_labels() {
		return [
			'A' => 'A — Comprendre les constellations (fondamentaux)',
			'B' => 'B — Avant / après une séance (parcours vécu)',
			'C' => 'C — Couple & relations amoureuses',
			'D' => 'D — Sexualité & intimité',
			'E' => 'E — Transmission transgénérationnelle & loyautés',
			'F' => 'F — Parentalité, enfants & familles recomposées',
			'G' => 'G — Deuil, absence & mort',
			'H' => 'H — Corps, neurochimie & émotions',
			'J' => 'J — Outils & rituels (passe-partout par défaut)',
			'K' => 'K — Cheminement personnel',
		];
	}
}
