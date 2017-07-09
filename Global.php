<?php

if (defined('__GLOBAL__')) {
    return;
}
define('__GLOBAL__', 1);

interface ToWordPress
{
    public function to_wp($site);
}

;

class Erreur
{
    private static $niveau = 0;
    private static $errors = array();
    private static $type = array();
    private static $id = array();

    public static function contexte_push($type, $id)
    {
        ++self::$niveau;
        self::$type[self::$niveau] = $type;
        self::$id[self::$niveau] = $id;
    }

    public static function contexte_pop()
    {
        --self::$niveau;
    }

    public static function error($str)
    {
        $type = self::$type[self::$niveau];
        $id = self::$id[self::$niveau];
        if (!array_key_exists($type, self::$errors)) {
            self::$errors[$type] = array();
        }
        if (!array_key_exists($id, self::$errors[$type])) {
            self::$errors[$type][$id] = array();
        }
        array_push(self::$errors[self::$type[self::$niveau]][self::$id[self::$niveau]], $str);
    }

    public static function dump()
    {
        ob_start();
        echo "<h1>Rapport d'erreurs</h1>\n";
        foreach (self::$errors as $type => $e) {
            if (count($e > 0)) {
                echo "<h2>$type</h2><dl>\n";
                foreach ($e as $id => $err) {
                    echo "<dt>$type $id</dt><dd>\n";
                    foreach ($err as $str) {
                        echo "<p>$str</p>\n";
                    }
                    echo "</dd>\n";
                }
                echo "</dt>\n\n";
            }
            echo "</dl>\n";
        }
        $out = ob_get_contents();
        ob_end_clean();
        echo $out;
    }

    public static function courant()
    {
        return self::$id[self::$niveau];
    }
}

function normalise($str)
{
    $str = strtolower(preg_replace('/[^0-9\-A-Za-z_]/', '-', $str));
    $str = preg_replace('/\-+/', '-', $str);
    $str = preg_replace('/^([0-9]\-)+/', '', $str);

    return $str;
}

function valider_date($str, &$local, &$utc)
{
    $annee = '2000';
    $mois = '06';
    $jour = '14';
    $heure = '12';
    $minute = '00';
    $second = '00';

    if (strlen($str) >= 4) {
        $annee = substr($str, 0, 4);
        if ((int) $annee <= 2000 || (int) $annee >= 2100) {
            $annee = '1999';
        }
        if (strlen($str) >= 7) {
            $mois = substr($str, 5, 2);
            if ((int) $mois <= 0 || (int) $mois > 12) {
                $mois = '06';
            }
            if (strlen($str) >= 10) {
                $jour = substr($str, 8, 2);
                if ((int) $jour <= 0 || (int) $jour > 31) {
                    $jour = '15';
                }
                if (strlen($str) >= 13) {
                    $heure = substr($str, 11, 2);
                    if ((int) $heure < 0 || (int) $heure > 23) {
                        $heure = '12';
                    }
                    if (strlen($str) >= 16) {
                        $minute = substr($str, 14, 2);
                        if ((int) $minute < 0 || (int) $minute > 59) {
                            $minute = '30';
                        }
                        if (strlen($str) >= 19) {
                            $second = substr($str, 17, 2);
                            if ((int) $second < 0 || (int) $second > 50) {
                                $second = '30';
                            }
                        }
                    }
                }
            }
        }
    }

    date_default_timezone_set('Europe/Paris');
    $utc = gmdate('Y-m-d H:i:s', mktime($heure, $minute, $second, $mois, $jour, $annee));
    date_default_timezone_set('UTC');
    $local = gmdate('Y-m-d H:i:s', mktime($heure, $minute, $second, $mois, $jour, $annee));

    return $utc;
}

$ligne_horizontale = "\n<hr />\n";
$debut_intertitre = "\n<h3>";
$fin_intertitre = "</h3>\n";
$debut_gras = '<strong>';
$fin_gras = '</strong>';
$debut_italique = '<i>';
$fin_italique = '</i>';
$ouvre_ref = '&nbsp;[';
$ferme_ref = ']';
$ouvre_note = '[';
$ferme_note = '] ';
$les_notes = '';
$compt_note = 0;
$notes_vues = array();

function definir_raccourcis_alineas()
{
    global $ligne_horizontale;
    static $alineas = array();

    $alineas = array(
        array(
            /* 0 */ "/\n(----+|____+)/S",
            /* 1 */ "/\n-- */S",
            /* 2 */ "/\n- */S", /* DOIT rester a cette position */
            /* 3 */ "/\n_ +/S",
        ),
        array(
            /* 0 */ "\n\n".$ligne_horizontale."\n\n",
            /* 1 */ "\n<br />&mdash;&nbsp;",
            /* 2 */ "\n<br />".definir_puce().'&nbsp;',
            /* 3 */ "\n<br />",
        ),
    );

    return $alineas;
}

// On initialise la puce pour eviter find_in_path() a chaque rencontre de \n-
// Mais attention elle depend de la direction et de X_fonctions.php, ainsi que
// de l'espace choisi (public/prive)
// http://doc.spip.org/@definir_puce
function definir_puce()
{
    return '&mdash;';
}

function paragrapher($letexte)
{
    if (!defined('_BALISES_BLOCS')) {
        define('_BALISES_BLOCS', 'div|pre|ul|ol|li|blockquote|h[1-6r]|'
                .'t(able|[rdh]|body|foot|extarea)|'
                .'form|object|center|marquee|address|'
                .'d[ltd]|script|noscript|map|button|fieldset|style');
    }

    $letexte = trim($letexte);
    if (!strlen($letexte)) {
        return '';
    }

    // Ajouter un espace aux <p> et un "STOP P"
    // transformer aussi les </p> existants en <p>, nettoyes ensuite
    $letexte = preg_replace(',</?p\b\s?(.*?)>,iS', '<STOP P><p \1>', '<p>'.$letexte.'<STOP P>');

    // Fermer les paragraphes (y compris sur "STOP P")
    $letexte = preg_replace(',(<p\s.*)(</?(STOP P|'._BALISES_BLOCS.')[>[:space:]]),UimsS', "\n\\1</p>\n\\2", $letexte);

    // Supprimer les marqueurs "STOP P"
    $letexte = str_replace('<STOP P>', '', $letexte);

    // Reduire les blancs dans les <p>
    $u = 'u';
    $letexte = preg_replace(',(<p\b.*>)\s*,UiS'.$u, '\1', $letexte);
    $letexte = preg_replace(',\s*(</p\b.*>),UiS'.$u, '\1', $letexte);

    // Supprimer les <p xx></p> vides
    $letexte = preg_replace(',<p\b[^<>]*></p>\s*,iS'.$u, '', $letexte);

    // Renommer les paragraphes normaux
    // $letexte = str_replace('<p >', "<p$class_spip>",
    //		$letexte);
    return $letexte;
}

