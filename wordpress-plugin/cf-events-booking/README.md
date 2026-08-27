# CF Événements & Réservations

Plugin WordPress léger remplaçant **The Events Calendar + SSA Booking** — optimisé pour OVH offre perso, compatible avec la plupart des thèmes WP standards.

## Fonctionnalités

| Fonctionnalité | Détail |
|---|---|
| 🗓️ Événements | CPT dédié avec date, lieu, prix, places max, animateur, lien visio |
| 📂 Catégories & étiquettes | Taxonomies hiérarchiques avec couleurs personnalisables |
| ✅ Réservation 2 clics | Clic sur « Réserver » → modal → soumettre → confirmé (AJAX) |
| 💾 Pré-remplissage | Nom / email mémorisés dans localStorage (retour utilisateur) |
| 👥 Liste d'attente | Automatique quand l'événement est complet + promotion auto |
| 📅 Vue calendrier | Grille mensuelle CSS (zéro librairie externe) |
| 📋 Liste événements | Cartes responsives avec filtres par catégorie |
| 🗓️ Mes réservations | Recherche par email + annulation par token |
| ✉️ Emails | Confirmation + notification admin + annulation + rappel + suivi |
| ⏰ Rappels automatiques | Email X heures avant l'événement (WP Cron) |
| 💌 Suivi post-événement | Email Y heures après la fin (WP Cron) |
| 📊 Admin — Réservations | Tableau, filtres, changement statut inline, export CSV |
| ➕ Admin — Ajout manuel | Formulaire pour créer une réservation depuis l'admin |
| ☑️ Admin — Actions en masse | Changer statut / supprimer plusieurs réservations d'un coup |
| 📈 Admin — Statistiques | Cartes résumé, graphique mensuel, top événements, taux remplissage |
| 📅 Admin — Calendrier | Tableau hebdomadaire des réservations (lun→dim) avec panneau détail |
| 📊 Widget tableau de bord | Vue rapide des événements et réservations sur le dashboard WP |
| ⚙️ Paramètres | Email admin, expéditeur, confirmation, RGPD, rappel, suivi, redirection |
| 📅 iCal | Export ICS de tous les événements + pièce jointe dans l'email de confirmation |
| 🔗 REST API | Endpoints publics `/cfeb/v1/events` et `/cfeb/v1/venues` |
| 🏟️ Lieux (Venues) | CPT dédié avec adresse, téléphone, lien Google Maps |
| 🔖 Duplication d'événement | Action en liste « Dupliquer » pour cloner rapidement |
| 🌐 OpenGraph | Balises OG automatiques sur les pages événement |
| 📝 JSON-LD | Données structurées Schema.org Event |
| 📦 Widget sidebar | Prochains événements dans une barre latérale |

## Installation FTP

```
wp-content/
└── plugins/
    └── cf-events-booking/
        ├── cf-events-booking.php    ← fichier principal
        ├── includes/
        │   ├── class-cf-cpt.php           ← CPT, meta boxes, taxonomies
        │   ├── class-cf-booking.php       ← CRUD réservations
        │   ├── class-cf-admin.php         ← Pages admin + widget dashboard
        │   ├── class-cf-stats.php         ← Page Statistiques
        │   ├── class-cf-frontend.php      ← Shortcodes + gabarits
        │   ├── class-cf-ajax.php          ← Handlers AJAX
        │   ├── class-cf-email.php         ← Emails HTML
        │   ├── class-cf-ical.php          ← Export iCal
        │   ├── class-cf-jsonld.php        ← Schema.org JSON-LD
        │   ├── class-cf-reminders.php     ← Rappels + suivis (WP Cron)
        │   ├── class-cf-opengraph.php     ← Balises OpenGraph
        │   ├── class-cf-rest-api.php      ← REST API publique
        │   └── class-cf-widget.php        ← Widget sidebar
        ├── assets/
        │   ├── css/
        │   │   ├── frontend.css
        │   │   └── admin.css
        │   └── js/
        │       ├── frontend.js
        │       └── admin.js
        └── templates/
            ├── single-cf-event.php
            ├── archive-cf-event.php
            └── single-cf_venue.php
```

**Étapes :**
1. Uploader le dossier `cf-events-booking/` dans `wp-content/plugins/`
2. Activer dans **WordPress → Extensions**
3. La table `wp_cf_bookings` est créée automatiquement à l'activation

## Shortcodes

