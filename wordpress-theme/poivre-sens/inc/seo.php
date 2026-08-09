<?php
/**
 * inc/seo.php
 *
 * Balises minimales d'indexation et de partage, absentes du thème :
 *   - meta description
 *   - Open Graph / Twitter (aperçu lors des partages)
 *   - données structurées JSON-LD décrivant la compagnie (schema.org)
 *
 * Les données structurées aident Google à comprendre que « Poivre & Sens »,
 * « Poivre et Sens » et « Compagnie Poivre & Sens » désignent la même
 * entité — utile pour les recherches sur le nom de la compagnie.
 *
 * Si une extension SEO (Yoast, Rank Math, SEOPress, All in One SEO) est
 * active, ce fichier s'efface : c'est elle qui gère ces balises, et les
 * dupliquer nuirait au référencement.
 */
defined('ABSPATH') || exit;

/** Une extension SEO gère-t-elle déjà ces balises ? */
function ps_seo_plugin_actif() {
    return defined('WPSEO_VERSION')            // Yoast
        || class_exists('RankMath')            // Rank Math
        || defined('SEOPRESS_VERSION')         // SEOPress
        || defined('AIOSEO_VERSION');          // All in One SEO
}

/**
 * Variantes du nom de la compagnie.
 * Déclarer « Poivre et Sens » à côté de « Poivre & Sens » aide Google à
 * relier les deux graphies à une même entité, et donc à faire ressortir
 * le site sur des recherches du type « poivre et sens compagnie ».
 */
function ps_seo_name_variants() {
    $saisie = get_theme_mod('ps_seo_alt_names', ps_seo_alt_names_default());

    $noms = array_map('trim', explode("\n", (string) $saisie));
    $noms = array_filter($noms, function ($n) { return $n !== ''; });

    return array_values(array_unique($noms));
}

/** Variantes proposées par défaut (modifiables dans le Customizer). */
function ps_seo_alt_names_default() {
    return "Compagnie Poivre & Sens\nCie Poivre & Sens\nPoivre et Sens\nCompagnie Poivre et Sens";
}

