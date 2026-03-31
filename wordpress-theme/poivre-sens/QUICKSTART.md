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

### Gérer les abonnés

**Newsletter › Abonnés**

| Action | Comment |
|--------|---------|
| **Rechercher** | Par email, prénom ou nom |
| **Filtrer** | Par statut (actif / désabonné / en attente) |
| **Ajouter** | Bouton `+ Ajouter` → formulaire manuel |
| **Importer** | Bouton `⬆ Import CSV` → format `email,prenom,nom` |
| **Exporter** | Bouton `⬇ Export CSV` → fichier UTF-8 avec BOM |
| **Supprimer** | Unitaire ou en masse (checkbox + bouton Supprimer) |

### Créer et envoyer une campagne

**Newsletter › Nouvelle campagne**

1. **Sujet** : ligne d'objet de l'email
2. **Texte d'aperçu** : texte visible après l'objet (preheader)
3. **Nom / Email expéditeur** : défaut `contact@cie.poivresens.fr`
4. **Contenu HTML** : éditeur WYSIWYG complet
   - Le template par défaut est aux couleurs de la compagnie
   - Variables disponibles : `{prenom}`, `{email}`, `{desinscription}`
5. **Enregistrer brouillon** ou **Envoyer maintenant**

> L'envoi se fait via `wp_mail()`. Pour un volume > 500 abonnés,
> configurez un service SMTP (WP Mail SMTP + Brevo/Mailgun).

### Statistiques de campagne

**Newsletter › Campagnes** → chaque campagne envoyée affiche :
- Nombre d'e-mails envoyés
- Nombre d'ouvertures (via pixel de tracking 1×1)
- Taux d'ouverture avec barre de progression

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
