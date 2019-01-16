<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Validator
{
    protected static $instances = [];

    protected $rules = [];
    protected $errors = [];
    protected $validated = [];
    protected $customRules = [];

    public static function forge($name, $rules = [])
    {
        if (!isset(static::$instances[$name]))
            static::$instances[$name] = new Validator($rules);

        return static::$instances[$name];
    }

    public function __construct($rules = [])
    {
        $this->customRules = \Config::get('validation.custom_rules', []);
        $this->rules = $rules;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function validated()
    {
        return $this->validated;
    }

    /**
     * Adds validation rules
     *
     * Example: $val->addRules([
     *                  'username' => 'required',
     *                  'email'    => 'required|valid_email'
     * ]);
     *
     * rule             params          description
     * --------------------------------------------------------------------------------------
     * required         none            field is required
     * match_value      [compare]       field must match compare
     * match_pattern    [pattern]       field must match regex pattern
     * match_collection [val1, val2...] field must match one of the parameter values
     * min_length       [length]        string or array must have min length of length
     * max_length       [length]        string or array must have max length of length
     * exact_length     [length]        string or array must have exact length of length
     * valid_email      none            field must be a valid email
     * valid_url        none            field must be a valid url
     * valid_ip         none            field must be a valid ip address
     * numeric_min      [minVal]        numeric field must be >= minVal
     * numeric_max      [maxVal]        numeric field must be <= minVal
     * numeric_between  [min, max]      numeric field must be between min and max
     * @param $rules array
     */
    public function addRules($rules)
    {
        $this->rules = array_replace_recursive($this->rules, $rules);
    }

    public function validate($data)
    {
        foreach($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            if(array_key_exists($field, $data)) {
                foreach ($rules as $rule) {
                    $this->validateSingle($field, $data[$field], $rule, $rule);
                }
            }
            elseif (in_array('required', $rules)) {
                if (!isset($this->errors[$field]))
                    $this->errors[$field] = [];

                $this->errors[$field][] = 'required';
            }
        }
    }

    /**
     * Validates a single item
     *
     * @param $field string name of the field to validate
     * @param $val mixed value to validate
     * @param $method mixed validation function to call or a regular expression string
     */
    public function validateSingle($field, $val, $method, $rule)
    {
        $hasParams = preg_match('/.*\s\[(.*)\]/', $rule, $params);
        if ($hasParams) {
            $method = str_replace(' [' . $params[1] . ']', '', $method);
        }

        $method = 'validate' . \Str::convertToStudlyCaps($method);

        $success = false;
        if (\Str::contains($method, '.')) {
            $segments = explode('.', $method);
            if (count($segments) > 2 && method_exists($segments[0], $segments[1])) {
                $segments[0]->$segments[1]($val);
                if (!isset($this->errors[$field]))
                    $this->errors[$field] = [];
                $this->errors[$field][] = $rule;
                return;
            }
        }

        if (method_exists($this, $method)) {
            if ($hasParams)
                $success = $this->$method($val, $params[1]);
            else
                $success = $this->$method($val);
        } elseif (function_exists($method))
            if ($hasParams)
                $success = $method($val, $params[1]);
            else
                $success = $method($val);

        if ($success === false) {
            if (!isset($this->errors[$field]))
                $this->errors[$field] = [];

            $this->errors[$field][] = $rule;
        } else
               $this->validated[$field]= $val;
    }

    protected function validateRequired($val)
    {
        return !$this->_empty($val);
    }

    protected function validateMatchValue($val, $comp)
    {
        return $val === $comp;
    }

    protected function validateMatchPattern($val, $pattern)
    {
        return preg_match($pattern, $val);
    }

    protected function validateMatchCollection($val, $collection)
    {
        $collection = explode(',', \Str::removeSpaces($collection));
        return in_array($val, $collection);
    }

    protected function validateMinLength($val, $length)
    {
        if (is_array($val))
            return count($val) >= $length;
        elseif (is_string($val))
            return strlen($val) >= $length;
    }

    protected function validateMaxLength($val, $length)
    {
        if (is_array($val))
            return count($val) <= $length;
        elseif (is_string($val))
            return strlen($val) <= $length;
    }

    protected function validateExactLength($val, $length)
    {
        if (is_array($val))
            return count($val) === $length;
        elseif (is_string($val))
            return strlen($val) === $length;
    }

    protected function validateValidEmail($val)
    {
        return filter_var($val, FILTER_VALIDATE_EMAIL);
    }

    protected function validateValidUrl($val)
    {
        return filter_var($val, FILTER_VALIDATE_URL);
    }

    protected function validateValidIp($val)
    {
        return filter_var($val, FILTER_VALIDATE_IP);
    }

    protected function validateNumeric($val)
    {
        return is_numeric($val);
    }

    protected function validateNumericMin($val, $minVal)
    {
        if (is_numeric($val))
            return $val >= $minVal;
        return false;
    }

    protected function validateNumericMax($val, $maxVal)
    {
        if (is_numeric($val))
            return $val <= $maxVal;
        return false;
    }

    protected function validateNumericBetween($val, $range)
    {
        if (!is_numeric($val))
            return false;

        $range = \Str::removeSpaces($range);
        $range = explode(',', $range);

        return $val >= $range[0] && $val <= $range[1];
    }

    protected function _empty($val)
    {
        $val = trim($val);
        return ($val === false or $val === null or $val === '' or $val === array());
    }
}