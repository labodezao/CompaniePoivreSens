<?php
/**
 * Poivre & Sens — Page d'options admin
 * Accessible via : Réglages › Contenu du site
 *
 * Toutes les zones de texte de la page d'accueil sur une seule page.
 * Cliquez "Enregistrer les réglages" — c'est tout.
 */
defined('ABSPATH') || exit;

/* ── Enregistrement de l'option ─────────────────────────────── */
add_action('admin_init', function () {
    register_setting('ps_options_group', 'ps_options', [
        'sanitize_callback' => 'ps_sanitize_options',
    ]);
});

function ps_sanitize_options(array $input): array {
    $clean = [];
    $textarea_keys = [
        'hero_disciplines', 'hero_quote', 'hero_intro',
        'manifeste_p1', 'manifeste_p2', 'manifeste_p3',
        'ambre_bio1', 'ambre_bio2',
        'ewen_bio1',  'ewen_bio2',
        'contact_note_reseaux',
    ];
    foreach ($input as $key => $val) {
        if (in_array($key, $textarea_keys, true)) {
            $clean[$key] = wp_kses_post(wp_unslash($val));
        } else {
            $clean[$key] = sanitize_text_field(wp_unslash($val));
        }
    }
    return $clean;
}

/* ── Menu dans Réglages ─────────────────────────────────────── */
add_action('admin_menu', function () {
    add_options_page(
        'Contenu du site — Poivre & Sens',
        '🌶 Contenu du site',
        'manage_options',
        'ps-options',
        'ps_render_options_page'
    );
});

/* ── Styles inline pour la page ─────────────────────────────── */
add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'settings_page_ps-options') return;
    ?>
