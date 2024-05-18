<?php

use Crawler\Crawler;

require 'classes/Crawler.class.php';

// $crawl_url = 'https://www.urbs.curitiba.pr.gov.br';
// $crawl_url = 'https://gamejolt.com';
$crawl_url = 'https://github.com'; // Has over 200.000 pages
$crawl_host = parse_url($crawl_url)['host'] ?? 'unknown-host';

$start = date('Y-m-d.H-i-s');

$sitemap_folder = "sitemaps/{$crawl_host}/{$start}/";
if(!file_exists($sitemap_folder) || !is_dir($sitemap_folder)){
    mkdir($sitemap_folder, 0777, true);
}

$sitemap_file = fopen("{$sitemap_folder}/sitemap.xml", 'w');
$links_file = fopen("{$sitemap_folder}/links.txt", 'w');
fwrite($sitemap_file, "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\" xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n");

$crawler = new Crawler();

$crawler->set_opt(Crawler::OPT_PERSERVE_HOST, true);

$crawler->set_opt(Crawler::OPT_DISPLAY_CRAWLS, true);
$crawler->set_opt(Crawler::OPT_DISPLAY_MEMORY_INFO, true);
$crawler->set_opt(Crawler::OPT_RESPECT_CANONICAL, false);
$crawler->set_opt(Crawler::OPT_RESPECT_NOINDEX, true);
$crawler->set_opt(Crawler::OPT_RESPECT_NOFOLLOW, true);

$crawler->add_event(Crawler::EVENT_ON_CRAWL, function($url_info, $robots, $canonical, $page_info) use ($sitemap_file, $links_file) {

    $url = $url_info->buildUrl();

    $skip_url = !$robots['index'] || ($canonical != $url_info->Url && $canonical != $url) || $page_info['status'] != 200;

    $skip_write = $skip_url ? 'skipped' : 'written';

    $index_write = $robots['index'] ? 'index' : 'noindex';
    $follow_write = $robots['follow'] ? 'follow' : 'nofollow';
    fwrite($links_file, "({$page_info['status']} - {$skip_write}) {$page_info['content-type']} [{$index_write}, {$follow_write}] {$page_info['response-time']}ms {$url} -> {$canonical}\n");

    if($skip_url){
        return;
    }

    fwrite($sitemap_file, "\t<url>\n\t\t<loc>{$url}</loc>\n\t</url>\n");
});

$crawler->add_event(Crawler::EVENT_ON_FINISH, function($elapsed_time) use ($sitemap_file) {
    echo "Finished in {$elapsed_time}s\n";
    fwrite($sitemap_file, '</urlset>');
});

$crawler->add_event(Crawler::EVENT_ON_MISMATCH_CONTENT, function($url_info, $info) {
    echo "{$url_info->Url} -> {$info['content-type']}\n";
});

$crawler->start($crawl_url);
