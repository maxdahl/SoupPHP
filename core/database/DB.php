<?php

namespace Soup\DB;
defined('ROOT') or die('No direct script access');

class DB
{
    //Query types
    const SELECT =  1;
    const INSERT =  2;
    const UPDATE =  3;
    const DELETE =  4;

    /**
     * Create a new Query of the given type.
     *
     *     // Create a new SELECT query
     *     $query = DB::query('SELECT * FROM users');
     *
     *     // Create a new DELETE query
     *     $query = DB::query('DELETE FROM users WHERE id = 5');
     *
     * Specifying the type changes the returned result. When using
     * `DB::SELECT`, a [Database_Query_Result] will be returned.
     * `DB::INSERT` queries will return the insert id and number of rows.
     * For all other queries, the number of affected rows is returned.
     *
     * @param   string   SQL statement
     * @param   integer  type: DB::SELECT, DB::UPDATE, etc
     * @return  Query
     */
    public static function query($sql, $type = null)
    {
        return new Query($sql, $type);
    }

    /**
     * Returns the last query
     *
     * @param  string $db the db name
     * @return string the last query
     */
    public static function lastQuery($db = 'default')
    {
        return Connection::instance($db)->lastQuery();
    }

    /**
     * Returns the DB drivers error info
     *
     * @param  string $db the db name
     * @return mixed the DB drivers error info
     */
    public static function errorInfo($db = 'default')
    {
        return Connection::instance($db)->errorInfo();
    }

    /**
     * Create a new [Query\Builder\Select]. Each argument will be
     * treated as a column. To generate a `foo AS bar` alias, use an array.
     *
     *     // SELECT id, username
     *     $query = DB::select('id', 'username');
     *
     *     // SELECT id AS user_id
     *     $query = DB::select(array('id', 'user_id'));
     *
     * @param   mixed   column name or array($column, $alias) or object
     * @param   ...
     * @return  Query\Builder\Select
     */
    public static function select($args = null)
    {
        if(is_array($args) && is_array($args[0]))
            $funcArgs = $args[0];
        else
            $funcArgs = func_get_args();

        return Connection::instance()->select($funcArgs);
    }

    /**
     * Create a new [Query\Builder\Select] from an array of columns.
     *
     *     // SELECT id, username
     *     $query = DB::selectArray(array('id', 'username'));
     *
     * @param   array   columns to select
     * @return  Query\Builder\Select
     */
    public static function selectArray(array $columns = null)
    {
        return Connection::instance()->select($columns);
    }

    /**
     * Create a new [Query\Builder\Insert].
     *
     *     // INSERT INTO users (id, username)
     *     $query = DB::insert('users', array('id', 'username'));
     *
     * @param   string  table to insert into
     * @param   array   list of column names or array($column, $alias) or object
     * @return  Query\Builder\Insert
     */
    public static function insert($table = null, array $columns = null)
    {
        return Connection::instance()->insert($table, $columns);
    }

    /**
     * Create a new [Query\Builder\Update].
     *
     *     // UPDATE users
     *     $query = DB::update('users');
     *
     * @param   string  table to update
     * @return  Query\Builder\Update
     */
    public static function update($table = null)
    {
        return Connection::instance()->update($table);
    }

    /**
     * Create a new [Query\Builder\Delete].
     *
     *     // DELETE FROM users
     *     $query = DB::delete('users');
     *
     * @param   string  table to delete from
     * @return  Query\Builder\Delete
     */
    public static function delete($table = null)
    {
        return Connection::instance()->delete($table);
    }

    /**
     * Create a new [Database_Expression] which is not escaped. An expression
     * is the only way to use SQL functions within query builders.
     *
     *     $expression = DB::expr('COUNT(users.id)');
     *
     * @param   string $string expression
     * @return  Expression
     */
    public static function expr($string)
    {
        return new Expression($string);
    }

