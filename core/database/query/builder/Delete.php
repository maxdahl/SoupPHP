<?php

namespace Soup\DB\Query\Builder;
defined('ROOT') or die('No direct script access');

class Delete extends Where
{
    protected $table;

    /**
     * Set the table for a delete.
     *
     * @param mixed $table table name or array($table, $alias) or object
     */
    public function __construct($table = null)
    {
        if ($table)
        {
            // Set the initial table name
            $this->table = $table;
        }

        // Start the query with no SQL
        parent::__construct('', \DB::DELETE);
    }

    /**
     * Sets the table to delete from.
     *
     * @param   mixed  $table  table name or array($table, $alias) or object
     *
     * @return  $this
     */
    public function table($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param   mixed  $db  Database_Connection instance or instance name
     *
     * @return  string
     */
    public function compile($db = null)
    {
        if (!$db instanceof \Soup\DB\Connection)
            $db = \Soup\DB\Connection::instance();

        // Start a deletion query
        $query = 'DELETE FROM ' . $db->quoteTable($this->table);

        if ( ! empty($this->where))
        {
            // Add deletion conditions
            $query .= ' WHERE ' . $this->compileConditions($db, $this->where);
        }

        if ( ! empty($this->orderBy))
        {
            // Add sorting
            $query .= ' ' . $this->compileOrderBy($db, $this->orderBy);
        }

        if ($this->limit !== null)
        {
            // Add limiting
            $query .= ' LIMIT ' . $this->limit;
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
        $this->table = NULL;

        $this->where    = array();
        $this->order_by = array();

        $this->parameters = array();

        $this->limit = NULL;

        return $this;
    }
}