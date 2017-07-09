<?php
include_once 'Config.php';

include_once 'Global.php';

include_once 'Rubriques.php';
include_once 'Rubrique.php';

include_once 'Articles.php';
include_once 'Article.php';

include_once 'Breves.php';
include_once 'Breve.php';

include_once 'Documents.php';
include_once 'Document.php';

include_once 'Auteurs.php';
include_once 'Auteur.php';

include_once 'Lien.php';

include_once 'Evenements.php';
include_once 'Evenement.php';

include_once 'MotsCles.php';
include_once 'MotCle.php';

include_once 'Urls.php';

include_once 'Modele.php';

if ($argc < 5) {
    print_r($argv);
    die('Usage: '.$argv[0]." blog_id spip_rub wp_dom wp_path\n");
}
$la_rubrique = $argv[2];
Config::init($argv[1], $la_rubrique, $argv[3], $argv[4]);

$site = '_'.Config::$wp_site;

$sql = "truncate wp${site}_term_taxonomy;";
Config::$db_wp->query($sql);
$sql = "truncate wp${site}_terms;";
Config::$db_wp->query($sql);
$sql = "truncate wp${site}_posts;";
Config::$db_wp->query($sql);
$sql = "truncate wp${site}_term_relationships;";
Config::$db_wp->query($sql);
$sql = "truncate wp${site}_postmeta;";
Config::$db_wp->query($sql);

Config::set_detecte();

$rubriques = [];
if (intval(Config::$spip_rubrique) === 0) {
    $sql = 'select id_rubrique from '.Config::$spip_prefix.'rubriques where id_parent=0';
    $stmt = Config::$db_spip->prepare($sql);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rubriques[] = $row['id_rubrique'];
    }
} else {
    $rubriques[] = Config::$spip_rubrique;
}
foreach ($rubriques as $rub) {
    echo $rub;
    $r = new Rubriques($rub);
}

Auteurs::to_wp($site);
Rubriques::to_wp($site);
Articles::to_wp($site);
Breves::to_wp($site);
Evenements::to_wp($site);
MotsCles::to_wp($site);
Documents::to_wp($site);

Config::set_transforme();

Rubriques::update_refs($site);
Articles::update_refs($site);
Breves::update_refs($site);
MotsCles::update_refs($site);
Evenements::update_refs($site);
Documents::attacher($site);
MotsCles::attacher($site);

function update_or_insert($option, $value, $error)
{
    $site = '_'.Config::$wp_site;
    $pp = Config::$db_wp->query("SELECT * from wp{$site}_options where option_name='$option'");
    if ($pp->num_rows != 0) {
        $stmt = Config::$db_wp->prepare("UPDATE wp{$site}_options SET option_value=? WHERE option_name='$option'");
        $stmt->bind_param('s', $value);
        $stmt->execute();
    } else {
        $stmt = Config::$db_wp->prepare("INSERT INTO wp{$site}_options (option_value,option_name) VALUES (?,'$option')");
        $stmt->bind_param('s', $value);
        $stmt->execute();
    }
    if (Config::$db_wp->error) {
        Erreur::error("$error : ".Config::$db_wp->error);
    }
}

$stmt4 = Config::$db_wp->prepare("DELETE FROM wp{$site}_options WHERE option_name LIKE '%theme_%'");
$stmt4->execute();

$sql = "update wp{$site}_term_taxonomy as t set count=("
." select count(*) from wp{$site}_term_relationships as r join wp{$site}_posts on (wp{$site}_posts.ID = r.object_id AND wp{$site}_posts.post_status='publish')"
.' where r.term_taxonomy_id=t.term_taxonomy_id)'; // status pour ne pas avoir de liens 404 dans le nuage de tags

Config::$db_wp->query($sql);

$racine = Rubriques::rub_racine();
$racine_wp = Rubriques::get_rub($racine);

