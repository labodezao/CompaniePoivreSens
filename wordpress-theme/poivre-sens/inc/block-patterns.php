<?php
/**
 * Poivre & Sens — Shortcodes & Patterns Gutenberg
 *
 * Remplace admin-options.php. Le contenu de la page d'accueil est
 * désormais édité directement dans l'éditeur Gutenberg.
 *
 * Shortcodes disponibles (à insérer via un bloc « Shortcode ») :
 *   [ps_galerie]     — galerie photos (CPT)
 *   [ps_evenements]  — prochains événements (CPT)
 *   [ps_newsletter]  — formulaire d'inscription newsletter
 *   [ps_projet]      — section projet artistique (axes)
 *   [ps_influences]  — références & influences
 *   [ps_activites]   — nos activités + axes de diffusion
 *   [ps_valeurs]     — valeurs esthétiques (colonne gauche)
 */
defined('ABSPATH') || exit;

/* ══════════════════════════════════════════════════════════════
   1. SHORTCODES — Sections dynamiques & complexes
   ══════════════════════════════════════════════════════════════ */

/** [ps_galerie] — Galerie photos depuis le CPT "galerie" */
add_shortcode('ps_galerie', function (): string {
    ob_start();
    $theme_img    = get_template_directory_uri() . '/images/';
    $svg_slugs    = ['spectacle', 'jam', 'ewen', 'ambre', 'residence', 'atelier'];
    $svg_caps_def = [
        ['En scène',              'Spectacle vivant · Création'],
        ['Jam de contact',        'Contact-improvisation · Rencontre ouverte'],
        ["Ewen d'Aviau",          'Luthier · Musicien · Danseur'],
        ['Ambre Lavignac',        'Danseuse · Pédagogue · Praticienne'],
        ['En résidence',          'Laboratoire artistique · Recherche'],
        ['Pédagogie du sensible', 'Atelier · Stage · Transmission'],
    ];
    $q     = new WP_Query([
        'post_type'      => 'galerie',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);
    $items = [];
    if ($q->have_posts()) {
        $i = 0;
        while ($q->have_posts() && $i < 6) {
            $q->the_post();
            $items[] = [
                'img'     => get_the_post_thumbnail_url(null, 'galerie-thumb')
                             ?: ($theme_img . 'galerie-0' . ($i + 1) . '-' . $svg_slugs[$i] . '.svg'),
                'alt'     => get_the_title(),
                'titre'   => get_the_title(),
                'caption' => get_post_meta(get_the_ID(), '_galerie_caption', true)
                             ?: ($svg_caps_def[$i][1] ?? ''),
            ];
            $i++;
        }
        wp_reset_postdata();
    }
    for ($i = count($items); $i < 6; $i++) {
        $items[] = [
            'img'     => $theme_img . 'galerie-0' . ($i + 1) . '-' . $svg_slugs[$i] . '.svg',
            'alt'     => $svg_caps_def[$i][0],
            'titre'   => $svg_caps_def[$i][0],
            'caption' => $svg_caps_def[$i][1],
        ];
    }
    ?>
    <section class="galerie sec2" id="galerie" aria-labelledby="titre-galerie">
      <div class="galerie__hdr">
        <div>
          <p class="lbl">Galerie</p>
          <h2 class="galerie__t" id="titre-galerie">Images de la compagnie</h2>
          <div class="regle"></div>
        </div>
        <p class="galerie__n">Photos de la compagnie — ajoutez vos clichés via<br>
          <strong>Galerie › Ajouter</strong> dans l'admin WordPress.</p>
      </div>
      <div class="galerie__g" role="list">
        <?php foreach ($items as $item) : ?>
        <figure class="photo" role="listitem" aria-label="<?= esc_attr($item['titre']) ?>">
          <img src="<?= esc_url($item['img']) ?>" alt="<?= esc_attr($item['alt']) ?>" loading="lazy">
          <div class="phcap">
            <p class="phcap-t"><?= esc_html($item['titre']) ?></p>
            <p class="phcap-d"><?= esc_html($item['caption']) ?></p>
          </div>
        </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_evenements] — Prochains événements depuis le CPT "evenement" */
add_shortcode('ps_evenements', function (): string {
    ob_start();
    $q        = ps_get_upcoming_events(3);
    $today    = date('Y-m-d');
    $jours_fr = ['Sun' => 'Dim', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer',
                 'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam'];
    ?>
    <section class="sec sec2" id="evenements" aria-labelledby="titre-evts">
      <div style="margin-bottom:40px">
        <p class="lbl">Agenda</p>
        <h2 class="sh" id="titre-evts">Prochains événements</h2>
        <div class="regle"></div>
      </div>
      <?php if ($q->have_posts()) : ?>
      <div class="cal-list cal-list--compact">
        <?php while ($q->have_posts()) : $q->the_post();
          $d  = get_post_meta(get_the_ID(), '_evt_date',        true);
          $h  = get_post_meta(get_the_ID(), '_evt_heure',       true);
          $l  = get_post_meta(get_the_ID(), '_evt_lieu',        true);
          $v  = get_post_meta(get_the_ID(), '_evt_ville',       true);
          $ty = get_post_meta(get_the_ID(), '_evt_type',        true);
          $p  = get_post_meta(get_the_ID(), '_evt_prix',        true);
          $b  = get_post_meta(get_the_ID(), '_evt_billetterie', true);
          $cp = get_post_meta(get_the_ID(), '_evt_complet',     true);
          $ts = $d ? strtotime($d) : 0;
        ?>
        <div class="cal-list__event <?= $d === $today ? 'cal-list__event--today' : '' ?>">
          <div class="cal-list__date">
            <span class="cal-list__day-ltr"><?= $ts ? esc_html($jours_fr[date('D', $ts)] ?? date('D', $ts)) : '' ?></span>
            <span class="cal-list__day-num"><?= $ts ? esc_html(date('j', $ts)) : '?' ?></span>
            <span style="font-size:.6rem;color:var(--or);letter-spacing:.1em;text-transform:uppercase"><?= $ts ? esc_html(date_i18n('M', $ts)) : '' ?></span>
          </div>
          <div class="cal-list__line" aria-hidden="true"></div>
          <div class="cal-list__body">
            <?php if ($ty) : ?><span class="cal-list__type"><?= esc_html(ps_evt_type_label($ty)) ?></span><?php endif; ?>
            <?php if ($cp) : ?><span class="cal-list__complet"><?php _e('Complet', 'poivre-sens'); ?></span><?php endif; ?>
            <h3 class="cal-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <ul class="cal-list__meta" role="list">
              <?php if ($h) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🕐</span><?= esc_html($h) ?></li><?php endif; ?>
              <?php if ($l || $v) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">📍</span><?= esc_html(implode(', ', array_filter([$l, $v]))) ?></li><?php endif; ?>
              <?php if ($p) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🎟</span><?= esc_html($p) ?></li><?php endif; ?>
            </ul>
            <div class="cal-list__actions">
              <a href="<?php the_permalink(); ?>" class="cal-list__action-link"><?php _e('En savoir plus', 'poivre-sens'); ?> →</a>
              <?php if ($b && !$cp) : ?><a href="<?= esc_url($b) ?>" class="cal-list__action-btn" target="_blank" rel="noopener"><?php _e('Réserver', 'poivre-sens'); ?></a><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <a href="<?= esc_url(get_post_type_archive_link('evenement')) ?>" class="evts__lien"><?php _e("Voir tout l'agenda", 'poivre-sens'); ?></a>
      <?php else : ?>
      <div style="padding:48px 0;text-align:center;color:var(--gris);font-size:.9rem">
        <?php _e('Aucun événement programmé pour le moment.', 'poivre-sens'); ?>
        <?php if (current_user_can('publish_posts')) : ?>
        <br><br><a href="<?= esc_url(admin_url('post-new.php?post_type=evenement')) ?>" class="evts__lien">+ <?php _e('Créer un événement', 'poivre-sens'); ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_newsletter] — Section formulaire newsletter */
add_shortcode('ps_newsletter', function (): string {
    ob_start();
    ?>
    <section class="sec sec3" id="newsletter" aria-labelledby="titre-nl">
      <?php get_template_part('template-parts/newsletter-form'); ?>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_projet] — Section projet artistique (axes créatifs) */
add_shortcode('ps_projet', function (): string {
    ob_start();
    ?>
    <section class="sec" id="projet" aria-labelledby="titre-projet">
      <div style="margin-bottom:56px">
        <p class="lbl">Note d'intention</p>
        <h2 class="sh" id="titre-projet">Le projet artistique</h2>
        <div class="regle"></div>
      </div>
      <div class="axes">
        <div class="axe">
          <p class="axe__n">01</p>
          <h3 class="axe__t">Création chorégraphique &amp; musicale</h3>
          <p class="axe__tx">Des pièces scéniques en duo ou avec artistes invités, où la frontière entre la composition musicale et la partition corporelle s'efface. Le musicien se déplace, la danseuse émet, le son se fait matière, le corps se fait résonance.</p>
        </div>
        <div class="axe">
          <p class="axe__n">02</p>
          <h3 class="axe__t">L'improvisation comme forme</h3>
          <p class="axe__tx">Non pas une absence de forme, mais une forme en devenir. Jams ouvertes, laboratoires de recherche, performances situées dans des espaces non conventionnels : parcs, friches industrielles, espaces naturels.</p>
        </div>
        <div class="axe">
          <p class="axe__n">03</p>
          <h3 class="axe__t">La pédagogie du sensible</h3>
          <p class="axe__tx">Stages, ateliers de mouvement somatique, ateliers de musique, résidences pédagogiques en milieu scolaire, médico-social ou en entreprise. La transmission est au cœur du projet.</p>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_influences] — Références & influences (dans la section artistes) */
add_shortcode('ps_influences', function (): string {
    ob_start();
    ?>
    <div>
      <p class="lbl" style="margin-top:0">Références &amp; influences</p>
      <div class="regle" style="margin-bottom:28px"></div>
      <div class="influences">
        <div class="inf"><p class="inf__n">Steve Paxton &amp; Lisa Nelson</p><p class="inf__d">Fondateurs du contact-improvisation</p></div>
        <div class="inf"><p class="inf__n">Anna Halprin &amp; Thomas Hanna</p><p class="inf__d">Éducation somatique et danse thérapie</p></div>
        <div class="inf"><p class="inf__n">John Cage &amp; Derek Bailey</p><p class="inf__d">Improvisation musicale et indétermination</p></div>
        <div class="inf"><p class="inf__n">Huangdi Neijing</p><p class="inf__d">Classique de médecine interne — méridiens</p></div>
        <div class="inf"><p class="inf__n">Ueshiba Morihei</p><p class="inf__d">Fondateur de l'aïkido</p></div>
        <div class="inf"><p class="inf__n">Peter Szendy &amp; Jean-Luc Nancy</p><p class="inf__d">Pensée du corps sonore</p></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/** [ps_activites] — Section nos activités + axes de diffusion */
add_shortcode('ps_activites', function (): string {
    ob_start();
    ?>
    <section class="sec" id="activites" aria-labelledby="titre-activites">
      <div style="margin-bottom:56px">
        <p class="lbl">Ce que nous proposons</p>
        <h2 class="sh" id="titre-activites">Nos activités</h2>
        <div class="regle"></div>
      </div>
      <ul role="list">
        <li class="act"><span class="act__n" aria-hidden="true">01</span><div><h3 class="act__t">Spectacles vivants</h3><p class="act__tx">Créations scéniques en duo ou en collaboration avec artistes invités, mêlant danse, improvisation et musique live. En festivals, théâtres et lieux non conventionnels.</p></div><span class="act__b">Scène</span></li>
        <li class="act"><span class="act__n" aria-hidden="true">02</span><div><h3 class="act__t">Jams contact-improvisation</h3><p class="act__tx">Sessions d'improvisation ouvertes au public, en contact-improvisation et musique improvisée, dans des espaces variés et inattendus.</p></div><span class="act__b">Jam</span></li>
        <li class="act"><span class="act__n" aria-hidden="true">03</span><div><h3 class="act__t">Stages &amp; ateliers</h3><p class="act__tx">Stages de contact-improvisation, ateliers de mouvement somatique, ateliers de musique improvisée. Tous niveaux, du débutant à l'artiste confirmé.</p></div><span class="act__b">Pédagogie</span></li>
        <li class="act"><span class="act__n" aria-hidden="true">04</span><div><h3 class="act__t">Résidences de recherche</h3><p class="act__tx">Espaces d'exploration artistique pour chercheurs du mouvement, musiciens et artistes pluridisciplinaires. Laboratoires de création et d'expérimentation.</p></div><span class="act__b">Résidence</span></li>
        <li class="act"><span class="act__n" aria-hidden="true">05</span><div><h3 class="act__t">Résidences pédagogiques</h3><p class="act__tx">Interventions en milieu scolaire, médico-social ou en entreprise. Une pédagogie du sensible adaptée à tous les publics.</p></div><span class="act__b">Médiation</span></li>
        <li class="act"><span class="act__n" aria-hidden="true">06</span><div><h3 class="act__t">Lutherie &amp; fabrication</h3><p class="act__tx">Conception et modification d'instruments comme geste artistique. Ateliers de sensibilisation à la fabrication sonore.</p></div><span class="act__b">Artisanat</span></li>
      </ul>
      <div>
        <p class="lbl" style="margin-top:64px">Axes de diffusion</p>
        <div class="regle" style="margin-bottom:28px"></div>
        <div class="diff">
          <div class="diff-i">Festivals de danse contemporaine, contact-improvisation et musique improvisée — France &amp; Europe</div>
          <div class="diff-i">Théâtres et scènes labellisées accueillant les écritures chorégraphiques émergentes</div>
          <div class="diff-i">Lieux non conventionnels : musées, bibliothèques, espaces naturels, ateliers d'artistes</div>
          <div class="diff-i">Établissements scolaires et structures socioculturelles pour les ateliers pédagogiques</div>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_valeurs] — Valeurs esthétiques (colonne gauche de la section esthétique) */
add_shortcode('ps_valeurs', function (): string {
    ob_start();
    ?>
    <div class="esthet__vals">
      <div class="val"><p class="val__l">Scénographie</p><p class="val__t">Sobriété des accessoires. Lumière travaillée — diffuse, rasante, traversante. Sons produits en direct. La présence des artistes au premier plan.</p></div>
      <div class="val"><p class="val__l">Musique</p><p class="val__t">Instruments acoustiques, voix, objets, cordes préparées. Improvisation libre et composition en temps réel. Le silence comme espace habité.</p></div>
      <div class="val"><p class="val__l">Mouvement</p><p class="val__t">Qualité de présence, écoute du poids, circulation de l'énergie. Ancré dans les pratiques somatiques — Body-Mind Centering, Feldenkrais, méridiens.</p></div>
      <div class="val"><p class="val__l">Artisanat</p><p class="val__t">Instruments fabriqués ou modifiés par Ewen d'Aviau. Costumes et accessoires pensés en rapport avec la matière corporelle.</p></div>
      <div class="val"><p class="val__l">Philosophie</p><p class="val__t">Inspiré du Tao, des méridiens, de l'aïkido — fluidité, transformation, redirection, vacuité. Non-résistance, accord avec la force de l'autre.</p></div>
    </div>
    <?php
    return ob_get_clean();
});

/* ══════════════════════════════════════════════════════════════
   2. CATÉGORIE & PATTERNS GUTENBERG
   ══════════════════════════════════════════════════════════════ */

add_action('init', function () {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    register_block_pattern_category('poivre-sens', [
        'label'       => 'Poivre &amp; Sens',
        'description' => 'Sections de la page d\'accueil',
    ]);

    register_block_pattern('poivre-sens/homepage', [
        'title'       => 'Page d\'accueil complète',
        'description' => 'Toutes les sections. À insérer sur une page vierge intitulée « Accueil ».',
        'categories'  => ['poivre-sens'],
        'content'     => _ps_pat_hero() . _ps_pat_galerie_sc()
                       . _ps_pat_manifeste() . _ps_pat_artistes()
                       . _ps_pat_projet_sc() . _ps_pat_activites_sc()
                       . _ps_pat_evenements_sc() . _ps_pat_esthetique()
                       . _ps_pat_newsletter_sc() . _ps_pat_contact(),
    ]);

    register_block_pattern('poivre-sens/hero', [
        'title'      => '① Hero — En-tête',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_hero(),
    ]);

    register_block_pattern('poivre-sens/manifeste', [
        'title'      => '② Manifeste',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_manifeste(),
    ]);

    register_block_pattern('poivre-sens/artistes', [
        'title'      => '③ Artistes &amp; fondateurs',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_artistes(),
    ]);

    register_block_pattern('poivre-sens/esthetique', [
        'title'      => '④ Esthétique &amp; citation',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_esthetique(),
    ]);

    register_block_pattern('poivre-sens/contact', [
        'title'      => '⑤ Contact',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_contact(),
    ]);
});

/* ══════════════════════════════════════════════════════════════
   3. FONCTIONS DE RENDU DES PATTERNS
   ══════════════════════════════════════════════════════════════ */

/** Shortcode block helper */
function _ps_sc(string $tag): string {
    return "\n<!-- wp:shortcode -->\n[{$tag}]\n<!-- /wp:shortcode -->\n";
}
function _ps_pat_galerie_sc(): string    { return _ps_sc('ps_galerie'); }
function _ps_pat_projet_sc(): string     { return _ps_sc('ps_projet'); }
function _ps_pat_activites_sc(): string  { return _ps_sc('ps_activites'); }
function _ps_pat_evenements_sc(): string { return _ps_sc('ps_evenements'); }
function _ps_pat_newsletter_sc(): string { return _ps_sc('ps_newsletter'); }

/* ── ① Hero ────────────────────────────────────────────────── */
function _ps_pat_hero(): string {
    return <<<'BLOCK'

<!-- wp:group {"tagName":"section","className":"hero","anchor":"accueil","layout":{"type":"default"}} -->
<section class="wp-block-group hero" id="accueil">

<!-- wp:group {"className":"hero__g","layout":{"type":"default"}} -->
<div class="wp-block-group hero__g">

<!-- wp:paragraph {"className":"hero__sup"} -->
<p class="hero__sup">Compagnie artistique · Association loi 1901</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hero__nom"} -->
<h1 class="wp-block-heading hero__nom">Poivre<span class="et">&amp;</span>Sens</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hero__disc"} -->
<p class="hero__disc"><strong>Ambre Lavignac &amp; Ewen d'Aviau</strong><br>Danse contemporaine<br>Contact-improvisation<br>Musique improvisée<br>Pratiques somatiques</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#projet" class="hero__cta">Découvrir la compagnie</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

<!-- wp:group {"className":"hero__d","layout":{"type":"default"}} -->
<div class="wp-block-group hero__d">

<!-- wp:paragraph {"className":"hero__q"} -->
<p class="hero__q">Le corps sait ce que l'esprit cherche encore.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"hero__intro"} -->
<p class="hero__intro">Née de la rencontre d'un corps et d'un son, d'une main qui écoute et d'une oreille qui se déplace, la compagnie explore les espaces de porosité entre le mouvement et la musique.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ── ② Manifeste ───────────────────────────────────────────── */
function _ps_pat_manifeste(): string {
    return <<<'BLOCK'

<!-- wp:group {"tagName":"div","className":"manifeste sec3","layout":{"type":"default"}} -->
<div class="wp-block-group manifeste sec3">

<!-- wp:paragraph {"className":"mf-ax"} -->
<p class="mf-ax">Manifeste</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"mf-corps","layout":{"type":"default"}} -->
<div class="wp-block-group mf-corps">

<!-- wp:heading {"level":2,"className":"mf-t"} -->
<h2 class="wp-block-heading mf-t">Une rencontre entre <em>le corps</em> et <em>le son</em></h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"mf-tx","layout":{"type":"default"}} -->
<div class="wp-block-group mf-tx">

<!-- wp:paragraph -->
<p>La compagnie explore les espaces de porosité entre le mouvement et le son, entre la structure et le lâcher-prise, entre la transmission d'un savoir et l'ouverture à l'inconnu. Ses créations ne cherchent pas à illustrer ni à démontrer, mais à <em>habiter</em>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ce qui unit leurs univers, c'est la qualité de présence : être là, pleinement, dans l'instant d'une rencontre — entre deux corps, entre un corps et un instrument, entre une sensation et une image, entre ce qui est attendu et ce qui surgit.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Inspirée du Tao, des méridiens, de l'aïkido et de la lutherie, la compagnie croit en l'<em>artisanat du spectacle</em> : chaque geste compte, chaque son est matière, chaque silence est espace.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

BLOCK;
}

/* ── ③ Artistes ────────────────────────────────────────────── */
function _ps_pat_artistes(): string {
    return <<<'BLOCK'

<!-- wp:group {"tagName":"section","className":"sec sec2","anchor":"artistes","layout":{"type":"default"}} -->
<section class="wp-block-group sec sec2" id="artistes">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">Les fondateurs</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">Artistes &amp; pédagogues</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"bios","layout":{"type":"default"}} -->
<div class="wp-block-group bios">

<!-- wp:group {"className":"bio","layout":{"type":"default"}} -->
<div class="wp-block-group bio">
<!-- wp:group {"className":"bio__hd","layout":{"type":"default"}} -->
<div class="wp-block-group bio__hd">
<!-- wp:group {"className":"bio__mn","layout":{"type":"default"}} -->
<div class="wp-block-group bio__mn">
<!-- wp:paragraph -->
<p>A</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"className":"bio__nom"} -->
<h3 class="wp-block-heading bio__nom">Ambre Lavignac</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"bio__rol"} -->
<p class="bio__rol">Danseuse · Pédagogue · Praticienne du mouvement</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- wp:paragraph {"className":"bio__tx"} -->
<p class="bio__tx">Formée à la danse contemporaine, Ambre Lavignac oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens. Inspirée par la philosophie taoïste et la médecine traditionnelle chinoise, elle explore les correspondances entre les éléments naturels, les méridiens énergétiques et les qualités de mouvement.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"bio__tx"} -->
<p class="bio__tx">Praticienne du massage, elle travaille les liens entre le toucher, la conscience corporelle et la circulation de l'énergie. En tant que chorégraphe, elle s'intéresse à l'improvisation comme espace de création vivante.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"bio__tgs"} -->
<p class="bio__tgs"><span class="bio__tg">Danse contemporaine</span><span class="bio__tg">Improvisation</span><span class="bio__tg">Somatique</span><span class="bio__tg">Tao</span><span class="bio__tg">Méridiens</span><span class="bio__tg">Massage</span><span class="bio__tg">Pédagogie</span></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"bio","layout":{"type":"default"}} -->
<div class="wp-block-group bio">
<!-- wp:group {"className":"bio__hd","layout":{"type":"default"}} -->
<div class="wp-block-group bio__hd">
<!-- wp:group {"className":"bio__mn","layout":{"type":"default"}} -->
<div class="wp-block-group bio__mn">
<!-- wp:paragraph -->
<p>E</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"className":"bio__nom"} -->
<h3 class="wp-block-heading bio__nom">Ewen d'Aviau</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"bio__rol"} -->
<p class="bio__rol">Luthier-ingénieur · Musicien · Danseur</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- wp:paragraph {"className":"bio__tx"} -->
<p class="bio__tx">Ingénieur de formation, Ewen d'Aviau se tourne vers la lutherie pour explorer la fabrication des instruments à cordes comme geste à la fois artisanal, scientifique et artistique. Il conçoit le son comme une matière vivante, façonnable, imprévue.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"bio__tx"} -->
<p class="bio__tx">Musicien, il pratique l'improvisation libre avec une oreille particulière pour l'espace, le silence et la relation. Danseur, imprégné du contact-improvisation et de l'aïkido, il retient l'art de la redirection et de la présence active non agressive.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"bio__tgs"} -->
<p class="bio__tgs"><span class="bio__tg">Lutherie</span><span class="bio__tg">Musique improvisée</span><span class="bio__tg">Contact-improvisation</span><span class="bio__tg">Somatique</span><span class="bio__tg">Aïkido</span><span class="bio__tg">Enseignement</span></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[ps_influences]
<!-- /wp:shortcode -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ── ④ Esthétique ──────────────────────────────────────────── */
function _ps_pat_esthetique(): string {
    return <<<'BLOCK'

<!-- wp:group {"tagName":"section","className":"sec","anchor":"esthetique","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="esthetique">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">Identité &amp; valeurs</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">Esthétique de la compagnie</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"esthet","layout":{"type":"default"}} -->
<div class="wp-block-group esthet">

<!-- wp:shortcode -->
[ps_valeurs]
<!-- /wp:shortcode -->

<!-- wp:group {"className":"esthet__cite","layout":{"type":"default"}} -->
<div class="wp-block-group esthet__cite">
<!-- wp:quote {"className":"gcite"} -->
<blockquote class="wp-block-quote gcite"><p>Habiter un espace de jeu partagé —<br>entre deux corps,<br><em>un corps et un instrument</em>.</p><cite>Poivre &amp; Sens · Note d'intention</cite></blockquote>
<!-- /wp:quote -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ── ⑤ Contact ─────────────────────────────────────────────── */
function _ps_pat_contact(): string {
    return <<<'BLOCK'

<!-- wp:group {"tagName":"section","className":"sec","anchor":"contact","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="contact">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">Nous rejoindre</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">Contact</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"contact","layout":{"type":"default"}} -->
<div class="wp-block-group contact">

<!-- wp:group {"className":"co-col","layout":{"type":"default"}} -->
<div class="wp-block-group co-col">
<!-- wp:paragraph {"className":"co-h"} -->
<p class="co-h">La compagnie</p>
<!-- /wp:paragraph -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Nom</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v">Poivre &amp; Sens</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Statut</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v">Association loi 1901</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Direction</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v">Ambre Lavignac &amp; Ewen d'Aviau</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Disciplines</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v">Danse · Contact-improvisation · Musique · Somatique</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Courriel</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v"><a href="mailto:contact@cie.poivresens.fr">contact@cie.poivresens.fr</a></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Site</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v"><a href="https://cie.poivresens.fr">cie.poivresens.fr</a></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"co-col","layout":{"type":"default"}} -->
<div class="wp-block-group co-col">
<!-- wp:paragraph {"className":"co-h"} -->
<p class="co-h">Les fondateurs</p>
<!-- /wp:paragraph -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Ambre</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v"><a href="mailto:ambre@cie.poivresens.fr">ambre@cie.poivresens.fr</a></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"co-row","layout":{"type":"default"}} -->
<div class="wp-block-group co-row">
<!-- wp:paragraph {"className":"co-k"} --><p class="co-k">Ewen</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"co-v"} --><p class="co-v"><a href="mailto:ewen@cie.poivresens.fr">ewen@cie.poivresens.fr</a></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:heading {"level":4,"className":"co-h","style":{"spacing":{"margin":{"top":"32px","bottom":"28px"}}}} -->
<h4 class="co-h" style="margin-top:32px;margin-bottom:28px">Suivre la compagnie</h4>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"co-note"} -->
<p class="co-note">Retrouvez Poivre &amp; Sens dans les réseaux du spectacle vivant, les festivals de contact-improvisation et les scènes de musique improvisée en France et en Europe.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}
