<?php
/**
 * Fiche thèmes constellations — installation (table dédiée).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Install {

	public static function activate() {
		self::create_table();
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cfi_fiches';
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta( "CREATE TABLE IF NOT EXISTS {$t} (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			prenom      VARCHAR(100) NOT NULL DEFAULT '',
			nom         VARCHAR(100) NOT NULL DEFAULT '',
			email       VARCHAR(200) NOT NULL DEFAULT '',
			telephone   VARCHAR(50)  NOT NULL DEFAULT '',
			donnees     LONGTEXT     NOT NULL,
			vu          TINYINT(1)   NOT NULL DEFAULT 0,
			cree_le     DATETIME     NOT NULL,
			PRIMARY KEY (id),
			KEY email (email(100))
		) " . $wpdb->get_charset_collate() . ';' );
	}
}
