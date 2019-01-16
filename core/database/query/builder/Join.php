<?php

namespace Soup\DB\Query\Builder;
defined('ROOT') or die('No direct script access');

class Join extends \Soup\DB\Query\Builder
{
    /**
     * @var string  $_type  join type
     */
    protected $type = null;

    /**
     * @var string  $_table  join table
     */
    protected $table = null;

    /**
     * @var string  $_alias  join table alias
     */
    protected $alias = null;

    /**
     * @var array  $_on  ON clauses
     */
    protected $on = array();

    /**
     * Creates a new JOIN statement for a table. Optionally, the type of JOIN
     * can be specified as the second parameter.
     *
     * @param   mixed  $table column name or array($column, $alias) or object
     * @param   string $type  type of JOIN: INNER, RIGHT, LEFT, etc
     */
    public function __construct($table, $type = null)
    {
        // Set the table and alias to JOIN on
        if (is_array($table))
        {
            $this->table = array_shift($table);
            $this->alias = array_shift($table);
        }
        else
        {
            $this->table = $table;
            $this->alias = null;
        }

        if ($type !== null)
        {
            // Set the JOIN type
            $this->type = (string) $type;
        }
    }

    /**
     * Adds a new OR condition for joining.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function orOn($c1, $op, $c2)
    {
        $this->on[] = array($c1, $op, $c2, 'OR');

        return $this;
    }

    /**
     * Adds a new AND condition for joining.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function on($c1, $op, $c2)
    {
        $this->on[] = array($c1, $op, $c2, 'AND');

        return $this;
    }

    /**
     * Adds a new AND condition for joining.
     *
     * @param   mixed   $c1  column name or array($column, $alias) or object
     * @param   string  $op  logic operator
     * @param   mixed   $c2  column name or array($column, $alias) or object
     *
     * @return  $this
     */
    public function andOn($c1, $op, $c2)
    {
        return $this->on($c1, $op, $c2);
    }

    /**
     * Adds a opening bracket.
     *
     * @return  $this
     */
    public function onOpen()
    {
        $this->on[] = array('', '', '', '(');

        return $this;
    }

    /**
     * Adds a closing bracket.
     *
     * @return  $this
     */
    public function onClose()
    {
        $this->on[] = array('', '', '', ')');

        return $this;
    }

    /**
     * Compile the SQL partial for a JOIN statement and return it.
     *
     * @param   mixed  $db  Database_Connection instance or instance name
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

        if ($this->type)
        {
            $sql = strtoupper($this->type).' JOIN';
        }
        else
        {
            $sql = 'JOIN';
        }

        if ($this->table instanceof Select)
        {
            // Compile the subquery and add it
            $sql .= ' ('.$this->table->compile().')';
        }
        elseif ($this->table instanceof \Soup\DB\Expression)
        {
            // Compile the expression and add its value
            $sql .= ' ('.trim($this->table->value(), ' ()').')';
        }
        else
        {
            // Quote the table name that is being joined
            $sql .= ' '.$db->quoteTable($this->table);
        }

        // Add the alias if needed
        if ($this->alias)
        {
            $sql .= ' AS '.$db->quoteTable($this->alias);
        }

        $conditions = array();

        foreach ($this->on as $condition)
        {
            // Split the condition
            list($c1, $op, $c2, $chaining) = $condition;

            $c_string = $c1 . $op . $c2;

            // Just a chaining character?
            if (empty($c_string))
            {
                $conditions[] = $chaining;
            }
            else
            {
                // Check if we have a pending bracket open
                if (end($conditions) == '(')
                {
                    // Update the chain type
                    $conditions[key($conditions)] = ' '.$chaining.' (';
                }
                else
                {
                    // Just add chain type
                    $conditions[] = ' '.$chaining.' ';
                }

                if ($op)
                {
                    // Make the operator uppercase and spaced
                    $op = ' '.strtoupper($op);
                }

                // Quote each of the identifiers used for the condition
                $conditions[] = $db->quoteIdentifier($c1).$op.' '.(is_null($c2) ? 'NULL' : $db->quoteIdentifier($c2));
            }
        }

        // remove the first chain type
        array_shift($conditions);

        // if there are conditions, concat the conditions "... AND ..." and glue them on...
        empty($conditions) or $sql .= ' ON ('.implode('', $conditions).')';

        return $sql;
    }

    /**
     * Resets the join values.
     *
     * @return  $this
     */
    public function reset()
    {
        $this->type = null;
        $this->table = null;
        $this->alias = null;
        $this->on = array();
    }
}