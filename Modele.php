<?php

require_once 'Documents.php';

function doc_spip_2_wp($id)
{
    if ($doc = Documents::get_doc($id)) {
        return $doc->wp_id();
    } else {
        //Erreur::error("Doc inexitstant");
        return 0;
    }
}

class Modele
{
    private static function align($params)
    {
        $align = '';
        $p = explode('|', $params);
        foreach (array('center', 'left', 'right') as $a) {
            if (in_array($a, $p)) {
                $align = "align$a";
                break;
            }
        }

        return $align;
    }
    public static function emb($id, $params, $lien)
    {
        echo '=== ATTENTION === Embedded converti en doc'.PHP_EOL;

        return self::doc($id, $params, $lien);
    }
    public static function doc($id, $params, $lien)
    {
        $align = self::align($params);
        if (Config::detect()) {
            Documents::add($id);

            return false;
        }
        $doc = Documents::get_doc($id);
        if (!$doc) {
            return '';
        }
        $fn = $doc->guid();
        $ext = substr($fn, strrpos($fn, '.') + 1);
        //echo "=== fn = $fn et ext = $ext \n";
        $vignette = $doc->vignette();
        if (!$vignette) {
            if (strpos($doc->mime(), 'image') === 0) {
                $vignette = $doc->guid();
            } else {
                if (file_exists(Config::$SPIP_ABS_PATH.Config::$SPIP_PATH_VIGNETTES.$ext.'.png')) {
                    $v = Config::copy_file(Config::$SPIP_ABS_PATH.Config::$SPIP_PATH_VIGNETTES.$ext.'.png', null, "Vignette $ext");
                } else {
                    $v = Config::copy_file(Config::$SPIP_ABS_PATH.Config::$SPIP_PATH_VIGNETTES.'defaut'.'.png', null, "Vignette $ext");
                }
                $vignette = $v['guid'];
                //print_r($v);
                //echo("XX vignette : ".Config::$SPIP_ABS_PATH.Config::$SPIP_PATH_VIGNETTES . $ext . '.png'."\n");
                //$vignette = Config::$wp_base_url . Config::$wp_upload_path .  'vignettes/' . $ext . '.png';
            }
        } else {
            $vignette = $vignette->guid();
        }
        //echo "=== vignette = $vignette\n";
        $titre = str_replace(array('_', '-'), array(' ', ' '), basename($doc->titre()));
        $desc = $doc->descriptif();
        $html = "<dl class='$align'>".
                "<dt><a href='".($lien ? $lien['href'] : $fn)."'>".
                "<img class='wp-image-".$doc->wp_id()."' src='".$vignette."'></a></dt>".
                ($titre ? '<dd><strong>'.$titre.'</strong></dd>' : '').
                '</dl>';
        //echo $html;
        //if ($lien) { $ancre = "<a href='".$lien["href"]."'>".$ancre."</a>"; }
        return $html;
    }

    public static function img($id, $params, $lien)
    {
        $align = self::align($params);
        if (Config::detect()) {
            if (is_array($lien)) {
                $href = $lien['href'];
                $class = $lien['class'];
                $mime = $lien['mime'];
            } else {
                assert($lien === false);
            }
            Documents::add($id);

            return false;
        }
        $doc = Documents::get_doc($id);
        if (!$doc) {
            return '';
        }
        $ancre = '<img alt="'.$doc->titre().'" class="'.$align.' wp-image-'.$doc->wp_id().'" '.
                'src="'.$doc->guid().'" />';
        if ($lien) {
            $ancre = '<a href="'.$lien['href'].'">'.$ancre.'</a>';
        }

        return $ancre;
    }

    public static function galerie($id, $args, $lien)
    {
        $pattern = '/\|images=([0-9, ]+)/';
        preg_match($pattern, $args, $matches);
        $ids = explode(',', $matches[1]);
        $verif = array();
        foreach ($ids as $idstr) {
            $id = trim($idstr);
            if ($id && ctype_digit($id) && ((int) $id) > 0) {
                if (Config::detect()) {
                    Documents::add($id);
                }
                array_push($verif, $id);
            } else {
                Erreur::error('Paramètre images eronné : '.$id);
            }
        }
        if (Config::detect()) {
            return false;
        }

        return '[gallery type="gallery" link="file" ids="'.implode(',', array_map('doc_spip_2_wp', $verif)).'"]';
    }

    public static function diaporama($id, $args, $lien)
    {
        $pattern = '/\|images=([0-9, ]+)/';
        preg_match($pattern, $args, $matches);
        $ids = explode(',', $matches[1]);
        $verif = array();
        foreach ($ids as $idstr) {
            $id = trim($idstr);
            if ($id && ctype_digit($id) && ((int) $id) > 0) {
                if (Config::detect()) {
                    Documents::add($id);
                }
                array_push($verif, $id);
            } else {
                Erreur::error('Paramètre images eronné : '.$id);
            }
        }
        if (Config::detect()) {
            return false;
        }
        //return '[diaporama link="file" ids="' . implode(',', array_map("doc_spip_2_wp", $verif)) . '"]';
        return '[gallery type="slideshow" link="none" ids="'.implode(',', array_map('doc_spip_2_wp', $verif)).'"]';
    }

    public static function rotator($id, $args, $lien)
    {
        return self::diaporama($id, $args, $lien);
    }

    public static function defiler($id, $args, $lien)
    {
        $p = explode('|', $args);
        $texte = '';
        foreach ($p as $a) {
            if (strncmp('texte=', $a, 6) == 0) {
                $texte = substr($a, 6);
                break;
            }
        }
        if (!empty($texte)) {
            $lien = '';
            if (in_array('lien', $p)) {
                $lien = $p['lien'];
            }
            $str = '<div style="border-style: outset; border-width: 2px; border-color: #CCC; background-color: #DDD; padding: 5px; font-size: 120%; color: red">';
            $str .= '<marquee onmouseout="this.start();" onmouseover="this.stop();" loop="infinite" scrolldelay="30" scrollamount="5" direction="left" behavior="scroll">';
            //$str .= '<a style="text-decoration: none"  href="#URL_ARTICLE{#ENV{lien}}">(#ENV{lien}|oui)';
            $str .= $texte;
            //$str .= '</a>(#ENV{lien}|oui)';
            $str .= '</marquee></div>';

            return $str;
        } else {
            echo 'DEFILER: pas de texte';
            var_dump($args);
            Erreur::error('Défiler sans texte à faire défiler...');

            return $texte;
        }
    }
}
