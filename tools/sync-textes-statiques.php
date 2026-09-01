<?php
/**
 * Régénère les textes des fichiers autonomes de `site/` à partir de la
 * source unique du thème : wordpress-theme/poivre-sens/inc/textes.php
 *
 *     php tools/sync-textes-statiques.php
 *
 * Pourquoi : les mêmes phrases vivaient en plusieurs copies, sous trois
 * balisages différents. Les copies ont divergé sans que cela se voie.
 * Ce script supprime la recopie manuelle — on n'écrit plus que dans
 * textes.php.
 *
 * Le script refuse d'écrire si l'un des repères attendus est introuvable :
 * mieux vaut un échec bruyant qu'un fichier à moitié synchronisé.
 *
 * Fichiers régénérés :
 *   · site/index.html          — site autonome (classes CSS du thème)
 *   · site/gutenberg-import.txt — import Gutenberg (styles en ligne)
 *
 * site/source.txt n'est PAS régénéré : c'est une capture de ce que le site
 * en production renvoie réellement, pas une source éditoriale.
 */

if (PHP_SAPI !== 'cli') { exit("À lancer en ligne de commande.\n"); }

define('ABSPATH', __DIR__);
$racine = dirname(__DIR__);
require $racine . '/wordpress-theme/poivre-sens/inc/textes.php';

$T       = ps_textes();
$erreurs = [];

/* ══════════════════════════════════════════════════════════════
   Outils de découpe
   ══════════════════════════════════════════════════════════════ */

/**
 * Remplace le contenu intérieur de l'élément ouvert par $ouverture,
 * en comptant l'imbrication de $balise pour trouver la vraie fermeture.
 */
function inner(string $doc, string $ouverture, string $balise, string $neuf, string $label): string
{
    global $erreurs;
    $i = strpos($doc, $ouverture);
    if ($i === false) { $erreurs[] = "repère introuvable : $label"; return $doc; }
    $debut = $i + strlen($ouverture);
    $prof  = 1;
    $pos   = $debut;
    $fin   = null;
    while ($prof > 0) {
        $o = strpos($doc, '<' . $balise, $pos);
        $f = strpos($doc, '</' . $balise . '>', $pos);
        if ($f === false) { $erreurs[] = "fermeture introuvable : $label"; return $doc; }
        if ($o !== false && $o < $f) { $prof++; $pos = $o + 1; continue; }
        $prof--;
        if ($prof === 0) { $fin = $f; break; }
        $pos = $f + 1;
    }
    return substr($doc, 0, $debut) . $neuf . substr($doc, $fin);
}

/** Remplace la région allant de $debut (inclus) à $fin (inclus). */
function region(string $doc, string $ancre, string $debut, string $fin, string $neuf, string $label): string
{
    global $erreurs;
    $a = $ancre === '' ? 0 : strpos($doc, $ancre);
    if ($a === false) { $erreurs[] = "ancre introuvable : $label"; return $doc; }
    $i = strpos($doc, $debut, $a);
    if ($i === false) { $erreurs[] = "début introuvable : $label"; return $doc; }
    $j = strpos($doc, $fin, $i);
    if ($j === false) { $erreurs[] = "fin introuvable : $label"; return $doc; }
    return substr($doc, 0, $i) . $neuf . substr($doc, $j + strlen($fin));
}

/** Remplacement littéral, avec contrôle du nombre d'occurrences. */
function litteral(string $doc, string $ancien, string $neuf, int $attendu, string $label): string
{
    global $erreurs;
    $n = substr_count($doc, $ancien);
    if ($n !== $attendu) {
        $erreurs[] = "occurrences inattendues ($n au lieu de $attendu) : $label";
        return $doc;
    }
    return str_replace($ancien, $neuf, $doc);
}

/* ══════════════════════════════════════════════════════════════
   1. site/index.html — classes CSS du thème
   ══════════════════════════════════════════════════════════════ */

$f = $racine . '/site/index.html';
$d = file_get_contents($f);

$d = inner($d, '<blockquote class="hero__q">', 'blockquote',
    "\n      " . $T['hero']['citation'] . "\n    ", 'index/hero citation');

$d = inner($d, '<p class="hero__intro">', 'p',
    "\n      " . $T['hero']['intro'] . "\n    ", 'index/hero intro');

$d = inner($d, '<h2 class="mf-t">', 'h2',
    $T['manifeste']['titre_html'], 'index/manifeste titre');

$paras = '';
foreach ($T['manifeste']['paragraphes'] as $p) { $paras .= "\n      <p>$p</p>"; }
$d = inner($d, '<div class="mf-tx">', 'div', $paras . "\n    ", 'index/manifeste corps');

