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
});

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ps_temoignage_details',
        __('Détails du témoignage', 'poivre-sens'),
        function ($post) {
            wp_nonce_field('ps_temoignage_save', 'ps_temoignage_nonce');
            $role    = get_post_meta($post->ID, '_temoignage_role', true);
            $etoiles = get_post_meta($post->ID, '_temoignage_etoiles', true);
            $video   = get_post_meta($post->ID, '_temoignage_video', true);
            ?>
            <p>
                <label style="display:block;margin-bottom:6px;font-size:11px;text-transform:uppercase;color:#555;letter-spacing:.1em;font-weight:600"><?php _e('Rôle ou contexte (affiché sous le nom)', 'poivre-sens'); ?></label>
                <input type="text" name="temoignage_role" value="<?php echo esc_attr($role); ?>" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px" placeholder="<?php echo esc_attr__("ex. Participante à l'atelier Corps Vivant", 'poivre-sens'); ?>">
            </p>
            <p>
                <label style="display:block;margin-bottom:6px;font-size:11px;text-transform:uppercase;color:#555;letter-spacing:.1em;font-weight:600"><?php _e('Note (facultatif)', 'poivre-sens'); ?></label>
                <select name="temoignage_etoiles" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px">
                    <option value="0" <?php selected($etoiles, '0'); ?>><?php _e('Aucune', 'poivre-sens'); ?></option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?php echo (int) $i; ?>" <?php selected($etoiles, (string) $i); ?>><?php echo str_repeat('★', $i); ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p>
                <label style="display:block;margin-bottom:6px;font-size:11px;text-transform:uppercase;color:#555;letter-spacing:.1em;font-weight:600"><?php _e('Vidéo (URL YouTube ou Vimeo, facultatif)', 'poivre-sens'); ?></label>
                <input type="url" name="temoignage_video" value="<?php echo esc_attr($video); ?>" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px" placeholder="https://www.youtube.com/watch?v=…">
                <span style="display:block;margin-top:4px;color:#787878;font-size:12px;"><?php _e('Si renseignée, la vidéo remplace le texte du témoignage sur le site.', 'poivre-sens'); ?></span>
            </p>
            <p style="color:#787878;font-size:12px;">
                <?php _e("Le titre est le nom affiché. Le texte du témoignage (ignoré si une vidéo est réglée ci-dessus) se saisit dans le corps de l'article ci-dessous, sa photo (facultative) dans « Image mise en avant ».", 'poivre-sens'); ?>
            </p>
            <p style="color:#787878;font-size:12px;">
                <?php _e('Reste en brouillon tant que la personne n\'a pas explicitement autorisé sa publication sur le site : seuls les témoignages publiés apparaissent.', 'poivre-sens'); ?>
            </p>
            <?php
        },
        'temoignage', 'side', 'default'
    );
});

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
});
