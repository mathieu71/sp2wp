<?php

if (defined('__RUBRIQUES__')) {
    return;
}
define('__RUBRIQUES__', 1);

class Rubriques
{
    public static $rubriques = array();
    private static $racine;
    public function __construct($id)
    {
        self::$racine = (int) $id;
        self::arbo($id);
    }
    private static function arbo($rubrique)
    {
        $rub = new Rubrique($rubrique);

        self::$rubriques [$rubrique] = $rub;
        $sql = 'SELECT id_rubrique, id_parent FROM '.Config::$spip_prefix."rubriques WHERE id_parent=$rubrique;";
        $res = Config::$db_spip->query($sql);
        while ($cur = $res->fetch_assoc()) {
            self::arbo($cur ['id_rubrique']);
        }
        $rub->remplir();
    }
    public static function to_wp($site)
    {
        foreach (self::$rubriques as $rub) {
            $rub->to_wp($site);
        }
    }

    public static function update_refs($site)
    {
        foreach (self::$rubriques as $rub) {
            $rub->update_refs($site);
        }
    }
    public static function get_rub($id)
    {
        if (is_object($id)) {
            Erreur::dump();
            exit(0);
        }
        if (array_key_exists($id, self::$rubriques)) {
            return self::$rubriques [$id];
        } else {
            return;
        }
    }
    public static function rub_racine()
    {
        return self::$racine;
    }
    public static function get_spip_title_url($id)
    {
        /* pour les références à des rubriques externes */
        $sql = 'SELECT titre, (SELECT url FROM '.Config::$spip_prefix.'urls WHERE '.Config::$spip_prefix.'urls.id_objet='.Config::$spip_prefix."rubriques.id_rubrique AND type='rubrique' ORDER BY date DESC limit 1) as url FROM ".Config::$spip_prefix.'rubriques WHERE id_rubrique=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row['url'] == '') {
            $row['url'] = "spip.php?page=rubrique&id_rubrique=$id";
        }
        $row['url'] = Config::$spip_base_url.$row['url'];

        return $row;
    }
    public static function dump_url()
    {
        $out = '';
        foreach (self::$rubriques as $key => $rub) {
            $sql = 'select url from '.Config::$spip_prefix.'urls where type=\'rubrique\' and id_objet='.$key.' order by date desc limit 1;';
            if ($result = mysqli_query(Config::$db_spip, $sql)) {
                $row = mysqli_fetch_row($result);
                $out .= 'rewrite '.$row[0].' '.Config::$wp_base_url.'?p='.$rub->wp_id()." redirect;\n";
            }
        }

        return $out;
    }
}
