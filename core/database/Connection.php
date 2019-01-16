<?php

namespace Soup\DB;

defined('ROOT') or die('No direct script access');

abstract class Connection
{
    protected $lastQuery;

    protected static $instances = [];
    protected $config;

    /**
     * @var  string  Character that is used to quote identifiers
     */
    protected $identifier = '';

    abstract public function errorInfo();

    abstract public function setCharset($charset);

    abstract public function escape($value);

    abstract public function query($type, $sql, $asObject);

    abstract public function listColumns($table, $like = null);

    abstract public function listTables($like = null);

    public static function instance($db = 'default')
    {
        if (!isset(static::$instances[$db])) {
            $config = \Config::get('db.' . $db);
            $type = $config['type'];

            $driver = '\\Soup\\DB\\' . strtoupper($type) . '\\Connection';
            self::$instances[$db] = new $driver($config);
        }

        return self::$instances[$db];
    }

    public function lastQuery()
    {
        return $this->lastQuery;
    }

    public function select(array $args)
    {
        $instance = new Query\Builder\Select($args);
        return $instance->setConnection($this);
    }

    public function insert($table = null, $columns = null)
    {
        $instance = new Query\Builder\Insert($table, $columns);
        return $instance->setConnection($this);
    }

    public function update($table = null)
    {
        $instance = new Query\Builder\Update($table);
        return $instance->setConnection($this);
    }

    public function delete($table = null)
    {
        $instance = new Query\Builder\Delete($table);
        return $instance->setConnection($this);
    }

    /**
     * Count the number of records in a table.
     *
     * @param   mixed $table table name string or array(query, alias)
     *
     * @return  integer
     */

    public function countRecords($table)
    {
        $table = $this->quoteTable($table);

        return $this->query(\DB::SELECT, 'SELECT COUNT(*) AS total_row_count FROM ' . $table, false)
            ->get('total_row_count');
    }

    /**
     * Count the number of records in the last query, without LIMIT or OFFSET applied.
     *
     * @return  integer
     */
    public function countLastQuery()
    {
        if ($sql = $this->lastQuery) {
            $sql = trim($sql);
            if (stripos($sql, 'SELECT') !== 0)
                return false;

            if (stripos($sql, 'LIMIT') !== false)
                $sql = preg_replace('/\sLIMIT\s+[^a-z\)]+/i', ' ', $sql); // Remove LIMIT from the SQL

            if (stripos($sql, 'OFFSET') !== false)
                $sql = preg_replace('/\sOFFSET\s+\d+/i', '', $sql); // Remove OFFSET from the SQL

            if (stripos($sql, 'ORDER BY') !== false)
                $sql = preg_replace('/ORDER BY (.+?)(?=LIMIT|GROUP|PROCEDURE|INTO|FOR|LOCK|\)|$)/mi', '', $sql); // Remove ORDER BY clauses from the SQL to improve count query performance

            // Get the total rows from the last query executed
            $result = $this->query(
                \DB::SELECT,
                'SELECT COUNT(*) AS ' . $this->quoteIdentifier('total_rows') . ' ' .
                'FROM (' . $sql . ') AS ' . $this->quoteTable('counted_results'),
                true
            );

            // Return the total number of rows from the query
            return (int)$result->current()->totalRows;
        }

        return false;
    }

    public function tablePrefix($table = null)
    {
        if ($table !== null) {
            return $this->config['table_prefix'] . $table;
        }

        return $this->config['table_prefix'];
    }

    public function quote($value)
    {
        if ($value === null)
            return 'null';
        elseif ($value === true)
            return "'1'";
        elseif ($value === false)
            return "'0'";
        elseif (is_object($value)) {
            if ($value instanceof Query)
                return '(' . $value->compile($this) . ')';
            elseif ($value instanceof Expression)
                return $value->value();
            else
                return $this->quote((string)$value);
        } elseif (is_array($value))
            return '(' . implode(', ', array_map([$this, __FUNCTION__], $value)) . ')';
        elseif (is_int($value))
            return (int)$value;
        elseif (is_float($value))
            return sprintf('%F', $value); // Convert to non-locale aware float to prevent possible commas

        return $this->escape($value);
    }

    /**
     * Quote a database table name and adds the table prefix if needed.
     *
     * @param    mixed $value table name or array(table, alias)
     * @return    string    the quoted identifier
     */
    public function quoteTable($value)
    {
        if (is_array($value)) {
            $table =& $value[0];

            //Add table prefix to the alias
            $value[1] = $this->tablePrefix() . $value[1];
        } else
            $table =& $value;

        if ($table instanceof Query)
            $table = '(' . $table->compile($this) . ')';
        elseif (is_string($table)) {
            if (strpos($table, '.') === false)
                $table = $this->quoteIdentifier($this->tablePrefix() . $table);
            else {
                $parts = explode('.', $table);

                if ($prefix = $this->tablePrefix()) {
                    // Get the offset of the table name, 2nd-to-last part
                    // This works for databases that can have 3 identifiers (Postgre)
                    if (($offset = count($parts)) == 2)
                        $offset = 1;
                    else
                        $offset = $offset - 2;

                    // Add the table prefix to the table name
                    $parts[$offset] = $prefix . $parts[$offset];
                }

                $table = implode('.', array_map([$this, 'quoteIdentifier'], $parts));
            }
        }

        if (is_array($value)) {
            list($value, $alias) = $value;
            return $value . ' AS ' . $alias;
        } else
            return $value;
    }

