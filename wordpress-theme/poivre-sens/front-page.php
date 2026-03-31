<?php
/**
 * front-page.php — Page d'accueil Poivre & Sens
 *
 * Le contenu est édité dans l'éditeur Gutenberg de la page « Accueil ».
 * Pour démarrer, ouvrez la page et insérez le pattern :
 *   Blocs › Patterns › Poivre & Sens › Page d'accueil complète
 *
 * Sections éditables directement (blocs Gutenberg) :
 *   Hero · Manifeste · Artistes · Esthétique citation · Contact
 *
 * Sections dynamiques (shortcodes inclus dans le pattern) :
 *   [ps_galerie]  [ps_evenements]  [ps_newsletter]
 *   [ps_projet]   [ps_activites]   [ps_influences]  [ps_valeurs]
 */
get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
endif;

get_footer();