function traiter_listes($texte)
{
    $parags = preg_split(",\n[[:space:]]*\n,S", $texte);
    $texte = '';

    // chaque paragraphe est traite a part
    while (list(, $para) = each($parags)) {
        $niveau = 0;
        $pile_li = $pile_type = array();
        $lignes = explode("\n-", "\n".$para);

        // ne pas toucher a la premiere ligne
        list(, $debut) = each($lignes);
        $texte .= $debut;

        // chaque item a sa profondeur = nb d'etoiles
        $type = '';
        while (list(, $item) = each($lignes)) {
            preg_match(',^([*]*|[#]*)([^*#].*)$,sS', $item, $regs);
            $profond = strlen($regs[1]);

            if ($profond > 0) {
                $ajout = '';

                // changement de type de liste au meme niveau : il faut
                // descendre un niveau plus bas, fermer ce niveau, et
                // remonter
                $nouv_type = (substr($item, 0, 1) == '*') ? 'ul' : 'ol';
                $change_type = ($type and ($type != $nouv_type) and ($profond == $niveau)) ? 1 : 0;
                $type = $nouv_type;

                // d'abord traiter les descentes
                while ($niveau > $profond - $change_type) {
                    $ajout .= $pile_li[$niveau];
                    $ajout .= $pile_type[$niveau];
                    if (!$change_type) {
                        unset($pile_li[$niveau]);
                    }
                    --$niveau;
                }

                // puis les identites (y compris en fin de descente)
                if ($niveau == $profond && !$change_type) {
                    $ajout .= $pile_li[$niveau];
                }

                // puis les montees (y compris apres une descente un cran trop bas)
                while ($niveau < $profond) {
                    if ($niveau == 0) {
                        $ajout .= "\n\n";
                    } elseif (!isset($pile_li[$niveau])) {
                        $ajout .= '<li>';
                        $pile_li[$niveau] = '</li>';
                    }
                    ++$niveau;
                    $ajout .= "<$type>";
                    $pile_type[$niveau] = "</$type>";
                }

                $ajout .= '<li>';
                $pile_li[$profond] = '</li>';
            } else {
                $ajout = "\n-"; // puce normale ou <hr>
            }

            $texte .= $ajout.$regs[2];
        }

        // retour sur terre
        $ajout = '';
        while ($niveau > 0) {
            $ajout .= $pile_li[$niveau];
            $ajout .= $pile_type[$niveau];
            --$niveau;
        }
        $texte .= $ajout;

        // paragraphe
        $texte .= "\n\n";
    }

    // sucrer les deux derniers \n
    return substr($texte, 0, -2);
}

define('_RACCOURCI_CAPTION', ',^\|\|([^|]*)(\|(.*))?$,sS');
define('_RACCOURCI_TH_SPAN', '\s*(?:{{[^{}]+}}\s*)?|<');
define('_RACCOURCI_THEAD', true);

function corriger_entites_html($texte)
{
    if (strpos($texte, '&amp;') === false) {
        return $texte;
    }

    return preg_replace(',&amp;(#[0-9][0-9][0-9]+;|amp;),iS', '&\1', $texte);
}

// idem mais corriger aussi les &amp;eacute; en &eacute;
// http://doc.spip.org/@corriger_toutes_entites_html
function corriger_toutes_entites_html($texte)
{
    if (strpos($texte, '&amp;') === false) {
        return $texte;
    }

    return preg_replace(',&amp;(#?[a-z0-9]+;),iS', '&\1', $texte);
}

// http://doc.spip.org/@proteger_amp
function proteger_amp($texte)
{
    return str_replace('&', '&amp;', $texte);
}

// http://doc.spip.org/@entites_html
function entites_html($texte, $tout = false)
{
    if (!is_string($texte) or !$texte
            or !preg_match(",[&\"'<>],S", $texte) # strpbrk($texte, "&\"'<>")!==false
    ) {
        return $texte;
    }
    //include_spip('inc/texte');
    //$texte = htmlspecialchars(echappe_retour(echappe_html($texte,'',true),'','proteger_amp'));
    if ($tout) {
        return corriger_toutes_entites_html($texte);
    } else {
        return corriger_entites_html($texte);
    }
}

define('_PROTEGE_BLOCS', ',<(html|code|cadre|frame|script|math|onglets)(\s[^>]*)?>(.*)</\1>,UimsS');

// http://doc.spip.org/@traiter_echap_html_dist
function traiter_echap_html_dist($regs)
{
    return $regs[3];
}

@define('_decoupe_NB_CARACTERES', 60);

define('_onglets_CONTENU', '<div class="onglets_contenu"><h2 class="cs_onglet"><a href="#">');
define('_onglets_CONTENU2', '</a></h2>'); // sans le </div> !
define('_onglets_DEBUT', '<div id="tabs" class="onglets_bloc_initial">');
define('_onglets_REGEXPR', ',<onglets([0-9]*)>(.*?)</onglets\1>,ms');
@define('_decoupe_SEPARATEUR', '++++');
define('_onglets_FIN', '');

function traiter_echap_onglets($regs)
{
    // cas des onglets imbriques
    $regs[0] = preg_replace("/<\/?onglets>(\n\n|\r\n\r\n|\r\r)*/ms", '', $regs[0]);
    if (strpos($regs[0], '<onglets') !== false) {
        $regs[0] = preg_replace_callback(_onglets_REGEXPR, 'onglets_callback', $regs[0]);
    }
    // nettoyage apres les separateurs
    $regs[0] = preg_replace(','.preg_quote(_decoupe_SEPARATEUR, ',').'\s+,', _decoupe_SEPARATEUR, $regs[0]);
    $onglets = $contenus = array();
    $pages = explode(_decoupe_SEPARATEUR, $regs[0]);
    $n = 1;
    $tmp = '';
    foreach ($pages as $p) {
        $t = preg_split(',(\n\n|\r\n\r\n|\r\r),', $p, 2);
        $t[0] = trim($t[0]);
        $t[0] = preg_replace("/\{\{\{/", '', $t[0]);
        $t[0] = preg_replace("/\}\}\}/", '', $t[0]);
        array_push($onglets, "<li><a href='#tab-$n'>".traiter_raccourcis($t[0], false)."</a></li>\n");
        array_push($contenus, "<h3 style='display: none' class='tab-title'>".traiter_raccourcis($t[0], false)."</h3>\n<div id='tab-$n' class='tab-content'>\n".traiter_raccourcis($t[1])."\n</div>\n");
        // $tmp .= "<h2>" . trim(traiter_raccourcis($t[0], FALSE)) . "</h2>\n" . str_replace('h2', 'h3', traiter_raccourcis($t[1])) . "\n"; // TODO : descendre les    autres niveau de titre ?
        $tmp .= '<h2>'.trim(strip_tags($t[0], '<strong><em><i><b><font>'))."</h2>\n"
                .str_replace('h2', 'h3', traiter_raccourcis($t[1], false))."\n"; // DONE : descendre les    autres niveau de titre ?
        ++$n;
    }

    return '[onglets]'.$tmp.'[/onglets]'; //_onglets_DEBUT."<ul>".join('',$onglets)."</ul>".join('', $contenus).'</div>'._onglets_FIN;
}

