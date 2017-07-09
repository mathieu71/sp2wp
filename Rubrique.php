<?php

if (defined('__RUBRIQUE__')) {
    return;
}
define('__RUBRIQUE__', 1);

class Rubrique implements ToWordPress
{
    private $id;
    private $term;
    private $taxonomy;
    private $titre;
    private $descriptif;
    private $texte;
    private $article; // post pour le texte de la rubrique
    private $parent;
    private $url;
    private $intro; // soit le descriptif (pour le slogan du site) , soit le texte formaté... (pour une page)
    private $date;
    private $date_gmt;
    private $date_modif;
    private $date_modif_gmt;

    private function url()
    {
        $sql = 'select url,id_objet from '.Config::$spip_prefix."urls where type='rubrique' and id_objet=? order by date desc limit 1";
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $this->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        $slug = Config::supprimer_numero($this->titre);

        $this->url = Urls::add($slug);
        if (!$row) {
            Erreur::error("Pas d'url");
        }
    }

    public function the_url()
    {
        return $this->url;
    }

    public function __construct($id)
    {
        Erreur::contexte_push('Rubrique', $id);

        $sql = 'SELECT id_rubrique, titre ,id_parent, descriptif, texte, date, maj  FROM '.Config::$spip_prefix.'rubriques WHERE id_rubrique=(?)';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) {
            $this->id = (int) $row ['id_rubrique'];
            $this->parent = (int) $row ['id_parent'];
            $this->titre = $row ['titre'];
            $this->descriptif = spip2wp($row ['descriptif']);
            $this->texte = spip2wp($row ['texte']);
            valider_date($row ['date'], $this->date, $this->date_gmt);
            valider_date($row ['maj'], $this->date_modif, $this->date_modif_gmt);
        }
        $stmt->close();

