<?php
/**
 * Poivre & Sens — Interface Newsletter (MailPoet-like)
 * Gestion complète abonnés + campagnes depuis l'admin WP
 */
defined('ABSPATH') || exit;

/* ═══════════════════════════════════════════════════════════
   TABLES DATABASE
   ═══════════════════════════════════════════════════════════ */
function ps_create_all_newsletter_tables() {
    global $wpdb;
    $cs = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Abonnés
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ps_newsletter (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email         VARCHAR(255)    NOT NULL,
        prenom        VARCHAR(100)    NOT NULL DEFAULT '',
        nom           VARCHAR(100)    NOT NULL DEFAULT '',
        statut        ENUM('actif','desabonne','en_attente') NOT NULL DEFAULT 'actif',
        token         VARCHAR(64)     NOT NULL DEFAULT '',
        source        VARCHAR(100)    NOT NULL DEFAULT 'site',
        date_creation DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_confirm  DATETIME        NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_email (email)
    ) $cs;");

    // Listes (segments de prospects) — permet de localiser l'origine des abonnés
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ps_newsletter_lists (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nom           VARCHAR(150)    NOT NULL,
        slug          VARCHAR(150)    NOT NULL,
        description   VARCHAR(255)    NOT NULL DEFAULT '',
        couleur       VARCHAR(7)      NOT NULL DEFAULT '#c28b36',
        date_creation DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_slug (slug)
    ) $cs;");

    // Appartenance abonné ⇄ liste (plusieurs listes par abonné)
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ps_newsletter_subscriber_lists (
        subscriber_id BIGINT UNSIGNED NOT NULL,
        list_id       BIGINT UNSIGNED NOT NULL,
        date_ajout    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (subscriber_id, list_id),
        KEY k_list (list_id)
    ) $cs;");

    // Campagnes
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ps_newsletter_campaigns (
        id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sujet          VARCHAR(255)    NOT NULL,
        preheader      VARCHAR(255)    NOT NULL DEFAULT '',
        contenu_html   LONGTEXT        NOT NULL,
        contenu_texte  LONGTEXT        NOT NULL DEFAULT '',
        from_nom       VARCHAR(100)    NOT NULL DEFAULT '',
        from_email     VARCHAR(255)    NOT NULL DEFAULT '',
        target_lists   VARCHAR(255)    NOT NULL DEFAULT '',
        statut         ENUM('brouillon','envoi_en_cours','envoye','erreur') NOT NULL DEFAULT 'brouillon',
        envoye_le      DATETIME        NULL,
        nb_envoyes     INT UNSIGNED    NOT NULL DEFAULT 0,
        nb_ouverts     INT UNSIGNED    NOT NULL DEFAULT 0,
        cree_le        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modifie_le     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $cs;");

    // Envois individuels (pour stats + déduplication)
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ps_newsletter_sends (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id  BIGINT UNSIGNED NOT NULL,
        subscriber_id BIGINT UNSIGNED NOT NULL,
        email        VARCHAR(255)    NOT NULL,
        envoye_le    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ouvert_le    DATETIME        NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_send (campaign_id, subscriber_id),
        KEY k_campaign (campaign_id),
        KEY k_subscriber (subscriber_id)
    ) $cs;");
}

/**
 * Migration / mise à niveau du schéma newsletter.
 * Crée les tables manquantes, ajoute les colonnes absentes sur d'anciennes
 * installations (nom, source, target_lists) et sème les listes par défaut.
 */
function ps_nl_upgrade_db() {
    global $wpdb;
    ps_create_all_newsletter_tables();

    // Colonnes ajoutées après coup sur la table abonnés (anciennes installs)
    $ts   = $wpdb->prefix . 'ps_newsletter';
    $cols = $wpdb->get_col("DESC $ts", 0);
    if ($cols) {
        if (!in_array('nom', $cols, true))    $wpdb->query("ALTER TABLE $ts ADD COLUMN nom VARCHAR(100) NOT NULL DEFAULT '' AFTER prenom");
        if (!in_array('source', $cols, true)) $wpdb->query("ALTER TABLE $ts ADD COLUMN source VARCHAR(100) NOT NULL DEFAULT 'site' AFTER token");
    }
    // Colonne cible sur les campagnes
    $tc    = $wpdb->prefix . 'ps_newsletter_campaigns';
    $ccols = $wpdb->get_col("DESC $tc", 0);
    if ($ccols && !in_array('target_lists', $ccols, true)) {
        $wpdb->query("ALTER TABLE $tc ADD COLUMN target_lists VARCHAR(255) NOT NULL DEFAULT '' AFTER from_email");
    }

    ps_nl_seed_default_lists();
}

/** Sème les listes par défaut si aucune n'existe encore. */
function ps_nl_seed_default_lists() {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $tl") > 0) return;

    $defauts = [
        ['nom' => __('Site web', 'poivre-sens'),             'slug' => 'site',             'description' => __('Inscriptions via le site cie.poivresens.fr', 'poivre-sens'), 'couleur' => '#c28b36'],
        ['nom' => __('Grand Bal de l\'Europe', 'poivre-sens'), 'slug' => 'grand-bal-europe', 'description' => __('Prospects rencontrés au Grand Bal de l\'Europe', 'poivre-sens'), 'couleur' => '#9C3E1C'],
    ];
    foreach ($defauts as $d) {
        $wpdb->insert($tl, $d + ['date_creation' => current_time('mysql')]);
    }
}

/* ═══════════════════════════════════════════════════════════
   MODÈLE : LISTES DE PROSPECTS  (API interne)
   ═══════════════════════════════════════════════════════════ */

/** Toutes les listes, avec le nombre d'abonnés actifs par liste. */
function ps_nl_get_lists() {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    $tj = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    $ts = $wpdb->prefix . 'ps_newsletter';
    return $wpdb->get_results("
        SELECT l.*,
               (SELECT COUNT(*) FROM $tj j INNER JOIN $ts s ON s.id=j.subscriber_id
                 WHERE j.list_id=l.id AND s.statut='actif') AS nb_actifs,
               (SELECT COUNT(*) FROM $tj j WHERE j.list_id=l.id) AS nb_total
        FROM $tl l ORDER BY l.nom ASC");
}

/** Une liste par son id. */
function ps_nl_get_list($id) {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tl WHERE id=%d", (int)$id));
}

/** Une liste par son slug. */
function ps_nl_get_list_by_slug($slug) {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tl WHERE slug=%s", $slug));
}

/** Ids des listes d'un abonné. */
function ps_nl_get_subscriber_list_ids($subscriber_id) {
    global $wpdb;
    $tj = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT list_id FROM $tj WHERE subscriber_id=%d", (int)$subscriber_id)));
}

/** Objets liste d'un abonné (id, nom, couleur, slug). */
function ps_nl_get_subscriber_lists($subscriber_id) {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    $tj = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT l.id, l.nom, l.slug, l.couleur FROM $tl l
         INNER JOIN $tj j ON j.list_id = l.id
         WHERE j.subscriber_id = %d ORDER BY l.nom ASC", (int)$subscriber_id));
}

/** Ajoute un abonné à une liste (idempotent). */
function ps_nl_add_subscriber_to_list($subscriber_id, $list_id) {
    global $wpdb;
    $tj = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    if (!$subscriber_id || !$list_id) return;
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $tj (subscriber_id, list_id, date_ajout) VALUES (%d, %d, %s)",
        (int)$subscriber_id, (int)$list_id, current_time('mysql')
    ));
}

/** Remplace intégralement les listes d'un abonné. */
function ps_nl_set_subscriber_lists($subscriber_id, array $list_ids) {
    global $wpdb;
    $tj = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    $wpdb->delete($tj, ['subscriber_id' => (int)$subscriber_id]);
    foreach (array_unique(array_map('intval', $list_ids)) as $lid) {
        if ($lid > 0) ps_nl_add_subscriber_to_list($subscriber_id, $lid);
    }
}

/** Normalise une valeur de ciblage (« 2,5 » ou tableau) en tableau d'ids. */
function ps_nl_parse_target($target) {
    if (is_array($target)) $ids = $target;
    else $ids = array_filter(array_map('trim', explode(',', (string)$target)), 'strlen');
    return array_values(array_unique(array_map('intval', $ids)));
}

/**
 * Abonnés actifs correspondant à un ciblage de listes.
 * Ciblage vide => tous les abonnés actifs (comportement historique).
 */
