<?php
/**
 * Poivre & Sens — functions.php
 * Thème WordPress pour la Compagnie de danse & musique improvisées
 */

defined('ABSPATH') || exit;

// Interface newsletter
require_once get_template_directory() . '/inc/newsletter-admin.php';

// Shortcodes & patterns Gutenberg — édition WYSIWYG de la page d'accueil
require_once get_template_directory() . '/inc/block-patterns.php';

// Popup « site en construction » + capture newsletter (non invasif, cookie)
require_once get_template_directory() . '/inc/construction-popup.php';

// Balises description / Open Graph / données structurées
require_once get_template_directory() . '/inc/seo.php';

// Interface d'édition d'un événement (champs + aperçu en direct)
require_once get_template_directory() . '/inc/event-meta-box.php';

// Bascule des événements du thème vers le plugin CF (outil de migration)
require_once get_template_directory() . '/inc/event-migration.php';

/* ═══════════════════════════════════════════════════════════
   0. INDEXATION — pages utilitaires à exclure de Google
   ═══════════════════════════════════════════════════════════ */

/**
 * Marque en « noindex » les URL utilitaires à paramètre, qui affichent une
 * variante d'une page existante et que Google signale sinon comme doublons
 * (« Page en double : Google n'a pas choisi la même URL canonique »).
 *
 * Concerné :
 *   ?mailpoet_page=…  gestion d'abonnement / désinscription MailPoet
 *   ?ps_popup=1       affichage forcé du popup (mode test du thème)
 *
 * Ces pages restent parfaitement accessibles aux visiteurs : on demande
 * seulement aux moteurs de ne pas les indexer.
 */
add_filter('wp_robots', function (array $robots) {
    $params_utilitaires = ['mailpoet_page', 'mailpoet_router', 'ps_popup'];
    foreach ($params_utilitaires as $param) {
        if (isset($_GET[$param])) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
            unset($robots['index'], $robots['follow']);
            break;
        }
    }
    return $robots;
});

/* ═══════════════════════════════════════════════════════════
   1. SUPPORTS & SETUP
   ═══════════════════════════════════════════════════════════ */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('editor-styles');
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_editor_style('assets/css/editor.css');

    register_nav_menus([
        'primary' => __('Menu principal', 'poivre-sens'),
        'footer'  => __('Menu pied de page', 'poivre-sens'),
    ]);

    add_image_size('evt-thumbnail', 800, 450, true);
    add_image_size('evt-card',      480, 270, true);
    add_image_size('galerie-thumb', 900, 900, true);
});

/* ═══════════════════════════════════════════════════════════
   2. CHARTE GRAPHIQUE — Customizer WordPress
   ═══════════════════════════════════════════════════════════ */

/**
 * Chartes disponibles — label + aperçu couleur de fond + accent
 */
function ps_color_schemes(): array {
    return [
        'nuit'    => [
            'label'  => __('🌑 Nuit (défaut) — élégance sombre',  'poivre-sens'),
            'bg'     => '#080705',
            'accent' => '#c28b36',
        ],
        'aurore'  => [
            'label'  => __('🌅 Aurore — brun ambré, chaleur d\'âtre', 'poivre-sens'),
            'bg'     => '#1f1008',
            'accent' => '#d49820',
        ],
        'foret'   => [
            'label'  => __('🌿 Forêt claire — sauge, respiration et profondeur', 'poivre-sens'),
            'bg'     => '#e7efe7',
            'accent' => '#6d8f70',
        ],
        'lumiere' => [
            'label'  => __('☀️ Clair — ivoire nocturne, plus lumineux', 'poivre-sens'),
            'bg'     => '#f1e9dd',
            'accent' => '#b68952',
        ],
    ];
}

/** Enregistrement Customizer */
add_action('customize_register', function ( \WP_Customize_Manager $wp_customize ) {
    $wp_customize->add_section('ps_charte', [
        'title'       => __('Charte graphique', 'poivre-sens'),
        'description' => __('Choisissez le thème de couleurs du site. Le changement est immédiat.', 'poivre-sens'),
        'priority'    => 28,
    ]);

    $wp_customize->add_setting('color_scheme', [
        'default'           => '',
        'sanitize_callback' => function ( $val ) {
            return ( '' === $val || 'auto' === $val || array_key_exists($val, ps_color_schemes()) ) ? $val : '';
        },
        'transport'         => 'postMessage', // mise à jour live sans rechargement
    ]);

    $choices = [
        ''     => __('— Par défaut (Nuit) —', 'poivre-sens'),
        'auto' => __('🔄 Automatique (heure + système sombre/clair)', 'poivre-sens'),
    ];
    foreach ( ps_color_schemes() as $key => $data ) {
        $choices[ $key ] = $data['label'];
    }

    $wp_customize->add_control('color_scheme', [
        'label'       => __('Thème de couleurs', 'poivre-sens'),
        'description' => __('« Automatique » choisit le thème selon l\'heure (nuit → Aurore, jour → Forêt ou Lumière) et la préférence sombre/clair de l\'appareil.', 'poivre-sens'),
        'section'     => 'ps_charte',
        'type'        => 'select',
        'choices'     => $choices,
    ]);
});

