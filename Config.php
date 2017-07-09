<?php

if (defined('__CONFIG__')) {
    return;
}
define('__CONFIG__', 1);

class Config
{
    public static $mots_a_exclure = [273];
    public static $spip_version = 2;

    private static $WP_SITE_PATH = '/';
    public static $SPIP_ABS_PATH = '/srv/http/spip/';
    public static $SPIP_PATH_IMG = 'IMG/';
    public static $SPIP_PATH_VIGNETTES = 'prive/vignettes/';
    public static $SPIP_DOM = 'domain.tld';
    private static $WP_ABS_PATH = '/srv/http/wordpress/';
    private static $WP_PATH_UPLOAD = 'wp-content/uploads/sites/';
    public static $DETECT = true;
    public static $RECHERCHE_LIENS = false;
    public static $db_wp;
    public static $db_spip;
    public static $wp_base_url;
    public static $wp_site;
    public static $spip_rubrique;

    public static $wp_upload_path;
    public static $spip_path;
    public static $spip_img_path;
    public static $wp_folder;
    public static $wp_root;
    public static $old_domain;
    public static $spip_base_url;
    public static $RubriqueEnPage = false; // TRUE; /* tout dans la catégorie actualité, et les pages reprennent le contenu de la rubrique */
    public static $PlusPage = true; //    TRUE; /* si pas en page, alors les sous-rubriques sont reprises en catégories. Avec ça, une page de description de la catégorie est créée */
    public static $ArticlesEnPage = false;
    public static $CreerMenuDiocese = true;

    private static $page_contact = null;

    public static $spip_prefix = 'spip_';

    /** connexion */
    private static $database_spip = 'DATABASE';
    private static $pwd_spip = 'DB_PASSWORD';
    private static $host_spip = 'DB_HOST';
    private static $user_spip = 'DB_USER';

    private static $database_wp = 'DATABASE';
    private static $pwd_wp = 'DB_PASSWORD';
    private static $host_wp = 'DB_HOST';
    private static $user_wp = 'DB_USER';

    /* valeurs par défaut pour auteur */
    public static $default_email = 'DEFAULT_EMAIL';
    public static $default_passwd = 'DEFAULT_PASSWORD';

    public function set_contact($id)
    {
        self::$page_contact = $id;
    }

    public static function detect()
    {
        return self::$DETECT;
    }

    public static function set_transforme()
    {
        self::$DETECT = false;
    }

    public static function set_detecte()
    {
        self::$DETECT = true;
    }

    public static function init($wp_site, $spip_rubrique, $wp_dom, $wp_path)
    {
        self::$spip_rubrique = $spip_rubrique;
        self::$wp_site = $wp_site;
        self::$WP_SITE_PATH = $wp_path;

        self::$db_spip = mysqli_connect(self::$host_spip, self::$user_spip, self::$pwd_spip, self::$database_spip) or die("Impossible de se connecter à SPIP\n");
        self::$db_wp = mysqli_connect(self::$host_wp, self::$user_wp, self::$pwd_wp, self::$database_wp) or die("Impossible de se connecter à WordPress\n");
        $pp = self::$db_wp->query('SELECT * from wp_blogs where blog_id='.self::$wp_site) or die("Impossible de lire les blogs : installation multi sites ?\n");
        if ($pp->num_rows == 0) {
            die("Site WordPress inexistant\n");
        }

        self::$spip_path = self::$SPIP_ABS_PATH;
        self::$spip_img_path = self::$spip_path.self::$SPIP_PATH_IMG;

        self::$wp_upload_path = self::$WP_PATH_UPLOAD.self::$wp_site.'/';
        self::$wp_folder = self::$WP_SITE_PATH; // TODO : rechercher dans les options du site !
        self::$wp_base_url = 'http://'.$wp_dom.self::$wp_folder;
        self::$wp_root = self::$WP_ABS_PATH;

        if (file_exists(self::$WP_ABS_PATH.self::$WP_PATH_UPLOAD.self::$wp_site)) {
            echo 'Le dossier '.self::$WP_ABS_PATH.self::$WP_PATH_UPLOAD.self::$wp_site." existe déjà\n";
        }

        $dom = self::$SPIP_DOM;
        self::$old_domain = array($dom, "www.$dom", "img0.$dom", "img1.$dom", "img2.$dom");
        self::$spip_base_url = 'http://'.$dom.'/';
    }