/* ── Réglage éditable : variantes du nom ──────────────────── */
add_action('customize_register', function (\WP_Customize_Manager $wp_customize) {
    $wp_customize->add_section('ps_seo', [
        'title'       => __('Référencement (SEO)', 'poivre-sens'),
        'description' => __('Aide Google à reconnaître votre compagnie, quelle que soit la façon dont son nom est écrit.', 'poivre-sens'),
        'priority'    => 30,
    ]);

    $wp_customize->add_setting('ps_seo_alt_names', [
        'default'           => ps_seo_alt_names_default(),
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);

    $wp_customize->add_control('ps_seo_alt_names', [
        'label'       => __('Autres façons d\'écrire le nom', 'poivre-sens'),
        'description' => __('Une par ligne. Déclarer « Poivre et Sens » à côté de « Poivre & Sens » aide Google à comprendre qu\'il s\'agit de la même compagnie. Ces variantes sont transmises à Yoast si l\'extension est active.', 'poivre-sens'),
        'section'     => 'ps_seo',
        'type'        => 'textarea',
        'input_attrs' => ['rows' => 5],
    ]);
});

/**
 * Yoast gère lui-même les données structurées : on lui ajoute seulement
 * les variantes du nom, plutôt que d'émettre un second bloc concurrent.
 * Ce filtre ne se déclenche que si Yoast est actif.
 */
add_filter('wpseo_schema_organization', function ($data) {
    $existant = $data['alternateName'] ?? [];
    if (is_string($existant)) $existant = [$existant];
    if (!is_array($existant))  $existant = [];

    $noms = array_values(array_unique(array_filter(
        array_merge($existant, ps_seo_name_variants()),
        function ($n) use ($data) {
            // Inutile de répéter le nom principal dans les variantes.
            return is_string($n) && $n !== '' && $n !== ($data['name'] ?? '');
        }
    )));

    if ($noms) $data['alternateName'] = $noms;
    return $data;
});

/* ═══════════════════════════════════════════════════════════
   DONNÉES STRUCTURÉES DES ÉVÉNEMENTS (schema.org/Event)
   ═══════════════════════════════════════════════════════════
   Ni Yoast (même Premium) ni les autres extensions SEO ne génèrent de
   schéma « Event » pour un type de contenu personnalisé. Ce bloc est donc
   émis même lorsqu'une extension SEO est active : il ne fait doublon avec
   rien, et permet à Google d'afficher date, lieu et billetterie
   directement dans les résultats de recherche.
   ═══════════════════════════════════════════════════════════ */

/** Combine une date (Y-m-d) et une heure (H:i) en ISO 8601 avec fuseau. */
function ps_seo_event_datetime($date, $heure = '') {
    if (!$date) return '';
    $heure = $heure ?: '00:00';
    try {
        $dt = new DateTime($date . ' ' . $heure, wp_timezone());
    } catch (Exception $e) {
        return '';
    }
    return $dt->format('c');
}

/**
 * Tarif exploitable par Google : un nombre, ou 0 si l'entrée dit « gratuit ».
 * Renvoie null si le texte libre n'est pas interprétable (« sur réservation »,
 * « prix libre »…), auquel cas on n'annonce pas de prix plutôt que d'en
 * inventer un.
 */
function ps_seo_event_price($texte) {
    $texte = trim((string) $texte);
    if ($texte === '') return null;
    if (preg_match('/gratuit|libre|offert|free/i', $texte)) return '0';
    // Premier nombre rencontré : « 12€ », « 12,50 € », « 10 à 15 € » → 10
    if (preg_match('/(\d+(?:[.,]\d{1,2})?)/', $texte, $m)) {
        return str_replace(',', '.', $m[1]);
    }
    return null;
}

/** Construit le schéma Event d'un événement, ou null si inexploitable. */
function ps_seo_event_schema($post_id) {
    $date = get_post_meta($post_id, '_evt_date', true);
    if (!$date) return null; // sans date, pas d'événement valide pour Google

    $debut = ps_seo_event_datetime($date, get_post_meta($post_id, '_evt_heure', true));
    if (!$debut) return null;

    $lieu    = get_post_meta($post_id, '_evt_lieu', true);
    $adresse = get_post_meta($post_id, '_evt_adresse', true);
    $ville   = get_post_meta($post_id, '_evt_ville', true);
    $prix    = get_post_meta($post_id, '_evt_prix', true);
    $billet  = get_post_meta($post_id, '_evt_billetterie', true);
    $complet = get_post_meta($post_id, '_evt_complet', true) === '1';
    $fin     = get_post_meta($post_id, '_evt_heure_fin', true);

    $compagnie = [
        '@type' => 'PerformingGroup',
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];

    $schema = [
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => get_the_title($post_id),
        'startDate'           => $debut,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'url'                 => get_permalink($post_id),
        'performer'           => $compagnie,
        'organizer'           => $compagnie,
    ];

    if ($fin) {
        $date_fin = ps_seo_event_datetime($date, $fin);
        // Une heure de fin antérieure au début signifie un passage après minuit.
        if ($date_fin && $date_fin < $debut) {
            $lendemain = date('Y-m-d', strtotime($date . ' +1 day'));
            $date_fin  = ps_seo_event_datetime($lendemain, $fin);
        }
        if ($date_fin) $schema['endDate'] = $date_fin;
    }

    $desc = get_the_excerpt($post_id) ?: wp_strip_all_tags(get_post_field('post_content', $post_id));
    $desc = trim(preg_replace('/\s+/', ' ', $desc));
    if ($desc) $schema['description'] = wp_html_excerpt($desc, 300, '…');

    if (has_post_thumbnail($post_id)) {
        $img = get_the_post_thumbnail_url($post_id, 'evt-thumbnail');
        if ($img) $schema['image'] = $img;
    }

    // Lieu : Google exige au minimum un nom ou une adresse.
    if ($lieu || $adresse || $ville) {
        $place = ['@type' => 'Place', 'name' => $lieu ?: $ville];
        $postal = ['@type' => 'PostalAddress', 'addressCountry' => 'FR'];
        if ($adresse) $postal['streetAddress']   = $adresse;
        if ($ville)   $postal['addressLocality'] = $ville;
        $place['address'] = $postal;
        $schema['location'] = $place;
    }

    // Offre : uniquement si l'on dispose d'un prix exploitable ou d'un lien.
    $montant = ps_seo_event_price($prix);
    if ($montant !== null || $billet) {
        $offre = [
            '@type'         => 'Offer',
            'availability'  => $complet
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
            'url'           => $billet ?: get_permalink($post_id),
        ];
        if ($montant !== null) {
            $offre['price']         = $montant;
            $offre['priceCurrency'] = 'EUR';
        }
        $schema['offers'] = $offre;
    }

    /** Permet d'ajuster le schéma d'un événement. */
    return apply_filters('ps_seo_event_schema', $schema, $post_id);
}

add_action('wp_head', function () {
    if (!is_singular('evenement')) return;
    $schema = ps_seo_event_schema(get_the_ID());
    if (!$schema) return;

    echo "\n<!-- Poivre & Sens — événement -->\n"
       . '<script type="application/ld+json">'
       . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
       . '</script>' . "\n";
}, 6);

/** Description de la page courante (160 caractères max, comme Google). */
function ps_seo_description() {
    $desc = '';
    if (is_front_page()) {
        $desc = get_bloginfo('description');
    } elseif (is_singular()) {
        $post = get_queried_object();
        $desc = has_excerpt($post) ? get_the_excerpt($post) : wp_strip_all_tags($post->post_content ?? '');
    } elseif (is_post_type_archive('evenement')) {
        $desc = __('Prochaines dates de la Compagnie Poivre & Sens : spectacles, jams de contact-improvisation, ateliers et résidences.', 'poivre-sens');
    }
    $desc = trim(preg_replace('/\s+/', ' ', (string) $desc));
    if ($desc === '') {
        $desc = get_bloginfo('description');
    }
    return wp_html_excerpt($desc, 160, '…');
}

/**
 * Injecte description, Open Graph et JSON-LD dans le <head>.
 * Priorité 5 : avant les éventuels ajouts d'extensions.
 */
add_action('wp_head', function () {
    if (ps_seo_plugin_actif()) return;
    if (is_404() || is_search()) return;

    $nom   = get_bloginfo('name');
    $desc  = ps_seo_description();
    $url   = is_front_page() ? home_url('/') : get_permalink();
    $titre = is_front_page() ? $nom : wp_get_document_title();

    // Image de partage : image à la une, sinon rien (pas de logo dans le thème).
    $image = '';
    if (is_singular() && has_post_thumbnail()) {
        $image = get_the_post_thumbnail_url(null, 'evt-thumbnail');
    }

    echo "\n<!-- Poivre & Sens — SEO -->\n";
    printf('<meta name="description" content="%s">' . "\n", esc_attr($desc));

    printf('<meta property="og:type" content="%s">' . "\n", is_singular() && !is_front_page() ? 'article' : 'website');
    printf('<meta property="og:site_name" content="%s">' . "\n", esc_attr($nom));
    printf('<meta property="og:title" content="%s">' . "\n", esc_attr($titre));
    printf('<meta property="og:description" content="%s">' . "\n", esc_attr($desc));
    printf('<meta property="og:url" content="%s">' . "\n", esc_url($url));
    printf('<meta property="og:locale" content="%s">' . "\n", esc_attr(get_locale()));
    if ($image) printf('<meta property="og:image" content="%s">' . "\n", esc_url($image));

    printf('<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary');
    printf('<meta name="twitter:title" content="%s">' . "\n", esc_attr($titre));
    printf('<meta name="twitter:description" content="%s">' . "\n", esc_attr($desc));
    if ($image) printf('<meta name="twitter:image" content="%s">' . "\n", esc_url($image));

    // ── Données structurées : l'entité « compagnie » ──────────
    if (is_front_page()) {
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'PerformingGroup',
            'name'          => $nom,
            // Variantes du nom : aide Google à relier les recherches
            // « poivre et sens », « cie poivre & sens », etc.
            'alternateName' => ps_seo_name_variants(),
            'url'           => home_url('/'),
            'description'   => $desc,
            'address'       => [
                '@type'           => 'PostalAddress',
                'addressLocality' => 'Saint-Nazaire',
                'postalCode'      => '44600',
                'addressCountry'  => 'FR',
            ],
            'knowsAbout'    => [
                __('Danse contemporaine', 'poivre-sens'),
                __('Contact-improvisation', 'poivre-sens'),
                __('Musique improvisée', 'poivre-sens'),
            ],
        ];

        /**
         * Permet de compléter les données structurées, par exemple pour
         * ajouter 'sameAs' => ['https://instagram.com/…', …] une fois les
         * réseaux sociaux en place.
         */
        $schema = apply_filters('ps_seo_schema', $schema);

        echo '<script type="application/ld+json">'
           . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
           . '</script>' . "\n";
    }
}, 5);
