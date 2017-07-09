<?php

if (defined('__LIEN__')) {
    return;
}
define('__LIEN__', 1);

require_once 'Global.php';

class Lien
{
    public static $externes = array();
    public static $hors_rubrique = array();

    public static function url_document($id, $args, $ancre)
    {
        if (Config::detect()) {
            Documents::add($id);

            return true;
        }
        if ($doc = Documents::get_doc($id)) {
            if ($doc->is_valid()) {
                $retour = array(
                    'titre' => $doc->titre(),
                    'class' => 'spip_document',
                    'mime' => $doc->mime(),
                    'url' => $doc->guid().($args ? ('?'.$args) : ''),
                );
            } else {
                Erreur::error('Lien vers un document inexistant '.$id);
            }
        } else {
            echo 'On ne devrait jammais passer par là car on ajoute tous les documents SPIP lors de la détection, sauf si ajout de doc échoue...';
            Erreur::error("Document : $id n'a pas pu être repris");
            echo "Document QUI DEVRAIT ÊTRE REPRIS :  doc$id \n";
            $retour = array(
                'url' => Config::$SPIP_DOM."/doc$id",
                'titre' => "doc$id",
            );
        }

        return $retour;
    }

    public static function url_article($id, $args, $ancre)
    {
        if (Config::detect()) {
            return true;
        }
        if ($art = Articles::get_art($id)) {
            $retour = array(
                'titre' => $art->titre(),
                'class' => 'spip_article',
                'mime' => '',
                'url' => Config::$wp_folder.'?p='.$art->wp_id().($args ? ('&amp;'.$args) : '').'#'.$ancre,
            );
        } else {
            Erreur::error("Article devient externe : $id");
            $retour = Articles::get_spip_title_url($id);
            self::$hors_rubrique[] = $retour['url'];
        }

        return $retour;
    }

    public static function url_breve($id, $args, $ancre)
    {
        if (Config::detect()) {
            echo "Lien breve : $id,$args,$ancre\n";

            return true;
        }
    }

    public static function url_rubrique($id, $args, $ancre)
    {
        if (Config::detect()) {
            echo "Lien rubrique : $id,$args,$ancre\n";

            return true;
        }
        if ($rub = Rubriques::get_rub($id)) {
            if (Config::$RubriqueEnPage || Config::$PlusPage) {
                $url = $rub->url_page();
            } else {
                $url = $rub->url_category();
            }
            $retour = array(
                'titre' => $rub->titre(),
                'class' => 'spip_rubrique',
                'mime' => '',
                'url' => $url.($args ? ('&amp;'.$args) : '').'#'.$ancre,
            );
        } else {
            Erreur::error("Rubrique devient externe : $id");
            $retour = Rubriques::get_spip_title_url($id);
            self::$hors_rubrique[] = $retour['url'];
        }

        return $retour;
    }

    public static function url_auteur($id, $args, $ancre)
    {
        if (Config::detect()) {
            echo "TODO : Lien auteur : $id,$args,$ancre\n";

            return true;
        }
    }

    private static function url_spip($url)
    {
        $retour = false;
        if ((strlen($url) >= 5 && $b = strpos($url, '.html', strlen($url) - 5)) != false || (strlen($url) >= 4 && ($b = strpos($url, '.htm', strlen($url) - 4)) != false)) {
            $url = substr($url, 0, $b);  // enlever le / initial et l'extension .htm ou .html
        }

        /* chercher articles ou rubriques */
        $url = trim(ltrim($url, '/'));
        $sql = 'select type,id_objet from '.Config::$spip_prefix."urls where url='$url' OR concat('-',url,'-')='$url' OR concat('_',url)='$url' OR concat('+',url,'+')='$url' ";
        $res = Config::$db_spip->query($sql);
        $row = $res->fetch_assoc();
        if ($row) {
            $retour = array($row['type'], $row['id_objet']);
        } else {
            /* url "propre" */
            foreach (['article', 'rubrique', 'breve', 'auteur'] as $obj) {
                if (strpos($url, $obj) === 0) {
                    $id = intval(substr($url, strlen($obj)));
                    $retour = array($obj, $id);
                    break;
                }
            }
        }
        /* recherche document */
        if (!$retour) {
            $sql = 'select * from '.Config::$spip_prefix.'documents where locate(concat(\''.Config::$SPIP_PATH_IMG.'\',fichier),\''.$url.'\')>0';
            $res = Config::$db_spip->query($sql);
            $row = $res->fetch_assoc();
            if ($row) {
                echo "lien vers document $url : vérifier son existence sinon ajouter \n";
                Documents::add($row['id_document']);
                $retour = array('document', $row['id_document']);
            }
        }

        return $retour;
    }

