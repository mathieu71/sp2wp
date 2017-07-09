<?php

if (defined('__ARTICLE__')) {
    return;
}
define('__ARTICLE__', 1);

class Article implements ToWordPress
{
    private $id;
    private $wp_id;
    private $titre;
    // private $url;
    private $chapo;
    private $descriptif;
    private $texte;
    private $date;
    private $date_gmt;
    private $date_modif;
    private $date_modif_gmt;
    private $rubrique;
    private $auteurs;
    private $menu_order;
    private $url;
    private $redirection;

    private function url()
    {
        $sql = 'select url,id_objet from '.Config::$spip_prefix."urls where type='article' and id_objet=? order by date desc limit 1";
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $this->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            Erreur::error("Pas d'url : article non publié ?");
        }

        if ('Contact' === trim($this->titre)) {
            $type = 'page';
            $slug = 'contactez-nous';
        } else {
            $slug = Config::supprimer_numero($this->titre);
        }
        $this->url = Urls::add($slug);
    }

    public function is_redirection()
    {
        return $this->redirection;
    }

    public function the_url()
    {
        return $this->url;
    }

    public function __construct($id)
    {
        Erreur::contexte_push('Article', $id);

        $this->auteurs = array();

        $sql = 'SELECT id_article AS ID, date, id_rubrique, chapo, descriptif, texte, titre , date_modif, statut FROM '.Config::$spip_prefix.'articles  WHERE id_article=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row) {
            $this->id = $id;
            $this->rubrique = $row ['id_rubrique'];
            $this->titre = $row ['titre'];
            $this->chapo = $row ['chapo'];
            $this->redirection = false;

            $this->texte = ''; // on ajoute les redirections au bout du texte et on supprime le chapo de redirection
            if ((strlen($this->chapo) > 0) && substr($this->chapo, 0, 1) === '=') {
                $url = substr($this->chapo, 1);
                Erreur::error("Attention : Redirection vers $url");

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

                if (in_array($lien['host'], Config::$old_domain) and array_key_exists('query', $lien)) { // 1
                    $qa = array();
                    parse_str($lien['query'], $qa);
                    if (array_key_exists('page', $qa) && $qa['page'] == 'galerie') { // 2
                        if ($this->id == $qa['id_article']) {
                            $this->chapo = '';
                            $ids = [];
                            $images = 'SELECT l.id_document AS id,d.extension FROM '.Config::$spip_prefix.'documents AS d JOIN '.Config::$spip_prefix."documents_liens AS l ON (d.id_document=l.id_document AND l.objet='article') WHERE d.extension IN ('jpg','jpeg','JPG','JPEG') AND l.id_objet=".$this->id;
                            $res2 = Config::$db_spip->query($images);
                            $row2 = $res2->fetch_assoc();
                            while ($row2) {
                                Documents::add($row2['id']);
                                // vérification que le document a bien été ajouté (fichier existant)
                                if (Documents::get_doc($row2['id'])) {
                                    $ids[] = $row2['id'];
                                }
                                $row2 = $res2->fetch_assoc();
                            }
                            $this->texte = '<galerie|images='.implode(',', $ids).'>'; //[gallery link="file"]';
                        } else {
                            // TODO : on retourne direct et on enleve cet article ... À voir ...
                            $this->redirection = true;
                            $error = "Redirection vers une page de galerie différente de l'article courant => mettre à la poubelle l'article";
                            Erreur::error($error);

                            return;
                        }
                    }
                }
            }

            $this->texte .= spip2wp($row ['texte']);
            $this->descriptif = spip2wp($row ['descriptif']);
            valider_date($row ['date'], $this->date, $this->date_gmt);
            valider_date($row ['date_modif'], $this->date_modif, $this->date_modif_gmt);
            $this->statut = $row ['statut'];

            // menu pour articles numérotés
            $this->menu_order = 0;
            $pattern = '/([0-9]+)\.[[:blank:]]+(.*)/';
            preg_replace_callback($pattern, function ($matches) {
                $this->menu_order = $matches [1];

                return $matches [2];
            }, $row ['titre']);

            $this->titre = Config::supprimer_numero($row ['titre']);
            // les auteurs
            switch (Config::$spip_version) {
            case 3: {
                $sql2 = 'SELECT id_auteur as ID FROM '.Config::$spip_prefix."auteurs_liens WHERE id_objet=? and objet='article'";
                break;
            }
            case 2: {
                $sql2 = 'SELECT id_auteur as ID FROM '.Config::$spip_prefix.'auteurs_articles WHERE id_article=?';
                break;
            }

            };

            $stmt2 = Config::$db_spip->prepare($sql2);
            $stmt2->bind_param('d', $id);

            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($row2 = $res2->fetch_assoc()) {
                array_push($this->auteurs, $row2 ['ID']);
                Auteurs::add($row2 ['ID']);
            }

            // les évenements
            $sql3 = 'SELECT id_evenement as ID FROM '.Config::$spip_prefix.'evenements WHERE id_article=?';
            $stmt3 = Config::$db_spip->prepare($sql3);
            if ($stmt3) {
                $stmt3->bind_param('d', $id);
            //echo "=== evenements === \n";
            $stmt3->execute();
                $res3 = $stmt3->get_result();
                while ($row3 = $res3->fetch_assoc()) {
                    //echo $row ["ID"]." ajouté \n";
                Evenements::add($row3 ['ID']);
                }
            } else {
                echo 'pas de table évenements'.PHP_EOL;
            }

            // les mots cles
            $sql4 = 'SELECT spip_mots.id_mot AS ID FROM '.Config::$spip_prefix.'mots JOIN '
                    .Config::$spip_prefix.'mots_articles ON ('
                    .Config::$spip_prefix.'mots.id_mot = '.Config::$spip_prefix.'mots_articles.id_mot) '
                    .' WHERE id_article=? AND id_mot NOT IN ('.implode(', ', Config::$mots_a_exclure).')';
            $stmt4 = Config::$db_spip->prepare($sql4);
            if ($stmt4) {
                $stmt4->bind_param('d', $id);
            //echo "=== mots clés === \n";
            $stmt4->execute();
                $res4 = $stmt4->get_result();
                while ($row4 = $res4->fetch_assoc()) {
                    // echo "Article $id : mot ".$row ["ID"]." ajouté \n";
                MotsCles::add($row4 ['ID']);
                }
            } else {
                echo 'pas de table mots clés'.PHP_EOL;
            }
        }
        $this->url();

        Erreur::contexte_pop();
    }

    private function spip_to_wp_statut()
    {
        if ($this->statut === 'publie') {
            return 'publish';
        } elseif ($this->statut === 'poubelle') {
            return 'trash';
        } else {
            return 'draft';
        }
    }

    public function to_wp($site)
    {
        Erreur::contexte_push('Article', $this->id);

        $sql = 'INSERT INTO wp'.$site.'_posts (post_type, post_author, post_date, post_date_gmt, post_status,  post_modified, post_modified_gmt, post_name, comment_status) VALUES '."('post',?,?,?,?,?,?,?,'closed')";
        $auteur = Auteurs::prolifique(array_keys(Articles::$articles), $this->auteurs);
        //var_dump($this->auteurs); echo "auteur : $auteur\n"; exit(0);
        $statut = $this->spip_to_wp_statut($this->statut);
        $stmt = Config::$db_wp->prepare($sql);

        $stmt->bind_param('dssssss', $auteur, $this->date, $this->date_gmt, $statut, $this->date_modif, $this->date_modif_gmt, $this->url);
        $stmt->execute();

        if (Config::$db_wp->error) {
            Erreur::error('Article non inséré : '.Config::$db_wp->error);
        }
        $this->wp_id = Config::$db_wp->insert_id;

        if (Config::$RubriqueEnPage) {
            $rub = Rubriques::get_rub(Rubriques::rub_racine());
        } else {
            $rub = Rubriques::get_rub($this->rubrique);
        }
        $taxonomy = $rub->taxonomy_id();

        $sql = 'INSERT INTO wp'.$site.'_term_relationships(object_id, term_taxonomy_id) VALUES (?,?)';
        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('dd', $this->wp_id, $taxonomy);
        $stmt->execute();

        if (Config::$db_wp->error) {
            Erreur::error("Lien article->rubrique non inséré : $sql ".Config::$db_wp->error);
        }
        Erreur::contexte_pop();
    }

    private function insert_logo($site)
    {
        $fn = '';
        $last = 0;
        foreach (array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'JPG', 'JPEG', 'PNG', 'GIF', 'BMP') as $ext) {
            $fn = Config::$spip_img_path.'arton'.$this->id.".$ext";
            if (file_exists($fn)) {
                $tmp = Config::copy_file('arton'.$this->id.".$ext");
                $last = $tmp['wp_id'];
                break;
            } else {
                // Erreur::error("Vignette d'article ".$this->id." introuvable, type inconnu ou pas de vignette ?");
            }
        }
        if ($last != 0) {
            $sql = 'INSERT INTO wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_thumbnail_id',?);";
            $stmt = Config::$db_wp->prepare($sql);
            $stmt->bind_param('ss', $this->wp_id, $last);
            $stmt->execute();

            if (Config::$db_wp->error) {
                Erreur::error("Logo Article : $sql ".Config::$db_wp->error);
            }
        } else {
            return; // pas de logo trouvé, on retourne
        }
    }

    public function update_refs($site)
    {
        Erreur::contexte_push('Liens Article', $this->id);
        $chapo = ($this->chapo ? "<div class='chapo'>".$this->chapo."</div>\n" : '');
        $desc = $this->descriptif ? spip2wp($this->descriptif) : '';
        $texte = spip2wp("$chapo".$this->texte);
        $type = 'post';
        $order = 0;
        $titre = strip_tags(
                preg_replace_callback('/^([0-9]+)\. (.*)$/', function ($matches) use (&$order, &$type) {
                    if (count($matches) == 3) {
                        list($tout, $numero, $titre) = $matches;
                        // echo "article numeroté : $tout : $numero : $titre\n";
                        $type = 'page';
                        $order = intval($matches[1]);

                        return $matches[2];
                    }
                }, $this->titre)
        );

        if ('Contact' === trim($this->titre)) {
            $type = 'page';
            $order = 1000;
            Config::set_contact($this->wp_id);
        }
        //echo "=> type = $type et ordre = $order\n";
        $rub = Rubriques::get_rub($this->rubrique);
        if ($rub && Config::$ArticlesEnPage) { // TODO : paramètre articles en page
            $page = $rub->article();
            $type = 'page';
        }
        if ($type !== 'page') {
            $page = 0;
        }
        $sql = 'UPDATE wp'.$site.'_posts SET post_title=?, post_excerpt=?, post_content=?, post_type=?, menu_order=?, post_parent=? WHERE ID=?';

        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('ssssddd', $titre, $desc, $texte, $type, $order, $page, $this->wp_id);
        $stmt->execute();
        // $res = $stmt->get_result();

        if (Config::$db_wp->error) {
            Erreur::error("Article Liens : $sql ".Config::$db_wp->error);
        }

        $this->insert_logo($site);

        Erreur::contexte_pop();
    }

    public function id()
    {
        return $this->id;
    }

    public function wp_id()
    {
        return $this->wp_id;
    }

    public function titre()
    {
        return Config::supprimer_numero($this->titre);
    }

    public function auteurs()
    {
        return $this->auteurs;
    }
}