    public static function supprimer_numero($slug)
    {
        $len = strlen($slug);
        $i = 0;
        while (($i < $len) && ($slug[$i] >= '0' and $slug [$i] <= '9')):
            $i++;
        endwhile;
        if ($slug[$i] == '.'):
            $i++;
        while (($i < $len) && $slug[$i] == ' '):
                $i++;
        endwhile;
        $slug = substr($slug, $i);
        endif;

        return $slug;
    }

    public static $v_et_l = array(); // vignettes et logos dédupliqués

    public static function copy_file($src_spip, $dest = null, $titre = 'Logo')
    {
        if ($src_spip[0] !== '/') {
            $filename = self::$spip_img_path.$src_spip;
        } else {
            $filename = $src_spip;
        }
        if (is_file($filename)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filename);

            $md5 = md5(file_get_contents($filename));
            if (!isset(self::$v_et_l[$md5])) {
                $dt = filemtime($filename);
                if ($dt === false) {
                    $dt = 0;
                }
                $year = date('Y', $dt);
                $month = date('m', $dt);
                $dn = self::$wp_root.self::$wp_upload_path."$year/$month/";
                $fn = basename($src_spip);
                if (!is_dir($dn)) {
                    // echo "création de $dn\n";
                    mkdir($dn, 0755, true);
                }

                if (!$dest) {
                    $dest = self::$wp_root.self::$wp_upload_path."$year/$month/".basename($src_spip);
                }

                if (!copy($filename, $dest)) {
                    echo 'fichier '.$src_spip." impossible à copier\n";
                } else { // préserver la date de modification
                    touch($dest, $dt); // remettre à la bonne date le fichier
                    $sql = 'INSERT INTO wp'.'_'.self::$wp_site.'_posts (post_author,post_date,post_date_gmt,'.'post_title,post_status,post_type,comment_status,ping_status,post_name,post_modified,post_modified_gmt,'.'post_parent,post_mime_type,guid) VALUES '."(?,?,?,?,'inherit','attachment','closed','closed',?,?,?,0,?,?);";
                    $stmt = self::$db_wp->prepare($sql);

                    $auteur = Auteurs::prolifique(array_keys(Articles::$articles), array()); // TODO vérifier que c'est appelé après détection...

                    $fichier = "$year/$month/".basename($dest);
                    $guid = self::$wp_base_url.self::$wp_upload_path.$fichier;
                    $dt = gmdate('Y-m-d', $dt);

                    $stmt->bind_param('dssssssss', $auteur, $dt, $dt, $titre, $titre, $dt, $dt, $mime, $guid);
                    $stmt->execute();

                    if (self::$db_wp->error) {
                        Erreur::error("Logo  : $sql ".self::$db_wp->error);
                    }
                    $last = self::$db_wp->insert_id;
                    $size = getimagesize($dest);

                    $sql = 'INSERT INTO wp'.'_'.self::$wp_site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_wp_attached_file',?)";
                    $stmt = self::$db_wp->prepare($sql);

                    $stmt->bind_param('ds', $last, $fichier);
                    $stmt->execute();

                    if (self::$db_wp->error) {
                        Erreur::error("Logo  : $sql ".self::$db_wp->error);
                    }

                    $sql = 'INSERT INTO wp'.'_'.self::$wp_site."_postmeta (post_id,meta_key,meta_value) VALUES (?,'_wp_attachment_metadata',?);";
                    $stmt = self::$db_wp->prepare($sql);
                    $meta = serialize(array(
                        'file' => $fichier,
                        'width' => $size [0],
                        'height' => $size [0],
                            )
                    );
                    $stmt->bind_param('ds', $last, $meta);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if (self::$db_wp->error) {
                        Erreur::error("Logo  : $sql ".self::$db_wp->error);
                    }

                    self::$v_et_l[$md5]['id'] = $last;
                    self::$v_et_l[$md5]['fichier'] = $fichier;
                }
            }

            $retour = array('wp_id' => self::$v_et_l[$md5]['id'], 'guid' => self::$wp_base_url.self::$wp_upload_path.self::$v_et_l[$md5]['fichier']);

            return $retour;
        }

