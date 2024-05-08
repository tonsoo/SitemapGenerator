<?php

namespace Crawler\Templates;

class AsyncAction {

    private $Callback;
    private array $Parameters;

    public function __construct(callable $callback, ...$params) {

        $this->Callback = $callback;
        $this->Parameters = $params;
    }

    public function run(){

        if(!$this->Callback || !is_callable($this->Callback) || is_null($this->Callback)){
            return;
        }

        call_user_func($this->Callback, ...$this->Parameters);
    }
}
