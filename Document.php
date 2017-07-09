<?php

if (defined('__DOCUMENT__')) {
    return;
}
define('__DOCUMENT__', 1);

class Document implements ToWordPress
{
    private $id;
    private $wp_id;
    private $titre;
    private $descriptif;
    private $date;
    private $date_gmt;
    private $modif;
    private $modif_gmt;
    public $fichier;
    private $taille;
    private $largeur;
    private $hauteur;
    private $mode;
    private $mime;
    private $vignette;
    private $valid = false;
    private $rel_fn; // nom relatif du fichier copié
    private $guid; // url du document
    private $inexistant = false; // si le fichier n'existe pas en base !

    public function copy($src_spip)
    {
        /* copier le document au bon endroit (date...) */
        $dt = filemtime(Config::$spip_img_path.$src_spip);
        if ($dt === false) {
            $dt = 0;
        }
        $year = date('Y', $dt);
        $month = date('m', $dt);
        $dn = Config::$wp_root.Config::$wp_upload_path."$year/$month/";
        $fn = basename($src_spip);
        if (!is_dir($dn)) {
            echo "création de $dn\n";
            mkdir($dn, 0755, true);
        }

        $dest = $dn.$fn;
        if (!file_exists($dest) && !copy(Config::$spip_img_path.$src_spip, $dest)) {
            echo 'fichier '.$src_spip." impossible à copier\n";
        } elseif (is_file($dest)) {
            echo 'fichier '.$dest." existe\n";
        } else { // préserver la date de modification
            touch($dest, $dt);
        }
        // TODO : guid sans wp-content/sites/
        $this->rel_fn = "$year/$month/".$fn;
        //echo "Fichier relatif : ".$this->rel_fn."\n";
        $this->guid = Config::$wp_base_url.Config::$wp_upload_path.$this->rel_fn;
    }

    public function __construct($id)
    {
        Erreur::contexte_push('Document', $id);
        $this->id = $id;

        $stmt = Documents::get_query();
        $stmt->bind_param('d', $id);
        $stmt->execute();
        $res = $stmt->get_result();

        $row = $res->fetch_assoc();
        if ($row) {
            $this->titre = $row ['titre'];
            valider_date($row ['date'], $this->date, $this->date_gmt);
            valider_date($row ['maj'], $this->modif, $this->modif_gmt);
            $this->descriptif = spip2wp($row ['descriptif']);
            $this->fichier = $row ['fichier'];

            $tmp_fn = basename($this->fichier);
            $tmp_fn2 = strpos($tmp_fn, '.');
            if ($tmp_fn2 !== false) {
                $tmp_fn = substr($tmp_fn, 0, $tmp_fn2);
            }
            $this->titre = $this->titre ? $this->titre : str_replace(array('_', '-'), array(' ', ' '), $tmp_fn);

            $this->taille = $row ['taille'];
            $this->largeur = $row ['largeur'];
            $this->hauteur = $row ['hauteur'];
            $this->mode = $row ['mode'];
            $this->mime = $row['extension'];
            if ($row ['id_vignette']) {
                $this->vignette = $row ['id_vignette'];
                Documents::add($row ['id_vignette']);
            }
            $fn = Config::$spip_img_path.$this->fichier;
            if (is_file($fn) or is_link($fn)) {
                $this->valid = true;
                $this->copy($row ['fichier']);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $this->mime = finfo_file($finfo, $fn);
            } else {
                Erreur::error('Fichier inexistant : '.$fn."\n");
                $this->rel_fn = $this->fichier;
            }
        } else {
            Erreur::error("Document $id non trouvé\n");
            $this->inexistant = true;
        }
        Erreur::contexte_pop();
    }

    public function inexistant()
    {
        return $this->inexistant;
    }

    public function is_valid()
    {
        return $this->valid;
    }

    public function to_wp($site)
    {
        Erreur::contexte_push('Document', $this->id);

        if (!$this->valid) {
            echo 'Document '.$this->id." sans fichier.... peut-être externe ?\n";
            $this->guid = $this->rel_fn = $this->fichier;
        }

        $stmt = Documents::get_insert($site);

        $url_fichier = $this->guid;

        $desc = $this->descriptif ? $this->descriptif : '';
        $titre = $this->titre ? $this->titre : str_replace('_', ' ', basename($this->fichier));
        $stmt->bind_param('ssssssss', $this->date, $this->date_gmt, $desc, $titre, $this->modif, $this->modif_gmt, $this->mime, $url_fichier);
        $stmt->execute();

        if (Config::$db_wp->error) {
            print_r($this);
            Erreur::error('Document '.$this->id.' non inséré : '.Config::$db_wp->error);
            Documents::remove($this->id);

            return;
        }
        $this->wp_id = Config::$db_wp->insert_id;

        $stmt = Documents::get_insert_file($site);
        $fichier = $this->rel_fn;
        $stmt->bind_param('ds', $this->wp_id, $fichier);
        $stmt->execute();
        if (Config::$db_wp->error) {
            Erreur::error('Fichier non inséré : '.Config::$db_wp->error);
        }

        $stmt = Documents::get_insert_file_meta($site);
        $meta = serialize(array(
            'width' => $this->largeur,
            'height' => $this->hauteur,
            'file' => $this->rel_fn,
                ));
        $stmt->bind_param('ds', $this->wp_id, $meta);
        $stmt->execute();
        if (Config::$db_wp->error) {
            Erreur::error('Méta-données du fichier non insérés : '.Config::$db_wp->error);
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
        return htmlspecialchars($this->titre ? $this->titre : $this->fichier);
    }

    public function guid()
    {
        return $this->guid;
    }

    public function mime()
    {
        return $this->mime;
    }

    public function vignette()
    {
        return Documents::get_doc($this->vignette);
    }

    public function descriptif()
    {
        return $this->descriptif;
    }
}

;