        return 0;
    }

    public static function menu_item($page, $menu, $order, $parent, $type, $obj)
    {
        $site = '_'.self::$wp_site;

        $insert = 'INSERT into wp'.$site."_posts (post_date,post_status,menu_order,post_type) VALUES (NOW(),'publish',$order,'nav_menu_item');";
        $stmt = self::$db_wp->prepare($insert);
        $stmt->execute();
        $menu_item = self::$db_wp->insert_id;

        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_type','$type')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_menu_item_parent',$parent)";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_object_id',$page)";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_object','$obj')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_target','')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_classes','a:1:{i:0;s:0:\"\";}')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_xfn','')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_postmeta (post_id,meta_key,meta_value) VALUES ($menu_item ,'_menu_item_url','')";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();
        $meta = 'INSERT into wp'.$site."_term_relationships (object_id, term_taxonomy_id) VALUES ($menu_item, $menu);";
        $stmt = self::$db_wp->prepare($meta);
        $stmt->execute();

        return $menu_item;
    }

    public static function menu()
    {
        $site = '_'.self::$wp_site;
        $ml = <<<EOF
    <h2>Éditeur</h2>
Conformément à la loi dite « informatique et libertés » du 6 janvier 1978, un droit d'accès aux données, de rectification et d'opposition à leur traitement ou leur conservation peut être exercé par toute personne concernée.

Vous pouvez utiliser le <a href="/contactez-nous">formulaire de contact</a> pour exercer ce droit.
EOF;
        $sql = 'INSERT INTO wp'.$site."_terms (name, slug, term_group) VALUES ('Menu Principal', 'menu-principal', 0)";
        $stmt = self::$db_wp->prepare($sql);
        $stmt->execute();

        if (self::$db_wp->error) {
            Erreur::error('Menu Principal (terme) non inséré : '.self::$db_wp->error);
        }
        $menu_principal = self::$db_wp->insert_id;

        $sql = 'INSERT INTO wp'.$site.'_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ('.$menu_principal.", 'nav_menu', 'Menu princiapl', 0, 0);";
        $stmt = self::$db_wp->prepare($sql);
        $stmt->execute();

        if (self::$db_wp->error) {
            Erreur::error('Menu Principal (taxonomie) non inséré : '.self::$db_wp->error);
        }
        $menu_principal2 = self::$db_wp->insert_id;

        // ajouter les pages au menu avant de créer les pages de mentions et contact
        $sql = 'SELECT ID,post_title,post_parent FROM wp'.$site."_posts WHERE  post_type LIKE 'page' AND post_status='publish' AND post_parent=0 ORDER BY menu_order";
        $pp = self::$db_wp->query($sql);
        if ($pp->num_rows > 0) { // des pages existent
            $i = 10;
            while ($row = $pp->fetch_assoc()) {
                $page_parente = $row['ID'];
                $menu_parent = self::menu_item($row['ID'], $menu_principal2, $i, 0, 'post_type', 'page');

                $sql2 = 'SELECT ID,post_title,post_parent FROM wp'.$site."_posts WHERE  post_type LIKE 'page' AND post_status='publish' AND post_parent=$page_parente ORDER BY menu_order";
                $pp2 = self::$db_wp->query($sql2);
                $i2 = 10;
                while ($row2 = $pp2->fetch_assoc()) {
                    self::menu_item($row2['ID'], $menu_principal2, $i2, $menu_parent, 'post_type', 'page');
                    $i2 += 10;
                }

                $i += 10;
            }
        } else { // menu de catégories ?
            $sql = 'SELECT term_id, parent from wp'.$site."_term_taxonomy WHERE taxonomy='category' and parent=0 ORDER BY term_id";
            $pp = self::$db_wp->query($sql);
            if ($pp->num_rows > 0) { // des pages existent
                $i = 10;
                while ($row = $pp->fetch_assoc()) {
                    $page_parente = $row['term_id'];
                    $menu_parent = self::menu_item($row['term_id'], $menu_principal2, $i, 0, 'taxonomy', 'category');
                    $sql2 = 'SELECT term_id, parent from wp'.$site."_term_taxonomy WHERE taxonomy='category' and parent=$page_parente ORDER BY term_id";
                    $pp2 = self::$db_wp->query($sql2);
                    $i2 = 10;
                    while ($row2 = $pp2->fetch_assoc()) {
                        self::menu_item($row2['term_id'], $menu_principal2, $i2, $menu_parent, 'taxonomy', 'category');
                        $i2 += 10;
                    }
                    $i += 10;
                }
            }
        }

        // menu de pied de page
        $sql = 'INSERT INTO wp'.$site."_terms (name, slug, term_group) VALUES ('Menu pied de page', 'menu-pied', 0)";
        $stmt = self::$db_wp->prepare($sql);
        $stmt->execute();

        if (self::$db_wp->error) {
            Erreur::error('Menu Principal (terme) non inséré : '.self::$db_wp->error);
        }
        $menu_pied = self::$db_wp->insert_id;

        $sql = 'INSERT INTO wp'.$site.'_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ('.$menu_pied.", 'nav_menu', 'Menu pied de page', 0, 0);";
        $stmt = self::$db_wp->prepare($sql);
        $stmt->execute();

        if (self::$db_wp->error) {
            Erreur::error('Menu Principal (taxonomie) non inséré : '.self::$db_wp->error);
        }
        $menu_pied2 = self::$db_wp->insert_id;

        $a_ml = self::creer([['Mentions légales']]);
        self::menu_item($a_ml[0], $menu_pied2, 10, 0, 'post_type', 'page');

        if (!self::$page_contact) {
            $cn = self::creer([['Contactez-nous']]);
        } else {
            $cn = [self::$page_contact];
        }
        self::menu_item($cn[0], $menu_pied2, 20, 0, 'post_type', 'page');

        $sql = 'UPDATE wp'.$site.'_posts SET post_content=? WHERE ID=?';
        $stmt = self::$db_wp->prepare($sql);
        $stmt->bind_param('sd', $ml, $a_ml[0]);
        $stmt->execute();

        return array($menu_principal2, $menu_pied2);
    }

    public static function creer($tab, $parent = 0)
    {
        if (empty($tab)) {
            return;
        }
        $racine = array();
        $order = 10;
        foreach ($tab as $tableau) {
            $title = $tableau[0];

            $site = '_'.self::$wp_site;

            $sql = 'INSERT INTO wp'.$site.'_posts '.
                    ' (post_type, menu_order, post_parent, post_title, post_author, post_date, post_date_gmt, post_status,  post_modified, post_modified_gmt, post_name, comment_status) VALUES '.
                    "('page',?,?,?,?,?,?,?,?,?,?,'closed')";
            $auteur = Auteurs::prolifique(array_keys(Articles::$articles), array()); // voir le second paramètre ?
            $statut = 'draft';
            $date = '';
            $local = '';
            $utc = '';
            valider_date('2010-01-01 12:00:00', $local, $utc);
            $url = Urls::add($title);
            //echo "dates : $local et $utc\n"; // PB , fournir une date parse_str ok

            $stmt = self::$db_wp->prepare($sql);
            $stmt->bind_param('ddsdssssss', $order, $parent, $title, $auteur, $local, $utc, $statut, $local, $utc, $url);
            $stmt->execute();

            if (self::$db_wp->error) {
                Erreur::error("Article par défaut $title non inséré : ".self::$db_wp->error);
            }
            $last = self::$db_wp->insert_id;
            if ((count($tableau) > 1) && is_array($tableau[1])) {
                self::creer($tableau[1], $last);
            }
            $order = $order + 5;
            array_push($racine, $last);
        }

        return $racine;
    }

    public static function get_results()
    {
        $params = func_get_args();
        $db = $params[0];
        $sql = $params[1];
        $bind = $params[2];
        $error = $params[3];
        print_r($sql);
        print_r($bind);
        $stmt = $db->prepare($sql);
        if (!empty($bind)) {
            call_user_func(array($stmt, 'bind_param'), $bind[0], $bind[1]);
        }
        $stmt->execute();
        if ($db->error) {
            Erreur::error("$error : ".$db->error);

            return array();
        } else {
            return $stmt->get_result();
        }
    }
}
