<?php
/**
 * Poivre & Sens — Textes éditoriaux de la page d'accueil
 *
 * SOURCE UNIQUE DE LA STRUCTURE. Les mêmes phrases étaient auparavant
 * recopiées dans les shortcodes PHP, dans les patterns Gutenberg et dans
 * les trois fichiers autonomes de `site/`. Quatre copies, donc quatre
 * occasions de diverger — et elles ont divergé trois fois sans que cela
 * se voie à l'œil.
 *
 * Désormais :
 *   · ps_textes_defaut() porte les valeurs d'origine (issues des
 *     entretiens avec Ambre et Ewen — autant que possible leurs mots,
 *     pas une paraphrase) ; c'est le point de départ d'un site neuf, et
 *     ce que régénère `tools/sync-textes-statiques.php` pour `site/` ;
 *   · Réglages › Textes du site permet de les modifier depuis
 *     l'administration WordPress, sans toucher au code (voir
 *     inc/textes-admin.php) ; l'édition est enregistrée dans l'option
 *     `ps_textes_overrides` et prend le pas sur les valeurs d'origine ;
 *   · ps_textes() est le point d'entrée que lisent shortcodes et
 *     patterns : il renvoie les valeurs d'origine fusionnées avec ce qui
 *     a été édité dans l'admin.
 *
 * Important : les fichiers `site/index.html` et `site/gutenberg-import.txt`
 * restent des exports autonomes, sans base de données. Ils reflètent
 * ps_textes_defaut(), pas les éditions faites depuis l'admin WordPress —
 * ce sont deux usages différents (site WordPress en ligne / export
 * indépendant), pas deux copies d'une même vérité à garder synchrones.
 */
defined('ABSPATH') || exit;

/**
 * Valeurs d'origine de tous les textes de la page d'accueil.
 * Ne PAS modifier au fil de l'eau pour un usage courant : c'est le
 * point de départ, pas le texte en ligne — voir Réglages › Textes du
 * site dans l'admin WordPress pour l'édition courante.
 *
 * @return array<string,mixed>
 */