$axes = '';
foreach ($T['projet']['axes'] as $a) {
    $axes .= "\n    <div class=\"axe\">\n      <p class=\"axe__n\">{$a['num']}</p>\n"
           . "      <h3 class=\"axe__t\">{$a['titre']}</h3>\n"
           . "      <p class=\"axe__tx\">{$a['texte']}</p>\n    </div>";
}
$d = inner($d, '<div class="axes">', 'div', $axes . "\n  ", 'index/axes');

$bios = '';
foreach ($T['artistes']['bios'] as $b) {
    $tx = '';
    foreach ($b['textes'] as $t) { $tx .= "\n      <p class=\"bio__tx\">$t</p>"; }
    $tg = '';
    foreach ($b['tags'] as $t) { $tg .= "<span class=\"bio__tg\">$t</span>"; }
    $bios .= "\n    <div class=\"bio\">\n      <div class=\"bio__hd\">\n"
           . "        <div class=\"bio__mn\" aria-hidden=\"true\">{$b['initiale']}</div>\n"
           . "        <div>\n          <h3 class=\"bio__nom\">{$b['nom']}</h3>\n"
           . "          <p class=\"bio__rol\">{$b['role']}</p>\n        </div>\n      </div>"
           . $tx . "\n      <p class=\"bio__tgs\">$tg</p>\n    </div>";
}
$d = inner($d, '<div class="bios">', 'div', $bios . "\n  ", 'index/bios');

$inf = '';
foreach ($T['influences']['items'] as $i2) {
    $inf .= "\n      <div class=\"inf\"><p class=\"inf__n\">{$i2[0]}</p><p class=\"inf__d\">{$i2[1]}</p></div>";
}
$d = inner($d, '<div class="influences">', 'div', $inf . "\n    ", 'index/influences');

$act = '';
foreach ($T['activites']['items'] as $a) {
    $act .= "\n    <li class=\"act\"><span class=\"act__n\" aria-hidden=\"true\">{$a['num']}</span>"
          . "<div><h3 class=\"act__t\">{$a['titre']}</h3><p class=\"act__tx\">{$a['texte']}</p></div>"
          . "<span class=\"act__b\">{$a['badge']}</span></li>";
}
$d = inner($d, '<ul role="list">', 'ul', $act . "\n  ", 'index/activités');

$dif = '';
foreach ($T['activites']['diffusion'] as $x) { $dif .= "\n      <div class=\"diff-i\">$x</div>"; }
$d = inner($d, '<div class="diff">', 'div', $dif . "\n    ", 'index/diffusion');

$val = '';
foreach ($T['esthetique']['valeurs'] as $v) {
    $val .= "\n      <div class=\"val\"><p class=\"val__l\">{$v[0]}</p><p class=\"val__t\">{$v[1]}</p></div>";
}
$d = inner($d, '<div class="esthet__vals">', 'div', $val . "\n    ", 'index/valeurs');

$d = inner($d, '<blockquote class="gcite">', 'blockquote',
    "\n        " . str_replace('<br>', '<br/>', $T['esthetique']['citation_html']) . "\n      ",
    'index/citation');
$d = inner($d, '<p class="gcite__src">', 'p', $T['esthetique']['citation_source'], 'index/citation source');
$d = inner($d, '<p class="co-note">', 'p', $T['contact']['note'], 'index/note contact');

if (!$erreurs) { file_put_contents($f, $d); echo "✓ site/index.html régénéré\n"; }

/* ══════════════════════════════════════════════════════════════
   2. site/gutenberg-import.txt — styles en ligne
   ══════════════════════════════════════════════════════════════ */

$OR    = '#c28b36';
$CREME = '#ece3cb';
$SERIF = "'Cormorant Garamond',Georgia,serif";
$SANS  = 'Inter,system-ui,sans-serif';
$GRIS  = 'rgba(236,227,203,.65)';

$f2 = $racine . '/site/gutenberg-import.txt';
$g  = file_get_contents($f2);

/* Hero — citation puis chapô */
$g = region($g, '<!-- wp:column {"className":"ps-hero-d"} -->',
    "  <span style=\"display:block;font-size:3.8rem", "</blockquote>",
    "  <span style=\"display:block;font-size:3.8rem;line-height:1;color:$OR;font-style:normal;margin-bottom:6px\">&ldquo;</span>\n"
    . "  " . $T['hero']['citation'] . "\n</blockquote>",
    'gut/hero citation');

