<?php

namespace Soup\DB\Query\Builder;
defined('ROOT') or die('No direct script access');

class Update extends Where
{
    /**
     * @var string  $_table  table name
     */
    protected $table;

    /**
     * @var array  $_set  update values
     */
    protected $set = array();

    /**
     * @var array  $_join  join statements
     */
    protected $join = array();

    /**
     * @var Database_Query_Builder_Join  $_last_join  last join statement
     */
    protected $lastJoin;

    /**
     * Set the table for a update.
     *
     * @param  mixed  $table  table name or array($table, $alias) or object
     *
     * @return  void
     */
    public function __construct($table = NULL)
    {
        if ($table)
        {
            // Set the initial table name
            $this->table = $table;
        }

        // Start the query with no SQL
        parent::__construct('', \DB::UPDATE);
    }

    /**
     * Sets the table to update.
     *
     * @param  mixed  $table  table name or array($table, $alias)
     *
     * @return  $this
     */
    public function table($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set the values to update with an associative array.
     *
     * @param  array  $pairs   associative (column => value) list
     *
     * @return  $this
     */
    public function set(array $pairs)
    {
        foreach ($pairs as $column => $value)
        {
            $this->set[] = array($column, $value);
        }

        return $this;
    }

    /**
     * Set the value of a single column.
     *
     * @param   mixed  $column  table name or array($table, $alias) or object
     * @param   mixed  $value   column value
     *
     * @return  $this
     */
    public function value($column, $value)
    {
        $this->set[] = array($column, $value);

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

        // Start an update query
        $query = 'UPDATE '.$db->quoteTable($this->table);

        if ( ! empty($this->join))
        {
            // Add tables to join
            $query .= ' '.$this->compileJoin($db, $this->join);
        }

        // Add the columns to update
        $query .= ' SET '.$this->compileSet($db, $this->set);

        if ( ! empty($this->where))
        {
            // Add selection conditions
            $query .= ' WHERE '.$this->compileConditions($db, $this->where);
        }

        if ( ! empty($this->orderBy))
        {
            // Add sorting
            $query .= ' '.$this->compileOrderBy($db, $this->orderBy);
        }

        if ($this->limit !== null)
        {
            // Add limiting
            $query .= ' LIMIT '.$this->limit;
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
        $this->table      = null;
        $this->join       = array();
        $this->set        = array();
        $this->where      = array();
        $this->orderBy   = array();
        $this->limit      = null;
        $this->lastJoin  = null;
        $this->parameters = array();

        return $this;
    }

    /**
     * Adds addition tables to "JOIN ...".
     *
     * @param   mixed   $table  column name or array($column, $alias) or object
     * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
     *
     * @return  $this
     */
    public function join($table, $type = null)
    {
        $this->join[] = $this->lastJoin = new Join($table, $type);

        return $this;
    }

    /**
     * Adds "ON ..." conditions for the last created JOIN statement.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function on($c1, $op, $c2)
    {
        $this->lastJoin->on($c1, $op, $c2);

        return $this;
    }

    /**
     * Adds "AND ON ..." conditions for the last created JOIN statement.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function andOn($c1, $op, $c2)
    {
        $this->lastJoin->andOn($c1, $op, $c2);

        return $this;
    }

    /**
     * Adds "OR ON ..." conditions for the last created JOIN statement.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function orOn($c1, $op, $c2)
    {
        $this->lastJoin->orOn($c1, $op, $c2);

        return $this;
    }

    /**
     * Adds an opening bracket the last created JOIN statement.
     *
     * @return  $this
     */
    public function onOpen()
    {
        $this->lastJoin->onOpen();

        return $this;
    }

    /**
     * Adds a closing bracket for the last created JOIN statement.
     *
     * @return  $this
     */
    public function onClose()
    {
        $this->lastJoin->onClose();

        return $this;
    }
}