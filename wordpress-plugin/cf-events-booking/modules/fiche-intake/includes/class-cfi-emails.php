<?php
/**
 * Fiche thèmes constellations — envoi des emails (récapitulatif admin +
 * accusé de réception client).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Emails {

	private static function wrap( $inner ) {
		return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="font-family:Georgia,serif;color:#2c2c2c;max-width:640px;margin:0 auto;padding:24px 18px;font-size:15px;line-height:1.7;">'
			. $inner . '</body></html>';
	}

	/** Email récapitulatif complet, envoyé à l'administrateur — remplace le PDF. */
	public static function notify_admin( $row ) {
		$opts  = class_exists( 'CF_Admin' ) ? CF_Admin::get_options() : [];
		$to    = $opts['admin_email'] ?? get_option( 'admin_email' );
		$donnees = $row->donnees_a ?? [];

		$rows_html = '<tr><td colspan="2" style="padding:10px 0 4px;font-weight:700;color:#5a3e6b;">Coordonnées</td></tr>'
			. self::row( 'Nom prénom', trim( $row->prenom . ' ' . $row->nom ) )
			. self::row( 'Email', $row->email )
			. self::row( 'Téléphone', $row->telephone );

		foreach ( CFI_Fiches::sections() as $section ) {
			if ( 'Vos coordonnées' !== $section['heading'] ) {
				$rows_html .= '<tr><td colspan="2" style="padding:14px 0 4px;font-weight:700;color:#5a3e6b;">' . esc_html( $section['heading'] ) . '</td></tr>';
			}
			foreach ( $section['fields'] as $field ) {
				$val = $donnees[ $field['key'] ] ?? '';
				if ( 'checkbox' === $field['type'] ) {
					$val = $val ? 'Oui' : 'Non';
				}
				$rows_html .= self::row( $field['label'], $val );
			}
		}

		$subject = '📋 Nouvelle fiche thèmes — ' . trim( $row->prenom . ' ' . $row->nom );
		$body    = self::wrap(
			'<h2 style="color:#5a3e6b;margin-top:0;">Nouvelle fiche thèmes constellations</h2>'
			. '<p style="color:#888;font-size:13px;">Reçue le ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->cree_le ) ) ) . ' — personnel et confidentiel.</p>'
			. '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows_html . '</table>'
		);

		return wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	private static function row( $label, $value ) {
		$value = trim( (string) $value );
		return '<tr><td style="padding:5px 12px 5px 0;color:#666;width:260px;vertical-align:top;">' . esc_html( $label ) . '</td>'
			. '<td style="padding:5px 0;white-space:pre-wrap;">' . ( $value !== '' ? nl2br( esc_html( $value ) ) : '<span style="color:#bbb;">—</span>' ) . '</td></tr>';
	}

	/** Accusé de réception, envoyé au client dès la soumission. */
	public static function send_ack( $row ) {
		$geno_link_html = self::genogramme_link_html( $row );

		$body = self::wrap(
			'<p>Bonjour ' . esc_html( $row->prenom ) . ',</p>'
			. '<p>Merci pour ta fiche thèmes — je l\'ai bien reçue, en toute confidentialité. Elle m\'aide à préparer notre premier temps ensemble.</p>'
			. $geno_link_html
			. '<p>À très vite.</p>'
			. '<p>— Ewen</p>'
		);
		return wp_mail( $row->email, 'J\'ai bien reçu ta fiche thèmes', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	/**
	 * Lien personnel vers le génogramme (mode simple), à insérer dans
	 * l'accusé de réception — un seul enregistrement par email, retrouvé
	 * ou créé, pré-rempli une première fois avec ce que la fiche sait déjà
	 * (jamais réécrasé si le client a déjà commencé à le compléter).
	 * Ne fait rien si le plugin génogramme-familial n'est pas actif.
	 */
	private static function genogramme_link_html( $row ): string {
		if ( ! class_exists( 'Geno_Client_Saves' ) ) {
			return '';
		}

		$save = Geno_Client_Saves::get_or_create( $row->email, trim( $row->prenom . ' ' . $row->nom ) );
		if ( class_exists( 'CFI_Genogramme' ) ) {
			Geno_Client_Saves::seed_preset_if_empty( (int) $save['id'], CFI_Genogramme::build_preset( $row ) );
		}
		$link = Geno_Client_Saves::link( $save['token'] );

		return '<p>Si tu veux, tu peux aussi commencer à compléter ton arbre familial ici, à ton rythme — pas besoin de tout faire d\'un coup, tu peux y revenir quand tu veux avec ce même lien :<br>'
			. '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></p>';
	}
}
