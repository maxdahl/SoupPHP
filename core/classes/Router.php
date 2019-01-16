<?php

namespace Soup\Core;

/**
 * Class Router
 * @package Soup\Core
 * Matches a root to the defined controller and action
 * TODO: implement NotFoundException (404 error)
 */
class Router
{
    /**
     * The Routing table
     * @var array
     */
    protected $routes = [];

    /**
     * Parameters from the matched route
     * @var array
     */
    protected $params = [];
    protected $request = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function dispatch()
    {
        $this->loadRoutes();
        $url = $this->request->getUri()->getProcessedUri();

        if(defined('MODPATH')) {
            $url = explode('/', $url);
            unset($url[0]);
            $url = implode('/', $url);
        }

        if ($this->match($url)) {
            $controller = $this->convertToStudlyCaps($this->params['controller']);
            $controller = $this->getNamespace() . $controller;
            unset($this->params['controller']);
            unset($this->params['directory']);

            $action = Config::get('core.action_prefix', '');
            $action .= '-' . $this->params['action'] . '-';
            $action .= Config::get('core.action_suffix', '');
            $action = $this->convertToCamelCase($action);
            unset($this->params['action']);

            $params = [];
            foreach ($this->params as $name => $value) {
                if ($name != 'params') {
                    $params[$name] = $value;
                    unset($this->params[$name]);
                }
            }

            if (strlen($this->params['params']) > 0)
                $params = array_merge($params, explode('/', trim($this->params['params'], ' /')));

            unset($this->params['params']);

            $this->request->setClass($controller);
            $this->request->setAction($action);
            $this->request->setParams($params);
        } else {
            //TODO: ERROR HANDLING ROUTE NOT FOUND
            echo "404";
        }
    }

    public function loadRoutes()
    {
        $routes = Config::get('routes.uri', []);
        foreach ($routes as $route => $params)
            $this->addRoute($route, $params);
    }

    /**
     * Match a query string to the route in the routing table
     * set $params property if a route exists
     *
     * @param string $url the query string
     * @return bool         true if a match is found, false otherwise
     */
    public function match($url)
    {
        foreach ($this->routes as $route => $params) {
            if (preg_match($route, $url, $matches)) {
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }

                $this->params = $params;
                return true;
            }
        }
    }

    /**
     * Get the currently matched parameters
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Add a route to the routing table
     *
     * @param string $route The route query string
     * @param array $params [controller, action, ...]
     *
     * @return void
     */
    public function addRoute($route, $params = [])
    {
        // Convert the route to a regular expression: escape forward slashes
        $route = preg_replace('/\//', '\\/', $route);

        // Convert variables e.g. {controller}
        $route = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z-]+)', $route);

        //Convert custom variables e.g. {id: \d+}
        $route = preg_replace('/\{([a-z]+):\s*([^\}]+)\}/', '(?P<\1>\2)', $route);


        //Everything that follows are parameters
        $route .= '(?P<params>.*)';

        // Add start and end delimiters, and case insensitive flag
        $route = '/^' . $route . '$/i';

        $this->routes[$route] = $params;
    }

    /**
     * Get all routes from the routing table
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Convert the string with hyphens to StudlyCaps,
     * e.g. post-authors => PostAuthors
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    protected function convertToStudlyCaps($string)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }

    /**
     * Convert the string with hyphens to camelCase,
     * e.g. add-new => addNew
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    protected function convertToCamelCase($string)
    {
        return lcfirst($this->convertToStudlyCaps($string));
    }

    /**
     * Returns the controller namespace and decides if ajax requests are handled by seperate controllers
     */
    protected function getNamespace()
    {
        if(defined('MODPATH'))
            $namespace = '\\' . LOADEDMOD . '\\Controller\\';
        else
            $namespace = '\\App\\Controller\\';

        if(isset($this->params['directory']))
            $namespace .= ucfirst($this->params['directory']) . '\\';

        if(\Input::isAjaxRequest())
            $namespace .= \Config::get('core.ajax_namespace', '');
        return trim($namespace);
    }
}