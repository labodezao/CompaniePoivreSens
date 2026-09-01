<?php
/**
 * Poivre & Sens — Shortcodes & Patterns Gutenberg
 *
 * Le contenu de la page d'accueil est édité directement dans
 * l'éditeur Gutenberg. Insérez le pattern « Page d'accueil complète »
 * pour démarrer, puis éditez chaque section en place.
 *
 * Sections entièrement éditables (blocs Gutenberg natifs) :
 *   Hero · Manifeste · Artistes · Références/influences
 *   Projet artistique · Nos activités · Esthétique · Contact
 *
 * Shortcodes dynamiques (contenu chargé depuis les CPT/formulaire) :
 *   [ps_galerie]         — galerie photos (CPT « Photo »)
 *   [ps_evenements]      — prochains événements (CPT « Événement »)
 *   [ps_newsletter]      — formulaire d'inscription newsletter (section complète)
 *   [ps_newsletter_liste slug="..." bouton="..."] — formulaire compact rattaché
 *        à une liste précise, à insérer (bloc Shortcode) au milieu de blocs
 *        Gutenberg natifs entièrement éditables — pour composer une landing
 *        page dédiée (ex. un événement) sans passer par un modèle de page.
 *
 * Patterns disponibles dans Blocs › Patterns › Poivre & Sens :
 *   ① Hero, ② Manifeste, ③ Artistes, ④ Projet artistique,
 *   ⑤ Nos activités, ⑥ Événements, ⑦ Esthétique, ⑧ Contact,
 *      Page d'accueil complète
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

/** [ps_evenements] — Prochains événements, quelle qu'en soit la source */
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
          $id = get_the_ID();
          $d  = ps_evt_champ($id, 'date');
          $h  = ps_evt_champ($id, 'heure');
          $l  = ps_evt_champ($id, 'lieu');
          $v  = ps_evt_champ($id, 'ville');
          $ty = ps_evt_champ($id, 'type_label');
          $p  = ps_evt_champ($id, 'prix');
          $b  = ps_evt_champ($id, 'billetterie');
          $cp = ps_evt_champ($id, 'complet');
          $se = ps_evt_champ($id, 'statut_event') ?: 'publie';
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
            <?php if ($ty) : ?><span class="cal-list__type"><?= esc_html($ty) ?></span><?php endif; ?>
            <?php if ($se === 'annule') : ?><span class="cal-list__complet"><?php _e('Annulé', 'poivre-sens'); ?></span>
            <?php elseif ($se === 'reporte') : ?><span class="cal-list__complet"><?php _e('Reporté', 'poivre-sens'); ?></span>
            <?php elseif ($cp) : ?><span class="cal-list__complet"><?php _e('Complet', 'poivre-sens'); ?></span><?php endif; ?>
            <h3 class="cal-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <ul class="cal-list__meta" role="list">
              <?php if ($h) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🕐</span><?= esc_html($h) ?></li><?php endif; ?>
              <?php if ($l || $v) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">📍</span><?= esc_html(implode(', ', array_filter([$l, $v]))) ?></li><?php endif; ?>
              <?php if ($p) : ?><li class="cal-list__meta-item"><span class="cal-list__meta-ic">🎟</span><?= esc_html($p) ?></li><?php endif; ?>
            </ul>
            <div class="cal-list__actions">
              <a href="<?php the_permalink(); ?>" class="cal-list__action-link"><?php _e('En savoir plus', 'poivre-sens'); ?> →</a>
              <?php if ($b && !$cp && $se === 'publie') : ?><a href="<?= esc_url($b) ?>" class="cal-list__action-btn" target="_blank" rel="noopener"><?php _e('Réserver', 'poivre-sens'); ?></a><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <a href="<?= esc_url(get_post_type_archive_link(ps_evt_cpt())) ?>" class="evts__lien"><?php _e("Voir tout l'agenda", 'poivre-sens'); ?></a>
      <?php else : ?>
      <div style="padding:48px 0;text-align:center;color:var(--gris);font-size:.9rem">
        <?php _e('Aucun événement programmé pour le moment.', 'poivre-sens'); ?>
        <?php if (current_user_can('publish_posts')) : ?>
        <br><br><a href="<?= esc_url(admin_url('post-new.php?post_type=' . ps_evt_cpt())) ?>" class="evts__lien">+ <?php _e('Créer un événement', 'poivre-sens'); ?></a>
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