$stmt = Config::$db_wp->prepare("UPDATE wp{$site}_options SET option_value=? WHERE option_name='blogname'");
$titre = $racine_wp->titre();
$stmt->bind_param('s', $titre);
$stmt->execute();
if (Config::$db_wp->error) {
    Erreur::error('Titre du site non inséré : '.Config::$db_wp->error);
}

$stmt2 = Config::$db_wp->prepare("UPDATE wp{$site}_options SET option_value=? WHERE option_name='blogdescription'");
$desc = ''; //$racine_wp->intro ();
$stmt2->bind_param('s', $desc);
$stmt2->execute();
if (Config::$db_wp->error) {
    Erreur::error('Description du site non insérée : '.Config::$db_wp->error);
}

$stmt3 = Config::$db_wp->prepare("DELETE FROM wp{$site}_options WHERE option_name LIKE '%\_transient_feed%'");
$stmt3->execute();

$stmt5 = Config::$db_wp->prepare("UPDATE wp{$site}_options SET option_value='closed' WHERE option_name='default_comment_status'");
$stmt5->execute();

$stmt6 = Config::$db_wp->prepare("UPDATE wp{$site}_options SET option_value='/%postname%,%post_id%' WHERE option_name='permalink_structure'");
$stmt6->execute();

$rewrite_rules = array(
    'newsletter/?$' => 'index.php?post_type=newsletter',
    'newsletter/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter&feed=$matches[1]',
    'newsletter/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter&feed=$matches[1]',
    'newsletter/page/([0-9]{1,})/?$' => 'index.php?post_type=newsletter&paged=$matches[1]',
    'newsletter_template/?$' => 'index.php?post_type=newsletter_template',
    'newsletter_template/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter_template&feed=$matches[1]',
    'newsletter_template/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter_template&feed=$matches[1]',
    'newsletter_template/page/([0-9]{1,})/?$' => 'index.php?post_type=newsletter_template&paged=$matches[1]',
    'newsletter_archive/?$' => 'index.php?post_type=newsletter_archive',
    'newsletter_archive/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter_archive&feed=$matches[1]',
    'newsletter_archive/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=newsletter_archive&feed=$matches[1]',
    'newsletter_archive/page/([0-9]{1,})/?$' => 'index.php?post_type=newsletter_archive&paged=$matches[1]',
    'evenement/?$' => 'index.php?post_type=event',
    'evenement/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=event&feed=$matches[1]',
    'evenement/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_type=event&feed=$matches[1]',
    'evenement/page/([0-9]{1,})/?$' => 'index.php?post_type=event&paged=$matches[1]',
    'category/(.+?)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
    'category/(.+?)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
    'category/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?category_name=$matches[1]&paged=$matches[2]',
    'category/(.+?)/?$' => 'index.php?category_name=$matches[1]',
    'tag/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
    'tag/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
    'tag/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?tag=$matches[1]&paged=$matches[2]',
    'tag/([^/]+)/?$' => 'index.php?tag=$matches[1]',
    'type/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_format=$matches[1]&feed=$matches[2]',
    'type/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?post_format=$matches[1]&feed=$matches[2]',
    'type/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?post_format=$matches[1]&paged=$matches[2]',
    'type/([^/]+)/?$' => 'index.php?post_format=$matches[1]',
    'commune/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?commune=$matches[1]&feed=$matches[2]',
    'commune/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?commune=$matches[1]&feed=$matches[2]',
    'commune/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?commune=$matches[1]&paged=$matches[2]',
    'commune/([^/]+)/?$' => 'index.php?commune=$matches[1]',
    'lieu/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?lieu=$matches[1]&feed=$matches[2]',
    'lieu/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?lieu=$matches[1]&feed=$matches[2]',
    'lieu/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?lieu=$matches[1]&paged=$matches[2]',
    'lieu/([^/]+)/?$' => 'index.php?lieu=$matches[1]',
    'type_evenement/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?type_evenement=$matches[1]&feed=$matches[2]',
    'type_evenement/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?type_evenement=$matches[1]&feed=$matches[2]',
    'type_evenement/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?type_evenement=$matches[1]&paged=$matches[2]',
    'type_evenement/([^/]+)/?$' => 'index.php?type_evenement=$matches[1]',
    'newsletter/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter/[^/]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter/([^/]+)/trackback/?$' => 'index.php?newsletter=$matches[1]&tb=1',
    'newsletter/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter=$matches[1]&feed=$matches[2]',
    'newsletter/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter=$matches[1]&feed=$matches[2]',
    'newsletter/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?newsletter=$matches[1]&paged=$matches[2]',
    'newsletter/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?newsletter=$matches[1]&cpage=$matches[2]',
    'newsletter/([^/]+)(/[0-9]+)?/?$' => 'index.php?newsletter=$matches[1]&page=$matches[2]',
    'newsletter/[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter/[^/]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter_template/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter_template/[^/]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter_template/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_template/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_template/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter_template/([^/]+)/trackback/?$' => 'index.php?newsletter_template=$matches[1]&tb=1',
    'newsletter_template/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter_template=$matches[1]&feed=$matches[2]',
    'newsletter_template/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter_template=$matches[1]&feed=$matches[2]',
    'newsletter_template/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?newsletter_template=$matches[1]&paged=$matches[2]',
    'newsletter_template/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?newsletter_template=$matches[1]&cpage=$matches[2]',
    'newsletter_template/([^/]+)(/[0-9]+)?/?$' => 'index.php?newsletter_template=$matches[1]&page=$matches[2]',
    'newsletter_template/[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter_template/[^/]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter_template/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_template/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_template/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter_archive/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter_archive/[^/]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter_archive/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_archive/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_archive/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter_archive/([^/]+)/trackback/?$' => 'index.php?newsletter_archive=$matches[1]&tb=1',
    'newsletter_archive/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter_archives_types=$matches[1]&feed=$matches[2]',
    'newsletter_archive/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?newsletter_archives_types=$matches[1]&feed=$matches[2]',
    'newsletter_archive/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?newsletter_archives_types=$matches[1]&paged=$matches[2]',
    'newsletter_archive/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?newsletter_archive=$matches[1]&cpage=$matches[2]',
    'newsletter_archive/([^/]+)(/[0-9]+)?/?$' => 'index.php?newsletter_archive=$matches[1]&page=$matches[2]',
    'newsletter_archive/[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'newsletter_archive/[^/]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'newsletter_archive/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_archive/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'newsletter_archive/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'newsletter_archive/([^/]+)/?$' => 'index.php?newsletter_archives_types=$matches[1]',
    'evenement/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'evenement/[^/]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'evenement/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'evenement/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'evenement/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'evenement/([^/]+)/trackback/?$' => 'index.php?event=$matches[1]&tb=1',
    'evenement/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?event=$matches[1]&feed=$matches[2]',
    'evenement/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?event=$matches[1]&feed=$matches[2]',
    'evenement/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?event=$matches[1]&paged=$matches[2]',
    'evenement/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?event=$matches[1]&cpage=$matches[2]',
    'evenement/([^/]+)(/[0-9]+)?/?$' => 'index.php?event=$matches[1]&page=$matches[2]',
    'evenement/[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    'evenement/[^/]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    'evenement/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'evenement/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    'evenement/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    'robots\.txt$' => 'index.php?robots=1',
    '.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => 'index.php?feed=old',
    '.*wp-app\.php(/.*)?$' => 'index.php?error=403',
    '.*wp-register.php$' => 'index.php?register=true',
    'feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?&feed=$matches[1]',
    '(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?&feed=$matches[1]',
    'page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
    'comments/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
    'comments/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
    'search/(.+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
    'search/(.+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
    'search/(.+)/page/?([0-9]{1,})/?$' => 'index.php?s=$matches[1]&paged=$matches[2]',
    'search/(.+)/?$' => 'index.php?s=$matches[1]',
    'author/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
    'author/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
    'author/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?author_name=$matches[1]&paged=$matches[2]',
    'author/([^/]+)/?$' => 'index.php?author_name=$matches[1]',
    'date/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
    'date/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
    'date/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
    'date/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
    'date/([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
    'date/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
    'date/([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
    'date/([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
    'date/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
    'date/([0-9]{4})/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
    'date/([0-9]{4})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&paged=$matches[2]',
    'date/([0-9]{4})/?$' => 'index.php?year=$matches[1]',
    '.?.+?/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    '.?.+?/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    '.?.+?/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '.?.+?/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '.?.+?/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    '(.?.+?)/trackback/?$' => 'index.php?pagename=$matches[1]&tb=1',
    '(.?.+?)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]',
    '(.?.+?)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]',
    '(.?.+?)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]',
    '(.?.+?)/comment-page-([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&cpage=$matches[2]',
    '(.?.+?)(/[0-9]+)?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]',
    '[^/]+,[0-9]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    '[^/]+,[0-9]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    '[^/]+,[0-9]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '[^/]+,[0-9]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '[^/]+,[0-9]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
    '([^/]+),([0-9]+)/trackback/?$' => 'index.php?name=$matches[1]&p=$matches[2]&tb=1',
    '([^/]+),([0-9]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?name=$matches[1]&p=$matches[2]&feed=$matches[3]',
    '([^/]+),([0-9]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?name=$matches[1]&p=$matches[2]&feed=$matches[3]',
    '([^/]+),([0-9]+)/page/?([0-9]{1,})/?$' => 'index.php?name=$matches[1]&p=$matches[2]&paged=$matches[3]',
    '([^/]+),([0-9]+)/comment-page-([0-9]{1,})/?$' => 'index.php?name=$matches[1]&p=$matches[2]&cpage=$matches[3]',
    '([^/]+),([0-9]+)(/[0-9]+)?/?$' => 'index.php?name=$matches[1]&p=$matches[2]&page=$matches[3]',
    '[^/]+,[0-9]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
    '[^/]+,[0-9]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
    '[^/]+,[0-9]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '[^/]+,[0-9]+/([^/]+)/(feed|rdf|rss|rss2|atom|sitemap.xml)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
    '[^/]+,[0-9]+/([^/]+)/comment-page-([0-9]{1,})/?$' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
);
update_or_insert('rewrite_rules', serialize($rewrite_rules), 'Règles de réécriture non écrites : ');

