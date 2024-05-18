<?php

namespace Crawler;

require 'Url.class.php';

class Crawler {

    public const OPT_DISPLAY_MEMORY_INFO = 100; // Determines if memory usage should be displayed in each crawl
    public const OPT_DISPLAY_CRAWLS = 101; // Determines if the url crawled should be displayed in the console
    public const OPT_RESPECT_NOINDEX = 102; // Determines if the program should search for the "noindex" inside the "robots" or will it simply ignore it
    public const OPT_RESPECT_NOFOLLOW = 103; // Determines if the program should search for the "nofollow" inside the "robots" or will it simply ignore it
    public const OPT_RESPECT_CANONICAL = 104; // Determines if the program should search for the canonical url of the page crawled
    public const OPT_PRESERVE_SCHEME = 105; // Determines if the scheme used to connect will be preserved, connections started with "https" will remain "https" until the end of execution
    public const OPT_PERSERVE_HOST = 106; // Determines if the host used to connect will be preserved, connections on the host "example.com" will only connect to other pages inside the host "example.com"

    public const EVENT_ON_CRAWL = 201; // Event that is called in each url crawl
    public const EVENT_ON_LINK_FOUND = 202; // Event called whenever a link is found
    public const EVENT_ON_NEW_LINK_FOUND = 203; // Event called whenever a NEW link is found
    public const EVENT_ON_FINISH = 204; // Event called after the crawled has reached the end
    public const EVENT_ON_MISSING_HTML = 205; // Event called whenever a url does not respond with html content
    public const EVENT_ON_MISMATCH_CONTENT = 206; // Event called whenever a url response is not "text/html"

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

        $this->set_opt(self::OPT_PRESERVE_SCHEME, true);
        $this->set_opt(self::OPT_PERSERVE_HOST, true);

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

    public function set_opt(int $option, mixed $value) : void {

        $this->Options[$option] = $value;
    }

    public function get_opt(int $option) : mixed {

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
        $built_url = $url_info->buildUrl();

        // $insert_index = $this->search_page($built_url);
        // if(($this->Pages[$insert_index] ?? '') == $built_url){
        //     return;
        // }
        $insert_index = array_search($built_url, $this->Pages);
        if(false !== $insert_index){
            return;
        }
        
        $this->trigger_event(self::EVENT_ON_NEW_LINK_FOUND, $url_info);

        array_splice($this->Pages, $insert_index, 0, [ $built_url ]);

        $display_memory = (bool)$this->get_opt(self::OPT_DISPLAY_MEMORY_INFO);
        $display_crawls = (bool)$this->get_opt(self::OPT_DISPLAY_CRAWLS);

        if($display_memory || $display_crawls){
            $pages_crawled = count($this->Pages);
    
            $display_text = $display_memory ? "Memory usage: {$this->get_memory_usage()}; " : '';
            $display_text = $display_crawls ? "{$display_text}Pages crawled: {$pages_crawled} -> Current crawl: {$built_url}\n" : "{$display_text}\n";

            echo $display_text;

            unset($display_text);
            unset($pages_crawled);
        }

        unset($display_memory);
        unset($display_crawls);

        $page_info = [];
        $this->get_url_remote_information($url, $page_info);

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
        unset($page_info['html']);

        $robots = $this->get_robots();
        $canonical = $this->get_canonical_url($url);

        $this->trigger_event(self::EVENT_ON_CRAWL, $url_info, $robots, $canonical, $page_info);

        unset($page_info);

        if(!$robots['follow']){
            return;
        }

        unset($robots);
        unset($canonical);

        $page_links = [];
        $this->get_links($url_info, $page_links);

        foreach($page_links as $page_index => &$check_page){
            $this->trigger_event(self::EVENT_ON_LINK_FOUND, $url_info, $check_page);

            $crawl_url = $check_page->buildUrl();
            if($crawl_url){
                $this->crawl_page($crawl_url);
                unset($crawl_url);
            }

            unset($page_links[$page_index]);
        }

        unset($url_info);
    }

    private function get_canonical_url(string &$url) : string {

        if(!$this->get_opt(self::OPT_RESPECT_CANONICAL)){
            return $url;
        }

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

        if(!$url_info->Host || !$url_info->Scheme){
            return;
        }

        $preserve_scheme = (bool)$this->get_opt(self::OPT_PRESERVE_SCHEME);
        $preserve_host = (bool)$this->get_opt(self::OPT_PERSERVE_HOST);

        $output = [];

        $links = $this->DOM->getElementsByTagName('a');
        foreach($links as $link){
            $link_address = $link->getAttribute('href');

            $link_info = $this->get_info_from_url($link_address);

            if($preserve_host && $link_info->Host != $url_info->Host){
                continue;
            }

            if($preserve_scheme && $link_info->Scheme != $url_info->Scheme){
                continue;
            }

            $page_to_add = $link_info->Page;
            if(substr($link_info->Page, 0, 1) != '/'){
                $current_page = explode('/', $url_info->Page);
                $current_page = array_slice($current_page, 0, count($current_page) - 1);
                $current_page = implode('/', $current_page);

                $page_to_add = "{$current_page}/{$page_to_add}";
            }

            $url_to_add = $preserve_host ? $url_info->buildUrl($page_to_add) : $link_info->buildUrl($page_to_add);

            if(!in_array($url_to_add, $output)){
                $output[] = $link_info;
            }
        }
    }

    private function get_url_remote_information(string $url, array &$output) : void {

        curl_setopt($this->Curl, CURLOPT_URL, $url);

        $output = [
            'html' => curl_exec($this->Curl),
            'status' => curl_getinfo($this->Curl, CURLINFO_HTTP_CODE),
            'content-type' => curl_getinfo($this->Curl, CURLINFO_CONTENT_TYPE),
            'redirect-url' => curl_getinfo($this->Curl, CURLINFO_REDIRECT_URL),
            'response-time' => curl_getinfo($this->Curl, CURLINFO_TOTAL_TIME),
        ];
    }

    private function get_memory_usage(bool $real_usage = false) : string {
        $size = memory_get_usage($real_usage);
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $i = floor(log($size, 1024));
        return @round($size / pow(1024, $i), 2)."{$unit[$i]}";
    }
}