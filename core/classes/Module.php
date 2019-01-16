<?php

namespace Soup\Core;

defined('ROOT') or die('No direct script access');

class ModuleNotFoundException extends \Exception
{
}

class Module
{
    protected static $loadedModules = [];

    /**
     * Loads a module and adds all namespaces (routes.php config), core_namespaces (core.php config)
     * and class_paths (core.php config) of the module to the autoloader
     *
     * If no path is specified it looks in APP/modules
     *
     * @param $name string name of the module
     * @param $path string path to the module. APP/modules/$name if null
     *
     * @throws ModuleNotFoundException if the module is not found
     */
    public static function load($name, $path = null)
    {
        $name = trim($name, ' /\\');

        if (static::loaded($name))
            return;

        if ($path === null)
            $path = APP . 'modules' . DS . $name . DS;

        if (!is_dir($path))
            throw new ModuleNotFoundException('Module ' . $name . ' not found in ' . $path);

        $namespace = ucfirst($name);

        \Autoloader::addNamespace($namespace . '\\Controller', $path . 'classes' . DS . 'controllers');
        \Autoloader::addNamespace($namespace . '\\Model', $path . 'classes' . DS . 'models');

        foreach (\Config::get($name . '::routes.namespace', []) as $namespace => $path)
            \Autoloader::addNamespace($namespace, $path);

        foreach(\Config::get($name . '::core.core_namespaces', []) as $ns)
            \Autoloader::addCoreNamespace($ns);

        foreach (Config::get($name . '::core.class_paths', []) as $class => $path)
            \Autoloader::addClass($class, $path);

        static::$loadedModules[$name] = $path;
    }

    /**
     * Unloads a module if it is loaded
     *
     * @param $name string name of the module
     */
    public static function unload($name)
    {
        //TODO: remove from autoloader
        if (static::loaded($name))
            unset(static::$loadedModules[$name]);
    }

    /**
     * Checks if a module is loaded
     *
     * @param $name string name of the module
     * @return bool
     */
    public static function loaded($name)
    {
        return array_key_exists($name, static::$loadedModules);
    }

    /**
     * Checks if a module exists and returns the path to the module if it exists
     *
     * @param $name string name of the module
     * @return mixed
     */
    public static function exists($name)
    {
        $name = trim($name, ' /\\');
        if ($name != '' && is_dir(APP . 'modules' . DS . $name))
            return APP . 'modules' . DS . $name;
        return false;
    }
}