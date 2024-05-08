<?php

namespace Crawler\Templates;

class AsyncModel extends Model {

    private AsyncAction $Thread;

    public function __construct(callable $callback) {

        $this->Thread = new AsyncAction($callback);
    }

    public function start() : void {

        try{
            $this->Thread->start();
        } catch(Exception $e){
            echo "An error ocurred while trying to start async action\n";
        }
    }

    public function stop() : void {

        try{
            $this->Thread->join();
        } catch(Exception $e){
            echo "An error ocurred while trying to stop async action\n";
        }
    }

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