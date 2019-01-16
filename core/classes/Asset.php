<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Asset
{
    protected static $paths = [];

    public static function init()
    {
        self::$paths['css'] = \Config::get('assets.paths.css', []);
        self::$paths['js'] = \Config::get('assets.paths.js', []);
        self::$paths['img'] = \Config::get('assets.paths.img', []);
    }

    public static function img($name, $attr = [])
    {
        if(\Str::startsWith($name, ['http', 'https']))
        {
            $html = '<img src="' . $name . '" ';

            foreach($attr as $name => $value)
                $html .= $name . '="' . $value . '" ';
            $html .= '>';

            return $html;
        }

        foreach (self::$paths['img'] as $dir)
        {
            if(file_exists(ROOT . 'public' . DS . $dir . DS . $name))
            {
                $file = \Uri::create($dir . DS . $name);
                $html = '<img src="' . $file . '" ';

                foreach($attr as $name => $value)
                    $html .= $name . '="' . $value . '" ';
                $html .= '>';

                return $html;
            }
        }
    }

    public static function js($name)
    {
        if(\Str::startsWith($name, ['http', 'https']))
            return '<script src="' . $name . '" type="text/javascript"></script>';

        foreach (self::$paths['js'] as $dir)
        {
            if(file_exists(ROOT . 'public' . DS . $dir . DS . $name))
            {

                $file = $dir . DS . $name;
                $html = '<script src="' . Uri::create($file) . '" type="text/javascript"></script>';
                return $html;
            }
        }
    }

    public static function css($name)
    {
        if(\Str::startsWith($name, ['http', 'https']))
            return '<link href="' . $name . '" rel="stylesheet" type="text/css"/>';

        foreach (self::$paths['css'] as $dir)
        {
            if(file_exists(ROOT . 'public' . DS . $dir . DS . $name))
            {
                $file = $dir . DS . $name;
                $html = '<link href="' . Uri::create($file) . '" rel="stylesheet" type="text/css"/>';

                return $html;
            }
        }
    }
}