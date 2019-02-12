<?php

namespace Setcooki\Webhook\Proxy;

/**
 * Class Config
 * @package Setcooki\Webhook\Proxy
 */
class Config
{
    /**
     * @var mixed|null
     */
    public $config = null;


    /**
     * Config constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = static::objectToArray($config);
    }


    /**
     * @param null $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key = null, $default = null)
    {
        $config = $this->config;
        if($key !== null)
        {
            if(array_key_exists($key, $config))
            {
                return $config[$key];
            }
            foreach(explode('.', trim($key, '.')) as $k => $v)
            {
                if(!is_array($config) || !array_key_exists($v, $config))
                {
                    return $default;
                }
                $config = $config[$v];
            }
        }
        return $config;
    }


    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function set($key, $value)
    {
        $config =& $this->config;
        if(strpos($key, '.') === false)
        {
            $config[$key] = $value;
            return $config[$key];
        }
        $keys = explode('.', trim($key, '.'));
        while(count($keys) > 1)
        {
            $key = array_shift($keys);
            if(!isset($config[$key]) || !is_array($config[$key]))
            {
                $config[$key] = [];
            }
            $config =& $config[$key];
        }
        $config[array_shift($keys)] = $value;
        return true;
    }


    /**
     * @param null $key
     * @param bool $strict
     * @return bool
     */
    function has($key = null, $strict = false)
    {
        $config = $this->config;
        if($key === null)
        {
            return (!empty($config)) ? true : false;
        }
        if(array_key_exists($key, $config))
        {
            if((bool)$strict)
            {
                return (static::isValue($config[$key])) ? true : false;
            }else{
                return true;
            }
        }
        foreach(explode('.', trim($key, '.')) as $k => $v)
        {
            if(!is_array($config) || !array_key_exists($v, $config))
            {
                return false;
            }
            $config = $config[$v];
        }
        if((bool)$strict)
        {
            return (static::isValue($config)) ? true : false;
        }else{
            return true;
        }
    }


    /**
     * @param null $value
     * @return bool
     */
    public static function isValue($value = null)
    {
        if(is_null($value))
        {
            return false;
        }
        if(is_bool($value) && $value === false)
        {
            return false;
        }
        if(is_array($value) && empty($value))
        {
            return false;
        }
        if(is_string($value) && $value === '')
        {
            return false;
        }
        return true;
    }



    /**
     * @param $object
     * @param null $default
     * @return mixed|null
     */
    public static function objectToArray($object, $default = null)
    {
        if(is_array($object))
        {
            return $object;
        }
        if(($object = json_encode($object)) !== false)
        {
            return json_decode($object, true);
        }else{
            return $default;
        }
    }
}