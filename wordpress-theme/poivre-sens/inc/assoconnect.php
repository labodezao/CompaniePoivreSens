<?php
/**
 * inc/assoconnect.php
 *
 * Widgets de paiement AssoConnect (adhésions, dons…), sur le même principe
 * que inc/helloasso.php : un shortcode plutôt que l'iframe brute (le bloc
 * « HTML personnalisé » de l'éditeur filtre les balises actives pour tout
 * compte sans capacité unfiltered_html), et des campagnes gérées depuis
 * l'admin plutôt qu'un identifiant figé dans le code — l'association ouvre
 * une nouvelle campagne d'adhésion chaque année.
 *
 * Utilise la méthode « div + script » recommandée par AssoConnect (le
 * widget se charge et se redimensionne lui-même via iframe.js) plutôt que
 * l'ancienne iframe brute avec écouteur postMessage manuel — plus robuste,
 * et un seul script partagé quel que soit le nombre de widgets sur la page.
 *
 * Usage (bloc « Shortcode » de l'éditeur) :
 *   [assoconnect]                          → campagne par défaut
 *   [assoconnect campagne="dons"]          → une autre campagne enregistrée
 *   [assoconnect collect_id="01ABCDEF…"]   → campagne ponctuelle, non enregistrée
 */
defined('ABSPATH') || exit;

/* ═══════════════════════════════════════════════════════════
   1. STOCKAGE DES CAMPAGNES — option ps_assoconnect_campagnes
   ═══════════════════════════════════════════════════════════ */

/** Campagne pré-remplie à la première utilisation (celle déjà en ligne). */
function ps_assoconnect_campagnes_defaut() {
    return [
        'adhesion' => [
            'label'      => __('Adhésion', 'poivre-sens'),
            'site'       => 'compagnie-poivresens',
            'collect_id' => '01M20DSQS51GK8S413328KY1TP',
        ],
    ];
}

/** Toutes les campagnes enregistrées, clé => [label, site, collect_id]. */
function ps_assoconnect_campagnes() {
    $campagnes = get_option('ps_assoconnect_campagnes', ps_assoconnect_campagnes_defaut());
    return is_array($campagnes) ? $campagnes : [];
}

/** Clé de la campagne utilisée quand le shortcode n'en précise aucune. */
function ps_assoconnect_campagne_defaut_cle() {
    return (string) get_option('ps_assoconnect_campagne_defaut', 'adhesion');
}

/* ═══════════════════════════════════════════════════════════
   2. SHORTCODE
   ═══════════════════════════════════════════════════════════ */

/** Petit avertissement, visible des seuls administrateurs, quand une
 *  campagne demandée est introuvable. */
function ps_assoconnect_avertissement($texte) {
    return current_user_can('manage_options')
        ? '<p style="color:#a00;font-size:13px;border:1px dashed #a00;padding:8px 12px">⚠ ' . esc_html($texte) . '</p>'
        : '';
}

function ps_assoconnect_shortcode($atts) {
    $atts = shortcode_atts([
        'campagne'   => '',   // clé d'une campagne enregistrée (Apparence → Campagnes AssoConnect)
        'site'       => 'compagnie-poivresens',
        'collect_id' => '',   // identifiant AssoConnect brut, pour une campagne ponctuelle non enregistrée
    ], $atts, 'assoconnect');

    $campagnes = ps_assoconnect_campagnes();

    if ($atts['campagne'] !== '') {
        $cle = sanitize_title($atts['campagne']);
        if (!isset($campagnes[$cle])) {
            return ps_assoconnect_avertissement(sprintf(
                /* translators: %s: clé de campagne recherchée */
                __('AssoConnect : aucune campagne enregistrée avec la clé « %s ». Vérifiez Apparence → Campagnes AssoConnect.', 'poivre-sens'),
                $cle
            ));
        }
        $site       = $campagnes[$cle]['site']       ?? '';
        $collect_id = $campagnes[$cle]['collect_id'] ?? '';
    } elseif ($atts['collect_id'] !== '') {
        $site       = $atts['site'];
        $collect_id = $atts['collect_id'];
    } else {
        $cle = sanitize_title(ps_assoconnect_campagne_defaut_cle());
        if (!isset($campagnes[$cle])) {
            return ps_assoconnect_avertissement(__('AssoConnect : aucune campagne par défaut configurée. Réglez-en une dans Apparence → Campagnes AssoConnect, ou précisez l\'attribut collect_id= du shortcode.', 'poivre-sens'));
        }
        $site       = $campagnes[$cle]['site']       ?? '';
        $collect_id = $campagnes[$cle]['collect_id'] ?? '';
    }

    $site       = sanitize_title($site);
    $collect_id = preg_replace('/[^A-Za-z0-9]/', '', (string) $collect_id);
    if ($site === '' || $collect_id === '') return '';

    wp_enqueue_script(
        'ps-assoconnect-iframe',
        "https://{$site}.assoconnect.com/public/build/js/iframe.js",
        [], null, true // en pied de page, comme demandé par AssoConnect
    );

    return '<div class="iframe-asc-container" data-type="collect" data-collect-id="' . esc_attr($collect_id) . '"></div>';
}
add_shortcode('assoconnect', 'ps_assoconnect_shortcode');

