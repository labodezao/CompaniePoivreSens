<?php
/**
 * template-parts/calendar-list.php
 * Calendrier mode LISTE — événements groupés par mois
 *
 * Params attendus (via set_query_var) :
 *   ps_cal_year  : int (défaut : année courante)
 *   ps_cal_month : int (défaut : mois courant)
 *   ps_cal_all   : bool (true = tous, false = à partir d'aujourd'hui)
 *   ps_cal_type  : string (identifiant de type, vide = tous)
 *   ps_cal_ville : string (nom de ville, vide = toutes)
 */
defined('ABSPATH') || exit;

$year    = (int)(get_query_var('ps_cal_year')  ?: date('Y'));
$month   = (int)(get_query_var('ps_cal_month') ?: date('n'));
$all     = (bool)get_query_var('ps_cal_all');

// Limiter sur 6 mois à venir depuis aujourd'hui
$today    = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+6 months'));

$cle = ps_evt_cle_date();

$args = [
    'post_type'      => ps_evt_cpt(),
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => $cle,
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    // Comparaison de chaînes : « 2026-09-12 » comme
    // « 2026-09-12T20:30 » se trient et se bornent correctement.
    'meta_query'     => [[
        'key'     => $cle,
        'value'   => ps_evt_borne_debut($all ? '1970-01-01' : $today),
        'compare' => '>=',
        'type'    => 'CHAR',
    ]],
];
if (!$all) {
    $args['meta_query'][] = [
        'key'     => $cle,
        'value'   => ps_evt_borne_fin($end_date),
        'compare' => '<=',
        'type'    => 'CHAR',
    ];
}

// Filtres de l'agenda (transmis par archive-evenement.php)
$args = ps_evt_filtrer_type($args, (string) get_query_var('ps_cal_type'));

$filtre_ville = (string) get_query_var('ps_cal_ville');
if ($filtre_ville !== '') {
    $args['meta_query'][] = ['key' => ps_evt_cle_ville(), 'value' => $filtre_ville, 'compare' => '='];
}

$query = new WP_Query($args);

// Grouper par mois
$grouped = [];
if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $id       = get_the_ID();
        $evt_date = ps_evt_champ($id, 'date');
        if (!$evt_date) continue;
        $key = date('Y-m', strtotime($evt_date)); // ex: "2026-04"
        $grouped[$key][] = [
            'id'          => $id,
            'title'       => get_the_title(),
            'permalink'   => get_permalink(),
            'date'        => $evt_date,
            'heure'       => ps_evt_champ($id, 'heure'),
            'heure_fin'   => ps_evt_champ($id, 'heure_fin'),
            'lieu'        => ps_evt_champ($id, 'lieu'),
            'ville'       => ps_evt_champ($id, 'ville'),
            'type'        => ps_evt_champ($id, 'type_label'),
            'type_slug'   => ps_evt_champ($id, 'type'),
            'prix'        => ps_evt_champ($id, 'prix'),
            'billetterie' => ps_evt_champ($id, 'billetterie'),
            'complet'     => ps_evt_champ($id, 'complet'),
            'statut_event' => ps_evt_champ($id, 'statut_event') ?: 'publie',
            'thumb'       => get_the_post_thumbnail_url($id, 'evt-card'),
        ];
    }
    wp_reset_postdata();
}

// Mois français
$mois_fr = [
    '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
    '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
    '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre',
];
// Jours français courts
$jours_fr = ['Sun'=>'Dim','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam'];
?>