    /**
     * Create a new [Database_Expression] containing a quoted identifier. An expression
     * is the only way to use SQL functions within query builders.
     *
     *     $expression = DB::identifier('users.id');	// returns `users`.`id` for MySQL
     *
     * @param	string	$string	the string to quote
     * @param   string  $db the database connection to use
     * @return	Expression
     */
    public static function identifier($string, $db = 'default')
    {
        return new Expression(static::quoteIdentifier($string));
    }

    /**
     * Quote a value for an SQL query.
     *
     * @param	mixed   $value any value to quote
     * @param   string  $db the database connection to use
     * @return	string	the quoted value
     */
    public static function quote($value, $db = 'default')
    {
        if (is_array($value))
        {
            foreach ($value as $k => $v)
                $value[$k] = static::quote($v);

            return $value;
        }

        return Connection::instance($db)->quote($value);
    }

    /**
     * Quotes an identifier so it is ready to use in a query.
     *
     * @param	string	$string	the string to quote
     * @param   string  $db the database connection to use
     * @return	string	the quoted identifier
     */
    public static function quoteIdentifier($string, $db = 'default')
    {
        if (is_array($string))
        {
            foreach ($string as $k => $s)
                $string[$k] = static::quoteIdentifier($s);

            return $string;
        }
        return Connection::instance($db)->quoteIdentifier($string);
    }

    /**
     * Quote a database table name and adds the table prefix if needed.
     *
     * @param	mixed   $value table name or array(table, alias)
     * @param   string  $db the database connection to use
     * @return	string	the quoted identifier
     */
    public static function quoteTable($value, $db = 'default')
    {
        if (is_array($value))
        {
            foreach ($value as $k => $v)
                $value[$k] = static::quoteTable($v);

            return $value;
        }
        return Connection::instance($db)->quoteTable($value);
    }

    /**
     * Escapes a string to be ready for use in a sql query
     *
     * @param	string	$string	the string to escape
     * @param   string  $db the database connection to use
     * @return	string	the escaped string
     */
    public static function escape($string, $db = 'default')
    {
        return Connection::instance($db)->escape($string);
    }

    /**
     * Lists all of the columns in a table. Optionally, a LIKE string can be
     * used to search for specific fields.
     *
     *     // Get all columns from the "users" table
     *     $columns = DB::listColumns('users');
     *
     *     // Get all name-related columns
     *     $columns = DB::listColumns('users', '%name%');
     *
     * @param   string  table to get columns from
     * @param   string  column to search for
     * @param   string  $db the database connection to use
     * @return  array
     */
    public static function listColumns($table = null, $like = null, $db = 'default')
    {
        return Connection::instance($db)->listColumns($table, $like);
    }

    /**
     * If a table name is given it will return the table name with the configured
     * prefix.  If not, then just the prefix is returned
     *
     * @param   string  $like table to search for
     * @param   string  $db the database connection to use
     * @return  string  the prefixed table name or the prefix
     */
    public static function listTables($like = null, $db = 'default')
    {
        return Connection::instance($db)->listTables($like);
    }

    /**
     * Count the number of records in a table.
     *
     *
     * @param   mixed    table name string or array(query, alias)
     * @param   string  $db the database connection to use
     * @return  integer
     */
    public static function countRecords($table, $db = 'default')
    {
        return Connection::instance($db)->countRecords($table);
    }

    /**
     * Count the number of records in the last query, without LIMIT or OFFSET applied.
     *
     * @param   string  $db the database connection to use
     * @return  integer
     */
    public static function countLastQuery($db = 'default')
    {
        return Connection::instance($db)->countLastQuery();
    }

    /**
     * Set the connection character set. This is called automatically by [static::connect].
     *
     * @param   string   character set name
     * @param   string  $db the database connection to use
     * @return  void
     */
    public static function setCharset($charset, $db = 'default')
    {
        Connection::instance($db)->setCharset($charset);
    }
}