<?php

namespace Soup\DB\Query\Builder;
use Soup\DB\Connection;
use Soup\DB\Query;

defined('ROOT') or die('No direct script access');

class Insert extends \Soup\DB\Query\Builder
{
    /**
     * @var string  $_table  table
     */
    protected $table;

    /**
     * @var array $_columns  columns
     */
    protected $columns = array();

    /**
     * @var array  $_values  values
     */
    protected $values = array();

    /**
     * Set the table and columns for an insert.
     *
     * @param   mixed $table   table name or array($table, $alias) or object
     * @param   array $columns column names
     */
    public function __construct($table = null, array $columns = null)
    {
        if ($table)
        {
            // Set the initial table name
            $this->table = $table;
        }

        if ($columns)
        {
            // Set the column names
            $this->columns = $columns;
        }

        // Start the query with no SQL
        parent::__construct('', \DB::INSERT);
    }

    /**
     * Sets the table to insert into.
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function table($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set the columns that will be inserted.
     *
     * @param   array $columns column names
     * @return  $this
     */
    public function columns(array $columns)
    {
        $this->columns = array_merge($this->columns, $columns);

        return $this;
    }

    /**
     * Adds values. Multiple value sets can be added.
     *
     * @throws \Exception
     * @param array $values
     * @return $this
     */
    public function values(array $values)
    {
        if ( ! is_array($this->values))
        {
            throw new \Exception('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
        }

        // Get all of the passed values
        $values = func_get_args();

        // And process them
        foreach ($values as $value)
        {
            if (is_array(reset($value)))
            {
                $this->values = array_merge($this->values, $value);
            }
            else
            {
                $this->values[] = $value;
            }
        }

        return $this;
    }

    /**
     * This is a wrapper function for calling columns() and values().
     *
     * @param array $pairs column value pairs
     *
     * @return	$this
     */
    public function set(array $pairs)
    {
        $this->columns(array_keys($pairs));
        $this->values($pairs);

        return $this;
    }

    /**
     * Use a sub-query to for the inserted values.
     *
     * @param   Database_Query  $query  Database_Query of SELECT type
     *
     * @return  $this
     *
     * @throws \FuelException
     */
    public function select(\Soup\DB\Query $query)
    {
        if ($query->type() !== \DB::SELECT)
        {
            throw new \Exception('Only SELECT queries can be combined with INSERT queries');
        }

        $this->values = $query;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param   mixed  $db  Database instance or instance name
     *
     * @return  string
     */
    public function compile($db = null)
    {
        if ( ! $db instanceof \Soup\DB\Connection)
        {
            // Get the database instance
            $db = \Soup\DB\Connection::instance();
        }

        // Start an insertion query
        $query = 'INSERT INTO '.$db->quoteTable($this->table);

        // Add the column names
        $query .= ' ('.implode(', ', array_map(array($db, 'quoteIdentifier'), $this->columns)).') ';

        if (is_array($this->values))
        {
            // Callback for quoting values
            $quote = array($db, 'quote');

            $groups = array();
            foreach ($this->values as $group)
            {
                foreach ($group as $i => $value)
                {
                    if (is_string($value) AND isset($this->parameters[$value]))
                    {
                        // Use the parameter value
                        $group[$i] = $this->parameters[$value];
                    }
                }

                $groups[] = '('.implode(', ', array_map($quote, $group)).')';
            }

            // Add the values
            $query .= 'VALUES '.implode(', ', $groups);
        }
        else
        {
            // Add the sub-query
            $query .= (string) $this->values;
        }

        return $query;
    }

    /**
     * Reset the query parameters
     *
     * @return $this
     */
    public function reset()
    {
        $this->table = null;
        $this->columns = array();
        $this->values  = array();
        $this->parameters = array();

        return $this;
    }
}