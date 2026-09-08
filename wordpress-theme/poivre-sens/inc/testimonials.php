<?php
/**
 * Poivre & Sens — Témoignages
 *
 * Un témoignage n'est visible sur le site qu'une fois publié : la
 * publication tient lieu d'autorisation explicite (comme les autres
 * contenus du site), au lieu d'une case « consentement » séparée à
 * oublier de cocher.
 *
 * Le nom de la personne est le titre de l'article, son texte est le
 * corps de l'article (édité en WYSIWYG), et sa photo (facultative) est
 * l'image mise en avant — trois champs déjà connus, sans en réinventer
 * de nouveaux pour ce qui n'en a pas besoin.
 */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('temoignage', [
        'labels' => [
            'name'          => __('Témoignages',           'poivre-sens'),
            'singular_name' => __('Témoignage',             'poivre-sens'),
            'add_new'       => __('Ajouter',                'poivre-sens'),
            'add_new_item'  => __('Nouveau témoignage',     'poivre-sens'),
            'edit_item'     => __('Modifier le témoignage', 'poivre-sens'),
            'menu_name'     => __('Témoignages',            'poivre-sens'),
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-format-quote',
        'menu_position' => 7,
        'supports'      => ['title', 'editor', 'thumbnail', 'page-attributes'],
        'show_in_rest'  => true,
    ]);

    // Type de témoignage (Atelier, Stage, Résidence…) — même principe que les
    // catégories d'événement du plugin CF Réservations : une couleur par type,
    // affichée en pastille sur chaque témoignage plutôt qu'un simple libellé.
    register_taxonomy('temoignage_type', 'temoignage', [
        'labels' => [
            'name'          => __('Types de témoignage', 'poivre-sens'),
            'singular_name' => __('Type',                'poivre-sens'),
            'add_new_item'  => __('Nouveau type',         'poivre-sens'),
        ],
        'hierarchical'      => false,
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
    ]);
});

/**
 * Force un éditeur clair (fond blanc, texte sombre) sur l'écran de
 * modification d'un témoignage, quel que soit le mode sombre du
 * navigateur/système du visiteur — color-scheme empêche l'inversion
 * automatique, le reste couvre les navigateurs qui l'ignorent encore.
 * Un style sans fichier source (juste du CSS en ligne) suffit : WordPress
 * le recopie automatiquement dans l'iframe de l'éditeur de blocs.
 */
add_action('enqueue_block_editor_assets', function () {
    $ecran = get_current_screen();
    if (!$ecran || $ecran->post_type !== 'temoignage') return;

    wp_register_style('ps-temoignage-editeur-clair', false);
    wp_enqueue_style('ps-temoignage-editeur-clair');
    wp_add_inline_style('ps-temoignage-editeur-clair', '
        html, :root { color-scheme: light !important; }
        body.block-editor-iframe__body,
        .editor-styles-wrapper,
        .edit-post-visual-editor,
        .interface-interface-skeleton,
        .interface-interface-skeleton__sidebar,
        .block-editor-writing-flow,
        .editor-post-title__input {
            background: #fff !important;
            color: #1e1e1e !important;
        }
    ');
});

/** Couleur d'un type de témoignage (choisie sur sa page de modification). */
function ps_temoignage_type_couleur($term_id) {
    return (string) get_term_meta($term_id, '_temoignage_type_color', true);
}

/** Champ couleur sur l'écran d'AJOUT d'un type de témoignage. */
add_action('temoignage_type_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="temoignage_type_color"><?php _e('Couleur', 'poivre-sens'); ?></label>
        <input type="color" name="temoignage_type_color" id="temoignage_type_color" value="#c28b36">
        <p><?php _e('Couleur de la pastille affichée sur les témoignages de ce type.', 'poivre-sens'); ?></p>
    </div>
    <?php
});

/** Champ couleur sur l'écran de MODIFICATION d'un type de témoignage. */
add_action('temoignage_type_edit_form_fields', function ($term) {
    $couleur = ps_temoignage_type_couleur($term->term_id) ?: '#c28b36';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="temoignage_type_color"><?php _e('Couleur', 'poivre-sens'); ?></label></th>
        <td>
            <input type="color" name="temoignage_type_color" id="temoignage_type_color" value="<?php echo esc_attr($couleur); ?>">
            <p class="description"><?php _e('Couleur de la pastille affichée sur les témoignages de ce type.', 'poivre-sens'); ?></p>
        </td>
    </tr>
    <?php
});

