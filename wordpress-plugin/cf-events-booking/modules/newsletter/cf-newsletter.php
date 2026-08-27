<?php
/**
 * Module Newsletter — système d'emailing maison, gratuit, 100% intégré.
 * Abonnés (double opt-in + fréquence), campagnes (brouillons), envoi par
 * lots via cron, suivi ouvertures/clics, désinscription. Aucune dépendance
 * à MailPoet. L'envoi passe par wp_mail() : brancher un relais SMTP gratuit
 * (Brevo…) pour la délivrabilité.
 *
 * Chargé par cf-events-booking.php ; installation via ses hooks d'activation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CFNL_VERSION', '1.5.0' );
define( 'CFNL_DIR',     plugin_dir_path( __FILE__ ) );

require_once CFNL_DIR . 'includes/class-cfnl-install.php';
require_once CFNL_DIR . 'includes/class-cfnl-subscribers.php';
require_once CFNL_DIR . 'includes/class-cfnl-campaigns.php';
require_once CFNL_DIR . 'includes/class-cfnl-sender.php';
require_once CFNL_DIR . 'includes/class-cfnl-public.php';
require_once CFNL_DIR . 'includes/class-cfnl-smtp.php';
require_once CFNL_DIR . 'includes/class-cfnl-automations.php';
require_once CFNL_DIR . 'includes/class-cfnl-library.php';
require_once CFNL_DIR . 'includes/class-cfnl-admin.php';

/* ── SMTP intégré : route tous les emails du site si configuré ──── */
add_action( 'phpmailer_init', [ 'CFNL_SMTP', 'configure' ] );

/* ── Automatisation : nouvel article publié → newsletter ────────── */
add_action( 'transition_post_status', [ 'CFNL_Automations', 'on_post_published' ], 10, 3 );

/* ── Intervalle cron personnalisé (5 min) ─────────────────────────── */
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['cfnl_five_min'] ) ) {
		$schedules['cfnl_five_min'] = [ 'interval' => 300, 'display' => 'Toutes les 5 minutes (Newsletter)' ];
	}
	return $schedules;
} );

/* ── Cron d'envoi par lots ────────────────────────────────────────── */
add_action( 'cfnl_send_cron', [ 'CFNL_Sender', 'run_cron' ] );

/* ── Automatisation : nouveau texte dans la bibliothèque → campagne ──
   Repère aussi les textes arrivés par déploiement du plugin (FTP), que
   rien d'autre ne signalerait. */
add_action( 'cfnl_send_cron', [ 'CFNL_Automations', 'check_new_library' ] );

/* ── Front : liens d'action + formulaire ──────────────────────────── */
add_action( 'init', [ 'CFNL_Public', 'handle_actions' ] );
add_action( 'init', [ 'CFNL_Public', 'handle_form' ] );
add_shortcode( 'cf_newsletter', [ 'CFNL_Public', 'form_shortcode' ] );

/* ── Admin ─────────────────────────────────────────────────────────── */
add_action( 'admin_menu',  [ 'CFNL_Admin', 'register_menu' ] );
add_action( 'admin_init',  [ 'CFNL_Admin', 'handle_actions' ] );
