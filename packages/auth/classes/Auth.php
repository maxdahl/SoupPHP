<?php

namespace Auth;

class Auth
{
    protected static $userModel;
    protected static $initialized = false;

    public static function init()
    {
        if(static::$initialized === false) {
            static::$userModel = \Config::get('auth.user_model', '\\Auth\\User');
            static::$initialized = true;
        }
    }

    public static function login($username, $password)
    {
        static::init();
        if (false !== ($user = static::loggedIn()))
            return $user;

        $user = static::$userModel::findOneBy('username', $username);
        if(!$user)
            return -1;

        $success = password_verify($password, $user->password);
        if ($success) {
            $loginHash = md5($user->id . time());
            $user->loginHash = $loginHash;
            $user->save();

            \Session::write('login_hash', $loginHash);
            \Session::write('user_id', $user->id);

            return true;
        }


        return -2;
    }

    public static function logOut()
    {
        \Session::delete('user_id');
        \Session::delete('login_hash');
    }

    public static function loggedIn()
    {
        static::init();
        try {
            if ((false !== $userId = \Session::read('user_id')) && (false !== $loginHash = \Session::read('login_hash'))) {
                $user = static::$userModel::findOneBy('id', $userId);

                if ($user && $user->loginHash === $loginHash)
                    return $user;
            }
        }
        catch (\Exception $e)
        {
            return false;
        }

        return false;
    }

    public static function addUser($username, $password)
    {
        static::init();
        $user = new static::$userModel();
        $user->username = $username;
        $user->password = password_hash($password, PASSWORD_BCRYPT);

        return $user;
    }
}