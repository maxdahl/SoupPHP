<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Security
{
    protected static $lastToken;

    public static function init()
    {
        if(Session::read(Config::get('security.csrf_token_key', 'csrf_token')) === false)
        {
            $token = static::generateCsrfToken();
            Session::write(Config::get('security.csrf_token_key', 'csrf_token'), $token);
        }
    }

    public static function validateToken($inputToken)
    {
        $rotate = Config::get('security.rotate_csrf_token', true);
        if ($inputToken === Session::read(\Config::get('security.csrf_token_key', 'csrf_token'))) {
            if ($rotate)
                static::generateCsrfToken();
            return true;
        }

        throw new \Exception('Invalid CSRF token');
    }

    public static function generateCsrfToken()
    {
        $token = Config::get('security.csrf_token_salt', '');
        $token .= bin2hex(random_bytes(32));

        static::$lastToken = $token;

        return $token;
    }
}