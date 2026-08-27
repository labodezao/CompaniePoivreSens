<?php
/**
 * archive-evenement.php — Agenda des événements, en liste ou en calendrier
 * URL : /evenements/
 */
get_header();

// Filtres
$filtre_type  = sanitize_text_field($_GET['type']  ?? '');
$filtre_ville = sanitize_text_field($_GET['ville'] ?? '');
$show_past    = isset($_GET['passes']);
$vue          = ($_GET['vue'] ?? '') === 'calendrier' ? 'calendrier' : 'liste';
$base         = get_post_type_archive_link(ps_evt_cpt());

// Mois affiché en vue calendrier (paramètre « mois », format AAAA-MM)
$mois_param = (string) ($_GET['mois'] ?? '');
if (preg_match('/^(\d{4})-(\d{2})$/', $mois_param, $m)) {
    $cal_year  = (int) $m[1];
    $cal_month = (int) $m[2];
} else {
    $cal_year  = (int) date('Y');
    $cal_month = (int) date('n');
}

// Liste des villes disponibles (pour le filtre)
global $wpdb;
$villes = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
    WHERE meta_key = %s AND meta_value != ''
    ORDER BY meta_value
", ps_evt_cle_ville()));
?>

<main class="arch-evts">

    <!-- En-tête -->
    <div style="margin-bottom:52px">
        <p class="lbl"><?php _e('Agenda', 'poivre-sens'); ?></p>
        <h1 class="sh" id="titre-evts"><?php _e('Événements à venir', 'poivre-sens'); ?></h1>
        <div class="regle" style="margin-bottom:28px"></div>
        <p style="font-size:.9rem;color:var(--gris);max-width:520px">
            <?php _e('Spectacles, jams de contact-improvisation, ateliers, stages et résidences de la Compagnie Poivre &amp; Sens.', 'poivre-sens'); ?>
        </p>
    </div>

    <!-- Vue : liste ou calendrier -->
    <div class="cal-list__vues">
        <a href="<?= esc_url(add_query_arg(array_filter(['vue' => 'liste', 'type' => $filtre_type, 'ville' => $filtre_ville, 'passes' => $show_past ? '1' : '']), $base)) ?>"
           class="cal-list__vue-lien<?= $vue === 'liste' ? ' cal-list__vue-lien--actif' : '' ?>">
            <?php _e('Liste', 'poivre-sens'); ?>
        </a>
        <a href="<?= esc_url(ps_evt_url_calendrier($base, $cal_year, $cal_month, $filtre_type, $filtre_ville)) ?>"
           class="cal-list__vue-lien<?= $vue === 'calendrier' ? ' cal-list__vue-lien--actif' : '' ?>">
            <?php _e('Calendrier', 'poivre-sens'); ?>
        </a>
    </div>

    <!-- Filtres -->
    <form class="cal-list__filters" method="get" action="<?= esc_url($base) ?>">
        <input type="hidden" name="vue" value="<?= esc_attr($vue) ?>">
        <?php if ($vue === 'calendrier'): ?>
        <input type="hidden" name="mois" value="<?= esc_attr(sprintf('%04d-%02d', $cal_year, $cal_month)) ?>">
        <?php endif; ?>
        <div class="cal-list__filter-row">

            <select name="type" onchange="this.form.submit()">
                <option value=""><?php _e('Tous les types', 'poivre-sens'); ?></option>
                <?php foreach (ps_evt_liste_types() as $k => $v): ?>
                <option value="<?= esc_attr($k) ?>" <?= selected($filtre_type, $k, false) ?>><?= esc_html($v) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($villes): ?>
            <select name="ville" onchange="this.form.submit()">
                <option value=""><?php _e('Toutes les villes', 'poivre-sens'); ?></option>
                <?php foreach ($villes as $v): ?>
                <option value="<?= esc_attr($v) ?>" <?= selected($filtre_ville, $v, false) ?>><?= esc_html($v) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if ($vue === 'liste'): ?>
            <label class="cal-list__filter-check">
                <input type="checkbox" name="passes" value="1" onchange="this.form.submit()" <?= $show_past ? 'checked' : '' ?>>
                <?php _e('Inclure les événements passés', 'poivre-sens'); ?>
            </label>
            <?php endif; ?>

            <?php if ($filtre_type || $filtre_ville || $show_past): ?>
            <a href="<?= esc_url(add_query_arg('vue', $vue, $base)) ?>" class="cal-list__filter-reset">
                ✕ <?php _e('Réinitialiser', 'poivre-sens'); ?>
            </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($vue === 'calendrier'):
        set_query_var('ps_cal_year',  $cal_year);
        set_query_var('ps_cal_month', $cal_month);
        set_query_var('ps_cal_type',  $filtre_type);
        set_query_var('ps_cal_ville', $filtre_ville);
        set_query_var('ps_cal_base',  $base);
        get_template_part('template-parts/calendar-grid');
    else:
        set_query_var('ps_cal_all',   $show_past);
        set_query_var('ps_cal_type',  $filtre_type);
        set_query_var('ps_cal_ville', $filtre_ville);
        get_template_part('template-parts/calendar-list');
    endif; ?>

    <?php if (current_user_can('publish_posts')): ?>
    <div style="margin-top:48px;padding-top:32px;border-top:1px solid var(--bord);text-align:center">
        <a href="<?= esc_url(admin_url('post-new.php?post_type=' . ps_evt_cpt())) ?>" class="evts__lien">
            + <?php _e('Ajouter un événement', 'poivre-sens'); ?>
        </a>
    </div>
    <?php endif; ?>

</main>

<?php get_footer();
