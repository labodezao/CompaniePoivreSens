<?php
/**
 * inc/construction-popup.php
 *
 * Popup « Site en construction » — non invasif.
 *
 * Comportement :
 *   - S'affiche après un court délai (pas immédiatement) sur le front-end.
 *   - Ne réapparaît pas à chaque visite : un cookie mémorise la fermeture
 *     pendant plusieurs jours (ps_construction_seen).
 *   - Ne s'affiche pas aux personnes déjà inscrites à la newsletter
 *     (cookie ps_nl_subscribed, posé à l'inscription — y compris « déjà
 *     inscrit·e »).
 *   - Ne s'affiche pas aux administrateurs connectés (ils construisent le
 *     site) ni dans l'éditeur/aperçu.
 *   - Le formulaire réutilise l'AJAX newsletter existant et rattache les
 *     inscrits à la liste « construction » (visible dans Newsletter › Listes).
 */
defined('ABSPATH') || exit;

/* Durée d'affichage du cookie « déjà vu » (en jours). */
if (!defined('PS_CONSTRUCTION_COOKIE_DAYS')) {
    define('PS_CONSTRUCTION_COOKIE_DAYS', 14);
}

/**
 * S'assure que la liste « construction » existe (le handler d'inscription
 * ne rattache qu'à une liste déjà présente, pour raison de sécurité).
 */
function ps_construction_ensure_list() {
    if (get_option('ps_construction_list_ready') === '1') return;
    if (!function_exists('ps_nl_get_list_by_slug')) return;

    if (!ps_nl_get_list_by_slug('construction')) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ps_newsletter_lists', [
            'nom'           => __('Site en construction', 'poivre-sens'),
            'slug'          => 'construction',
            'description'   => __('Inscriptions via le popup « site en construction »', 'poivre-sens'),
            'couleur'       => '#9C3E1C',
            'date_creation' => current_time('mysql'),
        ]);
    }
    update_option('ps_construction_list_ready', '1');
}
add_action('init', 'ps_construction_ensure_list', 20);

/**
 * Faut-il afficher le popup pour cette requête ?
 * (La décision fine « déjà vu / déjà abonné » est prise côté JS via cookies,
 *  pour rester compatible avec le cache de pages.)
 */
function ps_construction_should_render() {
    if (is_admin() || is_customize_preview()) return false;
    if (is_user_logged_in() && current_user_can('edit_posts')) return false; // les bâtisseurs du site
    return true;
}

/**
 * Injecte le popup dans le pied de page du front-end.
 */