// - pour $source voir commentaire infra (echappe_retour)
// - pour $no_transform voir le filtre post_autobr dans inc/filtres
// http://doc.spip.org/@echappe_html
function echappe_html($letexte, $source = '', $no_transform = false, $preg = '')
{
    if (!is_string($letexte) or !strlen($letexte)) {
        return $letexte;
    }
    $ok = 0;
    if (($preg or strpos($letexte, '<') !== false)
            and preg_match_all($preg ? $preg : _PROTEGE_BLOCS, $letexte, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $regs) {
            $ok = 1;
            if ($no_transform) {
                $echap = $regs[0];
            }

            // sinon les traiter selon le cas
            elseif (function_exists($f = 'traiter_echap_'.strtolower($regs[1]))) {
                $echap = $f($regs);
            } elseif (function_exists($f = $f.'_dist')) {
                $echap = $f($regs);
            } else {
                $echap = $regs[0];
            }

            $letexte = str_replace($regs[0], code_echappement($echap, $source, $no_transform), $letexte);
        }
    }

    if ($no_transform) {
        return $letexte;
    }

    return $letexte;
}

function traiter_tableau($bloc)
{

    // Decouper le tableau en lignes
    preg_match_all(',([|].*)[|]\n,UmsS', $bloc, $regs, PREG_PATTERN_ORDER);
    $lignes = array();
    $debut_table = $summary = '';
    $l = 0;
    $numeric = true;

    // Traiter chaque ligne
    $reg_line1 = ',^(\|('._RACCOURCI_TH_SPAN.'))+$,sS';
    $reg_line_all = ',^'._RACCOURCI_TH_SPAN.'$,sS';
    $num_cols = 0;
    foreach ($regs[1] as $ligne) {
        ++$l;

        // Gestion de la premiere ligne :
        if (($l == 1) and preg_match(_RACCOURCI_CAPTION, rtrim($ligne, '|'), $cap)) {
            // - <caption> et summary dans la premiere ligne :
            //   || caption | summary || (|summary est optionnel)
            $l = 0;
            if ($caption = trim($cap[1])) {
                $debut_table .= '<caption>'.$caption."</caption>\n";
            }
            $summary = ' summary="'.entites_html(trim($cap[3])).'"';
        } else {
            // - <th> sous la forme |{{titre}}|{{titre}}|
            if (preg_match($reg_line1, $ligne)) {
                preg_match_all('/\|([^|]*)/S', $ligne, $cols);
                $ligne = '';
                $cols = $cols[1];
                $colspan = 1;
                $num_cols = count($cols);
                for ($c = $num_cols - 1; $c >= 0; --$c) {
                    $attr = '';
                    if ($cols[$c] == '<') {
                        ++$colspan;
                    } else {
                        if ($colspan > 1) {
                            $attr = " colspan='$colspan'";
                            $colspan = 1;
                        }
                        // inutile de garder le strong qui n'a servi que de marqueur
                        $cols[$c] = str_replace(array('{', '}'), '', $cols[$c]);
                        $ligne = "<th scope='col'$attr>$cols[$c]</th>$ligne";
                    }
                }
                $lignes[] = $ligne;
            } else {
                // Sinon ligne normale
                // Gerer les listes a puce dans les cellules
                if (strpos($ligne, "\n-*") !== false or strpos($ligne, "\n-#") !== false) {
                    $ligne = traiter_listes($ligne);
                }

                // Pas de paragraphes dans les cellules
                $ligne = preg_replace("/\n{2,}/", "<br /><br />\n", $ligne);

                // tout mettre dans un tableau 2d
                preg_match_all('/\|([^|]*)/S', $ligne, $cols);
                $lignes[] = $cols[1];
            }
        }
    }
    // maintenant qu'on a toutes les cellules
    // on prepare une liste de rowspan par defaut, a partir
    // du nombre de colonnes dans la premiere ligne.
    // Reperer egalement les colonnes numeriques pour les cadrer a droite
    $rowspans = $numeric = array();
    $n = $num_cols ? $num_cols : count($lignes[0]);
    $k = count($lignes);
    // distinguer les colonnes numeriques a point ou a virgule,
    // pour les alignements eventuels sur "," ou "."
    $numeric_class = array('.' => 'point', ',' => 'virgule');
    for ($i = 0; $i < $n; ++$i) {
        $align = true;
        for ($j = 0; $j < $k; ++$j) {
            $rowspans[$j][$i] = 1;
        }
        for ($j = 0; $j < $k; ++$j) {
            if (!is_array($lignes[$j])) {
                continue;
            } // cas du th
            $cell = trim($lignes[$j][$i]);
            if (preg_match($reg_line_all, $cell)) {
                if (!preg_match('/^[+-]?(?:\s|\d)*([.,]?)\d*$/', $cell, $r)) {
                    $align = '';
                    break;
                } elseif ($r[1]) {
                    $align = $r[1];
                }
            }
        }
        $numeric[$i] = !$align ? '' : (" class='numeric ".$numeric_class[$align]."'");
    }

    // et on parcourt le tableau a l'envers pour ramasser les
    // colspan et rowspan en passant
    $html = '';

    for ($l = count($lignes) - 1; $l >= 0; --$l) {
        $cols = $lignes[$l];
        if (!is_array($cols)) {
            $class = 'first';
            $ligne = $cols;
        } else {
            $ligne = '';
            $colspan = 1;
            //$class = alterner($l+1, 'even', 'odd');
            for ($c = count($cols) - 1; $c >= 0; --$c) {
                $attr = $numeric[$c];
                $cell = trim($cols[$c]);
                if ($cell == '<') {
                    ++$colspan;
                } elseif ($cell == '^') {
                    $rowspans[$l - 1][$c] += $rowspans[$l][$c];
                } else {
                    if ($colspan > 1) {
                        $attr .= " colspan='$colspan'";
                        $colspan = 1;
                    }
                    if (($x = $rowspans[$l][$c]) > 1) {
                        $attr .= " rowspan='$x'";
                    }
                    $ligne = "\n<td".$attr.'>'.$cols[$c].'</td>'.$ligne;
                }
            }
        }
        $html = "<tr>$ligne</tr>\n$html";
    }
    if (_RACCOURCI_THEAD
            and preg_match("@^(<tr class='row_first'.*?</tr>)(.*)$@s", $html, $m)) {
        $html = "<thead>$m[1]</thead>\n<tbody>$m[2]</tbody>\n";
    }

    return "\n\n<table".$GLOBALS['class_spip_plus'].$summary.">\n"
            .$debut_table
            .$html
            ."</table>\n\n";
}