function ps_textes_defaut(): array {
    static $t = null;
    if ($t !== null) {
        return $t;
    }

    $t = [

        /* ── ① Hero ─────────────────────────────────────────────── */
        'hero' => [
            'surtitre'    => 'Jeune compagnie · Association loi 1901',
            'disciplines' => [
                'Danse contemporaine',
                'Contact-improvisation',
                'Musique improvisée',
                'Pratiques somatiques',
            ],
            'cta'      => 'Découvrir la compagnie',
            'citation' => "Le corps sait ce que l'esprit ne sait pas.",
            'intro'    => "À peine formée, la compagnie fait ses premiers pas. Nous sommes deux, venus au mouvement par des chemins qui n'ont rien à voir. Ce qui nous met en joie : créer ensemble, sans savoir d'avance ce qui va arriver.",
        ],

        /* ── ② Manifeste ────────────────────────────────────────── */
        'manifeste' => [
            'label'      => 'Manifeste',
            'titre_html' => "Être plus proche de soi, <em>au contact des autres</em>",
            'paragraphes' => [
                "À peine formée, la compagnie fait ses premiers pas. Elle est née de la rencontre de deux corps aux histoires différentes : l'une venue de la danse contemporaine, l'autre de la lutherie, passé par plusieurs arts et techniques de danse en y cherchant chaque fois un dépassement.",

                "Ce qui nous met en joie, c'est la créativité collective et partagée. L'inattendu, l'impromptu, l'improvisation. Ce moment où personne ne sait encore ce qui va se passer, et où l'on y va quand même.",

                "Et puis chercher, encore et toujours : comment habiter davantage son corps, comment être dans une présence de plus en plus juste, de plus en plus fine. Nous nous appuyons pour cela sur les pratiques somatiques, le Tao et la kinésiologie.",

                "Il y a dans ce travail une recherche d'intimité, et ce n'est pas un repli — c'est l'inverse. <em>Être plus proche de soi, précisément au contact des autres.</em> Rester relié à soi pendant qu'on est en relation : c'est là, pour nous, que tout se joue.",

                "Cela demande de savoir où l'on en est. « Je suis où, là ? » Il y a des jours où l'on n'est pas disponible pour être à deux, faute d'en avoir les ressources ce jour-là. Ce n'est pas un échec, c'est une information. Nous accueillons les limites telles qu'elles sont, dans le présent.",

                "Nous enseignons à deux, depuis deux pratiques distinctes. C'est ce croisement — le somatique et la danse, le geste et le son — qui fait, nous semble-t-il, ce que l'on ne trouve pas ailleurs.",

                "Vous n'avez pas besoin de savoir danser. Ce que nous entendons le plus souvent au début, ce sont ces phrases-là : « je n'ose pas danser devant les autres », « j'ai du mal avec mon corps », « j'ai peur du sol », « je me sens seul ». C'est exactement de là que l'on part. L'un de nous a bâti toute sa pratique en traversant des troubles dys : on ne vient pas ici <em>malgré</em> ce qui coince, on vient avec.",

                "Il y a aussi une envie plus large : faire connaître ce que la danse fait à qui la pratique. Pas celle qu'on regarde — <em>celle qui change la vie</em>. C'est pourquoi nous assumons deux entrées plutôt qu'une : selon le thème, un rendez-vous sera tourné vers le développement personnel, ou franchement artistique. L'orientation est annoncée pour chaque date, pour que vous sachiez avant de venir dans quel espace vous entrez.",

                "Nous espérons rencontrer des gens émerveillables, curieux d'aller chercher et d'expérimenter. Nous ne saurions pas quoi faire, en revanche, de quelqu'un qui viendrait chercher un chemin tout tracé, ou quelqu'un à admirer. Ici, l'engagement que vous mettez pour vous-même compte davantage que ce que nous pourrions vous apporter.",
            ],
        ],

        /* ── ③ Artistes ─────────────────────────────────────────── */
        'artistes' => [
            'label' => 'Les fondateurs',
            'titre' => 'Artistes &amp; pédagogues',
            'bios'  => [
                [
                    'initiale' => 'A',
                    'nom'      => 'Ambre Lavignac',
                    'role'     => 'Danseuse · Pédagogue · Praticienne du mouvement',
                    'textes'   => [
                        "Venue de la danse contemporaine, Ambre oriente sa recherche vers les pratiques somatiques et les savoirs corporels anciens : philosophie taoïste, méridiens, kinésiologie, massage. Elle explore les correspondances entre les éléments, la circulation de l'énergie et les qualités de mouvement.",
                        "S'il ne lui fallait transmettre qu'une chose, ce serait la sensation — cette finesse d'écoute que l'on affine peu à peu, en allant contacter une part de soi et en l'écoutant. L'improvisation est pour elle un espace de création vivante, et une affaire de poésie.",
                    ],
                    'tags' => ['Danse contemporaine', 'Improvisation', 'Somatique', 'Tao', 'Méridiens', 'Kinésiologie', 'Massage', 'Pédagogie'],
                ],
                [
                    'initiale' => 'E',
                    'nom'      => "Ewen d'Aviau",
                    'role'     => 'Luthier-ingénieur · Musicien · Danseur',
                    'textes'   => [
                        "Luthier, Ewen porte des troubles dys — il l'écrit lui-même : « Dys sur Dys ». Il est venu au mouvement par plusieurs arts et techniques de danse, en y cherchant chaque fois un dépassement. Traverser une différence oblige à inventer ses propres chemins : c'est vrai de sa pratique comme de sa pédagogie.",
                        "Il conçoit le son comme une matière vivante, façonnable, imprévue. S'il ne lui fallait transmettre qu'une chose, ce serait l'interconnexion : comment toutes les parties du corps peuvent être partie prenante d'un même mouvement. Une synergie, plutôt qu'une somme de gestes.",
                    ],
                    'tags' => ['Lutherie', 'Musique improvisée', 'Contact-improvisation', 'Somatique', 'Aïkido', 'Enseignement'],
                ],
            ],
        ],

        /* ── Ce qui nous traverse ───────────────────────────────── */
        'influences' => [
            'label' => 'Ce qui nous traverse',
            'items' => [
                ['Les pratiques somatiques', 'Écouter le corps avant de lui demander'],
                ['Le mouvement inné',        "Ce que le corps sait depuis l'enfance"],
                ['Le Tao',                   'Fluidité, transformation, non-résistance'],
                ['La kinésiologie',          "Le corps comme source d'information"],
                ['Le contact-improvisation', "Le poids, l'appui, l'écoute à deux"],
                ['La musique improvisée',    'Le son comme matière vivante'],
            ],
        ],

        /* ── ④ Projet artistique ────────────────────────────────── */
        'projet' => [
            'label' => "Note d'intention",
            'titre' => 'Le projet artistique',
            'axes'  => [
                [
                    'num'   => '01',
                    'titre' => 'Créer à deux voix',
                    'texte' => "La compagnie a pour but la création de performances : des pièces en duo ou avec des artistes invités, où la frontière entre la partition musicale et la partition corporelle s'efface. Grandir, et faire grandir ceux qui regardent. Les premières sont en chantier.",
                ],
                [
                    'num'   => '02',
                    'titre' => "L'improvisation comme forme",
                    'texte' => "Non pas une absence de forme, mais une forme en devenir. L'inattendu et l'impromptu ne sont pas des accidents que l'on rattrape : ce sont nos matériaux de départ.",
                ],
                [
                    'num'   => '03',
                    'titre' => 'Partir de ce que le corps sait',
                    'texte' => "Nous ne partons pas d'un vocabulaire à apprendre, mais de mouvements que vous portez déjà : marcher, s'appuyer, se tourner. Ils sont là, à l'état de graine ; notre travail est de les faire germer, sans rien couper de votre histoire.",
                ],
            ],
        ],

        /* ── ⑤ Nos activités ────────────────────────────────────── */
        'activites' => [
            'label'   => 'Ce que nous proposons',
            'titre'   => 'Nos activités',
            'chapeau' => "Chaque rendez-vous est annoncé avec son orientation : certains sont tournés vers le développement personnel, d'autres sont franchement artistiques. Cela dépend du thème, et jamais du niveau de qui vient.",
            'items' => [
                [
                    'num'    => '01',
                    'titre'  => 'Les ateliers de danse — deux fois par mois',
                    'texte'  => "Notre rendez-vous le plus régulier : 2 h 30, deux fois par mois, ouvert à tous, sans prérequis ni niveau demandé. On y explore le mouvement à deux voix, et l'on repart avec quelque chose à pratiquer chez soi.",
                    'badge'  => 'Bimensuel',
                ],
                [
                    'num'    => '02',
                    'titre'  => 'Les ateliers de danse — une fois par mois',
                    'texte'  => "Le même esprit, sur un rythme mensuel, pour celles et ceux qui ne peuvent pas venir deux fois. Chaque séance se suffit à elle-même : on peut arriver en cours d'année.",
                    'badge'  => 'Mensuel',
                ],
                [
                    'num'    => '03',
                    'titre'  => 'Stages',
                    'texte'  => "Sur une journée ou un week-end, autour d'une thématique. Deux intervenants, une trentaine de personnes au maximum : au-delà, la qualité de présence que nous voulons offrir ne tiendrait plus.",
                    'badge'  => 'Stage',
                ],
                [
                    'num'    => '04',
                    'titre'  => 'Jams contact-improvisation',
                    'texte'  => "Des sessions d'improvisation ouvertes, en contact-improvisation et musique improvisée. On vient danser, jouer, se rencontrer.",
                    'badge'  => 'Jam',
                ],
                [
                    'num'    => '05',
                    'titre'  => 'Créations &amp; performances',
                    'texte'  => "Pièces en duo ou avec des artistes invités. La compagnie débute : les premières créations sont en cours d'élaboration.",
                    'badge'  => 'Scène',
                ],
                [
                    'num'    => '06',
                    'titre'  => 'Interventions &amp; ateliers',
                    'texte'  => "Sur demande, pour des groupes constitués, des structures ou des événements. Nous adaptons le format et la thématique.",
                    'badge'  => 'Sur mesure',
                ],
            ],
            'diffusion_label' => 'Où nous aimerions jouer',
            'diffusion'       => [
                'Festivals de danse contemporaine, contact-improvisation et musique improvisée — France &amp; Europe',
                'Théâtres et scènes labellisées accueillant les écritures chorégraphiques émergentes',
                "Lieux non conventionnels : musées, bibliothèques, espaces naturels, ateliers d'artistes",
                'Établissements scolaires et structures socioculturelles pour les ateliers pédagogiques',
            ],
        ],

        /* ── ⑦ Esthétique ──────────────────────────────────────── */
        'esthetique' => [
            'label'   => 'Identité &amp; valeurs',
            'titre'   => 'Esthétique de la compagnie',
            'valeurs' => [
                ['Sensation',       "Avant la forme, la sensation. C'est elle qui guide le mouvement, et c'est elle que nous cherchons à affiner : cette finesse d'écoute que l'on développe en allant contacter une part de soi."],
                ['Interconnexion',  "Comment toutes les parties du corps peuvent être partie prenante d'un même mouvement. Une synergie, plutôt qu'une somme de gestes."],
                ['Mouvement inné',  "Nous partons de ce que le corps a développé depuis l'enfance — marcher, s'appuyer, se tourner — plutôt que d'un vocabulaire venu d'ailleurs. Rien n'est coupé de votre histoire."],
                ['Deux voix',       "Nous enseignons à deux, depuis deux pratiques distinctes : le somatique et la danse, le geste et le son. C'est le croisement qui fait la richesse."],
                ['Limites',         "Savoir où l'on en est, ce qui est juste pour soi. On n'est pas toujours disponible pour être à deux : c'est une information, pas un échec."],
                ['Joie',            "C'est le mot qui revient le plus souvent chez ceux qui repartent. La joie du relationnel, du jeu, de l'échange avec une autre personne."],
            ],
            'citation_html'   => "Partir de ce que le corps sait déjà —<br>et le faire <em>germer</em>.",
            'citation_source' => "Poivre &amp; Sens · Note d'intention",
        ],

        /* ── ⑧ Contact ─────────────────────────────────────────── */
        'contact' => [
            'label' => 'Nous rejoindre',
            'titre' => 'Contact',
            'note'  => "La compagnie débute. Pour être prévenu des premières dates — ateliers, stages, jams — le plus simple est de laisser votre adresse dans le formulaire ci-dessus.",
        ],
    ];

    return $t;
}

