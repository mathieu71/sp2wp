<?php

if (defined('__EVENEMENT__')) {
    return;
}
define('__EVENEMENT__', 1);

class Evenement implements ToWordPress
{
    private $id;
    private $id_article;
    private $wp_id;
    private $titre;
    private $texte;
    private $descriptif;
    private $date_debut;
    private $date_fin;
    private $lieu;
    private $horaire;
    private $url;

    public function lieu()
    {
        return $this->lieu;
    }

    public function __construct($id)
    {
        Erreur::contexte_push('evenement', $id);

        $sql = 'SELECT id_evenement AS ID, id_article, date_debut, date_fin, titre, descriptif, lieu, horaire FROM  '.Config::$spip_prefix.'evenements  WHERE id_evenement=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row) {
            $this->id = $id;
            $this->id_article = $row['id_article'];
            $this->date_debut = $row ['date_debut'];
            $this->date_fin = $row ['date_fin'];
            $this->titre = $row ['titre'];
            $this->descriptif = spip2wp($row ['descriptif']);
            $this->lieu = $row ['lieu'];
            $this->url = Urls::add($this->titre);
        }
        Erreur::contexte_pop();
    }

    public function the_url()
    {
        return $this->url;
    }

    public function to_wp($site)
    {
        Erreur::contexte_push('evenement', $this->id);

        $sql = 'INSERT INTO wp'.$site.'_posts (post_type, post_author, post_date, post_date_gmt, post_status,  post_modified, post_modified_gmt, post_name, comment_status) VALUES '."('event',?,?,?,?,?,?,?,'closed')";
        $auteur = 1; //Auteurs::prolifique ( array_keys ( evenements::$evenements ), $this->auteurs );
        $dummy = '';
        valider_date($this->date_debut, $date, $dummy);
        $statut = 'publish'; //$this->spip_to_wp_statut ( $this->statut );
        $stmt = Config::$db_wp->prepare($sql);
        $url = $this->url; //'ev-'. $this->id; //$this->url;

        $stmt->bind_param('dssssss', $auteur, $date, $date, $statut, $date, $date, $url);
        $stmt->execute();

        if (Config::$db_wp->error) {
            Erreur::error('evenement non inséré : '.Config::$db_wp->error);
        }
        $this->wp_id = Config::$db_wp->insert_id;
        $last = $this->wp_id;

        $deb_time = strtotime($this->date_debut);
        $sql = 'INSERT INTO wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_event_start',?);";
        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('ds', $last, $deb_time);
        $stmt->execute();

        $end_time = strtotime($this->date_fin);
        $sql = 'INSERT INTO wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_event_end',?);";
        $stmt = Config::$db_wp->prepare($sql);
        $stmt->bind_param('ds', $last, $end_time);
        $stmt->execute();

        if (!empty($this->lieu)) {
            $lieu = $this->lieu;
            $sql = 'INSERT INTO wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_event_lieu',?);";
            $stmt = Config::$db_wp->prepare($sql);
            $stmt->bind_param('ds', $last, $lieu);
            $stmt->execute();
        }

        if (Config::$db_wp->error) {
            Erreur::error("Evenement : $sql ".Config::$db_wp->error);
        }

        Erreur::contexte_pop();
    }

    public function detecte_messe()
    {
        $matches = array();
        $messe = false;
        foreach (array($this->titre, $this->texte) as $texte) {
            if (preg_match('/(messe|c[eé]l[eé]br[ea]|a.?d.?a.?p.?|profession de|bapt|mariage)/i', $texte, $matches)) {
                $messe = true;
            }
        }

        return $messe;
    }

    public function update_refs($site)
    {
        Erreur::contexte_push('Liens evenement', $this->id);
        $desc = $this->descriptif ? spip2wp($this->descriptif) : '';
        $this->texte = spip2wp("$desc");
        $this->titre = strip_tags($this->titre);

        $article = Articles::get_art($this->id_article);
        $parent = $article->wp_id();
        $auteur = $auteur = Auteurs::prolifique(array_keys(Articles::$articles), $article->auteurs());

        //echo "=> type = $type et ordre = $order\n";
        $sql = "UPDATE wp{$site}_posts SET post_content=?, post_title=?, post_parent=?, post_author=? WHERE ID=?";

        $stmt = Config::$db_wp->prepare($sql);
        //$guid = Config::$wp_base_url.'?event='.$this->wp_id;
        $stmt->bind_param('ssddd', $this->texte, $this->titre, $parent, $auteur, $this->wp_id);
        $stmt->execute();
        // $res = $stmt->get_result();

        if (Config::$db_wp->error) {
            Erreur::error("evenement Liens : $sql ".Config::$db_wp->error);
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
        return $this->titre;
    }
}

;