<div class="cal-list" role="region" aria-label="<?= esc_attr(__('Calendrier des événements', 'poivre-sens')) ?>">

    <?php if (empty($grouped)): ?>
    <div class="cal-list__empty">
        <p><?= __('Aucun événement programmé pour le moment.', 'poivre-sens') ?></p>
    </div>
    <?php else: ?>

    <?php foreach ($grouped as $month_key => $events):
        [$yr, $mn] = explode('-', $month_key);
        $month_label = ($mois_fr[$mn] ?? $mn) . ' ' . $yr;
        $is_current  = ($month_key === date('Y-m'));
    ?>
    <div class="cal-list__month <?= $is_current ? 'cal-list__month--current' : '' ?>">

        <div class="cal-list__month-hdr">
            <span class="cal-list__month-name"><?= esc_html($month_label) ?></span>
            <span class="cal-list__month-count"><?= count($events) ?> <?= _n('événement', 'événements', count($events), 'poivre-sens') ?></span>
        </div>

        <ul class="cal-list__events" role="list">
        <?php foreach ($events as $e):
            $ts          = strtotime($e['date']);
            $jour_num    = date('j',   $ts);
            $jour_lettre = $jours_fr[date('D', $ts)] ?? date('D', $ts);
            $is_today    = ($e['date'] === $today);
            $is_past     = ($e['date'] < $today);
        ?>
        <li class="cal-list__event <?= $is_today ? 'cal-list__event--today' : '' ?> <?= $is_past ? 'cal-list__event--past' : '' ?>">

            <!-- Colonne date -->
            <div class="cal-list__date" aria-label="<?= esc_attr("$jour_lettre $jour_num") ?>">
                <span class="cal-list__day-ltr"><?= esc_html($jour_lettre) ?></span>
                <span class="cal-list__day-num"><?= esc_html($jour_num) ?></span>
            </div>

            <!-- Barre verticale -->
            <div class="cal-list__line" aria-hidden="true"></div>

            <!-- Contenu -->
            <div class="cal-list__body">

                <?php if ($e['type']): ?>
                <span class="cal-list__type cal-list__type--<?= esc_attr(sanitize_html_class($e['type_slug'] ?: 'autre')) ?>"><?= esc_html($e['type']) ?></span>
                <?php endif; ?>

                <?php if ($e['statut_event'] === 'annule'): ?>
                <span class="cal-list__complet"><?= __('Annulé', 'poivre-sens') ?></span>
                <?php elseif ($e['statut_event'] === 'reporte'): ?>
                <span class="cal-list__complet"><?= __('Reporté', 'poivre-sens') ?></span>
                <?php elseif ($e['complet']): ?>
                <span class="cal-list__complet"><?= __('Complet', 'poivre-sens') ?></span>
                <?php endif; ?>

                <h3 class="cal-list__title">
                    <a href="<?= esc_url($e['permalink']) ?>"><?= esc_html($e['title']) ?></a>
                </h3>

                <ul class="cal-list__meta" role="list">
                    <?php if ($e['heure']): ?>
                    <li class="cal-list__meta-item">
                        <span class="cal-list__meta-ic" aria-hidden="true">🕐</span>
                        <?php
                        $h = esc_html($e['heure']);
                        if ($e['heure_fin']) $h .= ' – ' . esc_html($e['heure_fin']);
                        echo $h;
                        ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($e['lieu'] || $e['ville']): ?>
                    <li class="cal-list__meta-item">
                        <span class="cal-list__meta-ic" aria-hidden="true">📍</span>
                        <?php
                        $loc = array_filter([$e['lieu'], $e['ville']]);
                        echo esc_html(implode(', ', $loc));
                        ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($e['prix']): ?>
                    <li class="cal-list__meta-item">
                        <span class="cal-list__meta-ic" aria-hidden="true">🎟</span>
                        <?= esc_html($e['prix']) ?>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="cal-list__actions">
                    <a href="<?= esc_url($e['permalink']) ?>" class="cal-list__action-link">
                        <?= __('En savoir plus', 'poivre-sens') ?> →
                    </a>
                    <?php if ($e['billetterie'] && !$e['complet'] && $e['statut_event'] === 'publie'): ?>
                    <a href="<?= esc_url($e['billetterie']) ?>" class="cal-list__action-btn" target="_blank" rel="noopener">
                        <?= __('Réserver', 'poivre-sens') ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($e['thumb']): ?>
            <div class="cal-list__thumb">
                <a href="<?= esc_url($e['permalink']) ?>">
                    <img src="<?= esc_url($e['thumb']) ?>" alt="<?= esc_attr($e['title']) ?>" loading="lazy">
                </a>
            </div>
            <?php endif; ?>

        </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>