define('_RACCOURCI_PROTEGER', '{}_-');
define('_RACCOURCI_PROTECTEUR', "\x1\x2\x3\x4");

define('_RACCOURCI_BALISE', ',</?[a-z!][^<>]*['.preg_quote(_RACCOURCI_PROTEGER).'][^<>]*>,imsS');
if (!defined('_BALISES_BLOCS')) {
    define('_BALISES_BLOCS', 'div|pre|ul|ol|li|blockquote|h[1-6r]|'
            .'t(able|[rdh]|body|foot|extarea)|'
            .'form|object|center|marquee|address|'
            .'d[ltd]|script|noscript|map|button|fieldset|style');
}

function supprimer_caracteres_illegaux($texte)
{
    static $from = "\x0\x1\x2\x3\x4\x5\x6\x7\x8\xB\xC\xE\xF\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";
    static $to = null;

    if (is_array($texte)) {
        return array_map('supprimer_caracteres_illegaux', $texte);
    }
    $texte = str_replace("\r\n", "\r", $texte);
    $texte = str_replace("\r", "\n", $texte);

    if (preg_match(",\n+$,", $texte, $fin)) {
        $texte = substr($texte, 0, -strlen($fin[0]));
    }
    if (!$to) {
        $to = str_repeat('-', strlen($from));
    }

    return strtr($texte, $from, $to);
}

function code_echappement($rempl, $source = '', $no_transform = false)
{
    if (!strlen($rempl)) {
        return '';
    }

    // Tester si on echappe en span ou en div
    $mode = preg_match(',</?('._BALISES_BLOCS.')[>[:space:]],iS', $rempl) ?
            'div' : 'span';
    $return = '';

    // Decouper en morceaux, base64 a des probleme selon la taille de la pile
    $taille = 30000;
    for ($i = 0; $i < strlen($rempl); $i += $taille) {
        // Convertir en base64 et cacher dans un attribut
        // utiliser les " pour eviter le re-encodage de ' et &#8217
        $base64 = base64_encode(substr($rempl, $i, $taille));
        $return .= "<$mode class=\"base64$source\" title=\"$base64\"></$mode>";
    }

    return $return
            .((!$no_transform and $mode == 'div') ? "\n\n" : ''
            );
}

function notes($arg, $operation = 'traiter')
{
    static $pile = array();
    static $next_marqueur = 1;
    static $marqueur = 1;
    global $les_notes, $compt_note, $notes_vues;
    switch ($operation) {
        case 'traiter':
            if (is_array($arg)) {
                return traiter_les_notes($arg);
            } else {
                return traiter_raccourci_notes($arg, $marqueur > 1 ? $marqueur : '');
            }
            break;
        case 'empiler':
            if ($compt_note == 0) {
                // si le marqueur n'a pas encore ete utilise, on le recycle dans la pile courante
                array_push($pile, array(@$les_notes, @$compt_note, $notes_vues, 0));
            } else {
                // sinon on le stocke au chaud, et on en cree un nouveau
                array_push($pile, array(@$les_notes, @$compt_note, $notes_vues, $marqueur));
                ++$next_marqueur; // chaque fois qu'on rempile on incremente le marqueur general
                $marqueur = $next_marqueur; // et on le prend comme marqueur courant
            }
            $les_notes = '';
            $compt_note = 0;
            break;
        case 'depiler':
            #$prev_notes = $les_notes;
            if (strlen($les_notes)) {
                spip_log('notes perdues');
            }
            // si le marqueur n'a pas servi, le liberer
            if (!strlen($les_notes) and $marqueur == $next_marqueur) {
                --$next_marqueur;
            }
            // on redepile tout suite a une fin d'inclusion ou d'un affichage des notes
            list($les_notes, $compt_note, $notes_vues, $marqueur) = array_pop($pile);
            // si pas de marqueur attribue, on le fait
            if (!$marqueur) {
                ++$next_marqueur; // chaque fois qu'on rempile on incremente le marqueur general
                $marqueur = $next_marqueur; // et on le prend comme marqueur courant
            }
            break;
        case 'sauver_etat':
            if ($compt_note or $marqueur > 1 or $next_marqueur > 1) {
                return array($les_notes, $compt_note, $notes_vues, $marqueur, $next_marqueur);
            } else {
                return '';
            } // rien a sauver
            break;
        case 'restaurer_etat':
            if ($arg and is_array($arg)) { // si qqchose a restaurer
                list($les_notes, $compt_note, $notes_vues, $marqueur, $next_marqueur) = $arg;
            }
            break;
        case 'contexter_cache':
            if ($compt_note or $marqueur > 1 or $next_marqueur > 1) {
                return array("$compt_note:$marqueur:$next_marqueur");
            } else {
                return '';
            }
            break;
        case 'reset_all': // a n'utiliser qu'a fins de test
            if (strlen($les_notes)) {
                spip_log('notes perdues [reset_all]');
            }
            $pile = array();
            $next_marqueur = 1;
            $marqueur = 1;
            $les_notes = '';
            $compt_note = 0;
            $notes_vues = array();
            break;
    }
}

define('_RACCOURCI_NOTES', ', *\[\[(\s*(<([^>\'"]*)>)?(.*?))\]\],msS');

function filtrer_entites($texte)
{
    if (strpos($texte, '&') === false) {
        return $texte;
    }

    return $texte; // TODO : vérifier cela
    // filtrer
    $texte = html2unicode($texte);
    // remettre le tout dans le charset cible
    return unicode2charset($texte);
}

function extraire_attribut($balise, $attribut, $complet = false)
{
    if (is_array($balise)) {
        array_walk($balise, create_function('&$a,$key,$t', '$a = extraire_attribut($a,$t);'
                ), $attribut);

        return $balise;
    }
    if (preg_match(
                    ',(^.*?<(?:(?>\s*)(?>[\w:.-]+)(?>(?:=(?:"[^"]*"|\'[^\']*\'|[^\'"]\S*))?))*?)(\s+'
                    .$attribut
                    .'(?:=\s*("[^"]*"|\'[^\']*\'|[^\'"]\S*))?)()([^>]*>.*),isS', $balise, $r)) {
        if ($r[3][0] == '"' || $r[3][0] == "'") {
            $r[4] = substr($r[3], 1, -1);
            $r[3] = $r[3][0];
        } elseif ($r[3] !== '') {
            $r[4] = $r[3];
            $r[3] = '';
        } else {
            $r[4] = trim($r[2]);
        }
        $att = filtrer_entites(str_replace('&#39;', "'", $r[4]));
    } else {
        $att = null;
    }

    if ($complet) {
        return array($att, $r);
    } else {
        return $att;
    }
}

