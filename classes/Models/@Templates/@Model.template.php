<?php

namespace Crawler\Templates;

class Model {

    public function __construct() { }

    public function __get(string $attr) {

        if(!isset($this->$attr)){
            echo "Trying to access null parameter\n";
            return;
        }

        if(substr($attr, 0, 1) == '_'){
            echo "Trying to access private data\n";
            return;
        }

        return $this->$attr;
    }
}
