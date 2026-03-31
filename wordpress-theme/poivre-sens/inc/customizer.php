<?php
/**
 * Poivre & Sens — Customizer WordPress
 *
 * Rend toutes les zones de texte de la page d'accueil éditables depuis
 * Apparence › Personnaliser, avec aperçu en direct (live preview).
 *
 * Organisation :
 *   Panel "Page d'accueil"
 *     ├─ Section Hero
 *     ├─ Section Manifeste
 *     ├─ Section Artiste — Ambre Lavignac
 *     ├─ Section Artiste — Ewen d'Aviau
 *     ├─ Section Esthétique (citation)
 *     ├─ Section Contact
 *     └─ Section Footer
 */
defined('ABSPATH') || exit;

add_action('customize_register', function (WP_Customize_Manager $wp_customize) {

    /* ─────────────────────────────────────────────────────────────────
       PANEL PRINCIPAL
    ───────────────────────────────────────────────────────────────── */
    $wp_customize->add_panel('ps_homepage', [
        'title'       => __('🌶 Page d\'accueil', 'poivre-sens'),
        'description' => __('Modifiez tous les textes de la page d\'accueil. L\'aperçu se met à jour en temps réel.', 'poivre-sens'),
        'priority'    => 30,
    ]);

    /* ═══════════════════════════════════════════════════════════════
       1. HERO
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_hero', [
        'title'    => __('① Hero — En-tête', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 10,
    ]);

    ps_add_text($wp_customize, 'hero_surtitle',
        __('Sur-titre', 'poivre-sens'), 'ps_hero',
        'Compagnie artistique · Association loi 1901',
        'text'
    );
    ps_add_text($wp_customize, 'hero_disciplines',
        __('Disciplines (une par ligne)', 'poivre-sens'), 'ps_hero',
        "Danse contemporaine\nContact-improvisation\nMusique improvisée\nPratiques somatiques",
        'textarea'
    );
    ps_add_text($wp_customize, 'hero_cta_label',
        __('Texte du bouton CTA', 'poivre-sens'), 'ps_hero',
        'Découvrir la compagnie',
        'text'
    );
    ps_add_text($wp_customize, 'hero_quote',
        __('Citation (droite)', 'poivre-sens'), 'ps_hero',
        'Le corps sait ce que l\'esprit cherche encore.',
        'textarea'
    );
    ps_add_text($wp_customize, 'hero_intro',
        __('Texte d\'introduction (droite)', 'poivre-sens'), 'ps_hero',
        'Née de la rencontre d\'un corps et d\'un son, d\'une main qui écoute et d\'une oreille qui se déplace, la compagnie explore les espaces de porosité entre le mouvement et la musique.',
        'textarea'
    );

    /* ═══════════════════════════════════════════════════════════════
       2. MANIFESTE
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_manifeste', [
        'title'    => __('② Manifeste', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 20,
    ]);

    ps_add_text($wp_customize, 'manifeste_titre',
        __('Titre', 'poivre-sens'), 'ps_manifeste',
        'Une rencontre entre le corps et le son',
        'text'
    );
    ps_add_text($wp_customize, 'manifeste_titre_em1',
        __('Mot(s) mis en valeur 1 (italique doré)', 'poivre-sens'), 'ps_manifeste',
        'le corps',
        'text'
    );
    ps_add_text($wp_customize, 'manifeste_titre_em2',
        __('Mot(s) mis en valeur 2 (italique doré)', 'poivre-sens'), 'ps_manifeste',
        'le son',
        'text'
    );
    ps_add_text($wp_customize, 'manifeste_p1',
        __('Paragraphe 1', 'poivre-sens'), 'ps_manifeste',
        'La compagnie explore les espaces de porosité entre le mouvement et le son, entre la structure et le lâcher-prise, entre la transmission d\'un savoir et l\'ouverture à l\'inconnu. Ses créations ne cherchent pas à illustrer ni à démontrer, mais à <em>habiter</em>.',
        'textarea'
    );
    ps_add_text($wp_customize, 'manifeste_p2',
        __('Paragraphe 2', 'poivre-sens'), 'ps_manifeste',
        'Ce qui unit leurs univers, c\'est la qualité de présence : être là, pleinement, dans l\'instant d\'une rencontre — entre deux corps, entre un corps et un instrument, entre une sensation et une image, entre ce qui est attendu et ce qui surgit.',
        'textarea'
    );
    ps_add_text($wp_customize, 'manifeste_p3',
        __('Paragraphe 3', 'poivre-sens'), 'ps_manifeste',
        'Inspirée du Tao, des méridiens, de l\'aïkido et de la lutherie, la compagnie croit en l\'<em>artisanat du spectacle</em> : chaque geste compte, chaque son est matière, chaque silence est espace.',
        'textarea'
    );

    /* ═══════════════════════════════════════════════════════════════
       3. ARTISTE — AMBRE
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_ambre', [
        'title'    => __('③ Artiste — Ambre Lavignac', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 30,
    ]);

    ps_add_text($wp_customize, 'ambre_nom',
        __('Nom', 'poivre-sens'), 'ps_ambre', 'Ambre Lavignac', 'text');
    ps_add_text($wp_customize, 'ambre_role',
        __('Rôle / Titre', 'poivre-sens'), 'ps_ambre',
        'Danseuse · Pédagogue · Praticienne du mouvement', 'text');
    ps_add_text($wp_customize, 'ambre_initiale',
        __('Initiale (avatar)', 'poivre-sens'), 'ps_ambre', 'A', 'text');
    ps_add_text($wp_customize, 'ambre_bio1',
        __('Biographie — paragraphe 1', 'poivre-sens'), 'ps_ambre',
        'Formée à la danse contemporaine, Ambre Lavignac oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens. Inspirée par la philosophie taoïste et la médecine traditionnelle chinoise, elle explore les correspondances entre les éléments naturels, les méridiens énergétiques et les qualités de mouvement.',
        'textarea');
    ps_add_text($wp_customize, 'ambre_bio2',
        __('Biographie — paragraphe 2', 'poivre-sens'), 'ps_ambre',
        'Praticienne du massage, elle travaille les liens entre le toucher, la conscience corporelle et la circulation de l\'énergie. En tant que chorégraphe, elle s\'intéresse à l\'improvisation comme espace de création vivante.',
        'textarea');
    ps_add_text($wp_customize, 'ambre_tags',
        __('Mots-clés (séparés par des virgules)', 'poivre-sens'), 'ps_ambre',
        'Danse contemporaine,Improvisation,Somatique,Tao,Méridiens,Massage,Pédagogie',
        'text');

    /* ═══════════════════════════════════════════════════════════════
       4. ARTISTE — EWEN
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_ewen', [
        'title'    => __('④ Artiste — Ewen d\'Aviau', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 40,
    ]);

    ps_add_text($wp_customize, 'ewen_nom',
        __('Nom', 'poivre-sens'), 'ps_ewen', "Ewen d'Aviau", 'text');
    ps_add_text($wp_customize, 'ewen_role',
        __('Rôle / Titre', 'poivre-sens'), 'ps_ewen',
        "Luthier-ingénieur · Musicien · Danseur", 'text');
    ps_add_text($wp_customize, 'ewen_initiale',
        __('Initiale (avatar)', 'poivre-sens'), 'ps_ewen', 'E', 'text');
    ps_add_text($wp_customize, 'ewen_bio1',
        __('Biographie — paragraphe 1', 'poivre-sens'), 'ps_ewen',
        "Ingénieur de formation, Ewen d'Aviau se tourne vers la lutherie pour explorer la fabrication des instruments à cordes comme geste à la fois artisanal, scientifique et artistique. Il conçoit le son comme une matière vivante, façonnable, imprévue.",
        'textarea');
    ps_add_text($wp_customize, 'ewen_bio2',
        __('Biographie — paragraphe 2', 'poivre-sens'), 'ps_ewen',
        "Musicien, il pratique l'improvisation libre avec une oreille particulière pour l'espace, le silence et la relation. Danseur, imprégné du contact-improvisation et de l'aïkido, il retient l'art de la redirection et de la présence active non agressive.",
        'textarea');
    ps_add_text($wp_customize, 'ewen_tags',
        __('Mots-clés (séparés par des virgules)', 'poivre-sens'), 'ps_ewen',
        "Lutherie,Musique improvisée,Contact-improvisation,Somatique,Aïkido,Enseignement",
        'text');

    /* ═══════════════════════════════════════════════════════════════
       5. ESTHÉTIQUE — citation
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_esthetique', [
        'title'    => __('⑤ Esthétique — Citation', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 50,
    ]);

    ps_add_text($wp_customize, 'esthet_cite_ligne1',
        __('Ligne 1 de la citation', 'poivre-sens'), 'ps_esthetique',
        "Habiter un espace de jeu partagé —", 'text');
    ps_add_text($wp_customize, 'esthet_cite_ligne2',
        __('Ligne 2 (texte normal)', 'poivre-sens'), 'ps_esthetique',
        "entre deux corps,", 'text');
    ps_add_text($wp_customize, 'esthet_cite_em',
        __('Ligne 3 (texte doré)', 'poivre-sens'), 'ps_esthetique',
        "un corps et un instrument", 'text');
    ps_add_text($wp_customize, 'esthet_cite_source',
        __('Source de la citation', 'poivre-sens'), 'ps_esthetique',
        "Poivre & Sens · Note d'intention", 'text');

    /* ═══════════════════════════════════════════════════════════════
       6. CONTACT
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_contact', [
        'title'    => __('⑥ Contact', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 60,
    ]);

    ps_add_text($wp_customize, 'contact_nom',
        __('Nom de la compagnie', 'poivre-sens'), 'ps_contact',
        'Poivre & Sens', 'text');
    ps_add_text($wp_customize, 'contact_statut',
        __('Statut juridique', 'poivre-sens'), 'ps_contact',
        'Association loi 1901', 'text');
    ps_add_text($wp_customize, 'contact_direction',
        __('Direction artistique', 'poivre-sens'), 'ps_contact',
        'Ambre Lavignac & Ewen d\'Aviau', 'text');
    ps_add_text($wp_customize, 'contact_disciplines',
        __('Disciplines (affichées en contact)', 'poivre-sens'), 'ps_contact',
        'Danse · Contact-improvisation · Musique · Somatique', 'text');
    ps_add_text($wp_customize, 'contact_email',
        __('E-mail général', 'poivre-sens'), 'ps_contact',
        'contact@cie.poivresens.fr', 'text');
    ps_add_text($wp_customize, 'contact_site',
        __('URL du site (affiché)', 'poivre-sens'), 'ps_contact',
        'cie.poivresens.fr', 'text');
    ps_add_text($wp_customize, 'contact_email_ambre',
        __('E-mail Ambre', 'poivre-sens'), 'ps_contact',
        'ambre@cie.poivresens.fr', 'text');
    ps_add_text($wp_customize, 'contact_email_ewen',
        __('E-mail Ewen', 'poivre-sens'), 'ps_contact',
        'ewen@cie.poivresens.fr', 'text');
    ps_add_text($wp_customize, 'contact_note_reseaux',
        __('Note "Suivre la compagnie"', 'poivre-sens'), 'ps_contact',
        'Retrouvez Poivre & Sens dans les réseaux du spectacle vivant, les festivals de contact-improvisation et les scènes de musique improvisée en France et en Europe.',
        'textarea');

    /* ═══════════════════════════════════════════════════════════════
       7. FOOTER
    ═══════════════════════════════════════════════════════════════ */
    $wp_customize->add_section('ps_footer', [
        'title'    => __('⑦ Pied de page', 'poivre-sens'),
        'panel'    => 'ps_homepage',
        'priority' => 70,
    ]);

    ps_add_text($wp_customize, 'footer_line1',
        __('Ligne 1 (type compagnie)', 'poivre-sens'), 'ps_footer',
        'Compagnie de danse et musique improvisées · Association loi 1901',
        'text');
    ps_add_text($wp_customize, 'footer_line2',
        __('Ligne 2 (direction)', 'poivre-sens'), 'ps_footer',
        'Direction artistique : Ambre Lavignac & Ewen d\'Aviau',
        'text');
});

