<?php
/**
 * Synchronisation Google Agenda (Calendar API v3)
 *
 * Flux OAuth 2.0 :
 *   1. Admin entre Client ID + Client Secret dans Paramètres → Google Agenda
 *   2. Clic sur "Autoriser avec Google" → redirect vers Google
 *   3. Callback → échange code contre access_token + refresh_token
 *   4. Lors de chaque réservation confirmée → création d'un événement dans l'agenda
 *   5. Lors d'une annulation → suppression de l'événement
 *
 * Stockage :
 *   - cfeb_gcal_settings  (wp_options) : client_id, client_secret, calendar_id
 *   - cfeb_gcal_token     (wp_options) : access_token, refresh_token, expires_at
 *   - cfeb_gcal_events    (wp_options) : tableau [booking_id => gcal_event_id]
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_GoogleCalendar {

	const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const OAUTH_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const CALENDAR_API    = 'https://www.googleapis.com/calendar/v3';
	// calendar.events seul ne donne pas accès à l'API freeBusy — il faut le scope complet.
	const SCOPE           = 'https://www.googleapis.com/auth/calendar';

	/* ══════════════════════════════════════════════════════════════
	   INIT
	══════════════════════════════════════════════════════════════ */
	public static function init() {
		add_action( 'admin_init',            [ __CLASS__, 'handle_oauth_callback' ] );
		add_action( 'admin_init',            [ __CLASS__, 'handle_disconnect' ] );
		add_action( 'wp_ajax_cfeb_gcal_test_freebusy',  [ __CLASS__, 'ajax_test_freebusy' ] );
		add_action( 'wp_ajax_cfeb_gcal_clear_cache',    [ __CLASS__, 'ajax_clear_busy_cache' ] );
	}

	/**
	 * Interprète une chaîne datetime naïve ("Y-m-d H:i:s" ou "Y-m-d\TH:i:s",
	 * sans indication de fuseau) comme une heure LOCALE DU SITE — celle que
	 * la personne a choisie dans le widget de réservation, celle affichée
	 * dans l'email de confirmation.
	 *
	 * Bug corrigé ici (créneaux envoyés à Google Agenda avec 1 à 3h de
	 * décalage) : le code appelait auparavant strtotime() sans fuseau
	 * explicite, ce qui utilise le fuseau PHP AMBIANT du serveur (réglage
	 * ini date.timezone) — potentiellement différent du fuseau du site WP,
	 * et pas forcément au courant de l'heure d'été. wp_timezone() (WP 5.3+)
	 * retourne le fuseau réellement configuré pour le site et gère l'heure
	 * d'été correctement.
	 *
	 * @return DateTime|null
	 */
	private static function local_datetime( $raw ) {
		$raw = trim( str_replace( 'T', ' ', (string) $raw ) );
		if ( '' === $raw ) {
			return null;
		}
		try {
			return new DateTime( $raw, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/** Supprime tous les transients freebusy en base. */
	public static function clear_busy_cache() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cfeb_gcal_busy_%' OR option_name LIKE '_transient_timeout_cfeb_gcal_busy_%'"
		);
	}

	public static function ajax_clear_busy_cache() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cfeb_gcal_test', 'nonce', false ) ) {
			wp_send_json_error( 'Permission refusée.', 403 );
		}
		self::clear_busy_cache();
		wp_send_json_success( 'Cache freebusy vidé.' );
	}

	/* ══════════════════════════════════════════════════════════════
	   PARAMÈTRES
	══════════════════════════════════════════════════════════════ */
	public static function get_settings() {
		return wp_parse_args( get_option( 'cfeb_gcal_settings', [] ), [
			'client_id'          => '',
			'client_secret'      => '',
			'calendar_id'        => 'primary',
			'extra_calendar_ids' => '',
		] );
	}

	/** Retourne tous les IDs d'agendas à vérifier (principal + supplémentaires). */
	public static function get_all_calendar_ids( $override_primary = '' ) {
		$s       = self::get_settings();
		$primary = $override_primary ?: $s['calendar_id'] ?: 'primary';
		$ids     = [ $primary ];
		if ( ! empty( $s['extra_calendar_ids'] ) ) {
			foreach ( array_filter( array_map( 'trim', explode( ',', $s['extra_calendar_ids'] ) ) ) as $extra ) {
				if ( $extra && ! in_array( $extra, $ids, true ) ) {
					$ids[] = $extra;
				}
			}
		}
		return $ids;
	}

	public static function save_settings( array $data ) {
		// Supprime les caractères invisibles (espaces zéro-largeur, etc.) fréquents lors d'un copier-coller
		$clean = static function( $v ) {
			return preg_replace( '/[^\x20-\x7E]/', '', trim( (string) $v ) );
		};
		update_option( 'cfeb_gcal_settings', [
			'client_id'          => $clean( $data['client_id']          ?? '' ),
			'client_secret'      => $clean( $data['client_secret']      ?? '' ),
			'calendar_id'        => sanitize_text_field( $data['calendar_id']        ?? 'primary' ),
			'extra_calendar_ids' => sanitize_text_field( $data['extra_calendar_ids'] ?? '' ),
		] );
	}

	/* ══════════════════════════════════════════════════════════════
	   ÉTAT DE LA CONNEXION
	══════════════════════════════════════════════════════════════ */
	public static function is_connected() {
		$token = get_option( 'cfeb_gcal_token', null );
		return ! empty( $token['refresh_token'] );
	}

	/** Vrai si Client ID + Secret sont renseignés (indépendamment du token OAuth). */
	public static function is_configured() {
		$s = self::get_settings();
		return ! empty( $s['client_id'] ) && ! empty( $s['client_secret'] );
	}

	public static function get_token() {
		return get_option( 'cfeb_gcal_token', null );
	}

	/* ══════════════════════════════════════════════════════════════
	   OAUTH — URL D'AUTORISATION
	══════════════════════════════════════════════════════════════ */
	public static function get_oauth_url() {
		$s = self::get_settings();
		if ( ! $s['client_id'] ) {
			return '';
		}

		// Réutilise le state existant s'il est encore valide, sinon en génère un nouveau.
		// Non lié à la session WordPress — fonctionne même si les cookies diffèrent.
		$state = get_transient( 'cfeb_gcal_oauth_state' );
		if ( ! $state ) {
			$state = bin2hex( random_bytes( 16 ) );
			set_transient( 'cfeb_gcal_oauth_state', $state, HOUR_IN_SECONDS );
		}

		$redirect_uri = self::get_redirect_uri();

		return self::OAUTH_AUTH_URL . '?' . http_build_query( [
			'client_id'     => $s['client_id'],
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => self::SCOPE,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		] );
	}

	public static function get_redirect_uri() {
		return add_query_arg( [
			'post_type'          => CFEB_SLUG,
			'page'               => 'cfeb-settings',
			'cfeb_gcal_callback' => '1',
		], admin_url( 'edit.php' ) );
	}

	/* ══════════════════════════════════════════════════════════════
	   OAUTH — TRAITEMENT DU CALLBACK
	══════════════════════════════════════════════════════════════ */
	public static function handle_oauth_callback() {
		if (
			! isset( $_GET['cfeb_gcal_callback'], $_GET['code'] ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		// Vérifier le state : accepte le transient (nouveau) OU le nonce WP (ancien, rétrocompat).
		$state          = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$expected_state = get_transient( 'cfeb_gcal_oauth_state' );

		$state_ok = $state && (
			( $expected_state && hash_equals( $expected_state, $state ) ) ||
			wp_verify_nonce( $state, 'cfeb_gcal_oauth' )
		);

		// Supprimer le transient seulement si c'est lui qui a validé (usage unique).
		if ( $expected_state && $state_ok && hash_equals( $expected_state, $state ) ) {
			delete_transient( 'cfeb_gcal_oauth_state' );
		}

		if ( ! $state_ok ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-settings',
				'tab'       => 'gcal',
				'gcal_err'  => 'invalid_state',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$s    = self::get_settings();

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, [
			'timeout' => 15,
			'body'    => [
				'code'          => $code,
				'client_id'     => $s['client_id'],
				'client_secret' => $s['client_secret'],
				'redirect_uri'  => self::get_redirect_uri(),
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-settings',
				'tab'       => 'gcal',
				'gcal_err'  => 'network',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-settings',
				'tab'       => 'gcal',
				'gcal_err'  => $body['error'] ?? 'token_error',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		update_option( 'cfeb_gcal_token', [
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ),
			'scope'         => $body['scope'] ?? self::SCOPE,
		] );
		self::clear_busy_cache();

		wp_safe_redirect( add_query_arg( [
			'post_type'      => CFEB_SLUG,
			'page'           => 'cfeb-settings',
			'tab'            => 'gcal',
			'gcal_connected' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   DÉCONNEXION
	══════════════════════════════════════════════════════════════ */
	public static function handle_disconnect() {
		if (
			! isset( $_GET['cfeb_gcal_disconnect'], $_GET['_wpnonce'] ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfeb_gcal_disconnect' ) ) {
			return;
		}
		delete_option( 'cfeb_gcal_token' );
		wp_safe_redirect( add_query_arg( [
			'post_type'      => CFEB_SLUG,
			'page'           => 'cfeb-settings',
			'tab'            => 'gcal',
			'gcal_connected' => '0',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   REFRESH TOKEN
	══════════════════════════════════════════════════════════════ */
	public static function refresh_access_token() {
		$token = self::get_token();
		if ( empty( $token['refresh_token'] ) ) {
			return false;
		}

		$s = self::get_settings();

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, [
			'timeout' => 10,
			'body'    => [
				'client_id'     => $s['client_id'],
				'client_secret' => $s['client_secret'],
				'refresh_token' => $token['refresh_token'],
				'grant_type'    => 'refresh_token',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return false;
		}

		$new_token = [
			'access_token'  => $body['access_token'],
			'refresh_token' => $token['refresh_token'],
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ),
		];
		update_option( 'cfeb_gcal_token', $new_token );
		return $new_token['access_token'];
	}

	/* ══════════════════════════════════════════════════════════════
	   TOKEN VALIDE (avec refresh auto)
	══════════════════════════════════════════════════════════════ */
	public static function get_valid_access_token() {
		$token = self::get_token();
		if ( ! $token ) {
			return null;
		}
		if ( time() >= ( (int) $token['expires_at'] - 60 ) ) {
			return self::refresh_access_token();
		}
		return $token['access_token'];
	}

	/* ══════════════════════════════════════════════════════════════
	   REQUÊTE API GÉNÉRIQUE
	══════════════════════════════════════════════════════════════ */
	private static function api_request( $method, $endpoint, $body = null ) {
		$access_token = self::get_valid_access_token();
		if ( ! $access_token ) {
			return new WP_Error( 'gcal_no_token', 'Google Calendar non connecté.' );
		}

		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::CALENDAR_API . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error( 'gcal_api_error', $body_decoded['error']['message'] ?? 'Erreur API' );
		}

		return $body_decoded;
	}

	/* ══════════════════════════════════════════════════════════════
	   CRÉER UN ÉVÉNEMENT GOOGLE AGENDA
	══════════════════════════════════════════════════════════════ */
	/**
	 * Crée un événement Google Agenda à partir d'une réservation.
	 *
	 * @param array    $booking   Données de la réservation (tableau ou stdClass)
	 * @param WP_Post  $event_post  Post WordPress de l'événement
	 * @param string   $calendar_id  ID du calendrier cible (défaut : 'primary')
	 * @param string   $title_tpl   Template du titre (ex: "{titre} — {prenom} {nom}")
	 */
	public static function create_event( $booking, $event_post, $calendar_id = 'primary', $title_tpl = '' ) {
		if ( ! self::is_connected() ) {
			return;
		}

		$booking    = (array) $booking;
		$event_meta = CF_CPT::get_meta( $event_post->ID );

		if ( ! $event_meta['date_debut'] ) {
			return;
		}

		$tz       = wp_timezone_string();
		$dt_debut = self::local_datetime( $event_meta['date_debut'] );
		if ( ! $dt_debut ) {
			return;
		}
		$duree_sec = 3600; // défaut 1h si pas de fin
		if ( $event_meta['date_fin'] ) {
			$dt_fin_brut = self::local_datetime( $event_meta['date_fin'] );
			if ( $dt_fin_brut ) {
				$duree_sec = max( 900, $dt_fin_brut->getTimestamp() - $dt_debut->getTimestamp() );
			}
		}
		$dt_fin   = ( clone $dt_debut )->modify( "+{$duree_sec} seconds" );
		$ts_debut = $dt_debut->getTimestamp();
		$ts_fin   = $dt_fin->getTimestamp();

		// Résoudre le titre
		$title_tpl = $title_tpl ?: '{titre} — {prenom} {nom}';
		$title     = strtr( $title_tpl, [
			'{titre}'  => $event_post->post_title,
			'{prenom}' => $booking['prenom'] ?? '',
			'{nom}'    => $booking['nom']    ?? '',
			'{email}'  => $booking['email']  ?? '',
			'{date}'   => date_i18n( 'd/m/Y', $ts_debut ),
			'{heure}'  => date_i18n( 'H\hi', $ts_debut ),
		] );

		$description = sprintf(
			"Réservation confirmée\nNom : %s %s\nEmail : %s\nTéléphone : %s\nPlaces : %d\n%s",
			$booking['prenom'] ?? '',
			$booking['nom']    ?? '',
			$booking['email']  ?? '',
			$booking['telephone'] ?? '',
			$booking['nb_places'] ?? 1,
			$booking['notes'] ? "\nNotes : " . $booking['notes'] : ''
		);

		$location = $event_meta['lieu'] ?: '';

		$event_body = [
			'summary'      => $title,
			'description'  => $description,
			// transparent = visible dans l'agenda mais ne bloque PAS le freebusy.
			// Seuls les événements personnels (opaques) doivent bloquer la disponibilité.
			'transparency' => 'transparent',
			// Format LOCAL nu (sans suffixe +00:00/Z) apparié au champ timeZone :
			// c'est la seule façon non ambiguë de dire à Google Agenda "cet
			// événement a lieu à cette heure d'horloge, dans ce fuseau nommé,
			// gérez l'heure d'été vous-même". gmdate('c', …) forçait un
			// suffixe UTC qui entrait en conflit avec le timeZone fourni.
			'start'        => [ 'dateTime' => $dt_debut->format( 'Y-m-d\TH:i:s' ), 'timeZone' => $tz ],
			'end'          => [ 'dateTime' => $dt_fin->format( 'Y-m-d\TH:i:s' ),   'timeZone' => $tz ],
			'attendees'    => [
				[
					'email'       => $booking['email'],
					'displayName' => trim( ( $booking['prenom'] ?? '' ) . ' ' . ( $booking['nom'] ?? '' ) ),
				],
			],
			'reminders'   => [
				'useDefault' => false,
				'overrides'  => [
					[ 'method' => 'email', 'minutes' => 1440 ],
					[ 'method' => 'popup', 'minutes' => 60 ],
				],
			],
			'extendedProperties' => [
				'private' => [
					'cfeb_booking_id' => (string) ( $booking['id'] ?? '' ),
					'cfeb_token'      => $booking['token'] ?? '',
				],
			],
		];

		if ( $location ) {
			$event_body['location'] = $location;
		}

		$calendar_id_enc = rawurlencode( $calendar_id ?: 'primary' );
		$result          = self::api_request( 'POST', '/calendars/' . $calendar_id_enc . '/events', $event_body );

		if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
			self::store_event_id( $booking['id'], $result['id'], $calendar_id ?: 'primary' );
		}
	}

	/* ══════════════════════════════════════════════════════════════
	   SUPPRIMER UN ÉVÉNEMENT GOOGLE AGENDA
	══════════════════════════════════════════════════════════════ */
	public static function delete_event( $booking_id ) {
		if ( ! self::is_connected() ) {
			return;
		}

		$info = self::get_event_info( $booking_id );
		if ( ! $info ) {
			return;
		}

		$calendar_id_enc = rawurlencode( $info['calendar_id'] );
		self::api_request( 'DELETE', '/calendars/' . $calendar_id_enc . '/events/' . $info['event_id'] );
		self::remove_event_id( $booking_id );
	}

	/* ══════════════════════════════════════════════════════════════
	   STOCKAGE DES IDS GCAL (dans wp_options)
	══════════════════════════════════════════════════════════════ */
	public static function store_event_id( $booking_id, $gcal_event_id, $calendar_id = 'primary' ) {
		$map = get_option( 'cfeb_gcal_events', [] );
		$map[ (int) $booking_id ] = [
			'event_id'    => $gcal_event_id,
			'calendar_id' => $calendar_id,
		];
		update_option( 'cfeb_gcal_events', $map );
	}

	public static function get_event_info( $booking_id ) {
		$map = get_option( 'cfeb_gcal_events', [] );
		return $map[ (int) $booking_id ] ?? null;
	}

	public static function remove_event_id( $booking_id ) {
		$map = get_option( 'cfeb_gcal_events', [] );
		unset( $map[ (int) $booking_id ] );
		update_option( 'cfeb_gcal_events', $map );
	}

	/* ══════════════════════════════════════════════════════════════
	   SYNCHRONISATION COMPLÈTE D'UNE RÉSERVATION
	══════════════════════════════════════════════════════════════ */
	/**
	 * Point d'entrée principal appelé depuis CF_Ajax::reserver().
	 * Crée l'événement Google Agenda si les conditions sont remplies.
	 *
	 * @param array   $booking    Données de réservation (inclut id, prenom, nom, email…)
	 * @param WP_Post $event_post Post de l'événement
	 */
	public static function sync_new_booking( $booking, $event_post ) {
		if ( ! self::is_connected() ) {
			return;
		}

		$s = self::get_settings();

		// Réservation slot-based (pas d'événement WP)
		if ( null === $event_post ) {
			$booking_arr = (array) $booking;
			$type_id     = (int) ( $booking_arr['appt_type_id'] ?? 0 );
			$opts        = CF_Admin::get_options();
			if ( $type_id && class_exists( 'CF_ApptType' ) ) {
				$tm = CF_ApptType::get_meta( $type_id );
				if ( $tm['gcal_enabled'] || ! empty( $opts['gcal_sync_all'] ) ) {
					self::create_event_from_slot(
						$booking_arr,
						$tm['gcal_calendar_id'] ?: $s['calendar_id'],
						$tm['gcal_title_template']
					);
				}
			} elseif ( ! empty( $opts['gcal_sync_all'] ) ) {
				// Aucun type connu mais sync globale activée
				self::create_event_from_slot( $booking_arr, $s['calendar_id'] );
			}
			return;
		}

		// Vérifier si l'événement a un type RDV avec synchro activée
		$type_id = (int) get_post_meta( $event_post->ID, '_cfeb_appt_type_id', true );
		if ( $type_id && class_exists( 'CF_ApptType' ) ) {
			$tm = CF_ApptType::get_meta( $type_id );
			if ( $tm['gcal_enabled'] ) {
				self::create_event(
					$booking,
					$event_post,
					$tm['gcal_calendar_id'] ?: $s['calendar_id'],
					$tm['gcal_title_template']
				);
				return;
			}
		}

		// Synchro globale (si activée dans les paramètres)
		$opts = CF_Admin::get_options();
		if ( ! empty( $opts['gcal_sync_all'] ) ) {
			self::create_event( $booking, $event_post, $s['calendar_id'] );
		}
	}

	/**
	 * Crée un événement Google Agenda à partir d'une réservation slot-based.
	 *
	 * @param array  $booking     Données de la réservation (tableau)
	 * @param string $calendar_id ID du calendrier cible
	 * @param string $title_tpl   Template du titre
	 */
	private static function create_event_from_slot( array $booking, $calendar_id = 'primary', $title_tpl = '' ) {
		if ( ! self::is_connected() ) {
			return;
		}

		$slot_debut = $booking['slot_debut'] ?? '';
		$slot_fin   = $booking['slot_fin']   ?? '';
		if ( ! $slot_debut ) {
			return;
		}

		$tz       = wp_timezone_string();
		$dt_debut = self::local_datetime( $slot_debut );
		if ( ! $dt_debut ) {
			return;
		}
		$dt_fin = $slot_fin ? self::local_datetime( $slot_fin ) : null;
		if ( ! $dt_fin ) {
			$dt_fin = ( clone $dt_debut )->modify( '+1 hour' );
		}
		if ( $dt_fin->getTimestamp() < $dt_debut->getTimestamp() + 900 ) {
			$dt_fin = ( clone $dt_debut )->modify( '+15 minutes' );
		}
		$ts_debut = $dt_debut->getTimestamp();
		$ts_fin   = $dt_fin->getTimestamp();

		$type_id   = (int) ( $booking['appt_type_id'] ?? 0 );
		$type_post = $type_id ? get_post( $type_id ) : null;
		$titre     = $type_post ? $type_post->post_title : 'Rendez-vous';

		$m    = ( $type_id && class_exists( 'CF_ApptType' ) ) ? CF_ApptType::get_meta( $type_id ) : [];
		$lieu = $m['lieu'] ?? '';

		$title_tpl = $title_tpl ?: '{titre} — {prenom} {nom}';
		$title     = strtr( $title_tpl, [
			'{titre}'  => $titre,
			'{prenom}' => $booking['prenom'] ?? '',
			'{nom}'    => $booking['nom']    ?? '',
			'{email}'  => $booking['email']  ?? '',
			'{date}'   => date_i18n( 'd/m/Y', $ts_debut ),
			'{heure}'  => date_i18n( 'H\hi', $ts_debut ),
		] );

		$description = sprintf(
			"Rendez-vous confirmé\nNom : %s %s\nEmail : %s\nTéléphone : %s\nPlaces : %d%s",
			$booking['prenom']    ?? '',
			$booking['nom']       ?? '',
			$booking['email']     ?? '',
			$booking['telephone'] ?? '',
			$booking['nb_places'] ?? 1,
			! empty( $booking['notes'] ) ? "\nNotes : " . $booking['notes'] : ''
		);

		$event_body = [
			'summary'      => $title,
			'description'  => $description,
			'transparency' => 'transparent',
			// Format LOCAL nu, apparié au timeZone — voir local_datetime().
			'start'        => [ 'dateTime' => $dt_debut->format( 'Y-m-d\TH:i:s' ), 'timeZone' => $tz ],
			'end'          => [ 'dateTime' => $dt_fin->format( 'Y-m-d\TH:i:s' ),   'timeZone' => $tz ],
			'attendees'    => [
				[
					'email'       => $booking['email'],
					'displayName' => trim( ( $booking['prenom'] ?? '' ) . ' ' . ( $booking['nom'] ?? '' ) ),
				],
			],
			'reminders'   => [
				'useDefault' => false,
				'overrides'  => [
					[ 'method' => 'email', 'minutes' => 1440 ],
					[ 'method' => 'popup', 'minutes' => 60 ],
				],
			],
			'extendedProperties' => [
				'private' => [
					'cfeb_booking_id' => (string) ( $booking['id'] ?? '' ),
					'cfeb_token'      => $booking['token'] ?? '',
				],
			],
		];

		if ( $lieu ) {
			$event_body['location'] = $lieu;
		}

		$calendar_id_enc = rawurlencode( $calendar_id ?: 'primary' );
		$result          = self::api_request( 'POST', '/calendars/' . $calendar_id_enc . '/events', $event_body );

		if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
			self::store_event_id( $booking['id'], $result['id'], $calendar_id ?: 'primary' );
		}
	}

	/**
	 * Appelé quand une réservation est annulée.
	 *
	 * @param int $booking_id
	 */
	public static function sync_cancel_booking( $booking_id ) {
		self::delete_event( $booking_id );
	}

	/* ══════════════════════════════════════════════════════════════
	   PLAGES OCCUPÉES — Freebusy API
	══════════════════════════════════════════════════════════════ */
	/**
	 * Retourne les plages occupées dans Google Agenda pour une journée donnée.
	 * Résultat mis en cache (transient 5 min) pour éviter des appels répétés.
	 *
	 * @param string       $date_str    Date YYYY-MM-DD
	 * @param string|array $calendar_ids  ID(s) de calendrier (défaut : tous les agendas configurés)
	 * @return array  Tableau de ['start' => timestamp, 'end' => timestamp]
	 */
	public static function get_busy_times( $date_str, $calendar_ids = null ) {
		if ( ! self::is_connected() ) {
			return [];
		}

		// Normalise en tableau — accepte string pour compat ascendante
		if ( null === $calendar_ids ) {
			$calendar_ids = self::get_all_calendar_ids();
		} elseif ( is_string( $calendar_ids ) ) {
			$calendar_ids = self::get_all_calendar_ids( $calendar_ids );
		}
		$calendar_ids = array_values( array_unique( array_filter( $calendar_ids ) ) );

		$cache_key = 'cfeb_gcal_busy_' . md5( $date_str . '|' . implode( ',', $calendar_ids ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$tz       = wp_timezone_string() ?: 'UTC';
		$time_min = $date_str . 'T00:00:00';
		$time_max = $date_str . 'T23:59:59';

		try {
			$tz_obj   = new DateTimeZone( $tz );
			$dt_min   = new DateTime( $time_min, $tz_obj );
			$dt_max   = new DateTime( $time_max, $tz_obj );
			$time_min = $dt_min->format( DateTime::RFC3339 );
			$time_max = $dt_max->format( DateTime::RFC3339 );
		} catch ( Exception $e ) {
			$time_min .= 'Z';
			$time_max .= 'Z';
		}

		// Un seul appel API pour tous les agendas
		$items  = array_map( fn( $id ) => [ 'id' => $id ], $calendar_ids );
		$result = self::api_request( 'POST', '/freeBusy', [
			'timeMin'  => $time_min,
			'timeMax'  => $time_max,
			'timeZone' => $tz,
			'items'    => $items,
		] );

		if ( is_wp_error( $result ) || empty( $result['calendars'] ) ) {
			set_transient( $cache_key, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		// Fusionner les plages occupées de tous les agendas retournés
		$busy = [];
		foreach ( $result['calendars'] as $cal_data ) {
			if ( empty( $cal_data['busy'] ) ) {
				continue;
			}
			foreach ( $cal_data['busy'] as $period ) {
				$busy[] = [
					'start' => strtotime( $period['start'] ),
					'end'   => strtotime( $period['end'] ),
				];
			}
		}

		// Compléter avec l'API Events pour les événements « toute la journée »
		// (freeBusy ne retourne que les événements opaques/busy ; les événements
		//  toute la journée créés sur mobile sont souvent transparents et ignorés)
		$tz_for_allday = isset( $tz_obj ) ? $tz_obj : new DateTimeZone( 'UTC' );
		foreach ( $calendar_ids as $cal_id ) {
			$params        = http_build_query( [
				'timeMin'      => $time_min,
				'timeMax'      => $time_max,
				'timeZone'     => $tz,
				'singleEvents' => 'true',
				'fields'       => 'items(start,end)',
			] );
			$events_result = self::api_request( 'GET', '/calendars/' . rawurlencode( $cal_id ) . '/events?' . $params );
			if ( is_wp_error( $events_result ) || empty( $events_result['items'] ) ) {
				continue;
			}
			foreach ( $events_result['items'] as $ev ) {
				// Événement toute la journée : start.date existe, start.dateTime n'existe pas
				if ( empty( $ev['start']['date'] ) ) {
					continue;
				}
				try {
					// end.date est exclusif dans l'API Google (lendemain pour un événement d'un jour)
					$d_start = new DateTime( $ev['start']['date'] . 'T00:00:00', $tz_for_allday );
					$d_end   = new DateTime( $ev['end']['date']   . 'T00:00:00', $tz_for_allday );
					$busy[]  = [
						'start' => $d_start->getTimestamp(),
						'end'   => $d_end->getTimestamp(),
					];
				} catch ( Exception $e ) {
					// événement mal formé, ignorer
				}
			}
		}

		set_transient( $cache_key, $busy, 5 * MINUTE_IN_SECONDS );
		return $busy;
	}

	/**
	 * Vérifie si un créneau (timestamps) est libre dans Google Agenda.
	 *
	 * @param int    $slot_start  Timestamp début du créneau
	 * @param int    $slot_end    Timestamp fin du créneau
	 * @param array  $busy_times  Résultat de get_busy_times()
	 * @return bool  true = libre, false = occupé
	 */
	public static function slot_is_free( $slot_start, $slot_end, array $busy_times ) {
		foreach ( $busy_times as $period ) {
			// Chevauchement : le slot commence avant la fin du bloc occupé ET finit après le début
			if ( $slot_start < $period['end'] && $slot_end > $period['start'] ) {
				return false;
			}
		}
		return true;
	}

	/* ══════════════════════════════════════════════════════════════
	   DIAGNOSTIC FREEBUSY (AJAX admin)
	══════════════════════════════════════════════════════════════ */
	public static function ajax_test_freebusy() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cfeb_gcal_test', 'nonce', false ) ) {
			wp_send_json_error( 'Permission refusée.', 403 );
		}

		if ( ! self::is_connected() ) {
			wp_send_json_error( 'Google Agenda non connecté.' );
		}

		$date        = sanitize_text_field( wp_unslash( $_POST['date'] ?? gmdate( 'Y-m-d' ) ) );
		$calendar_id = sanitize_text_field( wp_unslash( $_POST['calendar_id'] ?? 'primary' ) );

		// Vider le cache pour ce test
		delete_transient( 'cfeb_gcal_busy_' . md5( $date . '|' . $calendar_id ) );

		$token = self::get_token();
		$s     = self::get_settings();
		$tz    = wp_timezone_string() ?: 'UTC';

		try {
			$tz_obj   = new DateTimeZone( $tz );
			$dt_min   = new DateTime( $date . 'T00:00:00', $tz_obj );
			$dt_max   = new DateTime( $date . 'T23:59:59', $tz_obj );
			$time_min = $dt_min->format( DateTime::RFC3339 );
			$time_max = $dt_max->format( DateTime::RFC3339 );
		} catch ( Exception $e ) {
			$time_min = $date . 'T00:00:00Z';
			$time_max = $date . 'T23:59:59Z';
		}

		$access_token = self::get_valid_access_token();
		if ( ! $access_token ) {
			wp_send_json_error( 'Impossible d\'obtenir un access token valide. Reconnectez-vous.' );
		}

		// Appel brut pour voir la réponse complète
		$response = wp_remote_post( self::CALENDAR_API . '/freeBusy', [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode( [
				'timeMin'  => $time_min,
				'timeMax'  => $time_max,
				'timeZone' => $tz,
				'items'    => [ [ 'id' => $calendar_id ] ],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Erreur réseau : ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $body, true );

		wp_send_json_success( [
			'http_code'    => $http_code,
			'date'         => $date,
			'calendar_id'  => $calendar_id,
			'timezone'     => $tz,
			'time_min'     => $time_min,
			'time_max'     => $time_max,
			'token_scope'  => $token['scope'] ?? '(non enregistré — ancien token)',
			'raw_response' => $decoded ?: $body,
		] );
	}

	/* ══════════════════════════════════════════════════════════════
	   RENDU DES PARAMÈTRES (section de la page Paramètres)
	══════════════════════════════════════════════════════════════ */
	public static function render_settings_section() {
		$s           = self::get_settings();
		$connected   = self::is_connected();
		$oauth_url   = self::get_oauth_url();
		$token       = self::get_token();
		$disconnect_url = wp_nonce_url(
			add_query_arg( [
				'post_type'           => CFEB_SLUG,
				'page'                => 'cfeb-settings',
				'cfeb_gcal_disconnect'=> '1',
			], admin_url( 'edit.php' ) ),
			'cfeb_gcal_disconnect'
		);

		// Notifications de connexion/erreur
		if ( isset( $_GET['gcal_connected'] ) ) {
			if ( '1' === $_GET['gcal_connected'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>✅ Google Agenda connecté avec succès !</p></div>';
			} elseif ( '0' === $_GET['gcal_connected'] ) {
				echo '<div class="notice notice-info is-dismissible"><p>ℹ️ Déconnecté de Google Agenda.</p></div>';
			}
		}
		if ( isset( $_GET['gcal_err'] ) ) {
			$err_code = sanitize_key( $_GET['gcal_err'] );
			if ( 'invalid_state' === $err_code ) {
				$err_msg = 'Lien OAuth expiré ou déjà utilisé. <strong>Actualisez cette page</strong> et cliquez à nouveau sur « Autoriser l\'accès à mon compte Google ».';
			} else {
				$err_msg = esc_html( $err_code );
			}
			echo '<div class="notice notice-error is-dismissible"><p>❌ Erreur Google : ' . wp_kses( $err_msg, [ 'strong' => [] ] ) . '</p></div>';
		}

		// Avertir si le token a été obtenu avec l'ancien scope calendar.events (sans accès freeBusy).
		if ( $connected ) {
			$token_data   = self::get_token();
			$stored_scope = $token_data['scope'] ?? '';
			$needs_reauth = empty( $stored_scope ) || (
				false === strpos( $stored_scope, 'https://www.googleapis.com/auth/calendar ' ) &&
				'https://www.googleapis.com/auth/calendar' !== rtrim( $stored_scope )
			);
			if ( $needs_reauth ) {
				echo '<div class="notice notice-warning"><p>⚠️ <strong>Reconnexion requise pour la vérification d\'agenda.</strong> Votre autorisation actuelle ne permet pas de consulter votre agenda personnel (scope insuffisant). Cliquez sur « Déconnecter » puis « Autoriser l\'accès » pour activer la vérification de disponibilité.</p></div>';
			}
		}
		?>
		<div class="cfeb-gcal-settings" id="gcal">
			<h2 style="display:flex;align-items:center;gap:10px;">
				<img src="<?php echo esc_url( 'https://www.google.com/s2/favicons?domain=calendar.google.com&sz=24' ); ?>" alt="" style="width:20px;height:20px;" />
				Google Agenda
			</h2>

			<?php if ( $connected ) : ?>
			<div style="background:#f0fdf4;border:1px solid #22c55e;border-radius:8px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
				<span style="font-size:24px;">✅</span>
				<div>
					<strong>Connecté à Google Agenda</strong>
					<p style="margin:2px 0 0;font-size:13px;color:#646970;">Les nouvelles réservations seront synchronisées automatiquement.</p>
				</div>
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button button-small" style="margin-left:auto;">Déconnecter</a>
			</div>
			<?php else : ?>
			<div style="background:#fff8ed;border:1px solid #f59e0b;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
				<strong>⚠️ Non connecté</strong>
				<p style="margin:4px 0 0;font-size:13px;color:#646970;">Entrez vos identifiants OAuth, puis cliquez sur Autoriser.</p>
			</div>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th><label for="cfeb_gcal_client_id">Client ID</label></th>
					<td>
						<input type="text" id="cfeb_gcal_client_id" name="cfeb_gcal_client_id" value="<?php echo esc_attr( $s['client_id'] ); ?>" class="regular-text" autocomplete="off" />
						<p class="description">Depuis la <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a> → Identifiants → Client OAuth 2.0</p>
					</td>
				</tr>
				<tr>
					<th><label for="cfeb_gcal_client_secret">Client Secret</label></th>
					<td>
						<input type="password" id="cfeb_gcal_client_secret" name="cfeb_gcal_client_secret" value="<?php echo esc_attr( $s['client_secret'] ); ?>" class="regular-text" autocomplete="new-password" />
					</td>
				</tr>
				<tr>
					<th><label for="cfeb_gcal_calendar_id">Agenda principal</label></th>
					<td>
						<input type="text" id="cfeb_gcal_calendar_id" name="cfeb_gcal_calendar_id" value="<?php echo esc_attr( $s['calendar_id'] ); ?>" class="regular-text" placeholder="primary" />
						<p class="description">«&nbsp;primary&nbsp;» pour votre agenda principal. Sinon, copiez l'ID depuis les paramètres de votre agenda Google.</p>
					</td>
				</tr>
				<tr>
					<th><label for="cfeb_gcal_extra_calendar_ids">Agendas supplémentaires à vérifier</label></th>
					<td>
						<input type="text" id="cfeb_gcal_extra_calendar_ids" name="cfeb_gcal_extra_calendar_ids" value="<?php echo esc_attr( $s['extra_calendar_ids'] ); ?>" class="large-text" placeholder="famille@group.calendar.google.com, autre@gmail.com" />
						<p class="description">
							IDs séparés par des virgules. Ces agendas seront vérifiés en plus de l'agenda principal pour bloquer les créneaux.<br>
							<strong>Trouver l'ID :</strong> Google Agenda → ⋮ à côté de l'agenda → <em>Paramètres et partage</em> → section <em>Intégrer l'agenda</em> → <em>Identifiant de l'agenda</em>.
						</p>
					</td>
				</tr>
				<tr>
					<th>URI de redirection (à copier dans Google Cloud)</th>
					<td>
						<code style="background:#f0f4ff;padding:4px 8px;border-radius:4px;user-select:all;"><?php echo esc_html( self::get_redirect_uri() ); ?></code>
						<p class="description">Copiez cette URL dans <strong>URI de redirection autorisées</strong> dans votre projet Google Cloud.</p>
					</td>
				</tr>
				<tr>
					<th>Autorisation Google</th>
					<td>
						<?php if ( $connected ) : ?>
							<p style="margin:0;color:#16a34a;font-weight:600;">✅ Compte Google connecté.</p>
							<p class="description">La synchronisation est active. Cliquez sur « Déconnecter » ci-dessus pour révoquer l'accès.</p>
						<?php elseif ( $s['client_id'] ) : ?>
							<a href="<?php echo esc_url( $oauth_url ); ?>" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;">
								<img src="<?php echo esc_url( 'https://www.google.com/s2/favicons?domain=google.com&sz=16' ); ?>" alt="" style="width:14px;height:14px;" />
								Autoriser l'accès à mon compte Google
							</a>
							<p class="description">Enregistrez d'abord les paramètres ci-dessus, puis cliquez sur ce bouton pour accorder l'accès à votre agenda Google.</p>
							<details style="margin-top:8px;">
								<summary style="cursor:pointer;font-size:12px;color:#646970;">Lien direct (copier/coller)</summary>
								<input type="url" readonly value="<?php echo esc_url( $oauth_url ); ?>" class="regular-text code" style="max-width:100%;margin-top:4px;" onclick="this.select();" />
							</details>
						<?php else : ?>
							<button class="button button-primary" disabled style="opacity:.5;cursor:not-allowed;">
								Autoriser l'accès à mon compte Google
							</button>
							<p class="description">⚠️ Entrez votre <strong>Client ID</strong> et votre <strong>Client Secret</strong> ci-dessus, puis cliquez sur <em>Enregistrer les paramètres</em>. Le bouton d'autorisation s'activera ensuite.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Synchronisation globale</th>
					<td>
						<?php $opts = CF_Admin::get_options(); ?>
						<label>
							<input type="checkbox" name="cfeb_gcal_sync_all" value="1" <?php checked( $opts['gcal_sync_all'] ?? 0, 1 ); ?> />
							Synchroniser toutes les réservations confirmées (même sans type RDV configuré)
						</label>
					</td>
				</tr>
				<?php if ( $connected ) : ?>
				<tr>
					<th>Diagnostic FreeBusy</th>
					<td>
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
							<input type="date" id="cfeb-freebusy-date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
							<input type="text" id="cfeb-freebusy-cal" value="<?php echo esc_attr( $s['calendar_id'] ?: 'primary' ); ?>" placeholder="primary" style="width:220px;" />
							<button type="button" id="cfeb-freebusy-test" class="button">🔍 Tester la disponibilité</button>
						</div>
						<pre id="cfeb-freebusy-result" style="margin-top:10px;background:#f6f7f7;padding:12px;border-radius:4px;font-size:12px;white-space:pre-wrap;display:none;max-height:300px;overflow:auto;"></pre>
						<script>
						document.getElementById('cfeb-freebusy-test').addEventListener('click', function() {
							var btn    = this;
							var pre    = document.getElementById('cfeb-freebusy-result');
							var date   = document.getElementById('cfeb-freebusy-date').value;
							var cal    = document.getElementById('cfeb-freebusy-cal').value;
							btn.disabled = true;
							btn.textContent = 'Test en cours…';
							pre.style.display = 'none';
							var fd = new FormData();
							fd.append('action', 'cfeb_gcal_test_freebusy');
							fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'cfeb_gcal_test' ) ); ?>');
							fd.append('date', date);
							fd.append('calendar_id', cal);
							fetch(ajaxurl, { method: 'POST', body: fd })
								.then(r => r.json())
								.then(function(r) {
									pre.textContent = JSON.stringify(r.data, null, 2);
									pre.style.display = 'block';
									btn.disabled = false;
									btn.textContent = '🔍 Tester la disponibilité';
								})
								.catch(function(e) {
									pre.textContent = 'Erreur réseau : ' + e;
									pre.style.display = 'block';
									btn.disabled = false;
									btn.textContent = '🔍 Tester la disponibilité';
								});
						});
						</script>
						<button type="button" id="cfeb-freebusy-clear" class="button" style="margin-top:6px;">🗑️ Vider le cache freebusy</button>
						<script>
						document.getElementById('cfeb-freebusy-clear').addEventListener('click', function() {
							var btn = this;
							btn.disabled = true;
							var fd = new FormData();
							fd.append('action', 'cfeb_gcal_clear_cache');
							fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'cfeb_gcal_test' ) ); ?>');
							fetch(ajaxurl, { method: 'POST', body: fd })
								.then(r => r.json())
								.then(function(r) {
									btn.textContent = '✅ Cache vidé';
									setTimeout(function(){ btn.disabled = false; btn.textContent = '🗑️ Vider le cache freebusy'; }, 2000);
								});
						});
						</script>
						<p class="description">Teste l'appel freeBusy en direct. Videz le cache si les créneaux ne se mettent pas à jour après un changement d'agenda.</p>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
	}
}
