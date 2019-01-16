<?php

namespace Soup\Core;
defined('ROOT') or die('No direct script access');

class Model
{
    protected $_data = [];

    /**
     * @var string the table name
     */
    protected static $table = null;

    /**
     * @var array the field names
     */
    protected static $columnNames = [];

    /**
     * @var string name of the primary key field
     */
    protected static $uniqueIdentifier = 'id';

    /**
     * @var array mass assignment blacklist
     */
    protected static $blacklist = [];

    /**
     * @var array mass assignment whitelist
     */
    protected static $whitelist = [];

    /**
     * @var string fieldname of the created_at field
     */
    protected static $createdAt = 'created_at';

    /**
     * @var string fieldname of the updated_at field
     */
    protected static $updatedAt = 'updated_at';

    /**
     * @var The database connection to use
     */
    protected static $connection = 'default';

    /**
     * @var array validation rules
     */
    protected static $validationRules = [];

    /**
     * @var Default values for columns
     */
    protected static $defaultValues = [];

    /**
     * @var array Relationship properties
     *            Array index has to be named after existing model class
     * Usage $hasOne = ['profile' => array(
     *                      'var_name' => 'userProfile'
     *                      'key_from' => 'id',
     *                      'model_to' => 'Model_Profile',
     *                      'key_to' => 'user_id',
     *                  )]
     *
     */
    protected static $hasOne = [];
    protected static $belongsTo = [];
    protected static $hasMany = [];

    /**
     * @var array many to many relationships
     * 'songs' => array(
     *      'key_from' => 'playlist_id',
     *      'key_through_from' => 'playlist_id', // column 1 from the table in between, should match a posts.id
     *      'table_through' => 'playlist_songs', // both models plural without prefix in alphabetical order
     *      'key_through_to' => 'song_id', // column 2 from the table in between, should match a users.id
     *      'model_to' => 'Song',
     *      'key_to' => 'song_id',
     * )
     */
    protected static $manyMany = [];


    public function __construct($values = [])
    {
        $validator = \Validator::forge(get_called_class());
        $validator->addRules(static::$validationRules);

        unset($values[static::getUIDName()]);

        foreach ($values as $key => $value) {
            if (empty(static::$whitelist) && !in_array($key, static::$blacklist))
                $this->$key = $value;
            elseif (in_array($key, static::$whitelist))
                $this->$key = $value;
        }

        foreach (static::$defaultValues as $key => $val) {
            if (!isset($this->_data[$key]))
                $this->_data[$key] = $val;
        }
    }

    public static function getTable()
    {
        if (static::$table === null) {
            $table = strtolower(get_called_class());
            $parts = explode('\\', $table);
            $table = array_pop($parts);

            return $table . 's';
        }

        return static::$table;
    }

    public static function getUIDName()
    {
        return static::$uniqueIdentifier;
    }

    public static function findBy($column, $value = null, $operator = '=', $limit = null, $offset = 0)
    {
        if (!is_array($column))
            $column = [[$column, $operator, $value]];

        $config = [
            'limit' => $limit,
            'offset' => $offset,
            'where' => $column,
        ];

        return static::find($config);
    }

    public static function findOneBy($column, $value = null, $operator = '=')
    {
        if (!is_array($column))
            $column = [[$column, $operator, $value]];

        $config = [
            'limit' => 1,
            'where' => $column,
        ];

        $result = static::find($config);
        if ($result !== null)
            return $result[0];

        return null;
    }

    public static function findAll($limit = null, $offset = 0)
    {
        $config = [
            'limit' => $limit,
            'offset' => $offset
        ];

        return static::find($config);
    }

    public static function find($config = [], $key = null)
    {
        //var_dump(static::getTable());
        $query = \DB::select()->from(static::getTable())->asObject(get_called_class());

        $config = $config + array(
                'select' => array(static::getTable() . '.*'),
                'where' => array(),
                'order_by' => array(),
                'limit' => null,
                'offset' => 0,
            );

        extract($config);
        if (is_string($select))
            $select = [$select];

        $query->selectArray($select);

        if (!empty($where))
            $query->where($where);

        if (is_array($order_by)) {
            foreach ($order_by as $field => $direction)
                $query->orderBy($field, $direction);
        } else
            $query->orderBy($order_by);

        if ($limit !== null)
            $query = $query->limit($limit)->offset($offset);

        static::preQuery();
        static::preFind();

        $result = $query->execute(static::$connection);

        static::postFind();
        static::postQuery();

        $result = ($result->count() === 0) ? null : $result->asArray($key);

        return $result;
    }

