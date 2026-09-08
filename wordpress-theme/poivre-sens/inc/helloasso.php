<?php
/**
 * inc/helloasso.php
 *
 * Widgets de paiement HelloAsso (adhésions, dons…), intégrés via un shortcode
 * plutôt qu'en collant l'iframe brute dans chaque page :
 *   - le bloc « HTML personnalisé » de l'éditeur passe par wp_kses_post pour
 *     tout compte sans capacité unfiltered_html (rédacteurs, auteurs…), qui
 *     retire les balises <iframe> — le shortcode, lui, génère son HTML côté
 *     PHP, donc s'affiche pour tout le monde ;
 *   - les campagnes HelloAsso (adhésion, dons…) se gèrent depuis Apparence
 *     → Campagnes HelloAsso, sans toucher au code : la Compagnie ouvre une
 *     nouvelle campagne d'adhésion chaque année, il suffit de mettre à jour
 *     son identifiant à un seul endroit pour que tout le site suive.
 *
 * Usage (bloc « Shortcode » de l'éditeur) :
 *   [helloasso]                        → campagne par défaut, widget complet
 *   [helloasso campagne="dons"]        → une autre campagne enregistrée
 *   [helloasso campagne="dons" bouton="1"]  → sa version bouton compact (70px)
 *   [helloasso slug="don-libre" type="collectes"]
 *       → campagne ponctuelle, sans l'enregistrer dans les réglages
 */
defined('ABSPATH') || exit;

/* ═══════════════════════════════════════════════════════════
   1. STOCKAGE DES CAMPAGNES — option ps_helloasso_campagnes
   ═══════════════════════════════════════════════════════════ */

/** Campagne pré-remplie à la première utilisation (celle déjà en ligne). */
function ps_helloasso_campagnes_defaut() {
    return [
        'adhesion' => [
            'label' => __('Adhésion', 'poivre-sens'),
            'assoc' => 'compagnie-poivre-sens',
            'type'  => 'adhesions',
            'slug'  => 'adhesion-compagnie-poivre-et-sens',
        ],
    ];
}

/** Toutes les campagnes enregistrées, clé => [label, assoc, type, slug]. */
function ps_helloasso_campagnes() {
    $campagnes = get_option('ps_helloasso_campagnes', ps_helloasso_campagnes_defaut());
    return is_array($campagnes) ? $campagnes : [];
}

/** Clé de la campagne utilisée quand le shortcode n'en précise aucune. */
function ps_helloasso_campagne_defaut_cle() {
    return (string) get_option('ps_helloasso_campagne_defaut', 'adhesion');
}

/* ═══════════════════════════════════════════════════════════
   2. SHORTCODE
   ═══════════════════════════════════════════════════════════ */

/** Petit avertissement, visible des seuls administrateurs, quand une
 *  campagne demandée est introuvable — pour repérer une clé mal orthographiée
 *  sans qu'un visiteur ne voie jamais de message d'erreur. */
function ps_helloasso_avertissement($texte) {
    return current_user_can('manage_options')
        ? '<p style="color:#a00;font-size:13px;border:1px dashed #a00;padding:8px 12px">⚠ ' . esc_html($texte) . '</p>'
        : '';
}

