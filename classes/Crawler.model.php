<?php

namespace Crawler;

use BadFunctionCallException;

class Crawler {

    public const OPT_DISPLAY_MEMORY_INFO = 100;
    public const OPT_RESPECT_NOINDEX = 101;
    public const OPT_RESPECT_NOFOLLOW = 102;
    public const OPT_DISPLAY_CRAWLS = 103;

    private array $Pages;
    private \DomDocument $DOM;
    private $Curl;

    private $Options = [];

    private $Events = [];

    public function __construct() {

        $this->Pages = [];

        $this->DOM = @new \DomDocument();
        $this->Curl = curl_init();

        $this->Options = [];
        $this->Events = [];

        $this->set_opt(self::OPT_DISPLAY_MEMORY_INFO, false);
        $this->set_opt(self::OPT_RESPECT_NOINDEX, true);
        $this->set_opt(self::OPT_RESPECT_NOFOLLOW, true);
        $this->set_opt(self::OPT_DISPLAY_CRAWLS, false);

        curl_setopt($this->Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->Curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->Curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->Curl, CURLOPT_VERBOSE, true);
        curl_setopt($this->Curl, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($this->Curl, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($this->Curl, CURLOPT_TIMEOUT, 4);
        curl_setopt($this->Curl, CURLOPT_MAXREDIRS, 10);

        curl_setopt($this->Curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/81.0');
    }

    public function add_event(string $event, callable $callback) : void {

        if(is_null($callback) || !is_callable($callback)){
            return;
        }

        if(!isset($this->Events[$event])){
            $this->Events[$event] = [];
        }

        $this->Events[$event][] = $callback;
    }

    private function trigger_event(string $event, ...$params) : void {

        if(!isset($this->Events[$event])){
            return;
        }

        foreach($this->Events[$event] as $callback){
            call_user_func($callback, ...$params);
        }
    }

    public function set_opt(int $option, mixed $value) : void{

        $this->Options[$option] = $value;
    }

    public function get_opt(int $option) : mixed{

        if(!isset($this->Options[$option])){
            return false;
        }

        return $this->Options[$option];
    }

    public function get_info_from_url(string $url) : array {

        $info = parse_url($url);

        $page = $info['path'] ?? '';

        if(substr($page, strlen($page) - 1) == '/'){
            $page = substr($page, 0, strlen($page) - 1);
        }

        if(strlen($page) == 0){
            $page = '/';
        }

        return [
            'url' => $url,
            'scheme' => $info['scheme'] ?? 'https',
            'host' => $info['host'] ?? '',
            'page' => $page,
        ];
    }

    public function start(string $url) : void {

        $start = time();

        $url_info = $this->get_info_from_url($url);
        $this->Pages = [ $url_info['page'] ];

        $this->crawl_page($url);

        $this->trigger_event('finish', time() - $start);
    }

    private function crawl_page(string $url) : void {

        $display_memory = (bool)$this->get_opt(self::OPT_DISPLAY_MEMORY_INFO);
        $display_crawls = (bool)$this->get_opt(self::OPT_DISPLAY_CRAWLS);

        if($display_memory || $display_crawls){
            $pages_crawled = count($this->Pages);
    
            $display_text = $display_memory ? "Memory usage: {$this->get_memory_usage()}" : '';
            $display_text = $display_crawls ? "{$display_text}; Pages crawled: {$pages_crawled} -> Current crawl: {$url}\n" : "{$display_text}\n";

            echo $display_text;
        }

        $url_info = $this->get_info_from_url($url);

        $this->Pages[] = $url_info['page'];

        $page_info = [];
        $this->fetch_page($url, $page_info);

        if(!$page_info['html']){
            echo "no-html on {$url}\n";
            return;
        }

        $this->DOM->loadHTML($page_info['html']);

        $page_links = [];
        $this->links_from_html($url_info, $page_links);

        print_r($page_links);

        foreach($page_links as $check_page){
            $this->trigger_event('link_found', $check_page);

            $insert_index = $this->search_page($check_page);
            if(($this->Pages[$insert_index] ?? '') == $check_page){
                continue;
            }

            $this->trigger_event('new_link', $check_page);

            array_splice($this->Pages, $insert_index, 0, [$check_page]);

            $this->crawl_page("{$url_info['scheme']}://{$url_info['host']}{$check_page}");
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

        return $end;
    }

    private function links_from_html(array $url_info, array &$output) : void {

        if(!$url_info['host']){
            echo "no host\n";
            return;
        }

        $links = $this->DOM->getElementsByTagName('a');
        foreach($links as $link){
            $link_address = $link->getAttribute('href');

            $link_info = $this->get_info_from_url($link_address);

            if($link_info['host'] && $link_info['host'] != $url_info['host']){
                continue;
            }

            if($link_info['scheme'] && $link_info['host'] != $url_info['scheme']){
                continue;
            }

            if(!in_array($link_info['page'], $output)){
                $output[] = $link_info['page'];
            }
        }
    }

    private function fetch_page(string $url, array &$output) : void {

        curl_setopt($this->Curl, CURLOPT_URL, $url);

        $output = [
            'html' => curl_exec($this->Curl),
            'status' => curl_getinfo($this->Curl, CURLINFO_HTTP_CODE),
        ];

        // curl_close($this->Curl);
    }

    private function get_memory_usage(bool $real_usage = false) : string {
        $size = memory_get_usage($real_usage);
        $unit = ['b','kb','mb','gb','tb','pb'];
        $i = floor(log($size, 1024));
        return @round($size/pow(1024, $i), 2).' '.$unit[$i];
    }
}