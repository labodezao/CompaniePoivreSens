<?php
/**
 * inc/event-migration.php
 *
 * Bascule du module d'événements du thème (CPT « evenement », champs
 * _evt_*) vers le plugin CF Événements & Réservations (CPT « cf_event »,
 * champs _cfeb_*).
 *
 * La migration est volontairement **non destructive** : chaque événement
 * d'origine est conservé et simplement marqué comme migré. Rien n'est
 * supprimé — vous pourrez faire le ménage vous-même une fois le résultat
 * vérifié. Relancer la migration ne crée pas de doublons.
 */
defined('ABSPATH') || exit;

/** Le plugin est-il actif ? (voir inc/event-data.php) */
function ps_cfeb_actif() {
    return ps_evt_plugin_actif();
}

/**
 * Traduit les champs du thème vers ceux du plugin.
 *
 * Fonction pure (aucun accès base de données) afin d'être vérifiable :
 * $legacy est un tableau de clés _evt_*, le retour un tableau de clés
 * _cfeb_* prêtes à être enregistrées.
 */
function ps_evt_map_to_cfeb(array $legacy) {
    $date    = trim((string)($legacy['_evt_date'] ?? ''));
    $heure   = trim((string)($legacy['_evt_heure'] ?? ''));
    $fin     = trim((string)($legacy['_evt_heure_fin'] ?? ''));
    $prix    = trim((string)($legacy['_evt_prix'] ?? ''));
    $complet = (string)($legacy['_evt_complet'] ?? '') === '1';

    $out = [];

    // Le plugin attend le format des champs datetime-local : 2026-09-12T20:30
    if ($date !== '') {
        $out['_cfeb_date_debut'] = $date . 'T' . ($heure !== '' ? $heure : '00:00');

        if ($fin !== '') {
            // Une fin antérieure au début signifie un passage après minuit.
            $jour_fin = ($heure !== '' && $fin < $heure)
                ? date('Y-m-d', strtotime($date . ' +1 day'))
                : $date;
            $out['_cfeb_date_fin'] = $jour_fin . 'T' . $fin;
        }
    }

    $out['_cfeb_lieu']  = (string)($legacy['_evt_lieu'] ?? '');
    $out['_cfeb_ville'] = (string)($legacy['_evt_ville'] ?? '');

    if (!empty($legacy['_evt_adresse'])) {
        $out['_cfeb_infos_pratiques'] = (string) $legacy['_evt_adresse'];
    }

    // Le plugin raisonne en montant numérique ; le thème affiche un texte
    // libre (« prix libre », « sur réservation »…). On conserve les deux
    // pour ne rien perdre.
    $montant = function_exists('ps_seo_event_price') ? ps_seo_event_price($prix) : null;
    $out['_cfeb_prix']      = $montant !== null ? (float) $montant : 0.0;
    $out['_ps_prix_texte']  = $prix;

    if (!empty($legacy['_evt_billetterie'])) {
        $out['_cfeb_event_url'] = (string) $legacy['_evt_billetterie'];
    }

    $out['_cfeb_statut'] = $complet ? 'complet' : 'ouvert';

    return $out;
}

/**
 * Migre un événement du thème vers le plugin.
 * Retourne l'ID créé, ou 0 si déjà migré / migration impossible.
 */
function ps_evt_migrer_un($post_id) {
    if (!ps_cfeb_actif()) return 0;

    $deja = (int) get_post_meta($post_id, '_ps_migre_vers', true);
    if ($deja && get_post($deja)) return 0; // idempotent

    $source = get_post($post_id);
    if (!$source) return 0;

    $nouveau = wp_insert_post([
        'post_type'    => CFEB_SLUG,
        'post_title'   => $source->post_title,
        'post_content' => $source->post_content,
        'post_excerpt' => $source->post_excerpt,
        'post_status'  => $source->post_status,
        'post_date'    => $source->post_date,
        'post_name'    => $source->post_name,
    ], true);

    if (is_wp_error($nouveau) || !$nouveau) return 0;

    // Champs
    $legacy = [];
    foreach (['_evt_date','_evt_heure','_evt_heure_fin','_evt_lieu','_evt_adresse',
              '_evt_ville','_evt_type','_evt_prix','_evt_billetterie','_evt_complet'] as $k) {
        $legacy[$k] = get_post_meta($post_id, $k, true);
    }
    foreach (ps_evt_map_to_cfeb($legacy) as $cle => $valeur) {
        update_post_meta($nouveau, $cle, $valeur);
    }

    // Type d'événement → catégorie du plugin
    $type = (string) $legacy['_evt_type'];
    if ($type !== '' && defined('CFEB_TAX') && taxonomy_exists(CFEB_TAX)) {
        $libelles = function_exists('ps_evt_types') ? ps_evt_types() : [];
        $nom      = $libelles[$type] ?? ucfirst($type);
        $terme    = term_exists($type, CFEB_TAX) ?: wp_insert_term($nom, CFEB_TAX, ['slug' => $type]);
        if (!is_wp_error($terme)) {
            wp_set_object_terms($nouveau, (int) $terme['term_id'], CFEB_TAX);
        }
    }

    // Image à la une
    $vignette = get_post_thumbnail_id($post_id);
    if ($vignette) set_post_thumbnail($nouveau, $vignette);

    // Traçabilité dans les deux sens
    update_post_meta($post_id, '_ps_migre_vers',   $nouveau);
    update_post_meta($nouveau, '_ps_migre_depuis', $post_id);

    return $nouveau;
}

/** Événements du thème restant à migrer. */
function ps_evt_a_migrer() {
    return get_posts([
        'post_type'      => 'evenement',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => '_ps_migre_vers', 'compare' => 'NOT EXISTS'],
            ['key' => '_ps_migre_vers', 'value' => '0'],
        ],
    ]);
}

