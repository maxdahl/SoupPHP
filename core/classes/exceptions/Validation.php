<?php

namespace Soup\Exception;

class ValidationException extends \Exception
{
    protected $errors;

    public function __construct($errors, $message = '', $code = 0, \Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public function getErrors()
    {
        $errorStrings = [];
        foreach ($this->errors as $field => $errors) {
            $errorStrings[$field] = [];
            foreach ($errors as $error) {
                $errorStrings[$field][] = $field . $this->errorToString($error);
            }
        }
        return $errorStrings;
    }

    public function __toString()
    {
        $str = '';
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error)
                $str .= $field . $this->errorToString($error) . '<br>';
        }

        return $str;
    }

    protected function errorToString($error)
    {
        $message = $error;
        $hasParams = preg_match('/.*\s\[(.*)\]/', $error, $params);
        if ($hasParams) {
            $error = str_replace(' [' . $params[1] . ']', '', $error);
        }

        switch ($error) {
            case 'required':
                $message = ' is required';
                break;
            case 'match_value':
                $message = ' has to be ' . $params[1];
                break;
            case 'match_pattern':
                $message = ' has to match regex pattern (' . $params[1] . ')';
                break;
            case 'match_collection':
                $message = ' has to be a value of ' . implode(', ', $params);
                break;
            case 'min_length':
                $message = ' has to be at least ' . $params[1] . ' characters';
                break;
            case 'max_length':
                $message = ' can\'t be longer than ' . $params[1] . ' characters';
                break;
            case 'exact_length':
                $message = ' must be ' . $params[1] . ' characters long';
                break;
            case 'valid_email':
                $message = ' has to be a valid email address';
                break;
            case 'valid_url':
                $message = ' has to be a valid url';
                break;
            case 'valid_ip':
                $message = ' has to be a valid ip address';
                break;
            case 'numeric':
                $message = ' has to be a number';
                break;
            case 'numeric_min':
                $message = ' can\'t be less than ' . $params[1];
                break;
            case 'numeric_max':
                $message = ' can\'t be more than ' . $params[1];
                break;
            case 'numeric_between':
                $params = explode(',', $params[1]);
                $message = ' has to be in the range of ' . $params[0] . ' and ' . $params[1];
                break;
        }

        return $message;
    }
}