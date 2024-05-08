<?php

use Crawler\Crawler;

function memory_get_usage_converted() {
    $size = memory_get_usage();
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

require 'classes/autoload.php';

$crawler = new Crawler();
$crawler->start('https://www.php.net/');
