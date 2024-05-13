<?php

use Crawler\Crawler;

require 'classes/Crawler.model.php';

$crawler = new Crawler();

$crawler->set_opt(Crawler::OPT_DISPLAY_CRAWLS, true);
$crawler->set_opt(Crawler::OPT_DISPLAY_MEMORY_INFO, true);

$crawler->add_event('link_found', function($link) {
    echo "link: {$link}\n";
});

$crawler->add_event('finish', function($elapsed_time){
    echo "Finished in {$elapsed_time}s\n";
});

$crawler->start('https://www.nacionaleng.com.br');
