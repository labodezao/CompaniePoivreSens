<?php
/**
 * Poivre & Sens — Réglages › Textes du site
 *
 * Éditeur des textes de la page d'accueil (voir inc/textes.php), pour
 * les modifier depuis l'admin WordPress sans toucher au code.
 *
 * L'enregistrement écrit dans l'option `ps_textes_overrides`, que
 * ps_textes() fusionne par-dessus ps_textes_defaut(). Réinitialiser une
 * section revient à supprimer cette option : le site retrouve alors les
 * textes d'origine, qui restent lisibles (et modifiables par un
 * développeur) dans inc/textes.php.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_menu_page(
        __('Textes du site', 'poivre-sens'),
        __('Textes du site', 'poivre-sens'),
        'manage_options',
        'ps-textes',
        'ps_textes_admin_page',
        'dashicons-edit-page',
        59
    );
});

/* ══════════════════════════════════════════════════════════════
   1. LECTURE DU FORMULAIRE — POST → tableau au format de ps_textes()
   ══════════════════════════════════════════════════════════════ */

/** Champ texte court : une ligne. */
function ps_textes_champ(array $post, string $cle): string {
    return isset($post[$cle]) ? sanitize_text_field(wp_unslash($post[$cle])) : '';
}

/** Champ texte long, formatage inline autorisé (<em>, <br>, <strong>…). */
function ps_textes_champ_html(array $post, string $cle): string {
    return isset($post[$cle]) ? wp_kses_post(wp_unslash($post[$cle])) : '';
}

/** Textarea → liste de lignes non vides (une entrée par ligne). */
function ps_textes_lignes(array $post, string $cle): array {
    if (!isset($post[$cle])) {
        return [];
    }
    $lignes = preg_split('/\r\n|\r|\n/', wp_unslash($post[$cle]));
    $lignes = array_map(fn($l) => sanitize_text_field(trim($l)), $lignes);
    return array_values(array_filter($lignes, fn($l) => $l !== ''));
}

/** Textarea → liste de paragraphes (séparés par une ligne vide). */
function ps_textes_paragraphes(array $post, string $cle): array {
    if (!isset($post[$cle])) {
        return [];
    }
    $blocs = preg_split('/\n\s*\n/', trim(wp_unslash($post[$cle])));
    $blocs = array_map(fn($b) => wp_kses_post(trim($b)), $blocs);
    return array_values(array_filter($blocs, fn($b) => $b !== ''));
}

/** Champ texte court → liste, séparée par des virgules (ex. les tags d'une bio). */
function ps_textes_liste_virgules(array $post, string $cle): array {
    if (!isset($post[$cle])) {
        return [];
    }
    $items = explode(',', wp_unslash($post[$cle]));
    $items = array_map(fn($i) => sanitize_text_field(trim($i)), $items);
    return array_values(array_filter($items, fn($i) => $i !== ''));
}

/**
 * Lit une liste de lignes répétables (activités, axes, valeurs,
 * influences, diffusion…). $post[$cle] est un tableau indexé par les
 * identifiants de ligne générés côté navigateur (pas forcément 0,1,2…
 * contigus : le JS peut en retirer au milieu) ; $champs associe chaque
 * sous-champ du formulaire à son type de sanitisation.
 *
 * Une ligne entièrement vide (ajoutée puis jamais remplie) est ignorée.
 *
 * @param array<string,string> $champs cle => 'texte'|'html'
 */
function ps_textes_lire_lignes(array $post, string $cle, array $champs): array {
    $sortie = [];
    foreach ($post[$cle] ?? [] as $ligne) {
        if (!is_array($ligne)) {
            continue;
        }
        $item = [];
        $vide = true;
        foreach ($champs as $sous_cle => $type) {
            $v = $type === 'html'
                ? (isset($ligne[$sous_cle]) ? wp_kses_post(wp_unslash($ligne[$sous_cle])) : '')
                : (isset($ligne[$sous_cle]) ? sanitize_text_field(wp_unslash($ligne[$sous_cle])) : '');
            $item[$sous_cle] = $v;
            if (trim($v) !== '') {
                $vide = false;
            }
        }
        if (!$vide) {
            $sortie[] = $item;
        }
    }
    return $sortie;
}

