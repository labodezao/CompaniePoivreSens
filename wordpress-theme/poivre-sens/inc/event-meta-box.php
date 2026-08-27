<?php
/**
 * inc/event-meta-box.php
 *
 * Interface d'édition d'un événement : champs regroupés par thème
 * (quand / où / billetterie) et surtout un aperçu en direct montrant
 * le rendu réel — carte du site et extrait Google — mis à jour à
 * chaque frappe, sans avoir à enregistrer.
 */
defined('ABSPATH') || exit;

/** Types d'événement proposés. */
function ps_evt_types() {
    return [
        'spectacle' => __('Spectacle vivant', 'poivre-sens'),
        'jam'       => __('Jam contact-improvisation', 'poivre-sens'),
        'atelier'   => __('Atelier / Stage', 'poivre-sens'),
        'residence' => __('Résidence', 'poivre-sens'),
        'concert'   => __('Concert', 'poivre-sens'),
        'autre'     => __('Autre', 'poivre-sens'),
    ];
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ps_evt_details',
        __('Détails de l\'événement', 'poivre-sens'),
        'ps_evt_meta_box_html',
        'evenement',
        'normal',
        'high'
    );
});

function ps_evt_meta_box_html($post) {
    wp_nonce_field('ps_evt_save', 'ps_evt_nonce');

    $date        = get_post_meta($post->ID, '_evt_date',        true);
    $heure       = get_post_meta($post->ID, '_evt_heure',       true);
    $heure_fin   = get_post_meta($post->ID, '_evt_heure_fin',   true);
    $lieu        = get_post_meta($post->ID, '_evt_lieu',        true);
    $adresse     = get_post_meta($post->ID, '_evt_adresse',     true);
    $ville       = get_post_meta($post->ID, '_evt_ville',       true);
    $type        = get_post_meta($post->ID, '_evt_type',        true);
    $prix        = get_post_meta($post->ID, '_evt_prix',        true);
    $billetterie = get_post_meta($post->ID, '_evt_billetterie', true);
    $complet     = get_post_meta($post->ID, '_evt_complet',     true);

    $types  = ps_evt_types();
    $vignette = has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID, 'evt-card') : '';
    ?>
    <style>
    .ps-evt{ display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:24px; padding:6px 0 2px; }
    @media(max-width:1100px){ .ps-evt{ grid-template-columns:1fr; } }

    .ps-evt-sec{ border:1px solid #e3e3e3; border-radius:8px; padding:16px 18px 18px; margin-bottom:16px; background:#fff; }
    .ps-evt-sec:last-child{ margin-bottom:0; }
    .ps-evt-sec__t{ font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
                    color:#646970; margin:0 0 14px; display:flex; align-items:center; gap:7px; }
    .ps-evt-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; }
    .ps-evt-grid--3{ grid-template-columns:1fr 1fr 1fr; }
    .ps-evt-full{ grid-column:1/-1; }
    @media(max-width:782px){ .ps-evt-grid, .ps-evt-grid--3{ grid-template-columns:1fr; } }

    .ps-evt-lab{ display:block; font-weight:600; font-size:13px; margin-bottom:5px; color:#1d2327; }
    .ps-evt-hint{ font-size:11px; color:#8c8f94; margin:4px 0 0; line-height:1.5; }
    .ps-evt input[type=text], .ps-evt input[type=date], .ps-evt input[type=time],
    .ps-evt input[type=url], .ps-evt select{
        width:100%; padding:7px 10px; border:1px solid #dcdcde; border-radius:4px;
        font-size:13px; background:#fff; box-sizing:border-box;
    }
    .ps-evt input:focus, .ps-evt select:focus{ outline:none; border-color:#c28b36; box-shadow:0 0 0 2px rgba(194,139,54,.18); }
    .ps-evt-check{ display:flex; align-items:center; gap:9px; font-size:13px; color:#1d2327; padding:9px 12px;
                   border:1px solid #dcdcde; border-radius:4px; background:#fafafa; }
    .ps-evt-check input{ margin:0; }

    /* ── Colonne aperçu ────────────────────────────────── */
    .ps-evt-side{ position:sticky; top:36px; align-self:start; }
    .ps-evt-side__t{ font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
                     color:#646970; margin:0 0 10px; }

    .ps-evt-card{
        background:#100e0b; padding:26px 24px; position:relative; overflow:hidden;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; border-radius:6px;
    }
    .ps-evt-card::before{ content:''; position:absolute; left:0; top:0; bottom:0; width:2px;
                          background:linear-gradient(to bottom,#9e3710,#c28b36); }
    .ps-evt-card__img{ width:100%; aspect-ratio:16/9; object-fit:cover; margin-bottom:18px;
                       filter:brightness(.8) saturate(.85); display:block; border-radius:2px; }
    .ps-evt-card__ph{ width:100%; aspect-ratio:16/9; margin-bottom:18px; border-radius:2px;
                      background:repeating-linear-gradient(45deg,#1a1713,#1a1713 8px,#181510 8px,#181510 16px);
                      display:flex; align-items:center; justify-content:center;
                      color:#5f5747; font-size:11px; letter-spacing:.1em; text-transform:uppercase; }
    .ps-evt-card__date{ font-size:10.5px; letter-spacing:.22em; text-transform:uppercase; color:#c28b36; margin-bottom:12px; }
    .ps-evt-card__type{ display:inline-block; font-size:10px; letter-spacing:.12em; text-transform:uppercase;
                        color:rgba(194,139,54,.75); border:1px solid rgba(194,139,54,.28);
                        padding:2px 8px; margin-bottom:14px; }
    .ps-evt-card__t{ font-family:Georgia,'Cormorant Garamond',serif; font-size:20px; font-weight:400;
                     color:#ece3cb; line-height:1.25; margin:0 0 10px; }
    .ps-evt-card__lieu{ font-size:12px; color:#7f7463; margin:0 0 4px; }
    .ps-evt-card__prix{ font-size:12px; color:#7f7463; margin:0; }
    .ps-evt-card__complet{ display:inline-block; margin-top:12px; font-size:10px; letter-spacing:.1em;
                           text-transform:uppercase; color:#eb8e6f; border:1px solid rgba(158,55,16,.4); padding:2px 8px; }
    .ps-evt-card__btn{ display:inline-block; margin-top:14px; font-size:10.5px; letter-spacing:.14em;
                       text-transform:uppercase; color:#080705; background:#c28b36; padding:7px 14px; }

    /* Aperçu résultat Google */
    .ps-evt-goog{ border:1px solid #e3e3e3; border-radius:6px; padding:14px 16px; background:#fff; margin-top:16px;
                  font-family:arial,sans-serif; }
    .ps-evt-goog__url{ font-size:12px; color:#4d5156; margin-bottom:2px; }
    .ps-evt-goog__t{ font-size:17px; color:#1a0dab; line-height:1.3; margin:0 0 3px; }
    .ps-evt-goog__d{ font-size:12.5px; color:#4d5156; line-height:1.55; margin:0; }
    .ps-evt-goog__rich{ font-size:12.5px; color:#4d5156; margin-top:6px; padding-top:6px; border-top:1px solid #f0f0f0; }
    .ps-evt-goog__rich b{ color:#1a0dab; font-weight:400; }

    .ps-evt-warn{ margin-top:12px; font-size:12px; line-height:1.5; color:#8a6d3b;
                  background:#fcf8e3; border:1px solid #faebcc; border-radius:4px; padding:9px 11px; display:none; }
    .ps-evt-warn.on{ display:block; }
    </style>

    <div class="ps-evt" id="ps-evt">

      <div>
        <div class="ps-evt-sec">
          <h4 class="ps-evt-sec__t">🗓 <?= esc_html__('Quand', 'poivre-sens') ?></h4>
          <div class="ps-evt-grid ps-evt-grid--3">
            <div>
              <label class="ps-evt-lab" for="evt_date"><?= esc_html__('Date *', 'poivre-sens') ?></label>
              <input type="date" id="evt_date" name="evt_date" value="<?= esc_attr($date) ?>" required>
              <p class="ps-evt-hint"><?= esc_html__('Sans date, l\'événement n\'apparaît ni dans l\'agenda ni sur Google.', 'poivre-sens') ?></p>
            </div>
            <div>
              <label class="ps-evt-lab" for="evt_heure"><?= esc_html__('Début', 'poivre-sens') ?></label>
              <input type="time" id="evt_heure" name="evt_heure" value="<?= esc_attr($heure) ?>">
            </div>
            <div>
              <label class="ps-evt-lab" for="evt_heure_fin"><?= esc_html__('Fin', 'poivre-sens') ?></label>
              <input type="time" id="evt_heure_fin" name="evt_heure_fin" value="<?= esc_attr($heure_fin) ?>">
              <p class="ps-evt-hint"><?= esc_html__('Une fin plus tôt que le début = après minuit.', 'poivre-sens') ?></p>
            </div>
            <div class="ps-evt-full">
              <label class="ps-evt-lab" for="evt_type"><?= esc_html__('Type d\'événement', 'poivre-sens') ?></label>
              <select id="evt_type" name="evt_type">
                <?php foreach ($types as $k => $v): ?>
                <option value="<?= esc_attr($k) ?>" <?= selected($type, $k, false) ?>><?= esc_html($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="ps-evt-sec">
          <h4 class="ps-evt-sec__t">📍 <?= esc_html__('Où', 'poivre-sens') ?></h4>
          <div class="ps-evt-grid">
            <div class="ps-evt-full">
              <label class="ps-evt-lab" for="evt_lieu"><?= esc_html__('Lieu', 'poivre-sens') ?></label>
              <input type="text" id="evt_lieu" name="evt_lieu" value="<?= esc_attr($lieu) ?>" placeholder="<?= esc_attr__('Théâtre Athénor', 'poivre-sens') ?>">
            </div>
            <div>
              <label class="ps-evt-lab" for="evt_adresse"><?= esc_html__('Adresse', 'poivre-sens') ?></label>
              <input type="text" id="evt_adresse" name="evt_adresse" value="<?= esc_attr($adresse) ?>" placeholder="<?= esc_attr__('12 rue du Port', 'poivre-sens') ?>">
            </div>
            <div>
              <label class="ps-evt-lab" for="evt_ville"><?= esc_html__('Ville', 'poivre-sens') ?></label>
              <input type="text" id="evt_ville" name="evt_ville" value="<?= esc_attr($ville) ?>" placeholder="<?= esc_attr__('Saint-Nazaire', 'poivre-sens') ?>">
              <p class="ps-evt-hint"><?= esc_html__('Sert aussi de filtre dans l\'agenda.', 'poivre-sens') ?></p>
            </div>
          </div>
        </div>

        <div class="ps-evt-sec">
          <h4 class="ps-evt-sec__t">🎟 <?= esc_html__('Tarif et billetterie', 'poivre-sens') ?></h4>
          <div class="ps-evt-grid">
            <div>
              <label class="ps-evt-lab" for="evt_prix"><?= esc_html__('Tarif', 'poivre-sens') ?></label>
              <input type="text" id="evt_prix" name="evt_prix" value="<?= esc_attr($prix) ?>" placeholder="<?= esc_attr__('12€ · gratuit · prix libre', 'poivre-sens') ?>">
              <p class="ps-evt-hint"><?= esc_html__('« 12€ » et « gratuit » sont compris par Google. Un texte libre s\'affiche mais n\'annonce aucun prix.', 'poivre-sens') ?></p>
            </div>
            <div>
              <label class="ps-evt-lab" for="evt_billetterie"><?= esc_html__('Lien billetterie', 'poivre-sens') ?></label>
              <input type="url" id="evt_billetterie" name="evt_billetterie" value="<?= esc_attr($billetterie) ?>" placeholder="https://">
              <p class="ps-evt-hint"><?= esc_html__('Billetweb, HelloAsso… Affiche un bouton « Réserver ».', 'poivre-sens') ?></p>
            </div>
            <div class="ps-evt-full">
              <label class="ps-evt-check">
                <input type="checkbox" id="evt_complet" name="evt_complet" value="1" <?= checked($complet, '1', false) ?>>
                <?= esc_html__('Événement complet', 'poivre-sens') ?>
              </label>
            </div>
          </div>
        </div>
      </div>

      <aside class="ps-evt-side">
        <p class="ps-evt-side__t"><?= esc_html__('Aperçu en direct', 'poivre-sens') ?></p>

        <div class="ps-evt-card">
          <?php if ($vignette): ?>
          <img class="ps-evt-card__img" src="<?= esc_url($vignette) ?>" alt="">
          <?php else: ?>
          <div class="ps-evt-card__ph"><?= esc_html__('Image à la une', 'poivre-sens') ?></div>
          <?php endif; ?>
          <div class="ps-evt-card__date" id="ps-pv-date">—</div>
          <span class="ps-evt-card__type" id="ps-pv-type"></span>
          <h3 class="ps-evt-card__t" id="ps-pv-titre"><?= esc_html__('Titre de l\'événement', 'poivre-sens') ?></h3>
          <p class="ps-evt-card__lieu" id="ps-pv-lieu"></p>
          <p class="ps-evt-card__prix" id="ps-pv-prix"></p>
          <div id="ps-pv-etat"></div>
        </div>

        <div class="ps-evt-goog">
          <div class="ps-evt-goog__url">cie.poivresens.fr › evenements</div>
          <p class="ps-evt-goog__t" id="ps-pv-gt"><?= esc_html__('Titre de l\'événement', 'poivre-sens') ?></p>
          <p class="ps-evt-goog__d" id="ps-pv-gd"><?= esc_html__('Compagnie Poivre &amp; Sens', 'poivre-sens') ?></p>
          <div class="ps-evt-goog__rich" id="ps-pv-grich"></div>
        </div>

        <div class="ps-evt-warn" id="ps-pv-warn"></div>
      </aside>
    </div>

    <script>
    (function(){
      var $ = function(id){ return document.getElementById(id); };
      var champs = ['evt_date','evt_heure','evt_heure_fin','evt_lieu','evt_adresse','evt_ville','evt_type','evt_prix','evt_billetterie','evt_complet'];
      var LIBELLES = <?= wp_json_encode($types) ?>;

      function titre() {
        // Gutenberg d'abord, éditeur classique en repli.
        try {
          if (window.wp && wp.data && wp.data.select('core/editor')) {
            var t = wp.data.select('core/editor').getEditedPostAttribute('title');
            if (t) return t;
          }
        } catch (e) {}
        var cl = document.getElementById('title');
        return (cl && cl.value) || '';
      }

      function dateLongue(iso, h, hf) {
        if (!iso) return '';
        var d = new Date(iso + 'T' + (h || '00:00'));
        if (isNaN(d)) return '';
        var s = new Intl.DateTimeFormat('fr-FR', { weekday:'long', day:'numeric', month:'long', year:'numeric' }).format(d);
        if (h)  s += ' · ' + h.replace(':', 'h');
        if (hf) s += ' — ' + hf.replace(':', 'h');
        return s;
      }

      function maj() {
        var date = $('evt_date').value, h = $('evt_heure').value, hf = $('evt_heure_fin').value;
        var lieu = $('evt_lieu').value, ville = $('evt_ville').value;
        var prix = $('evt_prix').value, billet = $('evt_billetterie').value;
        var complet = $('evt_complet').checked;
        var t = titre() || <?= wp_json_encode(__('Titre de l\'événement', 'poivre-sens')) ?>;

        $('ps-pv-titre').textContent = t;
        $('ps-pv-gt').textContent    = t;

        var dl = dateLongue(date, h, hf);
        $('ps-pv-date').textContent = dl || <?= wp_json_encode(__('Date à définir', 'poivre-sens')) ?>;

        $('ps-pv-type').textContent = LIBELLES[$('evt_type').value] || '';

        var ou = [lieu, ville].filter(Boolean).join(' · ');
        $('ps-pv-lieu').textContent = ou ? '📍 ' + ou : '';
        $('ps-pv-prix').textContent = prix ? '🎟 ' + prix : '';

        var etat = '';
        if (complet)      etat = '<span class="ps-evt-card__complet"><?= esc_js(__('Complet', 'poivre-sens')) ?></span>';
        else if (billet)  etat = '<span class="ps-evt-card__btn"><?= esc_js(__('Réserver', 'poivre-sens')) ?></span>';
        $('ps-pv-etat').innerHTML = etat;

        // Extrait Google : ce que les données structurées permettent d'afficher
        var rich = [];
        if (dl) rich.push('<b>' + dl + '</b>');
        if (ou) rich.push(ou);
        if (prix) rich.push(prix);
        $('ps-pv-grich').innerHTML = rich.join(' · ');
        $('ps-pv-gd').textContent = [LIBELLES[$('evt_type').value], ou].filter(Boolean).join(' — ')
          || <?= wp_json_encode(__('Compagnie Poivre & Sens', 'poivre-sens')) ?>;

        // Avertissements utiles plutôt que silence
        var avert = [];
        if (!date) avert.push(<?= wp_json_encode(__('Sans date, l\'événement n\'apparaîtra pas dans l\'agenda ni dans les résultats Google.', 'poivre-sens')) ?>);
        if (date && !lieu && !ville) avert.push(<?= wp_json_encode(__('Ajoutez un lieu ou une ville : Google affiche l\'endroit dans ses résultats.', 'poivre-sens')) ?>);
        var w = $('ps-pv-warn');
        w.innerHTML = avert.join('<br>');
        w.classList.toggle('on', avert.length > 0);
      }

      champs.forEach(function(id){
        var el = $(id);
        if (el) { el.addEventListener('input', maj); el.addEventListener('change', maj); }
      });

      // Suivre le titre saisi dans Gutenberg
      try {
        if (window.wp && wp.data && wp.data.subscribe) {
          var dernier = null;
          wp.data.subscribe(function(){
            var t = titre();
            if (t !== dernier) { dernier = t; maj(); }
          });
        }
      } catch (e) {}
      var clas = document.getElementById('title');
      if (clas) clas.addEventListener('input', maj);

      maj();
    })();
    </script>
    <?php
}

/* ── Enregistrement ───────────────────────────────────────── */
add_action('save_post_evenement', function ($post_id) {
    if (!isset($_POST['ps_evt_nonce']) || !wp_verify_nonce($_POST['ps_evt_nonce'], 'ps_evt_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $champs = [
        '_evt_date'        => ['evt_date',        'sanitize_text_field'],
        '_evt_heure'       => ['evt_heure',       'sanitize_text_field'],
        '_evt_heure_fin'   => ['evt_heure_fin',   'sanitize_text_field'],
        '_evt_lieu'        => ['evt_lieu',        'sanitize_text_field'],
        '_evt_adresse'     => ['evt_adresse',     'sanitize_text_field'],
        '_evt_ville'       => ['evt_ville',       'sanitize_text_field'],
        '_evt_type'        => ['evt_type',        'sanitize_text_field'],
        '_evt_prix'        => ['evt_prix',        'sanitize_text_field'],
        '_evt_billetterie' => ['evt_billetterie', 'esc_url_raw'],
    ];
    foreach ($champs as $meta_key => [$champ, $nettoyage]) {
        if (isset($_POST[$champ])) {
            update_post_meta($post_id, $meta_key, $nettoyage(wp_unslash($_POST[$champ])));
        }
    }
    update_post_meta($post_id, '_evt_complet', isset($_POST['evt_complet']) ? '1' : '');
});