add_action('wp_footer', function () {
    if (!ps_construction_should_render()) return;

    $nonce      = wp_create_nonce('ps_newsletter');
    $ajax_url   = admin_url('admin-ajax.php');
    $cookie_max = PS_CONSTRUCTION_COOKIE_DAYS * DAY_IN_SECONDS;
    ?>
    <div class="ps-cp" id="ps-cp" hidden aria-hidden="true">
      <div class="ps-cp__backdrop" data-ps-cp-close></div>
      <div class="ps-cp__card" role="dialog" aria-modal="true" aria-labelledby="ps-cp-title">
        <button type="button" class="ps-cp__close" data-ps-cp-close aria-label="<?= esc_attr__('Fermer', 'poivre-sens') ?>">&times;</button>

        <div class="ps-cp__eyebrow"><?= esc_html__('Cie Poivre & Sens', 'poivre-sens') ?></div>
        <h2 class="ps-cp__title" id="ps-cp-title">
          <?= esc_html__('Notre site fait', 'poivre-sens') ?> <em><?= esc_html__('peau neuve', 'poivre-sens') ?></em>
        </h2>
        <p class="ps-cp__lede">
          <?= esc_html__('Le nouveau site arrive bientôt. En attendant, laissez-nous votre e-mail pour rester en lien : dates de stages, spectacles et nouvelles rares.', 'poivre-sens') ?>
        </p>

        <form class="ps-cp__form" id="ps-cp-form" novalidate>
          <input type="email" name="email" id="ps-cp-email" required autocomplete="email"
                 placeholder="<?= esc_attr__('prenom@exemple.fr', 'poivre-sens') ?>">
          <button type="submit" id="ps-cp-submit"><?= esc_html__('Rester en lien', 'poivre-sens') ?></button>
        </form>
        <p class="ps-cp__msg" id="ps-cp-msg" role="alert" aria-live="polite"></p>
        <p class="ps-cp__note"><?= esc_html__('Un e-mail rare, jamais de spam. Désinscription en un clic.', 'poivre-sens') ?></p>
      </div>
    </div>

    <style>
      .ps-cp{ position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px; }
      .ps-cp[hidden]{ display:none; }
      .ps-cp__backdrop{ position:absolute; inset:0; background:rgba(8,7,5,.62); backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px); opacity:0; transition:opacity .3s ease; }
      .ps-cp__card{
        position:relative; width:100%; max-width:440px; box-sizing:border-box;
        background:#E9E2D3; color:#2A2521; border-radius:14px;
        padding:40px 32px 32px; box-shadow:0 24px 60px rgba(8,7,5,.4);
        font-family:'Inter',system-ui,sans-serif;
        transform:translateY(14px) scale(.98); opacity:0; transition:transform .35s cubic-bezier(.25,.46,.45,.94), opacity .35s ease;
      }
      .ps-cp.is-visible .ps-cp__backdrop{ opacity:1; }
      .ps-cp.is-visible .ps-cp__card{ transform:none; opacity:1; }
      .ps-cp__close{
        position:absolute; top:12px; right:14px; width:34px; height:34px;
        border:none; background:none; color:#57624A; font-size:26px; line-height:1;
        cursor:pointer; border-radius:50%; transition:background .15s,color .15s;
      }
      .ps-cp__close:hover{ background:rgba(42,37,33,.08); color:#2A2521; }
      .ps-cp__eyebrow{ font-size:11px; letter-spacing:.22em; text-transform:uppercase; color:#57624A; font-weight:600; margin-bottom:14px; }
      .ps-cp__title{ font-family:'Cormorant Garamond',Georgia,serif; font-weight:600; font-size:34px; line-height:1.05; margin:0 0 12px; color:#2A2521; }
      .ps-cp__title em{ font-style:italic; font-weight:500; color:#9C3E1C; }
      .ps-cp__lede{ font-size:15px; line-height:1.55; color:#4a453f; margin:0 0 22px; }
      .ps-cp__form{ display:flex; flex-direction:column; gap:10px; margin:0; }
      .ps-cp__form input[type=email]{
        font-family:inherit; font-size:16px; padding:14px 16px; width:100%; box-sizing:border-box;
        border:1.5px solid rgba(42,37,33,.22); border-radius:10px; background:#F2EDE2; color:#2A2521;
      }
      .ps-cp__form input[type=email]:focus{ outline:none; border-color:#9C3E1C; box-shadow:0 0 0 3px rgba(156,62,28,.14); }
      .ps-cp__form button{
        font-family:inherit; font-size:16px; font-weight:600; padding:14px 18px; border:none; border-radius:10px;
        cursor:pointer; background:#9C3E1C; color:#fff; transition:background .15s,opacity .15s;
      }
      .ps-cp__form button:hover{ background:#b04a24; }
      .ps-cp__form button:disabled{ opacity:.65; cursor:default; }
      .ps-cp__msg{ font-size:14px; line-height:1.5; margin:12px 0 0; padding:0; display:none; }
      .ps-cp__msg.is-error{ display:block; color:#9C3E1C; }
      .ps-cp__msg.is-ok{ display:block; color:#57624A; }
      .ps-cp__note{ font-size:12px; line-height:1.45; color:#57624A; margin:14px 0 0; }
      @media(max-width:380px){ .ps-cp__title{ font-size:29px; } .ps-cp__card{ padding:36px 22px 26px; } }
    </style>

    <script>
    (function(){
      var COOKIE_SEEN = 'ps_construction_seen';
      var COOKIE_SUB  = 'ps_nl_subscribed';
      var SEEN_MAX    = <?= (int) $cookie_max ?>;
      var SUB_MAX     = 60*60*24*365;

      function hasCookie(name){ return document.cookie.split('; ').indexOf(name + '=1') !== -1; }
      function setCookie(name, maxAge){ document.cookie = name + '=1;path=/;max-age=' + maxAge + ';SameSite=Lax'; }

      // Ne rien faire si déjà vu récemment ou déjà abonné.
      if (hasCookie(COOKIE_SEEN) || hasCookie(COOKIE_SUB)) return;

      var pop    = document.getElementById('ps-cp');
      if (!pop) return;
      var form   = document.getElementById('ps-cp-form');
      var email  = document.getElementById('ps-cp-email');
      var btn    = document.getElementById('ps-cp-submit');
      var msg    = document.getElementById('ps-cp-msg');
      var label  = btn.textContent;
      var opened = false;

      function open(){
        if (opened) return; opened = true;
        pop.hidden = false;
        pop.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function(){ pop.classList.add('is-visible'); });
        document.addEventListener('keydown', onKey);
      }
      function dismiss(){
        pop.classList.remove('is-visible');
        setCookie(COOKIE_SEEN, SEEN_MAX);
        document.removeEventListener('keydown', onKey);
        setTimeout(function(){ pop.hidden = true; pop.setAttribute('aria-hidden','true'); }, 300);
      }
      function onKey(e){ if (e.key === 'Escape') dismiss(); }

      Array.prototype.forEach.call(pop.querySelectorAll('[data-ps-cp-close]'), function(el){
        el.addEventListener('click', dismiss);
      });

      form.addEventListener('submit', function(e){
        e.preventDefault();
        msg.className = 'ps-cp__msg';
        var val = email.value.trim();
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(val)) {
          msg.textContent = <?= wp_json_encode(__('Vérifiez votre adresse e-mail.', 'poivre-sens')) ?>;
          msg.className = 'ps-cp__msg is-error';
          return;
        }
        var data = new FormData();
        data.append('action', 'ps_newsletter_subscribe');
        data.append('nonce', <?= wp_json_encode($nonce) ?>);
        data.append('email', val);
        data.append('liste', 'construction');

        btn.disabled = true;
        btn.textContent = <?= wp_json_encode(__('Envoi…', 'poivre-sens')) ?>;

        fetch(<?= wp_json_encode($ajax_url) ?>, { method:'POST', body:data })
          .then(function(r){ return r.json(); })
          .then(function(res){
            if (res.success || (res.data && res.data.already)) {
              setCookie(COOKIE_SUB, SUB_MAX);
              setCookie(COOKIE_SEEN, SEEN_MAX);
              msg.className = 'ps-cp__msg is-ok';
              msg.textContent = (res.data && res.data.message) || <?= wp_json_encode(__('Merci ! À très bientôt.', 'poivre-sens')) ?>;
              form.style.display = 'none';
              setTimeout(dismiss, 2200);
            } else {
              msg.className = 'ps-cp__msg is-error';
              msg.textContent = (res.data && res.data.message) || <?= wp_json_encode(__('Un souci est survenu. Réessayez.', 'poivre-sens')) ?>;
              btn.disabled = false; btn.textContent = label;
            }
          })
          .catch(function(){
            msg.className = 'ps-cp__msg is-error';
            msg.textContent = <?= wp_json_encode(__('Connexion impossible. Réessayez.', 'poivre-sens')) ?>;
            btn.disabled = false; btn.textContent = label;
          });
      });

      // Non invasif : on laisse la page s'afficher, puis on ouvre après un délai.
      setTimeout(open, 3500);
    })();
    </script>
    <?php
}, 100);
