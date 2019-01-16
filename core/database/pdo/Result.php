<?php

namespace Soup\DB\PDO;
defined('ROOT') or die('No direct script access');

class Result extends \Soup\DB\Result
{
    public function __construct($result, $sql, $asObject)
    {
        parent::__construct($result, $sql, $asObject);

        $this->totalRows = $this->result->rowCount();
    }

    public function __destruct()
    {

    }

    public function current()
    {
        if($this->asObject === false)
            $result = $this->result->fetch(\PDO::FETCH_ASSOC);
        elseif(is_string($this->asObject))
            $result = $this->result->fetchObject($this->asObject);
        else
            $result = $this->result->fetchObject();

        if ($this->sanitizationEnabled)
        {
            $filter = \Config::get('security.output_filter', 'htmlentities');
            $result = $filter($result);
        }

        return $result;
    }
}