/** JS live-preview Customizer : met à jour data-theme sans rechargement */
add_action('customize_preview_init', function () {
    wp_add_inline_script(
        'customize-preview',
        "wp.customize('color_scheme', function(value){
            value.bind(function(newVal){
                if (newVal === 'auto') {
                    var h = new Date().getHours();
                    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches;
                    var t = (h >= 20 || h < 7) ? 'aurore' : (dark ? 'foret' : 'lumiere');
                    document.documentElement.setAttribute('data-theme', t);
                    document.documentElement.setAttribute('data-auto-theme', '1');
                } else if (newVal) {
                    document.documentElement.removeAttribute('data-auto-theme');
                    document.documentElement.setAttribute('data-theme', newVal);
                } else {
                    document.documentElement.removeAttribute('data-auto-theme');
                    document.documentElement.removeAttribute('data-theme');
                }
            });
        });"
    );
});

/* ═══════════════════════════════════════════════════════════
   3. ENQUEUE STYLES & SCRIPTS
   ═══════════════════════════════════════════════════════════ */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'google-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Inter:wght@300;400;500&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'poivre-sens-theme',
        get_template_directory_uri() . '/assets/css/theme.css',
        ['google-fonts'],
        filemtime(get_template_directory() . '/assets/css/theme.css')
    );
    wp_enqueue_script(
        'poivre-sens-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        [],
        filemtime(get_template_directory() . '/assets/js/theme.js'),
        true
    );
    // Passer les variables AJAX au JS
    wp_localize_script('poivre-sens-theme', 'PS', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ps_newsletter'),
    ]);
});

/* ═══════════════════════════════════════════════════════════
   3. CUSTOM POST TYPE — ÉVÉNEMENT
   ═══════════════════════════════════════════════════════════ */
add_action('init', function () {
    register_post_type('evenement', [
        'labels' => [
            'name'               => __('Événements',          'poivre-sens'),
            'singular_name'      => __('Événement',           'poivre-sens'),
            'add_new'            => __('Ajouter',             'poivre-sens'),
            'add_new_item'       => __('Nouvel événement',    'poivre-sens'),
            'edit_item'          => __('Modifier l\'événement', 'poivre-sens'),
            'new_item'           => __('Nouvel événement',    'poivre-sens'),
            'view_item'          => __('Voir l\'événement',   'poivre-sens'),
            'search_items'       => __('Chercher',            'poivre-sens'),
            'not_found'          => __('Aucun événement.',    'poivre-sens'),
            'not_found_in_trash' => __('Aucun événement dans la corbeille.', 'poivre-sens'),
            'menu_name'          => __('Événements',          'poivre-sens'),
        ],
        'public'            => true,
        'has_archive'       => true,
        'rewrite'           => ['slug' => 'evenements'],
        'menu_icon'         => 'dashicons-calendar-alt',
        'menu_position'     => 5,
        'supports'          => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest'      => true,   // Gutenberg
        'taxonomies'        => ['evt_type'],
    ]);
});

/* ── Taxonomie type d'événement ──────────────────────────── */
add_action('init', function () {
    register_taxonomy('evt_type', 'evenement', [
        'labels' => [
            'name'          => __('Types',              'poivre-sens'),
            'singular_name' => __('Type',               'poivre-sens'),
            'add_new_item'  => __('Ajouter un type',    'poivre-sens'),
            'edit_item'     => __('Modifier le type',   'poivre-sens'),
        ],
        'hierarchical'  => true,
        'public'        => true,
        'show_in_rest'  => true,
        'rewrite'       => ['slug' => 'type-evenement'],
    ]);
});

/* ═══════════════════════════════════════════════════════════
   4. CUSTOM POST TYPE — GALERIE
   ═══════════════════════════════════════════════════════════ */
