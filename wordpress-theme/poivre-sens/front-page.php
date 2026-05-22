<?php
/**
 * front-page.php — Page d'accueil Poivre & Sens
 *
 * Le contenu est édité dans l'éditeur Gutenberg de la page « Accueil ».
 * Pour démarrer, ouvrez la page et insérez le pattern :
 *   Blocs › Patterns › Poivre & Sens › Page d'accueil complète
 *
 * Toutes les sections sont des blocs Gutenberg natifs éditables :
 *   Hero · Manifeste · Artistes · Références/influences
 *   Projet artistique · Nos activités · Esthétique · Contact
 *
 * Les shortcodes suivants restent dynamiques (ne pas supprimer) :
 *   [ps_galerie]     — galerie depuis le CPT « Photo »
 *   [ps_evenements]  — agenda depuis le CPT « Événement »
 *   [ps_newsletter]  — formulaire d'abonnement
 */
get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
endif;

get_footer();
