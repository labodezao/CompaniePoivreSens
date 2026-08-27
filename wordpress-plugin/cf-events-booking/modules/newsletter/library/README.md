# Newsletters annuelles — modèles éditables

Une édition par fichier `NN-mois.html`, utilisées par **Newsletter →
Bibliothèque → Modèles annuels** (et par « Programmer toute l'année »).

## Deux façons de modifier un texte

1. **Depuis l'admin** (le plus simple) : Newsletter → Bibliothèque →
   Modifier. Le texte enregistré remplace celui du fichier, et « Rétablir »
   redonne la version d'origine. Aucune manipulation de fichiers.
2. **Dans ce dossier** : les fichiers servent de valeur par défaut, utilisée
   tant que l'édition n'a pas été retouchée dans l'admin.

L'ordre des fichiers (`01-…`, `02-…`) détermine le mois d'envoi lors du
« Programmer toute l'année » : garder les douze, dans l'ordre.

## Format d'un fichier
```
<!--meta
{
  "mois": "Janvier — Se libérer des schémas",
  "objet": "L'objet de l'email"
}
meta-->

<p>Bonjour {{prenom}},</p>
<p>… le corps en HTML simple …</p>
<p>— Ewen</p>
```

- `mois` : libellé interne affiché dans l'admin (titre de la campagne).
- `objet` : objet de l'email.
- Le corps accepte le HTML simple. Variable disponible : `{{prenom}}`.
- L'ordre d'affichage suit le numéro du fichier (`01-…`, `02-…`).

## Après modification
Committer + pousser : le fichier est déployé avec le plugin (FTP), et
« Modèles annuels » lit automatiquement la nouvelle version.
