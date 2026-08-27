# 🌶 Quickstart — Thème WordPress **Poivre & Sens**

> Site web officiel de la Compagnie Poivre & Sens  
> Danse contemporaine · Contact-improvisation · Musique improvisée  
> 🌐 `cie.poivresens.fr` · ✉ `contact@cie.poivresens.fr`

---

## 📋 Prérequis

| Logiciel | Version minimum |
|----------|-----------------|
| WordPress | 6.3+ |
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |

---

## ❓ Dois-je utiliser le fichier `gutenberg-import.txt` ?

**Non**, si vous installez le thème **Poivre & Sens** (ce dossier).

Le fichier `site/gutenberg-import.txt` existe pour deux situations différentes :

| Situation | Ce qu'il faut faire |
|-----------|---------------------|
| ✅ **Vous utilisez le thème `poivre-sens`** | **N'importez rien.** La page d'accueil est gérée automatiquement par `front-page.php`. Créez juste une page vide intitulée `Accueil` et définissez-la comme page statique (étape 3 ci-dessous). |
| 🔄 **Vous utilisez un autre thème WordPress** (Savoy, Kadence, Blocksy…) | Utilisez `gutenberg-import.txt` pour recréer le design manuellement dans l'éditeur Gutenberg. Collez son contenu dans l'éditeur de code d'une page vierge. |

> **En résumé avec notre thème :** vous gérez le contenu via les menus de l'admin —
> **Galerie**, **Événements**, **Newsletter** — et le thème construit la page automatiquement.
> Pas besoin de toucher à l'éditeur de la page "Accueil".

---

## 🚀 Installation en 5 minutes

### 1. Uploader le thème

1. Ouvrez votre admin WordPress → **Apparence › Thèmes › Ajouter**
2. Cliquez **Téléverser un thème**
3. Uploadez l'archive `poivre-sens.zip` (le dossier `wordpress-theme/poivre-sens/`)
4. Cliquez **Activer**

> **Alternative via FTP** : copiez le dossier `poivre-sens/` dans `wp-content/themes/`

#### Obtenir l'archive ZIP à jour

Pour éviter les erreurs GitHub sur les fichiers binaires, l'archive ZIP n'est pas
mise à jour directement dans les demandes d'extraction. Elle est générée comme
artefact téléchargeable par GitHub Actions.

- Sur GitHub : ouvrez l'onglet **Actions**, choisissez le workflow
  **Build WordPress theme zip**, ouvrez le dernier run, puis téléchargez
  l'artefact **poivre-sens-theme**. Il contient `poivre-sens.zip`.
- En local : lancez `scripts/build-wordpress-theme-zip.sh`, puis récupérez
  `dist/poivre-sens.zip`.

---

### 2. Configurer les permaliens ⚠️ Important

**Réglages › Permaliens** → sélectionnez **Nom de l'article** (`/%postname%/`)  
Puis cliquez **Enregistrer les modifications** pour activer `/evenements/`.

---

### 3. Créer la page d'accueil

1. **Pages › Ajouter** → titre : `Accueil`
2. **Laissez le contenu vide** — ne collez rien dans l'éditeur
3. **Réglages › Lecture** → sélectionnez **Une page statique** → choisissez `Accueil`
4. Le thème charge automatiquement `front-page.php` qui construit toute la page

> 💡 Le contenu de la page d'accueil est entièrement géré par le thème via les CPT
> (Galerie, Événements) et les options de l'admin. Vous n'avez **pas** à coller
> le fichier `gutenberg-import.txt` ici.

---

### 4. Structure des menus (optionnel)

**Apparence › Menus** → créez un menu avec :
- Accueil (`/`)
- Galerie (`#galerie`)
- Événements (`/evenements/`)
- Contact (`#contact`)

Affectez-le à l'emplacement **Menu principal**.

---

## ✏️ Modifier le contenu du site

Tout le contenu de la page d'accueil est édité directement dans **l'éditeur Gutenberg**.

### Accéder à l'éditeur

1. Admin → **Pages**
2. Cliquez sur la page **Accueil**
3. L'éditeur Gutenberg s'ouvre — chaque section est un bloc cliquable

> **Première installation ?** Si la page est vide, cliquez **+** › **Parcourir les patterns** › **Poivre & Sens** › **Page d'accueil complète**. Tous les blocs sont insérés en une fois.

---

### Sections entièrement éditables (cliquez, tapez, enregistrez)

