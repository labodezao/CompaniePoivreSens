<?php
/**
 * Module Post-Séance — séquence automatique de 4 emails après une
 * constellation individuelle. Email 4 (J+28) = notification GO/NO-GO
 * uniquement, jamais d'envoi automatique.
 *
 * Chargé par cf-events-booking.php ; l'installation (table, cron,
 * options) passe par les hooks d'activation du plugin principal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CFPS_VERSION', '1.0.0' );
define( 'CFPS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CFPS_URL',     plugin_dir_url( __FILE__ ) );
define( 'CFPS_TABLE',   'cfps_sequence' );

/* ── Includes ─────────────────────────────────────────────── */
require_once CFPS_DIR . 'includes/class-cfps-install.php';
require_once CFPS_DIR . 'includes/class-cfps-sequence.php';
require_once CFPS_DIR . 'includes/class-cfps-emails.php';
require_once CFPS_DIR . 'includes/class-cfps-admin.php';

/* ── Cron ─────────────────────────────────────────────────── */
add_action( 'cfps_daily_cron', [ 'CFPS_Sequence', 'run_daily' ] );

/* ── Liens GO / NO-GO en front ────────────────────────────── */
add_action( 'init', [ 'CFPS_Emails', 'handle_action_links' ] );

/* ── Admin ─────────────────────────────────────────────────── */
add_action( 'admin_menu',    [ 'CFPS_Admin', 'register_menu' ] );
add_action( 'admin_init',    [ 'CFPS_Admin', 'register_settings' ] );
add_action( 'admin_post_cfps_save_theme', [ 'CFPS_Admin', 'save_theme' ] );
add_action( 'wp_ajax_cfps_save_theme',    [ 'CFPS_Admin', 'ajax_save_theme' ] );
