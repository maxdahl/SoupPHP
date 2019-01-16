<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class PackageNotFoundException extends \Exception
{
}

class Package
{
    protected static $loadedPackages = [];

    public static function load($name, $path = null)
    {
        $name = trim($name, ' /\\');

        if (static::loaded($name))
            return;

        if ($path === null)
            $path = PKGPATH . $name . DS;

        if (!is_dir($path))
            throw new PackageNotFoundException('Module ' . $name . ' not found in ' . $path);

        static::$loadedPackages[$name] = $path;
        \Config::addPath($path);

        require($path . 'bootstrap.php');
    }

    public static function unload($name)
    {
        $name = trim($name, ' /\\');
        if(!static::loaded($name))
            return;

        \Config::removePath(static::$loadedPackages[$name]);
        unset(static::$loadedPackages[$name]);
    }

    public static function loaded($name)
    {
        $name = trim($name, ' /\\');
        return array_key_exists($name, static::$loadedPackages);
    }
}