| Pattern | Ce que vous pouvez modifier |
|---------|---------------------------|
| **① Hero** | Sur-titre, nom, disciplines, texte bouton, citation, texte d'intro |
| **② Manifeste** | Titre (avec _italiques_), 3 paragraphes |
| **③ Artistes** | Biographies, rôles, mots-clés, initiales |
| **Références & influences** | Noms et descriptions des 6 influences |
| **④ Projet artistique** | Titre, 3 axes (numéro, titre, texte) |
| **⑤ Nos activités** | 6 activités (numéro, titre, texte, badge) + 4 axes de diffusion |
| **⑦ Esthétique** | 5 valeurs (label, texte) + citation |
| **⑧ Contact** | Informations compagnie, emails, note réseaux |

> **Comment modifier un texte** : cliquez sur le paragraphe ou le titre → modifiez directement → **Mettre à jour** (bouton bleu en haut à droite).

---

### Insérer un pattern individuel

Si vous souhaitez refaire une section depuis zéro :

1. Cliquez sur **+** pour ajouter un bloc
2. Cherchez **Patterns** › **Poivre & Sens**
3. Les patterns disponibles sont :  
   ① Hero · ② Manifeste · ③ Artistes · ④ Projet artistique  
   ⑤ Nos activités · ⑥ Événements · ⑦ Esthétique · ⑧ Contact

---

### Sections dynamiques (ne pas supprimer les blocs Shortcode)

Ces sections se remplissent automatiquement depuis les menus de l'admin :

| Shortcode dans l'éditeur | Géré via | Ce qu'il affiche |
|--------------------------|----------|-----------------|
| `[ps_galerie]` | **Galerie › Ajouter** | 6 photos en grille |
| `[ps_evenements]` | **Événements › Ajouter** | 3 prochains événements |
| `[ps_newsletter]` | Automatique | Formulaire d'inscription |

> ⚠️ Ne supprimez pas ces blocs Shortcode dans l'éditeur — ils relient les sections dynamiques.

---

### Galerie photos et Événements

Ces sections se gèrent séparément (elles affichent un contenu dynamique) :
- **Galerie** → menu **Galerie › Ajouter** — ajoutez un titre, une image à la une, un sous-titre
- **Événements** → menu **Événements › Ajouter**

---

## 🎭 Gestion des événements

### Créer un événement

1. Admin → **Événements › Ajouter**
2. Remplissez le titre et le contenu (éditeur Gutenberg)
3. Dans le bloc **Détails de l'événement** (sur la droite) :

| Champ | Description |
|-------|-------------|
| **Date** | Date de l'événement (sélecteur de date) |
| **Heure de début / fin** | Format `HH:MM` |
| **Type** | Spectacle / Jam / Atelier / Résidence / Concert / Autre |
| **Lieu** | Nom de la salle ou du lieu |
| **Adresse / Ville** | Pour la carte et les filtres |
| **Tarif** | Ex : `12€`, `Sur réservation`, `Gratuit` |
| **Lien billetterie** | URL externe (Billetweb, HelloAsso, etc.) |
| **Complet** | Cocher si l'événement est complet |

4. Ajoutez une **Image à la une** (recommandé : 800×450 px minimum)
5. Cliquez **Publier**

L'événement apparaît automatiquement :
- Sur la page d'accueil (section **Événements à venir**, 3 prochains)
- Sur la page `/evenements/` (calendrier en liste, groupé par mois)

---

## 🖼 Gestion de la galerie

### Remplacer les photos placeholder

1. Admin → **Galerie › Ajouter**
2. Titre = légende principale (ex : `En scène`)
3. Champ **Sous-titre** = description courte (au survol)
4. **Image à la une** = la photo (JPEG/PNG, min. 900×900 px)
5. **Ordre** = utilisez le champ `Ordre` (1 à 6) ou le plugin **Simple Page Ordering**

> Les 6 premières photos (par ordre de menu) remplacent les SVG placeholder.

---

## 📧 Gestion de la newsletter

### Accès à l'interface

Admin → **Newsletter** (icône enveloppe dans le menu gauche)

### Tableau de bord

- Statistiques abonnés actifs / désabonnés
- Graphique des nouvelles inscriptions (12 mois)
- Dernière campagne et taux d'ouverture

### Gérer les listes (localiser l'origine des prospects)

**Newsletter › Listes**

Une liste permet de savoir **d'où vient chaque prospect** (site web, un
événement, un salon…) et d'envoyer des campagnes ciblées. Un même abonné
peut appartenir à plusieurs listes.