foreach (['created_temoignage_type', 'edited_temoignage_type'] as $ps_hook_couleur) {
    add_action($ps_hook_couleur, function ($term_id) {
        if (!isset($_POST['temoignage_type_color'])) return;
        $couleur = sanitize_hex_color(wp_unslash($_POST['temoignage_type_color']));
        update_term_meta($term_id, '_temoignage_type_color', $couleur ?: '#c28b36');
    });
}

/**
 * Type de témoignage effectif (un seul, comme pour les événements) : clé,
 * libellé et couleur — ou tableau vide si aucun type n'est assigné.
 */
function ps_temoignage_type($post_id) {
    $termes = get_the_terms($post_id, 'temoignage_type');
    if (!is_array($termes) || !$termes) return [];
    $terme = $termes[0];
    return [
        'slug'    => $terme->slug,
        'label'   => $terme->name,
        'couleur' => ps_temoignage_type_couleur($terme->term_id),
    ];
}

/* ═══════════════════════════════════════════════════════════
   ÉCRAN D'ÉDITION — inspiré des « Types de rendez-vous » du plugin
   CF Réservations : une seule boîte pleine largeur, aux champs
   organisés en grille avec libellé/aide, plutôt qu'une colonne
   latérale étroite empilant des <p> sans hiérarchie visuelle.
   ═══════════════════════════════════════════════════════════ */

/** Le champ « Titre » sert de nom affiché : le dire dans son propre indice
 *  plutôt que le générique « Ajouter un titre ». */
add_filter('enter_title_here', function ($defaut, $post) {
    return $post && $post->post_type === 'temoignage'
        ? __('Nom de la personne (ex. Marie Dupont)', 'poivre-sens')
        : $defaut;
}, 10, 2);

/** Retire la boîte de taxonomie par défaut (liste de mots-clés en texte
 *  libre) : remplacée ci-dessous par un choix à bouton radio avec pastille
 *  de couleur, cohérent avec l'affichage public du type. */
add_action('add_meta_boxes', function () {
    remove_meta_box('tagsdiv-temoignage_type', 'temoignage', 'side');
}, 20);

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ps_temoignage_details',
        __('Détails du témoignage', 'poivre-sens'),
        'ps_temoignage_metabox',
        'temoignage', 'normal', 'high'
    );
});

