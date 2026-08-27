<?php
/**
 * Programme Pleine Vie — envoi des emails (modèles éditables en réglages).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Emails {

	private static function settings() {
		return wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
	}

	/* ── Bloc « coordonnées de virement » formaté à partir des réglages ── */
	private static function virement_block() {
		$s     = self::settings();
		$lines = [];
		if ( ! empty( $s['vir_montant'] ) )   $lines[] = 'Montant : ' . $s['vir_montant'];
		if ( ! empty( $s['vir_titulaire'] ) ) $lines[] = 'Titulaire : ' . $s['vir_titulaire'];
		if ( ! empty( $s['vir_iban'] ) )      $lines[] = 'IBAN : ' . $s['vir_iban'];
		if ( ! empty( $s['vir_bic'] ) )       $lines[] = 'BIC : ' . $s['vir_bic'];
		$bloc = implode( "\n", $lines );
		if ( ! empty( $s['vir_instructions'] ) ) {
			$bloc .= ( $bloc ? "\n\n" : '' ) . $s['vir_instructions'];
		}
		return $bloc ?: '(Coordonnées de virement à compléter dans Pleine Vie → Modèles d\'emails.)';
	}

	/* ── Remplace les variables et met en forme ───────────────────── */
	private static function render( $texte, $row ) {
		$vars = [
			'{{prenom}}'   => $row->prenom,
			'{{nom}}'      => $row->nom,
			'{{email}}'    => $row->email,
			'{{session}}'  => $row->session,
			'{{virement}}' => self::virement_block(),
		];
		return strtr( $texte, $vars );
	}

	private static function wrap( $corps ) {
		$html = wpautop( wp_kses_post( $corps ) );
		return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="font-family:Georgia,serif;color:#2c2c2c;max-width:600px;margin:0 auto;padding:24px 18px;font-size:16px;line-height:1.75;">'
			. $html . '</body></html>';
	}

	private static function send( $to, $objet, $corps, $row ) {
		$s = self::settings();
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . ( $s['from_name'] ?: get_bloginfo( 'name' ) ) . ' <' . ( $s['from_email'] ?: get_option( 'admin_email' ) ) . '>',
		];
		return wp_mail( $to, self::render( $objet, $row ), self::wrap( self::render( $corps, $row ) ), $headers );
	}

	/* ── Accusé de réception au candidat (à l'inscription) ────────── */
	public static function send_ack( $row ) {
		$s = self::settings();
		return self::send( $row->email, $s['ack_objet'], $s['ack_corps'], $row );
	}

	/* ── Notification à l'administratrice ─────────────────────────── */
	public static function notify_admin( $row ) {
		$s   = self::settings();
		$to  = $s['notif_email'] ?: get_option( 'admin_email' );
		$url = admin_url( 'admin.php?page=cfpv-inscriptions' );
		$corps = 'Nouvelle demande d\'inscription au Programme Pleine Vie :' . "\n\n"
			. 'Prénom / Nom : ' . $row->prenom . ' ' . $row->nom . "\n"
			. 'Email : ' . $row->email . "\n"
			. ( $row->telephone ? 'Téléphone : ' . $row->telephone . "\n" : '' )
			. ( $row->message ? "\nMessage :\n" . $row->message . "\n" : '' )
			. "\nGérer les inscriptions : " . $url;
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( $row->email ) {
			$headers[] = 'Reply-To: ' . $row->email;
		}
		return wp_mail( $to, 'Nouvelle inscription Pleine Vie — ' . $row->prenom, self::wrap( $corps ), $headers );
	}

	/* ── Email de validation (quand tu confirmes l'inscription) ───── */
	public static function send_validation( $row ) {
		$s = self::settings();
		return self::send( $row->email, $s['valid_objet'], $s['valid_corps'], $row );
	}

	/* ── Email d'annulation (optionnel) ───────────────────────────── */
	public static function send_cancel( $row ) {
		$s = self::settings();
		return self::send( $row->email, $s['cancel_objet'], $s['cancel_corps'], $row );
	}

	/* ── Une étape de la séquence d'accompagnement ────────────────── */
	public static function send_sequence_step( $row, $objet, $corps ) {
		if ( '' === trim( (string) $objet ) || '' === trim( (string) $corps ) ) {
			return false;
		}
		return self::send( $row->email, $objet, $corps, $row );
	}
}