    private static function unparse_url($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'].'://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':'.$parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        if (substr($path, 0, 1) != '/') {
            $path = '/'.$path;
        } // S'assurer qu'on a un / dans le chemin !
        $query = isset($parsed_url['query']) ? '?'.$parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#'.$parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public static function url_explicite($url, $titre)
    {
        //echo ":::: url explicite : $url \n";
        $retour = array(
            'titre' => $titre,
            'url' => "$url",
        );

        // pas les mails
        if (strpos($url, '@') != false) {
            return $retour;
        }
        // pas les ancres
        if ($url[0] == '#') {
            return $retour;
        }

        $lien = parse_url($url); // pour rajouter domaine et host au besoin
        if (!array_key_exists('scheme', $lien)) {
            $lien['scheme'] = 'http';
        }
        if (!array_key_exists('host', $lien)) {
            $lien['host'] = Config::$old_domain[0];
        }
        if (!array_key_exists('path', $lien)) {
            $lien['path'] = '/';
        }

        $trouve = false;

        // Rechercher les modeles séciaux : page=XXX ex: page=galerie
        // echo "== URL = ".self::unparse_url($lien)."\n\n";
        //exit(0);
        // les urls...
        // 1 SI HOST = CATHO
        //  2 SI QUERY = gallerie ...
        //  3 SINON si fichier PATH existe
        //  4 SINON recherche ... d'abord liens internes convertis
        // PUIS vrais liens externes

        foreach (Config::$old_domain as $dom) {
            if ($lien['host'] === $dom) {

                // liens vers des galeries
                $qa = array();
                if (isset($lien['query'])) {
                    parse_str($lien['query'], $qa);
                    if (array_key_exists('page', $qa) && $qa['page'] == 'galerie') { // 2
                        if ($la_galerie = Articles::get_art($qa['id_article'])) {
                            $retour['url'] = Config::$wp_folder.'?p='.$la_galerie->wp_id();
                            $retour['titre'] = $la_galerie->titre();
                            $trouve = true;

                            return $retour;
                        }
                    }
                }
                // Rechercher les fichiers existants sur le domaine // 3
                // A FAIRE ICI !
                if (in_array($lien['path'], array('/', '/spip.php', '/index.php'))) {  // /?page=
                    echo '===> TODO : rechercher tous les fichiers existants plutôt '.$lien['path']."\n";
                } elseif ($o = self::url_spip($lien['path'])) {
                    $ancre = key_exists('fragment', $lien) ? $lien['fragment'] : '';
                    $args = key_exists('query', $lien) ? $lien['query'] : '';
                    // rechercher liens internes et rechercher rub ou art  ... pour /titre-de-larticle.html ou des trucs du genre...
                    if ($o[0] === 'article') {
                        if ($wp_a = Articles::get_art($o[1])) { // si c'est un des nôtres
                            $retour = self::url_article($wp_a->id(), $args, $ancre);
                            echo '=> nouvelle url art : '.$retour['url']."\n";
                            $trouve = true;
                        }
                    } elseif ($o[0] === 'rubrique') { //  tester rubrique
                        if ($wp_r = Rubriques::get_rub($o[1])) { // si c'est un des nôtres
                            $retour = self::url_rubrique($wp_r->id(), $args, $ancre);
                            echo '=> nouvelle url rub : '.$retour['url']."\n";
                            $trouve = true;
                        }
                    } elseif ($o[0] === 'document') { //  tester rubrique)
                        $doc = Documents::get_doc($o[1]);
                        $retour['url'] = $doc->guid();
                        $retour['titre'] = basename($doc->fichier);
                    }
                } else {
                    // Chercher si document non SPIP
                }

                return $retour;
            }
        }

        if (Config::$RECHERCHE_LIENS && !$trouve) {
            $url = self::unparse_url($lien); // http://nom-de-domaine-original par défaut
            self::$externes[] = $url;

            stream_context_set_default(array('http' => array('method' => 'GET', 'headers' => 'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36')));
            // récupérer les en têtes (au moins 1) et passer les clés en minuscules (Location, location, lOcAtIOn ?
            $headers = array_change_key_case(get_headers($url, 1));

            $r = $headers[0];

            while (strchr($r, '301')) {
                $loc = $headers['location'];
                if (is_array($loc)) {
                    $loc = $loc[0];
                } /* certains sites envoient plusieurs redirection ! */
                $l = parse_url($loc);
                if (!$l) {
                    echo "=====\nImpossible à analyser $loc...\n=====\n";
                    break;
                }
                if (array_key_exists('path', $l)) {
                    if ($l['path'][0] != '/') {
                        $l['path'] = '/'.$l['path'];
                    }
                }
                $lien = array_merge($lien, $l); /* on écrase les valeurs d'ancien par le nouveau, car il existe des redirection en chemin relatif aussi */
                unset($lien['query']); // risque de boucle infinie...
                $url = self::unparse_url($lien);
                $headers = array_change_key_case(get_headers($url, 1));
                $r = $headers[0];
                Erreur::error("Redirection de lien : $loc -> $r");
            }
            if (strchr($r, '200')) {
                $retour['url'] = $url;
            } else {
                Erreur::error("Code non OK ($r) pour {$retour['url']} => $url");
            }
        }

        return $retour;
    }
}
