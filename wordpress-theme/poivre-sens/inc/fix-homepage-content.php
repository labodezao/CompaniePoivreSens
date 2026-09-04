<?php
/**
 * Poivre & Sens — Réparer la page d'accueil
 *
 * inc/block-patterns.php a fait de chaque section de la page d'accueil un
 * shortcode relu à chaque affichage (voir son point 1, commit « toutes les
 * sections deviennent des shortcodes »). Mais côté WordPress, la page
 * « Accueil » déjà en ligne garde le contenu qu'elle avait avant ce
 * changement — des blocs Gutenberg figés pour le hero, le manifeste, les
 * bios des fondateurs, la citation et la note de contact — tant que
 * personne ne les a remplacés une fois. Éditer un champ dans Réglages ›
 * Textes du site n'a donc aucun effet sur ces sections précises tant que
 * ce remplacement n'a pas eu lieu.
 *
 * Cet outil fait ce remplacement en un clic : il pose sur la page
 * d'accueil exactement le contenu du pattern « Page d'accueil complète »
 * (poivre-sens/homepage, voir block-patterns.php) — neuf shortcodes, un
 * par section, rien d'autre. WordPress garde une révision de l'ancien
 * contenu (Pages › Accueil › Révisions) : l'opération n'est pas
 * destructrice.
 */
defined('ABSPATH') || exit;

/** La page d'accueil configurée dans Réglages › Lecture, sinon la page « Accueil » par son slug. */
function ps_fix_homepage_page_id(): int {
    if (get_option('show_on_front') === 'page') {
        $id = (int) get_option('page_on_front');
        if ($id && get_post($id)) return $id;
    }
    $page = get_page_by_path('accueil');
    return $page ? $page->ID : 0;
}

add_action('admin_menu', function (): void {
    add_management_page(
        __("Réparer la page d'accueil", 'poivre-sens'),
        __("Page d'accueil → shortcodes", 'poivre-sens'),
        'manage_options',
        'ps-fix-homepage',
        'ps_fix_homepage_render_page'
    );
});

function ps_fix_homepage_render_page(): void {
    if (!current_user_can('manage_options')) return;

    $page_id = ps_fix_homepage_page_id();
    $page    = $page_id ? get_post($page_id) : null;
    ?>
    <div class="wrap">
      <h1><?= esc_html__("Réparer la page d'accueil", 'poivre-sens') ?></h1>

      <?php if (isset($_GET['ps_fix_done'])): ?>
      <div class="notice notice-success"><p><?= esc_html__('Fait. La page d\'accueil est maintenant entièrement composée de shortcodes.', 'poivre-sens') ?></p></div>
      <?php elseif (isset($_GET['ps_fix_err'])): ?>
      <div class="notice notice-error"><p><?= esc_html__("Aucune page d'accueil trouvée (ni page statique définie dans Réglages › Lecture, ni page « Accueil »).", 'poivre-sens') ?></p></div>
      <?php endif; ?>

      <p style="max-width:46em">
        <?= esc_html__("Le hero, le manifeste, les bios des fondateurs, la citation et la note de contact de la page d'accueil ont d'abord existé en blocs Gutenberg figés, insérés une fois puis jamais recalculés. Éditer ces champs dans Réglages › Textes du site n'a donc aucun effet tant que la page n'a pas été repassée en shortcodes.", 'poivre-sens') ?>
      </p>
      <p style="max-width:46em">
        <?= esc_html__("Cet outil remplace le contenu de la page d'accueil par le pattern « Page d'accueil complète » — neuf shortcodes, un par section, tous relus à chaque affichage depuis Réglages › Textes du site.", 'poivre-sens') ?>
      </p>

      <?php if ($page): ?>
      <p style="max-width:46em">
        <?= esc_html(sprintf(__('Page d\'accueil détectée : « %1$s » (#%2$d).', 'poivre-sens'), $page->post_title, $page->ID)) ?>
      </p>
      <p style="max-width:46em">
        <strong><?= esc_html__('Non destructeur', 'poivre-sens') ?></strong> —
        <?= esc_html__('WordPress garde une révision de l\'ancien contenu (Pages › Accueil › Révisions) : vous pouvez revenir en arrière si besoin.', 'poivre-sens') ?>
      </p>
      <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
        <?php wp_nonce_field('ps_fix_homepage'); ?>
        <input type="hidden" name="action" value="ps_fix_homepage">
        <button type="submit" class="button button-primary" onclick="return confirm(<?= esc_attr(wp_json_encode(__("Remplacer le contenu actuel de la page d'accueil par la version shortcode ?", 'poivre-sens'))) ?>);">
          <?= esc_html__("Passer la page d'accueil en shortcodes", 'poivre-sens') ?>
        </button>
      </form>
      <?php else: ?>
      <div class="notice notice-warning"><p><?= esc_html__("Aucune page d'accueil trouvée (ni page statique définie dans Réglages › Lecture, ni page « Accueil »).", 'poivre-sens') ?></p></div>
      <?php endif; ?>
    </div>
    <?php
}

add_action('admin_post_ps_fix_homepage', function (): void {
    check_admin_referer('ps_fix_homepage');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Action non autorisée.', 'poivre-sens'));
    }

    $page_id = ps_fix_homepage_page_id();
    if (!$page_id) {
        wp_safe_redirect(add_query_arg('ps_fix_err', '1', admin_url('tools.php?page=ps-fix-homepage')));
        exit;
    }

    $contenu = _ps_pat_hero() . _ps_pat_galerie_sc()
             . _ps_pat_manifeste() . _ps_pat_artistes()
             . _ps_pat_projet() . _ps_pat_activites()
             . _ps_pat_evenements_sc() . _ps_pat_esthetique()
             . _ps_pat_newsletter_sc() . _ps_pat_contact();

    wp_update_post([
        'ID'           => $page_id,
        'post_content' => $contenu,
    ]);

    wp_safe_redirect(add_query_arg('ps_fix_done', '1', admin_url('tools.php?page=ps-fix-homepage')));
    exit;
});