function ps_helloasso_shortcode($atts) {
    $atts = shortcode_atts([
        'campagne' => '',   // clé d'une campagne enregistrée (Apparence → Campagnes HelloAsso)
        'assoc'    => 'compagnie-poivre-sens',
        'type'     => 'adhesions',
        'slug'     => '',   // identifiant HelloAsso brut, pour une campagne ponctuelle non enregistrée
        'bouton'   => '0',
        'hauteur'  => '750',
    ], $atts, 'helloasso');

    $campagnes = ps_helloasso_campagnes();

    if ($atts['campagne'] !== '') {
        $cle = sanitize_title($atts['campagne']);
        if (!isset($campagnes[$cle])) {
            return ps_helloasso_avertissement(sprintf(
                /* translators: %s: clé de campagne recherchée */
                __('HelloAsso : aucune campagne enregistrée avec la clé « %s ». Vérifiez Apparence → Campagnes HelloAsso.', 'poivre-sens'),
                $cle
            ));
        }
        $assoc = $campagnes[$cle]['assoc'] ?? '';
        $type  = $campagnes[$cle]['type']  ?? '';
        $slug  = $campagnes[$cle]['slug']  ?? '';
    } elseif ($atts['slug'] !== '') {
        $assoc = $atts['assoc'];
        $type  = $atts['type'];
        $slug  = $atts['slug'];
    } else {
        $cle = sanitize_title(ps_helloasso_campagne_defaut_cle());
        if (!isset($campagnes[$cle])) {
            return ps_helloasso_avertissement(__('HelloAsso : aucune campagne par défaut configurée. Réglez-en une dans Apparence → Campagnes HelloAsso, ou précisez l\'attribut slug= du shortcode.', 'poivre-sens'));
        }
        $assoc = $campagnes[$cle]['assoc'] ?? '';
        $type  = $campagnes[$cle]['type']  ?? '';
        $slug  = $campagnes[$cle]['slug']  ?? '';
    }

    $assoc = sanitize_title($assoc);
    $type  = sanitize_title($type);
    $slug  = sanitize_title($slug);
    if ($assoc === '' || $type === '' || $slug === '') return '';

    $bouton  = filter_var($atts['bouton'], FILTER_VALIDATE_BOOLEAN);
    $hauteur = max(1, (int) $atts['hauteur']);
    $base    = "https://www.helloasso.com/associations/{$assoc}/{$type}/{$slug}";

    if ($bouton) {
        return '<iframe allowtransparency="true" src="' . esc_url($base . '/widget-bouton') . '" '
             . 'style="width:100%;height:70px;border:none" '
             . 'title="' . esc_attr__('Paiement HelloAsso', 'poivre-sens') . '"></iframe>';
    }

    $id = 'ha-widget-' . wp_unique_id();
    ob_start();
    ?>
    <iframe id="<?= esc_attr($id) ?>"
        allow="payment 'self' https://paymenthub.helloassopay.com https://helloasso.com"
        allowtransparency="true" scrolling="auto"
        src="<?= esc_url($base . '/widget') ?>"
        style="width:100%;height:<?= (int) $hauteur ?>px;border:none"
        title="<?= esc_attr__('Paiement HelloAsso', 'poivre-sens') ?>"></iframe>
    <script>
    (function () {
        var frame = document.getElementById(<?= wp_json_encode($id) ?>);
        if (!frame) return;
        // HelloAsso annonce sa vraie hauteur (formulaire dépliable) via postMessage
        // une fois chargé : sans ce redimensionnement, le widget serait tronqué.
        window.addEventListener('message', function (e) {
            if (e.source !== frame.contentWindow) return;
            if (!e.data || typeof e.data.height === 'undefined') return;
            var h = parseFloat(e.data.height);
            if (h > parseFloat(frame.style.height || 0)) frame.style.height = h + 'px';
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('helloasso', 'ps_helloasso_shortcode');

/* ═══════════════════════════════════════════════════════════
   3. ADMIN — Apparence → Campagnes HelloAsso
   ═══════════════════════════════════════════════════════════ */

add_action('admin_menu', function () {
    add_theme_page(
        __('Campagnes HelloAsso', 'poivre-sens'),
        __('Campagnes HelloAsso', 'poivre-sens'),
        'manage_options',
        'ps-helloasso',
        'ps_helloasso_admin_page'
    );
});

/** Lit et enregistre le formulaire d'ajout/modification d'une campagne.
 *  Renvoie un message de résultat (ou null si aucun formulaire soumis). */
function ps_helloasso_traiter_soumission() {
    if (isset($_POST['ps_helloasso_enregistrer'])) {
        check_admin_referer('ps_helloasso_enregistrer');

        $cle = sanitize_title(wp_unslash($_POST['cle'] ?? ''));
        if ($cle === '') {
            return 'erreur';
        }

        $campagnes = ps_helloasso_campagnes();
        $campagnes[$cle] = [
            'label' => sanitize_text_field(wp_unslash($_POST['label'] ?? $cle)),
            'assoc' => sanitize_title(wp_unslash($_POST['assoc'] ?? '')),
            'type'  => sanitize_title(wp_unslash($_POST['type'] ?? '')),
            'slug'  => sanitize_title(wp_unslash($_POST['slug'] ?? '')),
        ];
        update_option('ps_helloasso_campagnes', $campagnes);

        if (!empty($_POST['par_defaut'])) {
            update_option('ps_helloasso_campagne_defaut', $cle);
        }

        return 'enregistre';
    }

    if (isset($_GET['ps_helloasso_supprimer']) && current_user_can('manage_options')) {
        $cle = sanitize_title(wp_unslash($_GET['ps_helloasso_supprimer']));
        check_admin_referer('ps_helloasso_supprimer_' . $cle);

        $campagnes = ps_helloasso_campagnes();
        unset($campagnes[$cle]);
        update_option('ps_helloasso_campagnes', $campagnes);

        if (ps_helloasso_campagne_defaut_cle() === $cle) {
            delete_option('ps_helloasso_campagne_defaut');
        }

        return 'supprime';
    }

    return null;
}

function ps_helloasso_admin_page() {
    if (!current_user_can('manage_options')) return;

    $resultat  = ps_helloasso_traiter_soumission();
    $campagnes = ps_helloasso_campagnes();
    $defaut    = ps_helloasso_campagne_defaut_cle();

    // Pré-remplissage du formulaire en mode « modifier »
    $modifier    = sanitize_title(wp_unslash($_GET['modifier'] ?? ''));
    $edite       = $campagnes[$modifier] ?? null;
    $cle_champ   = $edite ? $modifier : '';
    $label_champ = $edite['label'] ?? '';
    $assoc_champ = $edite['assoc'] ?? 'compagnie-poivre-sens';
    $type_champ  = $edite['type']  ?? 'adhesions';
    $slug_champ  = $edite['slug']  ?? '';
    ?>
    <div class="wrap">
        <h1><?php _e('Campagnes HelloAsso', 'poivre-sens'); ?></h1>
        <p style="max-width:760px;color:#555">
            <?php _e('Chaque campagne (adhésion, dons…) enregistrée ici devient utilisable partout sur le site via <code>[helloasso campagne="clé"]</code>. La campagne marquée « par défaut » est celle utilisée quand le shortcode <code>[helloasso]</code> est écrit seul — pratique pour l\'adhésion annuelle, dont l\'identifiant change chaque année : il suffit de le mettre à jour ici, sans modifier aucune page.', 'poivre-sens'); ?>
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
                    <th><?php _e('Association / type / campagne', 'poivre-sens'); ?></th>
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
                    <td style="font-size:12px;color:#666"><?= esc_html(($c['assoc'] ?? '') . ' / ' . ($c['type'] ?? '') . ' / ' . ($c['slug'] ?? '')) ?></td>
                    <td><code>[helloasso campagne="<?= esc_html($cle) ?>"]</code></td>
                    <td><?= $cle === $defaut ? '✓' : '' ?></td>
                    <td>
                        <a href="<?= esc_url(add_query_arg(['page' => 'ps-helloasso', 'modifier' => $cle], admin_url('themes.php'))) ?>"><?php _e('Modifier', 'poivre-sens'); ?></a>
                        &nbsp;·&nbsp;
                        <a href="<?= esc_url(wp_nonce_url(add_query_arg(['page' => 'ps-helloasso', 'ps_helloasso_supprimer' => $cle], admin_url('themes.php')), 'ps_helloasso_supprimer_' . $cle)) ?>"
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
            <?php wp_nonce_field('ps_helloasso_enregistrer'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="ps-ha-cle"><?php _e('Clé', 'poivre-sens'); ?></label></th>
                    <td>
                        <input type="text" id="ps-ha-cle" name="cle" value="<?= esc_attr($cle_champ) ?>" class="regular-text" required <?= $edite ? 'readonly' : '' ?>>
                        <p class="description"><?php _e('Identifiant court utilisé dans le shortcode, ex. « adhesion », « dons ». Ne peut plus être changé une fois créé (supprimez et recréez si besoin).', 'poivre-sens'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps-ha-label"><?php _e('Libellé', 'poivre-sens'); ?></label></th>
                    <td><input type="text" id="ps-ha-label" name="label" value="<?= esc_attr($label_champ) ?>" class="regular-text" placeholder="<?php esc_attr_e('Adhésion 2026-2027…', 'poivre-sens'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="ps-ha-assoc"><?php _e('Association (URL HelloAsso)', 'poivre-sens'); ?></label></th>
                    <td><input type="text" id="ps-ha-assoc" name="assoc" value="<?= esc_attr($assoc_champ) ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="ps-ha-type"><?php _e('Type', 'poivre-sens'); ?></label></th>
                    <td>
                        <input type="text" id="ps-ha-type" name="type" value="<?= esc_attr($type_champ) ?>" class="regular-text">
                        <p class="description"><?php _e('« adhesions », « collectes » (dons), « evenements » (billetterie), « ventes »… — visible dans l\'URL du widget fournie par HelloAsso.', 'poivre-sens'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps-ha-slug"><?php _e('Campagne (URL HelloAsso)', 'poivre-sens'); ?></label></th>
                    <td><input type="text" id="ps-ha-slug" name="slug" value="<?= esc_attr($slug_champ) ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php _e('Par défaut', 'poivre-sens'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="par_defaut" value="1" <?= checked($cle_champ !== '' && $cle_champ === $defaut, true, false) ?>>
                            <?php _e('Utiliser cette campagne quand le shortcode [helloasso] est écrit sans attribut campagne=', 'poivre-sens'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="ps_helloasso_enregistrer" value="1" class="button button-primary">
                    <?php _e('Enregistrer la campagne', 'poivre-sens'); ?>
                </button>
                <?php if ($edite): ?>
                <a href="<?= esc_url(add_query_arg(['page' => 'ps-helloasso'], admin_url('themes.php'))) ?>" class="button"><?php _e('Annuler', 'poivre-sens'); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <?php
}
