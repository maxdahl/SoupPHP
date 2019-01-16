<?php

namespace Soup\DB;
defined('ROOT') or die('No direct script access');

abstract class Result implements \Countable, \Iterator
{
    protected $query;
    protected $result;
    protected $totalRows;
    protected $currentRow;
    protected $asObject;
    protected $sanitizationEnabled = false;

    public function __construct($result, $sql, $asObject = null)
    {
        $this->result = $result;
        $this->query = $sql;

        if(is_object($asObject))
            $asObject = get_class($asObject);

        $this->asObject = $asObject;
    }

    abstract public function __destruct();

    /**
     * Return all of the rows in the result as an array.
     *
     *     // Indexed array of all rows
     *     $rows = $result->asArray();
     *
     *     // Associative array of rows by "id"
     *     $rows = $result->asArray('id');
     *
     *     // Associative array of rows, "id" => "name"
     *     $rows = $result->asArray('id', 'name');
     *
     * @param   string $key   column for associative keys
     * @param   string $value column for values
     * @return  array
     */
    public function asArray($key = null, $value = null)
    {
        $results = array();

        if ($key === null and $value === null)
        {
            foreach ($this as $row)
                $results[] = $row;
        }
        elseif ($key === null)
        {
            if ($this->asObject)
            {
                foreach ($this as $row)
                    $results[] = $row->$value;
            }
            else
            {
                foreach ($this as $row)
                    $results[] = $row[$value];
            }
        }
        elseif ($value === null)
        {
            if ($this->asObject)
            {
                foreach ($this as $row)
                    $results[$row->$key] = $row;
            }
            else
            {
                foreach ($this as $row)
                    $results[$row[$key]] = $row;
            }
        }
        else
        {
            // Associative columns

            if ($this->asObject)
            {
                foreach ($this as $row)
                    $results[$row->$key] = $row->$value;
            }
            else
            {
                foreach ($this as $row)
                    $results[$row[$key]] = $row[$value];
            }
        }

        $this->rewind();

        return $results;
    }

    /**
     * Return the named column from the current row.
     *
     * @param   string $name    column to get
     * @param   mixed  $default default value if the column does not exist
     *
     * @return  mixed
     */
    public function get($name, $default = null)
    {
        $row = $this->current();
        if($this->asObject)
        {
            if(isset($row->$name))
            {
                if(!$this->sanitizationEnabled)
                    $result = $row->$name;
                else
                {
                    $filter = \Config::get('security.output_filter', 'htmlentities');
                    $result = $filter($row->name);
                }

                return $result;
            }

        }
        else
        {
            if(isset($row[$name]))
            {
                if(!$this->sanitizationEnabled)
                    $result = $row->$name;
                else
                {
                    $filter = \Config::get('security.output_filter', 'htmlentities');
                    $result = $filter($row[$name]);
                }

                return $result;
            }
        }

        return $default;
    }

    public function sanitize()
    {
        $this->sanitizationEnabled = true;
        return $this;
    }

    public function unsanitize()
    {
        $this->sanitizationEnabled = false;
        return $this;
    }

    public function sanitized()
    {
        return $this->sanitizationEnabled;
    }

    public function count()
    {
        return $this->totalRows;
    }

    abstract function current();

    /**
     * Implements [Iterator::key], returns the current row number.
     *
     * @return  integer
     */
    public function key()
    {
        return $this->currentRow;
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     */
    public function next()
    {
        ++$this->currentRow;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     */
    public function rewind()
    {
        // first row is zero, not one!
        $this->currentRow = 0;
    }

    /**
     * Implements [Iterator::valid], checks if the current row exists.
     *
     * @return  boolean
     */
    public function valid()
    {
        return $this->currentRow < $this->totalRows;
    }
}