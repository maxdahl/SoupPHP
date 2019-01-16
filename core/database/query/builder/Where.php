<?php

namespace Soup\DB\Query\Builder;
defined('ROOT') or die('No direct script access');

abstract class Where extends \Soup\DB\Query\Builder
{
    /**
     * @var array  $_where  where statements
     */
    protected $where = array();

    /**
     * @var array  $_order_by  order by clause
     */
    protected $orderBy = array();

    /**
     * @var  integer  $_limit
     */
    protected $limit = null;

    /**
     * Alias of and_where()
     *
     * @return  $this
     */
    public function where()
    {
        return call_user_func_array(array($this, 'andWhere'), func_get_args());
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param   mixed   $column  column name or array($column, $alias) or object
     * @param   string  $op      logic operator
     * @param   mixed   $value   column value
     *
     * @return  $this
     */
    public function andWhere($column, $op = null, $value = null)
    {
        if($column instanceof \Closure)
        {
            $this->andWhereOpen();
            $column($this);
            $this->andWhereClose();
            return $this;
        }

        if (is_array($column))
        {
            foreach ($column as $key => $val)
            {
                if (is_array($val))
                {
                    $this->andWhere($val[0], $val[1], $val[2]);
                }
                else
                {
                    $this->andWhere($key, '=', $val);
                }
            }
        }
        else
        {
            if(func_num_args() === 2)
            {
                $value = $op;
                $op = '=';
            }
            $this->where[] = array('AND' => array($column, $op, $value));
        }

        return $this;
    }

    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param   mixed   $column  column name or array($column, $alias) or object
     * @param   string  $op      logic operator
     * @param   mixed   $value   column value
     *
     * @return  $this
     */
    public function orWhere($column, $op = null, $value = null)
    {
        if($column instanceof \Closure)
        {
            $this->orWhereOpen();
            $column($this);
            $this->orWhereClose();
            return $this;
        }

        if (is_array($column))
        {
            foreach ($column as $key => $val)
            {
                if (is_array($val))
                {
                    $this->orWhere($val[0], $val[1], $val[2]);
                }
                else
                {
                    $this->orWhere($key, '=', $val);
                }
            }
        }
        else
        {
            if(func_num_args() === 2)
            {
                $value = $op;
                $op = '=';
            }
            $this->where[] = array('OR' => array($column, $op, $value));
        }
        return $this;
    }

    /**
     * Alias of and_where_open()
     *
     * @return  $this
     */
    public function whereOpen()
    {
        return $this->andWhereOpen();
    }

    /**
     * Opens a new "AND WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function andWhereOpen()
    {
        $this->where[] = array('AND' => '(');

        return $this;
    }

    /**
     * Opens a new "OR WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function orWhereOpen()
    {
        $this->where[] = array('OR' => '(');

        return $this;
    }

    /**
     * Closes an open "AND WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function whereClose()
    {
        return $this->andWhereClose();
    }

    /**
     * Closes an open "AND WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function andWhereClose()
    {
        $this->where[] = array('AND' => ')');

        return $this;
    }

    /**
     * Closes an open "OR WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function orWhereClose()
    {
        $this->where[] = array('OR' => ')');

        return $this;
    }

    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param   mixed   $column     column name or array($column, $alias) or object
     * @param   string  $direction  direction of sorting
     *
     * @return  $this
     */
    public function orderBy($column, $direction = null)
    {
        $this->orderBy[] = array($column, $direction);

        return $this;
    }

    /**
     * Return up to "LIMIT ..." results
     *
     * @param   integer  $number  maximum results to return
     *
     * @return  $this
     */
    public function limit($number)
    {
        $this->limit = (int) $number;

        return $this;
    }
}