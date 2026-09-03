<?php
/**
 * inc/event-bulk-create.php
 *
 * Créer une série d'événements identiques (même titre, même horaire,
 * même lieu…) qui ne diffèrent que par leur date — un atelier
 * hebdomadaire ou mensuel reconduit sur toute une saison, par exemple.
 * Sans cet outil, chaque occurrence demande de remplir la métaboîte
 * « Détails de l'événement » (event-meta-box.php) à la main.
 *
 * Chaque événement est créé en repassant exactement par le formulaire
 * que cette métaboîte sait déjà lire (ps_evt_lire_formulaire()) et par
 * le hook save_post qu'elle enregistre : cet outil ne réécrit pas la
 * traduction vers les clés du plugin, il simule simplement une
 * soumission de ce formulaire pour chaque date.
 */
defined('ABSPATH') || exit;

/**
 * Découpe la zone de texte « une date par ligne » en dates ISO
 * (AAAA-MM-JJ), avec les lignes qui n'ont pas pu être comprises.
 *
 * Formats acceptés : JJ/MM/AAAA et AAAA-MM-JJ. Les lignes vides sont
 * ignorées silencieusement ; toute autre ligne mal formée est signalée
 * plutôt que devinée.
 */
function ps_evt_bulk_parser_dates(string $texte): array {
    $iso = [];
    $invalides = [];

    foreach (preg_split('/\R/', $texte) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '') continue;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ligne, $m)) {
            [$tout, $a, $mo, $j] = $m;
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $ligne, $m)) {
            [$tout, $j, $mo, $a] = $m;
        } else {
            $invalides[] = $ligne;
            continue;
        }

        if (checkdate((int) $mo, (int) $j, (int) $a)) {
            $iso[] = sprintf('%04d-%02d-%02d', (int) $a, (int) $mo, (int) $j);
        } else {
            $invalides[] = $ligne;
        }
    }

    return [$iso, $invalides];
}

/**
 * Un événement du même titre existe-t-il déjà à cette date ? Évite les
 * doublons si l'outil est relancé (double clic, page rechargée…).
 */
function ps_evt_bulk_existe_deja(string $titre, string $date_iso): bool {
    $existants = get_posts([
        'post_type'      => ps_evt_cpt(),
        'post_status'    => 'any',
        'title'          => $titre,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [['key' => ps_evt_cle_date(), 'value' => $date_iso, 'compare' => 'STARTS WITH']],
    ]);
    return !empty($existants);
}

/**
 * Crée une occurrence. Réutilise le hook save_post déjà enregistré par
 * event-meta-box.php en lui simulant sa propre soumission de
 * formulaire — la même conversion vers les clés du plugin (ou de
 * l'ancien module) qu'une saisie manuelle, sans code séparé à
 * maintenir en double.
 */
function ps_evt_bulk_creer_un(array $commun, string $date_iso): int {
    // $_POST doit porter les valeurs de CETTE occurrence avant l'appel :
    // wp_insert_post() déclenche lui-même save_post, une seule fois, à la
    // fin de son propre traitement — c'est cette unique déclenche
    // naturelle que lit le hook de event-meta-box.php, exactement comme
    // pour une création manuelle depuis l'écran d'édition. Provoquer un
    // second déclenchement à la main redéclencherait aussi les hooks
    // save_post d'autres extensions (Yoast, etc.) en double.
    $post_sauvegarde = $_POST;
    $_POST = [
        'ps_evt_nonce'     => wp_create_nonce('ps_evt_save'),
        'evt_date'         => $date_iso,
        'evt_heure'        => $commun['heure']       ?? '',
        'evt_heure_fin'    => $commun['heure_fin']   ?? '',
        'evt_lieu'         => $commun['lieu']        ?? '',
        'evt_adresse'      => $commun['adresse']     ?? '',
        'evt_ville'        => $commun['ville']       ?? '',
        'evt_type'         => $commun['type']        ?? '',
        'evt_prix'         => $commun['prix']        ?? '',
        'evt_billetterie'  => $commun['billetterie'] ?? '',
        'evt_max_places'   => $commun['max_places']  ?? '',
        'evt_animateur'    => $commun['animateur']   ?? '',
        'evt_statut'       => 'ouvert',
        'evt_statut_event' => 'publie',
    ];

    $post_id = wp_insert_post([
        'post_type'    => ps_evt_cpt(),
        'post_title'   => $commun['titre'],
        'post_content' => $commun['contenu'] ?? '',
        'post_status'  => 'publish',
    ], true);

    $_POST = $post_sauvegarde;

    return (is_wp_error($post_id) || !$post_id) ? 0 : $post_id;
}