function ps_nl_get_active_subscribers($target = '') {
    global $wpdb;
    $ts  = $wpdb->prefix . 'ps_newsletter';
    $tj  = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    $ids = ps_nl_parse_target($target);
    if (!$ids) {
        return $wpdb->get_results("SELECT * FROM $ts WHERE statut='actif'");
    }
    $in = implode(',', array_map('intval', $ids));
    return $wpdb->get_results("SELECT DISTINCT s.* FROM $ts s
        INNER JOIN $tj j ON j.subscriber_id = s.id
        WHERE s.statut='actif' AND j.list_id IN ($in)");
}

/** Nombre d'abonnés actifs pour un ciblage donné. */
function ps_nl_count_active($target = '') {
    global $wpdb;
    $ts  = $wpdb->prefix . 'ps_newsletter';
    $tj  = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    $ids = ps_nl_parse_target($target);
    if (!$ids) return (int)$wpdb->get_var("SELECT COUNT(*) FROM $ts WHERE statut='actif'");
    $in = implode(',', array_map('intval', $ids));
    return (int)$wpdb->get_var("SELECT COUNT(DISTINCT s.id) FROM $ts s
        INNER JOIN $tj j ON j.subscriber_id = s.id
        WHERE s.statut='actif' AND j.list_id IN ($in)");
}

/* ═══════════════════════════════════════════════════════════
   MENU ADMIN — STRUCTURE PRINCIPALE
   ═══════════════════════════════════════════════════════════ */
add_action('admin_menu', function () {
    // Menu principal "Newsletter"
    add_menu_page(
        __('Poivre & Sens Newsletter', 'poivre-sens'),
        __('Newsletter', 'poivre-sens'),
        'manage_options',
        'ps-newsletter',
        'ps_nl_page_dispatch',
        'dashicons-email-alt',
        30
    );
    // Sous-menus
    add_submenu_page('ps-newsletter', __('Tableau de bord', 'poivre-sens'), __('Tableau de bord', 'poivre-sens'), 'manage_options', 'ps-newsletter', 'ps_nl_page_dispatch');
    add_submenu_page('ps-newsletter', __('Abonnés',        'poivre-sens'), __('Abonnés',        'poivre-sens'), 'manage_options', 'ps-nl-abonnes',    'ps_nl_page_dispatch');
    add_submenu_page('ps-newsletter', __('Listes',         'poivre-sens'), __('Listes',         'poivre-sens'), 'manage_options', 'ps-nl-listes',     'ps_nl_page_dispatch');
    add_submenu_page('ps-newsletter', __('Campagnes',      'poivre-sens'), __('Campagnes',      'poivre-sens'), 'manage_options', 'ps-nl-campagnes',  'ps_nl_page_dispatch');
    add_submenu_page('ps-newsletter', __('Nouvelle campagne', 'poivre-sens'), __('Nouvelle campagne', 'poivre-sens'), 'manage_options', 'ps-nl-nouvelle-campagne', 'ps_nl_page_dispatch');
    add_submenu_page('ps-newsletter', __('Réglages',        'poivre-sens'), __('Réglages',        'poivre-sens'), 'manage_options', 'ps-nl-reglages',   'ps_nl_page_dispatch');
});

/** Dispatcher : redirige vers la bonne page selon page=  */
function ps_nl_page_dispatch() {
    $page = $_GET['page'] ?? 'ps-newsletter';
    switch ($page) {
        case 'ps-nl-abonnes':           ps_nl_page_abonnes();           break;
        case 'ps-nl-listes':            ps_nl_page_listes();            break;
        case 'ps-nl-campagnes':         ps_nl_page_campagnes();         break;
        case 'ps-nl-nouvelle-campagne': ps_nl_page_nouvelle_campagne(); break;
        case 'ps-nl-reglages':          ps_nl_page_reglages();          break;
        default:                        ps_nl_page_dashboard();         break;
    }
}

/* ─── CSS commun admin ───────────────────────────────────── */
add_action('admin_head', function () {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'ps-n') === false) return;
    ?>
    <style>
    :root{--ps-or:#c28b36;--ps-brun:#2a1208;--ps-noir:#080705;--ps-gris:#7f7463}
    .ps-wrap{max-width:1100px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .ps-tabs{display:flex;gap:0;border-bottom:2px solid var(--ps-or);margin-bottom:28px}
    .ps-tab{padding:10px 22px;font-size:13px;font-weight:500;color:#666;text-decoration:none;border:1px solid transparent;border-bottom:none;margin-bottom:-2px;border-radius:4px 4px 0 0;transition:all .2s}
    .ps-tab:hover{color:var(--ps-or);background:#fdf9f3}
    .ps-tab.active{color:var(--ps-or);background:#fff;border-color:var(--ps-or);border-bottom-color:#fff}
    .ps-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
    .ps-stat{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:20px 24px;border-top:3px solid var(--ps-or)}
    .ps-stat__n{font-size:2.2rem;font-weight:700;color:var(--ps-brun);line-height:1}
    .ps-stat__l{font-size:12px;color:#888;margin-top:6px;text-transform:uppercase;letter-spacing:.08em}
    .ps-stat__sub{font-size:11px;color:#bbb;margin-top:4px}
    .ps-card{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:24px;margin-bottom:20px}
    .ps-card h3{font-size:15px;font-weight:600;color:#333;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0}
    .ps-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:4px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:all .2s}
    .ps-btn-primary{background:var(--ps-or);color:#fff}.ps-btn-primary:hover{background:#a87830;color:#fff}
    .ps-btn-outline{background:#fff;color:var(--ps-or);border:1px solid var(--ps-or)}.ps-btn-outline:hover{background:#fdf9f3;color:var(--ps-or)}
    .ps-btn-danger{background:#c00;color:#fff}.ps-btn-danger:hover{background:#a00;color:#fff}
    .ps-btn-sm{padding:5px 12px;font-size:12px}
    .ps-btn-grey{background:#f0f0f0;color:#555;border:1px solid #ddd}.ps-btn-grey:hover{background:#e5e5e5}
    .ps-table{width:100%;border-collapse:collapse;font-size:13px}
    .ps-table th{background:#f8f8f8;padding:10px 14px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e5e5e5;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
    .ps-table td{padding:10px 14px;border-bottom:1px solid #f0f0f0;color:#333;vertical-align:middle}
    .ps-table tr:hover td{background:#fafafa}
    .ps-table .actions{display:flex;gap:6px;flex-wrap:wrap}
    .ps-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.04em}
    .ps-badge-actif{background:#d4edda;color:#155724}
    .ps-badge-desabonne{background:#f0f0f0;color:#888}
    .ps-badge-en_attente{background:#fff3cd;color:#856404}
    .ps-badge-brouillon{background:#e2e3e5;color:#383d41}
    .ps-badge-envoye{background:#d4edda;color:#155724}
    .ps-badge-envoi_en_cours{background:#fff3cd;color:#856404}
    .ps-badge-erreur{background:#f8d7da;color:#721c24}
    .ps-search-bar{display:flex;gap:10px;align-items:center;margin-bottom:16px}
    .ps-search-bar input,.ps-search-bar select{padding:7px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px}
    .ps-search-bar input{min-width:240px}
    .ps-pagination{display:flex;gap:4px;justify-content:center;margin-top:20px;align-items:center}
    .ps-pagination a,.ps-pagination span{padding:6px 12px;border:1px solid #ddd;border-radius:3px;font-size:13px;text-decoration:none;color:#333}
    .ps-pagination a:hover{background:#fdf9f3;border-color:var(--ps-or);color:var(--ps-or)}
    .ps-pagination .current{background:var(--ps-or);color:#fff;border-color:var(--ps-or)}
    .ps-form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
    .ps-form-row.full{grid-template-columns:1fr}
    .ps-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    .ps-field input,.ps-field select,.ps-field textarea{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px}
    .ps-field .help{font-size:11px;color:#aaa;margin-top:4px}
    .ps-notice{padding:12px 16px;border-radius:4px;margin-bottom:16px;font-size:13px;border-left:4px solid}
    .ps-notice-ok{background:#d4edda;color:#155724;border-color:#28a745}
    .ps-notice-err{background:#f8d7da;color:#721c24;border-color:#dc3545}
    .ps-notice-warn{background:#fff3cd;color:#856404;border-color:#ffc107}
    .ps-editor-wrap{border:1px solid #ddd;border-radius:4px;overflow:hidden}
    .ps-progress{height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden;margin-top:8px}
    .ps-progress-bar{height:100%;background:var(--ps-or);border-radius:4px;transition:width .5s}
    .ps-chart{display:flex;gap:2px;align-items:flex-end;height:60px;margin-top:8px}
    .ps-chart-bar{flex:1;background:rgba(194,139,54,.3);border-radius:2px 2px 0 0;min-height:2px;transition:background .2s}
    .ps-chart-bar:hover{background:var(--ps-or)}
    .ps-toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap}
    .ps-checkbox-all{cursor:pointer}
    .ps-bulk-bar{display:none;padding:10px 14px;background:#fdf9f3;border:1px solid #e8d5a3;border-radius:4px;margin-bottom:12px;font-size:13px;align-items:center;gap:12px}
    .ps-bulk-bar.visible{display:flex}
    </style>
    <?php
});

/* ═══════════════════════════════════════════════════════════
   PAGE : TABLEAU DE BORD
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_dashboard() {
    global $wpdb;
    $ts = $wpdb->prefix . 'ps_newsletter';
    $tc = $wpdb->prefix . 'ps_newsletter_campaigns';

    $actifs       = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ts WHERE statut='actif'");
    $total_abonnes = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ts");
    $desabonnes   = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ts WHERE statut='desabonne'");
    $campagnes    = (int)$wpdb->get_var("SELECT COUNT(*) FROM $tc WHERE statut='envoye'");
    $last_camp    = $wpdb->get_row("SELECT sujet, envoye_le, nb_envoyes, nb_ouverts FROM $tc WHERE statut='envoye' ORDER BY envoye_le DESC LIMIT 1");
    $recent_subs  = $wpdb->get_results("SELECT email, prenom, date_creation FROM $ts ORDER BY date_creation DESC LIMIT 8");

    // Inscriptions par mois (12 derniers mois)
    $monthly = $wpdb->get_results("
        SELECT DATE_FORMAT(date_creation,'%Y-%m') AS m, COUNT(*) AS n
        FROM $ts WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY m ORDER BY m
    ");
    $max_m = max(array_column($monthly ?: [['n'=>1]], 'n'));

    ps_nl_header(__('Tableau de bord', 'poivre-sens'), 'ps-newsletter');
    ?>
    <div class="ps-stats">
        <div class="ps-stat">
            <div class="ps-stat__n"><?= $actifs ?></div>
            <div class="ps-stat__l"><?= __('Abonnés actifs', 'poivre-sens') ?></div>
            <div class="ps-stat__sub"><?= $total_abonnes ?> <?= __('inscrits au total', 'poivre-sens') ?></div>
        </div>
        <div class="ps-stat">
            <div class="ps-stat__n"><?= $desabonnes ?></div>
            <div class="ps-stat__l"><?= __('Désabonnés', 'poivre-sens') ?></div>
            <div class="ps-stat__sub"><?= $total_abonnes ? round(100*$desabonnes/$total_abonnes).'%' : '—' ?> <?= __('du total', 'poivre-sens') ?></div>
        </div>
        <div class="ps-stat">
            <div class="ps-stat__n"><?= $campagnes ?></div>
            <div class="ps-stat__l"><?= __('Campagnes envoyées', 'poivre-sens') ?></div>
            <?php if ($last_camp): ?>
            <div class="ps-stat__sub"><?= __('Dernière :', 'poivre-sens') ?> <?= date_i18n('d/m/Y', strtotime($last_camp->envoye_le)) ?></div>
            <?php endif; ?>
        </div>
        <div class="ps-stat">
            <?php
            $total_envoyes = (int)$wpdb->get_var("SELECT SUM(nb_envoyes) FROM $tc WHERE statut='envoye'");
            $total_ouverts = (int)$wpdb->get_var("SELECT SUM(nb_ouverts) FROM $tc WHERE statut='envoye'");
            $taux = $total_envoyes ? round(100*$total_ouverts/$total_envoyes) : 0;
            ?>
            <div class="ps-stat__n"><?= $taux ?>%</div>
            <div class="ps-stat__l"><?= __('Taux d\'ouverture moyen', 'poivre-sens') ?></div>
            <div class="ps-stat__sub"><?= $total_ouverts ?>/<?= $total_envoyes ?> <?= __('e-mails ouverts', 'poivre-sens') ?></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

        <div class="ps-card">
            <h3>📈 <?= __('Nouvelles inscriptions (12 derniers mois)', 'poivre-sens') ?></h3>
            <?php if ($monthly): ?>
            <div class="ps-chart">
                <?php foreach ($monthly as $m): ?>
                <div class="ps-chart-bar" style="height:<?= $max_m ? round(100*$m->n/$max_m) : 0 ?>%;min-height:2px" title="<?= esc_attr($m->m) ?> : <?= $m->n ?> inscription(s)"></div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#bbb;margin-top:4px">
                <?php foreach ($monthly as $m): ?>
                <span><?= substr($m->m, 5) ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#aaa;font-size:13px;text-align:center;padding:20px"><?= __('Aucune donnée pour le moment.', 'poivre-sens') ?></p>
            <?php endif; ?>
        </div>

        <div>
            <div class="ps-card">
                <h3>⚡ <?= __('Actions rapides', 'poivre-sens') ?></h3>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <a href="<?= admin_url('admin.php?page=ps-nl-nouvelle-campagne') ?>" class="ps-btn ps-btn-primary">✉ <?= __('Nouvelle campagne', 'poivre-sens') ?></a>
                    <a href="<?= admin_url('admin.php?page=ps-nl-abonnes&action=add') ?>" class="ps-btn ps-btn-outline">+ <?= __('Ajouter un abonné', 'poivre-sens') ?></a>
                    <a href="<?= wp_nonce_url(admin_url('admin.php?page=ps-nl-abonnes&export=1'), 'ps_export_csv') ?>" class="ps-btn ps-btn-grey">⬇ <?= __('Exporter CSV', 'poivre-sens') ?></a>
                </div>
            </div>

            <?php if ($last_camp): ?>
            <div class="ps-card">
                <h3>📬 <?= __('Dernière campagne', 'poivre-sens') ?></h3>
                <p style="font-size:13px;font-weight:600;color:#333;margin-bottom:8px"><?= esc_html($last_camp->sujet) ?></p>
                <p style="font-size:12px;color:#888;margin-bottom:10px"><?= date_i18n(get_option('date_format'), strtotime($last_camp->envoye_le)) ?></p>
                <div style="font-size:12px;color:#555">
                    <div style="margin-bottom:6px"><?= __('Envoyés', 'poivre-sens') ?> : <strong><?= $last_camp->nb_envoyes ?></strong></div>
                    <div style="margin-bottom:6px"><?= __('Ouverts', 'poivre-sens') ?> : <strong><?= $last_camp->nb_ouverts ?></strong></div>
                    <?php $t = $last_camp->nb_envoyes ? round(100*$last_camp->nb_ouverts/$last_camp->nb_envoyes) : 0; ?>
                    <div class="ps-progress"><div class="ps-progress-bar" style="width:<?= $t ?>%"></div></div>
                    <div style="text-align:right;font-size:11px;color:#aaa;margin-top:3px"><?= $t ?>% <?= __('ouvertures', 'poivre-sens') ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="ps-card">
        <h3>🆕 <?= __('Dernières inscriptions', 'poivre-sens') ?></h3>
        <table class="ps-table">
            <thead><tr>
                <th><?= __('E-mail', 'poivre-sens') ?></th>
                <th><?= __('Prénom', 'poivre-sens') ?></th>
                <th><?= __('Inscrit le', 'poivre-sens') ?></th>
            </tr></thead>
            <tbody>
            <?php if ($recent_subs): foreach ($recent_subs as $s): ?>
            <tr>
                <td><?= esc_html($s->email) ?></td>
                <td><?= esc_html($s->prenom) ?></td>
                <td><?= date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($s->date_creation)) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3" style="text-align:center;color:#aaa;padding:24px"><?= __('Aucun abonné pour le moment.', 'poivre-sens') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if (count($recent_subs) >= 8): ?>
        <div style="text-align:right;margin-top:12px">
            <a href="<?= admin_url('admin.php?page=ps-nl-abonnes') ?>" class="ps-btn ps-btn-outline ps-btn-sm"><?= __('Voir tous les abonnés →', 'poivre-sens') ?></a>
        </div>
        <?php endif; ?>
    </div>

    </div><!-- .ps-wrap -->
    <?php
}

/* ═══════════════════════════════════════════════════════════
   PAGE : ABONNÉS
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_abonnes() {
    global $wpdb;
    $table = $wpdb->prefix . 'ps_newsletter';

    /* ── Actions POST ─────────────────────────────────────── */
    $notice = '';

    // Export CSV — filtrable par statut ET par liste
    if (isset($_GET['export']) && current_user_can('manage_options')) {
        check_admin_referer('ps_export_csv');
        $statut  = sanitize_text_field($_GET['statut'] ?? 'actif');
        $list_id = (int)($_GET['liste'] ?? 0);
        $tj      = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
        $join    = $list_id ? "INNER JOIN $tj j ON j.subscriber_id = t.id AND j.list_id = " . $list_id : '';
        $where   = $statut === 'tous' ? 'WHERE 1=1' : $wpdb->prepare('WHERE t.statut=%s', $statut);
        $rows    = $wpdb->get_results("SELECT t.id, t.email, t.prenom, t.nom, t.statut, t.source, t.date_creation FROM $table t $join $where ORDER BY t.date_creation DESC");

        // Précharge les listes de tous les abonnés exportés en une seule requête
        // (évite une requête par ligne sur un export volumineux).
        $list_names = [];
        $sub_ids = wp_list_pluck($rows, 'id');
        if ($sub_ids) {
            $tl   = $wpdb->prefix . 'ps_newsletter_lists';
            $in   = implode(',', array_map('intval', $sub_ids));
            $rels = $wpdb->get_results("SELECT j.subscriber_id, l.nom FROM $tj j INNER JOIN $tl l ON l.id = j.list_id WHERE j.subscriber_id IN ($in) ORDER BY l.nom ASC");
            foreach ($rels as $rel) $list_names[$rel->subscriber_id][] = $rel->nom;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-abonnes-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['Email', 'Prénom', 'Nom', 'Statut', 'Source', 'Listes', 'Date inscription']);
        foreach ($rows as $r) {
            $noms = implode(', ', $list_names[$r->id] ?? []);
            fputcsv($out, [(string)$r->email, (string)$r->prenom, (string)$r->nom, (string)$r->statut, (string)$r->source, $noms, (string)$r->date_creation]);
        }
        fclose($out); exit;
    }

    // Import CSV — avec affectation à une liste
    if (isset($_POST['ps_import_csv']) && check_admin_referer('ps_import_csv')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $import_list = (int)($_POST['import_list_id'] ?? 0);
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($handle); // skip header
            $imported = $skipped = $attached = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $email = sanitize_email(trim($row[0] ?? ''));
                if (!is_email($email)) { $skipped++; continue; }
                $prenom = sanitize_text_field(trim($row[1] ?? ''));
                $nom    = sanitize_text_field(trim($row[2] ?? ''));
                $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email=%s", $email));
                if ($exists) {
                    // Déjà inscrit : on ne recrée pas, mais on peut l'ajouter à la liste choisie
                    if ($import_list) { ps_nl_add_subscriber_to_list($exists, $import_list); $attached++; }
                    $skipped++; continue;
                }
                $wpdb->insert($table, ['email'=>$email,'prenom'=>$prenom,'nom'=>$nom,'statut'=>'actif','token'=>wp_generate_password(32,false),'source'=>'import','date_creation'=>current_time('mysql'),'date_confirm'=>current_time('mysql')]);
                if ($import_list) ps_nl_add_subscriber_to_list($wpdb->insert_id, $import_list);
                $imported++;
            }
            fclose($handle);
            $extra  = $attached ? ' ' . sprintf(__('(%d abonné(s) existant(s) ajouté(s) à la liste)', 'poivre-sens'), $attached) : '';
            $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('%d abonné(s) importé(s), %d ignoré(s).', 'poivre-sens'), $imported, $skipped) . $extra . '</div>';
        }
    }

    // Ajout manuel — avec affectation aux listes
    if (isset($_POST['ps_add_subscriber']) && check_admin_referer('ps_add_subscriber')) {
        $email  = sanitize_email($_POST['email'] ?? '');
        $prenom = sanitize_text_field($_POST['prenom'] ?? '');
        $nom    = sanitize_text_field($_POST['nom'] ?? '');
        $lists  = array_map('intval', (array)($_POST['lists'] ?? []));
        if (!is_email($email)) {
            $notice = '<div class="ps-notice ps-notice-err">' . __('E-mail invalide.', 'poivre-sens') . '</div>';
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email=%s", $email))) {
            $notice = '<div class="ps-notice ps-notice-warn">' . __('Cet e-mail est déjà inscrit.', 'poivre-sens') . '</div>';
        } else {
            $wpdb->insert($table, ['email'=>$email,'prenom'=>$prenom,'nom'=>$nom,'statut'=>'actif','token'=>wp_generate_password(32,false),'source'=>'admin','date_creation'=>current_time('mysql'),'date_confirm'=>current_time('mysql')]);
            if ($lists) ps_nl_set_subscriber_lists($wpdb->insert_id, $lists);
            $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('%s ajouté(e) avec succès.', 'poivre-sens'), esc_html($email)) . '</div>';
        }
    }

    // Affectation en masse à une liste
    if (isset($_POST['ps_bulk_addlist']) && check_admin_referer('ps_bulk_addlist')) {
        $ids  = array_map('intval', (array)($_POST['selected_ids'] ?? []));
        $lid  = (int)($_POST['bulk_list_id'] ?? 0);
        if ($lid) { foreach ($ids as $id) ps_nl_add_subscriber_to_list($id, $lid); }
        $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('%d abonné(s) ajouté(s) à la liste.', 'poivre-sens'), count($ids)) . '</div>';
    }

    // Suppression unitaire
    if (isset($_GET['delete_id']) && check_admin_referer('ps_del_sub_' . $_GET['delete_id'])) {
        $sid = (int)$_GET['delete_id'];
        $wpdb->delete($table, ['id' => $sid]);
        $wpdb->delete($wpdb->prefix . 'ps_newsletter_subscriber_lists', ['subscriber_id' => $sid]);
        $notice = '<div class="ps-notice ps-notice-ok">' . __('Abonné supprimé.', 'poivre-sens') . '</div>';
    }

    // Suppression en masse
    if (isset($_POST['ps_bulk_delete']) && check_admin_referer('ps_bulk_delete')) {
        $ids = array_map('intval', (array)($_POST['selected_ids'] ?? []));
        foreach ($ids as $id) {
            $wpdb->delete($table, ['id' => $id]);
            $wpdb->delete($wpdb->prefix . 'ps_newsletter_subscriber_lists', ['subscriber_id' => $id]);
        }
        $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('%d abonné(s) supprimé(s).', 'poivre-sens'), count($ids)) . '</div>';
    }

    // Changement de statut en masse
    if (isset($_POST['ps_bulk_status']) && check_admin_referer('ps_bulk_status')) {
        $ids    = array_map('intval', (array)($_POST['selected_ids'] ?? []));
        $statut = sanitize_text_field($_POST['bulk_statut'] ?? 'actif');
        foreach ($ids as $id) $wpdb->update($table, ['statut' => $statut], ['id' => $id]);
        $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('Statut de %d abonné(s) mis à jour.', 'poivre-sens'), count($ids)) . '</div>';
    }

    /* ── Recherche & filtres ─────────────────────────────── */
    $search    = sanitize_text_field($_GET['s'] ?? '');
    $filtre    = sanitize_text_field($_GET['statut'] ?? '');
    $liste_id  = (int)($_GET['liste'] ?? 0);
    $per_page  = 25;
    $paged     = max(1, (int)($_GET['paged'] ?? 1));
    $offset    = ($paged - 1) * $per_page;

    $tj    = $wpdb->prefix . 'ps_newsletter_subscriber_lists';
    $join  = $liste_id ? "INNER JOIN $tj j ON j.subscriber_id = t.id AND j.list_id = " . $liste_id : '';
    $where = 'WHERE 1=1';
    if ($filtre) $where .= $wpdb->prepare(' AND t.statut=%s', $filtre);
    if ($search) $where .= $wpdb->prepare(' AND (t.email LIKE %s OR t.prenom LIKE %s OR t.nom LIKE %s)', "%$search%", "%$search%", "%$search%");

    $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table t $join $where");
    $abonnes = $wpdb->get_results("SELECT t.* FROM $table t $join $where ORDER BY t.date_creation DESC LIMIT $per_page OFFSET $offset");
    $pages   = ceil($total / $per_page);

    $stats_statut = $wpdb->get_results("SELECT statut, COUNT(*) as n FROM $table GROUP BY statut");
    $ss = [];
    foreach ($stats_statut as $s) $ss[$s->statut] = $s->n;

    $all_lists     = ps_nl_get_lists();
    $liste_courante = $liste_id ? ps_nl_get_list($liste_id) : null;

    $export_url = wp_nonce_url(add_query_arg(['export'=>1,'statut'=>$filtre ?: 'tous','liste'=>$liste_id], admin_url('admin.php?page=ps-nl-abonnes')), 'ps_export_csv');
    $add_mode   = ($_GET['action'] ?? '') === 'add';

    ps_nl_header(__('Abonnés', 'poivre-sens'), 'ps-nl-abonnes');

    if (is_string($notice)) echo $notice;
    ?>

    <div class="ps-toolbar">
        <div style="display:flex;gap:8px;align-items:center">
            <form method="get" style="display:flex;gap:8px">
                <input type="hidden" name="page" value="ps-nl-abonnes">
                <input type="text" name="s" value="<?= esc_attr($search) ?>" placeholder="<?= esc_attr(__('Rechercher…', 'poivre-sens')) ?>" class="ps-search-bar" style="padding:7px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;min-width:220px">
                <select name="statut" style="padding:7px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px">
                    <option value=""><?= __('Tous les statuts', 'poivre-sens') ?> (<?= array_sum(array_column($stats_statut,'n')) ?>)</option>
                    <option value="actif"       <?= selected($filtre,'actif',false)       ?>><?= __('Actifs',     'poivre-sens') ?> (<?= $ss['actif']      ?? 0 ?>)</option>
                    <option value="desabonne"   <?= selected($filtre,'desabonne',false)   ?>><?= __('Désabonnés','poivre-sens') ?> (<?= $ss['desabonne']  ?? 0 ?>)</option>
                    <option value="en_attente"  <?= selected($filtre,'en_attente',false)  ?>><?= __('En attente','poivre-sens') ?> (<?= $ss['en_attente'] ?? 0 ?>)</option>
                </select>
                <select name="liste" style="padding:7px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px">
                    <option value="0"><?= __('Toutes les listes', 'poivre-sens') ?></option>
                    <?php foreach ($all_lists as $l): ?>
                    <option value="<?= (int)$l->id ?>" <?= selected($liste_id, $l->id, false) ?>><?= esc_html($l->nom) ?> (<?= (int)$l->nb_actifs ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ps-btn ps-btn-grey ps-btn-sm"><?= __('Filtrer', 'poivre-sens') ?></button>
            </form>
        </div>
        <div style="display:flex;gap:8px">
            <a href="<?= esc_url($export_url) ?>" class="ps-btn ps-btn-grey ps-btn-sm">⬇ <?= __('Export CSV', 'poivre-sens') ?></a>
            <button onclick="document.getElementById('ps-import-modal').style.display='flex'" class="ps-btn ps-btn-grey ps-btn-sm">⬆ <?= __('Import CSV', 'poivre-sens') ?></button>
            <a href="<?= admin_url('admin.php?page=ps-nl-abonnes&action=add') ?>" class="ps-btn ps-btn-primary ps-btn-sm">+ <?= __('Ajouter', 'poivre-sens') ?></a>
        </div>
    </div>

    <?php if ($add_mode): ?>
    <div class="ps-card">
        <h3>+ <?= __('Ajouter un abonné manuellement', 'poivre-sens') ?></h3>
        <form method="post">
            <?php wp_nonce_field('ps_add_subscriber'); ?>
            <div class="ps-form-row">
                <div class="ps-field"><label><?= __('E-mail *', 'poivre-sens') ?></label><input type="email" name="email" required placeholder="prenom@exemple.fr"></div>
                <div class="ps-field"><label><?= __('Prénom', 'poivre-sens') ?></label><input type="text" name="prenom" placeholder="Ambre"></div>
                <div class="ps-field"><label><?= __('Nom', 'poivre-sens') ?></label><input type="text" name="nom" placeholder="Lavignac"></div>
            </div>
            <?php if ($all_lists): ?>
            <div class="ps-field" style="margin-bottom:16px">
                <label><?= __('Listes', 'poivre-sens') ?></label>
                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:6px">
                    <?php foreach ($all_lists as $l): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;text-transform:none;letter-spacing:0;font-size:13px;color:#333">
                        <input type="checkbox" name="lists[]" value="<?= (int)$l->id ?>" <?= checked($liste_id, $l->id, false) ?> style="width:auto">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= esc_attr($l->couleur) ?>"></span>
                        <?= esc_html($l->nom) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" name="ps_add_subscriber" class="ps-btn ps-btn-primary"><?= __('Ajouter l\'abonné', 'poivre-sens') ?></button>
            <a href="<?= admin_url('admin.php?page=ps-nl-abonnes') ?>" class="ps-btn ps-btn-grey" style="margin-left:8px"><?= __('Annuler', 'poivre-sens') ?></a>
        </form>
    </div>
    <?php endif; ?>

    <div style="font-size:12px;color:#888;margin-bottom:10px">
        <?= sprintf(__('%d résultat(s)', 'poivre-sens'), $total) ?>
        <?php if ($liste_courante): ?> — <?= __('Liste :', 'poivre-sens') ?>
            <strong style="color:<?= esc_attr($liste_courante->couleur) ?>"><?= esc_html($liste_courante->nom) ?></strong>
            <a href="<?= admin_url('admin.php?page=ps-nl-abonnes') ?>" style="color:#c28b36">✕ <?= __('effacer', 'poivre-sens') ?></a>
        <?php endif; ?>
        <?php if ($search): ?> — <?= __('Recherche :', 'poivre-sens') ?> <strong><?= esc_html($search) ?></strong>
            <a href="<?= admin_url('admin.php?page=ps-nl-abonnes') ?>" style="color:#c28b36">✕ <?= __('effacer', 'poivre-sens') ?></a>
        <?php endif; ?>
    </div>

    <form method="post" id="ps-abonnes-form">
        <?php wp_nonce_field('ps_bulk_delete', '_wpnonce_bulk_delete'); ?>
        <?php wp_nonce_field('ps_bulk_status', '_wpnonce_bulk_status'); ?>
        <?php wp_nonce_field('ps_bulk_addlist', '_wpnonce_bulk_addlist'); ?>
        <input type="hidden" name="_wpnonce" value="">

        <div class="ps-bulk-bar" id="ps-bulk-bar">
            <span id="ps-bulk-count">0 <?= __('sélectionné(s)', 'poivre-sens') ?></span>
            <select name="bulk_statut" style="padding:4px 8px;border:1px solid #ddd;border-radius:3px;font-size:12px">
                <option value="actif"><?= __('Marquer actif', 'poivre-sens') ?></option>
                <option value="desabonne"><?= __('Désabonner', 'poivre-sens') ?></option>
            </select>
            <button type="submit" name="ps_bulk_status" onclick="this.form['_wpnonce'].value=this.form['_wpnonce_bulk_status'].value" class="ps-btn ps-btn-grey ps-btn-sm"><?= __('Changer statut', 'poivre-sens') ?></button>
            <?php if ($all_lists): ?>
            <span style="color:#ddd">|</span>
            <select name="bulk_list_id" style="padding:4px 8px;border:1px solid #ddd;border-radius:3px;font-size:12px">
                <?php foreach ($all_lists as $l): ?>
                <option value="<?= (int)$l->id ?>"><?= esc_html($l->nom) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="ps_bulk_addlist" onclick="this.form['_wpnonce'].value=this.form['_wpnonce_bulk_addlist'].value" class="ps-btn ps-btn-outline ps-btn-sm"><?= __('Ajouter à la liste', 'poivre-sens') ?></button>
            <?php endif; ?>
            <span style="color:#ddd">|</span>
            <button type="submit" name="ps_bulk_delete" onclick="return confirm('<?= esc_js(__('Supprimer les abonnés sélectionnés ?', 'poivre-sens')) ?>') && (this.form['_wpnonce'].value=this.form['_wpnonce_bulk_delete'].value, true)" class="ps-btn ps-btn-danger ps-btn-sm"><?= __('Supprimer', 'poivre-sens') ?></button>
        </div>

        <table class="ps-table">
            <thead><tr>
                <th style="width:32px"><input type="checkbox" class="ps-checkbox-all" id="cb-all"></th>
                <th><?= __('E-mail', 'poivre-sens') ?></th>
                <th><?= __('Prénom / Nom', 'poivre-sens') ?></th>
                <th><?= __('Listes', 'poivre-sens') ?></th>
                <th><?= __('Statut', 'poivre-sens') ?></th>
                <th><?= __('Source', 'poivre-sens') ?></th>
                <th><?= __('Inscrit le', 'poivre-sens') ?></th>
                <th><?= __('Actions', 'poivre-sens') ?></th>
            </tr></thead>
            <tbody>
            <?php if ($abonnes): foreach ($abonnes as $a): ?>
            <tr>
                <td><input type="checkbox" name="selected_ids[]" value="<?= $a->id ?>" class="ps-cb-row"></td>
                <td><strong><?= esc_html($a->email) ?></strong></td>
                <td><?= esc_html(trim("$a->prenom $a->nom")) ?: '<span style="color:#ccc">—</span>' ?></td>
                <td>
                    <?php $subs_lists = ps_nl_get_subscriber_lists($a->id); ?>
                    <?php if ($subs_lists): foreach ($subs_lists as $sl): ?>
                    <a href="<?= esc_url(admin_url('admin.php?page=ps-nl-abonnes&liste='.$sl->id)) ?>"
                       class="ps-badge" style="background:<?= esc_attr($sl->couleur) ?>1a;color:<?= esc_attr($sl->couleur) ?>;text-decoration:none;margin:0 3px 3px 0"><?= esc_html($sl->nom) ?></a>
                    <?php endforeach; else: ?><span style="color:#ccc">—</span><?php endif; ?>
                </td>
                <td><span class="ps-badge ps-badge-<?= esc_attr($a->statut) ?>"><?= esc_html(ucfirst(str_replace('_',' ',$a->statut))) ?></span></td>
                <td style="color:#aaa;font-size:12px"><?= esc_html($a->source) ?></td>
                <td style="color:#888;font-size:12px"><?= date_i18n('d/m/Y', strtotime($a->date_creation)) ?></td>
                <td class="actions">
                    <?php $del_url = wp_nonce_url(add_query_arg(['page'=>'ps-nl-abonnes','delete_id'=>$a->id], admin_url('admin.php')), 'ps_del_sub_'.$a->id); ?>
                    <a href="<?= esc_url($del_url) ?>" class="ps-btn ps-btn-danger ps-btn-sm" onclick="return confirm('<?= esc_js(__('Supprimer cet abonné ?', 'poivre-sens')) ?>')"><?= __('Supprimer', 'poivre-sens') ?></a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;color:#aaa;padding:32px"><?= __('Aucun abonné trouvé.', 'poivre-sens') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </form>

    <?php if ($pages > 1): ?>
    <div class="ps-pagination">
        <?php
        $base_url = add_query_arg(['page'=>'ps-nl-abonnes','s'=>$search,'statut'=>$filtre,'liste'=>$liste_id], admin_url('admin.php'));
        if ($paged > 1) echo '<a href="' . esc_url(add_query_arg('paged', $paged-1, $base_url)) . '">‹</a>';
        for ($i=1; $i<=$pages; $i++) {
            $cls = $i === $paged ? 'current' : '';
            if (abs($i-$paged) < 3 || $i===1 || $i===$pages) {
                echo '<a href="' . esc_url(add_query_arg('paged',$i,$base_url)) . '" class="' . $cls . '">' . $i . '</a>';
            } elseif (abs($i-$paged) === 3) {
                echo '<span>…</span>';
            }
        }
        if ($paged < $pages) echo '<a href="' . esc_url(add_query_arg('paged', $paged+1, $base_url)) . '">›</a>';
        ?>
    </div>
    <?php endif; ?>

    <!-- Modal Import CSV -->
    <div id="ps-import-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;padding:32px;max-width:480px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.2)">
            <h3 style="margin:0 0 16px;font-size:16px"><?= __('Importer des abonnés (CSV)', 'poivre-sens') ?></h3>
            <p style="font-size:13px;color:#666;margin-bottom:16px">
                <?= __('Format attendu : <code>email,prenom,nom</code> (une ligne d\'en-tête ignorée).', 'poivre-sens') ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ps_import_csv'); ?>
                <input type="file" name="csv_file" accept=".csv,.txt" style="margin-bottom:16px;display:block">
                <?php if ($all_lists): ?>
                <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em"><?= __('Ajouter les abonnés à la liste', 'poivre-sens') ?></label>
                <select name="import_list_id" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;margin-bottom:16px">
                    <option value="0"><?= __('— Aucune liste —', 'poivre-sens') ?></option>
                    <?php foreach ($all_lists as $l): ?>
                    <option value="<?= (int)$l->id ?>" <?= selected($liste_id, $l->id, false) ?>><?= esc_html($l->nom) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div style="display:flex;gap:10px">
                    <button type="submit" name="ps_import_csv" class="ps-btn ps-btn-primary"><?= __('Importer', 'poivre-sens') ?></button>
                    <button type="button" onclick="document.getElementById('ps-import-modal').style.display='none'" class="ps-btn ps-btn-grey"><?= __('Annuler', 'poivre-sens') ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var cbAll = document.getElementById('cb-all');
        var rows  = document.querySelectorAll('.ps-cb-row');
        var bar   = document.getElementById('ps-bulk-bar');
        var count = document.getElementById('ps-bulk-count');
        function updateBar(){
            var sel = document.querySelectorAll('.ps-cb-row:checked').length;
            bar.classList.toggle('visible', sel > 0);
            count.textContent = sel + ' <?= esc_js(__('sélectionné(s)', 'poivre-sens')) ?>';
        }
        if(cbAll) cbAll.addEventListener('change',function(){
            rows.forEach(function(r){r.checked=cbAll.checked});
            updateBar();
        });
        rows.forEach(function(r){r.addEventListener('change', updateBar)});
    })();
    </script>

    </div><!-- .ps-wrap -->
    <?php
}

/* ═══════════════════════════════════════════════════════════
   E-MAIL DE CONFIRMATION D'INSCRIPTION  (modèle éditable)
   ═══════════════════════════════════════════════════════════ */

/** Sujet et corps par défaut de l'e-mail de bienvenue. */
function ps_nl_confirm_defaults() {
    return [
        'ps_nl_confirm_actif' => '1',
        'ps_nl_confirm_sujet' => __('Bienvenue dans la newsletter de {site}', 'poivre-sens'),
        'ps_nl_confirm_corps' => __("{salutation}\n\nVous êtes maintenant inscrit(e) à la newsletter de la Compagnie Poivre & Sens.\n\nVous recevrez nos prochaines dates d'événements, résidences et stages.\n\nPour vous désinscrire à tout moment : {desinscription}\n\nCompagnie Poivre & Sens\n{url}", 'poivre-sens'),
    ];
}

/** Valeur d'un réglage de l'e-mail de confirmation. */
function ps_nl_confirm_opt($cle) {
    $defauts = ps_nl_confirm_defaults();
    return get_option($cle, $defauts[$cle] ?? '');
}

/**
 * Remplace les variables du modèle par leurs valeurs.
 * {salutation} gère l'absence de prénom (« Bonjour, » au lieu de « Bonjour , »).
 */
function ps_nl_confirm_render($texte, $prenom, $email, $unsub) {
    $salutation = $prenom
        ? sprintf(__('Bonjour %s,', 'poivre-sens'), $prenom)
        : __('Bonjour,', 'poivre-sens');

    return str_replace(
        ['{salutation}', '{prenom}', '{email}', '{desinscription}', '{site}', '{url}'],
        [$salutation, $prenom, $email, $unsub, get_bloginfo('name'), home_url('/')],
        (string) $texte
    );
}

/* ═══════════════════════════════════════════════════════════
   PAGE : RÉGLAGES
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_reglages() {
    $notice = '';

    if (isset($_POST['ps_save_reglages']) && check_admin_referer('ps_save_reglages')) {
        update_option('ps_nl_confirm_actif', isset($_POST['confirm_actif']) ? '1' : '0');
        update_option('ps_nl_confirm_sujet', sanitize_text_field(wp_unslash($_POST['confirm_sujet'] ?? '')));
        update_option('ps_nl_confirm_corps', sanitize_textarea_field(wp_unslash($_POST['confirm_corps'] ?? '')));
        $notice = '<div class="ps-notice ps-notice-ok">' . __('Réglages enregistrés.', 'poivre-sens') . '</div>';
    }

    // Restaurer le modèle par défaut
    if (isset($_POST['ps_reset_confirm']) && check_admin_referer('ps_save_reglages')) {
        $d = ps_nl_confirm_defaults();
        update_option('ps_nl_confirm_sujet', $d['ps_nl_confirm_sujet']);
        update_option('ps_nl_confirm_corps', $d['ps_nl_confirm_corps']);
        $notice = '<div class="ps-notice ps-notice-ok">' . __('Modèle par défaut restauré.', 'poivre-sens') . '</div>';
    }

    // Envoi d'un e-mail de test à l'administrateur
    if (isset($_POST['ps_test_confirm']) && check_admin_referer('ps_save_reglages')) {
        $dest = sanitize_email(wp_unslash($_POST['test_email'] ?? '')) ?: get_option('admin_email');
        $ok   = ps_send_confirm_email($dest, __('Prénom', 'poivre-sens'), 'TOKEN-DE-TEST');
        $notice = $ok
            ? '<div class="ps-notice ps-notice-ok">' . sprintf(__('E-mail de test envoyé à %s.', 'poivre-sens'), esc_html($dest)) . '</div>'
            : '<div class="ps-notice ps-notice-err">' . __('L\'envoi a échoué. Vérifiez la configuration e-mail du site (extension SMTP recommandée).', 'poivre-sens') . '</div>';
    }

    $actif = ps_nl_confirm_opt('ps_nl_confirm_actif') === '1';
    $sujet = ps_nl_confirm_opt('ps_nl_confirm_sujet');
    $corps = ps_nl_confirm_opt('ps_nl_confirm_corps');

    ps_nl_header(__('Réglages', 'poivre-sens'), 'ps-nl-reglages');
    echo $notice;
    ?>
    <form method="post">
        <?php wp_nonce_field('ps_save_reglages'); ?>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

            <div class="ps-card">
                <h3>✉ <?= __('E-mail de bienvenue', 'poivre-sens') ?></h3>
                <p style="font-size:13px;color:#666;margin-bottom:18px">
                    <?= __('Envoyé automatiquement à chaque nouvelle inscription (formulaire du site, popup, landing page).', 'poivre-sens') ?>
                </p>

                <div class="ps-field" style="margin-bottom:16px">
                    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:13px;font-weight:400;color:#333">
                        <input type="checkbox" name="confirm_actif" value="1" <?= checked($actif, true, false) ?> style="width:auto">
                        <?= __('Envoyer un e-mail de bienvenue aux nouveaux inscrits', 'poivre-sens') ?>
                    </label>
                </div>

                <div class="ps-field" style="margin-bottom:16px">
                    <label><?= __('Objet', 'poivre-sens') ?></label>
                    <input type="text" name="confirm_sujet" value="<?= esc_attr($sujet) ?>">
                </div>

                <div class="ps-field">
                    <label><?= __('Corps du message', 'poivre-sens') ?></label>
                    <textarea name="confirm_corps" rows="14" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;line-height:1.7;font-family:ui-monospace,SFMono-Regular,Menlo,monospace"><?= esc_textarea($corps) ?></textarea>
                    <div class="help"><?= __('Message en texte brut (pas de HTML) — le plus fiable pour arriver en boîte de réception.', 'poivre-sens') ?></div>
                </div>

                <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
                    <button type="submit" name="ps_save_reglages" class="ps-btn ps-btn-primary">💾 <?= __('Enregistrer', 'poivre-sens') ?></button>
                    <button type="submit" name="ps_reset_confirm" class="ps-btn ps-btn-grey"
                        onclick="return confirm('<?= esc_js(__('Restaurer le modèle par défaut ? Vos modifications seront perdues.', 'poivre-sens')) ?>')">
                        <?= __('Restaurer le modèle par défaut', 'poivre-sens') ?>
                    </button>
                </div>
            </div>

            <div>
                <div class="ps-card">
                    <h3>🔤 <?= __('Variables disponibles', 'poivre-sens') ?></h3>
                    <div style="font-size:12px;color:#555;line-height:2">
                        <code>{salutation}</code> — <?= __('« Bonjour Prénom, » ou « Bonjour, »', 'poivre-sens') ?><br>
                        <code>{prenom}</code> — <?= __('Prénom seul (peut être vide)', 'poivre-sens') ?><br>
                        <code>{email}</code> — <?= __('Adresse de l\'inscrit', 'poivre-sens') ?><br>
                        <code>{desinscription}</code> — <?= __('Lien de désinscription', 'poivre-sens') ?><br>
                        <code>{site}</code> — <?= __('Nom du site', 'poivre-sens') ?><br>
                        <code>{url}</code> — <?= __('Adresse du site', 'poivre-sens') ?>
                    </div>
                    <p style="font-size:11px;color:#999;margin-top:14px">
                        <?= __('Pensez à conserver {desinscription} : le lien de désinscription est une obligation légale (RGPD).', 'poivre-sens') ?>
                    </p>
                </div>

                <div class="ps-card">
                    <h3>📨 <?= __('Tester', 'poivre-sens') ?></h3>
                    <div class="ps-field" style="margin-bottom:12px">
                        <label><?= __('Envoyer un e-mail de test à', 'poivre-sens') ?></label>
                        <input type="email" name="test_email" value="<?= esc_attr(get_option('admin_email')) ?>">
                    </div>
                    <button type="submit" name="ps_test_confirm" class="ps-btn ps-btn-outline"><?= __('Envoyer le test', 'poivre-sens') ?></button>
                    <p style="font-size:11px;color:#999;margin-top:10px">
                        <?= __('Le test utilise le modèle enregistré. Enregistrez d\'abord vos modifications.', 'poivre-sens') ?>
                    </p>
                </div>
            </div>

        </div>
    </form>
    </div><!-- .ps-wrap -->
    <?php
}

/* ═══════════════════════════════════════════════════════════
   PAGE : LISTES  (segments de prospects)
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_listes() {
    global $wpdb;
    $tl = $wpdb->prefix . 'ps_newsletter_lists';
    $notice = '';

    // Créer / modifier une liste
    if (isset($_POST['ps_save_list']) && check_admin_referer('ps_save_list')) {
        $id   = (int)($_POST['list_id'] ?? 0);
        $nom  = sanitize_text_field($_POST['nom'] ?? '');
        $slug = sanitize_title(($_POST['slug'] ?? '') ?: $nom);
        $desc = sanitize_text_field($_POST['description'] ?? '');
        $coul = sanitize_hex_color($_POST['couleur'] ?? '') ?: '#c28b36';
        if (!$nom || !$slug) {
            $notice = '<div class="ps-notice ps-notice-err">' . __('Le nom de la liste est obligatoire.', 'poivre-sens') . '</div>';
        } else {
            // Slug unique (hors liste courante)
            $clash = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tl WHERE slug=%s AND id<>%d", $slug, $id));
            if ($clash) {
                $notice = '<div class="ps-notice ps-notice-err">' . __('Ce raccourci (slug) est déjà utilisé par une autre liste.', 'poivre-sens') . '</div>';
            } elseif ($id) {
                $wpdb->update($tl, ['nom'=>$nom,'slug'=>$slug,'description'=>$desc,'couleur'=>$coul], ['id'=>$id]);
                $notice = '<div class="ps-notice ps-notice-ok">' . __('Liste mise à jour.', 'poivre-sens') . '</div>';
            } else {
                $wpdb->insert($tl, ['nom'=>$nom,'slug'=>$slug,'description'=>$desc,'couleur'=>$coul,'date_creation'=>current_time('mysql')]);
                $notice = '<div class="ps-notice ps-notice-ok">' . sprintf(__('Liste « %s » créée.', 'poivre-sens'), esc_html($nom)) . '</div>';
            }
        }
    }

    // Suppression d'une liste (les appartenances sont supprimées, pas les abonnés)
    if (isset($_GET['delete_id']) && check_admin_referer('ps_del_list_' . $_GET['delete_id'])) {
        $id = (int)$_GET['delete_id'];
        $wpdb->delete($tl, ['id' => $id]);
        $wpdb->delete($wpdb->prefix . 'ps_newsletter_subscriber_lists', ['list_id' => $id]);
        $notice = '<div class="ps-notice ps-notice-ok">' . __('Liste supprimée. Les abonnés concernés restent inscrits.', 'poivre-sens') . '</div>';
    }

    $edit = null;
    if (isset($_GET['edit_id'])) $edit = ps_nl_get_list((int)$_GET['edit_id']);
    $lists = ps_nl_get_lists();

    ps_nl_header(__('Listes', 'poivre-sens'), 'ps-nl-listes');
    echo $notice;
    ?>
    <p style="font-size:13px;color:#666;margin-bottom:20px">
        <?= wp_kses_post(__('Les listes permettent de <strong>localiser l\'origine de vos prospects</strong> (site web, événement, salon…) et de leur envoyer des campagnes ciblées. Un même abonné peut appartenir à plusieurs listes.', 'poivre-sens')) ?>
    </p>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

        <div class="ps-card">
            <h3>🏷 <?= __('Vos listes', 'poivre-sens') ?></h3>
            <table class="ps-table">
                <thead><tr>
                    <th><?= __('Liste', 'poivre-sens') ?></th>
                    <th><?= __('Slug', 'poivre-sens') ?></th>
                    <th><?= __('Abonnés actifs', 'poivre-sens') ?></th>
                    <th><?= __('Actions', 'poivre-sens') ?></th>
                </tr></thead>
                <tbody>
                <?php if ($lists): foreach ($lists as $l): ?>
                <tr>
                    <td>
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= esc_attr($l->couleur) ?>;margin-right:8px;vertical-align:middle"></span>
                        <strong><?= esc_html($l->nom) ?></strong>
                        <?php if ($l->description): ?><br><span style="font-size:11px;color:#aaa;padding-left:18px"><?= esc_html($l->description) ?></span><?php endif; ?>
                    </td>
                    <td><code style="font-size:11px;color:#888"><?= esc_html($l->slug) ?></code></td>
                    <td>
                        <a href="<?= esc_url(admin_url('admin.php?page=ps-nl-abonnes&liste='.$l->id)) ?>">
                            <strong><?= (int)$l->nb_actifs ?></strong>
                        </a>
                        <span style="color:#bbb;font-size:11px"> / <?= (int)$l->nb_total ?> <?= __('au total', 'poivre-sens') ?></span>
                    </td>
                    <td class="actions">
                        <a href="<?= esc_url(admin_url('admin.php?page=ps-nl-listes&edit_id='.$l->id)) ?>" class="ps-btn ps-btn-outline ps-btn-sm"><?= __('Modifier', 'poivre-sens') ?></a>
                        <?php $del = wp_nonce_url(add_query_arg(['page'=>'ps-nl-listes','delete_id'=>$l->id], admin_url('admin.php')), 'ps_del_list_'.$l->id); ?>
                        <a href="<?= esc_url($del) ?>" class="ps-btn ps-btn-danger ps-btn-sm" onclick="return confirm('<?= esc_js(__('Supprimer cette liste ? Les abonnés ne seront pas supprimés.', 'poivre-sens')) ?>')"><?= __('Supprimer', 'poivre-sens') ?></a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align:center;color:#aaa;padding:28px"><?= __('Aucune liste. Créez-en une pour segmenter vos prospects.', 'poivre-sens') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ps-card">
            <h3><?= $edit ? '✏️ '.__('Modifier la liste', 'poivre-sens') : '➕ '.__('Nouvelle liste', 'poivre-sens') ?></h3>
            <form method="post">
                <?php wp_nonce_field('ps_save_list'); ?>
                <input type="hidden" name="list_id" value="<?= (int)($edit->id ?? 0) ?>">
                <div class="ps-field" style="margin-bottom:14px">
                    <label><?= __('Nom de la liste *', 'poivre-sens') ?></label>
                    <input type="text" name="nom" required value="<?= esc_attr($edit->nom ?? '') ?>" placeholder="<?= esc_attr(__('Ex : Grand Bal de l\'Europe', 'poivre-sens')) ?>">
                </div>
                <div class="ps-field" style="margin-bottom:14px">
                    <label><?= __('Slug (raccourci)', 'poivre-sens') ?></label>
                    <input type="text" name="slug" value="<?= esc_attr($edit->slug ?? '') ?>" placeholder="<?= esc_attr(__('grand-bal-europe', 'poivre-sens')) ?>">
                    <div class="help"><?= __('Identifiant utilisé dans les formulaires. Laissez vide pour le générer automatiquement.', 'poivre-sens') ?></div>
                </div>
                <div class="ps-field" style="margin-bottom:14px">
                    <label><?= __('Description', 'poivre-sens') ?></label>
                    <input type="text" name="description" value="<?= esc_attr($edit->description ?? '') ?>" placeholder="<?= esc_attr(__('Où avez-vous rencontré ces prospects ?', 'poivre-sens')) ?>">
                </div>
                <div class="ps-field" style="margin-bottom:18px">
                    <label><?= __('Couleur', 'poivre-sens') ?></label>
                    <input type="color" name="couleur" value="<?= esc_attr($edit->couleur ?? '#c28b36') ?>" style="width:60px;height:34px;padding:2px">
                </div>
                <button type="submit" name="ps_save_list" class="ps-btn ps-btn-primary"><?= $edit ? __('Enregistrer', 'poivre-sens') : __('Créer la liste', 'poivre-sens') ?></button>
                <?php if ($edit): ?>
                <a href="<?= admin_url('admin.php?page=ps-nl-listes') ?>" class="ps-btn ps-btn-grey" style="margin-left:8px"><?= __('Annuler', 'poivre-sens') ?></a>
                <?php endif; ?>
            </form>
        </div>

    </div>
    </div><!-- .ps-wrap -->
    <?php
}

/* ═══════════════════════════════════════════════════════════
   PAGE : CAMPAGNES
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_campagnes() {
    global $wpdb;
    $tc = $wpdb->prefix . 'ps_newsletter_campaigns';
    $ts = $wpdb->prefix . 'ps_newsletter';
    $notice = '';

    // Suppression
    if (isset($_GET['delete_id']) && check_admin_referer('ps_del_camp_' . $_GET['delete_id'])) {
        $wpdb->delete($tc, ['id' => (int)$_GET['delete_id']]);
        $wpdb->delete($wpdb->prefix . 'ps_newsletter_sends', ['campaign_id' => (int)$_GET['delete_id']]);
        $notice = '<div class="ps-notice ps-notice-ok">' . __('Campagne supprimée.', 'poivre-sens') . '</div>';
    }

    $campagnes = $wpdb->get_results("SELECT * FROM $tc ORDER BY cree_le DESC LIMIT 50");
    $actifs    = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ts WHERE statut='actif'");

    ps_nl_header(__('Campagnes', 'poivre-sens'), 'ps-nl-campagnes');
    echo $notice;
    ?>
    <div class="ps-toolbar">
        <div style="font-size:13px;color:#666">
            <?= sprintf(__('<strong>%d</strong> abonné(s) actif(s) recevront la prochaine campagne.', 'poivre-sens'), $actifs) ?>
        </div>
        <a href="<?= admin_url('admin.php?page=ps-nl-nouvelle-campagne') ?>" class="ps-btn ps-btn-primary">✉ <?= __('Nouvelle campagne', 'poivre-sens') ?></a>
    </div>

    <table class="ps-table">
        <thead><tr>
            <th><?= __('Sujet', 'poivre-sens') ?></th>
            <th><?= __('Ciblage', 'poivre-sens') ?></th>
            <th><?= __('Statut', 'poivre-sens') ?></th>
            <th><?= __('Envoyés', 'poivre-sens') ?></th>
            <th><?= __('Ouverts', 'poivre-sens') ?></th>
            <th><?= __('Taux', 'poivre-sens') ?></th>
            <th><?= __('Date', 'poivre-sens') ?></th>
            <th><?= __('Actions', 'poivre-sens') ?></th>
        </tr></thead>
        <tbody>
        <?php if ($campagnes): foreach ($campagnes as $c): ?>
        <?php $taux = $c->nb_envoyes ? round(100*$c->nb_ouverts/$c->nb_envoyes) : 0; ?>
        <?php $c_target_ids = ps_nl_parse_target($c->target_lists ?? ''); ?>
        <?php $c_actifs = ps_nl_count_active($c->target_lists ?? ''); ?>
        <tr>
            <td><strong><?= esc_html($c->sujet) ?></strong><?php if($c->preheader): ?><br><span style="font-size:11px;color:#aaa"><?= esc_html($c->preheader) ?></span><?php endif; ?></td>
            <td style="font-size:12px">
                <?php if (!$c_target_ids): ?>
                <span style="color:#888"><?= __('Tous les abonnés', 'poivre-sens') ?></span>
                <?php else: foreach ($c_target_ids as $lid): $l = ps_nl_get_list($lid); if (!$l) continue; ?>
                <span class="ps-badge" style="background:<?= esc_attr($l->couleur) ?>1a;color:<?= esc_attr($l->couleur) ?>;margin:0 3px 3px 0"><?= esc_html($l->nom) ?></span>
                <?php endforeach; endif; ?>
            </td>
            <td><span class="ps-badge ps-badge-<?= esc_attr($c->statut) ?>"><?= esc_html(ucfirst(str_replace('_',' ',$c->statut))) ?></span></td>
            <td><?= $c->nb_envoyes ?: '—' ?></td>
            <td><?= $c->nb_ouverts ?: '—' ?></td>
            <td>
                <?php if ($c->nb_envoyes): ?>
                <div style="width:60px"><div class="ps-progress"><div class="ps-progress-bar" style="width:<?= $taux ?>%"></div></div></div>
                <span style="font-size:11px;color:#888"><?= $taux ?>%</span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-size:12px;color:#888">
                <?= $c->envoye_le ? date_i18n('d/m/Y', strtotime($c->envoye_le)) : date_i18n('d/m/Y', strtotime($c->cree_le)) ?>
            </td>
            <td class="actions">
                <?php if ($c->statut === 'brouillon'): ?>
                <a href="<?= esc_url(admin_url('admin.php?page=ps-nl-nouvelle-campagne&edit_id='.$c->id)) ?>" class="ps-btn ps-btn-outline ps-btn-sm"><?= __('Modifier', 'poivre-sens') ?></a>
                <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=ps-nl-campagnes&send_id='.$c->id), 'ps_send_camp_'.$c->id)) ?>"
                   class="ps-btn ps-btn-primary ps-btn-sm"
                   onclick="return confirm('<?= esc_js(sprintf(__('Envoyer cette campagne à %d abonnés actifs ?', 'poivre-sens'), $c_actifs)) ?>')"
                >✉ <?= __('Envoyer', 'poivre-sens') ?></a>
                <?php elseif ($c->statut === 'envoye'): ?>
                <a href="<?= esc_url(admin_url('admin.php?page=ps-nl-nouvelle-campagne&view_id='.$c->id)) ?>" class="ps-btn ps-btn-grey ps-btn-sm"><?= __('Aperçu', 'poivre-sens') ?></a>
                <?php elseif ($c->statut === 'envoi_en_cours'): ?>
                <span style="color:#c28b36;font-size:12px">⏳ <?= __('Envoi en cours…', 'poivre-sens') ?></span>
                <?php endif; ?>
                <?php if ($c->statut !== 'envoi_en_cours'): ?>
                <?php $del_url = wp_nonce_url(add_query_arg(['page'=>'ps-nl-campagnes','delete_id'=>$c->id], admin_url('admin.php')), 'ps_del_camp_'.$c->id); ?>
                <a href="<?= esc_url($del_url) ?>" class="ps-btn ps-btn-danger ps-btn-sm" onclick="return confirm('<?= esc_js(__('Supprimer cette campagne ?', 'poivre-sens')) ?>')"><?= __('Supprimer', 'poivre-sens') ?></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;color:#aaa;padding:40px">
            <?= __('Aucune campagne. Créez votre première campagne !', 'poivre-sens') ?>
            <br><br><a href="<?= admin_url('admin.php?page=ps-nl-nouvelle-campagne') ?>" class="ps-btn ps-btn-primary"><?= __('Créer une campagne', 'poivre-sens') ?></a>
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div><!-- .ps-wrap -->
    <?php

    // Envoi déclenché depuis cette page
    if (isset($_GET['send_id']) && check_admin_referer('ps_send_camp_' . $_GET['send_id'])) {
        ps_nl_send_campaign((int)$_GET['send_id']);
        echo '<script>location.href="' . esc_js(admin_url('admin.php?page=ps-nl-campagnes')) . '";</script>';
    }
}

/* ═══════════════════════════════════════════════════════════
   PAGE : CRÉER / MODIFIER UNE CAMPAGNE
   ═══════════════════════════════════════════════════════════ */
function ps_nl_page_nouvelle_campagne() {
    global $wpdb;
    $tc = $wpdb->prefix . 'ps_newsletter_campaigns';
    $ts = $wpdb->prefix . 'ps_newsletter';
    $notice = '';

    $edit_id = (int)($_GET['edit_id'] ?? 0);
    $view_id = (int)($_GET['view_id'] ?? 0);
    $camp    = null;

    // Chargement en édition / aperçu
    if ($edit_id) $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tc WHERE id=%d AND statut='brouillon'", $edit_id));
    if ($view_id) $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tc WHERE id=%d", $view_id));

    // Sauvegarde / envoi
    if (isset($_POST['ps_save_campaign']) && check_admin_referer('ps_save_campaign')) {
        $target_ids = array_map('intval', (array)($_POST['target_lists'] ?? []));
        $data = [
            'sujet'         => sanitize_text_field($_POST['sujet'] ?? ''),
            'preheader'     => sanitize_text_field($_POST['preheader'] ?? ''),
            'contenu_html'  => wp_kses_post($_POST['contenu_html'] ?? ''),
            'contenu_texte' => sanitize_textarea_field($_POST['contenu_texte'] ?? ''),
            'from_nom'      => sanitize_text_field($_POST['from_nom'] ?? ''),
            'from_email'    => sanitize_email($_POST['from_email'] ?? ''),
            'target_lists'  => implode(',', $target_ids),
        ];

        if (!$data['sujet']) {
            $notice = '<div class="ps-notice ps-notice-err">' . __('Le sujet est obligatoire.', 'poivre-sens') . '</div>';
        } else {
            if ($edit_id) {
                $wpdb->update($tc, $data, ['id' => $edit_id]);
                $camp_id = $edit_id;
                $notice  = '<div class="ps-notice ps-notice-ok">' . __('Campagne mise à jour.', 'poivre-sens') . '</div>';
            } else {
                $wpdb->insert($tc, $data);
                $camp_id = $wpdb->insert_id;
                $notice  = '<div class="ps-notice ps-notice-ok">' . __('Brouillon enregistré.', 'poivre-sens') . '</div>';
            }

            // Envoi immédiat ?
            if (isset($_POST['ps_send_now'])) {
                $cible_actifs = ps_nl_count_active($data['target_lists']);
                if ($cible_actifs === 0) {
                    $notice = '<div class="ps-notice ps-notice-warn">' . __('Aucun abonné actif pour ce ciblage. Campagne sauvegardée en brouillon.', 'poivre-sens') . '</div>';
                } else {
                    ps_nl_send_campaign($camp_id);
                    wp_redirect(admin_url('admin.php?page=ps-nl-campagnes&sent=1'));
                    exit;
                }
            }
            // Recharger le camp
            $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tc WHERE id=%d", $camp_id));
            $edit_id = $camp_id;
        }
    }

    $all_lists      = ps_nl_get_lists();
    $target_ids_sel = ps_nl_parse_target($camp->target_lists ?? '');
    $actifs         = ps_nl_count_active($camp->target_lists ?? '');
    $def_nom  = get_bloginfo('name');
    $def_mail = get_option('admin_email');
    $view_only = $view_id && $camp && $camp->statut === 'envoye';

    ps_nl_header($view_only ? __('Aperçu de la campagne', 'poivre-sens') : ($edit_id ? __('Modifier la campagne', 'poivre-sens') : __('Nouvelle campagne', 'poivre-sens')), 'ps-nl-nouvelle-campagne');
    echo $notice;

    if ($view_only && $camp): ?>
    <div class="ps-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
            <div>
                <h2 style="font-size:18px;margin:0 0 6px"><?= esc_html($camp->sujet) ?></h2>
                <?php if($camp->preheader): ?><p style="color:#888;font-size:13px;margin:0 0 16px"><?= esc_html($camp->preheader) ?></p><?php endif; ?>
                <p style="font-size:12px;color:#aaa">
                    <?= __('Envoyée le', 'poivre-sens') ?> <?= date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($camp->envoye_le)) ?> •
                    <?= $camp->nb_envoyes ?> <?= __('envoi(s)', 'poivre-sens') ?> •
                    <?= $camp->nb_ouverts ?> <?= __('ouverture(s)', 'poivre-sens') ?>
                    (<?= $camp->nb_envoyes ? round(100*$camp->nb_ouverts/$camp->nb_envoyes) : 0 ?>%)
                </p>
            </div>
        </div>
        <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0">
        <div style="border:1px solid #e8e8e8;border-radius:4px;padding:20px;background:#fff;max-width:600px">
            <?= $camp->contenu_html ?>
        </div>
    </div>
    <?php else: ?>

    <form method="post">
        <?php wp_nonce_field('ps_save_campaign'); ?>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

            <div>
                <div class="ps-card">
                    <h3>✉ <?= __('Contenu de l\'e-mail', 'poivre-sens') ?></h3>
                    <div class="ps-form-row full">
                        <div class="ps-field">
                            <label><?= __('Sujet *', 'poivre-sens') ?></label>
                            <input type="text" name="sujet" required placeholder="<?= esc_attr(__('Ex : Prochains événements — Mars 2026', 'poivre-sens')) ?>" value="<?= esc_attr($camp->sujet ?? '') ?>">
                            <div class="help"><?= __('Ligne d\'objet visible dans la boîte de réception.', 'poivre-sens') ?></div>
                        </div>
                    </div>
                    <div class="ps-form-row full">
                        <div class="ps-field">
                            <label><?= __('Texte d\'aperçu (preheader)', 'poivre-sens') ?></label>
                            <input type="text" name="preheader" placeholder="<?= esc_attr(__('Visible après l\'objet dans certains clients mail…', 'poivre-sens')) ?>" value="<?= esc_attr($camp->preheader ?? '') ?>">
                        </div>
                    </div>
                    <div class="ps-form-row full" style="margin-top:8px">
                        <div class="ps-field">
                            <label><?= __('Contenu HTML', 'poivre-sens') ?></label>
                        </div>
                    </div>
                    <?php
                    wp_editor(
                        $camp->contenu_html ?? ps_nl_default_template(),
                        'contenu_html',
                        [
                            'textarea_name' => 'contenu_html',
                            'media_buttons' => true,
                            'textarea_rows' => 20,
                            'teeny'         => false,
                        ]
                    );
                    ?>
                    <div class="ps-form-row full" style="margin-top:16px">
                        <div class="ps-field">
                            <label><?= __('Version texte brut (optionnel)', 'poivre-sens') ?></label>
                            <textarea name="contenu_texte" rows="6" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;font-family:monospace"><?= esc_textarea($camp->contenu_texte ?? '') ?></textarea>
                            <div class="help"><?= __('Utilisé pour les clients e-mail qui n\'affichent pas le HTML. Laissez vide pour générer automatiquement.', 'poivre-sens') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if ($all_lists): ?>
                <div class="ps-card">
                    <h3>🎯 <?= __('Ciblage', 'poivre-sens') ?></h3>
                    <div class="ps-field" style="margin-bottom:6px">
                        <label style="margin-bottom:10px"><?= __('Listes destinataires', 'poivre-sens') ?></label>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <?php foreach ($all_lists as $l): ?>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:400;text-transform:none;letter-spacing:0;font-size:13px;color:#333">
                                <input type="checkbox" name="target_lists[]" value="<?= (int)$l->id ?>" class="ps-target-list" <?= checked(in_array((int)$l->id, $target_ids_sel, true)) ?> style="width:auto">
                                <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= esc_attr($l->couleur) ?>"></span>
                                <?= esc_html($l->nom) ?>
                                <span style="color:#aaa;font-size:11px">(<?= (int)$l->nb_actifs ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="help"><?= __('Aucune liste cochée = tous les abonnés actifs.', 'poivre-sens') ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="ps-card">
                    <h3>⚙ <?= __('Paramètres d\'envoi', 'poivre-sens') ?></h3>
                    <div class="ps-field" style="margin-bottom:14px">
                        <label><?= __('Nom de l\'expéditeur', 'poivre-sens') ?></label>
                        <input type="text" name="from_nom" value="<?= esc_attr($camp->from_nom ?? $def_nom) ?>">
                    </div>
                    <div class="ps-field" style="margin-bottom:14px">
                        <label><?= __('E-mail expéditeur', 'poivre-sens') ?></label>
                        <input type="email" name="from_email" value="<?= esc_attr($camp->from_email ?? 'contact@cie.poivresens.fr') ?>">
                    </div>
                    <hr style="border:none;border-top:1px solid #f0f0f0;margin:16px 0">
                    <div style="background:#fdf9f3;border:1px solid #e8d5a3;border-radius:4px;padding:14px;font-size:13px;margin-bottom:16px">
                        <strong style="color:#c28b36">📬 <span id="ps-cible-count"><?= $actifs ?></span> <?= __('abonné(s) actif(s)', 'poivre-sens') ?></strong><br>
                        <span style="color:#888;font-size:12px"><?= __('Recevront cette campagne.', 'poivre-sens') ?></span>
                    </div>
                    <div style="font-size:12px;color:#999;margin-bottom:14px">
                        <?= __('Variables disponibles :', 'poivre-sens') ?><br>
                        <code>{prenom}</code> — <?= __('Prénom de l\'abonné', 'poivre-sens') ?><br>
                        <code>{email}</code> — <?= __('E-mail', 'poivre-sens') ?><br>
                        <code>{desinscription}</code> — <?= __('Lien désinscription', 'poivre-sens') ?>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <button type="submit" name="ps_save_campaign" class="ps-btn ps-btn-grey">💾 <?= __('Enregistrer le brouillon', 'poivre-sens') ?></button>
                        <button type="submit" name="ps_send_now" value="1" id="ps-send-now-btn"
                            <?= $actifs > 0 ? '' : 'disabled' ?>
                            style="<?= $actifs > 0 ? '' : 'opacity:.5;cursor:not-allowed' ?>"
                            onclick="return confirm('<?= esc_js(__('Envoyer maintenant à ', 'poivre-sens')) ?>' + document.getElementById('ps-cible-count').textContent + '<?= esc_js(__(' abonné(s) actif(s) ?', 'poivre-sens')) ?>')"
                            class="ps-btn ps-btn-primary">✉ <span id="ps-send-now-label"><?= $actifs > 0 ? __('Envoyer maintenant', 'poivre-sens') : __('Aucun abonné actif', 'poivre-sens') ?></span>
                        </button>
                    </div>
                </div>

                <?php if ($edit_id && $camp): ?>
                <div class="ps-card">
                    <h3>📋 <?= __('Aperçu', 'poivre-sens') ?></h3>
                    <div style="border:1px solid #e8e8e8;border-radius:4px;padding:16px;background:#fff;max-height:300px;overflow-y:auto;font-size:12px">
                        <?= $camp->contenu_html ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($all_lists): ?>
    <script>
    (function(){
        var checks   = document.querySelectorAll('.ps-target-list');
        var countEl  = document.getElementById('ps-cible-count');
        var sendBtn  = document.getElementById('ps-send-now-btn');
        var labelEl  = document.getElementById('ps-send-now-label');
        if (!checks.length || !countEl) return;

        function refreshCount(){
            var ids = Array.prototype.filter.call(checks, function(c){ return c.checked; })
                                             .map(function(c){ return c.value; });
            var body = new URLSearchParams();
            body.append('action', 'ps_nl_count_target');
            body.append('nonce', <?= wp_json_encode(wp_create_nonce('ps_nl_count_target')) ?>);
            body.append('ids', ids.join(','));
            fetch(ajaxurl, { method:'POST', body: body })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.success) return;
                    countEl.textContent = res.data.count;
                    if (sendBtn) {
                        sendBtn.disabled = (res.data.count === 0);
                        sendBtn.style.opacity = res.data.count === 0 ? '.5' : '';
                        sendBtn.style.cursor  = res.data.count === 0 ? 'not-allowed' : '';
                    }
                    if (labelEl) {
                        labelEl.textContent = res.data.count === 0
                            ? <?= wp_json_encode(__('Aucun abonné actif', 'poivre-sens')) ?>
                            : <?= wp_json_encode(__('Envoyer maintenant', 'poivre-sens')) ?>;
                    }
                });
        }
        checks.forEach(function(c){ c.addEventListener('change', refreshCount); });
    })();
    </script>
    <?php endif; ?>

    <?php endif; ?>

    </div><!-- .ps-wrap -->
    <?php
}

/* ── AJAX (admin) : recompte les abonnés actifs pour un ciblage de listes ── */
add_action('wp_ajax_ps_nl_count_target', function () {
    check_ajax_referer('ps_nl_count_target', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $ids = sanitize_text_field(wp_unslash($_POST['ids'] ?? ''));
    wp_send_json_success(['count' => ps_nl_count_active($ids)]);
});

/* ═══════════════════════════════════════════════════════════
   ENVOI D'UNE CAMPAGNE
   ═══════════════════════════════════════════════════════════ */
function ps_nl_send_campaign($campaign_id) {
    global $wpdb;
    $tc = $wpdb->prefix . 'ps_newsletter_campaigns';
    $ts = $wpdb->prefix . 'ps_newsletter';
    $td = $wpdb->prefix . 'ps_newsletter_sends';

    $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tc WHERE id=%d AND statut IN ('brouillon','erreur')", $campaign_id));
    if (!$camp) return false;

    // Marquer en cours
    $wpdb->update($tc, ['statut' => 'envoi_en_cours'], ['id' => $campaign_id]);

    // Destinataires : ciblage par liste(s) si défini, sinon tous les actifs
    $abonnes = ps_nl_get_active_subscribers($camp->target_lists ?? '');
    if (!$abonnes) {
        $wpdb->update($tc, ['statut' => 'erreur'], ['id' => $campaign_id]);
        return false;
    }

    $from_nom   = $camp->from_nom   ?: get_bloginfo('name');
    $from_email = $camp->from_email ?: 'contact@cie.poivresens.fr';
    $nb_envoyes = 0;
    $errors     = 0;

    foreach ($abonnes as $a) {
        // Anti-doublon
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $td WHERE campaign_id=%d AND subscriber_id=%d", $campaign_id, $a->id))) continue;

        $unsub_url  = add_query_arg(['action'=>'ps_newsletter_unsubscribe','token'=>$a->token], admin_url('admin-ajax.php'));
        $track_url  = add_query_arg(['action'=>'ps_nl_open','cid'=>$campaign_id,'sid'=>$a->id,'t'=>$a->token], admin_url('admin-ajax.php'));

        // Personnalisation
        $html = str_replace(
            ['{prenom}', '{email}', '{desinscription}'],
            [esc_html($a->prenom ?: 'ami(e)'), esc_html($a->email), esc_url($unsub_url)],
            $camp->contenu_html
        );
        // Pixel de tracking
        $html .= '<img src="' . esc_url($track_url) . '" width="1" height="1" style="display:none" alt="">';

        $texte = $camp->contenu_texte ?: wp_strip_all_tags($html);
        $texte = str_replace(['{prenom}','{email}','{desinscription}'], [$a->prenom ?: 'ami(e)', $a->email, $unsub_url], $texte);
        $texte .= "\n\n" . __('Se désinscrire :', 'poivre-sens') . ' ' . $unsub_url;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_nom <$from_email>",
            "Reply-To: $from_email",
            'X-Mailer: Poivre-Sens-Newsletter/1.0',
        ];

        $ok = wp_mail($a->email, $camp->sujet, $html, $headers);
        if ($ok) {
            $wpdb->insert($td, ['campaign_id'=>$campaign_id,'subscriber_id'=>$a->id,'email'=>$a->email,'envoye_le'=>current_time('mysql')]);
            $nb_envoyes++;
        } else {
            $errors++;
        }
    }

    $final_statut = ($errors > 0 && $nb_envoyes === 0) ? 'erreur' : 'envoye';
    $wpdb->update($tc, [
        'statut'     => $final_statut,
        'envoye_le'  => current_time('mysql'),
        'nb_envoyes' => $nb_envoyes,
    ], ['id' => $campaign_id]);

    return $nb_envoyes;
}

/* ── Tracking pixel ouverture ────────────────────────────── */
add_action('wp_ajax_nopriv_ps_nl_open', 'ps_nl_track_open');
add_action('wp_ajax_ps_nl_open',        'ps_nl_track_open');
function ps_nl_track_open() {
    global $wpdb;
    $tc  = $wpdb->prefix . 'ps_newsletter_campaigns';
    $td  = $wpdb->prefix . 'ps_newsletter_sends';
    $cid = (int)($_GET['cid'] ?? 0);
    $sid = (int)($_GET['sid'] ?? 0);

    if ($cid && $sid) {
        $send = $wpdb->get_row($wpdb->prepare("SELECT id, ouvert_le FROM $td WHERE campaign_id=%d AND subscriber_id=%d", $cid, $sid));
        if ($send && !$send->ouvert_le) {
            $wpdb->update($td, ['ouvert_le'=>current_time('mysql')], ['id'=>$send->id]);
            // Incrémenter compteur
            $wpdb->query($wpdb->prepare("UPDATE $tc SET nb_ouverts = nb_ouverts + 1 WHERE id=%d", $cid));
        }
    }
    // Retourner un pixel transparent 1×1 GIF
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

/* ═══════════════════════════════════════════════════════════
   TEMPLATE PAR DÉFAUT D'UNE CAMPAGNE
   ═══════════════════════════════════════════════════════════ */
function ps_nl_default_template() {
    $site = get_bloginfo('name');
    $url  = home_url('/');
    return <<<HTML
<div style="max-width:600px;margin:0 auto;font-family:Georgia,serif;background:#080705;color:#ece3cb">
  <!-- Header -->
  <div style="padding:32px 40px 24px;border-bottom:1px solid rgba(194,139,54,.25);text-align:center">
    <p style="font-size:.62rem;letter-spacing:.28em;text-transform:uppercase;color:#c28b36;margin:0 0 8px">Newsletter</p>
    <h1 style="font-size:1.8rem;font-weight:300;color:#ece3cb;margin:0">{$site}</h1>
  </div>

  <!-- Contenu principal -->
  <div style="padding:32px 40px">
    <h2 style="font-size:1.4rem;font-weight:300;color:#ece3cb;margin:0 0 20px">Bonjour {prenom} 👋</h2>
    <p style="line-height:1.8;color:rgba(236,227,203,.7);margin:0 0 20px">
      Ajoutez ici votre message principal. Parlez de vos prochains spectacles, ateliers, jams…
    </p>

    <!-- Événement -->
    <div style="border-left:2px solid #c28b36;padding:16px 20px;margin:24px 0;background:rgba(194,139,54,.06)">
      <p style="font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:#c28b36;margin:0 0 8px">Prochain événement</p>
      <h3 style="font-size:1.2rem;font-weight:400;color:#ece3cb;margin:0 0 6px">Titre de l'événement</h3>
      <p style="font-size:.85rem;color:rgba(236,227,203,.6);margin:0">📅 Date — 📍 Lieu</p>
    </div>

    <p style="line-height:1.8;color:rgba(236,227,203,.7);margin:0 0 28px">
      Ajoutez d'autres paragraphes selon vos besoins.
    </p>

    <a href="{$url}" style="display:inline-block;padding:12px 28px;background:#c28b36;color:#080705;text-decoration:none;font-size:.75rem;letter-spacing:.15em;text-transform:uppercase">
      Visiter notre site →
    </a>
  </div>

  <!-- Footer -->
  <div style="padding:20px 40px;border-top:1px solid rgba(194,139,54,.15);text-align:center">
    <p style="font-size:.72rem;color:rgba(236,227,203,.35);margin:0 0 6px">{$site} · <a href="{$url}" style="color:#c28b36">{$url}</a></p>
    <p style="font-size:.68rem;color:rgba(127,116,99,.5);margin:0">
      Vous recevez cet e-mail car vous êtes inscrit(e) à notre newsletter.<br>
      <a href="{desinscription}" style="color:rgba(194,139,54,.5)">Se désinscrire</a>
    </p>
  </div>
</div>
HTML;
}

/* ═══════════════════════════════════════════════════════════
   HELPER : EN-TÊTE DE PAGE ADMIN
   ═══════════════════════════════════════════════════════════ */
function ps_nl_header($titre, $current_page) {
    $tabs = [
        'ps-newsletter'           => ['📊', __('Tableau de bord', 'poivre-sens')],
        'ps-nl-abonnes'           => ['👥', __('Abonnés',         'poivre-sens')],
        'ps-nl-listes'            => ['🏷', __('Listes',          'poivre-sens')],
        'ps-nl-campagnes'         => ['📬', __('Campagnes',       'poivre-sens')],
        'ps-nl-nouvelle-campagne' => ['✉',  __('Nouvelle campagne','poivre-sens')],
        'ps-nl-reglages'          => ['⚙',  __('Réglages',         'poivre-sens')],
    ];
    global $wpdb;
    $actifs = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ps_newsletter WHERE statut='actif'");
    ?>
    <div class="ps-wrap">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px">
        <div>
            <h1 style="font-size:20px;font-weight:600;color:#1d2327;margin:0 0 4px">
                📧 <?= __('Poivre &amp; Sens Newsletter', 'poivre-sens') ?>
            </h1>
            <p style="font-size:12px;color:#aaa;margin:0"><?= sprintf(__('%d abonné(s) actif(s)', 'poivre-sens'), $actifs) ?></p>
        </div>
    </div>
    <div class="ps-tabs">
        <?php foreach ($tabs as $page => [$icon, $label]): ?>
        <a href="<?= admin_url('admin.php?page=' . $page) ?>"
           class="ps-tab <?= $current_page === $page ? 'active' : '' ?>">
            <?= $icon ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
}
