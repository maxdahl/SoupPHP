<?php

namespace Soup\Helper;

class Str
{
    /**
     * Check if $haystack starts with $needle
     *
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        if (!is_array($needle))
            $needle = array($needle);

        foreach ($needle as $n) {
            if (strpos($haystack, $n) === 0)
                return true;
        }

        return false;
    }

    /**
     * Check if $haystack ends with $needle
     *
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        if (!is_array($needle))
            $needle = array($needle);

        foreach ($needle as $n) {
            if (substr($haystack, -strlen($n)) === $n)
                return true;
        }

        return false;
    }

    public static function contains($haystack, $needle)
    {
        if (!is_array($needle))
            $needle = [$needle];

        foreach ($needle as $n) {
            if (strpos($haystack, $n) !== false)
                return true;
        }

        return false;
    }

    /**
     * Parse the params from a string using strtr()
     *
     * @param   string $string string to parse
     * @param   array $array params to str_replace
     *
     * @return  string
     */
    public static function tr($string, $array = array())
    {
        if (is_string($string)) {
            $tr_arr = array();

            foreach ($array as $from => $to) {
                substr($from, 0, 1) !== ':' and $from = ':' . $from;
                $tr_arr[$from] = $to;
            }
            unset($array);

            return strtr($string, $tr_arr);
        } else {
            return $string;
        }
    }

    public static function replaceFirst($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    public static function replaceAll($search, $replace, $hayStack)
    {
        if(!is_array($search))
            $search = [$search];

        foreach ($search as $s)
            $hayStack = str_replace($s, $replace, $hayStack);

        return $hayStack;
    }

    public static function removeSpaces($haystack)
    {
        return str_replace(' ', '', $haystack);
    }

    /**
     * Convert the string with hyphens to StudlyCaps,
     * e.g. post-authors => PostAuthors
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    public static function convertToStudlyCaps($string)
    {
        return str_replace(' ', '', ucwords(static::replaceAll(['-', '_'], ' ', $string)));
    }

    /**
     * Convert the string with hyphens to camelCase,
     * e.g. add-new => addNew
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    public static function convertToCamelCase($string)
    {
        return lcfirst(static::convertToStudlyCaps($string));
    }
}