Deux listes sont créées automatiquement à l'activation :
- **Site web** (`site`) — inscriptions via le formulaire `[ps_newsletter]`
- **Grand Bal de l'Europe** (`grand-bal-europe`) — voir le shortcode
  `[ps_newsletter_liste]` ci-dessous, pour les landing pages composées à
  la main dans Gutenberg

Créez-en d'autres depuis **Newsletter › Listes** (nom, description, couleur).

### Gérer les abonnés

**Newsletter › Abonnés**

| Action | Comment |
|--------|---------|
| **Rechercher** | Par email, prénom ou nom |
| **Filtrer** | Par statut (actif / désabonné / en attente) **et par liste** |
| **Ajouter** | Bouton `+ Ajouter` → formulaire manuel, avec cases à cocher pour les listes |
| **Importer** | Bouton `⬆ Import CSV` → format `email,prenom,nom`, avec choix de la liste de destination |
| **Exporter** | Bouton `⬇ Export CSV` → fichier UTF-8 avec BOM, colonne « Listes » incluse |
| **Ajouter à une liste** | Sélection multiple (checkbox) → menu « Ajouter à la liste » |
| **Supprimer** | Unitaire ou en masse (checkbox + bouton Supprimer) |

### Créer et envoyer une campagne

**Newsletter › Nouvelle campagne**

1. **Sujet** : ligne d'objet de l'email
2. **Texte d'aperçu** : texte visible après l'objet (preheader)
3. **Ciblage** : cochez une ou plusieurs listes pour restreindre les
   destinataires (aucune case cochée = tous les abonnés actifs)
4. **Nom / Email expéditeur** : défaut `contact@cie.poivresens.fr`
5. **Contenu HTML** : éditeur WYSIWYG complet
   - Le template par défaut est aux couleurs de la compagnie
   - Variables disponibles : `{prenom}`, `{email}`, `{desinscription}`
6. **Enregistrer brouillon** ou **Envoyer maintenant**

> L'envoi se fait via `wp_mail()`. Pour un volume > 500 abonnés,
> configurez un service SMTP (WP Mail SMTP + Brevo/Mailgun).

### Landing page dédiée — composée dans Gutenberg

Pas de modèle de page ici : la landing page se compose entièrement avec des
blocs Gutenberg natifs (titre, paragraphe, liste, séparateur…), entièrement
éditables en ligne comme n'importe quelle page. Seul le formulaire lui-même
est un shortcode technique, à placer dans un **bloc Shortcode** :

```
[ps_newsletter_liste slug="grand-bal-europe" bouton="Recevez votre pratique"]
```