    /**
     * Quote a database identifier, such as a column name. Adds the
     * table prefix to the identifier if a table name is present.
     *
     *     $column = $db->quoteIdentifier($column);
     *
     * You can also use SQL methods within identifiers.
     *
     *     // The value of "column" will be quoted
     *     $column = $db->quoteIdentifier('COUNT("column")');
     *
     * Objects passed to this function will be converted to strings.
     * [DB\Expression] objects will use the value of the expression.
     * [DB\Query] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param   mixed $value any identifier
     *
     * @return  string
     */
    public function quoteIdentifier($value)
    {
        if ($value === '*')
            return $value;
        elseif (is_object($value)) {
            if ($value instanceof Query)
                return '(' . $value->compile($this) . ')';

            elseif ($value instanceof Expression)
                return $value->value();

            else
                return $this->quoteIdentifier((string)$value);
        } elseif (is_array($value)) {
            // Separate the column and alias
            list($value, $alias) = $value;

            return $this->quoteIdentifier($value) . ' AS ' . $this->quoteIdentifier($alias);
        }

        //value is already quoted
        if (preg_match('/^(["\']).*\1$/m', $value))
            return $value;

        if (strpos($value, '.') !== false) {
            // Split the identifier into the individual parts
            // This is slightly broken, because a table or column name
            // (or user-defined alias!) might legitimately contain a period.
            $parts = explode('.', $value);

            if ($prefix = $this->tablePrefix()) {
                // Get the offset of the table name, 2nd-to-last part
                // This works for databases that can have 3 identifiers (Postgre)
                $offset = count($parts) - 2;

                // Add the table prefix to the table name
                $parts[$offset] = $prefix . $parts[$offset];
            }

            // Quote each of the parts
            return implode('.', array_map(array($this, __FUNCTION__), $parts));
        }

        // That you can simply escape the identifier by doubling
        // it is a built-in assumption which may not be valid for
        // all connection types!  However, it's true for MySQL,
        // SQLite, Postgres and other ANSI SQL-compliant DBs.
        return $this->identifier . str_replace($this->identifier, $this->identifier . $this->identifier, $value) . $this->identifier;
    }

    /**
     * Extracts the text between parentheses, if any.
     *
     * @param string $type
     *
     * @return  array   list containing the type and length, if any
     */
    protected function parseType($type)
    {
        if (($open = strpos($type, '(')) === false)
            return array($type, null);

        // Closing parenthesis
        $close = strpos($type, ')', $open);

        // Length without parentheses
        $length = substr($type, $open + 1, $close - 1 - $open);

        // Type without the length
        $type = substr($type, 0, $open) . substr($type, $close + 1);

        return array($type, $length);
    }

    /**
     * Returns a normalized array describing the SQL data type
     *
     * @param   string $type SQL data type
     *
     * @return  array
     */
    public function datatype($type)
    {
        static $types = array(
            // SQL-92
            'bit' => array('type' => 'string', 'exact' => true),
            'bit varying' => array('type' => 'string'),
            'char' => array('type' => 'string', 'exact' => true),
            'char varying' => array('type' => 'string'),
            'character' => array('type' => 'string', 'exact' => true),
            'character varying' => array('type' => 'string'),
            'date' => array('type' => 'string'),
            'dec' => array('type' => 'float', 'exact' => true),
            'decimal' => array('type' => 'float', 'exact' => true),
            'double precision' => array('type' => 'float'),
            'float' => array('type' => 'float'),
            'int' => array('type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'),
            'integer' => array('type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'),
            'interval' => array('type' => 'string'),
            'national char' => array('type' => 'string', 'exact' => true),
            'national char varying' => array('type' => 'string'),
            'national character' => array('type' => 'string', 'exact' => true),
            'national character varying' => array('type' => 'string'),
            'nchar' => array('type' => 'string', 'exact' => true),
            'nchar varying' => array('type' => 'string'),
            'numeric' => array('type' => 'float', 'exact' => true),
            'real' => array('type' => 'float'),
            'smallint' => array('type' => 'int', 'min' => '-32768', 'max' => '32767'),
            'time' => array('type' => 'string'),
            'time with time zone' => array('type' => 'string'),
            'timestamp' => array('type' => 'string'),
            'timestamp with time zone' => array('type' => 'string'),
            'varchar' => array('type' => 'string'),

            // SQL:1999
            'binary large object' => array('type' => 'string', 'binary' => true),
            'blob' => array('type' => 'string', 'binary' => true),
            'boolean' => array('type' => 'bool'),
            'char large object' => array('type' => 'string'),
            'character large object' => array('type' => 'string'),
            'clob' => array('type' => 'string'),
            'national character large object' => array('type' => 'string'),
            'nchar large object' => array('type' => 'string'),
            'nclob' => array('type' => 'string'),
            'time without time zone' => array('type' => 'string'),
            'timestamp without time zone' => array('type' => 'string'),

            // SQL:2003
            'bigint' => array('type' => 'int', 'min' => '-9223372036854775808', 'max' => '9223372036854775807'),

            // SQL:2008
            'binary' => array('type' => 'string', 'binary' => true, 'exact' => true),
            'binary varying' => array('type' => 'string', 'binary' => true),
            'varbinary' => array('type' => 'string', 'binary' => true),
        );

        if (isset($types[$type])) {
            return $types[$type];
        }

        return array();
    }
}