function echappe_retour($letexte, $source = '', $filtre = '')
{
    if (strpos($letexte, "base64$source")) {
        # spip_log(htmlspecialchars($letexte));  ## pour les curieux
        if (strpos($letexte, '<') !== false and
                preg_match_all(',<(span|div)\sclass=[\'"]base64'.$source.'[\'"]\s(.*)>\s*</\1>,UmsS', $letexte, $regs, PREG_SET_ORDER)) {
            foreach ($regs as $reg) {
                $rempl = base64_decode(extraire_attribut($reg[0], 'title'));
                // recherche d'attributs supplementaires
                $at = array();
                foreach (array('lang', 'dir') as $attr) {
                    if ($a = extraire_attribut($reg[0], $attr)) {
                        $at[$attr] = $a;
                    }
                }
                if ($at) {
                    $rempl = '<'.$reg[1].'>'.$rempl.'</'.$reg[1].'>';
                    foreach ($at as $attr => $a) {
                        $rempl = inserer_attribut($rempl, $attr, $a);
                    }
                }
                if ($filtre) {
                    $rempl = $filtre($rempl);
                }
                $letexte = str_replace($reg[0], $rempl, $letexte);
            }
        }
    }

    return $letexte;
}

function traiter_raccourci_notes($letexte, $marqueur_notes)
{
    static $marqueur = 1;
    global $compt_note, $les_notes, $notes_vues;
    global $ouvre_ref, $ferme_ref;

    if (!preg_match_all(_RACCOURCI_NOTES, $letexte, $m, PREG_SET_ORDER)) {
        return array($letexte, array());
    }

    // quand il y a plusieurs series de notes sur une meme page
    $mn = !$marqueur_notes ? '' : ($marqueur_notes.'-');
    $mes_notes = array();
    foreach ($m as $r) {
        list($note_source, $note_all, $ref, $nom, $note_texte) = $r;

        // reperer une note nommee, i.e. entre chevrons
        // On leve la Confusion avec une balise en regardant
        // si la balise fermante correspondante existe
        // Cas pathologique:   [[ <a> <a href="x">x</a>]]

        if (!(isset($nom) and $ref
                and ((strpos($note_texte, '</'.$nom.'>') === false)
                or preg_match(",<$nom\W.*</$nom>,", $note_texte)))) {
            $nom = ++$compt_note;
            $note_texte = $note_all;
        }

        // eliminer '%' pour l'attribut id
        $ancre = $mn.str_replace('%', '_', rawurlencode($nom));

        // ne mettre qu'une ancre par appel de note (XHTML)
        if (!array_key_exists($ancre, $notes_vues)) {
            $notes_vues[$ancre] = 0;
        }
        $att = ($notes_vues[$ancre]++) ? '' : " id='nh$ancre'";

        // creer le popup 'title' sur l'appel de note
        //if ($title = supprimer_tags(propre($note_texte))) {
        //	$title = " title='" . couper($title,80) . "'";
        //}
        $title = $title = " title='".$note_texte."' ";

        // ajouter la note aux notes precedentes
        if ($note_texte) {
            $mes_notes[] = array($ancre, $nom, $note_texte);
        }

        // dans le texte, mettre l'appel de note a la place de la note
        if ($nom) {
            $nom = "$ouvre_ref<a href='#nb$ancre' class='spip_note' rel='footnote'$title$att>$nom</a>$ferme_ref";
        }

        $pos = strpos($letexte, $note_source);
        $letexte = substr($letexte, 0, $pos)
                .code_echappement($nom)
                .substr($letexte, $pos + strlen($note_source));
    }

    return array($letexte, $mes_notes);
}

function traiter_les_notes($notes)
{
    global $ouvre_note, $ferme_note;

    $mes_notes = '';
    if ($notes) {
        $title = 'info_notes';
        foreach ($notes as $r) {
            list($ancre, $nom, $texte) = $r;
            $atts = " href='#nh$ancre' id='nb$ancre' title='$title $ancre' rev='footnote'";
            $mes_notes .= "\n\n"
                    .code_echappement($nom ? "$ouvre_note<a$atts>$nom</a>$ferme_note" : '')
                    .$texte;
        }
        $mes_notes = '<p>'.echappe_retour($mes_notes);
    }

    return $mes_notes;
}

function traiter_retours_chariots($letexte)
{
    $letexte = preg_replace(",\r\n?,S", "\n", $letexte);
    $letexte = preg_replace(',<p[>[:space:]],iS', "\n\n\\0", $letexte);
    $letexte = preg_replace(',</p[>[:space:]],iS', "\\0\n\n", $letexte);

    return $letexte;
}

// ================== les modeles =====================
define('_RACCOURCI_MODELE', '(<([a-z_-]{3,})' # <modele
.'\s*([0-9]*)\s*' # id
.'([|](?:<[^<>]*>|[^>])*?)?' # |arguments (y compris des tags <...>)
.'\s*/?'.'>)' # fin du modele >
.'\s*(<\/a>)?' # eventuel </a>
);

define('_RACCOURCI_MODELE_DEBUT', '@^'._RACCOURCI_MODELE.'@isS');

// http://doc.spip.org/@traiter_modeles
function traiter_modeles($texte, $doublons = false, $echap = '', $connect = '', $liens = null)
{
    // preserver la compatibilite : true = recherche des documents
    if ($doublons === true) {
        $doublons = array('documents' => array('doc', 'emb', 'img'));
    }
    // detecter les modeles (rapide)
    if (strpos($texte, '<') !== false and
            preg_match_all('/<[a-z_-]{3,}\s*[0-9|]+/iS', $texte, $matches, PREG_SET_ORDER)) {
        //include_spip('public/assembler');
        foreach ($matches as $match) {
            // Recuperer l'appel complet (y compris un eventuel lien)

            $a = strpos($texte, $match[0]);
            preg_match(_RACCOURCI_MODELE_DEBUT, substr($texte, $a), $regs);
            $regs[] = ''; // s'assurer qu'il y a toujours un 5e arg, eventuellement vide
            if (count($regs) < 5) {
                Erreur::error('Pb modèle '.substr($texte, $a, 12));
            } else {
                list(, $mod, $type, $id, $params, $fin) = $regs;
                if ($fin and
                        preg_match('/<a\s[^<>]*>\s*$/i', substr($texte, 0, $a), $r)) {
                    $lien = array(
                        'href' => extraire_attribut($r[0], 'href'),
                        'class' => extraire_attribut($r[0], 'class'),
                        'mime' => extraire_attribut($r[0], 'type'),
                    );
                    $n = strlen($r[0]);
                    $a -= $n;
                    $cherche = $n + strlen($regs[0]);
                } else {
                    $lien = false;
                    $cherche = strlen($mod);
                }
                if (!method_exists('Modele', $type)) {
                    $modele = false;
                    Erreur::error("Modèle inexistant : $type");
                } else {
                    $modele = call_user_func(array('Modele', "$type"), $id, $params, $lien);
                }
                if ($modele !== false) {
                    //$modele = protege_js_modeles($modele);
                    $rempl = code_echappement($modele, $echap);

                    $texte = substr($texte, 0, $a)
                            .$rempl
                            .substr($texte, $a + $cherche);
                }

                if (!is_null($liens)) {
                    $params = str_replace($liens[0], $liens[1], $params);
                }
            }
        }
    }

    return $texte;
}

