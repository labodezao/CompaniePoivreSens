# Newsletters par article

Un mail par article du blog (89 au total), à piocher au fil de l'eau — pas
de calendrier imposé, contrairement aux 12 éditions mensuelles de
`../library/`.

## Où les écrire et les modifier

**Depuis l'admin WordPress** : Newsletter → Bibliothèque.

- « Nouveau cadeau » écrit un texte qui n'existe que là, en base. C'est le
  chemin normal pour un nouveau mail — pas besoin de toucher au dépôt.
- « Modifier » sur un texte venu d'un fichier enregistre la version
  retouchée par-dessus ; « Rétablir » redonne celle du fichier.

Les fichiers de ce dossier sont la **valeur par défaut** des textes livrés
avec le plugin : ils fournissent le contenu tant que personne ne l'a
retouché dans l'admin.

## Ce que contient chaque fichier

```
<!--meta
{
  "titre":     "Nom interne de la campagne",
  "objet":     "L'objet de l'email",
  "article":   "slug-de-larticle-source",
  "post_slug": "Slug WordPress réel de l'article",
  "cadeau":    "Ce que le lecteur repart avec — 1 phrase"
}
meta-->

<p>Bonjour {{prenom}},</p>
…
<p>— Ewen</p>
```

- Le **corps** est le mail : autonome, il se lit seul sans cliquer, avec un
  seul lien vers l'article en fin de message. Variable : `{{prenom}}`.
- `cadeau` est une note de rédaction, pas lue par le code : ce que le mail
  donne concrètement, pour éviter les envois qui disent seulement « j'ai
  publié un article ».
- `article` et `post_slug` documentent l'article d'origine. `post_slug` est
  souvent encodé quand le titre contenait un emoji
  (`%f0%9f%8c%99-cortisol-…`), c'est la forme que WordPress stocke.

## Envoyer

**À la main.** Newsletter → Bibliothèque → « Créer la campagne » crée un
brouillon. Tu le relis, tu prévisualises, tu envoies.

**Automatiquement, à la création.** Si « Nouveau cadeau dans la
bibliothèque » est activé (Newsletter → Automatisations), tout texte qui
**apparaît** déclenche sa campagne — qu'il ait été écrit dans l'admin ou
livré par une mise à jour du plugin. En mode brouillon (recommandé) elle
attend dans Campagnes ; en mode envoi immédiat elle part sans relecture.

Deux choses à savoir sur ce mécanisme :

- Les textes **déjà présents** au moment où tu actives ne déclenchent rien.
  Le module retient la liste de ce qu'il connaît, et ne réagit qu'à ce qui
  s'y ajoute ensuite. Sans cela, activer l'option enverrait une centaine de
  mails d'un coup.
- Un texte ajouté pendant que l'option est **désactivée** est enregistré
  comme connu : il ne partira pas rétroactivement à la réactivation.