| Shortcode | Description |
|---|---|
| `[cf_events]` | Liste des prochains événements |
| `[cf_events nombre="6"]` | Limiter le nombre affiché |
| `[cf_events categorie="jam"]` | Filtrer par slug de catégorie |
| `[cf_events vue="calendrier"]` | Vue calendrier mensuel |
| `[cf_calendrier]` | Alias vue calendrier |
| `[cf_mes_reservations]` | Espace « mes réservations » avec recherche par email |
| `[cf_rdv type="slug-ou-id"]` | Widget de prise de rendez-vous par créneau |
| `[cf_rdv type="slug-a,slug-b"]` | Cumuler plusieurs types dans un seul widget (séparateur `,` ou `+`) |
| `[cf_rdv type="slug" vue="liste"]` | Forcer la vue (`liste` ou `semaine`) |
| `[cf_rdv type="a,b" titre="Ateliers"]` | Titre personnalisé (`titre="none"` pour le masquer) |
| `[cf_rdv type="slug" nombre="20"]` | Plafond de dates affichées en vue liste (défaut 8, max 50) |
| `[cf_rdv_onglets type="a, b, c"]` | Plusieurs types de RDV en onglets (un seul visible à la fois) plutôt qu'empilés — voir ci-dessous |
| `[cf_filtres]` | Filtres frontend par type/catégorie/date |
| `[cf_lieux]` | Liste des lieux (`cf_venue`) |
| `[cf_fiche_intake]` | Formulaire en ligne de la fiche thèmes (premier RDV) — voir modules/fiche-intake |

Champs entièrement personnalisables depuis **CF Réservations → Fiches thèmes →
Paramètres** : ajouter/renommer/retirer des sections et des champs (texte
court, texte long, case à cocher), sans toucher au code. Retirer un champ
ne supprime jamais les réponses déjà reçues, il disparaît juste du
formulaire ; renommer le libellé d'un champ existant n'affecte pas les
données déjà enregistrées sous ce champ. Bouton dédié pour revenir aux
champs par défaut (ceux du PDF original).
| `[cf_temoignage_form]` | Formulaire public de dépôt d'un témoignage écrit |
| `[cf_temoignages]` | Témoignages du site, en citations (statut Publié + consentement) |
| `[cf_temoignages_google]` | Avis Google recopiés à la main, en bandeau défilant — voir ci-dessous |

### Témoignages — admin unifié