define('_RACCOURCI_URL', '/^\s*(\w*?)\s*(\d+)(\?(.*?))?(#([^\s]*))?\s*$/S');

// http://doc.spip.org/@typer_raccourci
function typer_raccourci($lien)
{
    if (!preg_match(_RACCOURCI_URL, $lien, $match)) {
        return array();
    }
    $f = $match[1];
    // valeur par defaut et alias historiques
    if (!$f) {
        $f = 'article';
    } elseif ($f == 'art') {
        $f = 'article';
    } elseif ($f == 'br') {
        $f = 'breve';
    } elseif ($f == 'rub') {
        $f = 'rubrique';
    } elseif ($f == 'aut') {
        $f = 'auteur';
    } elseif ($f == 'doc' or $f == 'im' or $f == 'img' or $f == 'image' or $f == 'emb') {
        $f = 'document';
    } elseif (preg_match('/^br..?ve$/S', $f)) {
        $f = 'breve';
    }# accents :(
    $match[0] = $f;

    return $match;
}

function calculer_url($ref, $texte = '', $pour = 'url', $connect = '')
{
    $sources = $inserts = $regs = array();
    $texte = traiter_modeles($texte, false, false, $connect, array($inserts, $sources));
    // $texte = corriger_typo($texte);
    $texte = str_replace($inserts, $regs, $texte);

    $r = traiter_lien_implicite($ref, $texte, $pour, $connect);

    return is_array($r) ? $r : traiter_lien_explicite($ref, $texte, $pour, $connect);
}

define('_EXTRAIRE_LIEN', ",^\s*(http:?/?/?|mailto:?)\s*$,iS");

// http://doc.spip.org/@email_valide
function email_valide($adresses)
{
    // eviter d'injecter n'importe quoi dans preg_match
    if (!is_string($adresses)) {
        return false;
    }

    // Si c'est un spammeur autant arreter tout de suite
    if (preg_match(",[\n\r].*(MIME|multipart|Content-),i", $adresses)) {
        spip_log("Tentative d'injection de mail : $adresses");

        return false;
    }

    foreach (explode(',', $adresses) as $v) {
        // nettoyer certains formats
        // "Marie Toto <Marie@toto.com>"
        $adresse = trim(preg_replace(',^[^<>"]*<([^<>"]+)>$,i', '\\1', $v));
        // RFC 822
        if (!preg_match('#^[^()<>@,;:\\"/[:space:]]+(@([-_0-9a-z]+\.)*[-_0-9a-z]+)$#i', $adresse)) {
            return false;
        }
    }

    return $adresse;
}

function quote_amp($u)
{
    return preg_replace(
            "/&(?![a-z]{0,4}\w{2,3};|#x?[0-9a-f]{2,5};)/i", '&amp;', $u);
}

// http://doc.spip.org/@traiter_lien_explicite
function traiter_lien_explicite($ref, $texte = '', $pour = 'url', $connect = '')
{
    if (preg_match(_EXTRAIRE_LIEN, $ref)) {
        return ($pour != 'tout') ? '' : array('', '', '', '');
    }

    $lien = entites_html(trim($ref));

    // Liens explicites
    if (!$texte) {
        $texte = str_replace('"', '', $lien);
        // evite l'affichage de trops longues urls.
        //$lien_court = charger_fonction('lien_court', 'inc');
        //$texte = $lien_court($texte);
        //$texte = "<html>".quote_amp($texte)."</html>";
        $texte = quote_amp($texte);
    }

    // petites corrections d'URL
    if (preg_match('/^www\.[^@]+$/S', $lien)) {
        $lien = 'http://'.$lien;
    } elseif (strpos($lien, '@') && email_valide($lien)) {
        if (!$texte) {
            $texte = $lien;
        }
        $lien = 'mailto:'.$lien;
    }
    $o = call_user_func(array('Lien', 'url_explicite'), $lien, $texte);
    $lien = $o['url'];
    $texte = $o['titre'];
    if ($pour == 'url') {
        return $lien;
    }

    if ($pour == 'titre') {
        return $texte;
    }

    return array('url' => $lien, 'titre' => $texte);
}

// http://doc.spip.org/@traiter_lien_implicite
function traiter_lien_implicite($ref, $texte = '', $pour = 'url', $connect = '')
{
    if (!($match = typer_raccourci($ref))) {
        return false;
    }
    @list($type, , $id, , $args, , $ancre) = array_pad($match, 5, '');
    $lien = call_user_func(array('Lien', "url_$type"), $id, $args, $ancre);
    if ($texte && is_array($lien)) {
        $lien['titre'] = $texte;
    }

    return $lien;
}

// http://doc.spip.org/@traiter_raccourci_lien_lang
function inc_lien_dist($lien, $texte = '', $class = '', $title = '', $hlang = '', $rel = '', $connect = '')
{
    $mode = ($texte and $class) ? 'url' : 'tout';
    $lien = calculer_url($lien, $texte, $mode, $connect);
    if ($mode === 'tout') {
        $texte = $lien['titre'];
        if (!$class and isset($lien['class'])) {
            $class = $lien['class'];
        }
        $lang = isset($lien['lang']) ? $lien['lang'] : '';
        $mime = isset($lien['mime']) ? " type='".$lien['mime']."'" : '';
        $lien = $lien['url'];
    }
    if (substr($lien, 0, 1) == '#') {  # ancres pures (internes a la page)
        $class = 'spip_ancre';
    } elseif (preg_match('/^\s*mailto:/', $lien)) { # pseudo URL de mail
        $class = 'spip_mail';
    } elseif (preg_match('/^<html>/', $texte)) { # cf traiter_lien_explicite
        $class = 'spip_url spip_out';
    } elseif (!$class) {
        # spip_out sur les URLs externes
        /*
          if (preg_match(',^\w+://,iS', $lien)
          AND strncasecmp($lien, url_de_base(), strlen(url_de_base()))
          )
          $class = "spip_out"; # si pas spip_in|spip_glossaire
         */
    }

    if ($title) {
        $title = ' title=\''.str_replace("'", '&apos;', $title).'\'';
    } else {
        $title = '';
    } // $title peut etre 'false'

// rel=external pour les liens externes
    /*
      if (preg_match(',^https?://,S', $lien)
      AND false === strpos("$lien/", url_de_base()))
      $rel = trim("$rel external");
      if ($rel) $rel = " rel='$rel'";
     */
    $lien = '<a href="'.str_replace('"', '&quot;', $lien)."\" class='$class'$lang$title$rel$mime>$texte</a>";

    # ceci s'execute heureusement avant les tableaux et leur "|".
    # Attention, le texte initial est deja echappe mais pas forcement
    # celui retourne par calculer_url.
    # Penser au cas [<imgXX|right>->URL], qui exige typo('<a>...</a>')
    return $lien; //typo($lien, true, $connect);
}

