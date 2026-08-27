<?php
/**
 * Plugin Name:  CF Événements & Réservations
 * Plugin URI:   https://soins.ewendaviau.com/
 * Description:  Gestion légère des événements de groupe et réservations en 2 clics, avec module Post-Séance intégré (séquence de 4 emails, validation GO/NO-GO) et acompte SumUp. Shortcodes : [cf_events] [cf_calendrier] [cf_mes_reservations]
 * Version:      1.13.0
 * Author:       Ewen Daviau — Constellations Familiales
 * License:      GPL-2.0-or-later
 * Text Domain:  cf-events
 *
 * ═══════════════════════════════════════════════════════════════════
 *  INSTALLATION FTP (wp-content/plugins/)
 * ═══════════════════════════════════════════════════════════════════
 *
 *  wp-content/plugins/
 *  └── cf-events-booking/
 *      ├── cf-events-booking.php   ← CE fichier
 *      ├── includes/               ← classes PHP
 *      ├── assets/                 ← CSS + JS
 *      └── templates/              ← gabarits d'affichage
 *
 *  Étapes :
 *  1. Uploader le dossier cf-events-booking/ dans wp-content/plugins/
 *  2. Activer le plugin dans WordPress → Extensions
 *  3. Aller dans "Événements" pour créer des ateliers
 *  4. Insérer les shortcodes dans vos pages
 *
 *  Shortcodes disponibles :
 *  [cf_events]                         → liste des prochains événements
 *  [cf_events nombre="6"]              → limiter le nombre affiché
 *  [cf_events categorie="constellation"] → filtrer par catégorie
 *  [cf_events vue="calendrier"]        → vue calendrier mensuel
 *  [cf_calendrier]                     → alias vue calendrier
 *  [cf_mes_reservations]               → réservations de l'utilisateur
 *  [cf_rdv type="slug-ou-id"]          → widget rendez-vous (créneaux)
 *  [cf_rdv type="slug-a,slug-b"]       → cumuler plusieurs types (aussi « + »)
 *  [cf_rdv type="slug" vue="liste"]    → forcer la vue (liste | semaine)
 *  [cf_rdv type="a,b" titre="Ateliers"]→ titre personnalisé (titre="none" = masqué)
 * ═══════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CFEB_VERSION',    '1.17.1' );
define( 'CFEB_DIR',        plugin_dir_path( __FILE__ ) );
// Surchargeable quand le plugin est embarqué dans le thème (URL calculée autrement)
if ( ! defined( 'CFEB_URL' ) ) {
	define( 'CFEB_URL', plugin_dir_url( __FILE__ ) );
}
define( 'CFEB_TABLE',      'cf_bookings' );
define( 'CFEB_SLUG',       'cf_event' );
define( 'CFEB_TAX',        'cf_event_cat' );

// Avis Google — fiche « Constellations systémiques et familiales St Nazaire ».
// Valeur par défaut utilisée partout où l'URL n'est pas surchargée par un
// réglage admin (CF Post-Séance → Paramètres → « URL avis Google »).
define( 'CFEB_GOOGLE_REVIEW_URL', 'https://g.page/r/CWGmDTN61wuzEAE/review' );

/* ── Chargement des classes ─────────────────────────────────────── */
foreach ( [
	'class-cf-cpt',
	'class-cf-booking',
	'class-cf-admin',
	'class-cf-stats',
	'class-cf-frontend',
	'class-cf-ajax',
	'class-cf-email',
	'class-cf-ical',
	'class-cf-jsonld',
	'class-cf-reminders',
	'class-cf-opengraph',
	'class-cf-rest-api',
	'class-cf-widget',
	'class-cf-planning',
	'class-cf-google-calendar',
	'class-cf-appt-type',
	'class-cf-sumup',
	'class-cf-mailpoet',
	'class-cf-vouchers',
	'class-cf-testimonials',
	'class-cf-privacy',
	'class-cf-dashboard',
	'class-cf-next-event',
] as $f ) {
	require_once CFEB_DIR . 'includes/' . $f . '.php';
}

