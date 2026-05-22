<?php
/**
 * archive-evenement.php — Calendrier événements mode LISTE
 * URL : /evenements/
 */
get_header();

// Filtres
$filtre_type  = sanitize_text_field($_GET['type']  ?? '');
$filtre_ville = sanitize_text_field($_GET['ville'] ?? '');
$show_past    = isset($_GET['passes']);

// Liste des villes disponibles (pour le filtre)
global $wpdb;
$villes = $wpdb->get_col("
    SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
    WHERE meta_key='_evt_ville' AND meta_value != ''
    ORDER BY meta_value
");
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

    <!-- Filtres -->
    <form class="cal-list__filters" method="get" action="<?= esc_url(get_post_type_archive_link('evenement')) ?>">
        <div class="cal-list__filter-row">

            <select name="type" onchange="this.form.submit()">
                <option value=""><?php _e('Tous les types', 'poivre-sens'); ?></option>
                <?php foreach (['spectacle'=>'Spectacle vivant','jam'=>'Jam contact','atelier'=>'Atelier / Stage','residence'=>'Résidence','concert'=>'Concert','autre'=>'Autre'] as $k=>$v): ?>
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

            <label class="cal-list__filter-check">
                <input type="checkbox" name="passes" value="1" onchange="this.form.submit()" <?= $show_past ? 'checked' : '' ?>>
                <?php _e('Inclure les événements passés', 'poivre-sens'); ?>
            </label>

            <?php if ($filtre_type || $filtre_ville || $show_past): ?>
            <a href="<?= esc_url(get_post_type_archive_link('evenement')) ?>" class="cal-list__filter-reset">
                ✕ <?php _e('Réinitialiser', 'poivre-sens'); ?>
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Calendrier liste -->
    <?php
    set_query_var('ps_cal_all', $show_past);
    // Passer les filtres supplémentaires via hook
    add_filter('ps_cal_list_meta_query', function ($mq) use ($filtre_type, $filtre_ville) {
        if ($filtre_type) {
            $mq[] = ['key' => '_evt_type', 'value' => $filtre_type, 'compare' => '='];
        }
        if ($filtre_ville) {
            $mq[] = ['key' => '_evt_ville', 'value' => $filtre_ville, 'compare' => '='];
        }
        return $mq;
    });
    get_template_part('template-parts/calendar-list');
    ?>

    <?php if (current_user_can('publish_posts')): ?>
    <div style="margin-top:48px;padding-top:32px;border-top:1px solid var(--bord);text-align:center">
        <a href="<?= esc_url(admin_url('post-new.php?post_type=evenement')) ?>" class="evts__lien">
            + <?php _e('Ajouter un événement', 'poivre-sens'); ?>
        </a>
    </div>
    <?php endif; ?>

</main>

<?php get_footer();
