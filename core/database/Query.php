<?php

namespace Soup\DB;

class Query
{
    /**
     * @var  int  Query type
     */
    protected $type;

    /**
     * @var  string  SQL statement
     */
    protected $sql;

    /**
     * @var  array  Quoted query parameters
     */
    protected $parameters = array();

    /**
     * @var  bool  Return results as associative arrays or objects
     */
    protected $asObject = false;

    /**
     * @var  Connection  Connection to use when compiling the SQL
     */
    protected $connection = null;

    /**
     * Creates a new SQL query of the specified type.
     *
     * @param string $sql   query string
     * @param integer $type query type: DB::SELECT, DB::INSERT, etc
     */
    public function __construct($sql, $type = null)
    {
        $this->type = $type;
        $this->sql = $sql;
    }

    /**
     * Return the SQL query string.
     *
     * @return  string
     */
    final public function __toString()
    {
        try
        {
            // Return the SQL string
            return $this->compile();
        }
        catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }

    /**
     * Get the type of the query.
     *
     * @return  integer
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * Returns results as associative arrays
     *
     * @return  $this
     */
    public function asAssoc()
    {
        $this->asObject = false;

        return $this;
    }

    /**
     * Returns results as objects
     *
     * @param   mixed $class classname or true for stdClass
     *
     * @return  $this
     */
    public function asObject($class = true)
    {
        $this->asObject = $class;

        return $this;
    }

    /**
     * Set the value of a parameter in the query.
     *
     * @param   string $param parameter key to replace
     * @param   mixed  $value value to use
     *
     * @return  $this
     */
    public function param($param, $value)
    {
        // Add or overload a new parameter
        $this->parameters[$param] = $value;

        return $this;
    }

    /**
     * Bind a variable to a parameter in the query.
     *
     * @param  string $param parameter key to replace
     * @param  mixed  $var   variable to use
     *
     * @return $this
     */
    public function bind($param, & $var)
    {
        // Bind a value to a variable
        $this->parameters[$param] =& $var;

        return $this;
    }

    /**
     * Add multiple parameters to the query.
     *
     * @param array $params list of parameters
     *
     * @return  $this
     */
    public function parameters(array $params)
    {
        // Merge the new parameters in
        $this->parameters = $params + $this->parameters;

        return $this;
    }

    /**
     * Set a DB connection to use when compiling the SQL
     *
     * @param  mixed  $db
     *
     * @return  $this
     */
    public function setConnection($db = 'default')
    {
        if (!$db instanceof Connection)
        {
            // Get the database instance
            $db = Connection::instance($db);
        }
        $this->connection = $db;

        return $this;
    }

    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param   mixed $db Database instance
     *
     * @return  string
     */
    public function compile($db = null)
    {
        if ($this->connection !== null and $db === null)
            $db = $this->connection;

        if($db === null)
            $db = 'default';

        if (!$db instanceof Connection)
            $db = Connection::instance($db);

        // Import the SQL locally
        $sql = $this->sql;

        if (!empty($this->parameters))
        {
            // Quote all of the values
            $values = array_map(array($db, 'quote'), $this->parameters);

            // Replace the values in the SQL
            $sql = \Soup\Helper\Str::tr($sql, $values);
        }

        return trim($sql);
    }

    /**
     * Execute the current query on the given database.
     *
     * @param   mixed   $db Database instance
     *
     * @return  object   DB\Result for SELECT queries
     * @return  mixed    the insert id for INSERT queries
     * @return  integer  number of affected rows for all other queries
     */
    public function execute($db = null)
    {

        if ($this->connection !== null && $db === null)
            $db = $this->connection;

        if($db === null)
            $db = 'default';

        if (!$db instanceof Connection)
            $db = Connection::instance($db);

        // Compile the SQL query
        $sql = $this->compile($db);

        // make sure we have a SQL type to work with
        if (is_null($this->type))
        {
            // get the SQL statement type without having to duplicate the entire statement
            $stmt = preg_split('/[\s]+/', ltrim(substr($sql, 0, 11), '('), 2);
            switch(strtoupper(reset($stmt)))
            {
                case 'DESCRIBE':
                case 'EXECUTE':
                case 'EXPLAIN':
                case 'SELECT':
                case 'SHOW':
                    $this->type = \DB::SELECT;
                    break;
                case 'INSERT':
                case 'REPLACE':
                    $this->type = \DB::INSERT;
                    break;
                case 'UPDATE':
                    $this->type = \DB::UPDATE;
                    break;
                case 'DELETE':
                    $this->type = \DB::DELETE;
                    break;
                default:
                    $this->type = 0;
            }
        }
        try {
            $result = $db->query($this->type, $sql, $this->asObject);
        }
        catch(\Exception $e) {
            \debug($e, false);
        }

        return $result;
    }
}
