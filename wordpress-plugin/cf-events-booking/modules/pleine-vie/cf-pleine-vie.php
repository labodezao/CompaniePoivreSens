<?php
/**
 * Module Programme Pleine Vie — gestion des inscriptions et des emails
 * (accusé de réception, notification admin, validation, annulation).
 * Modèles d'emails éditables depuis l'admin. Aucune dépendance externe.
 *
 * Chargé par cf-events-booking.php ; installation via ses hooks d'activation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CFPV_VERSION', '1.1.0' );
define( 'CFPV_DIR', plugin_dir_path( __FILE__ ) );

require_once CFPV_DIR . 'includes/class-cfpv-install.php';
require_once CFPV_DIR . 'includes/class-cfpv-registrations.php';
require_once CFPV_DIR . 'includes/class-cfpv-emails.php';
require_once CFPV_DIR . 'includes/class-cfpv-sequence.php';
require_once CFPV_DIR . 'includes/class-cfpv-public.php';
require_once CFPV_DIR . 'includes/class-cfpv-admin.php';

/* ── Front ─────────────────────────────────────────────────────────── */
add_action( 'init', [ 'CFPV_Public', 'handle_form' ] );
add_shortcode( 'cf_pleine_vie_inscription', [ 'CFPV_Public', 'form_shortcode' ] );

/* ── Séquence d'accompagnement (cron quotidien) ────────────────────── */
add_action( 'cfpv_daily_cron', [ 'CFPV_Sequence', 'run_daily' ] );

/* ── Admin ─────────────────────────────────────────────────────────── */
add_action( 'admin_menu', [ 'CFPV_Admin', 'register_menu' ] );
add_action( 'admin_init', [ 'CFPV_Admin', 'handle_actions' ] );
