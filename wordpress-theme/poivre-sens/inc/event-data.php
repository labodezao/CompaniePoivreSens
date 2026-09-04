<?php
/**
 * inc/event-data.php
 *
 * Couche d'adaptation entre les gabarits du thème et la source des
 * événements. Le site a d'abord stocké ses événements dans le CPT
 * « evenement » (champs _evt_*) ; il utilise désormais le plugin
 * CF Événements & Réservations (CPT « cf_event », champs _cfeb_*).
 *
 * Les gabarits n'interrogent plus les champs directement : ils passent
 * par ps_evt_champ(), qui lit au bon endroit selon le type du contenu.
 * Les deux sources peuvent ainsi coexister le temps de la migration.
 */
defined('ABSPATH') || exit;

/**
 * Le plugin CF est-il chargé ?
 *
 * On teste la constante et la classe, définies au chargement du
 * plugin : la réponse est donc déjà fiable avant « init », au
 * contraire de post_type_exists().
 */
function ps_evt_plugin_actif() {
    return defined('CFEB_SLUG') && class_exists('CF_CPT');
}

/** Type de contenu des événements réellement en service. */
function ps_evt_cpt() {
    return ps_evt_plugin_actif() ? CFEB_SLUG : 'evenement';
}

/** Clé méta servant au tri et aux filtres de date, selon la source. */
function ps_evt_cle_date() {
    return ps_evt_cpt() === 'evenement' ? '_evt_date' : '_cfeb_date_debut';
}

/** Le contenu vient-il du plugin ? */
function ps_evt_est_plugin($post_id) {
    return ps_evt_plugin_actif() && get_post_type($post_id) === CFEB_SLUG;
}

/**
 * Lit un champ d'événement sous une forme stable, quelle que soit la
 * source. Champs disponibles : date, heure, heure_fin, lieu, adresse,
 * ville, type (identifiant), type_label (libellé affichable), prix,
 * billetterie, complet.
 */
function ps_evt_champ($post_id, $champ) {
    if (!ps_evt_est_plugin($post_id)) {
        // Ancien module du thème
        $legacy = [
            'date' => '_evt_date', 'heure' => '_evt_heure', 'heure_fin' => '_evt_heure_fin',
            'lieu' => '_evt_lieu', 'adresse' => '_evt_adresse', 'ville' => '_evt_ville',
            'type' => '_evt_type', 'prix' => '_evt_prix', 'billetterie' => '_evt_billetterie',
        ];
        if ($champ === 'complet') return get_post_meta($post_id, '_evt_complet', true) === '1';
        if ($champ === 'type_label') {
            return ps_evt_type_label((string) get_post_meta($post_id, '_evt_type', true));
        }
        // Réservation en ligne : notion propre au plugin, sans équivalent ici.
        if ($champ === 'prix_brut') return 0.0;
        return isset($legacy[$champ]) ? get_post_meta($post_id, $legacy[$champ], true) : '';
    }

    // Plugin CF
    $debut = (string) get_post_meta($post_id, '_cfeb_date_debut', true); // 2026-09-12T20:30
    $fin   = (string) get_post_meta($post_id, '_cfeb_date_fin',   true);

    switch ($champ) {
        case 'date':
            return $debut !== '' ? substr($debut, 0, 10) : '';

        case 'heure':
            $h = $debut !== '' ? substr($debut, 11, 5) : '';
            // Minuit signifie « horaire non précisé », comme dans l'ancien module.
            return $h === '00:00' ? '' : $h;

        case 'heure_fin':
            return $fin !== '' ? substr($fin, 11, 5) : '';

        case 'lieu':    return (string) get_post_meta($post_id, '_cfeb_lieu',  true);
        case 'ville':   return (string) get_post_meta($post_id, '_cfeb_ville', true);
        case 'adresse': return (string) get_post_meta($post_id, '_cfeb_infos_pratiques', true);

        case 'type':
        case 'type_label':
        case 'type_color':
            if (!defined('CFEB_TAX')) return '';
            $termes = get_the_terms($post_id, CFEB_TAX);
            if (!is_array($termes) || !$termes) return '';
            if ($champ === 'type_color') {
                // La couleur se choisit dans CF Réservations › Catégories.
                // Vide tant qu'elle n'a pas été personnalisée (pas de méta
                // enregistrée), pour laisser l'affichage retomber sur la
                // couleur par défaut de son type (feuille de style).
                return (string) get_term_meta($termes[0]->term_id, 'cfeb_cat_color', true);
            }
            return $champ === 'type' ? $termes[0]->slug : $termes[0]->name;

        case 'prix':
            // Le libellé d'origine prime : « prix libre », « sur réservation »…
            $texte = (string) get_post_meta($post_id, '_ps_prix_texte', true);
            if ($texte !== '') return $texte;
            $montant = get_post_meta($post_id, '_cfeb_prix', true);
            if ($montant === '' || $montant === null) return '';
            $montant = (float) $montant;
            return $montant > 0
                ? rtrim(rtrim(number_format($montant, 2, ',', ' '), '0'), ',') . ' €'
                : __('Gratuit', 'poivre-sens');

        case 'billetterie':
            return (string) get_post_meta($post_id, '_cfeb_event_url', true);

        case 'prix_brut':
            $montant = get_post_meta($post_id, '_cfeb_prix', true);
            return ($montant === '' || $montant === null) ? 0.0 : (float) $montant;

        case 'complet':
            // Une capacité chiffrée rend ce statut automatique : dès que les
            // réservations confirmées l'atteignent, compute_statut() bascule
            // sur « complet » sans que l'administrateur ait à y penser. Sans
            // capacité (0 = illimité), on retombe sur le statut manuel.
            if (class_exists('CF_CPT')) {
                return CF_CPT::compute_statut($post_id) === 'complet';
            }
            return get_post_meta($post_id, '_cfeb_statut', true) === 'complet';

        case 'max_places':
            return (int) get_post_meta($post_id, '_cfeb_max_places', true);

        case 'deadline':
            return (string) get_post_meta($post_id, '_cfeb_deadline', true);

        case 'email_contact':
            return (string) get_post_meta($post_id, '_cfeb_email_contact', true);

        case 'animateur':
            return (string) get_post_meta($post_id, '_cfeb_animateur', true);

        case 'lien_visio':
            return (string) get_post_meta($post_id, '_cfeb_lien_visio', true);

        case 'all_day':
            return (bool) get_post_meta($post_id, '_cfeb_all_day', true);

        case 'featured':
            return (bool) get_post_meta($post_id, '_cfeb_featured', true);

        case 'statut_event':
            return (string) get_post_meta($post_id, '_cfeb_statut_event', true) ?: 'publie';
    }
    return '';
}

