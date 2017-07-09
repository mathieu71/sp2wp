<?php

if (defined('__MOTCLES__')) {
    return;
}
define('__MOTCLES__', 1);

class MotsCles
{
    public static $motcles;
    public static function add($id)
    {
        if (is_null(self::$motcles)) {
            self::$motcles = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
            }
        } else {
            if (!array_key_exists($id, self::$motcles)) {
                self::$motcles [$id] = new MotCle($id);
            }
        }
    }
    public static function to_wp($site)
    {
        if (is_null(self::$motcles)) {
            return;
        }
        foreach (self::$motcles as $mot) {
            $mot->to_wp($site);
        }
    }
    public static function update_refs($site)
    {
        if (is_null(self::$motcles)) {
            return;
        }
        foreach (self::$motcles as $mot) {
            $mot->update_refs($site);
        }
    }
    public static function get_mot($id)
    {
        if (is_null(self::$motcles)) {
            return;
        }
        if (array_key_exists($id, self::$motcles)) {
            return self::$motcles[$id];
        }

        return;
    }
    public static function attacher($site)
    {
        if (!is_null(Articles::$articles)) {
            $articles = array_keys(Articles::$articles);
            $les_articles = ' ('.implode(',', $articles).') ';

            $sql = 'SELECT id_mot AS mot,id_article AS art FROM '.Config::$spip_prefix."mots_articles having art IN $les_articles"; // faire la même chose pour les rubriques...
        //echo $sql;
        $stmt = Config::$db_spip->prepare($sql);
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();

                $row = $res->fetch_assoc();
                while ($row) {
                    if ($mot = self::get_mot($row['mot'])) {
                        $taxonomy = $mot->mot();
                        if ($row['art']) {
                            if ($art = Articles::get_art($row['art'])) {
                                $objet = $art->wp_id();
                                $sql1 = 'INSERT INTO wp'.$site.'_term_relationships(object_id, term_taxonomy_id) VALUES (?,?)';
                                $stmt = Config::$db_wp->prepare($sql1);
                                $stmt->bind_param('dd', $objet, $taxonomy);
                                $stmt->execute();

                                if (Config::$db_wp->error) {
                                    Erreur::error("Association mot-clé <-> article : $sql ".Config::$db_wp->error);
                                }
                            }
                        }
                    }
                    $row = $res->fetch_assoc();
                }
            }
        } else {
            echo 'pas de mot clé'.PHP_EOL;
        }
    }
}
