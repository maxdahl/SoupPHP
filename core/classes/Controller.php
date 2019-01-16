<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Controller
{
    protected $request;
    protected $response;

    public function __construct(\Request $request, \Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}