/**
 * Textes effectifs : les valeurs d'origine, fusionnées avec ce qui a été
 * édité depuis Réglages › Textes du site. C'est CETTE fonction que lisent
 * les shortcodes et les patterns de inc/block-patterns.php.
 *
 * Hors WordPress (le script tools/sync-textes-statiques.php, qui tourne
 * en CLI sans base de données) get_option() n'existe pas : on renvoie
 * alors simplement les valeurs d'origine, ce qui est le comportement
 * voulu pour un export autonome.
 *
 * @return array<string,mixed>
 */
function ps_textes(): array {
    static $effectif = null;
    if ($effectif !== null) {
        return $effectif;
    }

    $defaut = ps_textes_defaut();

    if (!function_exists('get_option')) {
        return $effectif = $defaut;
    }

    $edite = get_option('ps_textes_overrides', []);
    if (!is_array($edite) || !$edite) {
        return $effectif = $defaut;
    }

    return $effectif = ps_textes_fusionner($defaut, $edite);
}

/**
 * Un tableau est-il une liste (clés 0, 1, 2… dans l'ordre) plutôt qu'un
 * tableau associatif ? Compatible PHP 7.4+ (array_is_list() n'existe
 * qu'à partir de PHP 8.1).
 */
function ps_textes_est_liste(array $arr): bool {
    if ($arr === []) {
        return true;
    }
    return array_keys($arr) === range(0, count($arr) - 1);
}

