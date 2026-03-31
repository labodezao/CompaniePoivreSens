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

## ✏️ Modifier le contenu de la page d'accueil

Allez dans **Réglages › 🌶 Contenu du site**. Vous trouverez sur une seule page tous les textes éditables, organisés par section. Modifiez ce que vous voulez, puis cliquez **Enregistrer les réglages** — c'est tout.

| Section | Ce que vous pouvez modifier |
|---------|---------------------------|
| **① Hero** | Sur-titre, disciplines, texte du bouton, citation, texte d'intro |
| **② Manifeste** | Titre, mots en italique dorés, 3 paragraphes |
| **③ Ambre** | Nom, rôle, initiale, 2 paragraphes de bio, mots-clés |
| **④ Ewen** | Nom, rôle, initiale, 2 paragraphes de bio, mots-clés |
| **⑤ Citation** | 3 lignes de la citation + source |
| **⑥ Contact** | Nom compagnie, statut, direction, emails, site, note réseaux |
| **⑦ Footer** | 2 lignes du pied de page |

> **Galerie et Événements** se gèrent séparément via leurs menus dédiés dans l'admin
> (la page d'options contient des liens directs vers ces sections).

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
