<?php

if (defined('__DOCUMENTS__')) {
    return;
}
define('__DOCUMENTS__', 1);

class Documents
{
    private static $documents = null;
    private static $stmt_query;
    private static $stmt_insert;
    private static $stmt_insert_file;
    private static $stmt_insert_file_meta;

    public static function get_query()
    {
        if (!self::$stmt_query) {
            self::$stmt_query = Config::$db_spip->prepare('SELECT id_vignette, titre, date, maj, descriptif, fichier, taille, largeur, hauteur, mode, extension FROM '.Config::$spip_prefix.'documents where id_document=?');
        }

        return self::$stmt_query;
    }

    public static function get_insert($site)
    {
        if (!self::$stmt_insert) {
            self::$stmt_insert = Config::$db_wp->prepare('INSERT INTO wp'.$site."_posts (post_type, post_author , post_date, post_date_gmt ,post_status, post_content , post_title , post_modified, post_modified_gmt, comment_status, post_mime_type,guid  ) VALUES ('attachment',0, ?,?,'inherit',?,?,?,?,'closed',?,?)");
        }

        return self::$stmt_insert;
    }

    public static function get_insert_file($site)
    {
        if (!self::$stmt_insert_file) {
            self::$stmt_insert_file = Config::$db_wp->prepare('INSERT INTO wp'.$site."_postmeta (post_id, meta_key, meta_value) VALUES (?,'_wp_attached_file',?);");
        }

        return self::$stmt_insert_file;
    }

    public static function get_insert_file_meta($site)
    {
        if (!self::$stmt_insert_file_meta) {
            self::$stmt_insert_file_meta = Config::$db_wp->prepare('INSERT INTO wp'.$site."_postmeta (post_id, meta_key, meta_value) VALUES (?,'_wp_attachment_metadata',?);");
        }

        return self::$stmt_insert_file_meta;
    }

    public static function add($id)
    {
        if (is_null(self::$documents)) {
            self::$documents = array();
        }
        if (is_array($id)) {
            foreach ($id as $i) {
                self::add($i);
            }
        } else {
            assert($id);
            if (!array_key_exists($id, self::$documents)) {
                $doc = new Document($id);
                if (!$doc->inexistant()) {
                    self::$documents [$id] = $doc;
                }
            }
        }
    }

    public static function remove($id)
    { // en cas d'erreur
        unset(self::$documents [$id]);
    }

    public static function to_wp($site)
    {
        if (is_array(self::$documents)) {
            foreach (self::$documents as $doc) {
                $doc->to_wp($site);
            }
        }
    }

    public static function get_doc($id)
    {
        if (array_key_exists($id, self::$documents)) {
            return self::$documents [$id];
        }

        return;
    }

    public static function attacher($site)
    {
        if (is_null(Articles::$articles)) { // pas d'article publié...
            return;
        }
        $articles = array_keys(Articles::$articles);
        $les_articles = ' ('.implode(',', $articles).') ';

        $rubriques = array_keys(Rubriques::$rubriques);
        $les_rubriques = ' ('.implode(',', $rubriques).') ';

        //echo "$les_articles \n $les_rubriques \n\n";
        $sql = 'select id_document as doc,'.
                '(select id_objet from '.Config::$spip_prefix.'documents_liens join '.Config::$spip_prefix.'articles where l.id_document='.Config::$spip_prefix."documents_liens.id_document  and objet='article' and id_objet IN $les_articles and ".Config::$spip_prefix.'articles.id_article = '.Config::$spip_prefix.'documents_liens.id_objet order by '.Config::$spip_prefix.'articles.date limit 1) as art, '.
                '(select id_objet from '.Config::$spip_prefix.'documents_liens join '.Config::$spip_prefix.'rubriques where l.id_document='.Config::$spip_prefix."documents_liens.id_document  and objet='rubrique' and id_objet IN $les_rubriques and ".Config::$spip_prefix.'rubriques.id_rubrique = '.Config::$spip_prefix.'documents_liens.id_objet order by '.Config::$spip_prefix.'rubriques.date limit 1) as rub '.
                'from '.Config::$spip_prefix.'documents_liens as l  group by id_document having  not (art is null and rub is null) ;';
        // echo $sql;
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->execute();
        $res = $stmt->get_result();

        $row = $res->fetch_assoc();
        while ($row) {
            if ($doc = self::get_doc($row['doc'])) {
                $doc_wpid = $doc->wp_id();

                if ($row['art']) {
                    //echo "lier {$row['doc']} à {$row['art']}\n";
                    if ($art = Articles::get_art($row['art'])) {
                        $art_wpid = $art->wp_id();
// 			  echo "X=art==> $doc_wpid -> $art_wpid ".$row["art"]." / " . $row['doc'] ." \n";
                    }
                    $sql = 'UPDATE wp'.$site.'_posts SET post_parent=? where ID=?;';
                    $stmt = Config::$db_wp->prepare($sql);
                    $stmt->bind_param('dd', $art_wpid, $doc_wpid);
                    $stmt->execute();
                    //echo "$sql\n";
                }
                if ($row['rub']) {
                    //echo "lier {$row['doc']} à {$row['rub']}\n";
                    if ($rub = Rubriques::get_rub($row['rub'])) {
                        $rub_wpid = $rub->article();
// 			  echo "X=rub==> $doc_wpid -> $rub_wpid\n";
                    }
                    $sql = 'UPDATE wp'.$site.'_posts SET post_parent=? where ID=?;';
                    $stmt = Config::$db_wp->prepare($sql);
                    $stmt->bind_param('dd', $rub_wpid, $doc_wpid);
                    $stmt->execute();
                }
            } else {
                $doc = $row['doc'];
                // echo "Ajouter document non lié : $doc\n";
                self::add($doc);
            }

            $row = $res->fetch_assoc();
        }
    }
}
