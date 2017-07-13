# sp2wp

Pour convertir une ou plusieurs rubriques de Spip en site WordPress autonome.

## Problèmes

- Convertir la syntaxe de SPIP en code HTML.
- Convertir les liens internes (et générer des redirections 301)
- Convertir les modèles (images, diaporama...)
- conserver les dates de modifications des fichiers

## Pris en compte

- auteurs => auteurs (pas de reprise du mot de passe)
- rubriques => catégories
- articles numérotés => pages
- autres articles => posts
- mots clés => taxonomie de type catégorie (arborescente)
- documents => fichiers 
- modèles et liens internes : [Article->art12], [Rubrique->rubrique99], <doc34>, <img12> ...
- conversion mise en page (gras, italique, tableaux, listes)

## Pas pris en compte 

- Accès à des talbles personnalisées de la base de données (exemple plugin newsletter)
- multilinguismes
- modèles SPIP perso (juste diaporama ou galerie comme exemple)
- pas de support d'articles rédigés à plusieurs

## Idée

- Faire deux passes, l'une pour détecter les documents utilisés, ... , l'autre pour générer le code WordPress.
- Appeler les fonctions de SPIP (Global.php) de conversion SPIP vers HTML, dans lesquelles on remplace les user_call_func vers les fonctions générant le code WordPress
