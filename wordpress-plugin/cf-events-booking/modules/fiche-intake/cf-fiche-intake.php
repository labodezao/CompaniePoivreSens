<?php
/**
 * Module Fiche thèmes constellations — formulaire en ligne pour le premier
 * rendez-vous, en remplacement du PDF « Personnel et confidentiel » à
 * renvoyer par email. À la soumission : email récapitulatif complet à
 * l'administrateur, accusé de réception au client, stockage pour
 * consultation dans l'admin.
 *
 * Chargé par cf-events-booking.php ; installation via ses hooks d'activation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CFI_VERSION', '1.0.0' );
define( 'CFI_DIR', plugin_dir_path( __FILE__ ) );

require_once CFI_DIR . 'includes/class-cfi-install.php';
require_once CFI_DIR . 'includes/class-cfi-fiches.php';
require_once CFI_DIR . 'includes/class-cfi-genogramme.php';
require_once CFI_DIR . 'includes/class-cfi-ai.php';
require_once CFI_DIR . 'includes/class-cfi-emails.php';
require_once CFI_DIR . 'includes/class-cfi-public.php';
require_once CFI_DIR . 'includes/class-cfi-admin.php';
require_once CFI_DIR . 'includes/class-cfi-clients.php';

/* ── Front ─────────────────────────────────────────────────────────── */
add_action( 'init', [ 'CFI_Public', 'handle_form' ] );
add_shortcode( 'cf_fiche_intake', [ 'CFI_Public', 'form_shortcode' ] );

/* ── Admin ─────────────────────────────────────────────────────────── */
add_action( 'admin_menu', [ 'CFI_Admin', 'register_menu' ] );
add_action( 'admin_init', [ 'CFI_Admin', 'handle_actions' ] );
add_action( 'admin_menu', [ 'CFI_Clients', 'register_menu' ] );
