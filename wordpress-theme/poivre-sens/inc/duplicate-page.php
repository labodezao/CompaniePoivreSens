<?php
/**
 * Poivre & Sens — Dupliquer une page
 *
 * Ajoute un lien « Dupliquer » sous chaque page dans Pages › Toutes les
 * pages : la copie (brouillon, même contenu, même modèle) sert de point
 * de départ pour une nouvelle page sans repartir de zéro.
 */
defined('ABSPATH') || exit;

add_filter('page_row_actions', function (array $actions, WP_Post $post): array {
    if (!current_user_can('edit_pages')) {
        return $actions;
    }
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=ps_dupliquer_page&post=' . $post->ID),
        'ps_dupliquer_page_' . $post->ID
    );
    $actions['ps_dupliquer'] = '<a href="' . esc_url($url) . '">' . esc_html__('Dupliquer', 'poivre-sens') . '</a>';
    return $actions;
}, 10, 2);

add_action('admin_post_ps_dupliquer_page', function (): void {
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    check_admin_referer('ps_dupliquer_page_' . $post_id);

    $original = get_post($post_id);
    if (!$original || $original->post_type !== 'page' || !current_user_can('edit_pages')) {
        wp_die(esc_html__('Page introuvable ou action non autorisée.', 'poivre-sens'));
    }

    $nouveau_id = wp_insert_post([
        'post_title'     => $original->post_title . ' ' . __('(copie)', 'poivre-sens'),
        'post_content'   => $original->post_content,
        'post_excerpt'   => $original->post_excerpt,
        'post_status'    => 'draft',
        'post_type'      => 'page',
        'post_author'    => get_current_user_id(),
        'post_parent'    => $original->post_parent,
        'menu_order'     => $original->menu_order,
        'comment_status' => $original->comment_status,
        'ping_status'    => $original->ping_status,
    ], true);

    if (is_wp_error($nouveau_id)) {
        wp_die(esc_html($nouveau_id->get_error_message()));
    }

    // Métadonnées (modèle de page, image mise en avant, champs personnalisés…).
    foreach (get_post_meta($post_id) as $cle => $valeurs) {
        foreach ($valeurs as $valeur) {
            add_post_meta($nouveau_id, $cle, maybe_unserialize($valeur));
        }
    }

    // Taxonomies éventuellement rattachées au type "page".
    foreach (get_object_taxonomies('page') as $taxonomie) {
        $termes = wp_get_object_terms($post_id, $taxonomie, ['fields' => 'ids']);
        if (!is_wp_error($termes) && $termes) {
            wp_set_object_terms($nouveau_id, $termes, $taxonomie);
        }
    }

    wp_safe_redirect(admin_url('post.php?action=edit&post=' . $nouveau_id));
    exit;
});
