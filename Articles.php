<?php

if (defined('__ARTICLES__')) {
    return;
}
define('__ARTICLES__', 1);

class Articles
{
    public static $articles = [];
    /**
     * Ajout d'un article dans la liste.
     */
    public static function add($id)
    {
        if (is_null(self::$articles)) {
            self::$articles = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
                if (self::$articles[$i]->is_redirection()) {
                    self::remove($i);
                }
            }
        } else {
            if (!array_key_exists($id, self::$articles)) {
                self::$articles [$id] = new Article($id);
                if (self::$articles[$id]->is_redirection()) {
                    self::remove($id);
                }
            }
        }
    }
    public static function remove($id)
    { // en cas d'erreur
        unset(self::$articles[$id]);
    }
    /**
     * Création de page ou post ToWordPress
     * et liens.
     */
    public static function to_wp($site)
    {
        foreach (self::$articles as $art) {
            $art->to_wp($site);
        }
    }
    /**
     * mise à jour du contenu.
     */
    public static function update_refs($site)
    {
        foreach (self::$articles as $art) {
            $art->update_refs($site);
        }
    }
    public static function get_art($id)
    {
        if (Config::$DETECT) {
            return; /* article pas encore créé */
        }
        if (!$id) {
            Erreur::error("erreur d'id");

            return;
        }
        if (array_key_exists($id, self::$articles)) {
            return self::$articles [$id];
        }

        return;
    }
    public static function get_spip_title_url($id)
    {
        /* pour les références à des articles externes */
        $sql = 'SELECT titre, (SELECT url FROM '.Config::$spip_prefix.'urls WHERE '.Config::$spip_prefix.'urls.id_objet='.Config::$spip_prefix."articles.id_article AND type='article' ORDER BY date DESC limit 1) as url FROM ".Config::$spip_prefix.'articles WHERE id_article=?';

        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row['url'] == '') {
            $row['url'] = "spip.php?page=article&id_article=$id";
        }
        $row['url'] = Config::$spip_base_url.$row['url'];

        return $row;
    }
    public static function dump_url()
    {
        $out = '';
        foreach (self::$articles as $key => $art) {
            $sql = 'select url from '.Config::$spip_prefix."urls where type='article' and id_objet='.$key.' order by date desc limit 1;";
            if ($result = mysqli_query(Config::$db_spip, $sql)) {
                $row = mysqli_fetch_row($result);
                $out .= 'rewrite '.$row[0].' '.Config::$wp_base_url.'?p='.$art->wp_id()." redirect;\n";
            }
        }

        return $out;
    }
}
