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

// Champ couleur sur les écrans d'ajout/édition de catégorie — la seule
// façon pour l'administrateur de choisir la couleur d'une catégorie,
// maintenant que l'écran de gestion est visible (show_ui ci-dessus).
add_action( CFEB_TAX . '_add_form_fields',  [ __CLASS__, 'render_color_field_add' ] );
add_action( CFEB_TAX . '_edit_form_fields', [ __CLASS__, 'render_color_field_edit' ] );
add_action( 'create_' . CFEB_TAX, [ __CLASS__, 'save_color_field' ] );
add_action( 'edited_' . CFEB_TAX, [ __CLASS__, 'save_color_field' ] );
add_filter( 'manage_edit-' . CFEB_TAX . '_columns',       [ __CLASS__, 'add_color_column' ] );
add_filter( 'manage_' . CFEB_TAX . '_custom_column',      [ __CLASS__, 'render_color_column' ], 10, 3 );
}

/* ── Champ couleur de catégorie ───────────────────────────────
   Le reste du plugin (get_cat_color() ci-dessus) sait déjà lire cette
   couleur ; il ne manquait qu'un endroit pour la choisir. */
public static function render_color_field_add() {
?>
<div class="form-field">
<label for="cfeb_cat_color"><?php esc_html_e( 'Couleur', 'cf-events' ); ?></label>
<input type="color" name="cfeb_cat_color" id="cfeb_cat_color" value="#3b82f6">
<p><?php esc_html_e( 'Couleur du badge affiché sur le site pour les événements de cette catégorie.', 'cf-events' ); ?></p>
</div>
<?php
}

public static function render_color_field_edit( $term ) {
$couleur = self::get_cat_color( $term->term_id );
?>
<tr class="form-field">
<th scope="row"><label for="cfeb_cat_color"><?php esc_html_e( 'Couleur', 'cf-events' ); ?></label></th>
<td>
<input type="color" name="cfeb_cat_color" id="cfeb_cat_color" value="<?php echo esc_attr( $couleur ); ?>">
<p class="description"><?php esc_html_e( 'Couleur du badge affiché sur le site pour les événements de cette catégorie.', 'cf-events' ); ?></p>
</td>
</tr>
<?php
}

public static function save_color_field( $term_id ) {
if ( ! isset( $_POST['cfeb_cat_color'] ) ) return;
$couleur = sanitize_text_field( wp_unslash( $_POST['cfeb_cat_color'] ) );
if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $couleur ) ) {
update_term_meta( $term_id, 'cfeb_cat_color', $couleur );
}
}

public static function add_color_column( $columns ) {
$nouvelles = [];
foreach ( $columns as $cle => $label ) {
$nouvelles[ $cle ] = $label;
if ( 'name' === $cle ) {
$nouvelles['cfeb_cat_color'] = __( 'Couleur', 'cf-events' );
}
}
return $nouvelles;
}

public static function render_color_column( $contenu, $colonne, $term_id ) {
if ( 'cfeb_cat_color' !== $colonne ) return $contenu;
$couleur = self::get_cat_color( $term_id );
return sprintf(
'<span style="display:inline-block;width:16px;height:16px;border-radius:50%%;background:%1$s;border:1px solid rgba(0,0,0,.15);vertical-align:middle;margin-right:6px"></span>%1$s',
esc_html( $couleur )
);
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
'show_ui'           => true,
'show_in_menu'      => true,
'show_admin_column' => true,
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