/* ── Modules optionnels ─────────────────────────────────────────
 * Tous chargés par défaut. Un site qui n'a besoin que des
 * événements et des réservations — ou qui gère déjà sa newsletter
 * ailleurs — peut en désactiver par l'option « cfeb_modules_off »,
 * un tableau d'identifiants pris parmi ceux ci-dessous.
 * ─────────────────────────────────────────────────────────────── */
$cfeb_modules = [
	// identifiant   => fichier
	'post-seance'    => 'modules/post-seance/cf-post-seance.php',    // séquence de 4 emails après séance
	'newsletter'     => 'modules/newsletter/cf-newsletter.php',      // emailing maison, listes, envoi par lots
	'pleine-vie'     => 'modules/pleine-vie/cf-pleine-vie.php',      // inscriptions + emails de suivi
	'fiche-intake'   => 'modules/fiche-intake/cf-fiche-intake.php',  // formulaire en ligne, ex-PDF
];
$cfeb_off = (array) get_option( 'cfeb_modules_off', [] );

foreach ( $cfeb_modules as $cfeb_id => $cfeb_fichier ) {
	if ( ! in_array( $cfeb_id, $cfeb_off, true ) ) {
		require_once CFEB_DIR . $cfeb_fichier;
	}
}
unset( $cfeb_modules, $cfeb_off, $cfeb_id, $cfeb_fichier );

/* ── Activation : création de la table bookings ─────────────────── */
register_activation_hook( __FILE__, 'cfeb_activate' );
function cfeb_activate() {
	cfeb_create_table();
	CF_CPT::init();
	flush_rewrite_rules();
	if ( class_exists( 'CF_Reminders' ) ) {
		CF_Reminders::schedule();
	}
	if ( class_exists( 'CFPS_Install' ) ) {
		CFPS_Install::activate();
	}
	if ( class_exists( 'CFNL_Install' ) ) {
		CFNL_Install::activate();
	}
	if ( class_exists( 'CF_Vouchers' ) ) {
		CF_Vouchers::create_table();
	}
	if ( class_exists( 'CF_Privacy' ) ) {
		CF_Privacy::schedule();
	}
	if ( class_exists( 'CFPV_Install' ) ) {
		CFPV_Install::activate();
	}
	if ( class_exists( 'CFI_Install' ) ) {
		CFI_Install::activate();
	}
}

