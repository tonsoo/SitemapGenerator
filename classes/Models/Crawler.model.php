<?php

namespace Crawler;

use Crawler\Templates\Model;

class Crawler extends Model {

    private array $Pages;
    private \DomDocument $DOM;
    private $Curl;

    private array $AsyncCrawls;
    private int $AsyncCrawlsLimit;

    public function __construct(int $asyncCrawlsLimit = 2) {

        if($asyncCrawlsLimit < 1){
            $asyncCrawlsLimit = 1;
        }

        $this->AsyncCrawlsLimit = $asyncCrawlsLimit;
        $this->Pages = [];
        $this->AsyncCrawls = [];

        $this->DOM = @new \DomDocument();
        $this->Curl = curl_init();

        curl_setopt($this->Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->Curl, CURLOPT_VERBOSE, 0);
        curl_setopt($this->Curl, CURLOPT_SSL_VERIFYPEER, 0);
    }

    public function start(string $url) : void {

        $memory_usage = memory_get_usage_converted();
        $pages_crawled = count($this->Pages);
        echo "{$pages_crawled} crawled; {$memory_usage} -> {$url}\n";

        $url_info = parse_url($url);
        $scheme = $url_info['scheme'] ?? 'https';
        $host = $url_info['host'] ?? '';
        $__page = $url_info['path'] ?? '';

        $page_info = [];

        $this->fetch_page($url, $page_info);

        $page_html = $page_info['html'];
        if(!$page_html){
            return;
        }

        $page_links = [];

        $this->links_from_html($url_info, $page_html, $page_links);

        foreach($page_links as $page_index => $check_page){
            $insert_index = $this->search_page($check_page, true);
            if($this->Pages[$insert_index] == $check_page){
                continue;
            }

            array_splice($this->Pages, $insert_index, 0, [$check_page]);

            $this->start("{$scheme}://{$host}{$check_page}");
        }
    }

    private function search_page(string $page, bool $best_match = false) : int {

        $start = 0;
        $end = count($this->Pages);

        while($end - $start > 1){
            $middle = ($start + $end) / 2;
            if($this->Pages[$middle] < $page){
                $start = $middle;
            } else {
                $end = $middle;
            }
        }

        return $this->Pages[$end] == $page || $best_match ? $end : -1;
    }

    private function links_from_html(array $url_info, string &$html, array &$output) : void {

        if(!($url_info['host'] ?? '')){
            return;
        }

        $host = $url_info['host'];
        $scheme = $url_info['scheme'] ?? 'https';
        $page = $url_info['path'] ?? '';

        $this->DOM->loadHTML($html);

        $links = $this->DOM->getElementsByTagName('a');
        foreach($links as $link){
            $link_address = $link->getAttribute('href');

            $link_info = parse_url($link_address);
            $link_host = $link_info['host'] ?? '';
            $link_scheme = $link_info['scheme'] ?? '';
            $link_page = $link_info['path'] ?? '/';

            if($link_host && $link_host != $host){
                continue;
            }

            if($link_scheme && $link_scheme != $scheme){
                continue;
            }

            if($link_page){
                if(substr($link_page, 0, 1) != '/'){
                    $paths = explode('/', $page);
                    $removed_last = implode('/', array_slice($paths, 0, count($paths) - 1));
                    $link_page = "{$removed_last}/{$link_page}";
                }
            }

            if(!in_array($link_page, $output)){
                $output[] = $link_page;
            }
        }
    }

    private function fetch_page(string $url, array &$output) : void {

        curl_setopt($this->Curl, CURLOPT_URL, $url);

        $output = [
            'html' => curl_exec($this->Curl),
            'status' => curl_getinfo($this->Curl, CURLINFO_HTTP_CODE)
        ];

        // curl_close($this->Curl);
    }
}