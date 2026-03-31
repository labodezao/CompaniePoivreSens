<?php
/**
 * front-page.php — Page d'accueil one-page Poivre & Sens
 */
get_header();

/* ── Galerie : 6 photos depuis le CPT "galerie" ────────── */
$galerie_q = new WP_Query([
    'post_type'      => 'galerie',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);
$galerie_items = [];
$svgs = [
    get_template_directory_uri() . '/../../images/galerie-01-spectacle.svg',
    get_template_directory_uri() . '/../../images/galerie-02-jam.svg',
    get_template_directory_uri() . '/../../images/galerie-03-ewen.svg',
    get_template_directory_uri() . '/../../images/galerie-04-ambre.svg',
    get_template_directory_uri() . '/../../images/galerie-05-residence.svg',
    get_template_directory_uri() . '/../../images/galerie-06-atelier.svg',
];
// Raccourci propre vers les SVGs dans le dossier images/ du thème
$theme_img = get_template_directory_uri() . '/images/';
$svg_caps = [
    ['En scène',             'Spectacle vivant · Création'],
    ['Jam de contact',       'Contact-improvisation · Rencontre ouverte'],
    ["Ewen d'Aviau",         'Luthier · Musicien · Danseur'],
    ['Ambre Lavignac',       'Danseuse · Pédagogue · Praticienne'],
    ['En résidence',         'Laboratoire artistique · Recherche'],
    ['Pédagogie du sensible','Atelier · Stage · Transmission'],
];
if ($galerie_q->have_posts()) {
    $i = 0;
    while ($galerie_q->have_posts() && $i < 6) {
        $galerie_q->the_post();
        $galerie_items[] = [
            'img'     => get_the_post_thumbnail_url(null, 'galerie-thumb') ?: ($theme_img . 'galerie-0' . ($i+1) . '-' . ['spectacle','jam','ewen','ambre','residence','atelier'][$i] . '.svg'),
            'alt'     => get_the_title(),
            'titre'   => get_the_title(),
            'caption' => get_post_meta(get_the_ID(), '_galerie_caption', true) ?: ($svg_caps[$i][1] ?? ''),
        ];
        $i++;
    }
    wp_reset_postdata();
}
// Compléter avec les SVG si < 6 photos
for ($i = count($galerie_items); $i < 6; $i++) {
    $slugs = ['spectacle','jam','ewen','ambre','residence','atelier'];
    $galerie_items[] = [
        'img'     => $theme_img . 'galerie-0' . ($i+1) . '-' . $slugs[$i] . '.svg',
        'alt'     => $svg_caps[$i][0],
        'titre'   => $svg_caps[$i][0],
        'caption' => $svg_caps[$i][1],
    ];
}

/* ── 3 prochains événements ─────────────────────────────── */
$evts_q = ps_get_upcoming_events(3);
?>

<!-- ═══════════════════════════ HERO ════════════════════════ -->
<section class="hero" id="accueil" aria-label="Accueil">
  <div class="hero__bg" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none">
      <path d="M-80,450 C120,180 320,720 560,380 C780,60 980,620 1200,400 C1330,290 1400,340 1520,310" stroke="rgba(194,139,54,0.07)" stroke-width="1.5"/>
      <path d="M200,900 C300,600 480,820 640,500 C800,180 960,680 1100,350 C1200,140 1350,240 1520,180" stroke="rgba(158,55,16,0.05)" stroke-width="1"/>
      <path d="M-120,200 C80,420 260,120 480,360 C700,600 820,200 1060,480 C1240,700 1380,500 1540,600" stroke="rgba(194,139,54,0.05)" stroke-width="2"/>
      <ellipse cx="720" cy="450" rx="380" ry="240" stroke="rgba(194,139,54,0.04)" stroke-width="1"/>
    </svg>
  </div>
  <div class="hero__g">
    <p class="hero__sup">Compagnie artistique · Association loi 1901</p>
    <h1 class="hero__nom">Poivre<span class="et">&amp;</span>Sens</h1>
    <p class="hero__disc">
      <strong>Ambre Lavignac &amp; Ewen d'Aviau</strong>
      Danse contemporaine<br>Contact-improvisation<br>Musique improvisée<br>Pratiques somatiques
    </p>
    <a href="#projet" class="hero__cta">Découvrir la compagnie</a>
  </div>
  <div class="hero__d">
    <blockquote class="hero__q">Le corps sait ce que l'esprit cherche encore.</blockquote>
    <p class="hero__intro">Née de la rencontre d'un corps et d'un son, d'une main qui écoute et d'une oreille qui se déplace, la compagnie explore les espaces de porosité entre le mouvement et la musique.</p>
  </div>
  <div class="hero__scrl" aria-hidden="true">Défiler</div>
</section>

<!-- ═══════════════════════════ GALERIE ═════════════════════ -->
<section class="galerie sec2" id="galerie" aria-labelledby="titre-galerie">
  <div class="galerie__hdr">
    <div>
      <p class="lbl">Galerie</p>
      <h2 class="galerie__t" id="titre-galerie">Images de la compagnie</h2>
      <div class="regle"></div>
    </div>
    <p class="galerie__n">
      Photos de la compagnie — remplacez ces images<br>
      par vos propres clichés depuis l'admin WordPress.
    </p>
  </div>
  <div class="galerie__g" role="list">
    <?php foreach ($galerie_items as $item): ?>
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

<!-- ═══════════════════════════ MANIFESTE ═══════════════════ -->
<div class="manifeste sec3" role="region" aria-label="Manifeste">
  <p class="mf-ax" aria-hidden="true">Manifeste</p>
  <div class="mf-corps">
    <h2 class="mf-t">Une rencontre entre <em>le corps</em> et <em>le son</em></h2>
    <div class="mf-tx">
      <p>La compagnie explore les espaces de porosité entre le mouvement et le son, entre la structure et le lâcher-prise, entre la transmission d'un savoir et l'ouverture à l'inconnu. Ses créations ne cherchent pas à illustrer ni à démontrer, mais à <em>habiter</em>.</p>
      <p>Ce qui unit leurs univers, c'est la qualité de présence : être là, pleinement, dans l'instant d'une rencontre — entre deux corps, entre un corps et un instrument, entre une sensation et une image, entre ce qui est attendu et ce qui surgit.</p>
      <p>Inspirée du Tao, des méridiens, de l'aïkido et de la lutherie, la compagnie croit en l'<em>artisanat du spectacle</em> : chaque geste compte, chaque son est matière, chaque silence est espace.</p>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ PROJET ══════════════════════ -->
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

<!-- ═══════════════════════════ ARTISTES ════════════════════ -->
<section class="sec sec2" id="artistes" aria-labelledby="titre-artistes">
  <div style="margin-bottom:56px">
    <p class="lbl">Les fondateurs</p>
    <h2 class="sh" id="titre-artistes">Artistes &amp; pédagogues</h2>
    <div class="regle"></div>
  </div>
  <div class="bios">
    <div class="bio">
      <div class="bio__hd">
        <div class="bio__mn" aria-hidden="true">A</div>
        <div>
          <h3 class="bio__nom">Ambre Lavignac</h3>
          <p class="bio__rol">Danseuse · Pédagogue · Praticienne du mouvement</p>
        </div>
      </div>
      <p class="bio__tx">Formée à la danse contemporaine, Ambre Lavignac oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens. Inspirée par la philosophie taoïste et la médecine traditionnelle chinoise, elle explore les correspondances entre les éléments naturels, les méridiens énergétiques et les qualités de mouvement.</p>
      <p class="bio__tx">Praticienne du massage, elle travaille les liens entre le toucher, la conscience corporelle et la circulation de l'énergie. En tant que chorégraphe, elle s'intéresse à l'improvisation comme espace de création vivante.</p>
      <div class="bio__tgs">
        <span class="bio__tg">Danse contemporaine</span><span class="bio__tg">Improvisation</span><span class="bio__tg">Somatique</span><span class="bio__tg">Tao</span><span class="bio__tg">Méridiens</span><span class="bio__tg">Massage</span><span class="bio__tg">Pédagogie</span>
      </div>
    </div>
    <div class="bio">
      <div class="bio__hd">
        <div class="bio__mn" aria-hidden="true">E</div>
        <div>
          <h3 class="bio__nom">Ewen d'Aviau</h3>
          <p class="bio__rol">Luthier-ingénieur · Musicien · Danseur</p>
        </div>
      </div>
      <p class="bio__tx">Ingénieur de formation, Ewen d'Aviau se tourne vers la lutherie pour explorer la fabrication des instruments à cordes comme geste à la fois artisanal, scientifique et artistique. Il conçoit le son comme une matière vivante, façonnable, imprévue.</p>
      <p class="bio__tx">Musicien, il pratique l'improvisation libre avec une oreille particulière pour l'espace, le silence et la relation. Danseur, imprégné du contact-improvisation et de l'aïkido, il retient l'art de la redirection et de la présence active non agressive.</p>
      <div class="bio__tgs">
        <span class="bio__tg">Lutherie</span><span class="bio__tg">Musique improvisée</span><span class="bio__tg">Contact-improvisation</span><span class="bio__tg">Somatique</span><span class="bio__tg">Aïkido</span><span class="bio__tg">Enseignement</span>
      </div>
    </div>
  </div>
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
</section>

<!-- ═══════════════════════════ ACTIVITÉS ═══════════════════ -->
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

<!-- ═══════════════════════════ ÉVÉNEMENTS ══════════════════ -->
<section class="sec sec2" id="evenements" aria-labelledby="titre-evts">
  <div style="margin-bottom:40px">
    <p class="lbl">Agenda</p>
    <h2 class="sh" id="titre-evts">Prochains événements</h2>
    <div class="regle"></div>
  </div>

  <?php if ($evts_q->have_posts()): ?>

  <!-- Calendrier liste — 3 prochains -->
  <div class="cal-list cal-list--compact">
    <?php
    $today = date('Y-m-d');
    $jours_fr = ['Sun'=>'Dim','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam'];
    while ($evts_q->have_posts()): $evts_q->the_post();
        $evt_date  = get_post_meta(get_the_ID(), '_evt_date', true);
        $heure     = get_post_meta(get_the_ID(), '_evt_heure', true);
        $lieu      = get_post_meta(get_the_ID(), '_evt_lieu', true);
        $ville     = get_post_meta(get_the_ID(), '_evt_ville', true);
        $type      = get_post_meta(get_the_ID(), '_evt_type', true);
        $prix      = get_post_meta(get_the_ID(), '_evt_prix', true);
        $billet    = get_post_meta(get_the_ID(), '_evt_billetterie', true);
        $complet   = get_post_meta(get_the_ID(), '_evt_complet', true);
        $ts        = strtotime($evt_date);
        $j_num     = date('j', $ts);
        $j_ltr     = $jours_fr[date('D', $ts)] ?? date('D', $ts);
    ?>
    <div class="cal-list__event <?= $evt_date === $today ? 'cal-list__event--today' : '' ?>">
      <div class="cal-list__date">
        <span class="cal-list__day-ltr"><?= esc_html($j_ltr) ?></span>
        <span class="cal-list__day-num"><?= esc_html($j_num) ?></span>
        <span class="cal-list__day-mois" style="font-size:.6rem;color:var(--or);letter-spacing:.1em;text-transform:uppercase"><?= esc_html(date_i18n('M', $ts)) ?></span>
      </div>
      <div class="cal-list__line" aria-hidden="true"></div>
      <div class="cal-list__body">
        <?php if ($type): ?><span class="cal-list__type"><?= esc_html(ps_evt_type_label($type)) ?></span><?php endif; ?>
        <?php if ($complet): ?><span class="cal-list__complet"><?php _e('Complet', 'poivre-sens'); ?></span><?php endif; ?>
        <h3 class="cal-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <ul class="cal-list__meta" role="list">
          <?php if ($heure): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic" aria-hidden="true">🕐</span><?= esc_html($heure) ?></li><?php endif; ?>
          <?php if ($lieu || $ville): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic" aria-hidden="true">📍</span><?= esc_html(implode(', ', array_filter([$lieu,$ville]))) ?></li><?php endif; ?>
          <?php if ($prix): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic" aria-hidden="true">🎟</span><?= esc_html($prix) ?></li><?php endif; ?>
        </ul>
        <div class="cal-list__actions">
          <a href="<?php the_permalink(); ?>" class="cal-list__action-link"><?php _e('En savoir plus', 'poivre-sens'); ?> →</a>
          <?php if ($billet && !$complet): ?>
          <a href="<?= esc_url($billet) ?>" class="cal-list__action-btn" target="_blank" rel="noopener"><?php _e('Réserver', 'poivre-sens'); ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>

  <a href="<?= esc_url(get_post_type_archive_link('evenement')) ?>" class="evts__lien">
    <?php _e('Voir tout l\'agenda', 'poivre-sens'); ?>
  </a>

  <?php else: ?>
  <div style="padding:48px 0;text-align:center;color:var(--gris);font-size:.9rem">
    <?php _e('Aucun événement programmé pour le moment.', 'poivre-sens'); ?>
    <?php if (current_user_can('publish_posts')): ?>
    <br><br>
    <a href="<?= esc_url(admin_url('post-new.php?post_type=evenement')) ?>" class="evts__lien">
      + <?php _e('Créer un événement', 'poivre-sens'); ?>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ═══════════════════════════ ESTHÉTIQUE ══════════════════ -->
<section class="sec" id="esthetique" aria-labelledby="titre-esthetique">
  <div style="margin-bottom:56px">
    <p class="lbl">Identité &amp; valeurs</p>
    <h2 class="sh" id="titre-esthetique">Esthétique de la compagnie</h2>
    <div class="regle"></div>
  </div>
  <div class="esthet">
    <div class="esthet__vals">
      <div class="val"><p class="val__l">Scénographie</p><p class="val__t">Sobriété des accessoires. Lumière travaillée — diffuse, rasante, traversante. Sons produits en direct. La présence des artistes au premier plan.</p></div>
      <div class="val"><p class="val__l">Musique</p><p class="val__t">Instruments acoustiques, voix, objets, cordes préparées. Improvisation libre et composition en temps réel. Le silence comme espace habité.</p></div>
      <div class="val"><p class="val__l">Mouvement</p><p class="val__t">Qualité de présence, écoute du poids, circulation de l'énergie. Ancré dans les pratiques somatiques — Body-Mind Centering, Feldenkrais, méridiens.</p></div>
      <div class="val"><p class="val__l">Artisanat</p><p class="val__t">Instruments fabriqués ou modifiés par Ewen d'Aviau. Costumes et accessoires pensés en rapport avec la matière corporelle. Le faire comme geste artistique.</p></div>
      <div class="val"><p class="val__l">Philosophie</p><p class="val__t">Inspiré du Tao, des méridiens, de l'aïkido — fluidité, transformation, redirection, vacuité. Non-résistance, accord avec la force de l'autre.</p></div>
    </div>
    <div class="esthet__cite">
      <blockquote class="gcite">
        Habiter un espace de jeu partagé —<br>entre deux corps,<br>entre <em>un corps</em> et <em>un instrument</em>.
      </blockquote>
      <p class="gcite__src">Poivre &amp; Sens · Note d'intention</p>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ NEWSLETTER ══════════════════ -->
<section class="sec sec3" id="newsletter" aria-labelledby="titre-nl">
  <?php get_template_part('template-parts/newsletter-form'); ?>
</section>

<!-- ═══════════════════════════ CONTACT ═════════════════════ -->
<section class="sec" id="contact" aria-labelledby="titre-contact">
  <div style="margin-bottom:56px">
    <p class="lbl">Nous rejoindre</p>
    <h2 class="sh" id="titre-contact">Contact</h2>
    <div class="regle"></div>
  </div>
  <div class="contact">
    <div class="co-col">
      <p class="co-h">La compagnie</p>
      <div class="co-row"><span class="co-k">Nom</span><span class="co-v">Poivre &amp; Sens</span></div>
      <div class="co-row"><span class="co-k">Statut</span><span class="co-v">Association loi 1901</span></div>
      <div class="co-row"><span class="co-k">Direction</span><span class="co-v">Ambre Lavignac &amp; Ewen d'Aviau</span></div>
      <div class="co-row"><span class="co-k">Disciplines</span><span class="co-v">Danse · Contact-improvisation · Musique · Somatique</span></div>
      <div class="co-row"><span class="co-k">Courriel</span><span class="co-v"><a href="mailto:contact@cie.poivresens.fr">contact@cie.poivresens.fr</a></span></div>
      <div class="co-row"><span class="co-k">Site</span><span class="co-v"><a href="https://cie.poivresens.fr">cie.poivresens.fr</a></span></div>
    </div>
    <div class="co-col">
      <p class="co-h">Les fondateurs</p>
      <div class="co-row"><span class="co-k">Ambre L.</span><span class="co-v"><a href="mailto:ambre@cie.poivresens.fr">ambre@cie.poivresens.fr</a></span></div>
      <div class="co-row"><span class="co-k">Ewen d'A.</span><span class="co-v"><a href="mailto:ewen@cie.poivresens.fr">ewen@cie.poivresens.fr</a></span></div>
      <p class="co-h" style="margin-top:32px">Suivre la compagnie</p>
      <p style="font-size:.88rem;line-height:1.75;color:rgba(236,227,203,.55);margin-top:8px">Retrouvez Poivre &amp; Sens dans les réseaux du spectacle vivant, les festivals de contact-improvisation et les scènes de musique improvisée en France et en Europe.</p>
    </div>
  </div>
</section>

<?php get_footer();