$g = region($g, '<!-- wp:column {"className":"ps-hero-d"} -->',
    '<p style="color:rgba(236,227,203,0.6);font-size:.92rem', '</p>',
    '<p style="color:rgba(236,227,203,0.6);font-size:.92rem;line-height:1.85;font-family:' . $SANS
    . ';margin-top:0">' . $T['hero']['intro'] . '</p>',
    'gut/hero chapô');

/* Manifeste — tout le bloc wp:html */
$mf = '';
foreach ($T['manifeste']['paragraphes'] as $k => $p) {
    $marge = ($k === count($T['manifeste']['paragraphes']) - 1) ? '0' : '0 0 1.2em';
    $mf .= "      <p style=\"margin:$marge\">"
        . str_replace('<em>', '<em style="font-style:italic;color:' . $CREME . '">', $p)
        . "</p>\n";
}
$titre_mf = str_replace('<em>', '<em style="color:' . $OR . '">', $T['manifeste']['titre_html']);
$g = region($g, '<p class="ps-lbl" style="margin-bottom:32px">',
    '<div style="display:flex;gap:64px;align-items:flex-start">',
    "</div>\n<!-- /wp:html -->",
    '<div style="display:flex;gap:64px;align-items:flex-start">' . "\n"
    . '  <p style="font-size:.67rem;font-weight:400;letter-spacing:.28em;text-transform:uppercase;color:' . $OR
    . ";writing-mode:vertical-rl;transform:rotate(180deg);flex-shrink:0;padding-top:6px;font-family:$SANS\" aria-hidden=\"true\">"
    . $T['manifeste']['label'] . "</p>\n"
    . "  <div>\n"
    . '    <h2 style="font-family:' . $SERIF . ';font-size:clamp(2rem,3.8vw,3.2rem);font-weight:300;line-height:1.22;color:'
    . $CREME . ';margin:0 0 28px">' . $titre_mf . "</h2>\n"
    . '    <div style="columns:2;column-gap:52px;font-size:.96rem;line-height:1.88;color:' . $GRIS
    . ';font-family:' . $SANS . '">' . "\n" . $mf . "    </div>\n"
    . "  </div>\n</div>\n<!-- /wp:html -->",
    'gut/manifeste');

/* Axes du projet */
$cols = '';
foreach ($T['projet']['axes'] as $a) {
    $cols .= "\n" . '<!-- wp:column {"className":"ps-axe","style":{"color":{"background":"#080705"},"spacing":{"padding":{"top":"40px","right":"32px","bottom":"40px","left":"32px"}}}} -->' . "\n"
        . '<div class="wp-block-column ps-axe" style="background-color:#080705;padding:40px 32px;border-top:2px solid #9e3710">' . "\n"
        . '<!-- wp:html --><p style="font-family:' . $SERIF . ';font-size:4rem;font-weight:300;line-height:1;color:rgba(194,139,54,.18);margin:0 0 12px">' . $a['num'] . '</p><!-- /wp:html -->' . "\n"
        . '<!-- wp:heading {"level":3,"style":{"typography":{"fontFamily":"\'Cormorant Garamond\', Georgia, serif","fontSize":"1.35rem","fontWeight":"400"},"color":{"text":"#ece3cb"}}} -->' . "\n"
        . '<h3 class="wp-block-heading" style="font-family:' . $SERIF . ';font-size:1.35rem;font-weight:400;color:' . $CREME . '">' . $a['titre'] . "</h3>\n"
        . "<!-- /wp:heading -->\n"
        . '<!-- wp:paragraph {"style":{"color":{"text":"rgba(127,116,99,1)"},"typography":{"fontSize":"0.87rem","lineHeight":"1.78"}}} -->' . "\n"
        . '<p style="color:rgba(127,116,99,1);font-size:.87rem;line-height:1.78;font-family:' . $SANS . '">' . $a['texte'] . "</p>\n"
        . "<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n";
}
$g = region($g, '>Le projet artistique</h2>',
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->', "</div>\n<!-- /wp:columns -->",
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->' . "\n"
    . '<div class="wp-block-columns" style="gap:1px">' . "\n" . $cols . "\n</div>\n<!-- /wp:columns -->",
    'gut/axes');

