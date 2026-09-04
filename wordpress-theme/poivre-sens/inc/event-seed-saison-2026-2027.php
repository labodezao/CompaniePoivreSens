<?php
/**
 * inc/event-seed-saison-2026-2027.php
 *
 * Semis en un clic des trois séries d'ateliers de la saison 2026-2027
 * (Corps Vivant, Sens & Mouvement, Labo Danse — Quai des Bals), avec
 * les dates et informations confirmées le 2026-09-04.
 *
 * Outil à usage unique : une fois la saison semée, ce fichier peut être
 * supprimé sans rien casser — il ne fait que réutiliser
 * ps_evt_bulk_creer_un() (inc/event-bulk-create.php), déjà testée, avec
 * des valeurs figées ci-dessous plutôt que saisies dans un formulaire.
 * Un événement du même titre déjà présent à une date donnée est ignoré
 * (voir ps_evt_bulk_existe_deja()) : relancer l'opération ne double rien.
 */
defined('ABSPATH') || exit;

function ps_evt_seed_saison_2026_2027(): array {
    $series = [
        [
            'commun' => [
                'titre' => 'Vendredi Corps Vivant', 'type' => 'atelier',
                'heure' => '19:00', 'heure_fin' => '20:30',
                'lieu' => 'Salle Gambetta gauche', 'adresse' => '62 rue Gardurand', 'ville' => 'Saint-Nazaire',
                'prix' => "230 € l'année - 15 € à l'unité - Cours d'essai gratuit",
            ],
            'dates' => [
                '2026-09-11', '2026-09-25', '2026-10-16', '2026-11-06', '2026-11-20',
                '2026-12-04', '2026-12-18', '2027-01-15', '2027-01-29', '2027-02-12',
                '2027-03-12', '2027-03-26', '2027-04-09', '2027-05-07', '2027-05-21',
                '2027-06-04', '2027-06-18',
            ],
        ],
        [
            'commun' => [
                'titre' => 'Sens & Mouvement', 'type' => 'atelier',
                'heure' => '10:00', 'heure_fin' => '12:30',
                'lieu' => 'Salle Hibiscus', 'adresse' => 'rue des Hibiscus', 'ville' => 'Saint-Nazaire',
                'prix' => '30 € (35 € tarif solidaire)',
            ],
            'dates' => [
                '2026-10-17', '2026-11-07', '2026-12-19', '2027-01-10', '2027-02-07',
                '2027-03-07', '2027-04-04', '2027-05-08', '2027-06-06',
            ],
        ],
        [
            'commun' => [
                'titre' => 'Labo Danse — Quai des Bals', 'type' => 'atelier',
                'heure' => '19:00', 'heure_fin' => '20:30',
                'lieu' => 'Salle Gambetta gauche', 'adresse' => '62 rue Gardurand', 'ville' => 'Saint-Nazaire',
                'prix' => '15 €',
            ],
            'dates' => [
                '2026-10-30', '2026-11-27', '2026-12-11', '2027-01-22', '2027-02-19',
                '2027-03-19', '2027-04-02', '2027-05-28', '2027-06-11',
            ],
        ],
    ];

    $bilan = [];
    foreach ($series as $serie) {
        $titre = $serie['commun']['titre'];
        $crees = 0;
        $deja_la = 0;
        foreach ($serie['dates'] as $date_iso) {
            if (ps_evt_bulk_existe_deja($titre, $date_iso)) {
                $deja_la++;
                continue;
            }
            if (ps_evt_bulk_creer_un($serie['commun'], $date_iso)) {
                $crees++;
            }
        }
        $bilan[$titre] = ['crees' => $crees, 'deja_la' => $deja_la, 'total' => count($serie['dates'])];
    }
    return $bilan;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        __('Semer la saison 2026-2027', 'poivre-sens'),
        __('Semer saison 2026-2027', 'poivre-sens'),
        'publish_posts',
        'ps-evt-seed-saison',
        'ps_evt_page_seed_saison'
    );
});

function ps_evt_page_seed_saison() {
    if (!current_user_can('publish_posts')) return;

    $bilan = null;
    if (isset($_POST['ps_evt_seed']) && check_admin_referer('ps_evt_seed_saison')) {
        $bilan = ps_evt_seed_saison_2026_2027();
    }
    ?>
    <div class="wrap">
      <h1><?= esc_html__('Semer la saison 2026-2027', 'poivre-sens') ?></h1>
      <p style="max-width:46em">
        <?= esc_html__('Crée en un clic les trois séries d\'ateliers confirmées le 4 septembre 2026 : Vendredi Corps Vivant (17 dates), Sens & Mouvement (9 dates) et Labo Danse — Quai des Bals (9 dates), avec les horaires, lieux et tarifs déjà validés.', 'poivre-sens') ?>
      </p>
      <p style="max-width:46em">
        <strong><?= esc_html__('Sans risque à relancer', 'poivre-sens') ?></strong> —
        <?= esc_html__('un événement déjà créé à une date donnée est détecté et ignoré, jamais dupliqué.', 'poivre-sens') ?>
      </p>

      <?php if ($bilan !== null): ?>
      <div class="notice notice-success">
        <p><strong><?= esc_html__('Fait.', 'poivre-sens') ?></strong></p>
        <ul style="list-style:disc;margin-left:1.5em">
          <?php foreach ($bilan as $titre => $c): ?>
          <li>
            <?= esc_html($titre) ?> —
            <?= esc_html(sprintf(
                /* translators: 1: created, 2: total, 3: already existing */
                __('%1$d événement(s) créé(s) sur %2$d (%3$d déjà présents, ignorés).', 'poivre-sens'),
                $c['crees'], $c['total'], $c['deja_la']
            )) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('ps_evt_seed_saison'); ?>
        <button type="submit" name="ps_evt_seed" class="button button-primary">
          <?= esc_html__('Semer les 35 événements de la saison', 'poivre-sens') ?>
        </button>
      </form>
    </div>
    <?php
}
