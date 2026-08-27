<?php
/**
 * template-parts/calendar-grid.php
 * Calendrier mode GRILLE — un mois, un événement par jour
 *
 * Params attendus (via set_query_var) :
 *   ps_cal_year  : int (défaut : année courante)
 *   ps_cal_month : int (défaut : mois courant)
 *   ps_cal_type  : string (identifiant de type, vide = tous)
 *   ps_cal_ville : string (nom de ville, vide = toutes)
 *   ps_cal_base  : string (URL de l'agenda, pour les liens de navigation)
 */
defined('ABSPATH') || exit;

$year  = (int)(get_query_var('ps_cal_year')  ?: date('Y'));
$month = (int)(get_query_var('ps_cal_month') ?: date('n'));
$filtre_type  = (string) get_query_var('ps_cal_type');
$filtre_ville = (string) get_query_var('ps_cal_ville');
$base = (string) get_query_var('ps_cal_base');
if ($base === '') $base = get_post_type_archive_link(ps_evt_cpt());

$debut_mois = sprintf('%04d-%02d-01', $year, $month);
$ts_mois    = strtotime($debut_mois);
$nb_jours   = (int) date('t', $ts_mois);
$premier_jour_semaine = (int) date('N', $ts_mois); // 1=lun … 7=dim

$ts_mois_prec = strtotime('-1 month', $ts_mois);
$ts_mois_suiv = strtotime('+1 month', $ts_mois);

// Événements du mois
$cle  = ps_evt_cle_date();
$args = [
    'post_type'      => ps_evt_cpt(),
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => $cle,
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'meta_query'     => [[
        'key'     => $cle,
        'value'   => [ps_evt_borne_debut($debut_mois), ps_evt_borne_fin(date('Y-m-t', $ts_mois))],
        'compare' => 'BETWEEN',
        'type'    => 'CHAR',
    ]],
];
$args = ps_evt_filtrer_type($args, $filtre_type);
if ($filtre_ville !== '') {
    $args['meta_query'][] = ['key' => ps_evt_cle_ville(), 'value' => $filtre_ville, 'compare' => '='];
}

$query = new WP_Query($args);

// Regrouper par jour du mois
$par_jour = [];
if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $id   = get_the_ID();
        $date = ps_evt_champ($id, 'date');
        if (!$date) continue;
        $jour = (int) substr($date, 8, 2);
        $par_jour[$jour][] = [
            'titre'        => get_the_title(),
            'lien'         => get_permalink(),
            'heure'        => ps_evt_champ($id, 'heure'),
            'complet'      => ps_evt_champ($id, 'complet'),
            'statut_event' => ps_evt_champ($id, 'statut_event') ?: 'publie',
        ];
    }
    wp_reset_postdata();
}

$jours_sem  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$aujourdhui = date('Y-m-d', current_time('timestamp'));
?>
<div class="cal-grid">
  <div class="cal-grid__nav">
    <a href="<?= esc_url(ps_evt_url_calendrier($base, (int) date('Y', $ts_mois_prec), (int) date('n', $ts_mois_prec), $filtre_type, $filtre_ville)) ?>" class="cal-grid__nav-lien">
      ← <?= esc_html(date_i18n('F Y', $ts_mois_prec)) ?>
    </a>
    <strong class="cal-grid__titre"><?= esc_html(date_i18n('F Y', $ts_mois)) ?></strong>
    <a href="<?= esc_url(ps_evt_url_calendrier($base, (int) date('Y', $ts_mois_suiv), (int) date('n', $ts_mois_suiv), $filtre_type, $filtre_ville)) ?>" class="cal-grid__nav-lien">
      <?= esc_html(date_i18n('F Y', $ts_mois_suiv)) ?> →
    </a>
  </div>

  <div class="cal-grid__grille">
    <?php foreach ($jours_sem as $j): ?>
    <div class="cal-grid__entete"><?= esc_html($j) ?></div>
    <?php endforeach; ?>

    <?php for ($i = 1; $i < $premier_jour_semaine; $i++): ?>
    <div class="cal-grid__case cal-grid__case--vide" aria-hidden="true"></div>
    <?php endfor; ?>

    <?php for ($jour = 1; $jour <= $nb_jours; $jour++):
        $ymd     = sprintf('%04d-%02d-%02d', $year, $month, $jour);
        $evts    = $par_jour[$jour] ?? [];
        $classes = 'cal-grid__case';
        if ($evts)              $classes .= ' cal-grid__case--evt';
        if ($ymd === $aujourdhui) $classes .= ' cal-grid__case--aujourdhui';
        if ($ymd < $aujourdhui)   $classes .= ' cal-grid__case--passe';
    ?>
    <div class="<?= esc_attr($classes) ?>">
      <span class="cal-grid__jour"><?= (int) $jour ?></span>
      <?php if ($evts): ?>
      <div class="cal-grid__evts">
        <?php foreach ($evts as $e): ?>
        <a href="<?= esc_url($e['lien']) ?>" class="cal-grid__evt<?= ($e['complet'] || $e['statut_event'] !== 'publie') ? ' cal-grid__evt--complet' : '' ?>">
          <?php if ($e['heure']): ?><span class="cal-grid__heure"><?= esc_html($e['heure']) ?></span><?php endif; ?>
          <span class="cal-grid__evt-titre"><?= esc_html($e['titre']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endfor; ?>

    <?php
    $dernier_jour_sem = (int) date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $nb_jours)));
    for ($i = $dernier_jour_sem; $i < 7; $i++):
    ?>
    <div class="cal-grid__case cal-grid__case--vide" aria-hidden="true"></div>
    <?php endfor; ?>
  </div>

  <?php if (0 === $query->post_count): ?>
  <p class="cal-grid__vide"><?php _e('Aucun événement ce mois-ci.', 'poivre-sens'); ?></p>
  <?php endif; ?>
</div>
