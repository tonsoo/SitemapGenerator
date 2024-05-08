<?php

if(!isset($folder)){
    $folder = __DIR__;
}

$files = glob("{$folder}/*", GLOB_BRACE);
foreach($files as $file){
    if(basename($file) == basename(__FILE__)){
        continue;
    }

    if(is_dir($file)){
        $folder = $file;
        require __FILE__;
        continue;
    }

    try{
        require $file;
    } catch(Exception $e){
        echo "Error requiring {$file}";
    }
}