<style>
.ps-wrap { max-width: 860px; }
.ps-wrap h1 { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.ps-wrap .ps-sub { color:#646970; margin-bottom:32px; font-size:14px; }
.ps-section { background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin-bottom:24px; }
.ps-section h2 { margin:0; padding:14px 20px; font-size:14px; font-weight:600;
    background:#f6f7f7; border-bottom:1px solid #c3c4c7; border-radius:4px 4px 0 0;
    display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
.ps-section h2 .ps-toggle { margin-left:auto; color:#646970; font-weight:400; font-size:12px; }
.ps-section-body { padding:20px; display:grid; gap:18px; }
.ps-section-body.ps-hidden { display:none; }
.ps-row { display:grid; grid-template-columns: 220px 1fr; gap:12px; align-items:start; }
.ps-row label { font-size:13px; font-weight:600; padding-top:6px; color:#1d2327; }
.ps-row .ps-hint { font-size:11px; color:#646970; margin-top:3px; }
.ps-row input[type=text], .ps-row textarea {
    width:100%; border:1px solid #8c8f94; border-radius:3px;
    padding:6px 10px; font-size:13px; box-sizing:border-box;
    transition:border-color .15s; font-family:inherit; }
.ps-row input[type=text]:focus, .ps-row textarea:focus {
    border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; outline:none; }
.ps-row textarea { min-height:80px; resize:vertical; line-height:1.6; }
.ps-sticky { position:sticky; top:32px; }
.ps-save { background:#2271b1; color:#fff; border:none; border-radius:3px;
    padding:10px 24px; font-size:14px; font-weight:600; cursor:pointer; width:100%;
    margin-bottom:12px; transition:background .15s; }
.ps-save:hover { background:#135e96; }
.ps-sidebar { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px; }
.ps-sidebar h3 { margin:0 0 10px; font-size:13px; font-weight:600; }
.ps-sidebar ul { margin:0; padding:0 0 0 16px; font-size:12px; color:#646970; line-height:1.9; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ps-section h2').forEach(function (h2) {
        h2.addEventListener('click', function () {
            var body = h2.closest('.ps-section').querySelector('.ps-section-body');
            var tog  = h2.querySelector('.ps-toggle');
            var hidden = body.classList.toggle('ps-hidden');
            tog.textContent = hidden ? '▶ Afficher' : '▼ Masquer';
        });
    });
});
</script>
    <?php
});

/* ── Rendu de la page ───────────────────────────────────────── */
function ps_render_options_page(): void {
    if (!current_user_can('manage_options')) return;
    $o = get_option('ps_options', []);
    // Helper : valeur avec fallback
    $v = function (string $key, string $default = '') use ($o): string {
        return isset($o[$key]) ? $o[$key] : $default;
    };
    ?>
<div class="wrap ps-wrap">
<h1>🌶 Contenu du site</h1>
<p class="ps-sub">Modifiez les textes de la page d'accueil. Cliquez <strong>Enregistrer les réglages</strong> en bas ou dans la barre latérale pour sauvegarder.</p>

<?php if (isset($_GET['settings-updated'])): ?>
<div class="notice notice-success is-dismissible"><p>✅ <strong>Réglages enregistrés avec succès !</strong></p></div>
<?php endif; ?>

<form method="post" action="options.php">
<?php settings_fields('ps_options_group'); ?>

<div style="display:grid; grid-template-columns:1fr 220px; gap:24px; align-items:start;">
<div>

<!-- ①  HERO ─────────────────────────────────────────── -->
<?php ps_section('hero', '① Hero — En-tête de la page'); ?>
<div class="ps-section-body">
<?php ps_field('text',     'hero_surtitle',   'Sur-titre',                    $v('hero_surtitle',   'Compagnie artistique · Association loi 1901')); ?>
<?php ps_field('textarea', 'hero_disciplines','Disciplines (une par ligne)',   $v('hero_disciplines',"Danse contemporaine\nContact-improvisation\nMusique improvisée\nPratiques somatiques"), 'Chaque ligne = une discipline affichée'); ?>
<?php ps_field('text',     'hero_cta_label',  'Texte du bouton',              $v('hero_cta_label',  'Découvrir la compagnie')); ?>
<?php ps_field('textarea', 'hero_quote',      'Citation (côté droit)',         $v('hero_quote',      "Le corps sait ce que l'esprit cherche encore.")); ?>
<?php ps_field('textarea', 'hero_intro',      'Texte d\'intro (côté droit)',   $v('hero_intro',      "Née de la rencontre d'un corps et d'un son, d'une main qui écoute et d'une oreille qui se déplace, la compagnie explore les espaces de porosité entre le mouvement et la musique.")); ?>
</div></div>

<!-- ②  MANIFESTE ────────────────────────────────────── -->
<?php ps_section('mf', '② Manifeste'); ?>
<div class="ps-section-body">
<?php ps_field('text',     'manifeste_titre', 'Titre',                        $v('manifeste_titre', "Une rencontre entre le corps et le son")); ?>
<?php ps_field('text',     'manifeste_titre_em1','Mot(s) en italique doré 1', $v('manifeste_titre_em1','le corps'), 'Ces mots seront mis en valeur dans le titre'); ?>
<?php ps_field('text',     'manifeste_titre_em2','Mot(s) en italique doré 2', $v('manifeste_titre_em2','le son')); ?>
<?php ps_field('textarea', 'manifeste_p1',    'Paragraphe 1',                  $v('manifeste_p1',    "La compagnie explore les espaces de porosité entre le mouvement et le son, entre la structure et le lâcher-prise, entre la transmission d'un savoir et l'ouverture à l'inconnu. Ses créations ne cherchent pas à illustrer ni à démontrer, mais à <em>habiter</em>.")); ?>
<?php ps_field('textarea', 'manifeste_p2',    'Paragraphe 2',                  $v('manifeste_p2',    "Ce qui unit leurs univers, c'est la qualité de présence : être là, pleinement, dans l'instant d'une rencontre — entre deux corps, entre un corps et un instrument, entre une sensation et une image, entre ce qui est attendu et ce qui surgit.")); ?>
<?php ps_field('textarea', 'manifeste_p3',    'Paragraphe 3',                  $v('manifeste_p3',    "Inspirée du Tao, des méridiens, de l'aïkido et de la lutherie, la compagnie croit en l'<em>artisanat du spectacle</em> : chaque geste compte, chaque son est matière, chaque silence est espace.")); ?>
</div></div>

<!-- ③  ARTISTE AMBRE ────────────────────────────────── -->
<?php ps_section('ambre', '③ Artiste — Ambre Lavignac'); ?>
<div class="ps-section-body">
<?php ps_field('text',     'ambre_nom',     'Nom',                         $v('ambre_nom',     'Ambre Lavignac')); ?>
<?php ps_field('text',     'ambre_role',    'Rôle / Titre',                $v('ambre_role',    'Danseuse · Pédagogue · Praticienne du mouvement')); ?>
<?php ps_field('text',     'ambre_initiale','Initiale (bulle avatar)',     $v('ambre_initiale','A'), 'Lettre affichée dans le cercle'); ?>
<?php ps_field('textarea', 'ambre_bio1',    'Biographie — paragraphe 1',  $v('ambre_bio1',    "Formée à la danse contemporaine, Ambre Lavignac oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens. Inspirée par la philosophie taoïste et la médecine traditionnelle chinoise, elle explore les correspondances entre les éléments naturels, les méridiens énergétiques et les qualités de mouvement.")); ?>
<?php ps_field('textarea', 'ambre_bio2',    'Biographie — paragraphe 2',  $v('ambre_bio2',    "Praticienne du massage, elle travaille les liens entre le toucher, la conscience corporelle et la circulation de l'énergie. En tant que chorégraphe, elle s'intéresse à l'improvisation comme espace de création vivante.")); ?>
<?php ps_field('text',     'ambre_tags',    'Mots-clés (séparés par des virgules)', $v('ambre_tags', 'Danse contemporaine,Improvisation,Somatique,Tao,Méridiens,Massage,Pédagogie')); ?>
</div></div>

<!-- ④  ARTISTE EWEN ─────────────────────────────────── -->
<?php ps_section('ewen', "④ Artiste — Ewen d'Aviau"); ?>
<div class="ps-section-body">
<?php ps_field('text',     'ewen_nom',      'Nom',                        $v('ewen_nom',      "Ewen d'Aviau")); ?>
<?php ps_field('text',     'ewen_role',     'Rôle / Titre',               $v('ewen_role',     "Luthier-ingénieur · Musicien · Danseur")); ?>
<?php ps_field('text',     'ewen_initiale', 'Initiale (bulle avatar)',    $v('ewen_initiale', 'E')); ?>
<?php ps_field('textarea', 'ewen_bio1',     'Biographie — paragraphe 1', $v('ewen_bio1',     "Ingénieur de formation, Ewen d'Aviau se tourne vers la lutherie pour explorer la fabrication des instruments à cordes comme geste à la fois artisanal, scientifique et artistique. Il conçoit le son comme une matière vivante, façonnable, imprévue.")); ?>
<?php ps_field('textarea', 'ewen_bio2',     'Biographie — paragraphe 2', $v('ewen_bio2',     "Musicien, il pratique l'improvisation libre avec une oreille particulière pour l'espace, le silence et la relation. Danseur, imprégné du contact-improvisation et de l'aïkido, il retient l'art de la redirection et de la présence active non agressive.")); ?>
<?php ps_field('text',     'ewen_tags',     'Mots-clés (séparés par des virgules)', $v('ewen_tags', "Lutherie,Musique improvisée,Contact-improvisation,Somatique,Aïkido,Enseignement")); ?>
</div></div>

<!-- ⑤  CITATION ESTHÉTIQUE ──────────────────────────── -->
<?php ps_section('cite', '⑤ Citation esthétique'); ?>
<div class="ps-section-body">
<?php ps_field('text', 'esthet_cite_ligne1', 'Ligne 1',         $v('esthet_cite_ligne1', "Habiter un espace de jeu partagé —")); ?>
<?php ps_field('text', 'esthet_cite_ligne2', 'Ligne 2',         $v('esthet_cite_ligne2', 'entre deux corps,')); ?>
<?php ps_field('text', 'esthet_cite_em',     'Ligne 3 (dorée)', $v('esthet_cite_em',     'un corps et un instrument')); ?>
<?php ps_field('text', 'esthet_cite_source', 'Source',          $v('esthet_cite_source', "Poivre & Sens · Note d'intention")); ?>
</div></div>

<!-- ⑥  CONTACT ──────────────────────────────────────── -->
<?php ps_section('contact', '⑥ Contact'); ?>
<div class="ps-section-body">
<?php ps_field('text',     'contact_nom',         'Nom de la compagnie',      $v('contact_nom',         'Poivre & Sens')); ?>
<?php ps_field('text',     'contact_statut',      'Statut juridique',         $v('contact_statut',      'Association loi 1901')); ?>
<?php ps_field('text',     'contact_direction',   'Direction artistique',     $v('contact_direction',   "Ambre Lavignac & Ewen d'Aviau")); ?>
<?php ps_field('text',     'contact_disciplines', 'Disciplines',              $v('contact_disciplines', 'Danse · Contact-improvisation · Musique · Somatique')); ?>
<?php ps_field('text',     'contact_email',       'E-mail général',           $v('contact_email',       'contact@cie.poivresens.fr')); ?>
<?php ps_field('text',     'contact_site',        'Site web (affiché)',       $v('contact_site',        'cie.poivresens.fr')); ?>
<?php ps_field('text',     'contact_email_ambre', 'E-mail Ambre',            $v('contact_email_ambre', 'ambre@cie.poivresens.fr')); ?>
<?php ps_field('text',     'contact_email_ewen',  'E-mail Ewen',             $v('contact_email_ewen',  'ewen@cie.poivresens.fr')); ?>
<?php ps_field('textarea', 'contact_note_reseaux','Note "Suivre la compagnie"', $v('contact_note_reseaux', 'Retrouvez Poivre & Sens dans les réseaux du spectacle vivant, les festivals de contact-improvisation et les scènes de musique improvisée en France et en Europe.')); ?>
</div></div>

<!-- ⑦  FOOTER ───────────────────────────────────────── -->
<?php ps_section('footer', '⑦ Pied de page'); ?>
<div class="ps-section-body">
<?php ps_field('text', 'footer_line1', 'Ligne 1', $v('footer_line1', "Compagnie de danse et musique improvisées · Association loi 1901")); ?>
<?php ps_field('text', 'footer_line2', 'Ligne 2', $v('footer_line2', "Direction artistique : Ambre Lavignac & Ewen d'Aviau")); ?>
</div></div>

</div><!-- fin colonne gauche -->

<!-- BARRE LATÉRALE ──────────────────────────────────── -->
<div class="ps-sticky">
  <button type="submit" class="ps-save">💾 Enregistrer les réglages</button>
  <div class="ps-sidebar">
    <h3>Comment ça marche ?</h3>
    <ul>
      <li>Modifiez les textes ici</li>
      <li>Cliquez <strong>Enregistrer</strong></li>
      <li>Le site se met à jour immédiatement</li>
    </ul>
    <hr style="margin:12px 0">
    <h3>Galerie & Événements</h3>
    <ul>
      <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=galerie')); ?>">Gérer la galerie →</a></li>
      <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=evenement')); ?>">Gérer les événements →</a></li>
      <li><a href="<?php echo esc_url(admin_url('admin.php?page=ps-newsletter')); ?>">Newsletter →</a></li>
    </ul>
  </div>
</div>

</div><!-- fin grille -->
</form>
</div>
    <?php
}

/* ── Helpers de rendu ───────────────────────────────────────── */
function ps_section(string $id, string $title): void {
    echo '<div class="ps-section" id="ps-' . esc_attr($id) . '">';
    echo '<h2>' . esc_html($title) . ' <span class="ps-toggle">▼ Masquer</span></h2>';
}

function ps_field(string $type, string $key, string $label, string $value, string $hint = ''): void {
    $id = 'ps_opt_' . $key;
    echo '<div class="ps-row">';
    echo '<div><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    if ($hint) echo '<p class="ps-hint">' . esc_html($hint) . '</p>';
    echo '</div><div>';
    if ($type === 'textarea') {
        echo '<textarea id="' . esc_attr($id) . '" name="ps_options[' . esc_attr($key) . ']">'
            . esc_textarea($value) . '</textarea>';
    } else {
        echo '<input type="text" id="' . esc_attr($id) . '" name="ps_options[' . esc_attr($key) . ']" value="'
            . esc_attr($value) . '">';
    }
    echo '</div></div>';
}