function ps_temoignage_metabox($post) {
    wp_nonce_field('ps_temoignage_save', 'ps_temoignage_nonce');
    $role     = get_post_meta($post->ID, '_temoignage_role', true);
    $etoiles  = get_post_meta($post->ID, '_temoignage_etoiles', true);
    $video    = get_post_meta($post->ID, '_temoignage_video', true);
    $type_actif = ps_temoignage_type($post->ID)['slug'] ?? '';
    $types    = get_terms(['taxonomy' => 'temoignage_type', 'hide_empty' => false]);
    if (is_wp_error($types)) $types = [];
    ?>
    <style>
    .ps-tem-grid       { display:grid; grid-template-columns:1fr 1fr; gap:16px 24px; padding:4px 0; }
    .ps-tem-full       { grid-column:1/-1; }
    .ps-tem-label      { display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#1d2327; }
    .ps-tem-hint       { font-size:11px; color:#888; margin-top:3px; }
    .ps-tem-field      { width:100%; padding:7px 10px; border:1px solid #8c8f94; border-radius:3px; font-size:13px; }
    .ps-tem-intro      { background:#f6f7f7; border-left:3px solid #c28b36; padding:10px 14px; font-size:12px; color:#50575e; margin:0 0 18px; }
    .ps-tem-types      { display:flex; flex-wrap:wrap; gap:10px; }
    .ps-tem-type-opt   { display:flex; align-items:center; gap:6px; border:1px solid #dcdcde; border-radius:20px; padding:5px 12px 5px 8px; font-size:12px; cursor:pointer; }
    .ps-tem-type-opt input { margin:0; }
    .ps-tem-color-dot  { width:12px; height:12px; border-radius:50%; display:inline-block; flex-shrink:0; }
    .ps-tem-notice     { background:#fcf0e4; border-left:3px solid #c28b36; padding:10px 14px; font-size:12px; color:#5a4632; margin-top:20px; }
    </style>

    <p class="ps-tem-intro">
        <?php _e("Le titre ci-dessus est le nom affiché. Le texte du témoignage se saisit dans le corps de l'article, plus bas sur cette page (ignoré si une vidéo est réglée ici) ; sa photo, facultative, dans « Image mise en avant ».", 'poivre-sens'); ?>
    </p>

    <div class="ps-tem-grid">
        <div>
            <label class="ps-tem-label" for="ps-tem-role"><?php _e('Rôle ou contexte', 'poivre-sens'); ?></label>
            <input type="text" id="ps-tem-role" name="temoignage_role" value="<?php echo esc_attr($role); ?>" class="ps-tem-field" placeholder="<?php echo esc_attr__("ex. Participante à l'atelier Corps Vivant", 'poivre-sens'); ?>">
            <p class="ps-tem-hint"><?php _e('Affiché sous le nom.', 'poivre-sens'); ?></p>
        </div>
        <div>
            <label class="ps-tem-label" for="ps-tem-etoiles"><?php _e('Note', 'poivre-sens'); ?></label>
            <select id="ps-tem-etoiles" name="temoignage_etoiles" class="ps-tem-field">
                <option value="0" <?php selected($etoiles, '0'); ?>><?php _e('Aucune', 'poivre-sens'); ?></option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <option value="<?php echo (int) $i; ?>" <?php selected($etoiles, (string) $i); ?>><?php echo str_repeat('★', $i); ?></option>
                <?php endfor; ?>
            </select>
            <p class="ps-tem-hint"><?php _e('Facultative.', 'poivre-sens'); ?></p>
        </div>

        <div class="ps-tem-full">
            <label class="ps-tem-label"><?php _e('Type de témoignage', 'poivre-sens'); ?></label>
            <?php if ($types): ?>
            <div class="ps-tem-types">
                <label class="ps-tem-type-opt">
                    <input type="radio" name="temoignage_type" value="" <?php checked($type_actif, ''); ?>>
                    <?php _e('Aucun', 'poivre-sens'); ?>
                </label>
                <?php foreach ($types as $terme): $couleur = ps_temoignage_type_couleur($terme->term_id) ?: '#c28b36'; ?>
                <label class="ps-tem-type-opt">
                    <input type="radio" name="temoignage_type" value="<?php echo esc_attr($terme->slug); ?>" <?php checked($type_actif, $terme->slug); ?>>
                    <span class="ps-tem-color-dot" style="background:<?php echo esc_attr($couleur); ?>"></span>
                    <?php echo esc_html($terme->name); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="ps-tem-hint">
                <?php printf(
                    /* translators: %s: lien vers l'écran de gestion des types */
                    __('Aucun type créé pour le moment. %s pour en ajouter un (avec sa couleur).', 'poivre-sens'),
                    '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=temoignage_type&post_type=temoignage')) . '">' . esc_html__('Gérer les types de témoignage', 'poivre-sens') . '</a>'
                ); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="ps-tem-full">
            <label class="ps-tem-label" for="ps-tem-video"><?php _e('Vidéo (URL YouTube ou Vimeo)', 'poivre-sens'); ?></label>
            <input type="url" id="ps-tem-video" name="temoignage_video" value="<?php echo esc_attr($video); ?>" class="ps-tem-field" placeholder="https://www.youtube.com/watch?v=…">
            <p class="ps-tem-hint"><?php _e('Facultative. Si renseignée, la vidéo remplace le texte du témoignage sur le site.', 'poivre-sens'); ?></p>
        </div>
    </div>

    <p class="ps-tem-notice">
        <?php _e('Reste en brouillon tant que la personne n\'a pas explicitement autorisé sa publication sur le site : seuls les témoignages publiés apparaissent.', 'poivre-sens'); ?>
    </p>
    <?php
}

add_action('save_post_temoignage', function ($post_id) {
    if (!isset($_POST['ps_temoignage_nonce']) || !wp_verify_nonce($_POST['ps_temoignage_nonce'], 'ps_temoignage_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['temoignage_role'])) {
        update_post_meta($post_id, '_temoignage_role', sanitize_text_field($_POST['temoignage_role']));
    }
    if (isset($_POST['temoignage_etoiles'])) {
        update_post_meta($post_id, '_temoignage_etoiles', max(0, min(5, (int) $_POST['temoignage_etoiles'])));
    }
    if (isset($_POST['temoignage_video'])) {
        update_post_meta($post_id, '_temoignage_video', esc_url_raw($_POST['temoignage_video']));
    }
    if (isset($_POST['temoignage_type'])) {
        $slug = sanitize_title(wp_unslash($_POST['temoignage_type']));
        wp_set_object_terms($post_id, $slug === '' ? [] : [$slug], 'temoignage_type');
    }
});