- `slug` : la liste à laquelle rattacher les inscriptions (doit déjà exister
  dans Newsletter › Listes — sinon l'inscription se fait sans liste).
- `bouton` : texte du bouton (optionnel).
- `placeholder` : texte indicatif du champ e-mail (optionnel).

Un exemple complet de page (à coller dans l'éditeur de code de la page,
menu **⋮ → Éditeur de code**) est fourni à la fin de ce document.

### Statistiques de campagne

**Newsletter › Campagnes** → chaque campagne envoyée affiche :
- Nombre d'e-mails envoyés
- Nombre d'ouvertures (via pixel de tracking 1×1)
- Taux d'ouverture avec barre de progression

### Popup « site en construction »

Un popup discret invite les visiteurs à laisser leur e-mail pendant que le
site est en travaux (fichier `inc/construction-popup.php`). Il est conçu
pour être **non invasif** :

- Il s'affiche **après un court délai** (3,5 s par défaut), pas dès l'arrivée.
- Une fois **fermé**, il ne réapparaît pas avant plusieurs jours (14 j par
  défaut, cookie `ps_construction_seen`).
- Il **ne s'affiche pas** aux personnes déjà inscrites à la newsletter
  (cookie `ps_nl_subscribed`, posé à l'inscription), ni aux administrateurs
  connectés.
- Les inscriptions sont rattachées à la liste **« Site en construction »**
  (créée automatiquement, visible dans Newsletter › Listes).

### Modifier les textes du popup

**Apparence › Personnaliser › « Popup site en construction »** — aucun code
à toucher, avec aperçu en direct (le popup s'affiche en permanence dans
l'aperçu, pour vous permettre de régler les textes).

| Réglage | Rôle |
|---------|------|
| **Afficher le popup** | Case à cocher : décochez pour le désactiver entièrement |
| **Où afficher le popup** | *Page d'accueil uniquement* (par défaut) ou *Toutes les pages*. L'accueil seul évite le doublon avec les pages portant déjà un formulaire, comme « Restons en lien » |
| **Sur-titre** | Petite ligne en capitales au-dessus du titre |
| **Titre** | Début du titre |
| **Fin du titre (en italique coloré)** | Suite du titre, en italique dans la couleur d'accent |
| **Texte d'introduction** | Paragraphe explicatif |
| **Texte du bouton** | Libellé du bouton d'inscription |
| **Mention rassurante** | Petite ligne sous le bouton |
| **Message de remerciement** | Affiché après une inscription réussie |
| **Délai avant apparition** | En secondes (0 = immédiat) |
| **Ne pas réafficher pendant** | En jours, après fermeture par le visiteur |

> Laisser un champ texte vide masque simplement l'élément correspondant
> (sauf le titre et le bouton, nécessaires au fonctionnement).

### Modifier l'e-mail de bienvenue

**Newsletter › Réglages** — l'e-mail envoyé automatiquement à chaque
nouvelle inscription (objet et corps) s'y modifie sans toucher au code.

Variables utilisables dans le message :

| Variable | Remplacée par |
|----------|---------------|
| `{salutation}` | « Bonjour Prénom, » ou « Bonjour, » si le prénom est inconnu |
| `{prenom}` | Le prénom seul (peut être vide) |
| `{email}` | L'adresse de l'inscrit |
| `{desinscription}` | Le lien de désinscription |
| `{site}` | Le nom du site |
| `{url}` | L'adresse du site |

> ⚠️ Conservez `{desinscription}` : le lien de désinscription est une
> obligation légale (RGPD).

La page permet aussi de **désactiver** cet e-mail, de **restaurer le modèle
par défaut**, et d'**envoyer un test** à l'adresse de votre choix.

### SEO — où modifier les titres et descriptions

**Si une extension SEO est installée (Yoast, Rank Math, SEOPress, AIOSEO)**,
le thème s'efface : c'est l'extension qui gère titres, descriptions,
Open Graph et données structurées. Tout se règle donc chez elle.

Avec **Yoast** :

| Ce que vous voulez changer | Où |
|---|---|
| Titre et description d'une page précise | Éditez la page → encadré **Yoast SEO** sous le contenu → *Aperçu Google* |
| Modèles par défaut (toutes les pages, tous les événements) | **Yoast SEO › Réglages › Types de contenu** |
| Identité de la compagnie (nom, logo, réseaux sociaux) | **Yoast SEO › Réglages › Représentation du site** — choisissez *Organisation* |
| Image de partage réseaux sociaux | Encadré Yoast de la page → onglet **Réseaux sociaux** |

> Le thème complète malgré tout les données structurées de Yoast avec les
> **variantes du nom**, sans rien écraser de ce que vous y avez renseigné.

Ces variantes se modifient dans **Apparence › Personnaliser ›
« Référencement (SEO) » › Autres façons d'écrire le nom** — une par ligne.
Elles servent à ce que Google reconnaisse la compagnie quelle que soit
l'orthographe employée (« Poivre & Sens », « Poivre et Sens », « Cie
Poivre & Sens »…). Videz le champ pour n'en déclarer aucune.

**Sans extension SEO**, le thème génère lui-même description, Open Graph et
données structurées à partir du **titre** et de l'**accroche** du site
(Réglages › Général) et des extraits de page.

### Événements dans les résultats Google

Chaque événement publié émet automatiquement des **données structurées
`Event`** : date et heure, lieu et adresse, tarif, lien de billetterie,
et mention « complet » le cas échéant. Google peut alors afficher ces
informations directement dans ses résultats.

Ce bloc est produit **même si Yoast est installé** : aucune extension SEO,
y compris en version payante, ne génère de schéma d'événement pour un type
de contenu personnalisé — il n'y a donc pas de doublon.

Rien à configurer : remplissez simplement les champs du bloc **Détails de
l'événement**. Quelques précisions utiles :

- **Sans date**, aucun schéma n'est émis (Google refuserait un événement
  sans date) ;
- le **tarif** est interprété quand c'est possible (`12€` → 12, `gratuit`
  → 0). Un texte non chiffrable (« sur réservation ») n'invente aucun prix ;
- une **heure de fin antérieure à l'heure de début** est comprise comme un
  passage après minuit ;
- **Complet** coché ⇒ l'événement est annoncé comme complet.

