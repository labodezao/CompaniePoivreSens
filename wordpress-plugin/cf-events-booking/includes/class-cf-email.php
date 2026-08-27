<?php
/**
 * Emails de confirmation et d'annulation.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Email {

	/**
	 * URL de la visio pour une réservation (type de RDV en mode créneaux).
	 *
	 * Priorité à l'URL fixe si elle est renseignée sur le type. Sinon, pour
	 * Jitsi Meet (aucun compte ni API requis), la salle est dérivée du
	 * CRÉNEAU — type de RDV + heure de début — et non de la réservation.
	 *
	 * C'est le point important : dériver la salle du jeton de réservation
	 * donnait une adresse différente à chaque personne, y compris à celles
	 * inscrites au MÊME créneau. Sur une séance de groupe, chacun se
	 * retrouvait seul dans sa propre salle.
	 *
	 * Le nom de salle reste imprévisible : il est signé avec un secret
	 * propre au site (wp_salt), donc connaître le type de RDV et l'horaire
	 * ne permet pas de le deviner. Et comme il change à chaque créneau, un
	 * ancien client ne peut pas rejoindre les visios suivantes — ce que la
	 * version précédente cherchait à éviter, sans authentification côté
	 * serveur public meet.jit.si.
	 *
	 * Un type de RDV peut préférer UNE SEULE salle, toujours la même
	 * (online_room_mode = fixe) : le créneau sort alors de la signature. On
	 * y gagne une adresse stable, on y perd le renouvellement automatique —
	 * à charge pour l'admin de verrouiller la salle, Jitsi effaçant ses
	 * réglages dès que le dernier participant sort.
	 */
	private static function resolve_visio_url( array $m, array $booking ): string {
		$url = trim( (string) ( $m['online_url'] ?? '' ) );
		if ( $url ) {
			return $url;
		}
		if ( 'jitsi' !== ( $m['online_platform'] ?? '' ) ) {
			return '';
		}

		$type_id = (int) ( $booking['appt_type_id'] ?? 0 );
		$slot    = (string) ( $booking['slot_debut'] ?? '' );

		$salle_fixe = 'fixe' === ( $m['online_room_mode'] ?? 'creneau' );

		if ( $type_id && ( $slot || $salle_fixe ) ) {
			// Le nom de salle reprend le slug du type de RDV, pour qu'il soit
			// lisible dans le mail et dans l'historique Jitsi. L'empreinte qui
			// suit reste indispensable : le serveur public meet.jit.si n'ayant
			// aucune authentification, un nom devinable (slug + horaire, tous
			// deux publics) laisserait entrer n'importe qui.
			$signe     = $salle_fixe ? (string) $type_id : $type_id . '|' . $slot;
			$empreinte = substr(
				hash_hmac( 'sha256', $signe, wp_salt( 'cfeb_visio' ) ),
				0,
				20
			);
			return 'https://meet.jit.si/' . self::visio_prefixe( $type_id ) . $empreinte;
		}

		// Repli : réservation sans créneau (cas ancien ou import). On retombe
		// sur le jeton, quitte à ce que la salle soit propre à la personne.
		if ( ! empty( $booking['token'] ) ) {
			$jeton = substr( preg_replace( '/[^a-zA-Z0-9]/', '', $booking['token'] ), 0, 20 );
			return $jeton ? 'https://meet.jit.si/' . self::visio_prefixe( $type_id ) . $jeton : '';
		}
		return '';
	}

	/**
	 * Début du nom de salle : le slug du type de RDV, nettoyé et raccourci.
	 * Toujours terminé par un tiret, et jamais vide (repli « CF- ») pour que
	 * la salle reste valide si le type a été supprimé ou n'a pas de slug.
	 */
	private static function visio_prefixe( int $type_id ): string {
		$slug = $type_id ? (string) get_post_field( 'post_name', $type_id ) : '';
		$slug = preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $slug ) ) );
		$slug = trim( (string) $slug, '-' );

		if ( ! $slug ) {
			return 'CF-';
		}
		if ( strlen( $slug ) > 40 ) {
			$slug = rtrim( substr( $slug, 0, 40 ), '-' );
		}
		return $slug . '-';
	}

	/* ── Confirmation utilisateur ────────────────────────────────── */
	public static function confirmation_user( array $booking, $event ) {
		$opts = CF_Admin::get_options();

		// Slot-based booking (pas d'événement post)
		if ( null === $event ) {
			$type_id   = (int) ( $booking['appt_type_id'] ?? 0 );
			$type_post = $type_id ? get_post( $type_id ) : null;
			$m         = ( $type_id && class_exists( 'CF_ApptType' ) ) ? CF_ApptType::get_meta( $type_id ) : [];
			$titre     = $type_post ? $type_post->post_title : 'Rendez-vous';
			$date      = ! empty( $booking['slot_debut'] ) ? date_i18n( 'l j F Y \à H\hi', strtotime( $booking['slot_debut'] ) ) : '';
			$prix      = (float) ( $m['prix'] ?? 0 );

			// Modalité effectivement choisie (le client tranche si le type propose
			// les deux ; sinon c'est la modalité fixe du type) → lieu / lien visio.
			$booking_modalite = $booking['modalite'] ?? '';
			if ( '' === $booking_modalite ) {
				$booking_modalite = $m['modalite'] ?? 'presentiel';
			}
			$visio_url  = '';
			$visio_code = '';
			if ( 'distanciel' === $booking_modalite ) {
				$platform_labels = [ 'zoom' => 'Zoom', 'meet' => 'Google Meet', 'teams' => 'Microsoft Teams', 'jitsi' => 'Jitsi Meet', 'autre' => 'visio' ];
				$lieu      = 'À distance (' . ( $platform_labels[ $m['online_platform'] ?? '' ] ?? 'visio' ) . ')';
				$visio_url  = self::resolve_visio_url( $m, $booking );
				$visio_code = $visio_url ? trim( (string) ( $m['online_code'] ?? '' ) ) : '';
			} else {
				// Adresse effective déjà résolue à la réservation (voir
				// CF_Ajax::reserver() → CF_ApptType::resolve_slot_adresse()) :
				// le lieu propre à ce créneau si plusieurs lieux de
				// consultation sont enregistrés, sinon le lieu par défaut du
				// type, sinon l'adresse par défaut globale (repli, au cas où
				// la réservation daterait d'avant l'ajout de ce champ).
				$lieu = ! empty( $booking['adresse'] ) ? $booking['adresse'] : ( $m['lieu'] ?: CF_Admin::get_options()['default_lieu'] ?? '' );
			}

			$vars      = [
				'{prenom}'     => $booking['prenom'],
				'{nom}'        => $booking['nom'],
				'{email}'      => $booking['email'],
				'{evenement}'  => $titre,
				'{date}'       => $date,
				'{lieu}'       => $lieu,
				'{adresse}'    => 'presentiel' === $booking_modalite ? $lieu : '',
				'{lien_visio}' => $visio_url ? '<a href="' . esc_url( $visio_url ) . '">' . esc_html( $visio_url ) . '</a>' : '',
				'{code_visio}' => esc_html( $visio_code ),
			];
			// Message de confirmation propre au type de RDV s'il est renseigné
			// (CF Réservations → Types RDV → Notifications), avec une variante
			// propre au distanciel si elle existe (un type "les deux" peut avoir
			// un mail différent selon la modalité choisie) — sinon repli sur le
			// message présentiel/par défaut, puis sur le message global.
			if ( 'distanciel' === $booking_modalite && ! empty( $m['email_message_distanciel'] ) ) {
				$msg_template = $m['email_message_distanciel'];
			} elseif ( ! empty( $m['email_message'] ) ) {
				$msg_template = $m['email_message'];
			} else {
				$msg_template = $opts['confirmation_msg'];
			}
			$msg_perso    = str_replace( array_keys( $vars ), array_values( $vars ), $msg_template );
			$cancel_url = add_query_arg( [ 'cfeb_annuler' => 1, 'cfeb_token' => $booking['token'] ], home_url( '/mes-reservations/' ) );
			$subject   = '✅ Réservation confirmée — ' . $titre;
			$body      = self::template( [
				'titre'       => 'Réservation confirmée !',
				'prenom'      => $booking['prenom'],
				// wp_kses_post (pas esc_html) : ce message est un contenu géré
				// côté admin (réglage global ou champ du type de RDV), pas une
				// saisie de visiteur — le html qu'il contient (titres, listes,
				// liens, gras...) doit être rendu, pas affiché tel quel en texte
				// brut. wpautop (pas nl2br) pour rester HTML-aware : nl2br
				// insérerait des <br> à l'intérieur des balises de bloc (ul, h2...).
				'contenu'     => wpautop( wp_kses_post( $msg_perso ) ),
				'event_titre' => $titre,
				'event_date'  => $date,
				'event_lieu'  => $lieu,
				'event_prix'  => $prix > 0 ? number_format( $prix, 2, ',', ' ' ) . ' €' : 'Gratuit',
				// Jamais de repli sur l'accueil du site : un lien qui ne mène
				// nulle part en particulier vaut moins que pas de lien du tout
				// (voir self::template(), qui masque le bouton si vide).
				'event_url'   => $m['page_url'] ?? '',
				'cancel_url'  => $cancel_url,
				'nb_places'   => $booking['nb_places'],
				'is_attente'  => 'liste_attente' === $booking['statut'],
				'acompte_url' => $booking['acompte_url'] ?? '',
				'visio_url'   => $visio_url,
				'visio_code'  => $visio_code,
			] );
			self::send( $booking['email'], $subject, $body, $opts );
			return;
		}

		$meta  = CF_CPT::get_meta( $event->ID );
		$date  = $meta['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$lieu  = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );
		$prix  = (float) $meta['prix'];

		$vars = [
			'{prenom}'     => $booking['prenom'],
			'{nom}'        => $booking['nom'],
			'{email}'      => $booking['email'],
			'{evenement}'  => $event->post_title,
			'{date}'       => $date,
			'{lieu}'       => $lieu,
			'{adresse}'    => $meta['lieu'] ?: '',
			'{lien_visio}' => ! empty( $meta['lien_visio'] ) ? '<a href="' . esc_url( $meta['lien_visio'] ) . '">' . esc_html( $meta['lien_visio'] ) . '</a>' : '',
			// Pas de code d'accès sur les événements : la variable est tout de
			// même remplacée, sinon elle s'afficherait telle quelle si le
			// message global de confirmation l'utilise.
			'{code_visio}' => '',
		];

		$msg_perso = $opts['confirmation_msg'];
		$msg_perso = str_replace( array_keys( $vars ), array_values( $vars ), $msg_perso );

		$cancel_url = add_query_arg( [
			'cfeb_annuler' => 1,
			'cfeb_token'   => $booking['token'],
		], get_permalink( $event->ID ) ?: home_url( '/mes-reservations/' ) );

		$subject = '✅ Réservation confirmée — ' . $event->post_title;

		$body = self::template( [
			'titre'       => 'Réservation confirmée !',
			'prenom'      => $booking['prenom'],
			'contenu'     => wpautop( wp_kses_post( $msg_perso ) ),
			'event_titre' => $event->post_title,
			'event_date'  => $date,
			'event_lieu'  => $lieu,
			'event_prix'  => $prix > 0 ? number_format( $prix, 2, ',', ' ' ) . ' €' : 'Gratuit',
			'event_url'   => get_permalink( $event->ID ),
			'cancel_url'  => $cancel_url,
			'nb_places'   => $booking['nb_places'],
			'is_attente'  => 'liste_attente' === $booking['statut'],
		] );

		// Joindre le fichier iCal si l'événement est confirmé
		if ( 'liste_attente' !== $booking['statut'] && class_exists( 'CF_Ical' ) ) {
			$ics_string = CF_Ical::ical_attachment( $event->ID );
			if ( $ics_string ) {
				$ics_file = self::write_temp_ical( $ics_string, $event->ID );
				if ( $ics_file ) {
					self::send_with_attachments( $booking['email'], $subject, $body, $opts, [ $ics_file ] );
					if ( is_writable( $ics_file ) ) {
						unlink( $ics_file );
					}
					return;
				}
			}
		}

		self::send( $booking['email'], $subject, $body, $opts );
	}

	/* ── Promotion liste d'attente ───────────────────────────────── */
	public static function promotion_liste_attente( int $booking_id, $event ) {
		if ( ! $event ) {
			return;
		}
		$booking = CF_Booking::get( $booking_id );
		if ( ! $booking ) {
			return;
		}
		$opts    = CF_Admin::get_options();
		$meta    = CF_CPT::get_meta( $event->ID );
		$date    = $meta['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$lieu    = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );
		$subject = "\xf0\x9f\x8e\x89 Une place s'est lib\xe9r\xe9e \xe2\x80\x94 " . $event->post_title;

		$tpl_custom = $opts['liste_attente_msg'] ?? '';
		if ( $tpl_custom ) {
			$vars = [
				'{prenom}'    => $booking->prenom,
				'{nom}'       => $booking->nom,
				'{evenement}' => $event->post_title,
				'{date}'      => $date,
				'{lieu}'      => $lieu,
			];
			$contenu = wpautop( wp_kses_post( str_replace( array_keys( $vars ), array_values( $vars ), $tpl_custom ) ) );
			// <div>, pas <p> : $contenu peut contenir des balises de bloc
			// (titres, listes...) depuis l'éditeur WYSIWYG — un <p> autour
			// serait invalide et rendrait mal selon les clients mail.
			$body    = self::simple_template( $subject, '<div>' . $contenu . '</div>' );
		} else {
			$detail = '<p>Bonjour <strong>' . esc_html( $booking->prenom ) . '</strong>,</p>'
				. '<p>Bonne nouvelle ! Une place s\'est lib\xe9r\xe9e pour <strong>' . esc_html( $event->post_title ) . '</strong>'
				. ( $date ? ' le ' . esc_html( $date ) : '' ) . '.</p>'
				. '<p>Votre r\xe9servation est maintenant <strong>confirm\xe9e</strong>.</p>'
				. ( $lieu ? '<p>Lieu : ' . esc_html( $lieu ) . '</p>' : '' );
			$body = self::simple_template( $subject, $detail );
		}

		// Joindre iCal
		if ( class_exists( 'CF_Ical' ) ) {
			$ics_string = CF_Ical::ical_attachment( $event->ID );
			if ( $ics_string ) {
				$ics_file = self::write_temp_ical( $ics_string, $event->ID );
				if ( $ics_file ) {
					self::send_with_attachments( $booking->email, $subject, $body, $opts, [ $ics_file ] );
					if ( is_writable( $ics_file ) ) {
						unlink( $ics_file );
					}
					return;
				}
			}
		}
		self::send( $booking->email, $subject, $body, $opts );
	}

	/* ── Notification reprogrammation ───────────────────────────── */
	public static function reschedule_notification( int $booking_id, $event, string $new_date ) {
		$booking = CF_Booking::get( $booking_id );
		if ( ! $booking ) {
			return;
		}
		$opts    = CF_Admin::get_options();
		$date    = date_i18n( 'l j F Y à H\hi', strtotime( $new_date ) );
		$subject = '📅 Changement de date — ' . $event->post_title;
		$detail  = '<p>Bonjour <strong>' . esc_html( $booking->prenom ) . '</strong>,</p>'
			. '<p>L\'événement <strong>' . esc_html( $event->post_title ) . '</strong> a été reprogrammé.</p>'
			. '<p>Nouvelle date : <strong>' . esc_html( $date ) . '</strong></p>'
			. '<p><a href="' . esc_url( get_permalink( $event->ID ) ) . '">Voir l\'événement</a></p>';
		$body    = self::simple_template( $subject, $detail );
		self::send( $booking->email, $subject, $body, $opts );
	}

	/* ── Notification admin ──────────────────────────────────────── */
	public static function notification_admin( array $booking, $event ) {
		$opts = CF_Admin::get_options();

		// Slot-based booking
		if ( null === $event ) {
			$type_id   = (int) ( $booking['appt_type_id'] ?? 0 );
			$type_post = $type_id ? get_post( $type_id ) : null;
			$m         = ( $type_id && class_exists( 'CF_ApptType' ) ) ? CF_ApptType::get_meta( $type_id ) : [];
			$titre     = $type_post ? $type_post->post_title : 'Rendez-vous';
			$date      = ! empty( $booking['slot_debut'] ) ? date_i18n( 'l j F Y \à H\hi', strtotime( $booking['slot_debut'] ) ) : '';
			$statut_label = 'liste_attente' === $booking['statut'] ? '⏳ Liste d\'attente' : '✅ Confirmé';
			$subject   = '📋 Nouvelle réservation — ' . $titre;
			$admin_url = admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-reservations' );
			$visio_url  = 'distanciel' === ( $booking['modalite'] ?? '' ) ? self::resolve_visio_url( $m, $booking ) : '';
			$visio_code = $visio_url ? trim( (string) ( $m['online_code'] ?? '' ) ) : '';
			$detail    = "
				<table style='width:100%;border-collapse:collapse;font-size:15px;'>
					<tr><td style='padding:6px 0;color:#666;width:140px;'>Nom</td><td><strong>" . esc_html( $booking['prenom'] . ' ' . $booking['nom'] ) . "</strong></td></tr>
					<tr><td style='padding:6px 0;color:#666;'>Email</td><td><a href='mailto:" . esc_attr( $booking['email'] ) . "'>" . esc_html( $booking['email'] ) . "</a></td></tr>
					<tr><td style='padding:6px 0;color:#666;'>Téléphone</td><td>" . esc_html( $booking['telephone'] ?: '—' ) . "</td></tr>
					<tr><td style='padding:6px 0;color:#666;'>Places</td><td>" . (int) $booking['nb_places'] . "</td></tr>
					<tr><td style='padding:6px 0;color:#666;'>Statut</td><td>" . esc_html( $statut_label ) . "</td></tr>
					<tr><td style='padding:6px 0;color:#666;'>Type RDV</td><td>" . esc_html( $titre ) . " — " . esc_html( $date ) . "</td></tr>
					" . ( ! empty( $booking['modalite'] ) ? "<tr><td style='padding:6px 0;color:#666;'>Modalité</td><td>" . esc_html( 'distanciel' === $booking['modalite'] ? 'À distance' : 'Présentiel' ) . "</td></tr>" : '' ) . "
					" . ( ! empty( $booking['adresse'] ) ? "<tr><td style='padding:6px 0;color:#666;'>Adresse</td><td>" . esc_html( $booking['adresse'] ) . "</td></tr>" : '' ) . "
					" . ( $visio_url ? "<tr><td style='padding:6px 0;color:#666;'>Lien visio</td><td><a href='" . esc_url( $visio_url ) . "'>" . esc_html( $visio_url ) . "</a></td></tr>" : '' ) . "
					" . ( $visio_code ? "<tr><td style='padding:6px 0;color:#666;'>Code d'accès</td><td><strong>" . esc_html( $visio_code ) . "</strong></td></tr>" : '' ) . "
					" . ( $booking['notes'] ? "<tr><td style='padding:6px 0;color:#666;'>Message</td><td>" . esc_html( $booking['notes'] ) . "</td></tr>" : '' ) . "
				</table>
				<p style='margin-top:16px;'><a href='" . esc_url( $admin_url ) . "' style='background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;'>Gérer les réservations →</a></p>
			";
			$body = self::simple_template( $subject, $detail );
			self::send( $opts['admin_email'], $subject, $body, $opts );
			$extra = $opts['admin_emails_extra'] ?? '';
			if ( $extra ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $extra ) ) ) as $extra_email ) {
					if ( is_email( $extra_email ) ) {
						self::send( $extra_email, $subject, $body, $opts );
					}
				}
			}
			return;
		}

		$meta  = CF_CPT::get_meta( $event->ID );
		$date  = $meta['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$lieu  = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );

		$statut_label = 'liste_attente' === $booking['statut'] ? '⏳ Liste d\'attente' : '✅ Confirmé';

		$subject = '📋 Nouvelle réservation — ' . $event->post_title;
		$admin_url = admin_url( 'admin.php?page=cfeb-reservations&event_id=' . $event->ID );

		$detail = "
			<table style='width:100%;border-collapse:collapse;font-size:15px;'>
				<tr><td style='padding:6px 0;color:#666;width:140px;'>Nom</td><td><strong>" . esc_html( $booking['prenom'] . ' ' . $booking['nom'] ) . "</strong></td></tr>
				<tr><td style='padding:6px 0;color:#666;'>Email</td><td><a href='mailto:" . esc_attr( $booking['email'] ) . "'>" . esc_html( $booking['email'] ) . "</a></td></tr>
				<tr><td style='padding:6px 0;color:#666;'>Téléphone</td><td>" . esc_html( $booking['telephone'] ?: '—' ) . "</td></tr>
				<tr><td style='padding:6px 0;color:#666;'>Places</td><td>" . (int) $booking['nb_places'] . "</td></tr>
				<tr><td style='padding:6px 0;color:#666;'>Statut</td><td>" . esc_html( $statut_label ) . "</td></tr>
				<tr><td style='padding:6px 0;color:#666;'>Événement</td><td>" . esc_html( $event->post_title ) . " — " . esc_html( $date ) . "</td></tr>
				" . ( $booking['notes'] ? "<tr><td style='padding:6px 0;color:#666;'>Message</td><td>" . esc_html( $booking['notes'] ) . "</td></tr>" : '' ) . "
			</table>
			<p style='margin-top:16px;'><a href='" . esc_url( $admin_url ) . "' style='background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;'>Gérer les réservations →</a></p>
		";

		$body = self::simple_template( $subject, $detail );
		self::send( $opts['admin_email'], $subject, $body, $opts );

		// Emails admin supplémentaires
		$extra = $opts['admin_emails_extra'] ?? '';
		if ( $extra ) {
			foreach ( array_filter( array_map( 'trim', explode( ',', $extra ) ) ) as $extra_email ) {
				if ( is_email( $extra_email ) ) {
					self::send( $extra_email, $subject, $body, $opts );
				}
			}
		}
	}

	/* ── Email de confirmation après reprogrammation (client) ───── */
	public static function reschedule_confirmation( $old_booking, $new_booking, $new_event ) {
		if ( ! $new_event ) {
			return;
		}
		$opts    = CF_Admin::get_options();
		$meta    = CF_CPT::get_meta( $new_event->ID );
		$date    = $meta['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$lieu    = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );
		$prenom  = is_array( $new_booking ) ? ( $new_booking['prenom'] ?? '' ) : ( $new_booking->prenom ?? '' );
		$email   = is_array( $new_booking ) ? ( $new_booking['email'] ?? '' ) : ( $new_booking->email ?? '' );
		$token   = is_array( $new_booking ) ? ( $new_booking['token'] ?? '' ) : ( $new_booking->token ?? '' );

		$cancel_url = add_query_arg( [
			'cfeb_annuler' => 1,
			'cfeb_token'   => $token,
		], get_permalink( $new_event->ID ) ?: home_url( '/mes-reservations/' ) );

		$subject = '📅 Votre réservation a été reportée — ' . $new_event->post_title;
		$detail  = '<p>Bonjour <strong>' . esc_html( $prenom ) . '</strong>,</p>'
			. '<p>Votre réservation a bien été reportée sur une nouvelle date.</p>'
			. '<table style="width:100%;border-collapse:collapse;font-size:15px;">'
			. '<tr><td style="padding:4px 0;color:#666;width:120px;">Événement</td><td><strong>' . esc_html( $new_event->post_title ) . '</strong></td></tr>'
			. ( $date ? '<tr><td style="padding:4px 0;color:#666;">Nouvelle date</td><td><strong>' . esc_html( $date ) . '</strong></td></tr>' : '' )
			. ( $lieu ? '<tr><td style="padding:4px 0;color:#666;">Lieu</td><td>' . esc_html( $lieu ) . '</td></tr>' : '' )
			. '</table>'
			. '<p style="margin-top:16px;">'
			. '<a href="' . esc_url( get_permalink( $new_event->ID ) ) . '" style="background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;margin-right:8px;">Voir l\'événement</a>'
			. '<a href="' . esc_url( $cancel_url ) . '" style="background:#f3f4f6;color:#6b7280;padding:10px 20px;border-radius:4px;text-decoration:none;">Annuler</a>'
			. '</p>';

		$body = self::simple_template( $subject, $detail );
		self::send( $email, $subject, $body, $opts );
	}

	/* ── Confirmation annulation utilisateur ────────────────────── */
	public static function annulation_user( array $booking, $event ) {
		if ( ! $event ) return;
		$opts    = CF_Admin::get_options();
		$meta    = CF_CPT::get_meta( $event->ID );
		$date    = $meta['date_debut'] ? date_i18n( 'l j F Y \xc0 H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$subject = "\xe2\x9d\x8c Annulation confirm\xe9e \xe2\x80\x94 " . $event->post_title;

		$tpl_custom = $opts['annulation_msg'] ?? '';
		if ( $tpl_custom ) {
			$vars = [
				'{prenom}'    => $booking['prenom'],
				'{nom}'       => $booking['nom'],
				'{evenement}' => $event->post_title,
				'{date}'      => $date,
			];
			$contenu = wpautop( wp_kses_post( str_replace( array_keys( $vars ), array_values( $vars ), $tpl_custom ) ) );
			// <div>, pas <p> : $contenu peut contenir des balises de bloc
			// (titres, listes...) depuis l'éditeur WYSIWYG — un <p> autour
			// serait invalide et rendrait mal selon les clients mail.
			$body    = self::simple_template( $subject, '<div>' . $contenu . '</div>' );
		} else {
			$detail = '<p>Bonjour ' . esc_html( $booking['prenom'] ) . ',</p>'
				. '<p>Votre r\xe9servation pour <strong>' . esc_html( $event->post_title ) . '</strong> a bien \xe9t\xe9 annul\xe9e.</p>'
				. '<p>Si vous souhaitez vous r\xe9inscrire, rendez-vous sur notre site.</p>';
			$body = self::simple_template( $subject, $detail );
		}

		self::send( $booking['email'], $subject, $body, $opts );
	}

	/* ── Envoi ───────────────────────────────────────────────────── */
	private static function send( $to, $subject, $body, $opts ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $opts['from_name'] . ' <' . $opts['from_email'] . '>',
		];
		wp_mail( $to, $subject, $body, $headers );
	}

	/* ── Envoi avec pièce(s) jointe(s) ──────────────────────────── */
	private static function send_with_attachments( $to, $subject, $body, $opts, array $attachments ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $opts['from_name'] . ' <' . $opts['from_email'] . '>',
		];
		wp_mail( $to, $subject, $body, $headers, $attachments );
	}

	/* ── Écriture iCal dans fichier temporaire ───────────────────── */
	private static function write_temp_ical( string $ics_string, int $event_id ): string {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'cfeb-ical/';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$file = $dir . 'event-' . $event_id . '-' . wp_generate_password( 8, false ) . '.ics';
		if ( false === file_put_contents( $file, $ics_string ) ) {
			return '';
		}
		return $file;
	}

	/* ── Template email complet (confirmation) ───────────────────── */
	private static function template( array $d ) {
		$site   = get_bloginfo( 'name' );
		$logo   = get_site_icon_url( 64 );
		$titre  = $d['is_attente'] ? '⏳ Liste d\'attente enregistrée' : '✅ Réservation confirmée !';
		$badge  = $d['is_attente']
			? '<span style="background:#f59e0b;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;">Liste d\'attente</span>'
			: '<span style="background:#22c55e;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;">Confirmé</span>';

		return '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:linear-gradient(135deg,#1a3c5e,#2e6ea6);padding:32px 40px;text-align:center;">
    ' . ( $logo ? '<img src="' . esc_url( $logo ) . '" width="48" alt="" style="border-radius:8px;margin-bottom:12px;display:block;margin:0 auto 12px;"><br>' : '' ) . '
    <h1 style="color:#fff;font-size:22px;margin:0 0 6px;">' . esc_html( $titre ) . '</h1>
    <p style="color:#a8c8e8;margin:0;font-size:15px;">' . esc_html( $site ) . '</p>
  </td></tr>
  <tr><td style="padding:32px 40px;">
    <p style="font-size:16px;color:#374151;">Bonjour <strong>' . esc_html( $d['prenom'] ) . '</strong>, ' . $badge . '</p>
    <div style="color:#6b7280;font-size:15px;line-height:1.6;">' . $d['contenu'] . '</div>

    <table width="100%" style="background:#f9fafb;border-radius:8px;padding:20px;margin:20px 0;font-size:15px;">
      <tr><td style="padding:6px 0;color:#374151;font-weight:600;font-size:17px;" colspan="2">📅 ' . esc_html( $d['event_titre'] ) . '</td></tr>
      ' . ( $d['event_date'] ? '<tr><td style="padding:4px 0;color:#6b7280;width:100px;">Date</td><td><strong>' . esc_html( $d['event_date'] ) . '</strong></td></tr>' : '' ) . '
      ' . ( $d['event_lieu'] ? '<tr><td style="padding:4px 0;color:#6b7280;">Lieu</td><td>' . esc_html( $d['event_lieu'] ) . '</td></tr>' : '' ) . '
      ' . ( ! empty( $d['visio_url'] ) ? '<tr><td style="padding:4px 0;color:#6b7280;">Lien visio</td><td><a href="' . esc_url( $d['visio_url'] ) . '" style="color:#2271b1;">' . esc_html( $d['visio_url'] ) . '</a></td></tr>' : '' ) . '
      ' . ( ! empty( $d['visio_code'] ) ? '<tr><td style="padding:4px 0;color:#6b7280;">Code d\'accès</td><td><strong>' . esc_html( $d['visio_code'] ) . '</strong></td></tr>' : '' ) . '
      <tr><td style="padding:4px 0;color:#6b7280;">Prix</td><td>' . esc_html( $d['event_prix'] ) . '</td></tr>
      <tr><td style="padding:4px 0;color:#6b7280;">Places</td><td>' . (int) $d['nb_places'] . '</td></tr>
    </table>

    ' . ( ! empty( $d['acompte_url'] ) ? '
    <div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:8px;padding:20px 24px;margin:20px 0;text-align:center;">
      <p style="margin:0 0 6px;font-weight:700;color:#92400e;font-size:15px;">🔒 Confirmer votre créneau</p>
      <p style="margin:0 0 16px;color:#78350f;font-size:14px;">Un acompte de ' . (int) get_option( 'cfeb_sumup_acompte', 30 ) . ' € est demandé pour sécuriser votre réservation. Il sera déduit du montant total en séance.</p>
      <a href="' . esc_url( $d['acompte_url'] ) . '" style="background:#f59e0b;color:#fff;padding:13px 30px;border-radius:6px;text-decoration:none;font-size:15px;font-weight:700;display:inline-block;">Payer l\'acompte de ' . (int) get_option( 'cfeb_sumup_acompte', 30 ) . ' € →</a>
    </div>' : '' ) . '

    <p style="text-align:center;margin:24px 0;">
      ' . ( ! empty( $d['event_url'] ) ? '<a href="' . esc_url( $d['event_url'] ) . '" style="background:#2271b1;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:15px;margin-right:8px;">Voir l\'événement</a>' : '' ) . '
      <a href="' . esc_url( $d['cancel_url'] ) . '" style="background:#f3f4f6;color:#6b7280;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:15px;">Annuler ma réservation</a>
    </p>
  </td></tr>
  <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;">
    © ' . gmdate( 'Y' ) . ' ' . esc_html( $site ) . ' · Cet email vous a été envoyé suite à votre réservation.
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';
	}

	/* ── Template simple (admin, annulation) ─────────────────────── */
	private static function simple_template( $titre, $contenu ) {
		$site = get_bloginfo( 'name' );
		return '<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:32px 16px;background:#f3f4f6;font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;">
<table width="600" style="max-width:600px;width:100%;background:#fff;border-radius:12px;margin:0 auto;padding:32px 40px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
<tr><td>
  <h2 style="color:#1a3c5e;margin-top:0;">' . esc_html( $titre ) . '</h2>
  ' . $contenu . '
  <hr style="margin:24px 0;border:none;border-top:1px solid #e5e7eb;">
  <p style="font-size:12px;color:#9ca3af;">' . esc_html( $site ) . ' — Système de réservations</p>
</td></tr>
</table>
</body></html>';
	}
}
