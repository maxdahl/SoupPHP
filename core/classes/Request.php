<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Request
{
    protected $directory = '';
    protected $class     = '';
    protected $action    = '';
    protected $params    = [];
    protected $response  = null;
    protected $uri       = null;

    public function __construct()
    {
        $this->response = new \Response();
        $this->uri = new \Uri();
    }

    public function moduleRequested()
    {
        $modName = $this->getUri()->getProcessedUri();
        $modName = explode('/', $modName)[0];
        return Module::exists($modName);
    }

    public function execute()
    {
        $csrf = false;
        if(Input::method() === 'POST')
            $csrf = Input::post(Config::get('security.csrf_token_key', 'csrf_token'));

        if($csrf !== false)
            Security::validateToken($csrf);

        $router = new Router($this);
        $router->dispatch();

        if(class_exists($this->class))
        {
            $classObject = new $this->class($this, $this->response);
            $reflectClass = new \ReflectionClass($classObject);

            if($reflectClass->hasMethod($this->action))
            {
                $action = $reflectClass->getMethod($this->action);
                $reflectClass->hasMethod('before') and $reflectClass->getMethod('before')->invoke($classObject);
                $body = $action->invokeArgs($classObject, $this->params);
                $reflectClass->hasMethod('after') and $reflectClass->getMethod('after')->invoke($classObject);

                $this->response->setBody(($body));
                return $this->response;
            }
            else
            {
                throw new \Exception('No method ' . $this->action . ' in controller ' . $this->class);
            }
        }
        else
        {
            throw new \Exception('Controller ' . $this->class . ' not found');
        }
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function setParams($params)
    {
        if(!is_array($params))
            $params = [$params];

        $this->params = $params;
    }
}