/* ═══════════════════════════════════════════════════════════
   3. ADMIN — Apparence → Campagnes AssoConnect
   ═══════════════════════════════════════════════════════════ */

add_action('admin_menu', function () {
    add_theme_page(
        __('Campagnes AssoConnect', 'poivre-sens'),
        __('Campagnes AssoConnect', 'poivre-sens'),
        'manage_options',
        'ps-assoconnect',
        'ps_assoconnect_admin_page'
    );
});

/** Lit et enregistre le formulaire d'ajout/modification d'une campagne.
 *  Renvoie un message de résultat (ou null si aucun formulaire soumis). */
function ps_assoconnect_traiter_soumission() {
    if (isset($_POST['ps_assoconnect_enregistrer'])) {
        check_admin_referer('ps_assoconnect_enregistrer');

        $cle = sanitize_title(wp_unslash($_POST['cle'] ?? ''));
        if ($cle === '') {
            return 'erreur';
        }

        $campagnes = ps_assoconnect_campagnes();
        $campagnes[$cle] = [
            'label'      => sanitize_text_field(wp_unslash($_POST['label'] ?? $cle)),
            'site'       => sanitize_title(wp_unslash($_POST['site'] ?? '')),
            'collect_id' => preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_POST['collect_id'] ?? '')),
        ];
        update_option('ps_assoconnect_campagnes', $campagnes);

        if (!empty($_POST['par_defaut'])) {
            update_option('ps_assoconnect_campagne_defaut', $cle);
        }

        return 'enregistre';
    }

    if (isset($_GET['ps_assoconnect_supprimer']) && current_user_can('manage_options')) {
        $cle = sanitize_title(wp_unslash($_GET['ps_assoconnect_supprimer']));
        check_admin_referer('ps_assoconnect_supprimer_' . $cle);

        $campagnes = ps_assoconnect_campagnes();
        unset($campagnes[$cle]);
        update_option('ps_assoconnect_campagnes', $campagnes);

        if (ps_assoconnect_campagne_defaut_cle() === $cle) {
            delete_option('ps_assoconnect_campagne_defaut');
        }

        return 'supprime';
    }

    return null;
}