/**
 * [ps_newsletter_liste] — Formulaire d'inscription compact, rattaché à une
 * liste précise (pour les landing pages composées à la main dans Gutenberg,
 * ex. un événement). À insérer dans un bloc Shortcode, au milieu de blocs
 * natifs (titre, paragraphe, liste…) librement éditables dans l'éditeur.
 *
 * Attributs :
 *   slug        — slug de la liste à rattacher (doit déjà exister, voir
 *                 Newsletter › Listes). Vide = pas de liste, comportement
 *                 par défaut (source "site").
 *   bouton      — texte du bouton (défaut « Je m'inscrire »)
 *   placeholder — texte indicatif du champ e-mail
 *
 * Exemple : [ps_newsletter_liste slug="grand-bal-europe" bouton="Recevez votre pratique"]
 */
add_shortcode('ps_newsletter_liste', function ($atts): string {
    $atts = shortcode_atts([
        'slug'        => '',
        'bouton'      => __("Je m'inscrire", 'poivre-sens'),
        'placeholder' => __('prenom@exemple.fr', 'poivre-sens'),
    ], $atts, 'ps_newsletter_liste');

    static $instance = 0;
    $instance++;
    $uid   = 'ps-nlf-' . $instance;
    $liste = sanitize_title($atts['slug']);
    $nonce = wp_create_nonce('ps_newsletter');

    ob_start();
    ?>
    <div class="ps-nlf" id="<?= esc_attr($uid) ?>">
      <style>
        /* Marge propre au shortcode : ne dépend pas du conteneur qui
           l'entoure (bloc Shortcode, sans support de marge Gutenberg). */
        #<?= esc_attr($uid) ?>{ margin: 4px 0 1.4em; }
        #<?= esc_attr($uid) ?> .ps-nlf-row{ display:flex; flex-direction:column; gap:12px; max-width:440px; }
        #<?= esc_attr($uid) ?> input[type=email]{
          font-size:16px; padding:15px 16px; border:1.5px solid rgba(0,0,0,.18);
          border-radius:10px; width:100%; box-sizing:border-box;
        }
        #<?= esc_attr($uid) ?> input[type=email]:focus{ outline:none; border-color:#9C3E1C; box-shadow:0 0 0 3px rgba(156,62,28,.14); }
        #<?= esc_attr($uid) ?> button{
          font-size:16px; font-weight:600; padding:15px 18px; border:none; border-radius:10px;
          cursor:pointer; background:#9C3E1C; color:#fff; width:100%; transition:background .15s, opacity .15s;
        }
        #<?= esc_attr($uid) ?> button:hover{ background:#b04a24; }
        #<?= esc_attr($uid) ?> button:disabled{ opacity:.65; cursor:default; }
        #<?= esc_attr($uid) ?> .ps-nlf-msg{ font-size:14px; line-height:1.5; margin-top:6px; padding:12px 14px; border-radius:10px; display:none; }
        #<?= esc_attr($uid) ?> .ps-nlf-msg.error{ display:block; background:rgba(156,62,28,.10); color:#9C3E1C; }
        #<?= esc_attr($uid) ?> .ps-nlf-msg.ok{ display:block; background:rgba(87,98,74,.12); color:#57624A; }
      </style>

      <form class="ps-nlf-row" novalidate>
        <input type="email" name="email" placeholder="<?= esc_attr($atts['placeholder']) ?>" autocomplete="email" required>
        <button type="submit"><?= esc_html($atts['bouton']) ?></button>
        <div class="ps-nlf-msg" role="alert" aria-live="polite"></div>
      </form>

      <script>
      (function(){
        var root = document.getElementById(<?= wp_json_encode($uid) ?>);
        var form = root.querySelector('form');
        var btn  = form.querySelector('button');
        var msg  = root.querySelector('.ps-nlf-msg');
        var label = btn.textContent;

        form.addEventListener('submit', function(e){
          e.preventDefault();
          msg.className = 'ps-nlf-msg';
          var email = form.email.value.trim();
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
            msg.textContent = <?= wp_json_encode(__('Vérifiez votre adresse e-mail.', 'poivre-sens')) ?>;
            msg.className = 'ps-nlf-msg error';
            return;
          }
          var data = new FormData();
          data.append('action', 'ps_newsletter_subscribe');
          data.append('nonce', <?= wp_json_encode($nonce) ?>);
          data.append('email', email);
          data.append('liste', <?= wp_json_encode($liste) ?>);

          btn.disabled = true;
          btn.textContent = <?= wp_json_encode(__('Envoi…', 'poivre-sens')) ?>;

          fetch(<?= wp_json_encode(admin_url('admin-ajax.php')) ?>, { method:'POST', body:data })
            .then(function(r){ return r.json(); })
            .then(function(res){
              msg.className = 'ps-nlf-msg ' + (res.success ? 'ok' : 'error');
              msg.textContent = (res.data && res.data.message) || '';
              if (res.success) { form.reset(); btn.style.display = 'none'; }
            })
            .catch(function(){
              msg.className = 'ps-nlf-msg error';
              msg.textContent = <?= wp_json_encode(__('Connexion impossible. Réessayez.', 'poivre-sens')) ?>;
            })
            .finally(function(){
              btn.disabled = false;
              if (btn.style.display !== 'none') btn.textContent = label;
            });
        });
      })();
      </script>
    </div>
    <?php
    return ob_get_clean();
});

