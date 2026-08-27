<?php
/**
 * Tableau de bord unifié — réservations, revenus, newsletter, bons cadeaux.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Dashboard {

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Tableau de bord',
			'📊 Tableau de bord',
			'manage_options',
			'cfeb-dashboard',
			[ __CLASS__, 'page' ],
			0
		);
	}

	private static function card( $val, $label, $sub = '' ) {
		echo '<div style="flex:1;min-width:150px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px 18px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05);">'
			. '<div style="font-size:26px;font-weight:700;color:#5a3e6b;line-height:1.1;">' . $val . '</div>'
			. '<div style="font-size:12.5px;color:#1d2327;font-weight:600;margin-top:4px;">' . esc_html( $label ) . '</div>'
			. ( $sub ? '<div style="font-size:11px;color:#888;margin-top:2px;">' . esc_html( $sub ) . '</div>' : '' )
			. '</div>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$bookings = $wpdb->prefix . CFEB_TABLE;
		$month_start = date_i18n( 'Y-m-01 00:00:00' );

		// Réservations
		$resa_mois   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings} WHERE statut IN ('confirme','present') AND cree_le >= %s", $month_start ) );
		$resa_avenir = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings} WHERE statut = 'confirme' AND slot_debut >= NOW()" );
		$attente     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings} WHERE statut = 'liste_attente'" );
		$payees_mois = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings} WHERE paye = 1 AND cree_le >= %s", $month_start ) );

		echo '<div class="wrap"><h1>📊 Tableau de bord</h1>';

		echo '<h2 style="margin-top:18px;">Réservations</h2><div style="display:flex;flex-wrap:wrap;gap:12px;">';
		self::card( $resa_mois, 'Réservations ce mois' );
		self::card( $resa_avenir, 'Rendez-vous à venir' );
		self::card( $payees_mois, 'Payées ce mois', 'acompte, bon cadeau ou soldées' );
		self::card( $attente, 'En liste d\'attente' );
		echo '</div>';

		// Newsletter
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			$c = CFNL_Subscribers::counts();
			$last = $wpdb->get_row( "SELECT titre, envoyes, ouverts, clics FROM {$wpdb->prefix}cfnl_campaigns WHERE statut = 'sent' ORDER BY sent_at DESC LIMIT 1" );
			echo '<h2 style="margin-top:26px;">Newsletter</h2><div style="display:flex;flex-wrap:wrap;gap:12px;">';
			self::card( (int) $c['subscribed'], 'Abonnés confirmés' );
			self::card( (int) ( $c['engaged'] ?? 0 ), 'Engagés (90 j)' );
			self::card( (int) $c['quinzaine'] . ' / ' . (int) $c['mensuel'], 'Quinzaine / Mensuel' );
			if ( $last && $last->envoyes > 0 ) {
				self::card( round( $last->ouverts / $last->envoyes * 100 ) . ' %', 'Ouvertures — dernière campagne', $last->titre );
			}
			echo '</div>';
		}

		// Bons cadeaux
		if ( class_exists( 'CF_Vouchers' ) ) {
			$vt = CF_Vouchers::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vt ) ) === $vt ) {
				$actifs  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$vt} WHERE statut = 'actif'" );
				$attente_v = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$vt} WHERE statut = 'attente'" );
				$ca      = (float) $wpdb->get_var( "SELECT COALESCE(SUM(montant),0) FROM {$vt} WHERE statut IN ('actif','utilise')" );
				echo '<h2 style="margin-top:26px;">Bons cadeaux</h2><div style="display:flex;flex-wrap:wrap;gap:12px;">';
				self::card( $actifs, 'Bons actifs (non utilisés)' );
				self::card( $attente_v, 'En attente de paiement' );
				self::card( number_format( $ca, 0, ',', ' ' ) . ' €', 'Total vendu' );
				echo '</div>';
			}
		}

		// Séquences post-séance
		$cfps = $wpdb->prefix . 'cfps_sequence';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cfps ) ) === $cfps ) {
			$actives = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cfps} WHERE statut = 'active'" );
			$gonogo  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cfps} WHERE statut = 'active' AND step_4_notif_at IS NOT NULL AND step_4_sent_at IS NULL" );
			echo '<h2 style="margin-top:26px;">Suivi post-séance</h2><div style="display:flex;flex-wrap:wrap;gap:12px;">';
			self::card( $actives, 'Séquences en cours' );
			self::card( $gonogo, 'GO/NO-GO en attente de ta décision' );
			echo '</div>';
		}

		echo '<p style="margin-top:26px;"><a href="' . esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-reservations' ) ) . '" class="button">📋 Réservations</a> '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=cfnl-campaigns' ) ) . '" class="button">✉️ Newsletter</a> '
			. '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-vouchers' ) ) . '" class="button">🎁 Bons cadeaux</a> '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=cfps-sequences' ) ) . '" class="button">🌿 Post-séance</a></p>';
		echo '</div>';
	}
}