/**
 * Places encore libres, ou null si la capacité n'est pas renseignée
 * (illimitée) ou si le module n'a pas cette notion (ancien CPT du thème).
 */
function ps_evt_places_restantes($post_id) {
    if (!ps_evt_est_plugin($post_id)) return null;

    $max = (int) get_post_meta($post_id, '_cfeb_max_places', true);
    if ($max <= 0) return null;

    $reservees = class_exists('CF_Booking') ? (int) CF_Booking::count_for_event($post_id, 'confirme') : 0;
    return max(0, $max - $reservees);
}

/**
 * Statut de réservation détaillé : 'ouvert', 'complet' ou 'ferme'.
 * Sert à choisir entre bouton de réservation, liste d'attente et message
 * de fermeture — ps_evt_champ('complet') ne renvoie qu'un booléen, trop
 * pauvre pour ce choix à trois branches.
 */
function ps_evt_statut_resa($post_id) {
    if (!ps_evt_est_plugin($post_id)) {
        return get_post_meta($post_id, '_evt_complet', true) === '1' ? 'complet' : 'ouvert';
    }
    if (class_exists('CF_CPT')) {
        return CF_CPT::compute_statut($post_id);
    }
    return get_post_meta($post_id, '_cfeb_statut', true) ?: 'ouvert';
}

/** Clé méta de la ville, pour les filtres de l'agenda. */
function ps_evt_cle_ville() {
    return ps_evt_plugin_actif() ? '_cfeb_ville' : '_evt_ville';
}

/**
 * Types d'événement proposés dans les filtres, sous la forme
 * identifiant => libellé. Ils viennent des catégories du plugin
 * quand il est actif, sinon de la liste figée du thème.
 */
function ps_evt_liste_types() {
    if (!ps_evt_plugin_actif() || !defined('CFEB_TAX')) {
        return function_exists('ps_evt_types') ? ps_evt_types() : [];
    }
    $termes = get_terms(['taxonomy' => CFEB_TAX, 'hide_empty' => false]);
    if (is_wp_error($termes)) return [];

    $liste = [];
    foreach ($termes as $terme) {
        $liste[$terme->slug] = $terme->name;
    }
    return $liste;
}

/**
 * Restreint une requête d'événements à un type donné. Le thème
 * stockait le type en champ personnalisé, le plugin en catégorie :
 * le filtre ne se pose donc pas au même endroit.
 */
function ps_evt_filtrer_type(array $args, $type) {
    if ($type === '') return $args;

    if (ps_evt_plugin_actif() && defined('CFEB_TAX')) {
        $args['tax_query'][] = [
            'taxonomy' => CFEB_TAX,
            'field'    => 'slug',
            'terms'    => $type,
        ];
    } else {
        $args['meta_query'][] = ['key' => '_evt_type', 'value' => $type, 'compare' => '='];
    }
    return $args;
}

/**
 * Borne haute d'une plage de dates, adaptée au format de la source.
 * Le plugin stocke « 2026-09-30T20:00 », qui dépasse « 2026-09-30 » en
 * comparaison de chaînes : il faut donc inclure l'heure de fin de journée.
 */
