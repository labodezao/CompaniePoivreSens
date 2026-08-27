<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Subscribers {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cfnl_subscribers';
	}

	/* ── Ajoute ou met à jour un abonné ───────────────────────────
	   Retourne [ 'id' => int, 'status' => 'new_pending'|'new_subscribed'|'existing' ] */
	public static function add( $email, $prenom = '', $nom = '', $frequence = 'quinzaine', $source = '' ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Adresse email invalide.' );
		}
		// Un seul rythme de newsletter existe désormais (tous les 15 jours) ;
		// 'mensuel' reste accepté en lecture pour les abonnés déjà en base
		// avant la migration (voir CFNL_Install::create_tables()).
		$frequence = in_array( $frequence, [ 'quinzaine', 'mensuel' ], true ) ? $frequence : 'quinzaine';
		$table     = self::table();

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ) );
		if ( $existing ) {
			// Met à jour la fréquence, réactive si désabonné → repasse en pending (re-consentement)
			$new_statut = ( 'unsubscribed' === $existing->statut ) ? 'pending' : $existing->statut;
			$token      = $existing->token ?: wp_generate_password( 48, false );
			$wpdb->update( $table, [
				'prenom'    => sanitize_text_field( $prenom ) ?: $existing->prenom,
				'nom'       => sanitize_text_field( $nom ) ?: $existing->nom,
				'frequence' => $frequence,
				'statut'    => $new_statut,
				'token'     => $token,
			], [ 'id' => (int) $existing->id ] );
			return [ 'id' => (int) $existing->id, 'status' => 'existing', 'token' => $token, 'statut' => $new_statut ];
		}

		$settings = get_option( 'cfnl_settings', [] );
		$double   = ! empty( $settings['double_optin'] );
		$statut   = $double ? 'pending' : 'subscribed';
		$token    = wp_generate_password( 48, false );

		$wpdb->insert( $table, [
			'email'        => $email,
			'prenom'       => sanitize_text_field( $prenom ),
			'nom'          => sanitize_text_field( $nom ),
			'statut'       => $statut,
			'frequence'    => $frequence,
			'token'        => $token,
			'source'       => sanitize_text_field( $source ),
			'confirmed_at' => $double ? null : current_time( 'mysql' ),
			'cree_le'      => current_time( 'mysql' ),
		] );

		$new_id = (int) $wpdb->insert_id;

		// Sans double opt-in : abonné confirmé immédiatement → bienvenue
		if ( ! $double && class_exists( 'CFNL_Automations' ) ) {
			$row = self::get_by_token( $token );
			if ( $row ) {
				CFNL_Automations::send_welcome( $row );
			}
		}

		return [
			'id'     => $new_id,
			'status' => $double ? 'new_pending' : 'new_subscribed',
			'token'  => $token,
			'statut' => $statut,
		];
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$token = sanitize_text_field( $token );
		if ( ! $token ) return null;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE token = %s LIMIT 1", $token ) );
	}

	public static function confirm( $token ) {
		global $wpdb;
		$row = self::get_by_token( $token );
		if ( ! $row ) return false;
		if ( 'subscribed' === $row->statut ) return true;
		$wpdb->update( self::table(), [
			'statut'       => 'subscribed',
			'confirmed_at' => current_time( 'mysql' ),
		], [ 'id' => (int) $row->id ] );
		// Autorépondeur de bienvenue
		if ( class_exists( 'CFNL_Automations' ) ) {
			$row->statut = 'subscribed';
			CFNL_Automations::send_welcome( $row );
		}
		return true;
	}

	public static function unsubscribe( $token ) {
		global $wpdb;
		$row = self::get_by_token( $token );
		if ( ! $row ) return false;
		$wpdb->update( self::table(), [ 'statut' => 'unsubscribed' ], [ 'id' => (int) $row->id ] );
		return true;
	}

	/* ── Abonnés confirmés ciblés par une campagne ────────────────── */
	public static function recipients_for( $cible, $segment = 'all' ) {
		global $wpdb;
		$table = self::table();
		if ( 'quinzaine' === $cible ) {
			$where = "AND frequence = 'quinzaine'";
		} elseif ( 'mensuel' === $cible ) {
			$where = "AND frequence = 'mensuel'";
		} else {
			$where = ''; // both
		}

		// Segment comportemental (basé sur last_activity, fenêtre 90 jours)
		$limite = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days', current_time( 'timestamp' ) ) );
		if ( 'engaged' === $segment ) {
			$where .= $wpdb->prepare( ' AND last_activity IS NOT NULL AND last_activity >= %s', $limite );
		} elseif ( 'inactive' === $segment ) {
			$where .= $wpdb->prepare( ' AND (last_activity IS NULL OR last_activity < %s)', $limite );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT id, email, prenom, nom, token FROM {$table} WHERE statut = 'subscribed' {$where}" );
	}

	public static function counts() {
		global $wpdb;
		$t = self::table();
		$limite = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days', current_time( 'timestamp' ) ) );
		return [
			'subscribed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='subscribed'" ),
			'pending'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='pending'" ),
			'quinzaine'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='subscribed' AND frequence='quinzaine'" ),
			'mensuel'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='subscribed' AND frequence='mensuel'" ),
			'unsub'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='unsubscribed'" ),
			'engaged'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE statut='subscribed' AND last_activity IS NOT NULL AND last_activity >= %s", $limite ) ),
		];
	}
}
