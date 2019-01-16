<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

abstract class Input {
    protected static function fetchVar($name, $type, $sanitize, $isArray, $filter, $options) {
        if ($sanitize && $filter === FILTER_DEFAULT) {
            $filter = FILTER_SANITIZE_STRING; //FILTER_SANITIZE_FULL_SPECIAL_CHARS;
        }
        
        if($isArray) 
            $options['flags'] |= FILTER_REQUIRE_ARRAY;
        
        $var = filter_input($type, $name, $filter, $options);
        return $var;
    }
    
    public static function get($name = null, $sanitize = true, $isArray = false, $options = ['flags' => 0], $filter = FILTER_DEFAULT) {
        if($name === null)
            return empty($_GET);
        return self::fetchVar($name, INPUT_GET, $sanitize, $isArray, $filter, $options);
    }
    
    public static function post($name = null, $sanitize = true, $isArray = false, $options = ['flags' => 0], $filter = FILTER_DEFAULT) {
        if($name === null)
            return !empty($_POST);

        return self::fetchVar($name, INPUT_POST, $sanitize, $isArray, $filter, $options);
    }

    public static function getInt($name, $sanitize = false, $isArray = false, $options = ['flags' => 0]) {
        $filter = $sanitize ? FILTER_SANITIZE_NUMBER_INT : FILTER_VALIDATE_INT;
        return self::get($name, false, $isArray, $options, $filter);
    }

    public static function getFloat($name, $sanitize = false, $isArray = false, $options = ['flags' => 0]) {
        $filter = $sanitize ? FILTER_SANITIZE_NUMBER_FLOAT : FILTER_VALIDATE_FLOAT;
        return self::get($name, false, $isArray, $options, $filter);
    }
    
    public static function getBool($name) {
        return self::get($name, false, false, ['flags' => 0], FILTER_VALIDATE_BOOLEAN);
    }

    public static function postInt($name, $sanitize = false, $isArray = false, $options = ['flags' => 0]) {
        $filter = $sanitize ? FILTER_SANITIZE_NUMBER_INT : FILTER_VALIDATE_INT;
        return self::post($name, false, $isArray, $options, $filter);
    }

    public static function postFloat($name, $sanitize = false, $isArray = false, $options = ['flags' => 0]) {
        $filter = $sanitize ? FILTER_SANITIZE_NUMBER_FLOAT : FILTER_VALIDATE_FLOAT;
        return self::post($name, false, $isArray, $options, $filter);
    }
    
    public static function postBool($name) {
        return self::post($name, false, false, ['flags' => 0], FILTER_VALIDATE_BOOLEAN);
    }
    
    public static function getValidate($name, $filterPattern, $isArray = false) {
        $var = self::fetchVar($name, INPUT_GET, false, $isArray, FILTER_VALIDATE_REGEXP, ['flags' => 0, 'options' => ['regexp' => $filterPattern]]);
        return $var;
    }
    
    public static function postValidate($name, $filterPattern, $isArray = false) {
        $var = self::fetchVar($name, INPUT_POST, false, $isArray, FILTER_VALIDATE_REGEXP, ['flags' => 0, 'options' => ['regexp' => $filterPattern]]);
        return $var;
    }

    /**
     * Get the public ip address of the user.
     *
     * @return  string
     */
    public static function ip()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Return's whether this is an AJAX request or not
     *
     * @return  bool
     */
    public static function isAjaxRequest()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function Uri()
    {
//        if (!empty($_SERVER['PATH_INFO']))
//        {
//            $uri = $_SERVER['PATH_INFO'];
//        }
        if (isset($_SERVER['REQUEST_URI']))
        {
            $uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            //$uri = strpos($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']) !== 0 ? $_SERVER['REQUEST_URI'] : '';
        }
        else
            throw new \Exception('Unable to detect URI');

        return $uri;
    }

    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}