/* ── Modules optionnels du plugin ──────────────────────────
   Le plugin embarque des modules conçus pour un autre site
   (suivi de séances, programme, fiche d'accueil) et une
   newsletter complète qui ferait doublon avec celle du thème.
   On les laisse désactivés par défaut ici, tout en permettant
   de les rallumer.
   ─────────────────────────────────────────────────────────── */
function ps_cfeb_modules() {
    return [
        'newsletter'   => [
            __('Newsletter du plugin', 'poivre-sens'),
            __('Doublon : le thème gère déjà les inscriptions, les listes et les campagnes.', 'poivre-sens'),
        ],
        'post-seance'  => [
            __('Post-séance', 'poivre-sens'),
            __('Séquence d\'emails après une séance individuelle.', 'poivre-sens'),
        ],
        'pleine-vie'   => [
            __('Programme Pleine Vie', 'poivre-sens'),
            __('Inscriptions et emails de suivi d\'un programme.', 'poivre-sens'),
        ],
        'fiche-intake' => [
            __('Fiche d\'accueil', 'poivre-sens'),
            __('Formulaire de préparation rempli en ligne.', 'poivre-sens'),
        ],
    ];
}

/** À l'activation du thème, on part d'un plugin réduit aux événements. */
add_action('after_switch_theme', function () {
    add_option('cfeb_modules_off', array_keys(ps_cfeb_modules()));
});

/* ── Page d'outil dans l'administration ───────────────────── */
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        __('Migration des événements', 'poivre-sens'),
        __('Migration événements', 'poivre-sens'),
        'manage_options',
        'ps-evt-migration',
        'ps_evt_page_migration'
    );
});

function ps_evt_page_migration() {
    $notice = '';

    if (isset($_POST['ps_migrer']) && check_admin_referer('ps_evt_migration')) {
        $faits = 0;
        foreach (ps_evt_a_migrer() as $id) {
            if (ps_evt_migrer_un($id)) $faits++;
        }
        $notice = $faits
            ? '<div class="notice notice-success"><p>' . sprintf(esc_html__('%d événement(s) migré(s).', 'poivre-sens'), $faits) . '</p></div>'
            : '<div class="notice notice-info"><p>' . esc_html__('Rien à migrer.', 'poivre-sens') . '</p></div>';
    }

    if (isset($_POST['ps_modules']) && check_admin_referer('ps_evt_modules')) {
        $garder = array_map('sanitize_text_field', (array) ($_POST['ps_module'] ?? []));
        $off    = array_values(array_diff(array_keys(ps_cfeb_modules()), $garder));
        update_option('cfeb_modules_off', $off);
        $notice .= '<div class="notice notice-success"><p>'
                 . esc_html__('Modules enregistrés. Le changement prend effet au prochain chargement de page.', 'poivre-sens')
                 . '</p></div>';
    }

    $restants = ps_evt_a_migrer();
    $actif    = ps_cfeb_actif();
    $off      = (array) get_option('cfeb_modules_off', []);
    ?>
    <div class="wrap">
      <h1><?= esc_html__('Migration des événements vers le plugin CF', 'poivre-sens') ?></h1>
      <?= $notice ?>

      <?php if (!$actif): ?>
      <div class="notice notice-error"><p>
        <?= esc_html__('Le plugin « CF Événements & Réservations » n\'est pas actif. Activez-le dans Extensions avant de migrer.', 'poivre-sens') ?>
      </p></div>
      <?php endif; ?>

      <p style="max-width:46em">
        <?= esc_html__('Cette opération recopie chaque événement du thème vers le plugin : titre, contenu, image, date et heures, lieu, ville, tarif, billetterie et état « complet ». Le type devient une catégorie du plugin.', 'poivre-sens') ?>
      </p>
      <p style="max-width:46em">
        <strong><?= esc_html__('Rien n\'est supprimé', 'poivre-sens') ?></strong> —
        <?= esc_html__('les événements d\'origine sont conservés et marqués comme migrés. Relancer l\'opération ne crée pas de doublons.', 'poivre-sens') ?>
      </p>

      <p><?= sprintf(esc_html__('Événements restant à migrer : %d', 'poivre-sens'), count($restants)) ?></p>

      <form method="post">
        <?php wp_nonce_field('ps_evt_migration'); ?>
        <button type="submit" name="ps_migrer" class="button button-primary" <?= (!$actif || !$restants) ? 'disabled' : '' ?>>
          <?= esc_html__('Lancer la migration', 'poivre-sens') ?>
        </button>
      </form>

      <h2 style="margin-top:40px"><?= esc_html__('Modules du plugin', 'poivre-sens') ?></h2>
      <p style="max-width:46em">
        <?= esc_html__('Le plugin apporte d\'autres fonctions, conçues pour un autre site. Elles sont éteintes ici pour ne pas encombrer l\'administration ni faire doublon.', 'poivre-sens') ?>
      </p>

      <form method="post">
        <?php wp_nonce_field('ps_evt_modules'); ?>
        <table class="form-table" role="presentation"><tbody>
        <?php foreach (ps_cfeb_modules() as $id => $module): ?>
          <tr>
            <th scope="row"><?= esc_html($module[0]) ?></th>
            <td>
              <label>
                <input type="checkbox" name="ps_module[]" value="<?= esc_attr($id) ?>"
                       <?= checked(!in_array($id, $off, true), true, false) ?>>
                <?= esc_html__('Activer', 'poivre-sens') ?>
              </label>
              <p class="description"><?= esc_html($module[1]) ?></p>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
        <button type="submit" name="ps_modules" class="button">
          <?= esc_html__('Enregistrer les modules', 'poivre-sens') ?>
        </button>
      </form>
    </div>
    <?php
}