define('_RACCOURCI_ATTRIBUTS', '/^(.*?)([|]([^<>]*?))?([{]([a-z_]*)[}])?$/');

// http://doc.spip.org/@traiter_raccourci_lien_atts
function traiter_raccourci_lien_atts($texte)
{
    $bulle = $hlang = false;
    // title et hreflang donnes par le raccourci ?
    if (preg_match(_RACCOURCI_ATTRIBUTS, $texte, $m)) {
        $n = count($m);
        // |infobulle ?
        if ($n > 2) {
            $bulle = $m[3];
            // {hreflang} ?
            if ($n > 4) {
                // si c'est un code de langue connu, on met un hreflang
                /* 	if (traduire_nom_langue($m[5]) <> $m[5]) {
                  $hlang = $m[5];
                  } elseif (!$m[5]) {
                  $hlang = test_espace_prive() ?
                  $GLOBALS['lang_objet'] : $GLOBALS['spip_lang'];
                  // sinon c'est un italique
                  } else {
                  $m[1] .= $m[4];
                  }
                 */
                // S'il n'y a pas de hreflang sous la forme {}, ce qui suit le |
                // est peut-etre une langue
            } elseif (preg_match('/^[a-z_]+$/', $m[3])) {
                // si c'est un code de langue connu, on met un hreflang
                // mais on laisse le title (c'est arbitraire tout ca...)
                /* 	if (traduire_nom_langue($m[3]) <> $m[3]) {
                  $hlang = $m[3];
                  }
                 */
            }
        }
        $texte = $m[1];
    }

    return array(trim($texte), $bulle, $hlang);
}

define('_RACCOURCI_ANCRE', "/\[#?([^][]*)<-\]/S");

// http://doc.spip.org/@traiter_raccourci_ancre
function traiter_raccourci_ancre($letexte)
{
    if (preg_match_all(_RACCOURCI_ANCRE, $letexte, $m, PREG_SET_ORDER)) {
        foreach ($m as $regs) {
            $letexte = str_replace($regs[0], '<a name="'.entites_html($regs[1]).'"></a>', $letexte);
        }
    }

    return $letexte;
}

define('_EXTRAIRE_DOMAINE', '/^(?:[^\W_]((?:[^\W_]|-){0,61}[^\W_,])?\.)+[a-z]{2,6}\b/Si');

// callback pour la fonction traiter_raccourci_liens()
// http://doc.spip.org/@autoliens_callback
function traiter_autoliens($r)
{
    if (count($r) < 2) {
        return reset($r);
    }
    list($tout, $l) = $r;
    if (!$l) {
        return $tout;
    }
    // reperer le protocole
    if (preg_match(',^(https?):/*,S', $l, $m)) {
        $l = substr($l, strlen($m[0]));
        $protocol = $m[1];
    } else {
        $protocol = 'http';
    }
    // valider le nom de domaine
    if (!preg_match(_EXTRAIRE_DOMAINE, $l)) {
        return $tout;
    }
    // supprimer les ponctuations a la fin d'une URL
    preg_match('/^(.*?)([,.;?]?)$/', $l, $k);
    $url = $protocol.'://'.$k[1];
    $lien = 'inc_lien_dist'; //charger_fonction('lien', 'inc');
    $r = $lien($url, '', '', '', '', 'nofollow').$k[2];
    // si l'original ne contenait pas le 'http:' on le supprime du clic
    return $m ? $r : str_replace('>http://', '>', $r);
}

define('_EXTRAIRE_LIENS', ','.'\[[^\[\]]*(?:<-|->).*?\]'.'|<a\b.*?</a\b'.'|<\w.*?>'.'|((?:https?:/|www\.)[^"\'\s\[\]\}\)<>]*)'.',imsS');

// Les URLs brutes sont converties en <a href='url'>url</a>
// http://doc.spip.org/@traiter_raccourci_liens
function traiter_raccourci_liens($t)
{
    return preg_replace_callback(_EXTRAIRE_LIENS, 'traiter_autoliens', $t);
}

define('_RACCOURCI_LIEN', "/\[([^][]*?([[]\w*[]][^][]*)*)->(>?)([^]]*)\]/msS");

// http://doc.spip.org/@expanser_liens
function expanser_liens($texte, $connect = '')
{
    //$texte = pipeline('pre_liens', $texte);
    $texte = traiter_raccourci_ancre($texte);
    $texte = traiter_raccourci_liens($texte);
    $sources = $inserts = $regs = array();
    if (preg_match_all(_RACCOURCI_LIEN, $texte, $regs, PREG_SET_ORDER)) {
        $lien = 'inc_lien_dist'; //('lien', 'inc');
        foreach ($regs as $k => $reg) {
            $inserts[$k] = '@@SPIP_ECHAPPE_LIEN_'.$k.'@@';
            $sources[$k] = $reg[0];
            $texte = str_replace($sources[$k], $inserts[$k], $texte);
            list($titre, $bulle, $hlang) = traiter_raccourci_lien_atts($reg[1]);
            $r = $reg[count($reg) - 1];
            // la mise en lien automatique est passee par la a tort !
            // corrigeons pour eviter d'avoir un <a...> dans un href...
            if (strncmp($r, '<a', 2) == 0) {
                $href = extraire_attribut($r, 'href');
                // remplacons dans la source qui peut etre reinjectee dans les arguments
                // d'un modele
                $sources[$k] = str_replace($r, $href, $sources[$k]);
                // et prenons le href comme la vraie url a linker
                $r = $href;
            }
            $regs[$k] = $lien($r, $titre, '', $bulle, $hlang, '', $connect);
        }
    }
    // Je traite les modeles des ancres des liens
    if (Config::detect()) {
        $regs2 = array();
        foreach ($regs as $k) {
            array_push($regs2, $t = traiter_raccourcis($k));
        }
    }
    // on passe a traiter_modeles la liste des liens reperes pour lui permettre
    // de remettre le texte d'origine dans les parametres du modele
    $texte = traiter_modeles($texte, false, false, $connect, array($inserts, $sources));
    //$texte = corriger_typo($texte);
    $texte = str_replace($inserts, $regs, $texte);

    return $texte;
}