/* Bios */
$cols = '';
foreach ($T['artistes']['bios'] as $b) {
    $paras = '';
    foreach ($b['textes'] as $t) {
        $paras .= '<!-- wp:paragraph {"style":{"color":{"text":"rgba(236,227,203,0.65)"},"typography":{"fontSize":"0.9rem","lineHeight":"1.82"}}} -->' . "\n"
            . '<p style="color:' . $GRIS . ';font-size:.9rem;line-height:1.82;font-family:' . $SANS . '">' . $t . "</p>\n"
            . "<!-- /wp:paragraph -->\n\n";
    }
    $tags = '';
    foreach ($b['tags'] as $t) { $tags .= '  <span class="ps-bio-tag">' . $t . "</span>\n"; }
    $cols .= "\n" . '<!-- wp:column {"style":{"color":{"background":"#100e0b"},"spacing":{"padding":{"top":"48px","right":"44px","bottom":"48px","left":"44px"}},"border":{"color":"rgba(194,139,54,0.18)","width":"1px"}}} -->' . "\n"
        . '<div class="wp-block-column" style="background-color:#100e0b;padding:48px 44px;border:1px solid rgba(194,139,54,.18)">' . "\n\n"
        . "<!-- wp:html -->\n"
        . '<div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">' . "\n"
        . '  <div style="width:52px;height:52px;border-radius:50%;border:1px solid ' . $OR . ';display:flex;align-items:center;justify-content:center;font-family:' . $SERIF . ';font-size:1.5rem;font-weight:300;color:' . $OR . ';flex-shrink:0" aria-hidden="true">' . $b['initiale'] . "</div>\n"
        . "  <div>\n"
        . '    <h3 style="font-family:' . $SERIF . ';font-size:1.6rem;font-weight:400;color:' . $CREME . ';line-height:1.1;margin:0 0 4px">' . $b['nom'] . "</h3>\n"
        . '    <p style="font-size:.68rem;font-weight:400;letter-spacing:.15em;text-transform:uppercase;color:' . $OR . ';margin:0;font-family:' . $SANS . '">' . $b['role'] . "</p>\n"
        . "  </div>\n</div>\n<!-- /wp:html -->\n\n"
        . $paras
        . "<!-- wp:html -->\n"
        . '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:20px">' . "\n" . $tags . "</div>\n<!-- /wp:html -->\n\n"
        . "</div>\n<!-- /wp:column -->\n";
}
$g = region($g, '>Artistes &amp; pédagogues</h2>',
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->', "</div>\n<!-- /wp:columns -->",
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->' . "\n"
    . '<div class="wp-block-columns" style="gap:1px">' . "\n" . $cols . "\n</div>\n<!-- /wp:columns -->",
    'gut/bios');

/* Influences */
$cols = '';
foreach ($T['influences']['items'] as $i2) {
    $cols .= "\n<!-- wp:column -->\n<div class=\"wp-block-column\">\n"
        . '<!-- wp:paragraph {"style":{"typography":{"fontFamily":"\'Cormorant Garamond\',Georgia,serif","fontSize":"1.05rem","fontWeight":"400"},"color":{"text":"#ece3cb"}}} -->' . "\n"
        . '<p style="font-family:' . $SERIF . ';font-size:1.05rem;font-weight:400;color:' . $CREME . ';margin:0 0 4px">' . $i2[0] . "</p>\n"
        . "<!-- /wp:paragraph -->\n"
        . '<!-- wp:paragraph {"style":{"color":{"text":"rgba(127,116,99,1)"},"typography":{"fontSize":"0.78rem"}}} -->' . "\n"
        . '<p style="color:rgba(127,116,99,1);font-size:.78rem;margin:0;font-family:' . $SANS . '">' . $i2[1] . "</p>\n"
        . "<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n";
}
$g = region($g, '<p class="ps-lbl">Ce qui nous traverse</p>',
    '<!-- wp:columns', "</div>\n<!-- /wp:columns -->",
    '<!-- wp:columns -->' . "\n" . '<div class="wp-block-columns">' . "\n" . $cols . "\n</div>\n<!-- /wp:columns -->",
    'gut/influences');

/* Activités — la régression trouvée en revue : seule la 01 subsistait */
$acts = '';
foreach ($T['activites']['items'] as $a) {
    $acts .= "<!-- wp:html -->\n"
        . '<div class="ps-act" style="display:grid;grid-template-columns:56px 1fr auto;align-items:start;gap:24px;padding:36px 0;border-bottom:1px solid rgba(194,139,54,.18)">' . "\n"
        . '  <span style="font-family:' . $SERIF . ';font-size:2.2rem;font-weight:300;color:rgba(194,139,54,.22);line-height:1" aria-hidden="true">' . $a['num'] . "</span>\n"
        . "  <div>\n"
        . '    <h3 style="font-family:' . $SERIF . ';font-size:1.4rem;font-weight:400;color:' . $CREME . ';margin:0 0 8px">' . $a['titre'] . "</h3>\n"
        . '    <p style="font-size:.87rem;line-height:1.72;color:#7f7463;margin:0;font-family:' . $SANS . '">' . $a['texte'] . "</p>\n"
        . "  </div>\n"
        . '  <span style="font-size:.65rem;font-weight:400;letter-spacing:.14em;text-transform:uppercase;color:' . $OR . ';padding:5px 12px;border:1px solid rgba(194,139,54,.18);align-self:center;white-space:nowrap;font-family:' . $SANS . '">' . $a['badge'] . "</span>\n"
        . "</div>\n<!-- /wp:html -->\n\n";
}
$g = region($g, '<p class="ps-lbl">Ce que nous proposons</p>',
    "<!-- wp:html -->\n<div class=\"ps-act\"", '<!-- wp:spacer {"height":"52px"} -->',
    $acts . '<!-- wp:spacer {"height":"52px"} -->',
    'gut/activités');

