<?php

use Crawler\Crawler;

require 'classes/Crawler.class.php';

$crawler = new Crawler();

$crawler->set_opt(Crawler::OPT_DISPLAY_CRAWLS, true);
$crawler->set_opt(Crawler::OPT_DISPLAY_MEMORY_INFO, true);
$crawler->set_opt(Crawler::OPT_RESPECT_CANONICAL, true);
$crawler->set_opt(Crawler::OPT_RESPECT_NOINDEX, true);
$crawler->set_opt(Crawler::OPT_RESPECT_NOFOLLOW, true);

$crawler->add_event(Crawler::EVENT_ON_CRAWL, function($url_info, $robots, $canonical) {
    
    $index = $robots['index'];
    if(!$index){
        return;
    }

    $url = $url_info->Url;
    // echo "{$url} -> page: {$url_info['page']}\n";
});

$crawler->add_event(Crawler::EVENT_ON_FINISH, function($elapsed_time){
    echo "Finished in {$elapsed_time}s\n";
});

$crawler->add_event(Crawler::EVENT_ON_MISMATCH_CONTENT, function($url_info, $info) {
    echo "{$url_info['page']} -> {$info['content-type']}\n";
});

$crawler->start('https://facebook.com');
