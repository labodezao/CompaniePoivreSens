<?php
/**
 * Programme Pleine Vie — enregistrement et statuts des inscriptions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Registrations {

	public static function table() {
		return CFPV_Install::table();
	}

	public static function add( $data ) {
		global $wpdb;
		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Adresse email invalide.' );
		}
		$s     = get_option( 'cfpv_settings', [] );
		$token = wp_generate_password( 40, false );
		$ok = $wpdb->insert( self::table(), [
			'prenom'    => sanitize_text_field( $data['prenom'] ?? '' ),
			'nom'       => sanitize_text_field( $data['nom'] ?? '' ),
			'email'     => $email,
			'telephone' => sanitize_text_field( $data['telephone'] ?? '' ),
			'message'   => sanitize_textarea_field( $data['message'] ?? '' ),
			'session'   => sanitize_text_field( $data['session'] ?? ( $s['session'] ?? '' ) ),
			'statut'    => 'attente',
			'token'     => $token,
			'source'    => sanitize_text_field( $data['source'] ?? 'formulaire' ),
			'cree_le'   => current_time( 'mysql' ),
		] );
		if ( false === $ok ) {
			return new WP_Error( 'db_insert', 'Enregistrement impossible pour le moment.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
	}

	public static function all( $statut = '', $limit = 300 ) {
		global $wpdb;
		if ( $statut ) {
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE statut = %s ORDER BY id DESC LIMIT %d',
				$statut, $limit
			) );
		}
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit
		) );
	}

	public static function set_statut( $id, $statut ) {
		global $wpdb;
		$statut = in_array( $statut, [ 'attente', 'confirme', 'annule', 'termine' ], true ) ? $statut : 'attente';
		$fields = [ 'statut' => $statut ];
		if ( 'confirme' === $statut ) {
			$fields['confirme_le'] = current_time( 'mysql' );
		}
		return (bool) $wpdb->update( self::table(), $fields, [ 'id' => absint( $id ) ] );
	}

	public static function counts() {
		global $wpdb;
		$t = self::table();
		return [
			'attente'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='attente'" ),
			'confirme' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='confirme'" ),
			'annule'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='annule'" ),
			'termine'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE statut='termine'" ),
		];
	}
}