    public function save($validate = true)
    {
        if ($validate) {
            $validator = \Validator::forge(get_called_class());
            $validator->validate($this->_data);

            if ($validator->errors())
                throw new \ValidationException($validator->errors());
        }

        $data = [];

        foreach (static::filterColumnNames() as $column) {
            $varName = $column;
            $columnName = $column;

            if (is_array($column)) {
                $columnName = key($column);
                $varName = $column[$columnName];
            }
            if (array_key_exists($varName, $this->_data)) {
                $data[$columnName] = $this->get($varName, false);
            }
        }

        if (array_key_exists(static::getUIDName(), $this->_data)) {
            $query = \DB::update(static::getTable())->set($data)->where(static::getUIDName(), '=', $this->getUID());

            static::preQuery();
            $this->preUpdate();

            $result = $query->execute(static::$connection);

            $this->postUpdate();
            static::postQuery();
        } else {
            $columns = static::getColumnNames();
            $relations = array_merge(static::$hasOne, static::$belongsTo);

            foreach($relations as $relation => $attributes) {
                $keyTo = $attributes['key_to'];
                $columns[] = $attributes['key_from'];
                $data[$attributes['key_from']] = $this->$relation->$keyTo;
            }

            $query = \DB::insert($this->getTable(), $columns)->values($data);

            static::preQuery();
            $this->preSave();

            $result = $query->execute(static::$connection);

            $this->postSave();
            static::postQuery();
        }

        return $result;
    }

    public function delete()
    {
        $query = \DB::delete(static::getTable())->where(static::getUIDName(), '=', $this->getUID());

        static::preQuery();
        $this->preDelete();

        $result = $query->execute(static::$connection);

        $this->postDelete();
        static::postQuery();


    }

    protected static function filterColumnNames($columns = [])
    {
        if (empty($columns) && empty(static::$columnNames))
            throw new \Exception('Model ' . get_called_class() . ' has no columns');
        elseif (empty($columns))
            $columns = static::$columnNames;

        if (($key = array_search(static::getUIDName(), $columns)) !== false) {
            unset($columns[$key]);
        }

        return $columns;
    }

    protected static function getColumnPropertyNames($columns = [])
    {
        $columns = static::filterColumnNames($columns);

        foreach ($columns as $k => $v) {
            if (is_array($v)) {
                $columns[$k] = array_shift($v);
            }
        }

        return $columns;
    }

    protected static function getColumnNames($columns = [])
    {
        $columns = static::filterColumnNames($columns);

        foreach ($columns as $k => $v) {
            if (is_array($v)) {
                $columns[$k] = key($v);
            }
        }

        return $columns;
    }

    public function setRelation($name, $object)
    {
        $singleRelations = array_merge(static::$belongsTo, static::$hasOne);
        $manyRelations = array_merge(static::$manyMany, static::$hasMany);
        if (array_key_exists($name, $singleRelations)) {
            $this->$name = $object;
            return true;
        } elseif (array_key_exists($name, $manyRelations)) {
            if (!in_array($object, $this->_data[$name]))
                $this->_data[$name][] = $object;
            return true;
        }

        return false;
    }

    public function __set($key, $value)
    {
        if ($this->setRelation($key, $value) === true)
            return;

        foreach (static::$columnNames as $k => $v) {
            if (is_array($v) && array_key_exists($key, $v)) {
                $key = $v[$key];
                break;
            }
        }

        $this->_data[$key] = $value;
    }