/** [ps_projet] — Section projet artistique (axes créatifs) */
add_shortcode('ps_projet', function (): string {
    $t = ps_textes()['projet'];
    ob_start();
    ?>
    <section class="sec" id="projet" aria-labelledby="titre-projet">
      <div style="margin-bottom:56px">
        <p class="lbl"><?= $t['label'] ?></p>
        <h2 class="sh" id="titre-projet"><?= $t['titre'] ?></h2>
        <div class="regle"></div>
      </div>
      <div class="axes">
        <?php foreach ($t['axes'] as $axe) : ?>
        <div class="axe">
          <p class="axe__n"><?= $axe['num'] ?></p>
          <h3 class="axe__t"><?= $axe['titre'] ?></h3>
          <p class="axe__tx"><?= $axe['texte'] ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_influences] — Références & influences (dans la section artistes) */
add_shortcode('ps_influences', function (): string {
    $t = ps_textes()['influences'];
    ob_start();
    ?>
    <div>
      <p class="lbl" style="margin-top:0"><?= $t['label'] ?></p>
      <div class="regle" style="margin-bottom:28px"></div>
      <div class="influences">
        <?php foreach ($t['items'] as $inf) : ?>
        <div class="inf"><p class="inf__n"><?= $inf[0] ?></p><p class="inf__d"><?= $inf[1] ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/** [ps_activites] — Section nos activités + axes de diffusion */
add_shortcode('ps_activites', function (): string {
    $t = ps_textes()['activites'];
    ob_start();
    ?>
    <section class="sec" id="activites" aria-labelledby="titre-activites">
      <div style="margin-bottom:56px">
        <p class="lbl"><?= $t['label'] ?></p>
        <h2 class="sh" id="titre-activites"><?= $t['titre'] ?></h2>
        <div class="regle"></div>
        <p class="act-chapeau"><?= $t['chapeau'] ?></p>
      </div>
      <ul role="list">
        <?php foreach ($t['items'] as $a) : ?>
        <li class="act"><span class="act__n" aria-hidden="true"><?= $a['num'] ?></span><div><h3 class="act__t"><?= $a['titre'] ?></h3><p class="act__tx"><?= $a['texte'] ?></p></div><span class="act__b"><?= $a['badge'] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div>
        <p class="lbl" style="margin-top:64px"><?= $t['diffusion_label'] ?></p>
        <div class="regle" style="margin-bottom:28px"></div>
        <div class="diff">
          <?php foreach ($t['diffusion'] as $d) : ?>
          <div class="diff-i"><?= $d ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/** [ps_valeurs] — Valeurs esthétiques (colonne gauche de la section esthétique) */
add_shortcode('ps_valeurs', function (): string {
    $valeurs = ps_textes()['esthetique']['valeurs'];
    ob_start();
    ?>
    <div class="esthet__vals">
      <?php foreach ($valeurs as $v) : ?>
      <div class="val"><p class="val__l"><?= $v[0] ?></p><p class="val__t"><?= $v[1] ?></p></div>
      <?php endforeach; ?>
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
                       . _ps_pat_projet() . _ps_pat_activites()
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

    register_block_pattern('poivre-sens/projet', [
        'title'      => '④ Projet artistique',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_projet(),
    ]);

    register_block_pattern('poivre-sens/activites', [
        'title'      => '⑤ Nos activités',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_activites(),
    ]);

    register_block_pattern('poivre-sens/evenements', [
        'title'       => '⑥ Événements à venir',
        'description' => 'Liste dynamique des prochains événements. Alimentée via Événements › Ajouter.',
        'categories'  => ['poivre-sens'],
        'content'     => _ps_pat_evenements_sc(),
    ]);

    register_block_pattern('poivre-sens/esthetique', [
        'title'      => '⑦ Esthétique &amp; citation',
        'categories' => ['poivre-sens'],
        'content'    => _ps_pat_esthetique(),
    ]);

    register_block_pattern('poivre-sens/contact', [
        'title'      => '⑧ Contact',
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
function _ps_pat_evenements_sc(): string { return _ps_sc('ps_evenements'); }
function _ps_pat_newsletter_sc(): string { return _ps_sc('ps_newsletter'); }

/* ── ① Hero ────────────────────────────────────────────────── */
function _ps_pat_hero(): string {
    $t     = ps_textes()['hero'];
    $disc  = implode('<br>', $t['disciplines']);
    $sup   = $t['surtitre'];
    $cita  = $t['citation'];
    $intro = $t['intro'];
    $cta   = $t['cta'];
    return <<<BLOCK

<!-- wp:group {"tagName":"section","className":"hero","anchor":"accueil","layout":{"type":"default"}} -->
<section class="wp-block-group hero" id="accueil">

<!-- wp:group {"className":"hero__g","layout":{"type":"default"}} -->
<div class="wp-block-group hero__g">

<!-- wp:paragraph {"className":"hero__sup"} -->
<p class="hero__sup">{$sup}</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hero__nom"} -->
<h1 class="wp-block-heading hero__nom">Poivre<span class="et">&amp;</span>Sens</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hero__disc"} -->
<p class="hero__disc"><strong>Ambre Lavignac &amp; Ewen d'Aviau</strong><br>{$disc}</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#projet" class="hero__cta">{$cta}</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

<!-- wp:group {"className":"hero__d","layout":{"type":"default"}} -->
<div class="wp-block-group hero__d">

<!-- wp:paragraph {"className":"hero__q"} -->
<p class="hero__q">{$cita}</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"hero__intro"} -->
<p class="hero__intro">{$intro}</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ── ② Manifeste ───────────────────────────────────────────── */
function _ps_pat_manifeste(): string {
    $t     = ps_textes()['manifeste'];
    $label = $t['label'];
    $titre = $t['titre_html'];
    $paras = '';
    foreach ($t['paragraphes'] as $para) {
        $paras .= "\n<!-- wp:paragraph -->\n<p>{$para}</p>\n<!-- /wp:paragraph -->\n";
    }
    return <<<BLOCK

<!-- wp:group {"tagName":"div","className":"manifeste sec3","layout":{"type":"default"}} -->
<div class="wp-block-group manifeste sec3">

<!-- wp:paragraph {"className":"mf-ax"} -->
<p class="mf-ax">{$label}</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"mf-corps","layout":{"type":"default"}} -->
<div class="wp-block-group mf-corps">

<!-- wp:heading {"level":2,"className":"mf-t"} -->
<h2 class="wp-block-heading mf-t">{$titre}</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"mf-tx","layout":{"type":"default"}} -->
<div class="wp-block-group mf-tx">
{$paras}
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
    $t     = ps_textes()['artistes'];
    $label = $t['label'];
    $titre = $t['titre'];

    $bios = '';
    foreach ($t['bios'] as $b) {
        $ini  = $b['initiale'];
        $nom  = $b['nom'];
        $role = $b['role'];
        $txts = '';
        foreach ($b['textes'] as $tx) {
            $txts .= "\n<!-- wp:paragraph {\"className\":\"bio__tx\"} -->\n<p class=\"bio__tx\">{$tx}</p>\n<!-- /wp:paragraph -->\n";
        }
        $tags = '';
        foreach ($b['tags'] as $tag) {
            $tags .= '<span class="bio__tg">' . $tag . '</span>';
        }
        $bios .= <<<BIO

<!-- wp:group {"className":"bio","layout":{"type":"default"}} -->
<div class="wp-block-group bio">
<!-- wp:group {"className":"bio__hd","layout":{"type":"default"}} -->
<div class="wp-block-group bio__hd">
<!-- wp:group {"className":"bio__mn","layout":{"type":"default"}} -->
<div class="wp-block-group bio__mn">
<!-- wp:paragraph -->
<p>{$ini}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"className":"bio__nom"} -->
<h3 class="wp-block-heading bio__nom">{$nom}</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"bio__rol"} -->
<p class="bio__rol">{$role}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
{$txts}
<!-- wp:paragraph {"className":"bio__tgs"} -->
<p class="bio__tgs">{$tags}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

BIO;
    }

    $opening = <<<BLOCK

<!-- wp:group {"tagName":"section","className":"sec sec2","anchor":"artistes","layout":{"type":"default"}} -->
<section class="wp-block-group sec sec2" id="artistes">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">{$label}</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">{$titre}</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"bios","layout":{"type":"default"}} -->
<div class="wp-block-group bios">
{$bios}
</div>
<!-- /wp:group -->

BLOCK;
    $closing = <<<'BLOCK'

</section>
<!-- /wp:group -->

BLOCK;
    return $opening . _ps_pat_influences() . $closing;
}

/* ── ⑦ Esthétique ──────────────────────────────────────────── */
function _ps_pat_esthetique(): string {
    $t      = ps_textes()['esthetique'];
    $label  = $t['label'];
    $titre  = $t['titre'];
    $cite   = $t['citation_html'];
    $source = $t['citation_source'];

    $before = <<<BLOCK

<!-- wp:group {"tagName":"section","className":"sec","anchor":"esthetique","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="esthetique">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">{$label}</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">{$titre}</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"esthet","layout":{"type":"default"}} -->
<div class="wp-block-group esthet">

BLOCK;
    $after = <<<BLOCK

<!-- wp:group {"className":"esthet__cite","layout":{"type":"default"}} -->
<div class="wp-block-group esthet__cite">
<!-- wp:quote {"className":"gcite"} -->
<blockquote class="wp-block-quote gcite"><p>{$cite}</p><cite>{$source}</cite></blockquote>
<!-- /wp:quote -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
    return $before . _ps_pat_valeurs() . $after;
}

/* ── ⑤ Contact ─────────────────────────────────────────────── */
function _ps_pat_contact(): string {
    $t     = ps_textes()['contact'];
    $label = $t['label'];
    $titre = $t['titre'];
    $note  = $t['note'];
    return <<<BLOCK

<!-- wp:group {"tagName":"section","className":"sec","anchor":"contact","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="contact">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">{$label}</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">{$titre}</h2>
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
<p class="co-note">{$note}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ══════════════════════════════════════════════════════════════
   4. NOUVELLES FONCTIONS — Blocs Gutenberg natifs (éditables)
   ══════════════════════════════════════════════════════════════ */

/* ── Références & influences ───────────────────────────────── */
function _ps_pat_influences(): string {
    $t     = ps_textes()['influences'];
    $label = $t['label'];
    $items = '';
    foreach ($t['items'] as $inf) {
        $nom  = $inf[0];
        $desc = $inf[1];
        $items .= <<<INF

<!-- wp:group {"className":"inf","layout":{"type":"default"}} -->
<div class="wp-block-group inf">
<!-- wp:paragraph {"className":"inf__n"} --><p class="inf__n">{$nom}</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"inf__d"} --><p class="inf__d">{$desc}</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

INF;
    }
    return <<<BLOCK

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">

<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl" style="margin-top:0">{$label}</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->

<!-- wp:group {"className":"influences","layout":{"type":"default"}} -->
<div class="wp-block-group influences">
{$items}
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

BLOCK;
}

/* ── Valeurs esthétiques ───────────────────────────────────── */
function _ps_pat_valeurs(): string {
    $items = '';
    foreach (ps_textes()['esthetique']['valeurs'] as $v) {
        $label = $v[0];
        $texte = $v[1];
        $items .= <<<VAL

<!-- wp:group {"className":"val","layout":{"type":"default"}} -->
<div class="wp-block-group val">
<!-- wp:paragraph {"className":"val__l"} --><p class="val__l">{$label}</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"val__t"} --><p class="val__t">{$texte}</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

VAL;
    }
    return <<<BLOCK

<!-- wp:group {"className":"esthet__vals","layout":{"type":"default"}} -->
<div class="wp-block-group esthet__vals">
{$items}
</div>
<!-- /wp:group -->

BLOCK;
}

/* ── ④ Projet artistique ───────────────────────────────────── */
function _ps_pat_projet(): string {
    $t     = ps_textes()['projet'];
    $label = $t['label'];
    $titre = $t['titre'];
    $axes  = '';
    foreach ($t['axes'] as $a) {
        $num   = $a['num'];
        $at    = $a['titre'];
        $atx   = $a['texte'];
        $axes .= <<<AXE

<!-- wp:group {"className":"axe","layout":{"type":"default"}} -->
<div class="wp-block-group axe">
<!-- wp:paragraph {"className":"axe__n"} --><p class="axe__n">{$num}</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3,"className":"axe__t"} -->
<h3 class="wp-block-heading axe__t">{$at}</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"axe__tx"} -->
<p class="axe__tx">{$atx}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

AXE;
    }
    return <<<BLOCK

<!-- wp:group {"tagName":"section","className":"sec","anchor":"projet","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="projet">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">{$label}</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">{$titre}</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"axes","layout":{"type":"default"}} -->
<div class="wp-block-group axes">
{$axes}
</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}

/* ── ⑤ Nos activités ───────────────────────────────────────── */
function _ps_pat_activites(): string {
    $t       = ps_textes()['activites'];
    $label   = $t['label'];
    $titre   = $t['titre'];
    $chapeau = $t['chapeau'];

    $items = '';
    foreach ($t['items'] as $a) {
        $num   = $a['num'];
        $at    = $a['titre'];
        $atx   = $a['texte'];
        $badge = $a['badge'];
        $items .= <<<ACT

<!-- wp:group {"className":"act","layout":{"type":"default"}} -->
<div class="wp-block-group act">
<!-- wp:paragraph {"className":"act__n"} --><p class="act__n" aria-hidden="true">{$num}</p><!-- /wp:paragraph -->
<!-- wp:group {"layout":{"type":"default"}} --><div class="wp-block-group">
<!-- wp:heading {"level":3,"className":"act__t"} --><h3 class="wp-block-heading act__t">{$at}</h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"act__tx"} --><p class="act__tx">{$atx}</p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
<!-- wp:paragraph {"className":"act__b"} --><p class="act__b">{$badge}</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

ACT;
    }

    $diff_label = $t['diffusion_label'];
    $diffs = '';
    foreach ($t['diffusion'] as $d) {
        $diffs .= '<!-- wp:paragraph {"className":"diff-i"} --><p class="diff-i">' . $d . '</p><!-- /wp:paragraph -->' . "\n";
    }

    return <<<BLOCK

<!-- wp:group {"tagName":"section","className":"sec","anchor":"activites","layout":{"type":"default"}} -->
<section class="wp-block-group sec" id="activites">

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:56px">
<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl">{$label}</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"sh"} -->
<h2 class="wp-block-heading sh">{$titre}</h2>
<!-- /wp:heading -->
<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->
<!-- wp:paragraph {"className":"act-chapeau"} -->
<p class="act-chapeau">{$chapeau}</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
{$items}
</div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">

<!-- wp:paragraph {"className":"lbl"} -->
<p class="lbl" style="margin-top:64px">{$diff_label}</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"regle"} -->
<hr class="wp-block-separator regle"/>
<!-- /wp:separator -->

<!-- wp:group {"className":"diff","layout":{"type":"default"}} -->
<div class="wp-block-group diff">
{$diffs}
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->

BLOCK;
}