/* ── Page d'outil dans l'administration ───────────────────── */
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        __('Créer des événements en série', 'poivre-sens'),
        __('Événements en série', 'poivre-sens'),
        'publish_posts',
        'ps-evt-bulk-create',
        'ps_evt_page_bulk_create'
    );
});

function ps_evt_page_bulk_create() {
    if (!current_user_can('publish_posts')) return;

    $notice = '';
    $valeurs = [
        'titre' => '', 'type' => '', 'heure' => '', 'heure_fin' => '',
        'lieu' => '', 'adresse' => '', 'ville' => '', 'prix' => '',
        'max_places' => '', 'animateur' => '', 'billetterie' => '',
        'contenu' => '', 'dates' => '',
    ];

    if (isset($_POST['ps_evt_bulk_creer']) && check_admin_referer('ps_evt_bulk_create')) {
        foreach ($valeurs as $champ => $defaut) {
            $valeurs[$champ] = isset($_POST['ps_evt_bulk_' . $champ])
                ? wp_unslash($_POST['ps_evt_bulk_' . $champ])
                : $defaut;
        }

        $titre = trim($valeurs['titre']);
        [$dates_iso, $invalides] = ps_evt_bulk_parser_dates($valeurs['dates']);

        if ($titre === '') {
            $notice = '<div class="notice notice-error"><p>' . esc_html__('Le titre est obligatoire.', 'poivre-sens') . '</p></div>';
        } elseif (!$dates_iso) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__('Aucune date valide n\'a été reconnue.', 'poivre-sens') . '</p></div>';
        } else {
            $crees = 0;
            $deja_la = 0;
            foreach ($dates_iso as $date_iso) {
                if (ps_evt_bulk_existe_deja($titre, $date_iso)) {
                    $deja_la++;
                    continue;
                }
                if (ps_evt_bulk_creer_un(array_merge($valeurs, ['titre' => $titre]), $date_iso)) {
                    $crees++;
                }
            }

            $morceaux = [sprintf(_n('%d événement créé.', '%d événements créés.', $crees, 'poivre-sens'), $crees)];
            if ($deja_la) {
                $morceaux[] = sprintf(_n('%d existait déjà à cette date et a été ignoré.', '%d existaient déjà à cette date et ont été ignorés.', $deja_la, 'poivre-sens'), $deja_la);
            }
            if ($invalides) {
                $morceaux[] = sprintf(esc_html__('Lignes non reconnues, ignorées : %s', 'poivre-sens'), esc_html(implode(', ', $invalides)));
            }
            $notice = '<div class="notice notice-' . ($crees ? 'success' : 'warning') . '"><p>' . implode(' ', $morceaux) . '</p></div>';

            if ($crees) {
                // Le formulaire repart vide : la série est faite, pas besoin
                // de la resoumettre par erreur en rechargeant la page.
                foreach ($valeurs as $champ => $defaut) { $valeurs[$champ] = $defaut; }
            }
        }
    }
    ?>
    <div class="wrap">
      <h1><?= esc_html__('Créer des événements en série', 'poivre-sens') ?></h1>
      <?= $notice ?>
      <p style="max-width:46em">
        <?= esc_html__('Pour un atelier reconduit à plusieurs dates (même titre, même horaire, même lieu) : remplissez les champs communs une fois, listez les dates une par ligne, et chaque occurrence est créée comme un événement à part entière — modifiable ensuite individuellement.', 'poivre-sens') ?>
      </p>

      <form method="post">
        <?php wp_nonce_field('ps_evt_bulk_create'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th><label for="ps_evt_bulk_titre"><?= esc_html__('Titre', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_titre" name="ps_evt_bulk_titre" class="regular-text" required value="<?= esc_attr($valeurs['titre']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_type"><?= esc_html__('Type', 'poivre-sens') ?></label></th>
            <td>
              <select id="ps_evt_bulk_type" name="ps_evt_bulk_type">
                <option value=""><?= esc_html__('—', 'poivre-sens') ?></option>
                <?php foreach (ps_evt_types() as $cle => $libelle): ?>
                <option value="<?= esc_attr($cle) ?>" <?= selected($valeurs['type'], $cle, false) ?>><?= esc_html($libelle) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_heure"><?= esc_html__('Horaire', 'poivre-sens') ?></label></th>
            <td>
              <input type="time" id="ps_evt_bulk_heure" name="ps_evt_bulk_heure" value="<?= esc_attr($valeurs['heure']) ?>">
              –
              <input type="time" name="ps_evt_bulk_heure_fin" value="<?= esc_attr($valeurs['heure_fin']) ?>">
            </td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_lieu"><?= esc_html__('Lieu', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_lieu" name="ps_evt_bulk_lieu" class="regular-text" value="<?= esc_attr($valeurs['lieu']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_adresse"><?= esc_html__('Adresse', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_adresse" name="ps_evt_bulk_adresse" class="regular-text" value="<?= esc_attr($valeurs['adresse']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_ville"><?= esc_html__('Ville', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_ville" name="ps_evt_bulk_ville" class="regular-text" value="<?= esc_attr($valeurs['ville']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_prix"><?= esc_html__('Tarif', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_prix" name="ps_evt_bulk_prix" class="regular-text" placeholder="<?= esc_attr__('ex. 12 € ou Prix libre', 'poivre-sens') ?>" value="<?= esc_attr($valeurs['prix']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_max_places"><?= esc_html__('Places maximum', 'poivre-sens') ?></label></th>
            <td><input type="number" min="0" id="ps_evt_bulk_max_places" name="ps_evt_bulk_max_places" value="<?= esc_attr($valeurs['max_places']) ?>"> <span class="description"><?= esc_html__('vide ou 0 = illimité', 'poivre-sens') ?></span></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_animateur"><?= esc_html__('Animé par', 'poivre-sens') ?></label></th>
            <td><input type="text" id="ps_evt_bulk_animateur" name="ps_evt_bulk_animateur" class="regular-text" value="<?= esc_attr($valeurs['animateur']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_billetterie"><?= esc_html__('Billetterie externe', 'poivre-sens') ?></label></th>
            <td><input type="url" id="ps_evt_bulk_billetterie" name="ps_evt_bulk_billetterie" class="regular-text" placeholder="<?= esc_attr__('laisser vide pour la réservation en ligne du site', 'poivre-sens') ?>" value="<?= esc_attr($valeurs['billetterie']) ?>"></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_contenu"><?= esc_html__('Description', 'poivre-sens') ?></label></th>
            <td><textarea id="ps_evt_bulk_contenu" name="ps_evt_bulk_contenu" rows="4" class="large-text"><?= esc_textarea($valeurs['contenu']) ?></textarea></td>
          </tr>
          <tr>
            <th><label for="ps_evt_bulk_dates"><?= esc_html__('Dates', 'poivre-sens') ?></label></th>
            <td>
              <textarea id="ps_evt_bulk_dates" name="ps_evt_bulk_dates" rows="10" class="large-text" placeholder="11/09/2026&#10;25/09/2026&#10;16/10/2026" required><?= esc_textarea($valeurs['dates']) ?></textarea>
              <p class="description"><?= esc_html__('Une date par ligne, au format JJ/MM/AAAA (ou AAAA-MM-JJ).', 'poivre-sens') ?></p>
            </td>
          </tr>
        </table>
        <button type="submit" name="ps_evt_bulk_creer" class="button button-primary">
          <?= esc_html__('Créer ces événements', 'poivre-sens') ?>
        </button>
      </form>
    </div>
    <?php
}