Vous pouvez contrôler le rendu avec le
[test des résultats enrichis de Google](https://search.google.com/test/rich-results)
en y collant l'adresse d'un événement.

### Indexation Google des pages utilitaires

Les URL à paramètre qui affichent une variante d'une page existante sont
automatiquement marquées **`noindex`** par le thème, pour éviter que Google
les signale comme doublons :

- `?mailpoet_page=…` et `?mailpoet_router` — gestion d'abonnement et
  désinscription MailPoet ;
- `?ps_popup=1` — affichage forcé du popup (mode test).

Ces pages restent accessibles normalement aux visiteurs ; seuls les moteurs
de recherche sont invités à ne pas les indexer.

### « Je ne vois pas le popup »

C'est presque toujours normal — le popup s'efface volontairement dans
plusieurs situations. Par ordre de fréquence :

1. **Vous n'êtes pas sur la page d'accueil.** Par défaut le popup ne
   s'affiche que sur l'accueil (réglage « Où afficher le popup »).
2. **Vous êtes connecté·e à WordPress.** Le popup ne s'affiche jamais aux
   personnes qui peuvent éditer le site (vous), pour ne pas gêner le travail.
3. **Vous l'avez déjà fermé.** Le cookie `ps_construction_seen` le masque
   pendant le nombre de jours réglé.
4. **Vous êtes déjà inscrit·e** à la newsletter depuis ce navigateur
   (cookie `ps_nl_subscribed`) : il ne s'affiche plus jamais.
5. **Un cache** (plugin de cache, CDN) sert une version antérieure de la page.

**Pour le voir immédiatement, quel que soit le cas :** ajoutez `?ps_popup=1`
à l'URL, par exemple `https://cie.poivresens.fr/?ps_popup=1`. Le popup
s'ouvre aussitôt, sans délai, en ignorant les cookies — sans rien changer
pour vos visiteurs.

Sinon, testez simplement en **navigation privée** (vous n'y êtes ni
connecté·e ni porteur des cookies).

---

## 📅 Calendrier en mode liste

La page `/evenements/` affiche un calendrier **en mode liste** :
- Événements groupés par **mois** (couleur ambre pour le mois courant)
- Chaque événement : jour + barre verticale + titre + heure + lieu + prix + bouton réserver
- **Filtres** : par type d'événement, par ville, inclure les événements passés
- Les événements passés s'affichent en opacité réduite

---

## 🎨 Personnalisation

### Couleurs (variables CSS)

Modifiez `assets/css/theme.css`, bloc `:root` :

```css
--or:    #c28b36;  /* Ambre doré — couleur principale */
--rouge: #9e3710;  /* Rouge brique — accent */
--creme: #ece3cb;  /* Crème — textes */
--noir:  #080705;  /* Fond principal */
```

### Polices

Deux polices Google Fonts :
- `Cormorant Garamond` — titres élégants
- `Inter` — corps de texte lisible

Modifiez dans `functions.php`, fonction `ps_enqueue`.

---

## ⚙️ Configuration SMTP recommandée

Pour l'envoi fiable des emails :

1. Installez le plugin **WP Mail SMTP**
2. Configurez avec **Brevo** (ex-Sendinblue) — gratuit jusqu'à 300 emails/jour
   - SMTP Host : `smtp-relay.brevo.com`
   - Port : `587`
   - Email d'envoi : `contact@cie.poivresens.fr`

---

## 📁 Structure du thème

```
poivre-sens/
├── style.css                   Métadonnées du thème
├── functions.php               CPT événements/galerie, meta boxes, AJAX newsletter
├── front-page.php              Page d'accueil one-page
├── header.php                  Navigation fixe
├── footer.php                  Pied de page
├── single-evenement.php        Fiche événement
├── archive-evenement.php       Calendrier liste /evenements/
├── single.php / page.php       Templates génériques
├── assets/
│   ├── css/theme.css           Toutes les styles
│   └── js/theme.js             Navigation + animations
├── images/
│   └── galerie-0N-xxx.svg      Placeholders galerie
├── inc/
│   ├── admin-options.php           Réglages › Contenu du site (textes éditables)
│   └── newsletter-admin.php    Interface MailPoet-like complète
└── template-parts/
    ├── calendar-list.php       Composant calendrier liste
    └── newsletter-form.php     Formulaire abonnement front-end
```

---

## 🆘 Support

- **Email** : `contact@cie.poivresens.fr`
- **Site** : `https://cie.poivresens.fr`
- **Dépôt** : `github.com/labodezao/CompaniePoivreSens`
