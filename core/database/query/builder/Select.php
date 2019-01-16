<?php

namespace Soup\DB\Query\Builder;
defined('ROOT') or die('No direct script access');

class Select extends Where
{
    /**
     * @var array  $_select  columns to select
     */
    protected $select = array();

    /**
     * @var bool  $_distinct  whether to select distinct values
     */
    protected $distinct = false;

    /**
     * @var array  $_from  table name
     */
    protected $from = array();

    /**
     * @var array  $_join  join objects
     */
    protected $join = array();

    /**
     * @var array  $_group_by  group by clauses
     */
    protected $groupBy = array();

    /**
     * @var array  $_having  having clauses
     */
    protected $having = array();

    /**
     * @var integer  $_offset  offset
     */
    protected $offset = null;

    /**
     * @var  Join  $_last_join  last join statement
     */
    protected $lastJoin;

    /**
     * Sets the initial columns to select from.
     *
     * @param  array  $columns  column list
     */
    public function __construct(array $columns = null)
    {
        if ( ! empty($columns))
        {
            // Set the initial columns
            $this->select = $columns;
        }

        // Start the query with no actual SQL statement
        parent::__construct('', \DB::SELECT);
    }

    /**
     * Enables or disables selecting only unique columns using "SELECT DISTINCT"
     *
     * @param   boolean  $value  enable or disable distinct columns
     * @return  $this
     */
    public function distinct($value = true)
    {
        $this->distinct = (bool) $value;

        return $this;
    }

    /**
     * Choose the columns to select from.
     *
     * @param   mixed  $columns  column name or array($column, $alias) or object
     * @param   ...
     *
     * @return  $this
     */
    public function select($columns = null)
    {
        $columns = func_get_args();

        $this->select = array_merge($this->select, $columns);

        return $this;
    }

    /**
     * Choose the columns to select from, using an array.
     *
     * @param   array  $columns  list of column names or aliases
     * @param   bool   $reset    if true, don't merge but overwrite
     *
     * @return  $this
     */
    public function selectArray(array $columns, $reset = false)
    {
        $this->_select = $reset ? $columns : array_merge($this->select, $columns);

        return $this;
    }

    /**
     * Choose the tables to select "FROM ..."
     *
     * @param   mixed  $tables  table name or array($table, $alias)
     * @param   ...
     *
     * @return  $this
     */
    public function from($tables)
    {
        $tables = func_get_args();

        $this->from = array_merge($this->from, $tables);

        return $this;
    }

