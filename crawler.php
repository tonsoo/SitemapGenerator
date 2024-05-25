<?php

use Crawler\Crawler;

ini_set('memory_limit', -1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require './classes/Crawler.class.php';

// $crawl_url = 'https://www.urbs.curitiba.pr.gov.br';
// $crawl_url = 'https://gamejolt.com';
$crawl_url = 'https://github.com'; // Has over 200.000 pages
$crawl_host = parse_url($crawl_url)['host'] ?? 'unknown-host';

$start = date('Y-m-d.H-i-s');

function open_sitemap(string $sitemap_folder, &$sitemap_file){
    static $page_index = 0;
    $page_index++;
    $sitemap_file = fopen("{$sitemap_folder}/sitemap-{$page_index}.xml", 'w');
    if(!is_resource($sitemap_file)){
        $page_index--;
        open_sitemap($sitemap_folder, $sitemap_file);
        return;
    }

    fwrite($sitemap_file, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset\n\txmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n\txsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\nhttp://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n");
}

function close_sitemap(&$sitemap_file){
    fwrite($sitemap_file, '</urlset>');
    fclose($sitemap_file);
}

$sitemap_folder = "sitemaps/{$crawl_host}/{$start}/";
if(!file_exists($sitemap_folder) || !is_dir($sitemap_folder)){
    mkdir($sitemap_folder, 0777, true);
}

open_sitemap($sitemap_folder, $sitemap_file);
$links_file = fopen("{$sitemap_folder}/links.txt", 'w');

$pages_indexed = 0;

$crawler = new Crawler(1000);

$crawler->set_opt(Crawler::OPT_PERSERVE_HOST, true);

$crawler->set_opt(Crawler::OPT_DISPLAY_CRAWLS, true);
$crawler->set_opt(Crawler::OPT_DISPLAY_MEMORY_INFO, true);
$crawler->set_opt(Crawler::OPT_RESPECT_CANONICAL, false);
$crawler->set_opt(Crawler::OPT_RESPECT_NOINDEX, true);
$crawler->set_opt(Crawler::OPT_RESPECT_NOFOLLOW, true);

$crawler->add_event(Crawler::EVENT_ON_CRAWL, function($url_info, $robots, $canonical, $page_info) use (&$sitemap_file, $links_file, $sitemap_folder, &$pages_indexed) {

    $url = $url_info->buildUrl();

    $skip_url = !$robots['index'] || ($canonical != $url_info->Url && $canonical != $url) || $page_info['http_code'] != 200;

    $skip_write = $skip_url ? 'skipped' : 'written';

    $index_write = $robots['index'] ? 'index' : 'noindex';
    $follow_write = $robots['follow'] ? 'follow' : 'nofollow';
    fwrite($links_file, "({$page_info['http_code']} - {$skip_write}) {$page_info['content_type']} [{$index_write}, {$follow_write}] {$page_info['total_time']}ms {$url} -> {$canonical}\n");

    if($skip_url){
        return;
    }

    fwrite($sitemap_file, "\t<url>\n\t\t<loc>{$url}</loc>\n\t</url>\n");

    $pages_indexed++;

    if($pages_indexed > 45000){
        $pages_indexed = 0;
        close_sitemap($sitemap_file);
        open_sitemap($sitemap_folder, $sitemap_file);
    }
});

$crawler->add_event(Crawler::EVENT_ON_FINISH, function($elapsed_time) use ($sitemap_file) {
    echo "Finished in {$elapsed_time}s\n";
    close_sitemap($sitemap_file);
});

$crawler->add_event(Crawler::EVENT_ON_MISMATCH_CONTENT, function($url_info, $info) {
    echo "{$url_info->Url} -> {$info['content_type']}\n";
});

$crawler->start($crawl_url);
