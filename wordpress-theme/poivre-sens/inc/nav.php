<?php
/**
 * Poivre & Sens — Menu de la page d'accueil, calculé depuis la page
 *
 * Le menu affiché dans l'en-tête (Galerie, Projet, Artistes…) n'est plus
 * une liste écrite à la main : il est déduit de l'ordre réel des sections
 * sur la page d'accueil. Réorganiser ou supprimer une section dans
 * l'éditeur de blocs met donc le menu à jour tout seul, au prochain
 * chargement — sans repasser par le thème.
 *
 * Deux façons dont une section se reconnaît dans le contenu de la page :
 *   · un bloc natif portant une ancre (attribut « HTML anchor » du bloc,
 *     ex. le groupe « section » d'Artistes, Esthétique, Contact) ;
 *   · un bloc Shortcode dont le tag est connu (Galerie, Projet, Activités,
 *     Événements, Newsletter rendent eux-mêmes leur <section id="…">,
 *     invisible à l'attribut « ancre » du bloc).
 *
 * Seuls les blocs de premier niveau comptent — comme dans le panneau
 * « Plan » de l'éditeur, qu'on a indiqué à l'utilisateur pour réorganiser
 * les sections.
 */
defined('ABSPATH') || exit;

function ps_nav_sections_accueil(): array {
    $labels_ancre = [
        'artistes'   => __('Artistes', 'poivre-sens'),
        'esthetique' => __('Esthétique', 'poivre-sens'),
        'contact'    => __('Contact', 'poivre-sens'),
        // Projet et Activités sont aujourd'hui des shortcodes (voir
        // $labels_shortcode ci-dessous) — mais un bloc natif portant
        // directement anchor="projet"/"activites" (un pattern antérieur
        // à cette PR, ou une page modifiée à la main) doit être reconnu
        // tout autant : un signalement de revue (PR #20) a montré que
        // s'en remettre à une seule représentation fait disparaître ces
        // deux entrées dès que l'autre se présente.
        'projet'     => __('Projet', 'poivre-sens'),
        'activites'  => __('Activités', 'poivre-sens'),
    ];
    $labels_shortcode = [
        'ps_galerie'    => ['galerie',    __('Galerie', 'poivre-sens')],
        'ps_projet'     => ['projet',     __('Projet', 'poivre-sens')],
        'ps_activites'  => ['activites',  __('Activités', 'poivre-sens')],
        'ps_evenements' => ['evenements', __('Événements', 'poivre-sens')],
        'ps_newsletter' => ['newsletter', __('Newsletter', 'poivre-sens')],
    ];

    $sections = [];
    $front_id = (int) get_option('page_on_front');
    $post     = $front_id ? get_post($front_id) : null;

    if ($post && function_exists('parse_blocks')) {
        foreach (parse_blocks($post->post_content) as $bloc) {
            $ancre = $bloc['attrs']['anchor'] ?? null;
            if ($ancre && isset($labels_ancre[$ancre])) {
                $sections[] = ['ancre' => $ancre, 'label' => $labels_ancre[$ancre]];
                continue;
            }
            if (($bloc['blockName'] ?? '') === 'core/shortcode') {
                $contenu = trim((string) ($bloc['innerHTML'] ?? ''));
                foreach ($labels_shortcode as $tag => [$ancre_sc, $label]) {
                    if ($contenu === "[{$tag}]") {
                        $sections[] = ['ancre' => $ancre_sc, 'label' => $label];
                        break;
                    }
                }
            }
        }
    }

    // Filet de sécurité : page d'accueil pas encore configurée ou vide —
    // plutôt qu'un menu sans rien dedans, l'ordre par défaut du thème.
    if (!$sections) {
        $sections = [
            ['ancre' => 'galerie',    'label' => __('Galerie', 'poivre-sens')],
            ['ancre' => 'projet',     'label' => __('Projet', 'poivre-sens')],
            ['ancre' => 'artistes',   'label' => __('Artistes', 'poivre-sens')],
            ['ancre' => 'activites',  'label' => __('Activités', 'poivre-sens')],
            ['ancre' => 'evenements', 'label' => __('Événements', 'poivre-sens')],
            ['ancre' => 'esthetique', 'label' => __('Esthétique', 'poivre-sens')],
            ['ancre' => 'newsletter', 'label' => __('Newsletter', 'poivre-sens')],
            ['ancre' => 'contact',    'label' => __('Contact', 'poivre-sens')],
        ];
    }

    return $sections;
}