    /**
     * Adds addition tables to "JOIN ...".
     *
     * @param   mixed   $table  column name or array($column, $alias)
     * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
     *
     * @return  $this
     */
    public function join($table, $type = NULL)
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

    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param   mixed  $columns  column name or array($column, $column) or object
     * @param   ...
     *
     * @return  $this
     */
    public function groupBy($columns)
    {
        $columns = func_get_args();

        foreach($columns as $idx => $column)
        {
            // if an array of columns is passed, flatten it
            if (is_array($column))
            {
                foreach($column as $c)
                {
                    $columns[] = $c;
                }
                unset($columns[$idx]);
            }
        }

        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * Alias of and_having()
     *
     * @param   mixed  $column column name or array($column, $alias) or object
     * @param   string $op     logic operator
     * @param   mixed  $value  column value
     *
     * @return  $this
     */
    public function having($column, $op = null, $value = null)
    {
        return call_fuel_func_array(array($this, 'andHaving'), func_get_args());
    }

    /**
     * Creates a new "AND HAVING" condition for the query.
     *
     * @param   mixed  $column column name or array($column, $alias) or object
     * @param   string $op     logic operator
     * @param   mixed  $value  column value
     *
     * @return  $this
     */
    public function andHaving($column, $op = null, $value = null)
    {
        if($column instanceof \Closure)
        {
            $this->andHavingOpen();
            $column($this);
            $this->andHavingClose();
            return $this;
        }

        if(func_num_args() === 2)
        {
            $value = $op;
            $op = '=';
        }

        $this->having[] = array('AND' => array($column, $op, $value));

        return $this;
    }

    /**
     * Creates a new "OR HAVING" condition for the query.
     *
     * @param   mixed   $column  column name or array($column, $alias) or object
     * @param   string  $op      logic operator
     * @param   mixed   $value   column value
     *
     * @return  $this
     */
    public function orHaving($column, $op = null, $value = null)
    {
        if($column instanceof \Closure)
        {
            $this->orHavingOpen();
            $column($this);
            $this->orHavingClose();
            return $this;
        }

        if(func_num_args() === 2)
        {
            $value = $op;
            $op = '=';
        }

        $this->having[] = array('OR' => array($column, $op, $value));

        return $this;
    }

    /**
     * Alias of and_having_open()
     *
     * @return  $this
     */
    public function havingOpen()
    {
        return $this->andHavingOpen();
    }

    /**
     * Opens a new "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function andHavingOpen()
    {
        $this->having[] = array('AND' => '(');

        return $this;
    }

    /**
     * Opens a new "OR HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function orHavingOpen()
    {
        $this->having[] = array('OR' => '(');

        return $this;
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function havingClose()
    {
        return $this->andHavingClose();
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function andHavingClose()
    {
        $this->having[] = array('AND' => ')');

        return $this;
    }

    /**
     * Closes an open "OR HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function orHavingClose()
    {
        $this->having[] = array('OR' => ')');

        return $this;
    }

    /**
     * Start returning results after "OFFSET ..."
     *
     * @param   integer  $number  starting result number
     *
     * @return  $this
     */
    public function offset($number)
    {
        $this->offset = (int) $number;

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
        if ( ! $db instanceof \Soup\DB\Connection)
        {
            // Get the database instance
            $db = $this->connection ?: \Soup\DB\Connection::instance();
        }

        // Callback to quote identifiers
        $quote_ident = array($db, 'quoteIdentifier');

        // Callback to quote tables
        $quote_table = array($db, 'quoteTable');

        // Start a selection query
        $query = 'SELECT ';

        if ($this->distinct === TRUE)
        {
            // Select only unique results
            $query .= 'DISTINCT ';
        }

        if (empty($this->select))
        {
            // Select all columns
            $query .= '*';
        }
        else
        {
            // Select all columns
            $query .= implode(', ', array_unique(array_map($quote_ident, $this->select)));
        }

        if ( ! empty($this->from))
        {
            // Set tables to select from
            $query .= ' FROM '.implode(', ', array_unique(array_map($quote_table, $this->from)));
        }

        if ( ! empty($this->join))
        {
            // Add tables to join
            $query .= ' '.$this->compileJoin($db, $this->join);
        }

        if ( ! empty($this->where))
        {
            // Add selection conditions
            $query .= ' WHERE '.$this->compileConditions($db, $this->where);
        }

        if ( ! empty($this->groupBy))
        {
            // Add sorting
            $query .= ' GROUP BY '.implode(', ', array_map($quote_ident, $this->groupBy));
        }

        if ( ! empty($this->having))
        {
            // Add filtering conditions
            $query .= ' HAVING '.$this->compileConditions($db, $this->having);
        }

        if ( ! empty($this->orderBy))
        {
            // Add sorting
            $query .= ' '.$this->compileOrderBy($db, $this->orderBy);
        }

        if ($this->limit !== NULL)
        {
            // Add limiting
            $query .= ' LIMIT '.$this->limit;
        }

        if ($this->offset !== NULL)
        {
            // Add offsets
            $query .= ' OFFSET '.$this->offset;
        }

        return $query;
    }

    /**
     * Reset the query parameters
     * @return $this
     */
    public function reset()
    {
        $this->select   = array();
        $this->from     = array();
        $this->join     = array();
        $this->where    = array();
        $this->groupBy = array();
        $this->having   = array();
        $this->orderBy = array();
        $this->distinct = false;
        $this->limit     = null;
        $this->offset    = null;
        $this->lastJoin = null;
        $this->parameters = array();

        return $this;
    }

}