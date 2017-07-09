<?php

class MotCle implements ToWordPress
{
    private $id;
    private $term;
    private $taxonomy;
    private $titre;
    private $groupe;

    public function the_taxonomy()
    {
        if ($this->groupe == 4) {
            return 'post_tag';
        }
        if ($this->groupe == 12) {
            return 'commune';
        }

        return 'inconnu';
    }
    // select id_groupe,titre,descriptif,unseul,obligatoire,tables_liees,minirezo,comite,forum from spip_groupes_mots;
    public function __construct($id)
    {
        Erreur::contexte_push('motcle', $id);
        $sql = 'SELECT id_mot AS ID,id_groupe AS groupe, titre FROM '.Config::$spip_prefix.'mots  WHERE id_mot=?';
        $stmt = Config::$db_spip->prepare($sql);
        $stmt->bind_param('d', $id);

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row) {
            $this->id = $id;
            $this->titre = $row ['titre'];
            $this->groupe = $row ['groupe'];
        }

        Erreur::contexte_pop();
    }
    public function id()
    {
        return $this->id;
    }
    public function mot()
    {
        return $this->taxonomy;
    }
    public function update_refs($site)
    {
        // TODO : mot clés
                // echo "Rien à faire pour juste le titre, mais si description... c'est différent !\n";
    }
    public function to_wp($site)
    {
        // echo "Ajouter mot ".$this->id."\n";
        $sql1 = "INSERT INTO `wp${site}_terms` (`name`,`slug`,`term_group`) VALUES (?,?,0)";
        $stmt = Config::$db_wp->prepare($sql1);
        $slug = Urls::sanitize_title_with_dashes(str_replace("'", '-', Urls::remove_accents($this->titre)));
        $stmt->bind_param('ss', $this->titre, $slug);
        $stmt->execute();
        if (Config::$db_wp->error) {
            Erreur::error('mot clé (term) non inséré : '.Config::$db_wp->error);
        }
        $this->term = Config::$db_wp->insert_id;

        $sql2 = "INSERT INTO `wp${site}_term_taxonomy` (`term_id`,`taxonomy`,`description`,`parent`) VALUES (?,?,'',0)"; // count à mettre à jour...
        $stmt = Config::$db_wp->prepare($sql2);
        $tax = $this->the_taxonomy();

        $stmt->bind_param('ds', $this->term, $tax);
        $stmt->execute();
        if (Config::$db_wp->error) {
            Erreur::error('mot clé non inséré : '.Config::$db_wp->error);
        }
        $this->taxonomy = Config::$db_wp->insert_id;
    }
}
