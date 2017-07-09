<?php

if (defined('__BREVE__')) {
    return;
}
define('__BREVE__', 1);

class Breve implements ToWordPress
{
    private $id;
    private $wp_id;
    private $titre;
    // private $url;
    private $texte;
    private $date;
    private $date_gmt;
    private $date_modif;
    private $date_modif_gmt;
    private $rubrique;
    private $menu_order;
    private $url;
    private $redirection;

    private function url()
    {
        $sql = 'select url,id_objet from '.Config::$spip_prefix."urls where type='breve' and id_objet=? order by date desc limit 1";
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $this->id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            Erreur::error("Pas d'url : breve non publié ?");
        }

        $slug = Config::supprimer_numero($this->titre);
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
        Erreur::contexte_push('BREVE', $id);

        $this->auteurs = array();

        $sql = 'SELECT id_breve AS ID, maj, id_rubrique, texte, titre , maj, statut FROM '.Config::$spip_prefix.'breves  WHERE id_breve=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row) {
            $this->id = $id;
            $this->rubrique = $row ['id_rubrique'];
            $this->titre = $row ['titre'];
            $this->redirection = false;

            $this->texte = spip2wp($row ['texte']);
            valider_date($row ['maj'], $this->date, $this->date_gmt);
            valider_date($row ['maj'], $this->date_modif, $this->date_modif_gmt);
            $this->statut = $row ['statut'];

            // menu pour breves numérotés
            $this->menu_order = 0;
            $pattern = '/([0-9]+)\.[[:blank:]]+(.*)/';
            preg_replace_callback($pattern, function ($matches) {
                $this->menu_order = $matches [1];

                return $matches [2];
            }, $row ['titre']);

            $this->titre = Config::supprimer_numero($row ['titre']);

            // les mots cles
            $sql4 = 'SELECT spip_mots.id_mot AS ID FROM '.Config::$spip_prefix.'mots JOIN '
                    .Config::$spip_prefix.'mots_breves ON ('.Config::$spip_prefix.'mots.id_mot = '
                    .Config::$spip_prefix.'mots_breves.id_mot) '
                    .' WHERE id_breve=? and id_mot NOT IN ('.implode(', ', Config::$mots_a_exclure).')';
            $stmt4 = Config::$db_spip->prepare($sql4);
            if ($stmt4) {
                $stmt4->bind_param('d', $id);
            //echo "=== mots clés === \n";
            $stmt4->execute();
                $res4 = $stmt4->get_result();
                while ($row4 = $res4->fetch_assoc()) {
                    // echo "BREVE $id : mot ".$row ["ID"]." ajouté \n";
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
        Erreur::contexte_push('BREVE', $this->id);

        $sql = 'INSERT INTO wp'.$site.'_posts (post_type, post_author, post_date, post_date_gmt, post_status,  post_modified, post_modified_gmt, post_name, comment_status) VALUES '
                ."('breve',?,?,?,?,?,?,?,'closed')";
        $auteur = Auteurs::prolifique([], 1);
        $statut = $this->spip_to_wp_statut($this->statut);
        $stmt = Config::$db_wp->prepare($sql);

        $stmt->bind_param('dssssss', $auteur, $this->date, $this->date_gmt, $statut, $this->date_modif, $this->date_modif_gmt, $this->url);
        $stmt->execute();

        if (Config::$db_wp->error) {
            Erreur::error('BREVE non insérée : '.Config::$db_wp->error);
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
            Erreur::error("Lien breve->rubrique non inséré : $sql ".Config::$db_wp->error);
        }
        Erreur::contexte_pop();
    }

    public function update_refs($site)
    {
        Erreur::contexte_push('Liens BREVE', $this->id);
        $texte = traiter_raccourcis($this->texte);
        $type = 'post';
        $order = 0;
        $titre = strip_tags(
                preg_replace_callback('/^([0-9]+)\. (.*)$/', function ($matches) use (&$order, &$type) {
                    if (count($matches) == 3) {
                        list($tout, $numero, $titre) = $matches;
                        // echo "breve numeroté : $tout : $numero : $titre\n";
                        $type = 'page';
                        $order = intval($matches[1]);

                        return $matches[2];
                    }
                }, $this->titre)
        );

        $rub = Rubriques::get_rub($this->rubrique);
        if ($rub && Config::$ArticlesEnPage) { // TODO : paramètre breves en page
            $page = $rub->breve();
        }
        if ($type !== 'page') {
            $page = 0;
        }
        $sql = 'UPDATE wp'.$site.'_posts SET post_title=?, post_content=?, menu_order=?, post_parent=? WHERE ID=?';

        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('ssddd', $titre, $texte, $order, $page, $this->wp_id);
        $stmt->execute();
        // $res = $stmt->get_result();

        if (Config::$db_wp->error) {
            Erreur::error("BREVE Liens : $sql ".Config::$db_wp->error);
        }

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
}
