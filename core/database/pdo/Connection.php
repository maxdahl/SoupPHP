<?php

namespace Soup\DB\PDO;
defined('ROOT') or die('No direct script access');

class Connection extends \Soup\DB\Connection
{
    protected $connection = null;


    public function __construct($config)
    {
        $this->config = $config;

        $dsn = $config['driver'];
        $dsn .= ':host=' . $config['host'];
        $dsn .= ';dbname=' . $config['database'];

        try
        {
            $this->connection = new \PDO($dsn, $config['user'], $config['password']);
            $this->setCharset($config['charset']);
        }
        catch(\PDOException $e)
        {
            if($this->connection)
                $errorCode = $this->connection->errorInfo()[1];
            else
                $errorCode = 0;

            throw new \Exception(str_replace($config['password'], '*****', $e->getMessage()), $e->getCode(), $e); //TODO implement DBException that accepts db error code
        }
    }

    public function setCharset($charset)
    {
        if ($charset)
        {
            $this->connection->exec('SET NAMES '.$this->quote($charset));
        }
    }

    public function query($type, $sql, $asObject)
    {
        try
        {
            $result = $this->connection->query($sql);

            if($result === false)
            {
                $error = $this->connection->errorInfo();
                throw new \Exception($error[2], $error[1]);
            }
        }
        catch(\Exception $e)
        {
            if($this->connection)
                $errorCode = $this->connection->errorInfo()[1];
            else
                $errorCode = 0;

            throw new \Exception($e->getMessage() . ' with query "' . $sql . '"', $e->getCode(), $e); //TODO implement DBException that accepts db error code
        }

        $this->lastQuery = $sql;

        switch ($type)
        {
            case \Soup\DB\DB::SELECT:
                return new Result($result, $sql, $asObject);
                break;
            case \Soup\DB\DB::INSERT:
                return [
                    $this->connection->lastInsertId(),
                    $result->rowCount()
                ];
                break;
            case \Soup\DB\DB::UPDATE:
            case \Soup\DB\DB::DELETE:
                return $result->errorCode() === '00000' ? $result->rowCount() : -1;
        }

        return $result->errorCode() === '00000' ? true : false;
    }

    /**
     * Retrieve error info
     *
     * @return array
     */
    public function errorInfo()
    {
        return $this->connection->errorInfo();
    }

    /**
     * Escape a value
     *
     * @param mixed $value
     *
     * @return string
     */
    public function escape($value)
    {
        $result = $this->connection->quote($value);

        // poor-mans workaround for the fact that not all drivers implement quote()
        if (empty($result))
        {
            if ( ! is_numeric($value))
            {
                $result = "'".str_replace("'", "''", $value)."'";
            }
        }
        return $result;
    }

    public function listTables($like = null)
    {
        throw new \Exception('Database method '.__METHOD__.' is not supported by '.__CLASS__);
    }

    public function listColumns($table, $like = null)
    {
        $q = $this->connection->prepare("DESCRIBE ".$this->quoteTable($table));
        $q->execute();
        $result  = $q->fetchAll();
        $count   = 0;
        $columns = array();

        if(!is_null($like))
            $like = str_replace('%', '.*', $like);

        foreach ($result as $row)
        {
            if (!is_null($like) and !preg_match('#'.$like.'#', $row['Field']))
                continue;

            list($type, $length) = $this->parseType($row['Type']);

            $column = $this->datatype($type);

            $column['name']             = $row['Field'];
            $column['default']          = $row['Default'];
            $column['data_type']        = $type;
            $column['null']             = ($row['Null'] == 'YES');
            $column['ordinal_position'] = ++$count;
            switch ($column['type'])
            {
                case 'float':
                    if (isset($length))
                        list($column['numeric_precision'], $column['numeric_scale']) = explode(',', $length);
                    break;

                case 'int':
                    if (isset($length))
                        // MySQL attribute
                        $column['display'] = $length;
                    break;

                case 'string':
                    switch ($column['data_type'])
                    {
                        case 'binary':
                        case 'varbinary':
                            $column['character_maximum_length'] = $length;
                            break;

                        case 'char':
                        case 'varchar':
                            $column['character_maximum_length'] = $length;
                        case 'text':
                        case 'tinytext':
                        case 'mediumtext':
                        case 'longtext':
                            $column['collation_name'] = isset($row['Collation']) ? $row['Collation'] : null;
                            break;

                        case 'enum':
                        case 'set':
                            $column['collation_name'] = isset($row['Collation']) ? $row['Collation'] : null;
                            $column['options']        = explode('\',\'', substr($length, 1, - 1));
                            break;
                    }
                    break;
            }

            // MySQL attributes
            $column['comment']    = isset($row['Comment']) ? $row['Comment'] : null;
            $column['extra']      = $row['Extra'];
            $column['key']        = $row['Key'];
            $column['privileges'] = isset($row['Privileges']) ? $row['Privileges'] : null;

            $columns[$row['Field']] = $column;
        }

        return $columns;
    }
}