/** Comme ps_textes_lire_lignes(), mais pour une liste de simples chaînes
 *  (ex. la diffusion, ou les items de la section « ce qui nous traverse »
 *  quand ils n'ont qu'un champ). */
function ps_textes_lire_lignes_simples(array $post, string $cle): array {
    $sortie = [];
    foreach ($post[$cle] ?? [] as $v) {
        $v = sanitize_text_field(trim(wp_unslash((string) $v)));
        if ($v !== '') {
            $sortie[] = $v;
        }
    }
    return $sortie;
}

/**
 * Renumérote une liste d'items possédant un champ 'num' (activités, axes)
 * — l'utilisateur ne le saisit pas : on l'attribue par position, pour
 * éviter les doublons ou les trous qu'une saisie manuelle produirait.
 */
function ps_textes_numeroter(array $items): array {
    foreach ($items as $i => &$item) {
        $item['num'] = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    }
    return $items;
}

/** Construit le tableau d'édition à partir de $_POST, au format de ps_textes(). */
function ps_textes_lire_formulaire(array $post): array {
    $t = [];

    $t['hero'] = [
        'surtitre'    => ps_textes_champ($post['hero'] ?? [], 'surtitre'),
        'disciplines' => ps_textes_lignes($post['hero'] ?? [], 'disciplines'),
        'cta'         => ps_textes_champ($post['hero'] ?? [], 'cta'),
        'citation'    => ps_textes_champ_html($post['hero'] ?? [], 'citation'),
        'intro'       => ps_textes_champ_html($post['hero'] ?? [], 'intro'),
    ];

    $t['manifeste'] = [
        'label'       => ps_textes_champ($post['manifeste'] ?? [], 'label'),
        'titre_html'  => ps_textes_champ_html($post['manifeste'] ?? [], 'titre_html'),
        'paragraphes' => ps_textes_paragraphes($post['manifeste'] ?? [], 'paragraphes'),
    ];

    $bios = [];
    foreach ($post['artistes']['bios'] ?? [] as $b) {
        $bios[] = [
            'initiale' => ps_textes_champ($b, 'initiale'),
            'nom'      => ps_textes_champ($b, 'nom'),
            'role'     => ps_textes_champ($b, 'role'),
            'textes'   => ps_textes_paragraphes($b, 'textes'),
            'tags'     => ps_textes_liste_virgules($b, 'tags'),
        ];
    }
    $t['artistes'] = [
        'label' => ps_textes_champ($post['artistes'] ?? [], 'label'),
        'titre' => ps_textes_champ($post['artistes'] ?? [], 'titre'),
        'bios'  => $bios,
    ];

    $influences = ps_textes_lire_lignes($post['influences'] ?? [], 'items', ['nom' => 'texte', 'description' => 'texte']);
    $t['influences'] = [
        'label' => ps_textes_champ($post['influences'] ?? [], 'label'),
        'items' => array_map(fn($i) => [$i['nom'], $i['description']], $influences),
    ];

    $axes = ps_textes_lire_lignes($post['projet'] ?? [], 'axes', ['titre' => 'texte', 'texte' => 'html']);
    $t['projet'] = [
        'label' => ps_textes_champ($post['projet'] ?? [], 'label'),
        'titre' => ps_textes_champ($post['projet'] ?? [], 'titre'),
        'axes'  => ps_textes_numeroter($axes),
    ];

    $items_act = ps_textes_lire_lignes($post['activites'] ?? [], 'items', ['titre' => 'texte', 'texte' => 'html', 'badge' => 'texte']);
    $t['activites'] = [
        'label'           => ps_textes_champ($post['activites'] ?? [], 'label'),
        'titre'           => ps_textes_champ($post['activites'] ?? [], 'titre'),
        'chapeau'         => ps_textes_champ_html($post['activites'] ?? [], 'chapeau'),
        'items'           => ps_textes_numeroter($items_act),
        'diffusion_label' => ps_textes_champ($post['activites'] ?? [], 'diffusion_label'),
        'diffusion'       => ps_textes_lire_lignes_simples($post['activites'] ?? [], 'diffusion'),
    ];

    $valeurs = ps_textes_lire_lignes($post['esthetique'] ?? [], 'valeurs', ['label' => 'texte', 'texte' => 'html']);
    $t['esthetique'] = [
        'label'           => ps_textes_champ($post['esthetique'] ?? [], 'label'),
        'titre'           => ps_textes_champ($post['esthetique'] ?? [], 'titre'),
        'valeurs'         => array_map(fn($v) => [$v['label'], $v['texte']], $valeurs),
        'citation_html'   => ps_textes_champ_html($post['esthetique'] ?? [], 'citation_html'),
        'citation_source' => ps_textes_champ($post['esthetique'] ?? [], 'citation_source'),
    ];

    $t['contact'] = [
        'label' => ps_textes_champ($post['contact'] ?? [], 'label'),
        'titre' => ps_textes_champ($post['contact'] ?? [], 'titre'),
        'note'  => ps_textes_champ_html($post['contact'] ?? [], 'note'),
    ];

    return $t;
}