/* Diffusion */
$cols = '';
foreach ($T['activites']['diffusion'] as $x) {
    $cols .= "\n" . '<!-- wp:column {"className":"ps-diff","style":{"color":{"background":"#100e0b"},"spacing":{"padding":{"top":"24px","right":"28px","bottom":"24px","left":"28px"}}}} -->' . "\n"
        . '<div class="wp-block-column ps-diff" style="background-color:#100e0b;padding:24px 28px">' . "\n"
        . '<!-- wp:paragraph {"style":{"color":{"text":"rgba(236,227,203,0.68)"},"typography":{"fontSize":"0.9rem","lineHeight":"1.62"}}} -->' . "\n"
        . '<p style="color:rgba(236,227,203,.68);font-size:.9rem;line-height:1.62;font-family:' . $SANS . '"><span style="color:' . $OR . '">&mdash;</span>&nbsp; ' . $x . "</p>\n"
        . "<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n";
}
$g = region($g, '<p class="ps-lbl">Où nous aimerions jouer</p>',
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->', "</div>\n<!-- /wp:columns -->",
    '<!-- wp:columns {"style":{"spacing":{"blockGap":"1px"}}} -->' . "\n"
    . '<div class="wp-block-columns" style="gap:1px">' . "\n" . $cols . "\n</div>\n<!-- /wp:columns -->",
    'gut/diffusion');

/* Valeurs */
$vals = '';
foreach ($T['esthetique']['valeurs'] as $v) {
    $vals .= "\n" . '  <div class="ps-valeur" style="padding-left:24px;border-left:2px solid rgba(194,139,54,.2)">' . "\n"
        . '    <p style="font-size:.67rem;letter-spacing:.22em;text-transform:uppercase;color:' . $OR . ';margin:0 0 6px;font-family:Inter,sans-serif">' . $v[0] . "</p>\n"
        . '    <p style="font-size:.9rem;line-height:1.72;color:rgba(236,227,203,.62);margin:0;font-family:Inter,sans-serif">' . $v[1] . "</p>\n"
        . "  </div>\n";
}
/* Citation de la section esthétique */
$g = region($g, '>Esthétique de la compagnie</h2>',
    '<blockquote class="ps-gcite">', '</blockquote>',
    '<blockquote class="ps-gcite">' . "\n  "
    . str_replace('<br>', '<br/>', $T['esthetique']['citation_html']) . "\n</blockquote>",
    'gut/citation');

$g = region($g, '<blockquote class="ps-gcite">',
    '<p class="ps-gcite-src">', '</p>',
    '<p class="ps-gcite-src">' . $T['esthetique']['citation_source'] . '</p>',
    'gut/citation source');

/* Note de contact */
$g = region($g, '<p style="color:rgba(127,116,99,.8);font-size:.78rem;font-style:italic',
    '<p style="color:rgba(127,116,99,.8);font-size:.78rem;font-style:italic', '</p>',
    '<p style="color:rgba(127,116,99,.8);font-size:.78rem;font-style:italic;margin-top:20px;font-family:'
    . $SANS . '">' . $T['contact']['note'] . '</p>',
    'gut/note contact');

$g = region($g, '>Esthétique de la compagnie</h2>',
    '<div style="display:flex;flex-direction:column;gap:28px">', "</div>\n<!-- /wp:html -->",
    '<div style="display:flex;flex-direction:column;gap:28px">' . "\n" . $vals . "\n</div>\n<!-- /wp:html -->",
    'gut/valeurs');

if (!$erreurs) { file_put_contents($f2, $g); echo "✓ site/gutenberg-import.txt régénéré\n"; }

/* ══════════════════════════════════════════════════════════════ */
if ($erreurs) {
    echo "\nAucun fichier écrit — repères introuvables :\n";
    foreach ($erreurs as $e) { echo "  ✗ $e\n"; }
    exit(1);
}
echo "\nSynchronisé depuis inc/textes.php.\n";
