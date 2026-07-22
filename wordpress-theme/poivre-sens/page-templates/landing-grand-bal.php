<?php
/**
 * Template Name: Landing — Grand Bal de l'Europe
 *
 * Page d'atterrissage autonome pour les prospects rencontrés au Grand Bal
 * de l'Europe. Inscription rattachée à la liste « grand-bal-europe »
 * (créée automatiquement — voir ps_nl_seed_default_lists()), ce qui permet
 * de localiser l'origine de ces abonnés dans Newsletter → Abonnés / Listes.
 */
defined('ABSPATH') || exit;

$liste_slug = 'grand-bal-europe';
$ajax_url   = admin_url('admin-ajax.php');
$nonce      = wp_create_nonce('ps_newsletter');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html__('Poivre & Sens — Restons en lien', 'poivre-sens'); ?></title>
<?php wp_head(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,450;0,9..144,600;1,9..144,450&family=Archivo:wght@400;500;600&display=swap');

:root{
  --ink:#2A2521;
  --paper:#E9E2D3;
  --paper-soft:#F2EDE2;
  --spice:#9C3E1C;
  --moss:#57624A;
  --line: rgba(42,37,33,0.28);
}
.ps-landing *{ box-sizing:border-box; }
.ps-landing{
  background:var(--paper); color:var(--ink);
  font-family:'Archivo', sans-serif;
  display:flex; align-items:center; justify-content:center;
  padding:28px 20px; min-height:100vh; position:relative; overflow-x:hidden; margin:0;
}

.ps-landing .meridian{ position:fixed; inset:0; width:100%; height:100%; pointer-events:none; opacity:0.5; z-index:0; }

.ps-landing .card{ position:relative; z-index:1; width:100%; max-width:440px; margin:0 auto; }

.ps-landing .eyebrow{ font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:var(--moss); font-weight:600; margin-bottom:18px; }

.ps-landing .headline{ font-family:'Fraunces', serif; font-weight:600; font-size:40px; line-height:1.02; letter-spacing:-0.01em; margin:0 0 6px; }
.ps-landing .headline em{ font-style:italic; font-weight:450; color:var(--spice); }

.ps-landing .lede{ font-family:'Fraunces', serif; font-style:italic; font-weight:450; font-size:17px; line-height:1.4; margin:14px 0 22px; max-width:38ch; }

.ps-landing .gifts{ list-style:none; margin:0 0 26px; padding:0; display:flex; flex-direction:column; gap:11px; }
.ps-landing .gifts li{ font-size:14.5px; line-height:1.4; padding-left:22px; position:relative; }
.ps-landing .gifts li::before{ content:""; position:absolute; left:0; top:8px; width:8px; height:8px; border-radius:50%; background:var(--spice); }
.ps-landing .gifts strong{ font-weight:600; }

.ps-landing form{ display:flex; flex-direction:column; gap:12px; margin:0; }
.ps-landing label{ font-size:12.5px; font-weight:600; letter-spacing:0.02em; color:var(--ink); }
.ps-landing input[type=email]{
  font-family:'Archivo',sans-serif; font-size:16px;
  padding:15px 16px; border:1.5px solid var(--line); border-radius:10px;
  background:var(--paper-soft); color:var(--ink); width:100%;
}
.ps-landing input[type=email]:focus{ outline:none; border-color:var(--spice); box-shadow:0 0 0 3px rgba(156,62,28,0.14); }

.ps-landing button{
  font-family:'Archivo',sans-serif; font-size:16px; font-weight:600;
  padding:15px 18px; border:none; border-radius:10px; cursor:pointer;
  background:var(--spice); color:#fff; width:100%; transition:background .15s, opacity .15s;
}
.ps-landing button:hover{ background:#b04a24; }
.ps-landing button:disabled{ opacity:0.65; cursor:default; }

.ps-landing .reassure{ font-size:12px; line-height:1.45; color:var(--moss); margin-top:12px; }

.ps-landing .msg{ font-size:14px; line-height:1.5; margin-top:14px; padding:12px 14px; border-radius:10px; display:none; }
.ps-landing .msg.error{ display:block; background:rgba(156,62,28,0.10); color:var(--spice); }
.ps-landing .msg.ok{ display:block; background:rgba(87,98,74,0.12); color:var(--moss); }

.ps-landing .done{ display:none; }
.ps-landing .done .done-title{ font-family:'Fraunces',serif; font-weight:600; font-size:30px; line-height:1.1; margin-bottom:12px; }
.ps-landing .done .done-title em{ font-style:italic; color:var(--spice); }
.ps-landing .done p{ font-size:15px; line-height:1.5; max-width:36ch; margin:0; }

.ps-landing .footer{ margin-top:34px; padding-top:16px; border-top:1px solid var(--line); font-size:12px; line-height:1.6; color:var(--moss); }
.ps-landing .footer .url{ font-family:'Fraunces',serif; font-style:italic; color:var(--spice); }

@media (max-width:380px){ .ps-landing .headline{ font-size:34px; } }
</style>
</head>
<body <?php body_class('ps-landing'); ?>>

<svg class="meridian" viewBox="0 0 400 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
  <path d="M -20 120 C 120 170, 90 300, 250 280 S 460 420, 340 560 S 120 700, 300 760"
        fill="none" stroke="#57624A" stroke-width="1" opacity="0.5"/>
  <circle cx="250" cy="280" r="3.5" fill="#9C3E1C" opacity="0.7"/>
  <circle cx="340" cy="560" r="3.5" fill="#9C3E1C" opacity="0.55"/>
</svg>

<div class="card">

  <!-- ÉTAT 1 : inscription -->
  <div id="ps-gb-signup">
    <div class="eyebrow"><?php esc_html_e('Cie Poivre & Sens', 'poivre-sens'); ?></div>
    <h1 class="headline"><?php esc_html_e('Restons', 'poivre-sens'); ?> <em><?php esc_html_e('en lien', 'poivre-sens'); ?></em></h1>
    <p class="lede"><?php esc_html_e("Vous nous avez rencontrés au Grand Bal de l'Europe. Ce plaisir d'improviser ensemble, pourra se prolonger au-delà du festival.", 'poivre-sens'); ?></p>

    <ul class="gifts">
      <li><?php echo wp_kses(__('Les <strong>dates de nos stages immersifs</strong>, en avant-première.', 'poivre-sens'), ['strong' => []]); ?></li>
      <li><?php echo wp_kses(__('Une <strong>courte pratique à emporter</strong>, offerte à votre inscription.', 'poivre-sens'), ['strong' => []]); ?></li>
      <li><?php echo wp_kses(__('Des nouvelles <strong>rares et choisies</strong>, autour du corps, du mouvement et du son.', 'poivre-sens'), ['strong' => []]); ?></li>
    </ul>

    <form id="ps-gb-form" novalidate>
      <label for="ps-gb-email"><?php esc_html_e('Votre adresse e-mail', 'poivre-sens'); ?></label>
      <input type="email" id="ps-gb-email" name="email" placeholder="<?php esc_attr_e('prenom@exemple.fr', 'poivre-sens'); ?>" autocomplete="email" required>
      <button type="submit" id="ps-gb-submit"><?php esc_html_e('Recevez votre pratique', 'poivre-sens'); ?></button>
    </form>

    <p class="reassure"><?php esc_html_e('Un e-mail rare, jamais de spam. Désinscription en un clic, quand vous voulez.', 'poivre-sens'); ?></p>
    <div class="msg" id="ps-gb-msg" role="alert" aria-live="polite"></div>
  </div>

  <!-- ÉTAT 2 : merci -->
  <div class="done" id="ps-gb-done">
    <div class="done-title"><?php esc_html_e("C'est fait —", 'poivre-sens'); ?> <em><?php esc_html_e('bienvenue', 'poivre-sens'); ?></em>.</div>
    <p><?php esc_html_e('Merci de votre confiance. Regardez votre boîte mail : votre pratique arrive, et vous serez les premier·es informé·es de nos prochains stages.', 'poivre-sens'); ?></p>
  </div>

  <div class="footer">
    Ambre Lavignac &amp; Ewen d'Aviau · Saint-Nazaire (44)<br>
    <span class="url">cie.poivresens.fr</span>
  </div>

</div>

<script>
(function () {
  var form  = document.getElementById('ps-gb-form');
  var btn   = document.getElementById('ps-gb-submit');
  var msg   = document.getElementById('ps-gb-msg');
  var label = btn.textContent;

  function showMsg(text, isError) {
    msg.textContent = text;
    msg.className = 'msg ' + (isError ? 'error' : 'ok');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    msg.className = 'msg';

    var email = document.getElementById('ps-gb-email').value.trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      showMsg(<?php echo wp_json_encode(__('Vérifiez votre adresse e-mail, il semble y avoir une petite erreur.', 'poivre-sens')); ?>, true);
      return;
    }

    var data = new FormData();
    data.append('action', 'ps_newsletter_subscribe');
    data.append('nonce', <?php echo wp_json_encode($nonce); ?>);
    data.append('email', email);
    data.append('liste', <?php echo wp_json_encode($liste_slug); ?>);

    btn.disabled = true;
    btn.textContent = <?php echo wp_json_encode(__('Envoi…', 'poivre-sens')); ?>;

    fetch(<?php echo wp_json_encode($ajax_url); ?>, { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          document.getElementById('ps-gb-signup').style.display = 'none';
          document.getElementById('ps-gb-done').style.display = 'block';
        } else {
          showMsg((res.data && res.data.message) || <?php echo wp_json_encode(__('Un souci technique est survenu. Réessayez dans un instant.', 'poivre-sens')); ?>, true);
          btn.disabled = false; btn.textContent = label;
        }
      })
      .catch(function () {
        showMsg(<?php echo wp_json_encode(__('Connexion impossible. Vérifiez votre réseau et réessayez.', 'poivre-sens')); ?>, true);
        btn.disabled = false; btn.textContent = label;
      });
  });
}());
</script>

<?php wp_footer(); ?>
</body>
</html>