/* ─────────────────────────────────────────────────────────────────
   Helper : créer un réglage + un contrôle en une seule ligne
───────────────────────────────────────────────────────────────── */
function ps_add_text(
    WP_Customize_Manager $wpc,
    string $id,
    string $label,
    string $section,
    string $default,
    string $type = 'text'
): void {
    $wpc->add_setting('ps_' . $id, [
        'default'           => $default,
        'sanitize_callback' => $type === 'textarea' ? 'wp_kses_post' : 'sanitize_text_field',
        'transport'         => 'postMessage', // live preview sans rechargement
    ]);
    $wpc->add_control('ps_' . $id, [
        'label'   => $label,
        'section' => $section,
        'type'    => $type,
        'settings'=> 'ps_' . $id,
    ]);
}

/* ─────────────────────────────────────────────────────────────────
   Live Preview — JS partiel (postMessage)
   Pour les éléments non-rechargés via postMessage on laisse
   le fallback "refresh" pour les blocs complexes.
───────────────────────────────────────────────────────────────── */
add_action('customize_preview_init', function () {
    wp_enqueue_script(
        'ps-customizer-preview',
        get_template_directory_uri() . '/assets/js/customizer-preview.js',
        ['customize-preview', 'jquery'],
        wp_get_theme()->get('Version'),
        true
    );
});
