<?php

if (defined('__EVENEMENTS__')) {
    return;
}
define('__EVENEMENTS__', 1);

class Evenements
{
    public static $evenements = array();

    public static function add($id)
    {
        if (is_null(self::$evenements)) {
            self::$evenements = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
            }
        } else {
            if (!array_key_exists($id, self::$evenements)) {
                self::$evenements [$id] = new Evenement($id);
            }
        }
    }

    public static function to_wp($site)
    {
        if (is_null(self::$evenements)) {
            return;
        }
        foreach (self::$evenements as $evenement) {
            $evenement->to_wp($site);
        }
    }

    public static function update_refs($site)
    {
        foreach (self::$evenements as $evt) {
            $evt->update_refs($site);
        }
        // ajouter les lieux récupérés de Spip
        self::lieus($site);
        // Ajouter deux type d'événements par défaut
        self::types_ev($site);
        // reprise SPIP
    }

    private static function lieus($site)
    {
        foreach (self::$evenements as $evt) {
            $nom_lieu = $evt->lieu();
            if (!empty($nom_lieu)) {
                $slug = Urls::add($nom_lieu);

                $check = "SELECT term_taxonomy_id FROM `wp${site}_terms` AS term JOIN `wp${site}_term_taxonomy` AS lieu ON (lieu.term_id = term.term_id) WHERE term.name=?";
                $stmt = Config::$db_wp->prepare($check);
                $stmt->bind_param('s', $nom_lieu);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();

                if (!$row) {
                    $sql1 = "INSERT INTO `wp${site}_terms` (`name`,`slug`,`term_group`) VALUES (?,?,0)";
                    $stmt = Config::$db_wp->prepare($sql1);
                    $stmt->bind_param('ss', $nom_lieu, $slug);
                    $stmt->execute();
                    if (Config::$db_wp->error) {
                        Erreur::error('lieu (term) non inséré : '.Config::$db_wp->error);
                    }
                    $term = Config::$db_wp->insert_id;

                    $sql2 = "INSERT INTO `wp${site}_term_taxonomy` (`term_id`,`taxonomy`,`description`,`parent`) VALUES (?,?,'',0)"; // count à mettre à jour...
                    $stmt = Config::$db_wp->prepare($sql2);
                    $tax = 'lieu';

                    $stmt->bind_param('ds', $term, $tax);
                    $stmt->execute();
                    if (Config::$db_wp->error) {
                        Erreur::error('lieu non inséré : '.Config::$db_wp->error);
                    }
                    $taxonomy = Config::$db_wp->insert_id;
                } else {
                    $taxonomy = $row['term_taxonomy_id'];
                }

                $sql1 = 'INSERT INTO wp'.$site.'_term_relationships(object_id, term_taxonomy_id) VALUES (?,?)';
                $stmt = Config::$db_wp->prepare($sql1);
                $id_evt = $evt->wp_id();
                $stmt->bind_param('dd', $id_evt, $taxonomy);
                $stmt->execute();
            }
        }
    }

    private static function types_ev($site)
    {
        $types = array(array('Célébrations', 0), array('Autres événements', 0));
        foreach ($types as &$item) {
            $nom = $item[0];
            $slug = Urls::add($nom);

            $check = "SELECT term_taxonomy_id FROM `wp${site}_terms` AS term JOIN `wp${site}_term_taxonomy` AS lieu ON (lieu.term_id = term.term_id) WHERE term.slug=?";
            $stmt = Config::$db_wp->prepare($check);
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();

            if (!$row) {
                $sql1 = "INSERT INTO `wp${site}_terms` (`name`,`slug`,`term_group`) VALUES (?,?,0)";
                $stmt1 = Config::$db_wp->prepare($sql1);
                $stmt1->bind_param('ss', $nom, $slug);
                $stmt1->execute();
                if (Config::$db_wp->error) {
                    Erreur::error('typr ev (term) non inséré : '.Config::$db_wp->error);
                }
                $term = Config::$db_wp->insert_id;

                $sql2 = "INSERT INTO `wp${site}_term_taxonomy` (`term_id`,`taxonomy`,`description`,`parent`) VALUES (?,?,'',0)"; // count à mettre à jour...
                $stmt2 = Config::$db_wp->prepare($sql2);
                $tax = 'type_evenement';

                $stmt2->bind_param('ds', $term, $tax);
                $stmt2->execute();
                if (Config::$db_wp->error) {
                    Erreur::error('type ev non inséré : '.Config::$db_wp->error);
                }
                $taxonomy = Config::$db_wp->insert_id;
            } else {
                $taxonomy = $row['term_taxonomy_id'];
            }
            $item[1] = $taxonomy;
        }
        $sql3 = 'INSERT INTO wp'.$site.'_term_relationships(object_id, term_taxonomy_id) VALUES (?,?)';
        $stmt3 = Config::$db_wp->prepare($sql3);

        foreach (self::$evenements as $evt) {
            $messe = $evt->detecte_messe();
            $type = $messe ? $types[0][1] : $types[1][1];
            $id_evt = $evt->wp_id();
            $stmt3->bind_param('dd', $id_evt, $type);
            $stmt3->execute();
        }
    }
}
