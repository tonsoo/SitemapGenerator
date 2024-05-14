<?php

namespace Crawler;

require 'Url.class.php';

class Crawler {

    public const OPT_DISPLAY_MEMORY_INFO = 100;
    public const OPT_DISPLAY_CRAWLS = 101;
    public const OPT_RESPECT_NOINDEX = 102;
    public const OPT_RESPECT_NOFOLLOW = 103;
    public const OPT_RESPECT_CANONICAL = 104;
    public const OPT_SAME_SCHEME_TO_END = 105;
    public const OPT_SAME_SUB_DOMAIN_TO_END = 106;

    public const EVENT_ON_CRAWL = 201;
    public const EVENT_ON_LINK_FOUND = 202;
    public const EVENT_ON_NEW_LINK_FOUND = 203;
    public const EVENT_ON_FINISH = 204;
    public const EVENT_ON_MISSING_HTML = 205;
    public const EVENT_ON_MISMATCH_CONTENT = 206;

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
        $this->set_opt(self::OPT_DISPLAY_CRAWLS, false);

        $this->set_opt(self::OPT_RESPECT_NOINDEX, true);
        $this->set_opt(self::OPT_RESPECT_NOFOLLOW, true);
        $this->set_opt(self::OPT_RESPECT_CANONICAL, true);

        $this->set_opt(self::OPT_SAME_SCHEME_TO_END, true);
        $this->set_opt(self::OPT_SAME_SUB_DOMAIN_TO_END, true);

        curl_setopt($this->Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->Curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->Curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->Curl, CURLOPT_VERBOSE, false);
        curl_setopt($this->Curl, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($this->Curl, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($this->Curl, CURLOPT_TIMEOUT, 4);
        curl_setopt($this->Curl, CURLOPT_MAXREDIRS, 10);

        curl_setopt($this->Curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/81.0');
    }

    public function add_event(int $event, callable $callback) : void {

        if(is_null($callback) || !is_callable($callback)){
            return;
        }

        if(!isset($this->Events[$event])){
            $this->Events[$event] = [];
        }

        $this->Events[$event][] = $callback;
    }

    private function trigger_event(int $event, ...$params) : void {

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

    public function get_info_from_url(string $url) : Url {

        return new Url($url);
    }

    public function start(string $url) : void {

        $start = time();

        $this->crawl_page($url);

        $this->trigger_event(self::EVENT_ON_FINISH, time() - $start);
    }

    private function search_page(string $page) : mixed {

        $start = 0;
        $end = count($this->Pages);
        while($end - $start > 1) {
            $mid = floor(($start + $end) / 2);
            if ($this->Pages[$mid] < $page){
                $start = $mid;
            } else{
                $end = $mid;
            }
        }

        return $end;
    }

    private function crawl_page(string $url) : void {

        $url_info = $this->get_info_from_url($url);

        $insert_index = $this->search_page($url_info->Page);
        if(($this->Pages[$insert_index] ?? '') == $url_info->Page){
            return;
        }
        
        array_splice($this->Pages, $insert_index, 0, [ $url_info->Page ]);

        $display_memory = (bool)$this->get_opt(self::OPT_DISPLAY_MEMORY_INFO);
        $display_crawls = (bool)$this->get_opt(self::OPT_DISPLAY_CRAWLS);

        if($display_memory || $display_crawls){
            $pages_crawled = count($this->Pages);
    
            $display_text = $display_memory ? "Memory usage: {$this->get_memory_usage()}; " : '';
            $display_text = $display_crawls ? "{$display_text}Pages crawled: {$pages_crawled} -> Current crawl: {$url}\n" : "{$display_text}\n";

            echo $display_text;

            unset($display_text);
            unset($pages_crawled);
        }

        unset($display_memory);
        unset($display_crawls);

        $page_info = [];
        $this->get_url_content($url, $page_info);

        if(!$page_info['html']){
            $this->trigger_event(self::EVENT_ON_MISSING_HTML, $url_info, $page_info);
            return;
        }

        if(!preg_match('/text\/html/i', $page_info['content-type'])){
            unset($page_info['html']);
            $this->trigger_event(self::EVENT_ON_MISMATCH_CONTENT, $url_info, $page_info);
            return;
        }

        $this->DOM->loadHTML($page_info['html']);
        unset($page_info);

        $robots = $this->get_robots();
        $canonical = $this->get_canonical_url($url);

        $this->trigger_event(self::EVENT_ON_CRAWL, $url_info, $robots, $canonical);

        if(!$robots['follow']){
            return;
        }

        unset($robots);
        unset($canonical);

        $page_links = [];
        $this->get_links($url_info, $page_links);

        foreach($page_links as $page_index => &$check_page){
            $this->trigger_event(self::EVENT_ON_LINK_FOUND, $url_info, $check_page);

            $this->crawl_page("{$url_info->Scheme}://{$url_info->Host}{$check_page}");

            unset($page_links[$page_index]);
        }

        unset($url_info);
    }

    private function get_canonical_url(string &$url) : string {

        $links = $this->DOM->getElementsByTagName('link');
        foreach($links as $link){
            $rel = $link->getAttribute('rel');
            if($rel != 'canonical'){
                continue;
            }

            $href = $link->getAttribute('href');
            return $href;
        }

        return $url;
    }

    private function get_robots() : array {

        $robots = [
            'index' => true,
            'follow' => true,
        ];

        $respect_noindex = $this->get_opt(self::OPT_RESPECT_NOINDEX);
        $respect_nofollow = $this->get_opt(self::OPT_RESPECT_NOFOLLOW);

        if(!$respect_noindex && !$respect_nofollow){
            return $robots;
        }

        $metas = $this->DOM->getElementsByTagName('meta');
        foreach($metas as $meta) {
            $name = $meta->getAttribute('name');
            if($name != 'robots'){
                continue;
            }

            $content = $meta->getAttribute('content');

            if($respect_noindex){
                $robots['index'] = !preg_match('/noindex/', $content);
            }
            if($respect_nofollow){
                $robots['follow'] = !preg_match('/nofollow/', $content);
            }

            break;
        }

        return $robots;
    }

    private function get_links(Url $url_info, array &$output) : void {

        if(!$url_info->Host){
            return;
        }

        $output = [];

        $links = $this->DOM->getElementsByTagName('a');
        foreach($links as $link){
            $link_address = $link->getAttribute('href');
            $link_address = strtolower($link_address);

            $link_info = $this->get_info_from_url($link_address);

            if($link_info->Host && $link_info->Host != $url_info->Host){
                continue;
            }

            if($link_info->Scheme && $link_info->Scheme != $url_info->Scheme){
                continue;
            }

            if(!in_array($link_info->Page, $output)){
                $output[] = $link_info->Page;
            }
        }
    }

    private function get_url_content(string $url, array &$output) : void {

        curl_setopt($this->Curl, CURLOPT_URL, $url);

        $output = [
            'html' => curl_exec($this->Curl),
            'status' => curl_getinfo($this->Curl, CURLINFO_HTTP_CODE),
            'content-type' => curl_getinfo($this->Curl, CURLINFO_CONTENT_TYPE),
            'redirect-url' => curl_getinfo($this->Curl, CURLINFO_REDIRECT_URL),
        ];
    }

    private function get_memory_usage(bool $real_usage = false) : string {
        $size = memory_get_usage($real_usage);
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $i = floor(log($size, 1024));
        return @round($size / pow(1024, $i), 2)."{$unit[$i]}";
    }
}