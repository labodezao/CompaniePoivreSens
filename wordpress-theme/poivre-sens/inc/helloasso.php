<?php
/**
 * inc/helloasso.php
 *
 * Intègre les widgets de paiement HelloAsso (adhésions, dons…) via deux
 * shortcodes plutôt qu'en collant l'iframe brute dans chaque page :
 *   - l'association/campagne par défaut n'est écrite qu'à un seul endroit
 *     (à corriger ici si elle change, plutôt que sur chaque page) ;
 *   - le bloc « HTML personnalisé » de l'éditeur passe par wp_kses_post pour
 *     tout compte sans capacité unfiltered_html (rédacteurs, auteurs…), qui
 *     retire les balises <iframe> — le shortcode, lui, s'affiche pour tout
 *     le monde puisque le HTML est généré côté PHP, pas stocké dans le
 *     contenu de la page.
 *
 * Usage (bloc « Shortcode » de l'éditeur, ou dans un thème enfant) :
 *   [helloasso]                      → widget complet (formulaire, 750px)
 *   [helloasso bouton="1"]           → bouton compact (70px)
 *   [helloasso hauteur="600"]        → widget complet, hauteur de départ différente
 *   [helloasso type="collectes" campagne="don-libre"]
 *       → une autre campagne de la même association (don, billetterie…)
 */
defined('ABSPATH') || exit;

function ps_helloasso_shortcode($atts) {
    $atts = shortcode_atts([
        'assoc'    => 'compagnie-poivre-sens',
        'type'     => 'adhesions',
        'campagne' => 'adhesion-compagnie-poivre-et-sens',
        'bouton'   => '0',
        'hauteur'  => '750',
    ], $atts, 'helloasso');

    $assoc    = sanitize_title($atts['assoc']);
    $type     = sanitize_title($atts['type']);
    $campagne = sanitize_title($atts['campagne']);
    $bouton   = filter_var($atts['bouton'], FILTER_VALIDATE_BOOLEAN);
    $hauteur  = max(1, (int) $atts['hauteur']);

    if ($assoc === '' || $type === '' || $campagne === '') return '';

    $base = "https://www.helloasso.com/associations/{$assoc}/{$type}/{$campagne}";

    if ($bouton) {
        return '<iframe allowtransparency="true" src="' . esc_url($base . '/widget-bouton') . '" '
             . 'style="width:100%;height:70px;border:none" '
             . 'title="' . esc_attr__('Paiement HelloAsso', 'poivre-sens') . '"></iframe>';
    }

    $id = 'ha-widget-' . wp_unique_id();
    ob_start();
    ?>
    <iframe id="<?= esc_attr($id) ?>"
        allow="payment 'self' https://paymenthub.helloassopay.com https://helloasso.com"
        allowtransparency="true" scrolling="auto"
        src="<?= esc_url($base . '/widget') ?>"
        style="width:100%;height:<?= (int) $hauteur ?>px;border:none"
        title="<?= esc_attr__('Paiement HelloAsso', 'poivre-sens') ?>"></iframe>
    <script>
    (function () {
        var frame = document.getElementById(<?= wp_json_encode($id) ?>);
        if (!frame) return;
        // HelloAsso annonce sa vraie hauteur (formulaire dépliable) via postMessage
        // une fois chargé : sans ce redimensionnement, le widget serait tronqué.
        window.addEventListener('message', function (e) {
            if (e.source !== frame.contentWindow) return;
            if (!e.data || typeof e.data.height === 'undefined') return;
            var h = parseFloat(e.data.height);
            if (h > parseFloat(frame.style.height || 0)) frame.style.height = h + 'px';
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('helloasso', 'ps_helloasso_shortcode');