function traiter_raccourcis($letexte, $para = true)
{
    $original = $letexte;
    global $spip_raccourcis_typo, $class_spip_plus, $debut_intertitre, $fin_intertitre, $debut_gras, $fin_gras, $debut_italique, $fin_italique;

    $spip_raccourcis_typo = array(
        array(
            /* 4 */ '/(^|[^{])[{][{][{]/S',
            /* 5 */ '/[}][}][}]($|[^}])/S',
            /* 6 */ "/(( *)\n){2,}(<br\s*\/?".'>)?/S',
            /* 7 */ '/[{][{]/S',
            /* 8 */ '/[}][}]/S',
            /* 9 */ '/[{]/S',
            /* 10 */ '/[}]/S',
            /* 11 */ "/(?:<br\s*\/?".'>){2,}/S',
            /* 12 */ "/<p>\n*(?:<br\s*\/?".">\n*)*/S",
            /* 13 */ '/<quote>/S',
            /* 14 */ "/<\/quote>/S",
            /* 15 */ "/<\/?intro>/S",
        ),
        array(
            /* 4 */ "\$1\n\n".$debut_intertitre,
            /* 5 */ $fin_intertitre."\n\n\$1",
            /* 6 */ '<p>',
            /* 7 */ $debut_gras,
            /* 8 */ $fin_gras,
            /* 9 */ $debut_italique,
            /* 10 */ $fin_italique,
            /* 11 */ '<p>',
            /* 12 */ '<p>',
            /* 13 */ '<blockquote><p>',
            /* 14 */ '</blockquote><p>',
            /* 15 */ '',
        ),
    );

    // Appeler les fonctions de pre_traitement
    //$letexte = pipeline('pre_propre', $letexte);
    $letexte = echappe_html($letexte);

    //~ double accolades
    $pattern = '/\{\}/';
    $replace = '';
    $letexte = preg_replace($pattern, $replace, $letexte);

    // Gerer les notes (ne passe pas dans le pipeline)
    list($letexte, $mes_notes) = notes($letexte);

    $letexte = expanser_liens($letexte);

    $letexte = supprimer_caracteres_illegaux($letexte);

    //
    // Tableaux
    //
    // ne pas oublier les tableaux au debut ou a la fin du texte
    $letexte = preg_replace(",^\n?[|],S", "\n\n|", $letexte);
    $letexte = preg_replace(",\n\n+[|],S", "\n\n\n\n|", $letexte);
    $letexte = preg_replace(",[|](\n\n+|\n?$),S", "|\n\n\n\n", $letexte);

    if (preg_match_all(',[^|](\n[|].*[|]\n)[^|],UmsS', $letexte, $regs, PREG_SET_ORDER)) {
        foreach ($regs as $t) {
            $letexte = str_replace($t[1], traiter_tableau($t[1]), $letexte);
        }
    }

    $letexte = "\n".trim($letexte);

    // les listes
    if (strpos($letexte, "\n-*") !== false or strpos($letexte, "\n-#") !== false) {
        $letexte = traiter_listes($letexte);
    }

    // Proteger les caracteres actifs a l'interieur des tags html

    if (preg_match_all(_RACCOURCI_BALISE, $letexte, $regs, PREG_SET_ORDER)) {
        foreach ($regs as $reg) {
            $insert = strtr($reg[0], _RACCOURCI_PROTEGER, _RACCOURCI_PROTECTEUR);
            $letexte = str_replace($reg[0], $insert, $letexte);
        }
    }

    // Traitement des alineas
    list($a, $b) = definir_raccourcis_alineas();
    $letexte = preg_replace($a, $b, $letexte);
    //  Introduction des attributs class_spip* et autres raccourcis
    list($a, $b) = $spip_raccourcis_typo;
    $letexte = preg_replace($a, $b, $letexte);
    $letexte = preg_replace('@^\n<br />@S', '', $letexte);

    // Retablir les caracteres proteges
    $letexte = strtr($letexte, _RACCOURCI_PROTECTEUR, _RACCOURCI_PROTEGER);

    // Appeler les fonctions de post-traitement
    //$letexte = pipeline('post_propre', $letexte);
    // Remplacer les sauts simples par une espace
    $pattern = '/[\n]([^\n_-])/mU';
    $replace = ' $1';
    $letexte = preg_replace($pattern, $replace, $letexte);
    //~ sous titres onglets
    $pattern = '/^\+\+\+\+(.*)$/m';
    $replace = "<h3 class=\'onglet\'>$1</h3>";    // remplacer accolades dans h3 au cas ou !!!
    $letexte = preg_replace($pattern, $replace, $letexte);
    //~ onglets
    $pattern = '/<onglets>[ \n]*([^ \n].*)\n\n(.*)<\/onglets>/msUi';
    $replace = "<div class=\'onglets\'>\n<h3 class=\'onglet\'>$1</h3>\n$2</div>";    // remplacer accolades dans h3 au cas ou !!!
    $letexte = preg_replace($pattern, $replace, $letexte);

    if (Config::detect()) {
        return $original;
    }
    $ret = echappe_retour($letexte).notes($mes_notes);
    if ($para) {
        $ret = paragrapher($ret);
    }
    $tidy = tidy_parse_string('<!DOCTYPE html><html><head><title>Test</title></head><body>'.$ret.'</body></html>', array('wrap' => 0), 'utf8');
    if ($tidy) {
        $ret = preg_replace(',</?body>,', '', tidy_get_body($tidy));
    }
    $tidy->diagnose();

    return trim($ret);
}

function spip2wp($str)
{
    return traiter_raccourcis($str);

    // liens internes qui peuvent ne plus être internes !
    $pattern = '/\[([^\]]*)->(art|rub|img|doc|emb)?([0-9]+)\]/U';
    $str = preg_replace_callback($pattern, function ($matches) { // TODO : article sans art
        Liens::add($matches[1], $matches[2], $matches[3]);

        return $matches[0];
    }, $str);
    // images et docs
    $pattern = '/<(img|doc|emb)([0-9]+)\|?(left|right|center)?[^>]*>/';
    $str = preg_replace_callback($pattern, function ($matches) {
        Documents::add($matches[2]);
        Erreur::error('Ajout document '.$matches[2]."\n");

        return $matches[0];
    }, $str);
    // galeries & autre pour récupérer les images
    $pattern = '/<([a-z]+[0-9]*)\|.*images=([0-9, ]+)[^>]*>/';
    $str = preg_replace_callback($pattern, function ($matches) {
        $ids = explode(',', $matches[2]);
        $verif = array();
        foreach ($ids as $id) {
            if ($id && ctype_digit($id) && ((int) $id) > 0) {
                array_push($verif, $id);
            } else {
                Erreur::error('Paramètre images eronné : '.$matches[0]);
            }
        }
        Documents::add($verif);

        return $matches[0];
    }, $str);

    return $str;
}