add_action('init', function () {
    register_post_type('galerie', [
        'labels' => [
            'name'          => __('Galerie',         'poivre-sens'),
            'singular_name' => __('Photo',           'poivre-sens'),
            'add_new'       => __('Ajouter',         'poivre-sens'),
            'add_new_item'  => __('Nouvelle photo',  'poivre-sens'),
            'edit_item'     => __('Modifier la photo', 'poivre-sens'),
            'menu_name'     => __('Galerie',         'poivre-sens'),
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-format-gallery',
        'menu_position' => 6,
        'supports'      => ['title', 'thumbnail', 'excerpt'],
        'show_in_rest'  => true,
    ]);
});

/* ═══════════════════════════════════════════════════════════
   6. META BOX — GALERIE (caption)
   ═══════════════════════════════════════════════════════════ */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'ps_galerie_caption',
        __('Légende photo', 'poivre-sens'),
        function ($post) {
            wp_nonce_field('ps_galerie_save', 'ps_galerie_nonce');
            $caption = get_post_meta($post->ID, '_galerie_caption', true);
            echo '<label style="display:block;margin-bottom:6px;font-size:11px;text-transform:uppercase;color:#555;letter-spacing:.1em;font-weight:600">' . __('Sous-titre (affiché au survol)', 'poivre-sens') . '</label>';
            echo '<input type="text" name="galerie_caption" value="' . esc_attr($caption) . '" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px">';
        },
        'galerie', 'normal', 'default'
    );
});
add_action('save_post_galerie', function ($post_id) {
    if (!isset($_POST['ps_galerie_nonce']) || !wp_verify_nonce($_POST['ps_galerie_nonce'], 'ps_galerie_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['galerie_caption'])) {
        update_post_meta($post_id, '_galerie_caption', sanitize_text_field($_POST['galerie_caption']));
    }
});

/* ═══════════════════════════════════════════════════════════
   7. TABLE & GESTION NEWSLETTER
   Le schéma complet (abonnés, listes, campagnes) et les migrations
   sont gérés par ps_nl_upgrade_db() dans inc/newsletter-admin.php.
   NB : register_activation_hook() ne fonctionne que pour les plugins ;
   pour un thème, le hook correct est after_switch_theme.
   ═══════════════════════════════════════════════════════════ */
add_action('after_switch_theme', function () {
    ps_nl_upgrade_db();
    flush_rewrite_rules();
});

// Créer/mettre à niveau les tables si besoin (upgrade / thème activé sans hook)
add_action('init', function () {
    if (get_option('ps_newsletter_db_version') !== '2.0') {
        ps_nl_upgrade_db();
        update_option('ps_newsletter_db_version', '2.0');
    }
});

/* ── AJAX : inscription newsletter ──────────────────────── */
add_action('wp_ajax_ps_newsletter_subscribe',        'ps_newsletter_subscribe');
add_action('wp_ajax_nopriv_ps_newsletter_subscribe', 'ps_newsletter_subscribe');

function ps_newsletter_subscribe() {
    check_ajax_referer('ps_newsletter', 'nonce');

    $email  = sanitize_email(wp_unslash($_POST['email']  ?? ''));
    $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
    $nom    = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
    // Slug de liste optionnel : permet à un formulaire (landing page, événement…)
    // de localiser l'origine des prospects, ex. « grand-bal-europe ».
    $liste_slug = sanitize_title(wp_unslash($_POST['liste'] ?? ''));
    $source     = $liste_slug ?: sanitize_text_field(wp_unslash($_POST['source'] ?? 'site'));
    // À défaut de liste explicite, on rattache à la liste correspondant à la
    // source (ex. « site » pour le formulaire principal, déjà semée par défaut).
    $attach_slug = $liste_slug ?: sanitize_title($source);

    if (!is_email($email)) {
        wp_send_json_error(['message' => __('Adresse e-mail invalide.', 'poivre-sens')], 400);
    }

    global $wpdb;
    $table    = $wpdb->prefix . 'ps_newsletter';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT id, statut FROM $table WHERE email = %s", $email));

    // Sécurité : on ne rattache qu'à une liste déjà existante (créée depuis
    // l'admin). Un visiteur anonyme ne doit pas pouvoir faire créer de
    // nouvelles listes ou polluer les statistiques via un slug arbitraire.
    $list_id = 0;
    if ($attach_slug) {
        $target_list = ps_nl_get_list_by_slug($attach_slug);
        if ($target_list) $list_id = (int)$target_list->id;
    }

    if ($existing) {
        if ($list_id) ps_nl_add_subscriber_to_list($existing->id, $list_id);
        if ($existing->statut === 'actif') {
            // 'already' => permet au front (popup construction) de mémoriser
            // que cette personne est déjà abonnée et de ne plus l'importuner.
            wp_send_json_error(['message' => __('Vous êtes déjà inscrit(e) à notre newsletter.', 'poivre-sens'), 'already' => true]);
        }
        // Réactivation
        $wpdb->update($table, ['statut' => 'actif', 'date_confirm' => current_time('mysql')], ['id' => $existing->id]);
        wp_send_json_success(['message' => __('Votre inscription a bien été réactivée. Bienvenue !', 'poivre-sens')]);
    }

    $token = wp_generate_password(32, false);
    $wpdb->insert($table, [
        'email'         => $email,
        'prenom'        => $prenom,
        'nom'           => $nom,
        'statut'        => 'actif',
        'token'         => $token,
        'source'        => $source,
        'date_creation' => current_time('mysql'),
        'date_confirm'  => current_time('mysql'),
    ]);

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => __('Une erreur est survenue, veuillez réessayer.', 'poivre-sens')], 500);
    }

    if ($list_id) ps_nl_add_subscriber_to_list($wpdb->insert_id, $list_id);

    // E-mail de confirmation
    ps_send_confirm_email($email, $prenom, $token);

    wp_send_json_success(['message' => __('Merci ! Un e-mail de confirmation vous a été envoyé.', 'poivre-sens')]);
}

