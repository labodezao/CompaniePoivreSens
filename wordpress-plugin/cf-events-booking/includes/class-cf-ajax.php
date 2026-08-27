<?php
/**
 * AJAX : réservation, annulation, reprogrammation, filtrage.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Ajax {

	public static function init() {
		add_action( 'wp_ajax_cfeb_reserver',        [ __CLASS__, 'reserver' ] );
		add_action( 'wp_ajax_nopriv_cfeb_reserver', [ __CLASS__, 'reserver' ] );

		add_action( 'wp_ajax_cfeb_statut_update',        [ __CLASS__, 'statut_update' ] );
		add_action( 'wp_ajax_nopriv_cfeb_statut_update', [ __CLASS__, 'statut_noop' ] );

		add_action( 'wp_ajax_cfeb_reschedule',        [ __CLASS__, 'reschedule' ] );
		add_action( 'wp_ajax_nopriv_cfeb_reschedule', [ __CLASS__, 'statut_noop' ] );

		add_action( 'wp_ajax_cfeb_customer_reschedule',        [ __CLASS__, 'customer_reschedule' ] );
		add_action( 'wp_ajax_nopriv_cfeb_customer_reschedule', [ __CLASS__, 'customer_reschedule' ] );

		add_action( 'wp_ajax_cfeb_filter_events',        [ __CLASS__, 'filter_events' ] );
		add_action( 'wp_ajax_nopriv_cfeb_filter_events', [ __CLASS__, 'filter_events' ] );

		add_action( 'wp_ajax_cfeb_week_slots',        [ __CLASS__, 'week_slots' ] );
		add_action( 'wp_ajax_nopriv_cfeb_week_slots', [ __CLASS__, 'week_slots' ] );

		add_action( 'wp_ajax_cfeb_more_slots',        [ __CLASS__, 'more_slots' ] );
		add_action( 'wp_ajax_nopriv_cfeb_more_slots', [ __CLASS__, 'more_slots' ] );

		add_action( 'wp_ajax_cfeb_prev_slots',        [ __CLASS__, 'prev_slots' ] );
		add_action( 'wp_ajax_nopriv_cfeb_prev_slots', [ __CLASS__, 'prev_slots' ] );
	}

	/* ══════════════════════════════════════════════════════════════
	   RÉSERVATION
	══════════════════════════════════════════════════════════════ */
	public static function reserver() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( [ 'message' => 'Erreur de sécurité, veuillez recharger la page.' ], 403 );
		}

		$event_id     = absint( $_POST['event_id']     ?? 0 );
		$appt_type_id = absint( $_POST['appt_type_id'] ?? 0 );
		$slot_debut   = sanitize_text_field( wp_unslash( $_POST['slot_debut'] ?? '' ) );
		$slot_fin     = sanitize_text_field( wp_unslash( $_POST['slot_fin']   ?? '' ) );

		$prenom = sanitize_text_field( wp_unslash( $_POST['prenom']    ?? '' ) );
		$nom    = sanitize_text_field( wp_unslash( $_POST['nom']       ?? '' ) );
		$email  = sanitize_email(      wp_unslash( $_POST['email']     ?? '' ) );
		$tel    = sanitize_text_field( wp_unslash( $_POST['telephone'] ?? '' ) );
		$places = min( absint( $_POST['nb_places'] ?? 1 ), 10 );
		$notes  = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		$opts = CF_Admin::get_options();

		/* ── Chemin slot-based (appt_type_id + slot_debut) ──────────── */
		if ( $appt_type_id && $slot_debut ) {
			$type_post = get_post( $appt_type_id );
			if ( ! $type_post || CFEB_APPT_SLUG !== $type_post->post_type ) {
				wp_send_json_error( [ 'message' => 'Type de rendez-vous introuvable.' ], 404 );
			}
			$m = CF_ApptType::get_meta( $appt_type_id );

			// Le téléphone obligatoire se règle par type de rendez-vous (fiche
			// « Informations client »), pas par le réglage global — celui-ci ne
			// s'applique qu'au widget événements historique ci-dessous.
			$errors = [];
			if ( ! $prenom ) $errors[] = 'Le prénom est requis.';
			if ( ! $nom )    $errors[] = 'Le nom est requis.';
			if ( ! $email || ! is_email( $email ) ) $errors[] = 'Une adresse email valide est requise.';
			if ( $m['require_phone'] && ! $tel ) $errors[] = 'Le téléphone est requis.';
			if ( $errors ) {
				wp_send_json_error( [ 'message' => implode( ' ', $errors ) ], 422 );
			}

			// Modalité présentiel/distanciel : choix du client requis seulement
			// si le type propose les deux ; sinon la modalité du type s'applique.
			if ( 'les_deux' === $m['modalite'] ) {
				$modalite = sanitize_key( wp_unslash( $_POST['modalite'] ?? '' ) );
				if ( ! in_array( $modalite, [ 'presentiel', 'distanciel' ], true ) ) {
					wp_send_json_error( [ 'message' => 'Merci de préciser si vous souhaitez venir en présentiel ou à distance.' ], 422 );
				}
			} else {
				$modalite = $m['modalite'];
			}

			$slot_ts = strtotime( str_replace( 'T', ' ', $slot_debut ) );
			if ( $slot_ts <= time() ) {
				wp_send_json_error( [ 'message' => 'Ce créneau est déjà passé.' ], 400 );
			}
			if ( $m['delai_min'] > 0 && $slot_ts <= time() + $m['delai_min'] * HOUR_IN_SECONDS ) {
				wp_send_json_error( [ 'message' => sprintf( 'Les réservations sont fermées %d heure(s) avant le début du rendez-vous.', $m['delai_min'] ) ], 400 );
			}
			if ( $m['max_jours'] > 0 && $slot_ts > time() + $m['max_jours'] * DAY_IN_SECONDS ) {
				wp_send_json_error( [ 'message' => sprintf( 'Les réservations ouvrent %d jour(s) avant le rendez-vous.', $m['max_jours'] ) ], 400 );
			}

			if ( ! $opts['double_booking'] && CF_Booking::already_booked_slot( $appt_type_id, $slot_debut, $email ) ) {
				wp_send_json_error( [ 'message' => 'Vous avez déjà réservé ce créneau avec cette adresse email.' ], 409 );
			}

			$booked = CF_Booking::count_for_slot( $appt_type_id, $slot_debut );
			$dispo  = max( 0, $m['max_places'] - $booked );

			$statut_resa = 'confirme';
			if ( $dispo <= 0 ) {
				if ( $m['liste_attente'] ) {
					$statut_resa = 'liste_attente';
				} else {
					wp_send_json_error( [ 'message' => 'Désolé, ce créneau est complet.' ], 400 );
				}
			} elseif ( $places > $dispo ) {
				wp_send_json_error( [ 'message' => sprintf( 'Seulement %d place(s) disponible(s).', $dispo ) ], 400 );
			}

			// Bon cadeau : validation AVANT la création (erreur claire si code invalide)
			$voucher = null;
			if ( ! empty( $_POST['cfeb_bon_code'] ) && class_exists( 'CF_Vouchers' ) ) {
				$voucher = CF_Vouchers::check_code( sanitize_text_field( wp_unslash( $_POST['cfeb_bon_code'] ) ) );
				if ( is_wp_error( $voucher ) ) {
					wp_send_json_error( [ 'message' => $voucher->get_error_message() ], 422 );
				}
			}

			// Adresse effective du créneau choisi (résolue côté serveur, pas
			// depuis une valeur envoyée par le client) : le lieu spécifique
			// choisi pour cette plage horaire si le praticien consulte à
			// plusieurs endroits, sinon le lieu par défaut du type de RDV.
			$adresse = 'presentiel' === $modalite ? CF_ApptType::resolve_slot_adresse( $appt_type_id, $slot_debut ) : '';

			$result = CF_Booking::add( [
				'event_id'     => 0,
				'appt_type_id' => $appt_type_id,
				'slot_debut'   => $slot_debut,
				'slot_fin'     => $slot_fin,
				'user_id'      => is_user_logged_in() ? get_current_user_id() : 0,
				'prenom'       => $prenom,
				'nom'          => $nom,
				'email'        => $email,
				'telephone'    => $tel,
				'nb_places'    => $places,
				'statut'       => $statut_resa,
				'notes'        => $notes,
				'champs_perso' => '',
				'modalite'     => $modalite,
				'adresse'      => $adresse,
			] );

			if ( is_wp_error( $result ) ) {
				$msg = defined( 'WP_DEBUG' ) && WP_DEBUG
					? 'Erreur DB : ' . $result->get_error_message()
					: 'Erreur lors de l\'enregistrement. Réessayez.';
				wp_send_json_error( [ 'message' => $msg ], 500 );
			}

			delete_transient( 'cfeb_events_upcoming' );

			// Bon cadeau : consommation (la réservation devient payée)
			if ( $voucher && 'confirme' === $statut_resa ) {
				CF_Vouchers::redeem( $voucher, (int) $result['id'] );
			}

			// Générer un lien d'acompte SumUp si configuré et type RDV correspondant
			// (sauf si un bon cadeau couvre déjà la séance)
			if ( ! $voucher && 'confirme' === $statut_resa && class_exists( 'CF_SumUp' ) && get_option( 'cfeb_sumup_enabled' ) ) {
				if ( CF_SumUp::applies_to( get_post_field( 'post_name', $appt_type_id ) ) ) {
					$checkout_url = CF_SumUp::create_checkout( $result['id'], $prenom, $type_post->post_title );
					if ( ! is_wp_error( $checkout_url ) && $checkout_url ) {
						global $wpdb;
						$wpdb->update( $wpdb->prefix . CFEB_TABLE, [ 'acompte_url' => $checkout_url ], [ 'id' => $result['id'] ] );
						$result['acompte_url'] = $checkout_url;
					}
				}
			}

			CF_Email::confirmation_user( $result, null );
			CF_Email::notification_admin( $result, null );

			if ( 'confirme' === $statut_resa && class_exists( 'CF_GoogleCalendar' ) && CF_GoogleCalendar::is_connected() ) {
				$gcal_sync_slot = $m['gcal_enabled'] || ! empty( CF_Admin::get_options()['gcal_sync_all'] );
				if ( $gcal_sync_slot ) {
					CF_GoogleCalendar::sync_new_booking( CF_Booking::get( $result['id'] ), null );
				}
			}

			$msg = 'liste_attente' === $statut_resa
				? 'Vous avez été ajouté·e à la liste d\'attente. Nous vous contacterons si une place se libère.'
				: 'Un email de confirmation a été envoyé à ' . $email . '.';

			wp_send_json_success( [
				'message'      => $msg,
				'statut'       => $statut_resa,
				'prenom'       => $prenom,
				'email'        => $email,
				'redirect_url' => $opts['confirmation_redirect'] ?? '',
				'payment_url'  => $result['acompte_url'] ?? '',
				'acompte'      => (int) get_option( 'cfeb_sumup_acompte', 30 ),
			] );
		}

		/* ── Chemin event-based (legacy) ─────────────────────────────── */
		$errors = [];
		if ( ! $event_id ) $errors[] = 'Événement introuvable.';
		if ( ! $prenom )   $errors[] = 'Le prénom est requis.';
		if ( ! $nom )      $errors[] = 'Le nom est requis.';
		if ( ! $email || ! is_email( $email ) ) $errors[] = 'Une adresse email valide est requise.';
		if ( $opts['tel_obligatoire'] && ! $tel ) $errors[] = 'Le téléphone est requis.';
		if ( $errors ) {
			wp_send_json_error( [ 'message' => implode( ' ', $errors ) ], 422 );
		}

		$event = get_post( $event_id );
		if ( ! $event || 'publish' !== $event->post_status || CFEB_SLUG !== $event->post_type ) {
			wp_send_json_error( [ 'message' => 'Événement introuvable.' ], 404 );
		}

		$meta   = CF_CPT::get_meta( $event_id );
		$statut = CF_CPT::compute_statut( $event_id );
		$dispo  = CF_CPT::get_dispo( $event_id );
		$max    = (int) $meta['max_places'];

		$delai_min = (int) ( $meta['delai_min'] ?? 0 );
		if ( $delai_min > 0 && ! empty( $meta['date_debut'] ) ) {
			$ts_limit = strtotime( $meta['date_debut'] ) - ( $delai_min * HOUR_IN_SECONDS );
			if ( time() > $ts_limit ) {
				wp_send_json_error( [ 'message' => sprintf( 'Les réservations sont fermées %d heure(s) avant le début de l\'événement.', $delai_min ) ], 400 );
			}
		}

		$max_jours = (int) ( $meta['max_jours'] ?? 0 );
		if ( $max_jours > 0 && ! empty( $meta['date_debut'] ) ) {
			$ts_open = strtotime( $meta['date_debut'] ) - ( $max_jours * DAY_IN_SECONDS );
			if ( time() < $ts_open ) {
				wp_send_json_error( [ 'message' => sprintf( 'Les réservations ouvrent %d jour(s) avant l\'événement.', $max_jours ) ], 400 );
			}
		}

		if ( 'ferme' === $statut ) {
			wp_send_json_error( [ 'message' => 'Les inscriptions sont fermées pour cet événement.' ], 400 );
		}

		if ( ! $opts['double_booking'] && CF_Booking::already_booked( $event_id, $email ) ) {
			wp_send_json_error( [ 'message' => 'Vous avez déjà réservé cet événement avec cette adresse email.' ], 409 );
		}

		$statut_resa = 'confirme';
		if ( 'complet' === $statut ) {
			if ( $opts['liste_attente'] ) {
				$statut_resa = 'liste_attente';
			} else {
				wp_send_json_error( [ 'message' => 'Désolé, cet événement est complet.' ], 400 );
			}
		}

		if ( 'confirme' === $statut_resa && $max > 0 && $places > $dispo ) {
			wp_send_json_error( [ 'message' => sprintf( 'Seulement %d place(s) disponible(s).', $dispo ) ], 400 );
		}

		$champs_perso = [];
		$champs_def   = $meta['custom_fields'] ?? [];
		if ( is_array( $champs_def ) ) {
			foreach ( $champs_def as $champ ) {
				$slug = sanitize_key( $champ['slug'] ?? '' );
				if ( ! $slug ) continue;
				$val = sanitize_text_field( wp_unslash( $_POST[ 'cfeb_champ_' . $slug ] ?? '' ) );
				if ( ! empty( $champ['requis'] ) && '' === $val ) {
					wp_send_json_error( [ 'message' => sprintf( 'Le champ « %s » est requis.', esc_html( $champ['label'] ?? $slug ) ) ], 422 );
				}
				$champs_perso[ $slug ] = $val;
			}
		}

		// Bon cadeau : validation AVANT la création
		$voucher = null;
		if ( ! empty( $_POST['cfeb_bon_code'] ) && class_exists( 'CF_Vouchers' ) ) {
			$voucher = CF_Vouchers::check_code( sanitize_text_field( wp_unslash( $_POST['cfeb_bon_code'] ) ) );
			if ( is_wp_error( $voucher ) ) {
				wp_send_json_error( [ 'message' => $voucher->get_error_message() ], 422 );
			}
		}

		$result = CF_Booking::add( [
			'event_id'     => $event_id,
			'user_id'      => is_user_logged_in() ? get_current_user_id() : 0,
			'prenom'       => $prenom,
			'nom'          => $nom,
			'email'        => $email,
			'telephone'    => $tel,
			'nb_places'    => $places,
			'statut'       => $statut_resa,
			'notes'        => $notes,
			'champs_perso' => ! empty( $champs_perso ) ? wp_json_encode( $champs_perso ) : '',
		] );

		if ( is_wp_error( $result ) ) {
			$msg = defined( 'WP_DEBUG' ) && WP_DEBUG
				? 'Erreur DB : ' . $result->get_error_message()
				: 'Erreur lors de l\'enregistrement. Réessayez.';
			wp_send_json_error( [ 'message' => $msg ], 500 );
		}

		delete_transient( 'cfeb_events_upcoming' );

		if ( $voucher && 'confirme' === $statut_resa ) {
			CF_Vouchers::redeem( $voucher, (int) $result['id'] );
		}

		CF_Email::confirmation_user( $result, $event );
		CF_Email::notification_admin( $result, $event );

		if ( 'confirme' === $statut_resa && class_exists( 'CF_GoogleCalendar' ) && CF_GoogleCalendar::is_configured() ) {
			$gcal_type_id = (int) get_post_meta( $event_id, '_cfeb_appt_type_id', true );
			$gcal_enabled = $gcal_type_id
				? (bool) get_post_meta( $gcal_type_id, '_cfeb_appt_gcal_enabled', true )
				: (bool) get_option( 'cfeb_gcal_sync_all', 0 );
			if ( $gcal_enabled ) {
				CF_GoogleCalendar::sync_new_booking( CF_Booking::get( $result['id'] ), $event );
			}
		}

		$msg = 'liste_attente' === $statut_resa
			? 'Vous avez été ajouté·e à la liste d\'attente. Nous vous contacterons si une place se libère.'
			: 'Un email de confirmation a été envoyé à ' . $email . '.';

		wp_send_json_success( [
			'message'      => $msg,
			'statut'       => $statut_resa,
			'prenom'       => $prenom,
			'email'        => $email,
			'redirect_url' => $opts['confirmation_redirect'] ?? '',
		] );
	}

	/* ── Changement de statut (admin AJAX) ───────────────────────── */
	public static function statut_update() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_admin' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id     = absint( $_POST['id']     ?? 0 );
		$statut = sanitize_key( $_POST['statut'] ?? '' );
		$motif  = sanitize_textarea_field( wp_unslash( $_POST['motif'] ?? '' ) );
		if ( ! $id || ! $statut ) {
			wp_send_json_error( null, 422 );
		}

		if ( $motif && method_exists( 'CF_Booking', 'update_statut_with_motif' ) ) {
			CF_Booking::update_statut_with_motif( $id, $statut, $motif );
		} else {
			CF_Booking::update_statut( $id, $statut );
		}

		// Promouvoir la liste d'attente si une place se libère
		if ( 'annule' === $statut ) {
			$booking = CF_Booking::get( $id );
			if ( $booking ) {
				self::promote_waitlist( (int) $booking->event_id );
				// Supprimer l'événement Google Agenda si configuré
				if ( class_exists( 'CF_GoogleCalendar' ) && CF_GoogleCalendar::is_configured() ) {
					CF_GoogleCalendar::sync_cancel_booking( $id );
				}
			}
		}

		wp_send_json_success();
	}

	/* ── Promouvoir le premier en liste d'attente ─────────────────── */
	public static function promote_waitlist( int $event_id ) {
		if ( ! $event_id ) {
			return;
		}

		$statut = CF_CPT::compute_statut( $event_id );
		if ( 'complet' === $statut ) {
			return;
		}

		$waiting = CF_Booking::get_all( [
			'event_id' => $event_id,
			'statut'   => 'liste_attente',
			'per_page' => 1,
			'orderby'  => 'cree_le',
			'order'    => 'ASC',
		] );

		if ( empty( $waiting ) ) {
			return;
		}

		$first = $waiting[0];
		CF_Booking::update_statut( (int) $first->id, 'confirme' );
		CF_Email::promotion_liste_attente( (int) $first->id, get_post( $event_id ) );
	}

	/* ── Reprogrammation d'un événement (admin) ───────────────────── */
	public static function reschedule() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_admin' ) ) {
			wp_send_json_error( null, 403 );
		}

		$event_id     = absint( $_POST['event_id']     ?? 0 );
		$new_date     = sanitize_text_field( wp_unslash( $_POST['new_date']     ?? '' ) );
		$new_date_fin = sanitize_text_field( wp_unslash( $_POST['new_date_fin'] ?? '' ) );
		$notify       = ! empty( $_POST['notify_participants'] );

		if ( ! $event_id || ! $new_date ) {
			wp_send_json_error( [ 'message' => 'Données manquantes.' ], 422 );
		}

		$event = get_post( $event_id );
		if ( ! $event || CFEB_SLUG !== $event->post_type ) {
			wp_send_json_error( [ 'message' => 'Événement introuvable.' ], 404 );
		}

		update_post_meta( $event_id, '_cfeb_date_debut', sanitize_text_field( $new_date ) );
		if ( $new_date_fin ) {
			update_post_meta( $event_id, '_cfeb_date_fin', sanitize_text_field( $new_date_fin ) );
		}
		delete_transient( 'cfeb_events_upcoming' );

		if ( $notify ) {
			$confirmed = CF_Booking::get_all( [ 'event_id' => $event_id, 'statut' => 'confirme', 'per_page' => 9999 ] );
			foreach ( $confirmed as $b ) {
				CF_Email::reschedule_notification( (int) $b->id, $event, $new_date );
			}
		}

		wp_send_json_success( [ 'message' => 'Événement reprogrammé avec succès.' ] );
	}

	/* ── Filtrage AJAX événements (frontend) ─────────────────────── */
	public static function filter_events() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( null, 403 );
		}

		$args = [
			'categorie'    => sanitize_text_field( wp_unslash( $_POST['categorie']    ?? '' ) ),
			'tag'          => sanitize_text_field( wp_unslash( $_POST['tag']          ?? '' ) ),
			'statut_event' => sanitize_key(        wp_unslash( $_POST['statut_event'] ?? '' ) ),
			'date_debut'   => sanitize_text_field( wp_unslash( $_POST['date_debut']   ?? '' ) ),
			'date_fin'     => sanitize_text_field( wp_unslash( $_POST['date_fin']     ?? '' ) ),
			'passe'        => ! empty( $_POST['passe'] ),
			'per_page'     => min( absint( $_POST['per_page'] ?? 10 ), 100 ),
			'q'            => sanitize_text_field( wp_unslash( $_POST['q']            ?? '' ) ),
		];

		$events = CF_Frontend::get_events( $args );

		if ( empty( $events ) ) {
			wp_send_json_success( [ 'html' => '<p class="cfeb-no-events">Aucun événement trouvé.</p>' ] );
		}

		$html = '';
		foreach ( $events as $post ) {
			$html .= CF_Frontend::render_event_card( $post );
		}

		wp_send_json_success( [ 'html' => $html ] );
	}

	/* ── Reprogrammation cliente par token ───────────────────────── */
	public static function customer_reschedule() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( [ 'message' => 'Erreur de sécurité, veuillez recharger la page.' ], 403 );
		}

		$token        = sanitize_text_field( wp_unslash( $_POST['token']        ?? '' ) );
		$new_event_id = absint( $_POST['new_event_id'] ?? 0 );

		if ( ! $token || ! $new_event_id ) {
			wp_send_json_error( [ 'message' => 'Données manquantes.' ], 422 );
		}

		$old_booking = CF_Booking::get_by_token( $token );
		if ( ! $old_booking ) {
			wp_send_json_error( [ 'message' => 'Réservation introuvable.' ], 404 );
		}
		if ( 'confirme' !== $old_booking->statut ) {
			wp_send_json_error( [ 'message' => 'Seules les réservations confirmées peuvent être reportées.' ], 400 );
		}

		$new_event = get_post( $new_event_id );
		if ( ! $new_event || 'publish' !== $new_event->post_status || CFEB_SLUG !== $new_event->post_type ) {
			wp_send_json_error( [ 'message' => 'Événement de destination introuvable.' ], 404 );
		}

		$new_meta = CF_CPT::get_meta( $new_event_id );
		if ( ! $new_meta['reschedule_allowed'] ) {
			wp_send_json_error( [ 'message' => 'Cet événement ne permet pas le report.' ], 400 );
		}

		$dispo = CF_CPT::get_dispo( $new_event_id );
		if ( 0 === $dispo ) {
			wp_send_json_error( [ 'message' => 'Aucune place disponible sur cette date.' ], 400 );
		}

		// Annuler l'ancienne réservation
		CF_Booking::update_statut( (int) $old_booking->id, 'annule' );

		// Créer la nouvelle réservation
		$new_result = CF_Booking::add( [
			'event_id'     => $new_event_id,
			'user_id'      => absint( $old_booking->user_id ?? 0 ),
			'prenom'       => $old_booking->prenom,
			'nom'          => $old_booking->nom,
			'email'        => $old_booking->email,
			'telephone'    => $old_booking->telephone,
			'nb_places'    => $old_booking->nb_places,
			'statut'       => 'confirme',
			'notes'        => $old_booking->notes,
			'champs_perso' => $old_booking->champs_perso,
		] );

		if ( is_wp_error( $new_result ) ) {
			wp_send_json_error( [ 'message' => 'Erreur lors du report. Veuillez réessayer.' ], 500 );
		}

		delete_transient( 'cfeb_events_upcoming' );

		// Emails
		CF_Email::annulation_user( (array) $old_booking, get_post( $old_booking->event_id ) );
		CF_Email::reschedule_confirmation( $old_booking, $new_result, $new_event );

		wp_send_json_success( [ 'message' => 'Votre réservation a été reportée avec succès. Un email de confirmation vous a été envoyé.' ] );
	}

	/* ── Créneaux d'une semaine (navigation calendrier frontend) ─── */
	public static function week_slots() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( null, 403 );
		}
		$type_ids   = CF_ApptType::normalize_type_ids( sanitize_text_field( wp_unslash( $_POST['type_id'] ?? '' ) ) );
		$week_start = sanitize_text_field( wp_unslash( $_POST['week_start'] ?? '' ) );
		if ( empty( $type_ids ) || ! $week_start ) {
			wp_send_json_error( null, 422 );
		}
		$html = CF_ApptType::render_week_grid( $type_ids, $week_start );
		wp_send_json_success( [ 'html' => $html ] );
	}

	public static function more_slots() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( null, 403 );
		}
		$type_ids  = CF_ApptType::normalize_type_ids( sanitize_text_field( wp_unslash( $_POST['type_id'] ?? '' ) ) );
		$from_date = sanitize_text_field( wp_unslash( $_POST['from_date'] ?? '' ) );
		$limit     = min( 50, max( 1, absint( $_POST['limit'] ?? 8 ) ) );
		if ( empty( $type_ids ) ) {
			wp_send_json_error( null, 422 );
		}
		$html = CF_ApptType::render_slot_list( $type_ids, $from_date, $limit );
		wp_send_json_success( [ 'html' => $html ] );
	}

	/* ── Créneaux : page précédente de la vue liste ──────────────── */
	public static function prev_slots() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'cfeb_front' ) ) {
			wp_send_json_error( null, 403 );
		}
		$type_ids    = CF_ApptType::normalize_type_ids( sanitize_text_field( wp_unslash( $_POST['type_id'] ?? '' ) ) );
		$before_date = sanitize_text_field( wp_unslash( $_POST['before_date'] ?? '' ) );
		$limit       = min( 50, max( 1, absint( $_POST['limit'] ?? 8 ) ) );
		if ( empty( $type_ids ) || ! $before_date ) {
			wp_send_json_error( null, 422 );
		}
		$html = CF_ApptType::render_slot_list( $type_ids, null, $limit, 730, $before_date );
		wp_send_json_success( [ 'html' => $html ] );
	}

	public static function statut_noop() {
		wp_send_json_error( null, 403 );
	}
}