        $this->url();
        Erreur::contexte_pop();
    }

    public function remplir()
    {
        $sql = 'SELECT  id_article AS ID FROM '.Config::$spip_prefix.'articles WHERE id_rubrique=(?) ORDER BY date DESC';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $this->id);

        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            Articles::add($row ['ID']);
        }

        // breves
        $sql = 'SELECT  id_breve AS ID FROM '.Config::$spip_prefix.'breves WHERE id_rubrique=(?) ORDER BY id_breve';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $this->id);

        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            Breves::add($row ['ID']);
        }
    }

    public function url_page()
    {
        return Config::$wp_folder.'?p='.$this->article;
    }

    public function url_category()
    {
        return Config::$wp_folder.'?cat='.$this->term;
    }

    private function add_category($site)
    {
        $sql = 'INSERT INTO wp'.$site.'_terms (name,slug) VALUES (?,?)';
        $stmt = Config::$db_wp->prepare($sql);
        $name = $titre = preg_replace('/^[0-9]+\. (.*)/', '$1', $this->titre);
        $slug = $this->url; //'cat' . $this->id;
        if (Config::$RubriqueEnPage) {
            $slug = 'rubrique-'.$slug; // pas de même slug pour la catégorie et la page
        }
        if ($this->id == Rubriques::rub_racine()) {
            $name = 'Actualités'; // rubrique racine => Catégorie Actualités
            $slug = 'actualites'; // pas de même slug pour la catégorie et la page
        }
        $stmt->bind_param('ss', $name, $slug);
        $stmt->execute();
        if (Config::$db_wp->error) {
            Erreur::error('Rubrique non insérée : '.Config::$db_wp->error);
        }
        $this->term = Config::$db_wp->insert_id;

        $sql2 = 'INSERT INTO wp'.$site."_term_taxonomy (term_id,taxonomy ) VALUES (?,'category');";
        $stmt2 = Config::$db_wp->prepare($sql2);
        $term = $this->term;

        $stmt2->bind_param('d', $term);
        $stmt2->execute();
        if (Config::$db_wp->error) {
            Erreur::error('Rubrique terme taxonomie catégorie non inséré : '.Config::$db_wp->error);
        }
        $this->taxonomy = Config::$db_wp->insert_id;
    }

    private function add_page($site)
    {
        // === insertion de la page par la même occasion ===
        $sql2 = 'INSERT INTO wp'.$site.'_posts '.'(post_type, post_date, post_date_gmt, post_status, to_ping, pinged, post_modified, post_modified_gmt, post_name, comment_status) VALUES '."('page',?,?,'publish','','',?,?,?,'closed')";
        $stmt2 = Config::$db_wp->prepare($sql2);
        $stmt2->bind_param('sssss', $this->date, $this->date_gmt, $this->date_modif, $this->date_modif_gmt, $this->url);
        $stmt2->execute();

        if (Config::$db_wp->error) {
            Erreur::error("Page de rubrique non insérée : $sql ".Config::$db_wp->error);
        }
        $this->article = Config::$db_wp->insert_id;
    }

    public function to_wp($site)
    {
        Erreur::contexte_push('Rubrique', $this->id);

        if (Config::$RubriqueEnPage) {
            $this->add_page($site);
            if ($this->id == Rubriques::rub_racine()) {
                $this->add_category($site);
            } else {
                $this->taxonomy = Rubriques::get_rub(Rubriques::rub_racine())->taxonomy;
            }
//            return Rubriques::get_rub(Rubriques::rub_racine())->taxonomy;
        } else {
            $this->add_category($site);
            if (Config::$PlusPage) {
                $this->add_page($site);
            }
//            return $this->taxonomy;
        }
        Erreur::contexte_pop();
    }

    public function desc()
    {
        return $this->texte;
    }

    public function titre()
    {
        return Config::supprimer_numero($this->titre);
    }

    public function taxonomy_id()
    {
        return $this->taxonomy;
    }

    private function insert_logo($site)
    {
        $fn = '';
        $last = 0;
        foreach (array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'JPG', 'JPEG', 'PNG', 'GIF', 'BMP') as $ext) {
            $fn = Config::$spip_img_path.'rubon'.$this->id.".$ext";
            if (file_exists($fn)) {
                $tmp = Config::copy_file('rubon'.$this->id.".$ext");
                $last = $tmp['wp_id'];
                break;
            } else {
                //Erreur::error("Vignette de rubrique ".$this->id." introuvable, type inconnu ou pas de vignette ?");
            }
        }
        if ($last == 0) {
            return; // pas de logo trouvé, on retourne
        }
        $sql = 'INSERT INTO wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_thumbnail_id',?);";
        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('ss', $this->article, $last);
        $stmt->execute();

        if (Config::$db_wp->error) {
            Erreur::error("Logo Rubrique : $sql ".Config::$db_wp->error);
        }
    }

    public function id()
    {
        return $this->id;
    }

    public function article()
    {
        return $this->article;
    }

    public function wp_id()
    {
        // TODO : si pas en page et pas PlusPage, alors retourner l'url de la catégorie dnas Rubriques.php
        return $this->article;
    }

    public function update_refs($site)
    {
        Erreur::contexte_push('Liens Rubrique', $this->id);
        $texte = spip2wp($this->texte);
        $desc = spip2wp($this->descriptif);

        $parent = 0;
        $rub_parent = Rubriques::get_rub($this->parent);

        $order = 0;
        $titre = preg_replace_callback('/^([0-9]+)\. (.*)$/', function ($matches) use (&$order) {
            if (count($matches) == 3) {
                list($tout, $numero, $titre) = $matches;
                // echo "rubrique numerotée : $tout : $numero : $titre\n";
                $order = intval($matches[1]) * 10;

                return $matches[2];
            }
        }, $this->titre);

        if (Config::$RubriqueEnPage || Config::$PlusPage) {
            $this->intro = $desc;
            if ($rub_parent && ($rub_parent->id() != Rubriques::rub_racine())) {
                $parent = $rub_parent->article;
            }

            $sql = 'UPDATE wp'.$site.'_posts SET post_excerpt=?,post_content=?,post_title=?,post_parent=?,menu_order=?  WHERE ID=?';
            $stmt = Config::$db_wp->prepare($sql);
            $stmt->bind_param('sssddd', $desc, $texte, $titre, $parent, $order, $this->article);
            $stmt->execute();

            if (Config::$db_wp->error) {
                Erreur::error("Rubrique Liens : $sql ".Config::$db_wp->error);
            }
            $this->insert_logo($site);
        }
        if (!Config::$RubriqueEnPage || Config::$PlusPage) {
            $sql = 'UPDATE wp'.$site.'_term_taxonomy SET description=?,parent=? WHERE term_taxonomy_id=?';
            $stmt = Config::$db_wp->prepare($sql);
            $tmp = ($desc ? '<div class="descriptif">'.$desc."</div>\n" : '').$texte;
            if ($this->parent !== Rubriques::rub_racine()) {
                $tax_parent = $this->taxonomy_id();
            } else {
                $tax_parent = 0;
            }
            $stmt->bind_param('sdd', $tmp, $rub_parent->taxonomy, $tax_parent);
            $stmt->execute();

            if (Config::$db_wp->error) {
                Erreur::error("Rubrique Liens : $sql ".Config::$db_wp->error);
            }

            $this->intro = $texte;
        }
        Erreur::contexte_pop();
    }

    public function intro()
    {
        return $this->intro;
    }
}