update_or_insert('posts_per_page', 5, 'Erreur posts par page');
update_or_insert('posts_per_rss', 3, 'Erreur posts par rss');

update_or_insert('show_avatars', '', 'Erreur avatars');
update_or_insert('default_comment_status', 'closed', 'status des commentaires');
update_or_insert('comment_registration', 1, 'utilisateur enregistré pour commenter');
update_or_insert('timezone_string', 'Europe/Paris', 'Timezone');

update_or_insert('blog_public', 1, 'blog public');
?>
<h2>Liens Externes (pas artX, rubX ou url de spip_urls...) :</h2>
<?php
print_r(Lien::$externes);
?>
<h2>Liens Hors Rubrique :</h2>
<?php
print_r(Lien::$hors_rubrique);

Erreur::dump();

/*
  Mettre à jour le nombre de posts par catégorie...

  Vérifier le nombre de blogs et le chemin pour les fichiers ...
  uploads/site/X ...
 */

/* dump redirections */
$fredir = fopen("redir-$la_rubrique.conf", 'w');
$redirect = '';
$redirect .= Articles::dump_url();
$redirect .= Rubriques::dump_url();
fwrite($fredir, "/* reprise de la rubrique SPIP n° $la_rubrique */\n\n");
fwrite($fredir, $redirect, strlen($redirect));
fclose($fredir);
