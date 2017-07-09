<?php

if (defined('__BREVES__')) {
    return;
}
define('__BREVES__', 1);

class Breves
{
    public static $breves = [];
    /**
     * Ajout d'une brève dans la liste.
     */
    public static function add($id)
    {
        if (is_null(self::$breves)) {
            self::$breves = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
                if (self::$breves[$i]->is_redirection()) {
                    self::remove($i);
                }
            }
        } else {
            if (!array_key_exists($id, self::$breves)) {
                self::$breves [$id] = new Breve($id);
                if (self::$breves[$id]->is_redirection()) {
                    self::remove($id);
                }
            }
        }
    }
    public static function remove($id)
    { // en cas d'erreur
        unset(self::$breves[$id]);
    }
    /**
     * Création de page ou post ToWordPress
     * et liens.
     */
    public static function to_wp($site)
    {
        foreach (self::$breves as $breve) {
            $breve->to_wp($site);
        }
    }
    /**
     * mise à jour du contenu.
     */
    public static function update_refs($site)
    {
        foreach (self::$breves as $breve) {
            $breve->update_refs($site);
        }
    }
    public static function get_breve($id)
    {
        if (Config::$DETECT) {
            return; /* brève pas encore créée */
        }
        if (!$id) {
            Erreur::error("erreur d'id");

            return;
        }
        if (array_key_exists($id, self::$breves)) {
            return self::$breves [$id];
        }

        return;
    }
    public static function get_spip_title_url($id)
    {
        /* pour les références à des breves externes */
        $sql = 'SELECT titre, (SELECT url FROM '.Config::$spip_prefix.'urls WHERE '.Config::$spip_prefix.'urls.id_objet='.Config::$spip_prefix."breves.id_breve AND type='breve' ORDER BY date DESC limit 1) as url FROM ".Config::$spip_prefix.'_breves WHERE id_breve=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row['url'] == '') {
            $row['url'] = "spip.php?page=breve&id_breve=$id";
        }
        $row['url'] = Config::$spip_base_url.$row['url'];

        return $row;
    }
    public static function dump_url()
    {
        $out = '';
        foreach (self::$breves as $key => $breve) {
            $sql = 'select url from '.Config::$spip_prefix."urls where type='breve' and id_objet='.$key.' order by date desc limit 1;";
            if ($result = mysqli_query(Config::$db_spip, $sql)) {
                $row = mysqli_fetch_row($result);
                $out .= 'rewrite '.$row[0].' '.Config::$wp_base_url.'?p='.$breve->wp_id()." redirect;\n";
            }
        }

        return $out;
    }
}