    public function getRelation($name)
    {
        if(isset($this->_data[$name]))
            return $this->_data[$name];

        if (array_key_exists($name, static::$belongsTo)) {
            $config = [
                'key_from' => substr(static::getTable(), 0, -1) . '_id',
                'model_to' => '\\App\\Model\\' . ucfirst($name),
                'key_to' => 'id'
            ];

            $config = static::$belongsTo[$name] + $config;

            $modelClass = $config['model_to'];
            $keyTo = $config['key_to'];
            $keyFrom = $config['key_from'];

            $model = $modelClass::findOneBy($keyTo, $this->$keyFrom);
            $this->_data[$name] = $model;
            return $model;
        }

        elseif (array_key_exists($name, static::$hasOne)) {
            $config = [
                'key_from' => 'id',
                'model_to' => '\\App\\Model\\' . ucfirst($name),
                'key_to' => substr(static::getTable(), 0, -1) . '_id',
            ];

            $config = static::$hasOne[$name] + $config;

            $modelClass = $config['model_to'];
            $keyTo = $config['key_to'];
            $keyFrom = $config['key_from'];

            $model = $modelClass::findOneBy($keyTo, $this->$keyFrom);
            $this->_data[$name] = $model;
            return $model;
        }

        elseif (array_key_exists($name, static::$hasMany)) {
            $config = [
                'key_from' => 'id',
                'model_to' => '\\App\\Model\\' . ucfirst($name),
                'key_to' => substr(static::getTable(), 0, -1) . '_id',
            ];
            $config = static::$hasMany[$name] + $config;

            $modelClass = $config['model_to'];
            $keyTo = $config['key_to'];
            $keyFrom = $config['key_from'];

            $models = $modelClass::findBy($keyTo, $this->$keyFrom);
            $this->_data[$name] = $models;
            return $models;
        }
        elseif (array_key_exists($name, static::$manyMany)) {
            $config = [
                'key_from' => 'id',
                'model_to' => '\\App\\Model\\' . ucfirst($name),
                'key_to' => substr(static::getTable(), 0, -1) . '_id',
            ];
            $config = static::$manyMany[$name] + $config;

            $modelClass = $config['model_to'];
            $keyTo = $config['key_to'];
            $keyFrom = $config['key_from'];

//            $query = \Db::select()->from($modelClass::getTable())
//                                  ->join($config['table_through'])
//                                  ->on($config['table_through'] . '.' . $config['key_through_to'], '=', $modelClass::getTable() . '.' . $keyTo)
//                                  ->join(static::getTable())
//                                  ->on($config['table_through'] . '.' . $config['key_through_from'], '=', static::getTable() . '.' . $config['key_from'])
//                                  ->where(static::getTable() . '.' . $keyTo, $this->$keyTo)
//                                  ->asObject($modelClass);

            $fields = [$config['additional_columns']];
            $fields[0][] = $config['key_through_to'];
            $ids = \DB::select($fields)->from($config['table_through'])->where($config['key_through_from'], $this->$keyFrom)->execute()->asArray();

            if(empty($ids))
                return $ids;

            $query = \DB::select()->from($modelClass::getTable());
            foreach($ids as $id)
                $query = $query->orWhere($keyTo, '=', $id[$config['key_through_to']]);

            $query->asObject($config['model_to']);
            $models = $query->execute()->asArray();

            $i = 0;
            foreach($models as $model) {
                foreach($config['additional_columns'] as $column) {
                    if(is_array($column))
                        $column = $column[1];
                    $model->$column = $ids[$i++][$column];
                }
            }

            $this->_data[$name] = $models;
            return $models;
        }

        return false;
    }

    public function toArray()
    {
        return $this->_data;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function get($key, $filter = true)
    {
        if(false !== ($model = $this->getRelation($key)))
            return $model;

        foreach (static::$columnNames as $k => $v) {
            if (is_array($v) && in_array($key, $v)) {
                $key = array_shift($v);
                break;
            }
        }

        if (array_key_exists($key, $this->_data)) {
            if ($filter === true && !is_object($this->_data[$key])) {
                $filter = Config::get('security.output_filter', 'htmlentities');
                return $filter($this->_data[$key]);
            }

            return $this->_data[$key];
        }

        return null;
    }

    public function getUID()
    {
        $var = static::getUIDName();
        return $this->$var;
    }

    protected static function preQuery()
    {
    }

    protected static function postQuery()
    {
    }

    protected static function preFind()
    {
    }

    protected static function postFind()
    {
    }

    protected function preSave()
    {
    }

    protected function postSave()
    {
    }

    protected function preUpdate()
    {
    }

    protected function postUpdate()
    {
    }

    protected function preDelete()
    {
    }

    protected function postDelete()
    {
    }
}