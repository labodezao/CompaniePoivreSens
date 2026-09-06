<?php
/**
 * Poivre & Sens — Réorganiser la galerie par glisser-déposer
 *
 * [ps_galerie] (page d'accueil) affiche les 6 premières photos de
 * Galerie › Toutes les photos, dans l'ordre où elles y sont listées.
 * Sans cet outil, changer cet ordre demandait de modifier manuellement
 * le champ « Ordre » (page-attributes) photo par photo.
 */
defined('ABSPATH') || exit;

/** Liste triée par ordre d'affichage plutôt que par date, sans pagination
 *  — un tri partiel (page par page) rendrait le glisser-déposer incohérent. */
add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-galerie') return;
    if ($query->get('orderby')) return; // un tri explicite (colonne cliquée) prime.

    $query->set('orderby', 'menu_order');
    $query->set('order', 'ASC');
    $query->set('posts_per_page', -1);
});

/** Colonne d'aperçu : reconnaître une photo au premier coup d'œil pour la
 *  glisser au bon endroit, plutôt que de deviner à partir du seul titre. */
add_filter('manage_galerie_posts_columns', function (array $colonnes): array {
    $nouvelles = [];
    foreach ($colonnes as $cle => $label) {
        if ($cle === 'title') {
            $nouvelles['ps_apercu'] = __('Aperçu', 'poivre-sens');
        }
        $nouvelles[$cle] = $label;
    }
    return $nouvelles;
});

add_action('manage_galerie_posts_custom_column', function (string $colonne, int $post_id): void {
    if ($colonne !== 'ps_apercu') return;
    $vignette = get_the_post_thumbnail($post_id, [60, 60], ['style' => 'object-fit:cover;border-radius:4px;display:block']);
    echo $vignette ?: '<span style="color:#aaa;font-size:11px">—</span>';
}, 10, 2);

add_action('admin_notices', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-galerie') return;
    echo '<div class="notice notice-info"><p>' . esc_html__(
        'Glissez une ligne depuis sa vignette pour changer l\'ordre des photos sur l\'accueil (les 6 premières y sont affichées).',
        'poivre-sens'
    ) . '</p></div>';
    echo '<style>.column-ps_apercu{width:68px} .column-ps_apercu img{cursor:grab}</style>';
});

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'edit.php' || ($_GET['post_type'] ?? '') !== 'galerie') return;
    if (!current_user_can('edit_others_posts')) return;

    wp_enqueue_script('jquery-ui-sortable');
    wp_add_inline_script('jquery-ui-sortable', '
    jQuery(function ($) {
        var $liste = $("#the-list");
        if (!$liste.length || $liste.find("tr").length < 2) return;
        $liste.sortable({
            items: "tr",
            axis: "y",
            cursor: "move",
            opacity: 0.7,
            handle: ".column-ps_apercu, .check-column",
            helper: function (e, tr) {
                var $orig = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (i) { $(this).width($orig.eq(i).width()); });
                return $helper;
            },
            update: function () {
                var ids = $liste.find("tr").map(function () {
                    return (this.id || "").replace("post-", "");
                }).get();
                $.post(ajaxurl, {
                    action: "ps_galerie_reorder",
                    nonce: ' . wp_json_encode(wp_create_nonce('ps_galerie_reorder')) . ',
                    ids: ids
                });
            }
        });
    });
    ');
});

add_action('wp_ajax_ps_galerie_reorder', function (): void {
    check_ajax_referer('ps_galerie_reorder', 'nonce');
    if (!current_user_can('edit_others_posts')) {
        wp_send_json_error(null, 403);
    }

    foreach (array_map('absint', (array) ($_POST['ids'] ?? [])) as $ordre => $id) {
        if (get_post_type($id) !== 'galerie') continue;
        wp_update_post(['ID' => $id, 'menu_order' => $ordre]);
    }
    wp_send_json_success();
});
