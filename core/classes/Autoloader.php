<?php

namespace Soup\Core;
defined('ROOT') OR die('No direct script access');

/**
 * Class Autoloader
 * @package Soup\Core
 * Loads php files when they are needed
 */
class Autoloader
{
    protected static $routes;
    protected static $loaded = [];
    protected static $classes = [];
    protected static $coreNamespaces = [];
    protected static $userPath = APP;

    public static function addNamespace($namespace, $path)
    {
        static::$routes[$namespace] = $path;
    }

    public static function addCoreNamespace($namespace)
    {
        static::$coreNamespaces[] = $namespace;
    }

    public static function addClass($class, $path)
    {
        $path = rtrim($path, '\t\n\r\0\x0B \\/');
        $path = strpos($path, '.php') === false ? $path . DS : $path;

        static::$classes[$class] = $path;
    }

    public static function setUserPath($path)
    {
        static::$userPath = $path;
    }

    public static function findCoreNamespace($class)
    {
        if (array_key_exists($class, static::$classes))
            return $class;

        foreach (static::$coreNamespaces as $ns) {
            if (array_key_exists($nsClass = $ns . '\\' . $class, static::$classes))
                return $nsClass;
        }

        return false;
    }

    /**
     * Register the autoloader
     * @param mixed $loader The autoloader function to register
     */
    public static function register($loader = NULL)
    {
        if ($loader === NULL)
            spl_autoload_register(array('Soup\Core\Autoloader', 'load'));
        else
            spl_autoload_register($loader);

        foreach (\Config::get('routes.namespace', []) as $ns => $path)
            static::addNamespace($ns, $path);

        foreach (\Config::get('core.core_namespaces', []) as $ns)
            static::addCoreNamespace($ns);

        foreach (\Config::get('core.class_paths', []) as $class => $path)
            static::addClass($class, $path);
    }

    /**
     * Load a class
     * Namespaces can be routed to specific directory in config/ns-route.php
     * @param $className string the name of the class
     */
    public static function load($className)
    {
        $alias = false;
        $path = false;

        if ($fullClass = static::findCoreNamespace($className))
            $alias = true;

        if (array_key_exists($className, static::$loaded))
            return;

        if ($fullClass !== false && array_key_exists($fullClass, static::$classes)) {
            $classPath = static::$classes[$fullClass];

            if (strpos($classPath, '.php') !== false) {
                require($classPath);

                if ($alias)
                    class_alias($fullClass, $className, false);
                static::$loaded[$className] = true;
                return;
            }
        }

        if ($alias) {
            if (!class_exists($fullClass, false)) {
                if ($path = static::preparePath($fullClass) !== false)
                    require(static::preparePath($fullClass));
            }
            if (!class_exists($className, false)) {
                class_alias($fullClass, $className);
                $className = $fullClass;
            }
        } else {
            if ($path = static::preparePath($className) !== false)
                require(static::preparePath($className));
        }

        if ($path !== false)
            static::$loaded[$className] = $path;
    }

    public static function preparePath($class)
    {
        $filename = self::getNamespaceRoute($class);
        $userPath = static::$userPath;

        if (file_exists($userPath . $filename))
            $filename = $userPath . $filename;

        elseif (file_exists($userPath . 'core' . DS . $filename))
            $filename = $userPath . 'core' . DS . $filename;

        elseif (file_exists(ROOT . $filename))
            $filename = ROOT . $filename;

        elseif (!file_exists($filename)) {
            return false;
        }

        return $filename;

    }

    /**
     * Resolve the directory the namespace points to
     * @param $className
     *
     * @return mixed|string
     */
    public static function getNamespaceRoute($className)
    {
        $routes = static::$routes;
        if (strpos($className, '\\') !== 0) {
            $class = substr($className, strrpos($className, '\\') + 1);
            $namespace = trim(substr($className, 0, strrpos($className, '\\')), '\\');

            if (isset($routes[$namespace]))
                return $routes[$namespace] . DS . $class . '.php';

            else {
                $nsParts = explode('\\', $namespace);
                $setNS = '';
                $path = '';
                $lastIndex = -1;

                foreach ($nsParts as $index => $part) {
                    $setNS .= $part;
                    if (isset($routes[$setNS])) {
                        $path = $routes[$setNS];
                        $lastIndex = $index;
                    }

                    $setNS .= '\\';
                }

                if ($path !== '') {
                    $remains = array_slice($nsParts, $lastIndex + 1);
                    $path .= '\\' . implode('\\', $remains);
                    $namespace = $path;
                }

            }

            $namespace = str_replace('\\', DS, $namespace);
            $namespace = strtolower($namespace);

            return $namespace . DS . $class . '.php';
        }

        return $className;
    }
}