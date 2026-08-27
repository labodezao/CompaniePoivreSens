<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPS_Install {

	public static function activate() {
		self::create_table();
		self::schedule_cron();
		// Paramètres par défaut
		if ( ! get_option( 'cfps_settings' ) ) {
			update_option( 'cfps_settings', self::default_settings() );
		}
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( 'cfps_daily_cron' );
		if ( $ts ) wp_unschedule_event( $ts, 'cfps_daily_cron' );
	}

	public static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . CFPS_TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id      INT UNSIGNED NOT NULL,
			email           VARCHAR(255) NOT NULL,
			prenom          VARCHAR(100) NOT NULL DEFAULT '',
			date_seance     DATETIME     NOT NULL,
			theme_slug      VARCHAR(20)  NOT NULL DEFAULT '',
			step_1_sent_at  DATETIME     DEFAULT NULL,
			step_2_sent_at  DATETIME     DEFAULT NULL,
			step_3_sent_at  DATETIME     DEFAULT NULL,
			step_4_notif_at DATETIME     DEFAULT NULL,
			step_4_sent_at  DATETIME     DEFAULT NULL,
			step_4_token    VARCHAR(64)  NOT NULL DEFAULT '',
			step_4_expires  DATETIME     DEFAULT NULL,
			statut          VARCHAR(20)  NOT NULL DEFAULT 'active',
			cree_le         DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY booking_id (booking_id),
			KEY email (email),
			KEY statut (statut)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'cfps_daily_cron' ) ) {
			// Chaque jour à 8h heure WordPress
			$tz     = wp_timezone();
			$now    = new DateTime( 'now', $tz );
			$next   = new DateTime( 'today 08:00', $tz );
			if ( $next <= $now ) {
				$next->modify( '+1 day' );
			}
			wp_schedule_event( $next->getTimestamp(), 'daily', 'cfps_daily_cron' );
		}
	}

	public static function default_settings() {
		return [
			'admin_email'   => get_option( 'admin_email' ),
			'from_name'     => get_bloginfo( 'name' ),
			'from_email'    => get_option( 'admin_email' ),
			'whatsapp_num'  => '33783031670',
			'appt_type_slug'=> 'constellation-individuelle',
			'articles'      => [
				'A' => '',  // Fondamentaux
				'B' => '',  // Parcours vécu
				'C' => '',  // Couple & relations
				'D' => '',  // Sexualité
				'E' => '',  // Transgénérationnel
				'F' => '',  // Parentalité
				'G' => '',  // Deuil
				'H' => '',  // Corps & émotions
				'I' => '',  // Addictions
				'J' => '',  // Outils (passe-partout)
				'K' => '',  // Cheminement
			],
		];
	}
}
