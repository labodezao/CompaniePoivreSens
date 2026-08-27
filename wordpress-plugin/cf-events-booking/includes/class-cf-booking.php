<?php
/**
 * CRUD réservations — table dédiée wp_cf_bookings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Booking {

	/* ── Nom de la table ─────────────────────────────────────────── */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . CFEB_TABLE;
	}

	/* ── Ajouter une réservation ─────────────────────────────────── */
	public static function add( array $data ) {
		global $wpdb;

		$slot_debut_raw = sanitize_text_field( $data['slot_debut'] ?? '' );
		$slot_fin_raw   = sanitize_text_field( $data['slot_fin']   ?? '' );
		// Normalize: YYYY-MM-DDTHH:MM → YYYY-MM-DD HH:MM:00
		$slot_debut = $slot_debut_raw ? gmdate( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $slot_debut_raw ) ) ) : null;
		$slot_fin   = $slot_fin_raw   ? gmdate( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $slot_fin_raw ) ) )   : null;

		$row = [
			'event_id'        => absint( $data['event_id'] ?? 0 ),
			'appt_type_id'    => absint( $data['appt_type_id'] ?? 0 ),
			'slot_debut'      => $slot_debut,
			'slot_fin'        => $slot_fin,
			'user_id'         => absint( $data['user_id'] ?? 0 ),
			'prenom'          => sanitize_text_field( $data['prenom'] ),
			'nom'             => sanitize_text_field( $data['nom'] ),
			'email'           => sanitize_email( $data['email'] ),
			'telephone'       => sanitize_text_field( $data['telephone'] ?? '' ),
			'nb_places'       => min( absint( $data['nb_places'] ?? 1 ), 10 ),
			'statut'          => sanitize_key( $data['statut'] ?? 'confirme' ),
			'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
			'champs_perso'    => sanitize_textarea_field( $data['champs_perso'] ?? '' ),
			'motif_annulation'=> sanitize_textarea_field( $data['motif_annulation'] ?? '' ),
			'paye'            => absint( $data['paye'] ?? 0 ),
			'mode_paiement'   => sanitize_text_field( $data['mode_paiement'] ?? '' ),
			'modalite'        => sanitize_key( $data['modalite'] ?? '' ),
			'adresse'         => sanitize_text_field( $data['adresse'] ?? '' ),
			'token'           => wp_generate_password( 32, false ),
			'cree_le'         => current_time( 'mysql' ),
		];

		$insert_ok = self::insert_row( $row );

		// Schéma potentiellement obsolète (colonne manquante, table absente, etc.).
		// On tente la migration sur tout échec d'INSERT, indépendamment de last_error.
		if ( ! $insert_ok && function_exists( 'cfeb_create_table' ) ) {
			cfeb_create_table();
			$wpdb->last_error = '';
			$insert_ok        = self::insert_row( $row );
		}

		if ( ! $insert_ok ) {
			return new WP_Error( 'db_error', $wpdb->last_error ?: 'Erreur de base de données inconnue.' );
		}

		$row['id'] = $wpdb->insert_id;
		return $row;
	}

	private static function insert_row( array $row ) {
		global $wpdb;
		return false !== $wpdb->insert( self::table(), $row );
	}

	/* ── Récupérer une réservation par ID ────────────────────────── */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) )
		);
	}

	/* ── Réservations par user_id ────────────────────────────────── */
	public static function get_by_user_id( $user_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, p.post_title AS event_title FROM {$t} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id WHERE b.user_id = %d ORDER BY b.cree_le DESC",
				absint( $user_id )
			)
		);
	}

	/* ── Récupérer par token ─────────────────────────────────────── */
	public static function get_by_token( $token ) {
		global $wpdb;
		$clean = sanitize_text_field( $token );
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s LIMIT 1', $clean )
		);
	}

	/* ── Réservations pour un événement ──────────────────────────── */
	public static function get_for_event( $event_id, $statut = '' ) {
		global $wpdb;
		$t = self::table();
		if ( $statut ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t} WHERE event_id = %d AND statut = %s ORDER BY cree_le ASC",
					absint( $event_id ),
					sanitize_key( $statut )
				)
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE event_id = %d ORDER BY cree_le ASC",
				absint( $event_id )
			)
		);
	}

	/* ── Compter les réservations ────────────────────────────────── */
	public static function count_for_event( $event_id, $statut = '' ) {
		global $wpdb;
		$t = self::table();
		if ( $statut ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(nb_places),0) FROM {$t} WHERE event_id = %d AND statut = %s",
					absint( $event_id ),
					sanitize_key( $statut )
				)
			);
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(nb_places),0) FROM {$t} WHERE event_id = %d",
				absint( $event_id )
			)
		);
	}

	/* ── Compter les réservations pour un créneau de type RDV ──── */
	public static function count_for_slot( $appt_type_id, $slot_debut_str, $statut = 'confirme' ) {
		global $wpdb;
		$t          = self::table();
		$slot_debut = gmdate( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $slot_debut_str ) ) );
		$allowed    = [ 'confirme', 'annule', 'liste_attente', 'present', 'absent' ];
		$statut     = in_array( $statut, $allowed, true ) ? $statut : 'confirme';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(nb_places),0) FROM {$t} WHERE appt_type_id = %d AND slot_debut = %s AND statut = %s",
				absint( $appt_type_id ),
				$slot_debut,
				$statut
			)
		);
	}

	/* ── Vérifier si email a déjà RDV sur ce créneau ─────────── */
	public static function already_booked_slot( $appt_type_id, $slot_debut_str, $email ) {
		global $wpdb;
		$t          = self::table();
		$slot_debut = gmdate( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $slot_debut_str ) ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE appt_type_id = %d AND slot_debut = %s AND email = %s AND statut != 'annule'",
				absint( $appt_type_id ),
				$slot_debut,
				sanitize_email( $email )
			)
		) > 0;
	}

	/* ── Mettre à jour le paiement ──────────────────────────────── */
	public static function update_payment( $id, $paye, $mode_paiement = '' ) {
		global $wpdb;
		return $wpdb->update(
			self::table(),
			[
				'paye'          => (int) (bool) $paye,
				'mode_paiement' => sanitize_text_field( $mode_paiement ),
			],
			[ 'id' => absint( $id ) ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	/* ── Changer le statut ───────────────────────────────────────── */
	public static function update_statut( $id, $statut ) {
		global $wpdb;
		$allowed = [ 'confirme', 'annule', 'liste_attente', 'present', 'absent' ];
		if ( ! in_array( $statut, $allowed, true ) ) {
			return false;
		}
		return $wpdb->update(
			self::table(),
			[ 'statut' => $statut ],
			[ 'id'     => absint( $id ) ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/* ── Changer statut + motif ─────────────────────────────────── */
	public static function update_statut_with_motif( $id, $statut, $motif = '' ) {
		global $wpdb;
		$allowed = [ 'confirme', 'annule', 'liste_attente', 'present', 'absent' ];
		if ( ! in_array( $statut, $allowed, true ) ) {
			return false;
		}
		return $wpdb->update(
			self::table(),
			[
				'statut'          => $statut,
				'motif_annulation' => sanitize_textarea_field( $motif ),
			],
			[ 'id' => absint( $id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	/* ── Annuler par token ───────────────────────────────────────── */
	public static function cancel_by_token( $token ) {
		$booking = self::get_by_token( $token );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', 'Réservation introuvable.' );
		}
		if ( 'annule' === $booking->statut ) {
			return new WP_Error( 'already_cancelled', 'Déjà annulée.' );
		}
		self::update_statut( $booking->id, 'annule' );
		return $booking;
	}

	/* ── Supprimer ───────────────────────────────────────────────── */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => absint( $id ) ], [ '%d' ] );
	}

	/* ── Historique complet d'un email ──────────────────────────── */
	public static function get_history_by_email( $email ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, p.post_title AS event_title,
				        pm.meta_value AS event_date
				 FROM {$t} b
				 LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id
				 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = b.event_id AND pm.meta_key = '_cfeb_date_debut'
				 WHERE b.email = %s
				 ORDER BY b.cree_le DESC",
				sanitize_email( $email )
			)
		);
	}

	/* ── Vérifie si email a déjà réservé un événement ───────────── */
	public static function already_booked( $event_id, $email ) {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::table() . " WHERE event_id = %d AND email = %s AND statut != 'annule'",
				absint( $event_id ),
				sanitize_email( $email )
			)
		);
		return $count > 0;
	}

	/* ── Toutes les réservations (admin, avec pagination) ────────── */
	public static function get_all( $args = [] ) {
		global $wpdb;
		$defaults = [
			'per_page'  => 30,
			'page'      => 1,
			'event_id'  => 0,
			'statut'    => '',
			'search'    => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'   => 'cree_le',
			'order'     => 'DESC',
		];
		$a = wp_parse_args( $args, $defaults );
		$t = self::table();

		$join   = " LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id";
		$where  = ' WHERE 1=1';
		$params = [];

		if ( $a['event_id'] ) {
			$where   .= ' AND b.event_id = %d';
			$params[] = absint( $a['event_id'] );
		}
		if ( $a['statut'] ) {
			$where   .= ' AND b.statut = %s';
			$params[] = sanitize_key( $a['statut'] );
		}
		if ( $a['search'] ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $a['search'] ) ) . '%';
			$where   .= ' AND (b.prenom LIKE %s OR b.nom LIKE %s OR b.email LIKE %s OR b.telephone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $a['date_from'] || $a['date_to'] ) {
			$join .= " LEFT JOIN {$wpdb->postmeta} pm_date ON pm_date.post_id = b.event_id AND pm_date.meta_key = '_cfeb_date_debut'";
			if ( $a['date_from'] ) {
				$where   .= ' AND pm_date.meta_value >= %s';
				$params[] = sanitize_text_field( $a['date_from'] ) . ' 00:00:00';
			}
			if ( $a['date_to'] ) {
				$where   .= ' AND pm_date.meta_value <= %s';
				$params[] = sanitize_text_field( $a['date_to'] ) . ' 23:59:59';
			}
		}

		$allowed_order  = [ 'id', 'event_id', 'prenom', 'nom', 'email', 'cree_le', 'statut', 'nb_places' ];
		$orderby = in_array( $a['orderby'], $allowed_order, true ) ? 'b.' . $a['orderby'] : 'b.cree_le';
		$order   = 'ASC' === strtoupper( $a['order'] ) ? 'ASC' : 'DESC';

		$offset  = ( max( 1, (int) $a['page'] ) - 1 ) * (int) $a['per_page'];
		$limit   = max( 1, (int) $a['per_page'] );

		$sql = "SELECT b.*, p.post_title AS event_title FROM {$t} b{$join}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/* ── Compter total (pour pagination) ─────────────────────────── */
	public static function count_all( $args = [] ) {
		global $wpdb;
		$t      = self::table();
		$join   = '';
		$where  = ' WHERE 1=1';
		$params = [];

		if ( ! empty( $args['event_id'] ) ) {
			$where   .= ' AND b.event_id = %d';
			$params[] = absint( $args['event_id'] );
		}
		if ( ! empty( $args['statut'] ) ) {
			$where   .= ' AND b.statut = %s';
			$params[] = sanitize_key( $args['statut'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where   .= ' AND (b.prenom LIKE %s OR b.nom LIKE %s OR b.email LIKE %s OR b.telephone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['date_from'] ) || ! empty( $args['date_to'] ) ) {
			$join .= " LEFT JOIN {$wpdb->postmeta} pm_date ON pm_date.post_id = b.event_id AND pm_date.meta_key = '_cfeb_date_debut'";
			if ( ! empty( $args['date_from'] ) ) {
				$where   .= ' AND pm_date.meta_value >= %s';
				$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $args['date_to'] ) ) {
				$where   .= ' AND pm_date.meta_value <= %s';
				$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
			}
		}

		$sql = "SELECT COUNT(*) FROM {$t} b{$join}{$where}";

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}
}