/**
 * Fusionne les valeurs éditées dans l'admin par-dessus les valeurs
 * d'origine. Une liste (activités, axes, valeurs, influences, diffusion,
 * paragraphes du manifeste…) est remplacée EN BLOC par la version éditée
 * quand elle est présente : ajouter ou retirer une ligne dans l'admin
 * doit se voir, pas se retrouver mélangé avec les lignes d'origine.
 * Un tableau associatif (hero, manifeste, une bio…) est fusionné champ
 * par champ, pour que modifier un seul champ n'efface pas les autres.
 */
function ps_textes_fusionner(array $defaut, array $edite): array {
    foreach ($edite as $cle => $valeur) {
        if (!array_key_exists($cle, $defaut)) {
            continue; // Clé inconnue (ancienne version de l'admin, bidouille) : ignorée par prudence.
        }
        $valeur_defaut = $defaut[$cle];

        if (is_array($valeur) && is_array($valeur_defaut)) {
            if (ps_textes_est_liste($valeur) || ps_textes_est_liste($valeur_defaut)) {
                $defaut[$cle] = $valeur;
            } else {
                $defaut[$cle] = ps_textes_fusionner($valeur_defaut, $valeur);
            }
        } elseif (is_string($valeur)) {
            if (trim($valeur) !== '') {
                $defaut[$cle] = $valeur;
            }
            // Une chaîne vide dans l'édition ne remplace pas la valeur d'origine :
            // filet de sécurité contre un champ vidé par erreur.
        } else {
            $defaut[$cle] = $valeur;
        }
    }
    return $defaut;
}
