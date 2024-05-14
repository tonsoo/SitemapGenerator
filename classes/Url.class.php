<?php

namespace Crawler;

class Url {
    
    private string $Url;
    private string $Scheme;
    private string $Host;
    private string $Page;

    public function __construct(string $url) {
        
        $url_info = parse_url($url);

        $this->Url = $url;
        $this->Host = $url_info['host'] ?? '';
        $this->Scheme = $url_info['scheme'] ?? 'https';

        $page = $url_info['path'] ?? '';
        if(substr($page, strlen($page) - 1) == '/'){
            $page = substr($page, 0, strlen($page) - 1);
        }

        if(strlen($page) == 0){
            $page = '/';
        }

        $this->Page = $page;
    }

    public function __get(string $attr){

        if(isset($this->$attr)){
            return $this->$attr;
        }

        return null;
    }
}