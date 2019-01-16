<?php

namespace Auth;

class User extends \Model
{
    protected static $columnNames = [['login_hash' => 'loginHash'], 'username', 'password'];
    protected static $defaultValues = ['loginHash' => ''];

    protected static function preQuery()
    {
        static::$connection = \Config::get('auth.db', 'default');
    }
}