/* ── AJAX : désinscription newsletter ───────────────────── */
add_action('wp_ajax_nopriv_ps_newsletter_unsubscribe', 'ps_newsletter_unsubscribe');
add_action('wp_ajax_ps_newsletter_unsubscribe',        'ps_newsletter_unsubscribe');

function ps_newsletter_unsubscribe() {
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
    if (!$token) wp_die(__('Lien invalide.', 'poivre-sens'));

    global $wpdb;
    $table = $wpdb->prefix . 'ps_newsletter';
    $rows  = $wpdb->update($table, ['statut' => 'desabonne'], ['token' => $token]);

    if ($rows) {
        wp_die(__('Vous avez bien été désinscrit(e) de notre newsletter. À bientôt !', 'poivre-sens'));
    }
    wp_die(__('Lien invalide ou déjà utilisé.', 'poivre-sens'));
}

/**
 * E-mail de bienvenue envoyé à chaque nouvelle inscription.
 * Objet et corps sont modifiables dans Newsletter › Réglages ;
 * les valeurs par défaut reprennent le message d'origine.
 */
function ps_send_confirm_email($email, $prenom, $token) {
    if (ps_nl_confirm_opt('ps_nl_confirm_actif') !== '1') return false;

    $unsub = add_query_arg(
        ['action' => 'ps_newsletter_unsubscribe', 'token' => $token],
        admin_url('admin-ajax.php')
    );

    $subject = ps_nl_confirm_render(ps_nl_confirm_opt('ps_nl_confirm_sujet'), $prenom, $email, $unsub);
    $body    = ps_nl_confirm_render(ps_nl_confirm_opt('ps_nl_confirm_corps'), $prenom, $email, $unsub);

    return wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
}

/* ── Page admin abonnés newsletter ──────────────────────── */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=evenement',
        __('Newsletter — Abonnés', 'poivre-sens'),
        __('Newsletter', 'poivre-sens'),
        'manage_options',
        'ps-newsletter',
        'ps_newsletter_admin_page'
    );
});

