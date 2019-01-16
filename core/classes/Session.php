<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class ExpiredSessionException extends \Exception
{
}

class InvalidSessionException extends \Exception
{
}

class Session
{
    protected static $defaults = array(
        'cookie_domain' => null,
        'cookie_path' => '/',
        'cookie_http_only' => true,
        'cookie_expiration_time' => 7200,
        'lifetime' => 900,
        'validate_ip' => true
    );

    protected static $config;

    /**
     * Writes a variable to the current session data
     * @param string $key String identifier
     * @param mixed $value Value to be set
     * @return mixed
     * @throws ExpiredSessionException
     */
    public static function write($key, $value)
    {
        if (!is_string($key))
            throw new \InvalidArgumentException('Session key must be string value');

        static::init();
        $_SESSION[$key] = $value;
        return $value;
    }

    /**
     * Reads a specific value from the current session data.
     *
     * @param string $key String identifier.
     * @param boolean $child Optional child identifier for accessing array elements.
     * @return mixed Returns a string value upon success.  Returns false upon failure.
     * @throws \InvalidArgumentException Session key is not a string value.
     * @throws ExpiredSessionException Session is expired
     */
    public static function read($key, $child = false)
    {
        if (!is_string($key))
            throw new \InvalidArgumentException('Session key must be string value');

        static::init();

        if (isset($_SESSION[$key])) {
            if ($child === false)
                return $_SESSION[$key];
            else
                if (isset($_SESSION[$key][$child]))
                    return $_SESSION[$key][$child];
        }
        return false;
    }

    /**
     * Deletes a value from the current session data.
     *
     * @param string $key String identifying the array key to delete.
     * @return void
     * @throws \InvalidArgumentException Session key is not a string value.
     * * @throws ExpiredSessionException Session is expired
     */
    public static function delete($key)
    {
        if (!is_string($key))
            throw new \InvalidArgumentException('Session key must be string value');

        static::init();
        unset($_SESSION[$key]);
    }

    /**
     * Closes the current session and releases session file lock.
     *
     * @return boolean Returns true upon success and false upon failure.
     */
    public static function close()
    {
        if (session_id() !== '')
            return session_write_close();
        return true;
    }

    /**
     * Removes session data and destroys the current session.
     *
     * @return void
     */
    public static function destroy()
    {
        if (session_id() === '') {
            session_start();
        }

        $_SESSION = array();
        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Expires a session if it has been inactive for a specified amount of time.
     *
     * @return bool
     */
    protected static function expired()
    {
        $last = isset($_SESSION['LAST_ACTIVE']) ? $_SESSION['LAST_ACTIVE'] : false;

        if (false !== $last && (time() - $last > static::$config['lifetime'])) {
            $_SESSION['LAST_ACTIVE'] = time();
            return true;
        }


        return false;
    }

    /**
     * Sets security options for session
     *  -- Stores the ip in a session variable
     */
    protected static function secure()
    {
        if (static::$config['validate_ip'] === true && !isset($_SESSION['user_ip'])) {
            $_SESSION['user_ip'] = Input::ip();
        }
    }

    /**
     * Validates security options set by Session::secure()
     */
    protected static function validate()
    {
        $validated = false;
        if (static::$config['validate_ip'] === true) {
            $validated = $_SESSION['user_ip'] === Input::ip();
        }

        if(!$validated)
            throw new InvalidSessionException();

        return true;
    }

    protected static function init()
    {
        $config = Config::get('session', []);
        $config = array_merge(static::$defaults, $config);

        static::$config = $config;

        if (session_id() === '') {
            session_set_cookie_params(
                $config['cookie_expiration_time'], $config['cookie_path'],
                $config['cookie_domain'], false, $config['cookie_http_only']
            );

            if (session_start()) {
                static::secure();
            }
        }

        if(static::expired())
            session_regenerate_id();

        return static::validate();
    }
}