function cfeb_create_table() {
	global $wpdb;
	$table   = $wpdb->prefix . CFEB_TABLE;
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
		id               bigint(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id         bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
		appt_type_id     bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
		slot_debut       datetime     NULL DEFAULT NULL,
		slot_fin         datetime     NULL DEFAULT NULL,
		user_id          bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
		prenom           varchar(100) NOT NULL DEFAULT '',
		nom              varchar(100) NOT NULL DEFAULT '',
		email            varchar(200) NOT NULL DEFAULT '',
		telephone        varchar(50)  NOT NULL DEFAULT '',
		nb_places        tinyint(3)   UNSIGNED NOT NULL DEFAULT 1,
		statut           varchar(20)  NOT NULL DEFAULT 'confirme',
		notes            text         NOT NULL,
		token            varchar(64)  NOT NULL DEFAULT '',
		champs_perso     text         NOT NULL,
		rappel_envoye    tinyint(1)   NOT NULL DEFAULT 0,
		followup_envoye  tinyint(1)   NOT NULL DEFAULT 0,
		motif_annulation varchar(500) NOT NULL DEFAULT '',
		paye             tinyint(1)   NOT NULL DEFAULT 0,
		mode_paiement    varchar(50)  NOT NULL DEFAULT '',
		acompte_url      varchar(500) NOT NULL DEFAULT '',
		modalite         varchar(20)  NOT NULL DEFAULT '',
		adresse          varchar(255) NOT NULL DEFAULT '',
		cree_le          datetime     NOT NULL,
		PRIMARY KEY (id),
		KEY event_id (event_id),
		KEY appt_type_id (appt_type_id),
		KEY user_id (user_id),
		KEY email (email(100)),
		KEY statut (statut),
		KEY token (token)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Fallback : ALTER TABLE explicite pour les colonnes que dbDelta aurait pu manquer.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$existing = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
	if ( is_array( $existing ) && ! empty( $existing ) ) {
		$missing = [
			'appt_type_id'     => "bigint(20) UNSIGNED NOT NULL DEFAULT 0",
			'slot_debut'       => "datetime NULL DEFAULT NULL",
			'slot_fin'         => "datetime NULL DEFAULT NULL",
			'motif_annulation' => "varchar(500) NOT NULL DEFAULT ''",
			'paye'             => "tinyint(1) NOT NULL DEFAULT 0",
			'mode_paiement'    => "varchar(50) NOT NULL DEFAULT ''",
			'acompte_url'      => "varchar(500) NOT NULL DEFAULT ''",
			'modalite'         => "varchar(20) NOT NULL DEFAULT ''",
			'adresse'          => "varchar(255) NOT NULL DEFAULT ''",
		];
		foreach ( $missing as $col => $def ) {
			if ( ! in_array( $col, $existing, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}" );
			}
		}
	}

	update_option( 'cfeb_db_version', CFEB_VERSION );
}

function cfeb_table_has_required_columns() {
	global $wpdb;
	$table = $wpdb->prefix . CFEB_TABLE;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	if ( ! is_array( $columns ) || empty( $columns ) ) {
		return false;
	}

	$required = [
		'appt_type_id',
		'slot_debut',
		'slot_fin',
		'motif_annulation',
		'paye',
		'mode_paiement',
		'acompte_url',
		'modalite',
		'adresse',
	];

	return ! array_diff( $required, $columns );
}

/* ── Désactivation ──────────────────────────────────────────────── */
register_deactivation_hook( __FILE__, function () {
	if ( class_exists( 'CF_Reminders' ) ) {
		CF_Reminders::unschedule();
	}
	if ( class_exists( 'CFPS_Install' ) ) {
		CFPS_Install::deactivate();
	}
	if ( class_exists( 'CFNL_Install' ) ) {
		CFNL_Install::deactivate();
	}
	if ( class_exists( 'CFPV_Sequence' ) ) {
		CFPV_Sequence::unschedule();
	}
	flush_rewrite_rules();
} );

/* ── Mise à jour de la table si version DB ancienne ─────────────── */
function cfeb_maybe_migrate() {
	$needs_version_migration = get_option( 'cfeb_db_version' ) !== CFEB_VERSION;
	$needs_schema_migration  = ! cfeb_table_has_required_columns();

	if ( $needs_version_migration || $needs_schema_migration ) {
		cfeb_create_table();
	}

	// Module Post-Séance : rejoue l'installation si le cron a disparu
	// (mise à jour du plugin par écrasement FTP, sans réactivation)
	if ( class_exists( 'CFPS_Install' ) && ! wp_next_scheduled( 'cfps_daily_cron' ) ) {
		CFPS_Install::activate();
	}
	// Module Newsletter : idem (tables + cron d'envoi)
	if ( class_exists( 'CFNL_Install' ) && ! wp_next_scheduled( 'cfnl_send_cron' ) ) {
		CFNL_Install::activate();
	}
	// Module Newsletter : migration de schéma versionnée (ajout de colonnes
	// lors d'une mise à jour par écrasement FTP, sans réactivation).
	if ( class_exists( 'CFNL_Install' ) && defined( 'CFNL_VERSION' )
		&& get_option( 'cfnl_db_version' ) !== CFNL_VERSION ) {
		CFNL_Install::create_tables();
		update_option( 'cfnl_db_version', CFNL_VERSION );
	}
	// Bons cadeaux + purge RGPD : auto-réparation après update FTP
	if ( class_exists( 'CF_Vouchers' ) && get_option( 'cfeb_vouchers_db' ) !== '1' ) {
		CF_Vouchers::create_table();
		update_option( 'cfeb_vouchers_db', '1' );
	}
	if ( class_exists( 'CF_Privacy' ) && ! wp_next_scheduled( 'cfeb_privacy_cron' ) ) {
		CF_Privacy::schedule();
	}
	// Module Pleine Vie : crée la table si absente (update FTP sans réactivation)
	if ( class_exists( 'CFPV_Install' ) && get_option( 'cfpv_db' ) !== '2' ) {
		CFPV_Install::create_table();
		if ( ! get_option( 'cfpv_settings' ) ) {
			update_option( 'cfpv_settings', CFPV_Install::default_settings() );
		}
		update_option( 'cfpv_db', '2' );
	}
	// Cron de la séquence d'accompagnement Pleine Vie
	if ( class_exists( 'CFPV_Sequence' ) && ! wp_next_scheduled( 'cfpv_daily_cron' ) ) {
		CFPV_Sequence::schedule();
	}
	// Module Fiche thèmes constellations : crée la table si absente (update FTP sans réactivation)
	if ( class_exists( 'CFI_Install' ) && get_option( 'cfi_db' ) !== '1' ) {
		CFI_Install::create_table();
		update_option( 'cfi_db', '1' );
	}
}
// plugins_loaded est déjà passé quand le code est chargé depuis le thème
if ( did_action( 'plugins_loaded' ) ) {
	cfeb_maybe_migrate();
} else {
	add_action( 'plugins_loaded', 'cfeb_maybe_migrate' );
}

/* ── Initialisation ─────────────────────────────────────────────── */
add_action( 'init',                  [ 'CF_CPT',            'init' ] );
add_action( 'admin_menu',            [ 'CF_Admin',          'init' ] );
add_action( 'admin_menu',            [ 'CF_Stats',          'register_page' ] );
add_action( 'admin_menu',            [ 'CF_Planning',       'register_pages' ] );
add_action( 'wp_dashboard_setup',    [ 'CF_Admin',          'register_dashboard_widget' ] );
add_action( 'admin_enqueue_scripts', [ 'CF_Admin',          'enqueue' ] );
add_action( 'all_admin_notices',     [ 'CF_Admin',          'maybe_render_cpt_tab_nav' ] );

/* ── Bons cadeaux, témoignages, RGPD, tableau de bord ───────────── */
add_action( 'init',       [ 'CF_Testimonials', 'init' ] );
add_action( 'init',       [ 'CF_Testimonials', 'handle_submit' ] );
add_action( 'init',       [ 'CF_Vouchers',     'handle_purchase' ] );
add_action( 'init',       [ 'CF_Privacy',      'init' ] );
add_action( 'admin_menu', [ 'CF_Dashboard',    'register_menu' ], 5 );
add_action( 'admin_menu', [ 'CF_Vouchers',     'register_menu' ] );
add_shortcode( 'cf_bon_cadeau',      [ 'CF_Vouchers',     'shortcode' ] );
add_shortcode( 'cf_temoignage_form',   [ 'CF_Testimonials', 'form_shortcode' ] );
add_shortcode( 'cf_temoignages',       [ 'CF_Testimonials', 'display_shortcode' ] );
add_shortcode( 'cf_temoignages_google', [ 'CF_Testimonials', 'display_google_shortcode' ] );
add_shortcode( 'cf_prochain_atelier',[ 'CF_Next_Event',   'shortcode' ] );
add_action( 'wp_enqueue_scripts',    [ 'CF_Frontend',       'enqueue' ] );
add_action( 'init',                  [ 'CF_Frontend',       'init_shortcodes' ] );
add_action( 'init',                  [ 'CF_Ajax',           'init' ] );
add_action( 'init',                  [ 'CF_Ical',           'init' ] );
add_action( 'wp_head',               [ 'CF_JsonLd',         'init' ] );
add_action( 'init',                  [ 'CF_Reminders',      'init' ] );
add_action( 'wp_head',               [ 'CF_OpenGraph',      'init' ] );
add_action( 'rest_api_init',         [ 'CF_RestApi',        'init' ] );
add_action( 'widgets_init',          [ 'CF_Widget',         'register' ] );
add_action( 'init',                  [ 'CF_GoogleCalendar', 'init' ] );
add_action( 'init',                  [ 'CF_ApptType',       'init' ] );