Admin → CF Réservations → **Témoignages** : un seul écran pour les témoignages
soumis via `[cf_temoignage_form]` et pour les avis Google recopiés à la main
(plus besoin de coller du HTML dans l'éditeur de page). Chaque témoignage a :

- **Source** : « Site » (formulaire) ou « Avis Google ».
- **Note** (1 à 5, si source Google).
- **Ordre d'affichage** (bandeau `[cf_temoignages_google]` uniquement).
- **Autorisé à être affiché** — case à cocher, en plus du statut « Publié » de
  l'article : les deux sont nécessaires pour qu'un témoignage apparaisse sur
  le site (double verrou volontaire).

Pour ajouter un avis Google : CF Réservations → Témoignages → Ajouter,
choisir Source = « Avis Google », coller le texte, cocher Publication, régler
le statut sur Publié. Il apparaît alors dans `[cf_temoignages_google]` (même
rendu visuel `.ccf-marquee` que précédemment, juste géré depuis l'admin).

### [cf_rdv_onglets] — plusieurs types de RDV sans empiler les widgets

Sur une page qui propose plusieurs types de rendez-vous (individuel, groupe,
mouvement, génogramme, soirée Pleine Vie…), empiler un `[cf_rdv]` par type
oblige à faire défiler toute la page — et chaque widget vide ("Aucun créneau
cette semaine") ajoute à la confusion avant même d'atteindre celui qui a des
dates ouvertes. `[cf_rdv_onglets]` regroupe plusieurs types derrière une
barre d'onglets : un seul calendrier visible à la fois, changement instantané
(pas de rechargement).

```
[cf_rdv_onglets type="seance-individuelle:Séance individuelle, constellations-de-groupes:Constellations de groupes, mouvement:Mouvement, ateliers-genogrammes:Génogramme, soiree-pleine-vie:Soirée Pleine Vie"]
```

- Séparateur `,` entre types ; `slug:Libellé` pour personnaliser le nom de
  l'onglet (sinon le titre du type est repris).
- `vue` et `nombre` s'appliquent à tous les onglets, comme sur `[cf_rdv]`.
- Un seul type fourni : rend directement le widget, sans barre d'onglets.
- Un lien direct `#cf-rdv-<slug>` (voir ci-dessous) ouvre automatiquement
  le bon onglet avant de défiler jusqu'à lui.

### Lien direct vers un type de RDV (ex. depuis un email MailPoet)

Chaque widget `[cf_rdv]` porte une ancre stable `#cf-rdv-<slug-du-type>` (ou
`#cf-rdv-<slug-a>-<slug-b>` si plusieurs types sont cumulés). Un lien du
type `https://soins.ewendaviau.com/prendre-rdv/#cf-rdv-soiree-pleine-vie`
fait défiler directement jusqu'au bon widget et le surligne brièvement —
pratique pour un lien MailPoet vers un rendez-vous précis (ex. une soirée
de présentation) sans faire chercher le visiteur sur toute la page.

### Types de RDV — récurrence de la disponibilité hebdomadaire

Sur chaque type de rendez-vous (CF Réservations → Types RDV → modifier →
meta box « Disponibilité »), en plus des plages horaires par jour, un réglage
« Récurrence » permet de limiter quelles semaines sont ouvertes :

- **Toutes les semaines** (par défaut — comportement inchangé pour tout type déjà configuré).
- **Semaines paires / impaires** — une semaine sur deux, sur le numéro de semaine ISO.
- **Toutes les X semaines** — à partir d'une date de référence choisie.
- **Semaines spécifiques** — cases à cocher pour les numéros de semaine ISO actifs (1-53, se répètent chaque année).

Les « dates spécifiques » (créneaux ponctuels ajoutés plus bas dans la même
meta box) restent toujours actives, indépendamment de ce réglage.

## Workflow réservation (2 clics)

1. **Clic 1** — L'utilisateur clique sur **« Réserver »** sur la carte ou la page événement
   → La modal s'ouvre avec les champs pré-remplis si déjà venu (localStorage)
2. **Clic 2** — L'utilisateur clique sur **« Confirmer ma réservation →»**
   → Réservation enregistrée, email de confirmation envoyé, message de succès affiché

**Aucun rechargement de page**, tout se passe en AJAX.

## Administration

Allez dans **Événements** dans le menu WordPress :

### Sous-menus disponibles

| Sous-menu | Description |
|---|---|
| **Tous les événements** | Créer / modifier / supprimer des événements |
| **Réservations** | Liste complète avec filtres, statuts, ajout manuel, actions en masse, export CSV |
| **Calendrier** | Tableau hebdomadaire lun→dim des réservations avec navigation et panneau détail |
| **📊 Statistiques** | Cartes résumé, graphique mensuel, top événements, taux de remplissage |
| **Paramètres** | Configurer les emails, options et notifications |

### Page Réservations — fonctionnalités

#### Filtres
- Filtrer par événement
- Filtrer par statut

#### Actions en masse
1. Cocher les réservations souhaitées (ou « tout sélectionner »)
2. Choisir une action dans le menu déroulant :
   - ✅ Marquer Confirmé
   - ✔️ Marquer Présent
   - 🚫 Marquer Absent
   - ❌ Marquer Annulé
   - ⏳ Liste d'attente
   - 🗑️ Supprimer
3. Cliquer **Appliquer**

#### Ajouter une réservation manuellement
1. Cliquer sur **➕ Ajouter une réservation** (en haut de la page)
2. Remplir le formulaire : événement, prénom, nom, email, téléphone, places, statut, notes
3. Cocher / décocher l'envoi de l'email de confirmation
4. Cliquer **Enregistrer la réservation**

#### Changement de statut inline
Modifier directement le statut dans la liste sans rechargement de page.

### Page Statistiques — indicateurs

- **Cartes résumé** : total réservations, places confirmées, ce mois-ci (avec variation vs mois précédent), liste d'attente, annulées, présents, CA estimé
- **Graphique mensuel** : réservations des 6 derniers mois (barres CSS, zéro dépendance)
- **Top 5 événements** : classement par nombre de places confirmées
- **Taux de remplissage** : pour chaque événement à venir, jauge visuelle (vert < 50%, orange 50-80%, rouge > 80%)

### Widget tableau de bord

Le widget **CF Événements — Vue d'ensemble** est automatiquement ajouté au tableau de bord WordPress. Il affiche :
- Nombre d'événements à venir
- Événements aujourd'hui
- Réservations cette semaine
- Total réservations confirmées
- Liste des 4 prochains événements avec dates et compteurs
- Raccourcis vers Réservations, Statistiques, + Événement

### Statuts de réservation disponibles

| Statut | Code | Description |
|---|---|---|
| ✅ Confirmé | `confirme` | Réservation validée |
| ⏳ Liste d'attente | `liste_attente` | Événement complet, en attente |
| ✔️ Présent | `present` | Participant marqué présent |
| 🚫 Absent | `absent` | Participant marqué absent |
| ❌ Annulé | `annule` | Réservation annulée |

## Paramètres disponibles

| Paramètre | Description |
|---|---|
| Email admin | Destinataire des notifications de nouvelle réservation |
| Emails admin supplémentaires | Adresses CC (virgule-séparées) |
| Nom expéditeur | Affiché dans les emails envoyés |
| Email expéditeur | Adresse `From:` des emails |
| Message confirmation | Texte de l'email de confirmation (variables : `{prenom}` `{nom}` `{email}` `{evenement}` `{date}` `{lieu}`) |
| Mention RGPD | Texte affiché sous le formulaire de réservation |
| Téléphone obligatoire | Rend le champ téléphone requis |
| Double réservation | Autoriser plusieurs réservations par email |
| Liste d'attente | Activer quand l'événement est complet |
| Rappel avant événement | X heures avant → email de rappel automatique (0 = désactivé) |
| Suivi après événement | Y heures après → email de suivi automatique (0 = désactivé) |
| Message de suivi | Corps de l'email post-événement (variables : `{prenom}` `{nom}` `{evenement}` `{date}`) |
| Nom du calendrier iCal | Nom affiché dans les applications calendrier |
| URL de redirection | Redirection X secondes après confirmation (optionnel) |

## REST API

Namespace : `cfeb/v1`

| Endpoint | Méthode | Description |
|---|---|---|
| `/cfeb/v1/events` | GET | Liste des événements |
| `/cfeb/v1/events/{id}` | GET | Détail d'un événement |
| `/cfeb/v1/venues` | GET | Liste des lieux |
| `/cfeb/v1/venues/{id}` | GET | Détail d'un lieu |

### Paramètres de `/cfeb/v1/events`

| Paramètre | Type | Description |
|---|---|---|
| `per_page` | int | Nombre de résultats (max 100, défaut 10) |
| `page` | int | Page de pagination (défaut 1) |
| `categorie` | string | Slug de catégorie (ou plusieurs séparés par virgule) |
| `tag` | string | Slug d'étiquette |
| `featured` | string | `"1"` pour les événements mis en avant |
| `passe` | boolean | `true` pour les événements passés |
| `date_debut` | string | Date de début (format `YYYY-MM-DD HH:MM:SS`) |
| `date_fin` | string | Date de fin (format `YYYY-MM-DD HH:MM:SS`) |

## iCal

- **Flux global** : `https://votre-site.fr/?cfeb_ical=1` → télécharge tous les événements à venir en `.ics`
- **Événement individuel** : lien iCal disponible sur chaque page événement
- **Email de confirmation** : pièce jointe `.ics` automatique

## Compatibilité

- WordPress 6.0+
- PHP 7.4+
- Tous thèmes WP standards
- OVH hébergement mutualisé (zéro dépendance externe, assets chargés conditionnellement)

## Optimisation performance (OVH)

- CSS/JS chargés **uniquement** sur les pages contenant le shortcode ou les pages événements
- Zéro librairie externe (jQuery non requis)
- Requêtes DB minimales avec index sur `event_id`, `email`, `statut`, `token`
- `no_found_rows: true` sur les WP_Query (évite le `COUNT(*)` inutile)
- `update_post_term_cache: true` pour précharger les termes en une requête

## Surcharge des gabarits via le thème

Pour personnaliser l'affichage, copiez dans votre thème :

```
wp-content/themes/votre-theme/
├── single-cf_event.php     ← page événement individuel
└── archive-cf_event.php    ← liste / archive CPT
```

## Annulation par email

L'email de confirmation contient un lien d'annulation unique (token) :
```
https://votre-site.fr/evenements/nom-evenement/?cfeb_annuler=1&cfeb_token=xxxx
```
ou en pointant vers la page portant le shortcode `[cf_mes_reservations]`.
