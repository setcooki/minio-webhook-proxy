<?php

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
        $proxy = new \Setcooki\Webhook\Proxy\Proxy($config);
        $proxy->execute();
    }else{
        echo 'No config.json file found in current directory' . PHP_EOL;
        exit(1);
    }
}
catch(\Exception $e)
{
    error_log($e->getMessage());
}