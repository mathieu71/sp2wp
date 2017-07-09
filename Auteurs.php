<?php

if (defined('__AUTEURS__')) {
    return;
}
define('__AUTEURS__', 1);

class Auteurs
{
    private static $auteurs;

    public static function add($id)
    {
        if (is_null(self::$auteurs)) {
            self::$auteurs = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
            }
        } else {
            if (!array_key_exists($id, self::$auteurs)) {
                self::$auteurs [$id] = new Auteur($id);
            }
        }
    }

    public static function to_wp($site)
    {
        if (is_null(self::$auteurs)) {
            return;
        }
        foreach (self::$auteurs as $auteur) {
            $auteur->to_wp($site);
        }
    }

    public static function prolifique($articles, $auteurs)
    {
        if (!is_array($articles)) {
            return;
        }
        if (count($articles) == 0) {
            return 1; // si aucun article publié, on retourne le super admin...
        }
        if (is_array($auteurs) && count($auteurs)) {
            $q = ' AND id_auteur IN ('.implode(',', $auteurs).') ';
        } else {
            $q = '';
        }
        switch (Config::$spip_version) {
            case 3: {
                    $sql = 'SELECT COUNT(id_auteur) a,id_auteur FROM '.Config::$spip_prefix."auteurs_liens WHERE objet='article' AND ".'id_objet IN ('.implode(',', $articles).") $q"." GROUP BY id_objet ORDER BY a DESC LIMIT 1;\n";
                    break;
                }
            case 2: {
                    $sql = 'SELECT COUNT(id_auteur) a,id_auteur FROM '.Config::$spip_prefix.'auteurs_articles WHERE '.'id_article IN ('.implode(',', $articles).") $q"." GROUP BY id_auteur ORDER BY a DESC LIMIT 1;\n";
                    break;
                }
        }
        $res = Config::$db_spip->query($sql);
        $row = $res->fetch_assoc();

        if (array_key_exists($row ['id_auteur'], self::$auteurs)) {
            return self::$auteurs [$row ['id_auteur']]->wp_id();
        } else {
            Erreur::error("Les auteurs de l'article n'existent plus ...");

            return self::prolifique($articles, array());
        }
    }
}