function ps_evt_borne_fin($date_ymd) {
    return ps_evt_cle_date() === '_evt_date' ? $date_ymd : $date_ymd . 'T23:59';
}

/** Borne basse d'une plage, dans le même esprit. */
function ps_evt_borne_debut($date_ymd) {
    return ps_evt_cle_date() === '_evt_date' ? $date_ymd : $date_ymd . 'T00:00';
}

/* ═══════════════════════════════════════════════════════════
   ENRICHISSEMENT DU CPT DU PLUGIN
   ═══════════════════════════════════════════════════════════
   Le plugin déclare « cf_event » pour ses réservations : titre
   seul, sans archive publique. Le site, lui, publie de vraies
   pages d'événement (texte, affiche, agenda). On complète donc
   sa déclaration au lieu de la dupliquer, afin de conserver les
   adresses existantes : /evenements/ et /evenements/mon-spectacle/.
   ═══════════════════════════════════════════════════════════ */
add_filter('register_post_type_args', function ($args, $type) {
    if (!defined('CFEB_SLUG') || $type !== CFEB_SLUG) return $args;

    $args['labels'] = array_merge((array) ($args['labels'] ?? []), [
        'name'          => __('Événements',       'poivre-sens'),
        'singular_name' => __('Événement',        'poivre-sens'),
        'menu_name'     => __('Événements',       'poivre-sens'),
        'all_items'     => __('Tous les événements', 'poivre-sens'),
        'add_new_item'  => __('Nouvel événement', 'poivre-sens'),
        'edit_item'     => __('Modifier l\'événement', 'poivre-sens'),
    ]);

    // Contenu, affiche et chapô : indispensables aux gabarits du thème.
    $args['supports']    = array_values(array_unique(array_merge(
        (array) ($args['supports'] ?? ['title']),
        ['title', 'editor', 'thumbnail', 'excerpt']
    )));

    // L'agenda public reprend l'adresse historique.
    $args['has_archive'] = 'evenements';
    $args['rewrite']     = ['slug' => 'evenements', 'with_front' => false];

    // On reste sur l'éditeur classique : les métaboîtes du plugin
    // sont écrites pour lui.
    $args['show_in_rest'] = false;

    return $args;
}, 10, 2);

/**
 * Adresse d'un mois de l'agenda, filtres de type et de ville conservés.
 * Sert à la navigation prev/suivant du calendrier.
 */
function ps_evt_url_calendrier($base, $annee, $mois, $type = '', $ville = '') {
    $args = ['vue' => 'calendrier', 'mois' => sprintf('%04d-%02d', $annee, $mois)];
    if ($type !== '')  $args['type']  = $type;
    if ($ville !== '') $args['ville'] = $ville;
    return add_query_arg($args, $base);
}

/**
 * La catégorie du plugin sert de « type d'événement » côté site :
 * elle doit donc être visible et modifiable dans l'administration.
 */
add_filter('register_taxonomy_args', function ($args, $taxonomy) {
    if (!defined('CFEB_TAX') || $taxonomy !== CFEB_TAX) return $args;

    $args['labels'] = array_merge((array) ($args['labels'] ?? []), [
        'name'          => __('Types d\'événement', 'poivre-sens'),
        'singular_name' => __('Type',               'poivre-sens'),
    ]);
    $args['public']            = true;
    $args['show_ui']           = true;
    $args['show_admin_column'] = true;
    $args['rewrite']           = ['slug' => 'type-evenement'];

    return $args;
}, 10, 2);

/* ═══════════════════════════════════════════════════════════
   UNE SEULE SOURCE POUR LES BALISES
   ═══════════════════════════════════════════════════════════
   Le plugin ajoute ses propres Open Graph et données structurées
   sur la fiche d'un événement, en doublon de celles du thème —
   qui, elles, valent pour tout le site et connaissent la
   compagnie (PerformingGroup, tarif en toutes lettres, complet).
   On retire donc les siennes.

   Ces greffons sont posés au chargement du plugin, donc déjà en
   place ici : ce fichier est inclus depuis functions.php, après
   les extensions.
   ═══════════════════════════════════════════════════════════ */
if (class_exists('CF_JsonLd')) {
    remove_action('wp_head', ['CF_JsonLd', 'init']);
}
if (class_exists('CF_OpenGraph')) {
    remove_action('wp_head', ['CF_OpenGraph', 'init']);
}

/**
 * Les adresses des événements changent de main : quand on passe de
 * l'ancien module au plugin (ou l'inverse), les règles de réécriture
 * doivent être régénérées, sinon /evenements/ renvoie une 404.
 * On le fait une seule fois, à la bascule.
 */
add_action('admin_init', function () {
    $etat = ps_evt_cpt();
    if (get_option('ps_evt_source_reecriture') !== $etat) {
        flush_rewrite_rules();
        update_option('ps_evt_source_reecriture', $etat);
    }
});
