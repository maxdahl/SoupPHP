<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Response {
    /**
     * Status array taken from FuelPHP
     */
    public static $statuses = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a Teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        428 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    );

    protected $status = 0;
    protected $headers = array();
    protected $body = '';
    protected $encode = 'gzip';

    public function __construct($body = '', $status = 200, $headers = array())
    {
        if(ENVIRONMENT !== 'PRODUCTION')
            $this->encode = '';

        $this->setBody($body);
        $this->setStatus($status);
        $this->setHeaders($headers);
    }

    /**
     * Send the response headers and body
     */
    public function send()
    {
        $this->sendHeaders();
        echo $this->body;
    }

    public function setEncoding($encoding)
    {
        $this->encode = $encoding;
        $this->setHeader('Content-Encoding', $encoding);
    }

    /**
     * Send the response headers if they aren't send already
     * @return bool
     */
    public function sendHeaders()
    {
        if (!headers_sent())
        {
            switch($this->encode)
            {
                case 'gzip':
                    $this->setHeader('Content-Encoding', 'gzip');
                    break;
            }

            //TODO: create an input class and take $_SERVER['SERVER_PROTOCOL'] if it exists
            $protocol = 'HTTP/1.1';
            header($protocol . ' ' . $this->status . ' ' . static::$statuses[$this->status]);

            foreach ($this->headers as $name => $value)
            {
                // Parse non-replace headers
                if (is_int($name) and is_array($value))
                {
                    isset($value[0]) and $name = $value[0];
                    isset($value[1]) and $value = $value[1];
                }

                // Create the header
                is_string($name) and $value = "{$name}: {$value}";

                // Send it
                header($value, true);
            }
            return true;
        }
        return false;
    }

    /**
     * Set the body
     * @param $body
     */
    public function setBody($body)
    {
        switch($this->encode)
        {
            case 'gzip':
                $this->body = gzencode($body);
                break;
            case 'none':
            default:
                $this->body = $body;
                break;
        }

    }

    /**
     * Set the status
     * @param $status
     */
    public function setStatus($status)
    {
        if(array_key_exists($status, self::$statuses))
        {
            $this->status = $status;
        }
    }

    /**
     * Set a specific header
     * @param      $key
     * @param      $value
     * @param bool $override
     */
    public function setHeader($key, $value, $override = true)
    {
        if($override == true || !isset($this->headers[$key]))
            $this->headers[$key] = $value;
    }

    /**
     * Set a bunch of headers
     * @param      $headers
     * @param bool $override
     */
    public function setHeaders($headers, $override = true)
    {
        foreach($headers as $k => $v)
            $this->setHeader($k, $v, $override);
    }

    /**
     * Remove a specific header
     * If you specify an array of keys all of them will be removed
     * @param mixed $key
     */
    public function removeHeader($key)
    {
        if(!is_array($key))
            $key = array($key);

        foreach($key as $k)
        {
            if(isset($this->headers[$k]))
                unset($this->headers[$k]);
        }
    }

    /**
     * Remove all headers
     */
    public function clearHeaders()
    {
        $this->removeHeader(array_keys($this->headers));
    }

    /**
     * Get a specific header
     * Returns false if the header doesn't exist
     * @param $key
     *
     * @return mixed
     */
    public function getHeader($key)
    {
        return (isset($this->headers[$key])) ? $this->headers[$key] : false;
    }

    /**
     * Get all headers
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get the body
     * @return string
     */
    public function getBody()
    {
        switch($this->encode)
        {
            case 'gzip':
                return gzdecode($this->body);
                break;
            case 'none':
            default:
                return $this->body;
                break;
        }
    }

    /**
     * Get the status
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
}