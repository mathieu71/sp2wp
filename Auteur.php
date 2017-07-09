<?php

if (defined('__AUTEUR__')) {
    return;
}
define('__AUTEUR__', 1);

class Auteur implements ToWordPress
{
    public $id;
    public $wp_id;
    private $nom;
    private $bio;
    private $statut;
    private $email;
    private $login;
    public function __construct($id)
    {
        Erreur::contexte_push('Auteur', $id);

        $this->id = $id;

        $sql = 'SELECT bio, nom, email, login, statut FROM '.Config::$spip_prefix.'auteurs where id_auteur='.$id;
        $res = Config::$db_spip->query($sql);
        $row = $res->fetch_assoc();
        if ($row) {
            $this->nom = $row ['nom'];
            $this->bio = $row ['bio'];
            $this->email = $row ['email'];
            $this->statut = $row ['statut'];
            $this->login = $row ['login'];
        }
        Erreur::contexte_pop();
    }
    public function to_wp($site)
    {
        Erreur::contexte_push('Auteur', $this->id);

        $sql = "SELECT ID FROM wp_users WHERE user_login='".$this->login."';\n";
        $res = Config::$db_wp->query($sql);
        if (!$row = $res->fetch_assoc()) {
            $sql = 'INSERT INTO wp_users (user_login,user_nicename,user_email,user_pass,display_name) VALUES (?,?,?,?,?)';
            $stmt = Config::$db_wp->prepare($sql);
            $email = ($this->email ? $this->email : Config::$default_email);
            $passwd = MD5(Config::$default_passwd);
            $stmt->bind_param('sssss', $this->login, $this->login, $email, $passwd, $this->nom);
            $stmt->execute();
            if (Config::$db_wp->error) {
                Erreur::error('Auteur non inséré : '.Config::$db_wp->error); // TODO : le supprimer de Auteurs::
                return;
            } else {
                $this->wp_id = Config::$db_wp->insert_id;
                $sql = 'INSERT INTO wp_usermeta (user_id,meta_key,meta_value) VALUES (?,?,?)';
                $stmt = Config::$db_wp->prepare($sql);

                // editeur visuel par défaut
                $key = 'rich_editing';
                $value = 'true';
                $stmt->bind_param('dss', $this->wp_id, $key, $value);
                $stmt->execute();

                // couleur épiscopale par défaut
                $key = 'admin_color';
                $value = 'ectoplasm';
                $stmt->bind_param('dss', $this->wp_id, $key, $value);
                $stmt->execute();

                // simplification newsletter par défaut
                $hidden = serialize(array('newsletter_archives_typesdiv', 'slugdiv', 'authordiv', 'newsletter_admin_wizard'));
                $key = 'metaboxhidden_newsletter';
                $value = $hidden;
                $stmt->bind_param('dss', $this->wp_id, $key, $value);
                $stmt->execute();

                // pas de barre par défaut en fron-end // TODO : en attendant de réussir à faire comme en back-end
// 				$key='show_admin_bar_front'; $value='false';
// 				$stmt->bind_param ( 'dss', $this->wp_id,$key,$value);
// 				$stmt->execute ();
            }
        } else {
            $this->wp_id = $row ['ID'];
        }

        $sql = "SELECT meta_key FROM wp_usermeta WHERE meta_key='wp".$site."_capabilities' and user_id='".$this->wp_id."';\n";
        $res = Config::$db_wp->query($sql);
        if (!$row = $res->fetch_assoc()) {
            $cap = serialize(array(
                'administrator' => true,
            ));
            $sql = "INSERT INTO wp_usermeta (user_id,meta_key,meta_value) VALUES ('".$this->wp_id."','".'wp'.$site.'_capabilities'."','".$cap."');\n";
            $res = Config::$db_wp->query($sql);
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
}
