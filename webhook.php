<?php

error_reporting(E_ALL);

try
{
    require_once dirname(__FILE__) . '/lib/vendor/autoload.php';
    $config = dirname(__FILE__) . '/config.json';
    if
    (
        is_file($config)
        &&
        ($config = file_get_contents($config)) !== false
        &&
        ($config = json_decode($config, true)) !== null
    ){
        echo (int)(new \Setcooki\Webhook\Proxy\Proxy($config))->execute();
    }else{
        echo 'No config.json file found in current directory or unable to decode' . PHP_EOL;
        exit(1);
    }
}
catch(\Exception $e)
{
    \Setcooki\Webhook\Proxy\Proxy::log($e);
    echo 0;
}