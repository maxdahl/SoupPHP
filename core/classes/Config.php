<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Config
{
    protected static $loadedFiles = [];
    protected static $items = [];
    protected static $userPath = APP;
    protected static $paths = [];

    public static function addPath($path)
    {
        if (!isset(static::$paths[$path]))
            static::$paths[] = $path;
    }

    public static function removePath($path)
    {
        if (isset(static::$paths[$path]))
            unset(static::$paths[$path]);
    }

    public static function setUserPath($path)
    {
        static::$userPath = $path;
    }

    /**
     * Get a configuration item
     * @param $item string filename.item
     * @param $default mixed return value if the item is not found
     * @return mixed
     */
    public static function get($item, $default = null)
    {
        $segments = explode('.', $item);
        $fileSegments = explode('::', array_shift($segments));
        $module = 'app';

        if (count($fileSegments) > 1)
            $module = array_shift($fileSegments);

        $file = array_shift($fileSegments);

        if ((!array_key_exists($module, static::$items) || !array_key_exists($file, static::$items[$module]))
            && (!array_key_exists($module, static::$loadedFiles) || !in_array($file, static::$loadedFiles[$module]))) {
            if (!static::loadFile($file, $module))
                return $default;
        }

        $item = static::$items[$module][$file];

        foreach ($segments as $seg) {
            if (is_array($item) && array_key_exists($seg, $item))
                $item = $item[$seg];
            else
                $item = $default;
        }

        return $item;
    }

    public static function flush()
    {
        static::$loadedFiles = [];
        static::$items = [];
    }

    /**
     * Load a config file and add the items to self::$items
     * @param $fileName string config file name without .php extension
     * @param $module string module name
     * @return bool
     */
    public static function loadFile($fileName, $module)
    {
        if (in_array($fileName, self::$loadedFiles))
            return false;

        $items = [];
        $file = 'config' . DS . $fileName . '.php';
        $envFile = 'config' . DS . strtolower(ENVIRONMENT) . DS . $fileName . '.php';

        $modPath = null;

        if ($module !== 'app')
            $modPath = APP . 'modules' . DS . strtolower($module) . DS;

        if (file_exists(CORE . $envFile))
            $items = include(CORE . $envFile);

        else if (file_exists(CORE . $file))
            $items = include(CORE . $file);

        //Load Package configurations
        foreach (static::$paths as $path) {
            if (file_exists($path . $envFile)) {
                $items = array_replace_recursive($items, include($path . $envFile));
                break;
            } elseif (file_exists($path . $file)) {
                $items = array_replace_recursive($items, include($path . $file));
                break;
            }
        }

        if (file_exists(static::$userPath . $envFile))
            $items = array_replace_recursive($items, include(static::$userPath . $envFile));

        elseif (file_exists(static::$userPath . $file))
            $items = array_replace_recursive($items, include(static::$userPath . $file));

        if ($modPath !== null) {
            if (file_exists($modPath . $envFile))
                $items = array_replace_recursive($items, include($modPath . $envFile));

            elseif (file_exists($modPath . $file))
                $items = array_replace_recursive($items, include($modPath . $file));
        }

        if (!empty($items)) {
            static::$items[$module][$fileName] = $items;
            static::$loadedFiles[$module][] = $fileName;
            return true;
        }

        return false;

    }
}