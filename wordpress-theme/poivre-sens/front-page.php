<?php
/**
 * front-page.php — Page d'accueil one-page Poivre & Sens
 * Tous les textes sont éditables via Réglages › Contenu du site
 */
get_header();

/* ── Helpers options admin ──────────────────────────────── */
function ps_mod(string $key, string $default = ''): string {
    static $opts = null;
    if ($opts === null) $opts = (array) get_option('ps_options', []);
    return isset($opts[$key]) && $opts[$key] !== '' ? $opts[$key] : $default;
}
function ps_e(string $key, string $default = ''): void {
    echo esc_html(ps_mod($key, $default));
}
function ps_html(string $key, string $default = ''): void {
    echo wp_kses_post(ps_mod($key, $default));
}

/* ── Galerie : 6 photos depuis le CPT "galerie" ────────── */
$galerie_q = new WP_Query([
    'post_type'      => 'galerie',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);
$theme_img     = get_template_directory_uri() . '/images/';
$svg_slugs     = ['spectacle','jam','ewen','ambre','residence','atelier'];
$svg_caps_def  = [
    ['En scène',              'Spectacle vivant · Création'],
    ['Jam de contact',        'Contact-improvisation · Rencontre ouverte'],
    ["Ewen d'Aviau",          'Luthier · Musicien · Danseur'],
    ['Ambre Lavignac',        'Danseuse · Pédagogue · Praticienne'],
    ['En résidence',          'Laboratoire artistique · Recherche'],
    ['Pédagogie du sensible', 'Atelier · Stage · Transmission'],
];
$galerie_items = [];
if ($galerie_q->have_posts()) {
    $i = 0;
    while ($galerie_q->have_posts() && $i < 6) {
        $galerie_q->the_post();
        $galerie_items[] = [
            'img'     => get_the_post_thumbnail_url(null, 'galerie-thumb') ?: ($theme_img . 'galerie-0' . ($i+1) . '-' . $svg_slugs[$i] . '.svg'),
            'alt'     => get_the_title(),
            'titre'   => get_the_title(),
            'caption' => get_post_meta(get_the_ID(), '_galerie_caption', true) ?: ($svg_caps_def[$i][1] ?? ''),
        ];
        $i++;
    }
    wp_reset_postdata();
}
for ($i = count($galerie_items); $i < 6; $i++) {
    $galerie_items[] = [
        'img'     => $theme_img . 'galerie-0' . ($i+1) . '-' . $svg_slugs[$i] . '.svg',
        'alt'     => $svg_caps_def[$i][0],
        'titre'   => $svg_caps_def[$i][0],
        'caption' => $svg_caps_def[$i][1],
    ];
}

/* ── 3 prochains événements ─────────────────────────────── */
$evts_q = ps_get_upcoming_events(3);

/* ── Valeurs Customizer avec defaults ───────────────────── */
$hero_surtitle   = ps_mod('hero_surtitle',    'Compagnie artistique · Association loi 1901');
$hero_discs      = ps_mod('hero_disciplines', "Danse contemporaine\nContact-improvisation\nMusique improvisée\nPratiques somatiques");
$hero_cta        = ps_mod('hero_cta_label',   'Découvrir la compagnie');
$hero_quote      = ps_mod('hero_quote',       "Le corps sait ce que l'esprit cherche encore.");
$hero_intro      = ps_mod('hero_intro',       "Née de la rencontre d'un corps et d'un son, d'une main qui écoute et d'une oreille qui se déplace, la compagnie explore les espaces de porosité entre le mouvement et la musique.");

$mf_titre        = ps_mod('manifeste_titre',    "Une rencontre entre le corps et le son");
$mf_em1          = ps_mod('manifeste_titre_em1','le corps');
$mf_em2          = ps_mod('manifeste_titre_em2','le son');
$mf_p1           = ps_mod('manifeste_p1',       "La compagnie explore les espaces de porosité entre le mouvement et le son, entre la structure et le lâcher-prise, entre la transmission d'un savoir et l'ouverture à l'inconnu. Ses créations ne cherchent pas à illustrer ni à démontrer, mais à <em>habiter</em>.");
$mf_p2           = ps_mod('manifeste_p2',       "Ce qui unit leurs univers, c'est la qualité de présence : être là, pleinement, dans l'instant d'une rencontre — entre deux corps, entre un corps et un instrument, entre une sensation et une image, entre ce qui est attendu et ce qui surgit.");
$mf_p3           = ps_mod('manifeste_p3',       "Inspirée du Tao, des méridiens, de l'aïkido et de la lutherie, la compagnie croit en l'<em>artisanat du spectacle</em> : chaque geste compte, chaque son est matière, chaque silence est espace.");

// Titre manifeste avec mots en italique dorés
function ps_titre_mf(string $titre, string $em1, string $em2): string {
    if ($em1) $titre = str_replace($em1, '<em>' . esc_html($em1) . '</em>', esc_html($titre));
    else $titre = esc_html($titre);
    if ($em2) $titre = str_replace(esc_html($em2), '<em>' . esc_html($em2) . '</em>', $titre);
    return $titre;
}

$ambre_nom      = ps_mod('ambre_nom',      'Ambre Lavignac');
$ambre_role     = ps_mod('ambre_role',     'Danseuse · Pédagogue · Praticienne du mouvement');
$ambre_init     = ps_mod('ambre_initiale', 'A');
$ambre_bio1     = ps_mod('ambre_bio1',     "Formée à la danse contemporaine, Ambre Lavignac oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens. Inspirée par la philosophie taoïste et la médecine traditionnelle chinoise, elle explore les correspondances entre les éléments naturels, les méridiens énergétiques et les qualités de mouvement.");
$ambre_bio2     = ps_mod('ambre_bio2',     "Praticienne du massage, elle travaille les liens entre le toucher, la conscience corporelle et la circulation de l'énergie. En tant que chorégraphe, elle s'intéresse à l'improvisation comme espace de création vivante.");
$ambre_tags_raw = ps_mod('ambre_tags',     'Danse contemporaine,Improvisation,Somatique,Tao,Méridiens,Massage,Pédagogie');

$ewen_nom       = ps_mod('ewen_nom',       "Ewen d'Aviau");
$ewen_role      = ps_mod('ewen_role',      "Luthier-ingénieur · Musicien · Danseur");
$ewen_init      = ps_mod('ewen_initiale',  'E');
$ewen_bio1      = ps_mod('ewen_bio1',      "Ingénieur de formation, Ewen d'Aviau se tourne vers la lutherie pour explorer la fabrication des instruments à cordes comme geste à la fois artisanal, scientifique et artistique. Il conçoit le son comme une matière vivante, façonnable, imprévue.");
$ewen_bio2      = ps_mod('ewen_bio2',      "Musicien, il pratique l'improvisation libre avec une oreille particulière pour l'espace, le silence et la relation. Danseur, imprégné du contact-improvisation et de l'aïkido, il retient l'art de la redirection et de la présence active non agressive.");
$ewen_tags_raw  = ps_mod('ewen_tags',      "Lutherie,Musique improvisée,Contact-improvisation,Somatique,Aïkido,Enseignement");

$ec_l1          = ps_mod('esthet_cite_ligne1', "Habiter un espace de jeu partagé —");
$ec_l2          = ps_mod('esthet_cite_ligne2', 'entre deux corps,');
$ec_em          = ps_mod('esthet_cite_em',     'un corps et un instrument');
$ec_src         = ps_mod('esthet_cite_source', "Poivre & Sens · Note d'intention");

$co_nom         = ps_mod('contact_nom',         'Poivre & Sens');
$co_statut      = ps_mod('contact_statut',      'Association loi 1901');
$co_direction   = ps_mod('contact_direction',   "Ambre Lavignac & Ewen d'Aviau");
$co_disc        = ps_mod('contact_disciplines', 'Danse · Contact-improvisation · Musique · Somatique');
$co_email       = ps_mod('contact_email',       'contact@cie.poivresens.fr');
$co_site        = ps_mod('contact_site',        'cie.poivresens.fr');
$co_email_a     = ps_mod('contact_email_ambre', 'ambre@cie.poivresens.fr');
$co_email_e     = ps_mod('contact_email_ewen',  'ewen@cie.poivresens.fr');
$co_reseaux     = ps_mod('contact_note_reseaux','Retrouvez Poivre & Sens dans les réseaux du spectacle vivant, les festivals de contact-improvisation et les scènes de musique improvisée en France et en Europe.');

$ft_l1          = ps_mod('footer_line1', "Compagnie de danse et musique improvisées · Association loi 1901");
$ft_l2          = ps_mod('footer_line2', "Direction artistique : Ambre Lavignac & Ewen d'Aviau");

// Tags helpers
function ps_render_tags(string $raw): string {
    $tags = array_filter(array_map('trim', explode(',', $raw)));
    return implode('', array_map(function($t) {
        return '<span class="bio__tg">' . esc_html($t) . '</span>';
    }, $tags));
}
function ps_render_disciplines(string $raw): string {
    $lines = array_filter(array_map('trim', explode("\n", $raw)));
    return implode('<br>', array_map('esc_html', $lines));
}
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
    <p class="hero__sup"><?php echo esc_html($hero_surtitle); ?></p>
    <h1 class="hero__nom">Poivre<span class="et">&amp;</span>Sens</h1>
    <p class="hero__disc">
      <strong><?php echo esc_html($ambre_nom); ?> &amp; <?php echo esc_html($ewen_nom); ?></strong>
      <?php echo ps_render_disciplines($hero_discs); ?>
    </p>
    <a href="#projet" class="hero__cta"><?php echo esc_html($hero_cta); ?></a>
  </div>
  <div class="hero__d">
    <blockquote class="hero__q"><?php echo esc_html($hero_quote); ?></blockquote>
    <p class="hero__intro"><?php echo esc_html($hero_intro); ?></p>
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
      Photos de la compagnie — ajoutez vos clichés via<br>
      <strong>Galerie › Ajouter</strong> dans l'admin WordPress.
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
    <h2 class="mf-t"><?php echo ps_titre_mf($mf_titre, $mf_em1, $mf_em2); ?></h2>
    <div class="mf-tx">
      <p><?php echo wp_kses_post($mf_p1); ?></p>
      <p><?php echo wp_kses_post($mf_p2); ?></p>
      <p><?php echo wp_kses_post($mf_p3); ?></p>
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
    <div class="axe"><p class="axe__n">01</p><h3 class="axe__t">Création chorégraphique &amp; musicale</h3><p class="axe__tx">Des pièces scéniques en duo ou avec artistes invités, où la frontière entre la composition musicale et la partition corporelle s'efface. Le musicien se déplace, la danseuse émet, le son se fait matière, le corps se fait résonance.</p></div>
    <div class="axe"><p class="axe__n">02</p><h3 class="axe__t">L'improvisation comme forme</h3><p class="axe__tx">Non pas une absence de forme, mais une forme en devenir. Jams ouvertes, laboratoires de recherche, performances situées dans des espaces non conventionnels : parcs, friches industrielles, espaces naturels.</p></div>
    <div class="axe"><p class="axe__n">03</p><h3 class="axe__t">La pédagogie du sensible</h3><p class="axe__tx">Stages, ateliers de mouvement somatique, ateliers de musique, résidences pédagogiques en milieu scolaire, médico-social ou en entreprise. La transmission est au cœur du projet.</p></div>
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
    <!-- Ambre -->
    <div class="bio">
      <div class="bio__hd">
        <div class="bio__mn" aria-hidden="true"><?php echo esc_html($ambre_init); ?></div>
        <div>
          <h3 class="bio__nom"><?php echo esc_html($ambre_nom); ?></h3>
          <p class="bio__rol"><?php echo esc_html($ambre_role); ?></p>
        </div>
      </div>
      <p class="bio__tx"><?php echo esc_html($ambre_bio1); ?></p>
      <p class="bio__tx"><?php echo esc_html($ambre_bio2); ?></p>
      <div class="bio__tgs"><?php echo ps_render_tags($ambre_tags_raw); ?></div>
    </div>
    <!-- Ewen -->
    <div class="bio">
      <div class="bio__hd">
        <div class="bio__mn" aria-hidden="true"><?php echo esc_html($ewen_init); ?></div>
        <div>
          <h3 class="bio__nom"><?php echo esc_html($ewen_nom); ?></h3>
          <p class="bio__rol"><?php echo esc_html($ewen_role); ?></p>
        </div>
      </div>
      <p class="bio__tx"><?php echo esc_html($ewen_bio1); ?></p>
      <p class="bio__tx"><?php echo esc_html($ewen_bio2); ?></p>
      <div class="bio__tgs"><?php echo ps_render_tags($ewen_tags_raw); ?></div>
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
  <?php if ($evts_q->have_posts()):
    $today   = date('Y-m-d');
    $jours_fr = ['Sun'=>'Dim','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam'];
    ?>
  <div class="cal-list cal-list--compact">
    <?php while ($evts_q->have_posts()): $evts_q->the_post();
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
        <span class="cal-list__day-ltr"><?= $ts ? esc_html($jours_fr[date('D',$ts)] ?? date('D',$ts)) : '' ?></span>
        <span class="cal-list__day-num"><?= $ts ? esc_html(date('j',$ts)) : '?' ?></span>
        <span style="font-size:.6rem;color:var(--or);letter-spacing:.1em;text-transform:uppercase"><?= $ts ? esc_html(date_i18n('M',$ts)) : '' ?></span>
      </div>
      <div class="cal-list__line" aria-hidden="true"></div>
      <div class="cal-list__body">
        <?php if ($ty): ?><span class="cal-list__type"><?= esc_html(ps_evt_type_label($ty)) ?></span><?php endif; ?>
        <?php if ($cp): ?><span class="cal-list__complet"><?php _e('Complet','poivre-sens'); ?></span><?php endif; ?>
        <h3 class="cal-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <ul class="cal-list__meta" role="list">
          <?php if ($h): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🕐</span><?= esc_html($h) ?></li><?php endif; ?>
          <?php if ($l||$v): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">📍</span><?= esc_html(implode(', ',array_filter([$l,$v]))) ?></li><?php endif; ?>
          <?php if ($p): ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🎟</span><?= esc_html($p) ?></li><?php endif; ?>
        </ul>
        <div class="cal-list__actions">
          <a href="<?php the_permalink(); ?>" class="cal-list__action-link"><?php _e('En savoir plus','poivre-sens'); ?> →</a>
          <?php if ($b && !$cp): ?><a href="<?= esc_url($b) ?>" class="cal-list__action-btn" target="_blank" rel="noopener"><?php _e('Réserver','poivre-sens'); ?></a><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>
  <a href="<?= esc_url(get_post_type_archive_link('evenement')) ?>" class="evts__lien"><?php _e('Voir tout l\'agenda','poivre-sens'); ?></a>
  <?php else: ?>
  <div style="padding:48px 0;text-align:center;color:var(--gris);font-size:.9rem">
    <?php _e('Aucun événement programmé pour le moment.','poivre-sens'); ?>
    <?php if (current_user_can('publish_posts')): ?>
    <br><br><a href="<?= esc_url(admin_url('post-new.php?post_type=evenement')) ?>" class="evts__lien">+ <?php _e('Créer un événement','poivre-sens'); ?></a>
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
      <div class="val"><p class="val__l">Artisanat</p><p class="val__t">Instruments fabriqués ou modifiés par <?php echo esc_html($ewen_nom); ?>. Costumes et accessoires pensés en rapport avec la matière corporelle.</p></div>
      <div class="val"><p class="val__l">Philosophie</p><p class="val__t">Inspiré du Tao, des méridiens, de l'aïkido — fluidité, transformation, redirection, vacuité. Non-résistance, accord avec la force de l'autre.</p></div>
    </div>
    <div class="esthet__cite">
      <blockquote class="gcite">
        <?php echo esc_html($ec_l1); ?><br>
        <?php echo esc_html($ec_l2); ?><br>
        <em><?php echo esc_html($ec_em); ?></em>.
      </blockquote>
      <p class="gcite__src"><?php echo esc_html($ec_src); ?></p>
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
      <div class="co-row"><span class="co-k">Nom</span><span class="co-v"><?php echo esc_html($co_nom); ?></span></div>
      <div class="co-row"><span class="co-k">Statut</span><span class="co-v"><?php echo esc_html($co_statut); ?></span></div>
      <div class="co-row"><span class="co-k">Direction</span><span class="co-v"><?php echo esc_html($co_direction); ?></span></div>
      <div class="co-row"><span class="co-k">Disciplines</span><span class="co-v"><?php echo esc_html($co_disc); ?></span></div>
      <div class="co-row"><span class="co-k">Courriel</span><span class="co-v"><a href="mailto:<?php echo esc_attr($co_email); ?>"><?php echo esc_html($co_email); ?></a></span></div>
      <div class="co-row"><span class="co-k">Site</span><span class="co-v"><a href="https://<?php echo esc_attr($co_site); ?>"><?php echo esc_html($co_site); ?></a></span></div>
    </div>
    <div class="co-col">
      <p class="co-h">Les fondateurs</p>
      <div class="co-row"><span class="co-k"><?php echo esc_html(explode(' ', $ambre_nom)[0]); ?></span><span class="co-v"><a href="mailto:<?php echo esc_attr($co_email_a); ?>"><?php echo esc_html($co_email_a); ?></a></span></div>
      <div class="co-row"><span class="co-k"><?php echo esc_html(explode(' ', $ewen_nom)[0]); ?></span><span class="co-v"><a href="mailto:<?php echo esc_attr($co_email_e); ?>"><?php echo esc_html($co_email_e); ?></a></span></div>
      <p class="co-h" style="margin-top:32px">Suivre la compagnie</p>
      <p style="font-size:.88rem;line-height:1.75;color:rgba(236,227,203,.55);margin-top:8px"><?php echo esc_html($co_reseaux); ?></p>
    </div>
  </div>
</section>

<?php get_footer();
