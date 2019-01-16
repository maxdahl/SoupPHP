<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Uri
{
    protected $fullUri = '';
    protected $processedUri = '';

    public function __construct($uri = null)
    {
        if($uri === null)
            $uri = Input::uri();

        $this->processedUri = $this->parse($uri);
        $this->fullUri = $uri;
    }

    public function __toString()
    {
        return $this->fullUri;
    }

    public function getFullUri()
    {
        return $this->fullUri;
    }

    public function getProcessedUri()
    {
        return $this->processedUri;
    }

    //TODO: implement other protocols
    public static function create($dst)
    {
        $host = 'http://' . $_SERVER['HTTP_HOST'];
        $dst = str_replace('\\', '/', $dst);

        $uri = trim(Config::get('core.base_directory'), '\\/');
        $uri = $host . '/' . $uri . '/' . trim($dst);
        return $uri;
    }

    /**
     * Parse the URI and remove everything that is not a controller/method/param
     * and make sure it contains no not permitted chars
     *
     * @param string $uri
     *
     * @return string
     */
    public function parse($uri)
    {
        $uri = $this->filter($uri);

        $uri = str_replace(Config::get('core.base_directory'), '', $uri);
        $uri = str_replace('https://', '', $uri);
        $uri = str_replace('http://', '', $uri);
        $uri = substr($uri, strpos($uri, '/') + 1);
        $uri = trim($uri, ' /\\');

        $uri = explode('/', $uri);

        //remove everything that is not a controller/method or param
        foreach ($uri as $key => $val) {
            if ($val == '' || $val == 'index.php')
                unset($uri[$key]);
        }

        return implode('/', $uri);
    }

    /**
     * Check if the uri contains any not permitted chars
     * @param string $str the uri string
     *
     * @return string
     */
    private function filter($str)
    {
        if ($str != '' && \Config::get('uri.permitted_chars', '') != '' && \Config::get('uri.enable_query_strings') == FALSE)
        {
            if (!preg_match("|^[".str_replace(array('\\-', '\-'), '-', preg_quote(Config::get('uri.permitted_chars'), '-'))."]+$|i", $str))
                die('Wrong chars in uri');//helper\URL::show_error('The URI you submitted has disallowed characters.', 400);
        }

        // Convert programatic characters to entities
        $bad	= array('$',		'(',		')',		'%28',		'%29');
        $good	= array('&#36;',	'&#40;',	'&#41;',	'&#40;',	'&#41;');

        return str_replace($bad, $good, $str);
    }
}