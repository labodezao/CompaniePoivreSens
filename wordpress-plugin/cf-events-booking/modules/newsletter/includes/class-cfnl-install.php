<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Install {

	public static function activate() {
		self::create_tables();
		self::schedule_cron();
		if ( ! get_option( 'cfnl_settings' ) ) {
			update_option( 'cfnl_settings', self::default_settings() );
		}
		if ( defined( 'CFNL_VERSION' ) ) {
			update_option( 'cfnl_db_version', CFNL_VERSION );
		}
		// Prend acte des textes déjà présents dans la bibliothèque, pour que
		// l'automatisation « nouveau cadeau » ne les considère pas tous comme
		// nouveaux au premier passage.
		if ( class_exists( 'CFNL_Automations' ) && null === CFNL_Automations::seen_keys() ) {
			CFNL_Automations::seed_seen();
		}
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( 'cfnl_send_cron' );
		if ( $ts ) wp_unschedule_event( $ts, 'cfnl_send_cron' );
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$subs = $wpdb->prefix . 'cfnl_subscribers';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$subs} (
			id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
			email         VARCHAR(200) NOT NULL,
			prenom        VARCHAR(100) NOT NULL DEFAULT '',
			nom           VARCHAR(100) NOT NULL DEFAULT '',
			statut        VARCHAR(20)  NOT NULL DEFAULT 'pending',
			frequence     VARCHAR(20)  NOT NULL DEFAULT 'quinzaine',
			token         VARCHAR(64)  NOT NULL DEFAULT '',
			source        VARCHAR(50)  NOT NULL DEFAULT '',
			confirmed_at  DATETIME     DEFAULT NULL,
			last_activity DATETIME     DEFAULT NULL,
			cree_le       DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY email (email),
			KEY statut (statut),
			KEY frequence (frequence)
		) {$charset};" );

		// Migrations : colonnes ajoutées au fil des versions
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scols = $wpdb->get_col( "SHOW COLUMNS FROM `{$subs}`" );
		if ( is_array( $scols ) ) {
			$sadd = [
				'last_activity' => 'DATETIME DEFAULT NULL',
				'drip_step'     => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
				'drip_last_at'  => 'DATETIME DEFAULT NULL',
			];
			foreach ( $sadd as $col => $def ) {
				if ( ! in_array( $col, $scols, true ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE `{$subs}` ADD COLUMN `{$col}` {$def}" );
				}
			}
		}

		// Un seul rythme de newsletter désormais (tous les 15 jours) : les
		// abonnés qui avaient choisi « mensuel » basculent sur « quinzaine »
		// plutôt que de ne plus rien recevoir.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE `{$subs}` SET frequence = 'quinzaine' WHERE frequence = 'mensuel'" );

		// Réglages : ne corrige le texte de bienvenue par défaut que s'il n'a
		// jamais été personnalisé (toujours égal à l'ancien texte par défaut
		// mentionnant un choix de rythme qui n'existe plus).
		$settings = get_option( 'cfnl_settings', [] );
		if ( is_array( $settings ) && ( $settings['welcome_corps'] ?? '' )
			=== "Bonjour {{prenom}},\n\nMerci de me rejoindre. Tu recevras mes réflexions au rythme que tu as choisi.\n\nÀ très vite,\n\n— Ewen" ) {
			$settings['welcome_corps'] = "Bonjour {{prenom}},\n\nMerci de me rejoindre. Tu recevras mes réflexions tous les 15 jours.\n\nÀ très vite,\n\n— Ewen";
			update_option( 'cfnl_settings', $settings );
		}

		$camp = $wpdb->prefix . 'cfnl_campaigns';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$camp} (
			id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
			titre         VARCHAR(200) NOT NULL DEFAULT '',
			objet         VARCHAR(255) NOT NULL DEFAULT '',
			corps         LONGTEXT     NOT NULL,
			cible         VARCHAR(20)  NOT NULL DEFAULT 'both',
			statut        VARCHAR(20)  NOT NULL DEFAULT 'draft',
			total         INT UNSIGNED NOT NULL DEFAULT 0,
			envoyes       INT UNSIGNED NOT NULL DEFAULT 0,
			ouverts       INT UNSIGNED NOT NULL DEFAULT 0,
			clics         INT UNSIGNED NOT NULL DEFAULT 0,
			sent_at       DATETIME     DEFAULT NULL,
			scheduled_at  DATETIME     DEFAULT NULL,
			segment       VARCHAR(20)  NOT NULL DEFAULT 'all',
			objet_b       VARCHAR(255) NOT NULL DEFAULT '',
			ab_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
			ab_sample     TINYINT(3)   UNSIGNED NOT NULL DEFAULT 30,
			ab_decide_at  DATETIME     DEFAULT NULL,
			ab_winner     VARCHAR(1)   NOT NULL DEFAULT '',
			cree_le       DATETIME     NOT NULL,
			maj_le        DATETIME     NOT NULL,
			PRIMARY KEY (id),
			KEY statut (statut)
		) {$charset};" );

		// Migrations : colonnes ajoutées au fil des versions
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$camp}`" );
		if ( is_array( $cols ) ) {
			$add = [
				'scheduled_at' => 'DATETIME DEFAULT NULL',
				'segment'      => "VARCHAR(20) NOT NULL DEFAULT 'all'",
				'objet_b'      => "VARCHAR(255) NOT NULL DEFAULT ''",
				'ab_enabled'   => 'TINYINT(1) NOT NULL DEFAULT 0',
				'ab_sample'    => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 30',
				'ab_decide_at' => 'DATETIME DEFAULT NULL',
				'ab_winner'    => "VARCHAR(1) NOT NULL DEFAULT ''",
			];
			foreach ( $add as $col => $def ) {
				if ( ! in_array( $col, $cols, true ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE `{$camp}` ADD COLUMN `{$col}` {$def}" );
				}
			}
		}

		$queue = $wpdb->prefix . 'cfnl_queue';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$queue} (
			id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id   INT UNSIGNED NOT NULL,
			subscriber_id INT UNSIGNED NOT NULL,
			email         VARCHAR(200) NOT NULL,
			statut        VARCHAR(20)  NOT NULL DEFAULT 'pending',
			variant       VARCHAR(1)   NOT NULL DEFAULT '',
			opened_at     DATETIME     DEFAULT NULL,
			clics         INT UNSIGNED NOT NULL DEFAULT 0,
			sent_at       DATETIME     DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY camp_sub (campaign_id, subscriber_id),
			KEY campaign_id (campaign_id),
			KEY statut (statut)
		) {$charset};" );

		// Migration : colonne variant sur une file existante
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$qcols = $wpdb->get_col( "SHOW COLUMNS FROM `{$queue}`" );
		if ( is_array( $qcols ) && ! in_array( 'variant', $qcols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$queue}` ADD COLUMN `variant` VARCHAR(1) NOT NULL DEFAULT ''" );
		}
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'cfnl_send_cron' ) ) {
			wp_schedule_event( time() + 300, 'cfnl_five_min', 'cfnl_send_cron' );
		}
	}

	public static function default_settings() {
		return [
			'from_name'   => get_bloginfo( 'name' ),
			'from_email'  => get_option( 'admin_email' ),
			'double_optin' => 1,
			'batch_size'  => 30,   // emails par passage cron (toutes les 5 min)
			'daily_cap'   => 250,  // plafond/jour (marge sous la limite SMTP OVH mutualisé)
			// SMTP intégré (compte email OVH par défaut) — aucun plugin tiers requis
			'smtp_enabled' => 0,
			'smtp_host'    => 'ssl0.ovh.net',
			'smtp_port'    => 587,
			'smtp_secure'  => 'tls',
			'smtp_user'    => '',
			'smtp_pass'    => '',
			// Email de bienvenue (autorépondeur)
			'welcome_enabled' => 0,
			'welcome_objet'   => 'Bienvenue !',
			'welcome_corps'   => "Bonjour {{prenom}},\n\nMerci de me rejoindre. Tu recevras mes réflexions tous les 15 jours.\n\nÀ très vite,\n\n— Ewen",
			// Notification d'article (post → newsletter)
			'postnotif_enabled' => 0,
			'postnotif_mode'    => 'draft',      // draft | send
			'postnotif_cible'   => 'both',
			'postnotif_objet'   => 'Nouvel article : {{post_title}}',
			// Nouveau texte dans la bibliothèque → campagne
			'libnotif_enabled' => 0,
			'libnotif_mode'    => 'draft',   // draft | send
			'postnotif_corps'   => "Bonjour {{prenom}},\n\nJe viens de publier un nouvel article :\n\n<h2 style=\"font-size:20px;color:#5a3e6b;\">{{post_title}}</h2>\n\n{{post_image}}\n\n{{post_excerpt}}\n\n{{post_bouton}}\n\n— Ewen",
			// Suivi UTM (Google Analytics / Matomo)
			// Suivi des clics : chaque lien devient propre au destinataire.
			// Les liens partageables (visio…) en sont toujours exclus, voir
			// CFNL_Sender::NEVER_TRACK.
			'click_tracking' => 1,
			'track_exclude'  => '',
			'utm_enabled' => 1,
			'utm_source'  => 'newsletter',
			'utm_medium'  => 'email',
			// Séquence de bienvenue multi-étapes (drip)
			'welcome2_enabled' => 0,
			'welcome2_days'    => 3,
			'welcome2_objet'   => 'Par où commencer ?',
			'welcome2_corps'   => "Bonjour {{prenom}},\n\nQuelques jours ont passé depuis ton inscription. Si tu te demandes par où commencer, voici l'article que je conseille le plus souvent aux nouveaux venus :\n\n<a href=\"https://soins.ewendaviau.com/consteller-cest-quoi-pas-de-la-magie-mais-presque/\">→ Consteller, c'est quoi ?</a>\n\n— Ewen",
			'welcome3_enabled' => 0,
			'welcome3_days'    => 7,
			'welcome3_objet'   => 'Une question pour toi',
			'welcome3_corps'   => "Bonjour {{prenom}},\n\nUne simple question, si tu as 30 secondes : qu'est-ce qui t'a amené·e à t'inscrire ? Réponds-moi en un mot ou deux — ça m'aide vraiment à écrire des choses qui te servent.\n\n— Ewen",
		];
	}
}