/* ══════════════════════════════════════════════════════════════
   2. TRAITEMENT DE LA SOUMISSION
   ══════════════════════════════════════════════════════════════ */

function ps_textes_traiter_soumission(): ?string {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options')) {
        return null;
    }

    if (isset($_POST['ps_textes_reinitialiser'])) {
        check_admin_referer('ps_textes_reinitialiser');
        delete_option('ps_textes_overrides');
        return 'reinitialise';
    }

    if (!isset($_POST['ps_textes_enregistrer'])) {
        return null;
    }
    check_admin_referer('ps_textes_enregistrer', 'ps_textes_nonce');

    $edite = ps_textes_lire_formulaire(wp_unslash($_POST['t'] ?? []));
    update_option('ps_textes_overrides', $edite);
    return 'enregistre';
}

/* ══════════════════════════════════════════════════════════════
   3. AFFICHAGE
   ══════════════════════════════════════════════════════════════ */

function ps_textes_admin_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $resultat = ps_textes_traiter_soumission();
    $t = ps_textes(); // valeurs effectives : origine + éditions déjà enregistrées
    $personnalise = (bool) get_option('ps_textes_overrides');
    ?>
    <div class="wrap ps-textes-admin">
      <h1><?php _e('Textes du site', 'poivre-sens'); ?></h1>
      <p style="max-width:760px;color:#555">
        <?php _e("Les textes affichés sur la page d'accueil (via les patterns Gutenberg « Poivre & Sens »). Une modification ici s'applique à tout le site dès l'enregistrement — pas besoin de republier la page. Un champ laissé vide garde sa valeur précédente plutôt que de s'effacer.", 'poivre-sens'); ?>
      </p>

      <?php if ($resultat === 'enregistre'): ?>
      <div class="notice notice-success is-dismissible"><p><?php _e('Textes enregistrés.', 'poivre-sens'); ?></p></div>
      <?php elseif ($resultat === 'reinitialise'): ?>
      <div class="notice notice-success is-dismissible"><p><?php _e("Les textes d'origine ont été restaurés.", 'poivre-sens'); ?></p></div>
      <?php endif; ?>

      <?php if ($personnalise): ?>
      <p>
        <em><?php _e('Ces textes ont été personnalisés depuis cette page.', 'poivre-sens'); ?></em>
        &nbsp;
        <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__("Revenir aux textes d'origine sur toutes les sections ? Cette action n'est pas réversible depuis l'admin.", 'poivre-sens')); ?>');">
          <?php wp_nonce_field('ps_textes_reinitialiser'); ?>
          <button type="submit" name="ps_textes_reinitialiser" value="1" class="button-link" style="color:#a00">
            <?php _e("Réinitialiser tous les textes aux valeurs d'origine", 'poivre-sens'); ?>
          </button>
        </form>
      </p>
      <?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('ps_textes_enregistrer', 'ps_textes_nonce'); ?>

        <?php ps_textes_section_hero($t['hero']); ?>
        <?php ps_textes_section_manifeste($t['manifeste']); ?>
        <?php ps_textes_section_artistes($t['artistes']); ?>
        <?php ps_textes_section_influences($t['influences']); ?>
        <?php ps_textes_section_projet($t['projet']); ?>
        <?php ps_textes_section_activites($t['activites']); ?>
        <?php ps_textes_section_esthetique($t['esthetique']); ?>
        <?php ps_textes_section_contact($t['contact']); ?>

        <p class="submit">
          <button type="submit" name="ps_textes_enregistrer" value="1" class="button button-primary button-large">
            <?php _e('Enregistrer les textes', 'poivre-sens'); ?>
          </button>
        </p>
      </form>
    </div>

    <style>
      .ps-textes-admin .ps-section { background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:20px 24px; margin:24px 0; }
      .ps-textes-admin .ps-section h2 { margin-top:0; }
      .ps-textes-admin .ps-hint { color:#666; font-size:.9em; margin:-8px 0 16px; }
      .ps-textes-admin .ps-ligne { border:1px solid #e2e2e2; border-radius:4px; padding:14px 16px; margin-bottom:12px; position:relative; background:#fafafa; }
      .ps-textes-admin .ps-ligne .ps-retirer { position:absolute; top:10px; right:10px; }
      .ps-textes-admin .ps-champ { margin-bottom:10px; }
      .ps-textes-admin .ps-champ label { display:block; font-weight:600; margin-bottom:4px; }
      .ps-textes-admin .ps-champ input[type=text], .ps-textes-admin .ps-champ textarea { width:100%; max-width:720px; }
      .ps-textes-admin .ps-ajouter { margin-top:4px; }
    </style>

    <script>
    (function () {
      // Ajout / retrait de lignes répétables (activités, axes, valeurs,
      // influences, diffusion) sans rechargement de page. Chaque groupe
      // ".ps-repetable" porte un <template> et un conteneur de lignes ;
      // l'index inséré n'a pas besoin d'être contigu, PHP les relit dans
      // l'ordre où le navigateur les envoie.
      var compteur = 0;
      document.querySelectorAll('.ps-repetable').forEach(function (groupe) {
        var conteneur = groupe.querySelector('.ps-lignes');
        var modele    = groupe.querySelector('template');
        var bouton    = groupe.querySelector('.ps-ajouter');
        if (bouton) {
          bouton.addEventListener('click', function () {
            compteur++;
            var frag = modele.content.cloneNode(true);
            frag.querySelectorAll('[name]').forEach(function (champ) {
              champ.name = champ.name.replace('__i__', 'nouveau' + compteur);
            });
            conteneur.appendChild(frag);
          });
        }
        conteneur.addEventListener('click', function (e) {
          var retirer = e.target.closest('.ps-retirer');
          if (retirer) {
            e.preventDefault();
            retirer.closest('.ps-ligne').remove();
          }
        });
      });
    })();
    </script>
    <?php
}

/** Encart d'aide au-dessus d'une liste répétable en grille CSS fixe. */
function ps_textes_astuce_grille(int $multiple): void {
    printf(
        '<p class="ps-hint">%s</p>',
        esc_html(sprintf(
            /* translators: %d: nombre (colonnes de la grille) */
            __("S'affiche en grille sur le site : un nombre de lignes multiple de %d garde des rangées complètes.", 'poivre-sens'),
            $multiple
        ))
    );
}

function ps_textes_section_hero(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('① Hero — En-tête', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Sur-titre', 'poivre-sens'); ?></label>
        <input type="text" name="t[hero][surtitre]" value="<?= esc_attr($v['surtitre']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Disciplines (une par ligne)', 'poivre-sens'); ?></label>
        <textarea name="t[hero][disciplines]" rows="4"><?= esc_textarea(implode("\n", $v['disciplines'])) ?></textarea></div>
      <div class="ps-champ"><label><?php _e('Texte du bouton', 'poivre-sens'); ?></label>
        <input type="text" name="t[hero][cta]" value="<?= esc_attr($v['cta']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Citation', 'poivre-sens'); ?></label>
        <input type="text" name="t[hero][citation]" value="<?= esc_attr($v['citation']) ?>"></div>
      <div class="ps-champ"><label><?php _e("Chapô d'introduction", 'poivre-sens'); ?></label>
        <textarea name="t[hero][intro]" rows="3"><?= esc_textarea($v['intro']) ?></textarea></div>
    </div>
<?php }

function ps_textes_section_manifeste(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('② Manifeste', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[manifeste][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre (le HTML simple — &lt;em&gt; — est conservé)', 'poivre-sens'); ?></label>
        <input type="text" name="t[manifeste][titre_html]" value="<?= esc_attr($v['titre_html']) ?>"></div>
      <div class="ps-champ">
        <label><?php _e('Paragraphes (séparez-les par une ligne vide)', 'poivre-sens'); ?></label>
        <p class="ps-hint"><?php _e('Le HTML simple (&lt;em&gt;) est conservé.', 'poivre-sens'); ?></p>
        <textarea name="t[manifeste][paragraphes]" rows="16"><?= esc_textarea(implode("\n\n", $v['paragraphes'])) ?></textarea>
      </div>
    </div>
<?php }

function ps_textes_section_artistes(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('③ Artistes & pédagogues', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[artistes][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre de section', 'poivre-sens'); ?></label>
        <input type="text" name="t[artistes][titre]" value="<?= esc_attr($v['titre']) ?>"></div>

      <?php foreach ($v['bios'] as $i => $b): ?>
      <div class="ps-ligne">
        <h3 style="margin-top:0"><?= esc_html($b['nom'] ?: sprintf(__('Fondateur %d', 'poivre-sens'), $i + 1)) ?></h3>
        <div class="ps-champ"><label><?php _e('Initiale', 'poivre-sens'); ?></label>
          <input type="text" name="t[artistes][bios][<?= $i ?>][initiale]" value="<?= esc_attr($b['initiale']) ?>" maxlength="2" style="max-width:60px"></div>
        <div class="ps-champ"><label><?php _e('Nom', 'poivre-sens'); ?></label>
          <input type="text" name="t[artistes][bios][<?= $i ?>][nom]" value="<?= esc_attr($b['nom']) ?>"></div>
        <div class="ps-champ"><label><?php _e('Rôle', 'poivre-sens'); ?></label>
          <input type="text" name="t[artistes][bios][<?= $i ?>][role]" value="<?= esc_attr($b['role']) ?>"></div>
        <div class="ps-champ"><label><?php _e('Texte (séparez les paragraphes par une ligne vide)', 'poivre-sens'); ?></label>
          <textarea name="t[artistes][bios][<?= $i ?>][textes]" rows="6"><?= esc_textarea(implode("\n\n", $b['textes'])) ?></textarea></div>
        <div class="ps-champ"><label><?php _e('Étiquettes (séparées par des virgules)', 'poivre-sens'); ?></label>
          <input type="text" name="t[artistes][bios][<?= $i ?>][tags]" value="<?= esc_attr(implode(', ', $b['tags'])) ?>"></div>
      </div>
      <?php endforeach; ?>
      <p class="ps-hint"><?php _e("Le nombre de fondateurs n'est pas modifiable depuis cette page (la mise en page est prévue pour deux) — contactez votre développeur pour en ajouter un.", 'poivre-sens'); ?></p>
    </div>
<?php }

function ps_textes_section_influences(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('Ce qui nous traverse', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[influences][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <?php ps_textes_astuce_grille(3); ?>
      <div class="ps-repetable" data-groupe="influences">
        <div class="ps-lignes">
          <?php foreach ($v['items'] as $i => $item): ?>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Nom', 'poivre-sens'); ?></label>
              <input type="text" name="t[influences][items][<?= $i ?>][nom]" value="<?= esc_attr($item[0]) ?>"></div>
            <div class="ps-champ"><label><?php _e('Description', 'poivre-sens'); ?></label>
              <input type="text" name="t[influences][items][<?= $i ?>][description]" value="<?= esc_attr($item[1]) ?>"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Nom', 'poivre-sens'); ?></label>
              <input type="text" name="t[influences][items][__i__][nom]"></div>
            <div class="ps-champ"><label><?php _e('Description', 'poivre-sens'); ?></label>
              <input type="text" name="t[influences][items][__i__][description]"></div>
          </div>
        </template>
        <button type="button" class="button ps-ajouter"><?php _e('+ Ajouter une entrée', 'poivre-sens'); ?></button>
      </div>
    </div>
<?php }

function ps_textes_section_projet(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('④ Projet artistique', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[projet][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre de section', 'poivre-sens'); ?></label>
        <input type="text" name="t[projet][titre]" value="<?= esc_attr($v['titre']) ?>"></div>
      <?php ps_textes_astuce_grille(3); ?>
      <p class="ps-hint"><?php _e("La numérotation (01, 02…) est recalculée automatiquement selon l'ordre des lignes.", 'poivre-sens'); ?></p>
      <div class="ps-repetable" data-groupe="axes">
        <div class="ps-lignes">
          <?php foreach ($v['axes'] as $i => $axe): ?>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php echo esc_html($axe['num']); ?> — <?php _e('Titre', 'poivre-sens'); ?></label>
              <input type="text" name="t[projet][axes][<?= $i ?>][titre]" value="<?= esc_attr($axe['titre']) ?>"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[projet][axes][<?= $i ?>][texte]" rows="3"><?= esc_textarea($axe['texte']) ?></textarea></div>
          </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Titre', 'poivre-sens'); ?></label>
              <input type="text" name="t[projet][axes][__i__][titre]"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[projet][axes][__i__][texte]" rows="3"></textarea></div>
          </div>
        </template>
        <button type="button" class="button ps-ajouter"><?php _e('+ Ajouter un axe', 'poivre-sens'); ?></button>
      </div>
    </div>
<?php }

function ps_textes_section_activites(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('⑤ Nos activités', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[activites][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre de section', 'poivre-sens'); ?></label>
        <input type="text" name="t[activites][titre]" value="<?= esc_attr($v['titre']) ?>"></div>
      <div class="ps-champ"><label><?php _e("Chapô (au-dessus de la liste)", 'poivre-sens'); ?></label>
        <textarea name="t[activites][chapeau]" rows="2"><?= esc_textarea($v['chapeau']) ?></textarea></div>

      <p class="ps-hint"><?php _e("L'ordre des lignes est l'ordre d'affichage sur le site — la première ligne apparaît en premier. La numérotation (01, 02…) est recalculée automatiquement.", 'poivre-sens'); ?></p>
      <div class="ps-repetable" data-groupe="activites">
        <div class="ps-lignes">
          <?php foreach ($v['items'] as $i => $item): ?>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php echo esc_html($item['num']); ?> — <?php _e('Titre', 'poivre-sens'); ?></label>
              <input type="text" name="t[activites][items][<?= $i ?>][titre]" value="<?= esc_attr($item['titre']) ?>"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[activites][items][<?= $i ?>][texte]" rows="3"><?= esc_textarea($item['texte']) ?></textarea></div>
            <div class="ps-champ"><label><?php _e('Badge (ex. Bimensuel, Stage, Jam…)', 'poivre-sens'); ?></label>
              <input type="text" name="t[activites][items][<?= $i ?>][badge]" value="<?= esc_attr($item['badge']) ?>" style="max-width:220px"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Titre', 'poivre-sens'); ?></label>
              <input type="text" name="t[activites][items][__i__][titre]"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[activites][items][__i__][texte]" rows="3"></textarea></div>
            <div class="ps-champ"><label><?php _e('Badge', 'poivre-sens'); ?></label>
              <input type="text" name="t[activites][items][__i__][badge]" style="max-width:220px"></div>
          </div>
        </template>
        <button type="button" class="button ps-ajouter"><?php _e('+ Ajouter une activité', 'poivre-sens'); ?></button>
      </div>

      <h3><?php _e('Où nous aimerions jouer', 'poivre-sens'); ?></h3>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[activites][diffusion_label]" value="<?= esc_attr($v['diffusion_label']) ?>"></div>
      <?php ps_textes_astuce_grille(2); ?>
      <div class="ps-repetable" data-groupe="diffusion">
        <div class="ps-lignes">
          <?php foreach ($v['diffusion'] as $i => $d): ?>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <input type="text" name="t[activites][diffusion][<?= $i ?>]" value="<?= esc_attr($d) ?>">
          </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <input type="text" name="t[activites][diffusion][__i__]">
          </div>
        </template>
        <button type="button" class="button ps-ajouter"><?php _e('+ Ajouter une ligne', 'poivre-sens'); ?></button>
      </div>
    </div>
<?php }

function ps_textes_section_esthetique(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('⑦ Esthétique & citation', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[esthetique][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre de section', 'poivre-sens'); ?></label>
        <input type="text" name="t[esthetique][titre]" value="<?= esc_attr($v['titre']) ?>"></div>

      <div class="ps-repetable" data-groupe="valeurs">
        <div class="ps-lignes">
          <?php foreach ($v['valeurs'] as $i => $val): ?>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Mot-clé', 'poivre-sens'); ?></label>
              <input type="text" name="t[esthetique][valeurs][<?= $i ?>][label]" value="<?= esc_attr($val[0]) ?>" style="max-width:280px"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[esthetique][valeurs][<?= $i ?>][texte]" rows="2"><?= esc_textarea($val[1]) ?></textarea></div>
          </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="ps-ligne">
            <button type="button" class="button-link ps-retirer"><?php _e('Retirer', 'poivre-sens'); ?></button>
            <div class="ps-champ"><label><?php _e('Mot-clé', 'poivre-sens'); ?></label>
              <input type="text" name="t[esthetique][valeurs][__i__][label]" style="max-width:280px"></div>
            <div class="ps-champ"><label><?php _e('Texte', 'poivre-sens'); ?></label>
              <textarea name="t[esthetique][valeurs][__i__][texte]" rows="2"></textarea></div>
          </div>
        </template>
        <button type="button" class="button ps-ajouter"><?php _e('+ Ajouter une valeur', 'poivre-sens'); ?></button>
      </div>

      <div class="ps-champ" style="margin-top:18px"><label><?php _e('Citation (le HTML simple — &lt;br&gt;, &lt;em&gt; — est conservé)', 'poivre-sens'); ?></label>
        <textarea name="t[esthetique][citation_html]" rows="3"><?= esc_textarea($v['citation_html']) ?></textarea></div>
      <div class="ps-champ"><label><?php _e('Signature de la citation', 'poivre-sens'); ?></label>
        <input type="text" name="t[esthetique][citation_source]" value="<?= esc_attr($v['citation_source']) ?>"></div>
    </div>
<?php }

function ps_textes_section_contact(array $v): void { ?>
    <div class="ps-section">
      <h2><?php _e('⑧ Contact', 'poivre-sens'); ?></h2>
      <div class="ps-champ"><label><?php _e('Étiquette', 'poivre-sens'); ?></label>
        <input type="text" name="t[contact][label]" value="<?= esc_attr($v['label']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Titre de section', 'poivre-sens'); ?></label>
        <input type="text" name="t[contact][titre]" value="<?= esc_attr($v['titre']) ?>"></div>
      <div class="ps-champ"><label><?php _e('Note (sous « Suivre la compagnie »)', 'poivre-sens'); ?></label>
        <textarea name="t[contact][note]" rows="3"><?= esc_textarea($v['note']) ?></textarea></div>
    </div>
<?php }
