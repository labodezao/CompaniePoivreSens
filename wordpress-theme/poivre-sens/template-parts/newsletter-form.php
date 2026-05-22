<?php
/**
 * template-parts/newsletter-form.php
 * Formulaire d'inscription newsletter (front-end)
 */
defined('ABSPATH') || exit;
?>
<div class="nl-form-wrap" id="newsletter">
    <div class="nl-form-inner">
        <div class="nl-form-txt">
            <p class="lbl"><?= __('Newsletter', 'poivre-sens') ?></p>
            <h2 class="nl-form-t"><?= __('Restez dans la danse', 'poivre-sens') ?></h2>
            <p class="nl-form-desc">
                <?= __('Recevez les prochaines dates de spectacles, jams, ateliers et résidences directement dans votre boîte mail.', 'poivre-sens') ?>
            </p>
            <ul class="nl-form-liste">
                <li><?= __('Prochains événements en avant-première', 'poivre-sens') ?></li>
                <li><?= __('Dates de stages et ateliers', 'poivre-sens') ?></li>
                <li><?= __('Actualités de la compagnie', 'poivre-sens') ?></li>
            </ul>
        </div>

        <form class="nl-form" id="nl-form-subscribe" novalidate>
            <?php wp_nonce_field('ps_newsletter', 'nl_nonce', false); ?>
            <div class="nl-form-fields">
                <div class="nl-form-field">
                    <label for="nl-prenom" class="nl-form-label"><?= __('Prénom', 'poivre-sens') ?></label>
                    <input type="text" id="nl-prenom" name="prenom" autocomplete="given-name"
                           placeholder="<?= esc_attr(__('Ambre', 'poivre-sens')) ?>">
                </div>
                <div class="nl-form-field nl-form-field--email">
                    <label for="nl-email" class="nl-form-label"><?= __('E-mail *', 'poivre-sens') ?></label>
                    <input type="email" id="nl-email" name="email" required autocomplete="email"
                           placeholder="<?= esc_attr(__('votre@email.fr', 'poivre-sens')) ?>">
                </div>
            </div>
            <div class="nl-form-submit-row">
                <button type="submit" class="nl-form-btn" id="nl-submit">
                    <span class="nl-form-btn-txt"><?= __('S\'inscrire à la newsletter', 'poivre-sens') ?></span>
                    <span class="nl-form-btn-arrow">→</span>
                </button>
                <p class="nl-form-privacy">
                    <?= __('Pas de spam. Désinscription en un clic.', 'poivre-sens') ?>
                </p>
            </div>
            <div class="nl-form-msg" id="nl-msg" role="alert" aria-live="polite"></div>
        </form>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('nl-form-subscribe');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = document.getElementById('nl-submit');
        var msg  = document.getElementById('nl-msg');
        var data = new FormData(form);
        data.append('action', 'ps_newsletter_subscribe');
        data.append('nonce',  form.querySelector('[name="nl_nonce"]').value);
        btn.disabled = true;
        btn.querySelector('.nl-form-btn-txt').textContent = '<?= esc_js(__('Envoi…', 'poivre-sens')) ?>';
        fetch(PS.ajax_url, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                msg.className = 'nl-form-msg nl-form-msg--' + (res.success ? 'ok' : 'err');
                msg.textContent = res.data.message;
                if (res.success) {
                    form.reset();
                    btn.style.display = 'none';
                }
            })
            .catch(function () {
                msg.className = 'nl-form-msg nl-form-msg--err';
                msg.textContent = '<?= esc_js(__('Une erreur est survenue. Réessayez.', 'poivre-sens')) ?>';
            })
            .finally(function () {
                btn.disabled = false;
                btn.querySelector('.nl-form-btn-txt').textContent = '<?= esc_js(__("S'inscrire à la newsletter", 'poivre-sens')) ?>';
            });
    });
}());
</script>