function ps_newsletter_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ps_newsletter';

    // Export CSV
    if (isset($_GET['export']) && current_user_can('manage_options')) {
        check_admin_referer('ps_export_csv');
        $rows = $wpdb->get_results("SELECT email, prenom, statut, date_creation FROM $table WHERE statut='actif' ORDER BY date_creation DESC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Email', 'Prénom', 'Statut', 'Date inscription']);
        foreach ($rows as $r) {
            fputcsv($out, [$r->email, $r->prenom, $r->statut, $r->date_creation]);
        }
        fclose($out);
        exit;
    }

    $actifs     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE statut='actif'");
    $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $abonnes    = $wpdb->get_results("SELECT * FROM $table ORDER BY date_creation DESC LIMIT 200");
    $export_url = wp_nonce_url(add_query_arg(['export' => 1], admin_url('admin.php?page=ps-newsletter')), 'ps_export_csv');
    ?>
    <div class="wrap">
        <h1><?php _e('Newsletter — Abonnés', 'poivre-sens'); ?></h1>
        <div style="display:flex;gap:32px;margin:20px 0">
            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px 28px;text-align:center">
                <div style="font-size:2.5rem;font-weight:700;color:#c28b36"><?php echo $actifs; ?></div>
                <div style="color:#555;font-size:.9rem"><?php _e('Abonnés actifs', 'poivre-sens'); ?></div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px 28px;text-align:center">
                <div style="font-size:2.5rem;font-weight:700;color:#666"><?php echo $total; ?></div>
                <div style="color:#555;font-size:.9rem"><?php _e('Total inscrits', 'poivre-sens'); ?></div>
            </div>
            <div style="display:flex;align-items:center">
                <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">
                    ⬇ <?php _e('Exporter CSV (actifs)', 'poivre-sens'); ?>
                </a>
            </div>
        </div>
        <table class="widefat striped">
            <thead><tr>
                <th><?php _e('E-mail', 'poivre-sens'); ?></th>
                <th><?php _e('Prénom', 'poivre-sens'); ?></th>
                <th><?php _e('Statut', 'poivre-sens'); ?></th>
                <th><?php _e('Date inscription', 'poivre-sens'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($abonnes as $a): ?>
                <tr>
                    <td><?php echo esc_html($a->email); ?></td>
                    <td><?php echo esc_html($a->prenom); ?></td>
                    <td>
                        <?php if ($a->statut === 'actif'): ?>
                            <span style="color:#0a7c0a;font-weight:600">✓ <?php _e('Actif', 'poivre-sens'); ?></span>
                        <?php elseif ($a->statut === 'desabonne'): ?>
                            <span style="color:#999"><?php _e('Désabonné(e)', 'poivre-sens'); ?></span>
                        <?php else: ?>
                            <span style="color:#c28b36"><?php _e('En attente', 'poivre-sens'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($a->date_creation))); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ═══════════════════════════════════════════════════════════
   8. HELPER — ÉVÉNEMENTS
   ═══════════════════════════════════════════════════════════ */

/** Retourne le libellé d'un type d'événement */
function ps_evt_type_label($key) {
    $labels = [
        'spectacle' => 'Spectacle vivant',
        'jam'       => 'Jam contact',
        'atelier'   => 'Atelier / Stage',
        'residence' => 'Résidence',
        'concert'   => 'Concert',
        'autre'     => 'Événement',
    ];
    return $labels[$key] ?? ucfirst($key);
}

/** Formate la date d'un événement en français */
function ps_format_date($date_str, $format = 'j F Y') {
    if (!$date_str) return '';
    return date_i18n($format, strtotime($date_str));
}

/** Retourne les 3 prochains événements */
function ps_get_upcoming_events($limit = 3) {
    return new WP_Query([
        'post_type'      => 'evenement',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'meta_key'       => '_evt_date',
        'meta_value'     => date('Y-m-d'),
        'meta_compare'   => '>=',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ]);
}

/** Retourne les événements d'un mois donné */
function ps_get_events_for_month($year, $month) {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-t', strtotime($start));
    return new WP_Query([
        'post_type'      => 'evenement',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_evt_date',
        'meta_query'     => [[
            'key'     => '_evt_date',
            'value'   => [$start, $end],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ]],
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ]);
}

/* ═══════════════════════════════════════════════════════════
   10. COLONNES ADMIN ÉVÉNEMENTS
   ═══════════════════════════════════════════════════════════ */
add_filter('manage_evenement_posts_columns', function ($cols) {
    $new = [];
    foreach ($cols as $k => $v) {
        $new[$k] = $v;
        if ($k === 'title') {
            $new['evt_date'] = __('Date', 'poivre-sens');
            $new['evt_lieu'] = __('Lieu', 'poivre-sens');
            $new['evt_type'] = __('Type', 'poivre-sens');
        }
    }
    return $new;
});

add_action('manage_evenement_posts_custom_column', function ($col, $post_id) {
    if ($col === 'evt_date') echo esc_html(ps_format_date(get_post_meta($post_id, '_evt_date', true)));
    if ($col === 'evt_lieu') echo esc_html(get_post_meta($post_id, '_evt_lieu', true) . ' ' . get_post_meta($post_id, '_evt_ville', true));
    if ($col === 'evt_type') echo esc_html(ps_evt_type_label(get_post_meta($post_id, '_evt_type', true)));
}, 10, 2);

add_filter('manage_edit-evenement_sortable_columns', function ($cols) {
    $cols['evt_date'] = 'evt_date';
    return $cols;
});
