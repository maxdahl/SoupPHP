<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class View
{
    protected static $globalData = [];

    protected $data = [];
    protected $file = '';

    public function __construct($file, $data = [], $filter = true)
    {
        $this->setFile($file);
        $this->setData($data, $filter);
    }

    public function __toString()
    {
        return $this->render();
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function get($key)
    {
        if (isset($this->data[$key]))
            return $this->data[$key];

        return false;
    }

    public static function getGlobal($key)
    {
        if (isset(self::$globalData[$key]))
            return self::$globalData[$key];

        return false;
    }

    public function set($key, $value, $filter = true)
    {
        //var_dump($value) . "<br>";
        if ($filter === true)
            $value = View::filter($value);

        $this->data[$key] = $value;
    }

    public function setData($data, $filter = true)
    {
        if (!is_array($data))
            $data = [$data];

        foreach ($data as $k => $v)
            $this->set($k, $v, $filter);
    }

    public static function setGlobal($key, $value, $filter = true)
    {
        if ($filter === true)
            $value = self::filter($value);

        self::$globalData[$key] = $value;
    }

    public static function setGlobalData($data, $filter = true)
    {
        if (!is_array($data))
            $data = [$data];

        foreach ($data as $k => $v)
            self::setGlobal($k, $v);
    }

    public function clearData()
    {
        $this->data = [];
    }

    public static function clearGlobalData()
    {
        self::$globalData = [];
    }

    public function setFile($file)
    {
        $f = defined('MODPATH') ? MODPATH : APP;
        $f .= 'views' . DS . $file;

        //we use the .twig extension of templates because of syntax highlighting
        if (is_file($f . '.twig'))
            $this->file = $f . '.twig';
        elseif (is_file($f . '.php'))
            $this->file = $f . '.php';

        else throw new \Exception('View ' . $file . ' not found'); //TODO: implement special exception
    }

    public function render()
    {
        return $this->parseView();
    }

    protected function parseView()
    {
        $data = array_merge($this::$globalData, $this->data);
        extract($data);

        $file = $this->file;

        ob_start();
        try {
            if(strpos($file, '.twig') !== false) {
                $template = new Template($this->file);
                $file = $template->compile();
            }

            include($file);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $content = ob_get_clean();
        return $content;
    }

    public static function filter($value)
    {
        $filter = Config::get('security.view_data_filter', 'htmlentities');
        if (function_exists($filter)) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if(!is_object($v))
                        $value[$k] = $filter($v);
                }
            } elseif (!is_object($value) || (!($value instanceof \View) && method_exists($value, '__toString')))
                $value = $filter($value);
        }

        return $value;
    }
}