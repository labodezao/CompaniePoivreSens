<?php
/**
 * SMTP intégré — route wp_mail() via un serveur SMTP authentifié
 * (ex. le compte email OVH du site) sans dépendre d'un plugin tiers.
 * Améliore la délivrabilité de TOUS les emails du site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_SMTP {

	private static function cfg() {
		$s = get_option( 'cfnl_settings', [] );
		return [
			'enabled' => ! empty( $s['smtp_enabled'] ),
			'host'    => $s['smtp_host']   ?? '',
			'port'    => (int) ( $s['smtp_port'] ?? 587 ),
			'secure'  => $s['smtp_secure'] ?? 'tls',
			'user'    => $s['smtp_user']   ?? '',
			'pass'    => $s['smtp_pass']   ?? '',
			'from'    => $s['from_email']  ?? get_option( 'admin_email' ),
			'name'    => $s['from_name']   ?? get_bloginfo( 'name' ),
		];
	}

	public static function is_active() {
		$c = self::cfg();
		return $c['enabled'] && $c['host'] && $c['user'] && $c['pass'];
	}

	/* ── Applique la config SMTP à PHPMailer (hook phpmailer_init) ── */
	public static function configure( $phpmailer ) {
		if ( ! self::is_active() ) {
			return;
		}
		$c = self::cfg();
		$phpmailer->isSMTP();
		$phpmailer->Host       = $c['host'];
		$phpmailer->Port       = $c['port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $c['user'];
		$phpmailer->Password   = $c['pass'];
		$phpmailer->SMTPSecure = in_array( $c['secure'], [ 'tls', 'ssl' ], true ) ? $c['secure'] : '';
		if ( '' === $phpmailer->SMTPSecure ) {
			$phpmailer->SMTPAutoTLS = false;
		}
		// Expéditeur cohérent avec le compte authentifié (sinon rejet SPF/DMARC)
		if ( $c['from'] ) {
			$phpmailer->setFrom( $c['from'], $c['name'], false );
			$phpmailer->Sender = $c['from']; // enveloppe (Return-Path)
		}
	}

	/* ── Test rapide : envoie un email à l'admin ──────────────────── */
	public static function send_test( $to ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'bad_email', 'Adresse invalide.' );
		}
		$ok = wp_mail(
			$to,
			'Test SMTP — CF Newsletter',
			'<p>Si tu lis ceci, ta configuration SMTP fonctionne. 🎉</p>',
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
		return $ok ? true : new WP_Error( 'send_failed', 'wp_mail a échoué — vérifie les identifiants SMTP.' );
	}
}
