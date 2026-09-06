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
 *   [ps_temoignages]     — témoignages publiés (CPT « Témoignage ») — absent
 *        de la page tant qu'aucun n'est publié (voir inc/testimonials.php)
 *   [ps_newsletter]      — formulaire d'inscription newsletter (section complète)
 *   [ps_newsletter_liste slug="..." bouton="..."] — formulaire compact rattaché
 *        à une liste précise, à insérer (bloc Shortcode) au milieu de blocs
 *        Gutenberg natifs entièrement éditables — pour composer une landing
 *        page dédiée (ex. un événement) sans passer par un modèle de page.
 *
 * Patterns disponibles dans Blocs › Patterns › Poivre & Sens :
 *   ① Hero, ② Manifeste, ③ Artistes, ④ Projet artistique,
 *   ⑤ Nos activités, ⑥ Événements, ⑦ Esthétique, ⑧ Témoignages, ⑨ Contact,
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
            $id = get_the_ID();
            // « ?: » traiterait la chaîne "0" (point de mise au point sur le
            // bord gauche/haut) comme fausse et la remplacerait par 50 à tort.
            $fx = get_post_meta($id, '_galerie_focus_x', true);
            $fy = get_post_meta($id, '_galerie_focus_y', true);
            $fx = $fx === '' ? '50' : $fx;
            $fy = $fy === '' ? '50' : $fy;
            $items[] = [
                'img'     => get_the_post_thumbnail_url(null, 'galerie-thumb')
                             ?: ($theme_img . 'galerie-0' . ($i + 1) . '-' . $svg_slugs[$i] . '.svg'),
                'alt'     => get_the_title(),
                'titre'   => get_the_title(),
                'caption' => get_post_meta($id, '_galerie_caption', true)
                             ?: ($svg_caps_def[$i][1] ?? ''),
                'focus'   => "{$fx}% {$fy}%",
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
            'focus'   => '50% 50%',
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
      </div>
      <div class="galerie__g" role="list">
        <?php foreach ($items as $item) : ?>
        <figure class="photo" role="listitem" aria-label="<?= esc_attr($item['titre']) ?>">
          <img src="<?= esc_url($item['img']) ?>" alt="<?= esc_attr($item['alt']) ?>" loading="lazy" style="object-position:<?= esc_attr($item['focus']) ?>">
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
    $nb_evenements = class_exists('CF_Admin') ? (int) CF_Admin::get_options()['evenements_accueil_nombre'] : 3;
    $q        = ps_get_upcoming_events($nb_evenements ?: 3);
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
          $tys = ps_evt_champ($id, 'type');
          $tyc = ps_evt_champ($id, 'type_color');
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
            <?php if ($ty) : ?><span class="cal-list__type cal-list__type--<?= esc_attr(sanitize_html_class($tys ?: 'autre')) ?>"<?= $tyc ? ' style="color:' . esc_attr($tyc) . ';border-color:' . esc_attr($tyc) . '"' : '' ?>><?= esc_html($ty) ?></span><?php endif; ?>
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

/**
 * [ps_hero] [ps_manifeste] [ps_artistes] [ps_esthetique] [ps_contact]
 *
 * Avant ces cinq-là, seules galerie/projet/activités/événements/valeurs/
 * influences/newsletter étaient des sections « vivantes » (un shortcode,
 * relu à chaque affichage depuis ps_textes()) — hero, manifeste, les
 * bios des fondateurs, la citation et la note de contact n'existaient
 * qu'en blocs Gutenberg figés, insérés une fois puis jamais recalculés.
 * Éditer ces champs dans Réglages › Textes du site n'avait donc aucun
 * effet sur eux : deux systèmes qui semblaient n'en faire qu'un.
 *
 * Ces cinq shortcodes ferment cet écart : toutes les sections de la
 * page d'accueil sont désormais lues depuis la même source, sans
 * exception.
 */
add_shortcode('ps_hero', function (): string {
    $t = ps_textes()['hero'];
    ob_start();
    ?>
    <section class="hero" id="accueil">
      <div class="hero__g">
        <p class="hero__sup"><?= $t['surtitre'] ?></p>
        <h1 class="hero__nom">Poivre<span class="et">&amp;</span>Sens</h1>
        <p class="hero__disc"><strong>Ambre Lavignac &amp; Ewen d'Aviau</strong><br><?= implode('<br>', $t['disciplines']) ?></p>
        <p><a href="<?= esc_url($t['cta_lien']) ?>" class="hero__cta"><?= $t['cta'] ?></a></p>
      </div>
      <div class="hero__d">
        <p class="hero__q"><?= $t['citation'] ?></p>
        <p class="hero__intro"><?= $t['intro'] ?></p>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

add_shortcode('ps_manifeste', function (): string {
    $t = ps_textes()['manifeste'];
    ob_start();
    ?>
    <div class="manifeste sec3">
      <p class="mf-ax"><?= $t['label'] ?></p>
      <div class="mf-corps">
        <h2 class="mf-t"><?= $t['titre_html'] ?></h2>
        <div class="mf-tx">
          <?php foreach ($t['paragraphes'] as $para) : ?>
          <p><?= $para ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('ps_artistes', function (): string {
    $t = ps_textes()['artistes'];
    ob_start();
    ?>
    <section class="sec sec2" id="artistes" aria-labelledby="titre-artistes">
      <div style="margin-bottom:56px">
        <p class="lbl"><?= $t['label'] ?></p>
        <h2 class="sh" id="titre-artistes"><?= $t['titre'] ?></h2>
        <div class="regle"></div>
      </div>
      <div class="bios">
        <?php foreach ($t['bios'] as $b) : ?>
        <div class="bio">
          <div class="bio__hd">
            <div class="bio__mn" aria-hidden="true"><?= $b['initiale'] ?></div>
            <div>
              <h3 class="bio__nom"><?= $b['nom'] ?></h3>
              <p class="bio__rol"><?= $b['role'] ?></p>
            </div>
          </div>
          <?php foreach ($b['textes'] as $tx) : ?>
          <p class="bio__tx"><?= $tx ?></p>
          <?php endforeach; ?>
          <p class="bio__tgs"><?php foreach ($b['tags'] as $tag) : ?><span class="bio__tg"><?= $tag ?></span><?php endforeach; ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?= do_shortcode('[ps_influences]') ?>
    </section>
    <?php
    return ob_get_clean();
});

add_shortcode('ps_esthetique', function (): string {
    $t = ps_textes()['esthetique'];
    ob_start();
    ?>
    <section class="sec" id="esthetique" aria-labelledby="titre-esthetique">
      <div style="margin-bottom:56px">
        <p class="lbl"><?= $t['label'] ?></p>
        <h2 class="sh" id="titre-esthetique"><?= $t['titre'] ?></h2>
        <div class="regle"></div>
      </div>
      <div class="esthet">
        <?= do_shortcode('[ps_valeurs]') ?>
        <div class="esthet__cite">
          <blockquote class="gcite">
            <?= $t['citation_html'] ?>
          </blockquote>
          <p class="gcite__src"><?= $t['citation_source'] ?></p>
        </div>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/**
 * [ps_temoignages] — Témoignages publiés (brouillon = pas encore autorisé
 * à la publication, voir inc/testimonials.php). Rien à afficher tant
 * qu'aucun n'est publié : section absente plutôt que vide.
 */
add_shortcode('ps_temoignages', function (): string {
    $q = new WP_Query([
        'post_type'      => 'temoignage',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);
    if (!$q->have_posts()) {
        return '';
    }
    ob_start();
    ?>
    <section class="sec sec2 temoignages" id="temoignages" aria-labelledby="titre-temoignages">
      <div style="margin-bottom:56px">
        <p class="lbl"><?php _e('Témoignages', 'poivre-sens') ?></p>
        <h2 class="sh" id="titre-temoignages"><?php _e('Ce qu\'ils en disent', 'poivre-sens') ?></h2>
        <div class="regle"></div>
      </div>
      <div class="tem-grid">
        <?php while ($q->have_posts()) : $q->the_post();
          $id         = get_the_ID();
          $role       = get_post_meta($id, '_temoignage_role', true);
          $etoiles    = (int) get_post_meta($id, '_temoignage_etoiles', true);
          $photo      = get_the_post_thumbnail_url($id, 'thumbnail');
          $video_url  = get_post_meta($id, '_temoignage_video', true);
          $video_html = $video_url ? wp_oembed_get($video_url, ['width' => 600]) : false;
        ?>
        <figure class="tem-card">
          <?php if ($etoiles > 0) : ?>
          <div class="tem-etoiles" aria-hidden="true"><?= str_repeat('★', $etoiles) . str_repeat('☆', 5 - $etoiles) ?></div>
          <?php endif; ?>
          <?php if ($video_html) : ?>
          <div class="tem-video"><?= $video_html ?></div>
          <?php else : ?>
          <blockquote class="tem-texte"><?php the_content(); ?></blockquote>
          <?php endif; ?>
          <figcaption class="tem-auteur">
            <?php if ($photo) : ?><img src="<?= esc_url($photo) ?>" alt="" class="tem-photo" loading="lazy"><?php endif; ?>
            <div>
              <p class="tem-nom"><?php the_title(); ?></p>
              <?php if ($role) : ?><p class="tem-role"><?= esc_html($role) ?></p><?php endif; ?>
            </div>
          </figcaption>
        </figure>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

add_shortcode('ps_contact', function (): string {
    $t = ps_textes()['contact'];
    ob_start();
    ?>
    <section class="sec" id="contact" aria-labelledby="titre-contact">
      <div style="margin-bottom:56px">
        <p class="lbl"><?= $t['label'] ?></p>
        <h2 class="sh" id="titre-contact"><?= $t['titre'] ?></h2>
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
          <div class="co-row"><span class="co-k">Ambre</span><span class="co-v"><a href="mailto:ambre@cie.poivresens.fr">ambre@cie.poivresens.fr</a></span></div>
          <div class="co-row"><span class="co-k">Ewen</span><span class="co-v"><a href="mailto:ewen@cie.poivresens.fr">ewen@cie.poivresens.fr</a></span></div>
          <h4 class="co-h" style="margin-top:32px;margin-bottom:28px">Suivre la compagnie</h4>
          <p class="co-note"><?= $t['note'] ?></p>
        </div>
      </div>
    </section>
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
                       . _ps_pat_temoignages_sc()
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

    register_block_pattern('poivre-sens/temoignages', [
        'title'       => '⑧ Témoignages',
        'description' => 'Absent tant qu\'aucun témoignage n\'est publié. Alimenté via Témoignages › Ajouter.',
        'categories'  => ['poivre-sens'],
        'content'     => _ps_pat_temoignages_sc(),
    ]);

    register_block_pattern('poivre-sens/contact', [
        'title'      => '⑨ Contact',
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
function _ps_pat_temoignages_sc(): string { return _ps_sc('ps_temoignages'); }

/* ── ① Hero ────────────────────────────────────────────────── */
function _ps_pat_hero(): string {
    return _ps_sc('ps_hero');
}

/* ── ② Manifeste ───────────────────────────────────────────── */
function _ps_pat_manifeste(): string {
    return _ps_sc('ps_manifeste');
}

/* ── ③ Artistes ────────────────────────────────────────────── */
function _ps_pat_artistes(): string {
    return _ps_sc('ps_artistes');
}

/* ── ⑦ Esthétique ──────────────────────────────────────────── */
function _ps_pat_esthetique(): string {
    return _ps_sc('ps_esthetique');
}

/* ── ⑤ Contact ─────────────────────────────────────────────── */
function _ps_pat_contact(): string {
    return _ps_sc('ps_contact');
}

/* ══════════════════════════════════════════════════════════════
   4. NOUVELLES FONCTIONS — Blocs Gutenberg natifs (éditables)
   ══════════════════════════════════════════════════════════════ */

/* ── Références & influences ───────────────────────────────── */
function _ps_pat_influences(): string {
    return _ps_sc('ps_influences');
}

/* ── Valeurs esthétiques ───────────────────────────────────── */
function _ps_pat_valeurs(): string {
    return _ps_sc('ps_valeurs');
}

/* ── ④ Projet artistique ───────────────────────────────────── */
function _ps_pat_projet(): string {
    return _ps_sc('ps_projet');
}

/* ── ⑤ Nos activités ───────────────────────────────────────── */
function _ps_pat_activites(): string {
    return _ps_sc('ps_activites');
}
