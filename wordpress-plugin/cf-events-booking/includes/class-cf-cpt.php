<?php
/**
 * Enregistrement du CPT cf_event (ancre du menu admin + données legacy),
 * du CPT cf_venue et des taxonomies.
 * Le plugin utilise désormais cf_appt_type pour toute la gestion des RDV.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_CPT {

public static function init() {
self::register_post_type();
self::register_taxonomy();
self::register_venue_post_type();
self::register_event_tag_taxonomy();

add_filter( 'cfeb_cat_color', [ __CLASS__, 'get_cat_color' ], 10, 1 );
}

/* ── CPT Événements ──────────────────────────────────────────── */
private static function register_post_type() {
$labels = [
'name'               => 'Réservations CF',
'singular_name'      => 'Réservation CF',
'menu_name'          => 'CF Réservations',
'all_items'          => 'Tous les événements',
];
register_post_type( CFEB_SLUG, [
'labels'        => $labels,
'public'        => true,
'has_archive'   => false,
'rewrite'       => [ 'slug' => 'evenements', 'with_front' => false ],
'supports'      => [ 'title' ],
'menu_icon'     => 'dashicons-calendar-alt',
'menu_position' => 5,
'show_in_rest'  => false,
'show_ui'       => true,
] );
}

/* ── CPT Lieux ───────────────────────────────────────────────── */
private static function register_venue_post_type() {
register_post_type( 'cf_venue', [
'labels'        => [ 'name' => 'Lieux', 'singular_name' => 'Lieu', 'menu_name' => 'Lieux' ],
'public'        => false,
'show_ui'       => false,
'supports'      => [ 'title' ],
'show_in_rest'  => false,
] );
}

/* ── Taxonomie catégories ────────────────────────────────────── */
private static function register_taxonomy() {
register_taxonomy( CFEB_TAX, CFEB_SLUG, [
'labels'            => [ 'name' => 'Catégories', 'singular_name' => 'Catégorie', 'menu_name' => 'Catégories' ],
'hierarchical'      => true,
'public'            => false,
'show_ui'           => false,
'show_admin_column' => false,
'show_in_rest'      => false,
] );
}

/* ── Taxonomie étiquettes ────────────────────────────────────── */
private static function register_event_tag_taxonomy() {
register_taxonomy( 'cf_event_tag', CFEB_SLUG, [
'labels'            => [ 'name' => 'Étiquettes', 'singular_name' => 'Étiquette', 'menu_name' => 'Étiquettes' ],
'hierarchical'      => false,
'public'            => false,
'show_ui'           => false,
'show_admin_column' => false,
'show_in_rest'      => false,
] );
}

/* ── Couleur de catégorie (utilisée par le frontend) ─────────── */
public static function get_cat_color( $term_id ) {
return get_term_meta( $term_id, 'cfeb_cat_color', true ) ?: '#3b82f6';
}

/* ── Lecture meta événement (compatibilité réservations legacy) ─ */
public static function get_meta( $post_id ) {
$cf_raw = get_post_meta( $post_id, '_cfeb_custom_fields', true );
$cf     = [];
if ( $cf_raw ) {
$decoded = json_decode( $cf_raw, true );
if ( is_array( $decoded ) ) {
$cf = $decoded;
}
}
return [
'date_debut'      => get_post_meta( $post_id, '_cfeb_date_debut',      true ),
'date_fin'        => get_post_meta( $post_id, '_cfeb_date_fin',        true ),
'lieu'            => get_post_meta( $post_id, '_cfeb_lieu',            true ),
'lien_visio'      => get_post_meta( $post_id, '_cfeb_lien_visio',      true ),
'max_places'      => get_post_meta( $post_id, '_cfeb_max_places',      true ),
'prix'            => get_post_meta( $post_id, '_cfeb_prix',            true ),
'animateur'       => get_post_meta( $post_id, '_cfeb_animateur',       true ),
'email_contact'   => get_post_meta( $post_id, '_cfeb_email_contact',   true ),
'statut'          => get_post_meta( $post_id, '_cfeb_statut',          true ) ?: 'ouvert',
'deadline'        => get_post_meta( $post_id, '_cfeb_deadline',        true ),
'infos_pratiques' => get_post_meta( $post_id, '_cfeb_infos_pratiques', true ),
'venue_id'        => (int) get_post_meta( $post_id, '_cfeb_venue_id',  true ),
'all_day'         => (int) get_post_meta( $post_id, '_cfeb_all_day',   true ),
'featured'        => (int) get_post_meta( $post_id, '_cfeb_featured',  true ),
'event_url'       => get_post_meta( $post_id, '_cfeb_event_url',       true ),
'statut_event'    => get_post_meta( $post_id, '_cfeb_statut_event',    true ) ?: 'publie',
'delai_min'       => (int) get_post_meta( $post_id, '_cfeb_delai_min', true ),
'max_jours'       => (int) get_post_meta( $post_id, '_cfeb_max_jours', true ),
'custom_fields'   => $cf,
'parent_event'    => (int) get_post_meta( $post_id, '_cfeb_parent_event', true ),
'is_child'        => (int) get_post_meta( $post_id, '_cfeb_is_child',     true ),
];
}

/* ── Statut effectif d'un événement ─────────────────────────── */
public static function compute_statut( $post_id ) {
$meta   = self::get_meta( $post_id );
$statut = $meta['statut'];
$max    = (int) $meta['max_places'];
$dispo  = self::get_dispo( $post_id );
if ( 'ouvert' === $statut && $max > 0 && $dispo <= 0 ) {
$statut = 'complet';
}
if ( 'ouvert' === $statut && $meta['deadline'] && strtotime( $meta['deadline'] ) < time() ) {
$statut = 'ferme';
}
return $statut;
}

/* ── Places disponibles ──────────────────────────────────────── */
public static function get_dispo( $post_id ) {
$meta = self::get_meta( $post_id );
$max  = (int) $meta['max_places'];
if ( 0 === $max ) {
return PHP_INT_MAX;
}
$pris = CF_Booking::count_for_event( $post_id, 'confirme' );
return max( 0, $max - (int) $pris );
}

}
