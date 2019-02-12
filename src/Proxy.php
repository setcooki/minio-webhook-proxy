<?php

namespace Setcooki\Webhook\Proxy;

/**
 * Class Proxy
 * @package Setcooki\Webhook\Proxy
 */
class Proxy
{
    /**
     * @var null|\stdClass
     */
    protected $config = null;

    /*
     *
     */
    public $debug = false;


    /**
     * Proxy constructor.
     * @param $config
     * @throws \Exception
     */
    public function __construct($config)
    {
        $this->debug = (isset($config['debug'])) ? (bool)$config['debug'] : false;
        try
        {
            $this->config = new Config($config);
            $this->checkServer();
            $this->checkConfig();
            $this->checkReferer();
            $this->checkToken();
        }
        catch(\Exception $e)
        {
            if($this->debug)
            {
                $this->debug('%s (%d)', [$e->getMessage(), $e->getCode()]);
                throw new $e;
            }
        }
    }


    /**
     * @throws \Exception
     */
    protected function checkServer()
    {
        if(!function_exists('curl_init'))
        {
            throw new \Exception('Curl not installed on this server');
        }
    }


    /**
     * @throws \Exception
     */
    protected function checkConfig()
    {
        if(!$this->config->has('endpoints', true))
        {
            throw new \Exception('No endpoints set in config file');
        }
    }


    /**
     * @throws \Exception
     */
    protected function checkReferer()
    {
        if($this->config->has('referer', true))
        {
            $ip = static::getRemoteAddr();
            if($ip === null || !in_array($ip, $this->config->get('referer')))
            {
                throw new \Exception('Remote address is not a allowed');
            }
        }
    }


    /**
     * @throws \Exception
     */
    protected function checkToken()
    {
        if($this->config->has('token', true))
        {
            if(!isset($_REQUEST['token']) || (isset($_REQUEST['token']) && (string)$_REQUEST['token'] !== (string)$this->config->get('token')))
            {
                throw new \Exception('Token is not set or valid');
            }
        }
    }


    /**
     * @param $data
     * @return bool
     */
    protected function forward($data)
    {
        $i = 0;
        $handles = [];
        $options = $this->curlOptions($this->config->get('curl', []));
        foreach($this->config->get('endpoints') as $endpoint)
        {
            $handles[$i] = curl_init();
            $options[CURLOPT_URL] = $endpoint;
            $options[CURLOPT_POSTFIELDS] = $data;
            curl_setopt_array($handles[$i], $options);
            $i++;
        }

        $mh = curl_multi_init();
        foreach($handles as $handle)
        {
            curl_multi_add_handle($mh, $handle);
        }

        $active = null;
        do{
            $mrc = curl_multi_exec($mh, $active);
        }
        while($mrc == CURLM_CALL_MULTI_PERFORM);

        while($active && $mrc == CURLM_OK)
        {
            if((int)curl_multi_select($mh) !== -1)
            {
                do{
                    $mrc = curl_multi_exec($mh, $active);
                }
                while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach($handles as $handle)
        {
            curl_multi_remove_handle($mh, $handle);
        }
        curl_multi_close($mh);
        return true;
    }


    /**
     * @param null $data
     * @return bool
     */
    public function execute($data = null)
    {
        if($data === null)
        {
            $data = file_get_contents("php://input");
        }
        if(!empty($data))
        {
            return $this->forward($data);
        }else{
            if($this->debug){ $this->debug('No post data to forward found'); }
        }
        return false;
    }


    /**
     * @param $message
     * @param array|null $args
     */
    public static function debug($message, Array $args = null)
    {
        if($args !== null)
        {
            $message = vsprintf($message, $args);
        }
        if(strtolower(php_sapi_name()) === 'cli')
        {
            echo $message . PHP_EOL;
        }else{
            echo sprintf('<pre>%s</pre>', $message);
        }
    }


    /**
     * @param array|null $options
     * @return array
     */
    public function curlOptions(Array $options = null)
    {
        return
        ([
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_TIMEOUT => 100,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER => true
        ] + (array)$options);
    }


    /**
     * @return array|false|string|null
     */
    public static function getRemoteAddr()
    {
        $ip = null;
        if(getenv('REMOTE_ADDR')){
            $ip = getenv('REMOTE_ADDR');
        } else if(getenv('HTTP_X_FORWARDED_FOR')){
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        }else if(getenv('HTTP_X_FORWARDED')){
            $ip = getenv('HTTP_X_FORWARDED');
        }else if(getenv('HTTP_FORWARDED_FOR')){
            $ip = getenv('HTTP_FORWARDED_FOR');
        }else if(getenv('HTTP_FORWARDED')){
            $ip = getenv('HTTP_FORWARDED');
        }else if(getenv('HTTP_CLIENT_IP')){
            $ip = getenv('HTTP_CLIENT_IP');
        }
        return $ip;
    }
}