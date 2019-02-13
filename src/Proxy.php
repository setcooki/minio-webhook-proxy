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

    /**
     * @var bool
     */
    public $debug = false;

    /**
     * @var bool
     */
    public $log = false;


    /**
     * Proxy constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->log = (isset($config['log'])) ? (bool)$config['log'] : false;
        $this->debug = (isset($config['debug'])) ? (bool)$config['debug'] : false;
        try
        {
            $this->config = new Config($config);
            $this->setup();
            $this->checkServer();
            $this->checkConfig();
            $this->checkEndpoints();
            $this->checkReferer();
            $this->checkToken();
        }
        catch(\Exception $e)
        {
            if($this->log)
            {
                static::log($e);
            }
            if($this->debug)
            {
                static::debug('%s (%d)', [$e->getMessage(), $e->getCode()]);
                throw new $e;
            }
        }
    }


    /**
     *
     */
    protected function setup()
    {
        $GLOBALS['webhook_proxy_debug'] = 0;
        $GLOBALS['webhook_proxy_log'] = 0;

        if($this->debug)
        {
            $GLOBALS['webhook_proxy_debug'] = 1;
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        if($this->log)
        {
            $GLOBALS['webhook_proxy_log'] = 1;
            $dir = dirname(__FILE__) . '/../logs';
            if(!is_dir($dir))
            {
                mkdir($dir, 0755);
            }else{
                chmod($dir, 0775);
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
    protected function checkEndpoints()
    {
        if($this->config->has('endpoints', true))
        {
            foreach($this->config->has('endpoints') as $endpoint)
            {
                if(!filter_var($endpoint, FILTER_VALIDATE_URL))
                {
                    throw new \Exception(sprintf('Endpoint: %s is not a valid url', $endpoint));
                }
            }
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
                throw new \Exception('Remote address is not allowed to use webhook');
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
        $fp = null;
        $handles = [];
        $options = $this->curlOptions($this->config->get('curl', []));

        if($this->debug)
        {
            $options[CURLOPT_VERBOSE] = true;
            if($this->log)
            {
                $options[CURLOPT_STDERR] = @fopen(dirname(__FILE__ . '/../logs/proxy.log'), 'a+');
            }else{
                $options[CURLOPT_STDERR] = $fp = fopen('php://temp', 'w+');
            }
        }

        $mh = curl_multi_init();
        foreach($this->config->get('endpoints') as $endpoint)
        {
            $handles[$i] = curl_init();
            $options[CURLOPT_URL] = $endpoint;
            $options[CURLOPT_POSTFIELDS] = $data;
            $options[CURLOPT_HTTPHEADER] = ['Cache-Control: no-cache', 'Content-length: ' . strlen($data)];
            foreach($options as $key => $val)
            {
                curl_setopt($handles[$i], $key, $val);
            }
            if($this->debug)
            {
                static::debug($options);
            }
            curl_multi_add_handle($mh, $handles[$i]);
            $i++;
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
                    if($mrc > 0)
                    {
                        $this->debug(sprintf('Curl error %s', curl_multi_strerror($mrc)));
                    }
                }
                while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        $errors = 0;
        foreach($handles as $handle)
        {
            $error = curl_error($handle);
            if(!empty($error))
            {
                $errors++;
                if($fp)
                {
                    rewind($fp);
                    static::log(stream_get_contents($fp));
                }
                static::log(sprintf('%s (%d)', $error, curl_errno($handle)));
            }else{
                if($this->debug)
                {
                    static::debug(curl_getinfo($handle));
                    static::debug(curl_multi_getcontent($handle));
                }
            }
            curl_multi_remove_handle($mh, $handle);
        }
        curl_multi_close($mh);
        return ($errors) ? false : true;
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
        if($this->debug)
        {
            static::debug($data);
        }
        if(!empty($data))
        {
            return $this->forward($data);
        }else{
            if($this->debug){ static::debug('No post data to forward found'); }
        }
        return false;
    }


    /**
     * @param $message
     * @param array|null $args
     */
    public static function debug($message, Array $args = null)
    {
        if(is_array($message) || is_object($message))
        {
            $message = json_encode($message);
        }
        if($args !== null)
        {
            $message = vsprintf($message, $args);
        }
        if(array_key_exists('webhook_proxy_debug', $GLOBALS) && $GLOBALS['webhook_proxy_debug'] === 1)
        {
            static::log($message, $args);
        }else{
            if(strtolower(php_sapi_name()) === 'cli')
            {
                echo $message . PHP_EOL;
            }else{
                echo sprintf('<pre>%s</pre>', $message);
            }
        }
    }


    /**
     * @param $message
     * @param array|null $args
     */
    public static function log($message, Array $args = null)
    {
        $dir = dirname(__FILE__) . '/../logs/';

        if($message instanceof \Exception)
        {
            $message = sprintf('%s (%d)', $message->getMessage(), $message->getLine());
        }else if(is_array($message) || is_object($message)){
            $message = json_encode($message);
        }
        if($args !== null)
        {
            $message = vsprintf($message, $args);
        }
        if(is_dir($dir) && is_writable($dir))
        {
            $message = sprintf('[%s] %s', strftime('%D %T', time()), $message);
            file_put_contents(dirname(__FILE__) . '/../logs/proxy.log', "$message\n", FILE_APPEND);
        }else{
            error_log($message);
        }
    }


    /**
     * @param array|null $options
     * @return array
     */
    protected function curlOptions(Array $options = null)
    {
        return
        ([
            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_TIMEOUT => 60,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_SSL_VERIFYPEER >= false
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