function ps_assoconnect_admin_page() {
    if (!current_user_can('manage_options')) return;

    $resultat  = ps_assoconnect_traiter_soumission();
    $campagnes = ps_assoconnect_campagnes();
    $defaut    = ps_assoconnect_campagne_defaut_cle();

    // Pré-remplissage du formulaire en mode « modifier »
    $modifier         = sanitize_title(wp_unslash($_GET['modifier'] ?? ''));
    $edite            = $campagnes[$modifier] ?? null;
    $cle_champ        = $edite ? $modifier : '';
    $label_champ      = $edite['label']      ?? '';
    $site_champ       = $edite['site']       ?? 'compagnie-poivresens';
    $collect_id_champ = $edite['collect_id'] ?? '';
    ?>
    <div class="wrap">
        <h1><?php _e('Campagnes AssoConnect', 'poivre-sens'); ?></h1>
        <p style="max-width:760px;color:#555">
            <?php _e('Chaque campagne (adhésion, dons…) enregistrée ici devient utilisable partout sur le site via <code>[assoconnect campagne="clé"]</code>. La campagne marquée « par défaut » est celle utilisée quand le shortcode <code>[assoconnect]</code> est écrit seul — pratique pour l\'adhésion annuelle, dont l\'identifiant change chaque année : il suffit de le mettre à jour ici, sans modifier aucune page. L\'identifiant à copier est le <code>data-collect-id</code> donné par AssoConnect (Formulaire de campagne › Afficher sur un site externe).', 'poivre-sens'); ?>
        </p>

        <?php if ($resultat === 'enregistre'): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Campagne enregistrée.', 'poivre-sens'); ?></p></div>
        <?php elseif ($resultat === 'supprime'): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Campagne supprimée.', 'poivre-sens'); ?></p></div>
        <?php elseif ($resultat === 'erreur'): ?>
        <div class="notice notice-error is-dismissible"><p><?php _e('La clé de la campagne est obligatoire.', 'poivre-sens'); ?></p></div>
        <?php endif; ?>

        <?php if ($campagnes): ?>
        <table class="widefat striped" style="max-width:960px;margin:20px 0">
            <thead>
                <tr>
                    <th><?php _e('Clé', 'poivre-sens'); ?></th>
                    <th><?php _e('Libellé', 'poivre-sens'); ?></th>
                    <th><?php _e('Sous-domaine / identifiant', 'poivre-sens'); ?></th>
                    <th><?php _e('Shortcode', 'poivre-sens'); ?></th>
                    <th><?php _e('Par défaut', 'poivre-sens'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campagnes as $cle => $c): ?>
                <tr>
                    <td><code><?= esc_html($cle) ?></code></td>
                    <td><?= esc_html($c['label'] ?? '') ?></td>
                    <td style="font-size:12px;color:#666"><?= esc_html(($c['site'] ?? '') . ' / ' . ($c['collect_id'] ?? '')) ?></td>
                    <td><code>[assoconnect campagne="<?= esc_html($cle) ?>"]</code></td>
                    <td><?= $cle === $defaut ? '✓' : '' ?></td>
                    <td>
                        <a href="<?= esc_url(add_query_arg(['page' => 'ps-assoconnect', 'modifier' => $cle], admin_url('themes.php'))) ?>"><?php _e('Modifier', 'poivre-sens'); ?></a>
                        &nbsp;·&nbsp;
                        <a href="<?= esc_url(wp_nonce_url(add_query_arg(['page' => 'ps-assoconnect', 'ps_assoconnect_supprimer' => $cle], admin_url('themes.php')), 'ps_assoconnect_supprimer_' . $cle)) ?>"
                           onclick="return confirm('<?= esc_js(__('Supprimer cette campagne ?', 'poivre-sens')) ?>')" style="color:#a00">
                            <?php _e('Supprimer', 'poivre-sens'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h2><?= $edite ? esc_html__('Modifier la campagne', 'poivre-sens') : esc_html__('Ajouter une campagne', 'poivre-sens') ?></h2>
        <form method="post" style="max-width:640px">
            <?php wp_nonce_field('ps_assoconnect_enregistrer'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="ps-asc-cle"><?php _e('Clé', 'poivre-sens'); ?></label></th>
                    <td>
                        <input type="text" id="ps-asc-cle" name="cle" value="<?= esc_attr($cle_champ) ?>" class="regular-text" required <?= $edite ? 'readonly' : '' ?>>
                        <p class="description"><?php _e('Identifiant court utilisé dans le shortcode, ex. « adhesion », « dons ». Ne peut plus être changé une fois créé (supprimez et recréez si besoin).', 'poivre-sens'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps-asc-label"><?php _e('Libellé', 'poivre-sens'); ?></label></th>
                    <td><input type="text" id="ps-asc-label" name="label" value="<?= esc_attr($label_champ) ?>" class="regular-text" placeholder="<?php esc_attr_e('Adhésion 2026-2027…', 'poivre-sens'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="ps-asc-site"><?php _e('Sous-domaine AssoConnect', 'poivre-sens'); ?></label></th>
                    <td>
                        <input type="text" id="ps-asc-site" name="site" value="<?= esc_attr($site_champ) ?>" class="regular-text">
                        <p class="description"><?php _e('La partie avant « .assoconnect.com » dans l\'adresse de votre espace (ex. « compagnie-poivresens »).', 'poivre-sens'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps-asc-collect"><?php _e('Identifiant de la campagne (data-collect-id)', 'poivre-sens'); ?></label></th>
                    <td>
                        <input type="text" id="ps-asc-collect" name="collect_id" value="<?= esc_attr($collect_id_champ) ?>" class="regular-text">
                        <p class="description"><?php _e('Dans AssoConnect : Formulaire de campagne › Afficher le formulaire sur un site externe › copiez la valeur de data-collect-id.', 'poivre-sens'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Par défaut', 'poivre-sens'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="par_defaut" value="1" <?= checked($cle_champ !== '' && $cle_champ === $defaut, true, false) ?>>
                            <?php _e('Utiliser cette campagne quand le shortcode [assoconnect] est écrit sans attribut campagne=', 'poivre-sens'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="ps_assoconnect_enregistrer" value="1" class="button button-primary">
                    <?php _e('Enregistrer la campagne', 'poivre-sens'); ?>
                </button>
                <?php if ($edite): ?>
                <a href="<?= esc_url(add_query_arg(['page' => 'ps-assoconnect'], admin_url('themes.php'))) ?>" class="button"><?php _e('Annuler', 'poivre-sens'); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <?php
}
