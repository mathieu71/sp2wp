# sp2wp

Pour convertir une ou plusieurs rubriques de Spip en site WordPress autonome.

## Problèmes
- Convertir la syntaxe de SPIP en code HTML.
- Convertir les liens internes (et générer des redirections 301)
- Convertir les modèles (images, diaporama...)

## Pas pris en compte 
- Accès à des talbles personnalisées de la base de données (exemple plugin newsletter)
- multilinguismes
- modèles SPIP perso (juste diaporama ou galerie comme exemple)

## Idée :
- Faire deux passes, l'une pour détecter les documents utilisés, ... , l'autre pour générer le code WordPress.
- Appeler les fonctions de SPIP (Global.php) de conversion SPIP vers HTML, dans lesquelles on remplace les user_call_func vers les fonctions générant le code WordPress
