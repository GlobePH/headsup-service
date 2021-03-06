<?php

namespace App\Repositories;

use Log, DB;

class BaseRepository
{
    protected $modelName;

    /**
     * Map parameters and args values
     *
     * @param  array $params Default parameters
     * @param  array $args Overriding values
     * @return array Parameters with args values
     */
    protected function setParams($params, $args)
    {
        foreach ($args as $i => $value) {
            if (isset($args[$i])) {
                $params[$i] = $value;
            }
        }

        return $params;
    }

    /**
    * Create an instance for the model
    *
    * @access public
    * @return void
    */
    protected function getNewInstance()
    {
        $model = $this->modelName;
        return new $model;
    }

     /**
     * Checks if table record already exists.
     *
     * @param  array|string $where Where clause
     * @param int | id
     * @return boolean True if record already exists, false for non-existent record
     */
    public function exists(array $where, $id = 0)
    {
        $instance = $this->getNewInstance();
        $instance = $instance->where($where);

        if ($id != 0) $instance = $instance->where('id', '!=', $id);

        return $instance->exists();
    }

    /**
    * Get table record by primary key
    *
    * @access public
    * @params int | $id
    * @params array | columns
    * @return void
    */
    public function get($id = 0, $columns = array('*'))
    {
        return $this->getNewInstance()->select($columns)->find($id);
    }

    /**
     * Get table record using a generic where clause
     *
     * @param  array|string $where Where clause
     * @param  string $select Fields to include in select
     * @return object Table record
     */
    public function getBy(array $where, $select = ['*'], $join = [])
    {
        $instance = $this->getNewInstance();
        $instance = $instance->select($select);

        if ( ! empty($where)) {
            foreach($where as $field => $value) {
                $instance = $instance->where($field, $value);
            }
        }

        if ( ! empty($join))
        {
            foreach ($join as $row)
            {
                if (empty($row['operator'])) $row['operator'] = '=';
                if (empty($row['join'])) $row['join'] = '';

                switch ($row['join']) {
                    case 'left':
                        $instance = $instance->leftJoin($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;

                    default:
                        $instance = $instance->join($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;
                }
            }
        }

        return $instance->get()->first();
    }

    /**
    * Get record in DB by its column name
    *
    * @access public
    * @params string | name
    * @return void
    */
    public function getByName($name)
    {
        $instance = $this->getNewInstance();
        return $instance->ofName($name);
    }

    /**
    * Get record in DB by using query clauses
    *
    * @access public
    * @params array | params
    * @return void
    */
    public function getRecord(array $params)
    {
        if ( ! isset($params['select']))  $params['select'] = array('*');
        if ( ! isset($params['where']))  $params['where'] = array('*');
        if ( ! isset($params['join']))  $params['join'] = array('*');
        if ( ! isset($params['order_by']))  $params['order_by'] = array('*');
        if ( ! isset($params['group_by']))  $params['group_by'] = array('*');

        $instance = $this->getNewInstance();

        foreach($params['where'] as $field => $value) {
            $instance = $instance->where($field, $value);
        }

        return $instance->get($params['select'])->first();
    }

    /**
     * Get table list by building query.
     *
     * @param  array $args SQL clauses
     * @return object Result list or resource
     */
    public function getList(array $args)
    {
        $params = [
            'select' => null,
            'selectRaw' => null,
            'where' => null,
            'join' => [],
            'group_by' => null,
            'groupby_raw' => null,
            //'having' => null,
            'order_by' => null,
            'offset' => null,
            'limit' => null,
        ];

        $params = $this->setParams($params, $args);
        $instance = $this->getNewInstance();

        if ( ! empty($params['select'])) $instance = $instance->select($params['select']);

        if ( ! empty($params['selectRaw'])) $instance = $instance->select(DB::raw($params['selectRaw']));

        if ( ! empty($params['where']))
        {
            foreach ($params['where'] as $i => $row)
            {
                switch ($i)
                {
                    case 'or':
                        foreach ($row as $key) {
                            if (! isset($key['operator'])) $key['operator'] = '=';
                            $instance = $instance->orWhere($key['field'], $key['operator'], $key['value']);
                        }
                        break;

                    case 'between':
                        foreach ($row as $field => $values)
                        {
                            $instance = $instance->whereBetween($field, $values);
                        }
                        break;

                    case 'and':
                        $instance = $instance->where(function ($query) use ($row){

                            foreach ($row as $key) {

                                if (isset($key['raw']))
                                {
                                    $query->whereRaw($key['raw']);
                                    continue;
                                }

                                if (! isset($key['operator'])) $key['operator'] = '=';
                                $query->where($key['field'], $key['operator'], $key['value']);
                            }
                        });
                        break;
                    default:
                        $instance = $instance->where($row);
                        break;
                }
            }
        }

        if ( ! empty($params['join']))
        {
            foreach ($params['join'] as $row)
            {
                if (empty($row['operator'])) $row['operator'] = '=';
                if (empty($row['join'])) $row['join'] = '';

                switch ($row['join']) {
                    case 'left':
                        $instance = $instance->leftJoin($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;

                    default:
                        $instance = $instance->join($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;
                }
            }
        }

        if ( ! empty($params['order_by']))
        {
            foreach ($params['order_by'] as $key => $value)
            {
                $instance = $instance->orderBy($key, $value);
            }
        }

        if ( ! empty($params['group_by'])) $instance = $instance->groupBy($params['group_by']);

        if ( ! empty($params['groupby_raw'])) $instance = $instance->groupBy(DB::raw($params['groupby_raw']));

        if ( ! empty($params['offset'])) $instance = $instance->skip($params['offset']);

        if ( ! empty($params['limit'])) $instance = $instance->take($params['limit']);

        return $instance->get();
    }

    /**
    * Get lists using where clause
    *
    * @access public
    * @params array | fieldValue
    * @params array | columns
    * @return void
    */
    public function getListWhere(array $fieldValue, $columns = array('*'))
    {
        $instance = $this->getNewInstance();

        foreach ($where as $i => $row)
        {
            switch ($i)
            {
                case 'or':
                    foreach ($row as $key) {
                        if (! isset($key['operator'])) $key['operator'] = '=';
                        $instance = $instance->orWhere($key['field'], $key['operator'], $key['value']);
                    }
                    break;

                case 'between':
                    foreach ($row as $field => $values)
                    {
                        $instance = $instance->whereBetween($field, $values);
                    }
                    break;

                case 'and':
                    $instance = $instance->where(function ($query) use ($row){

                        foreach ($row as $key) {

                            if (isset($key['raw']))
                            {
                                $query->whereRaw($key['raw']);
                                continue;
                            }

                            if (! isset($key['operator'])) $key['operator'] = '=';
                            $query->where($key['field'], $key['operator'], $key['value']);
                        }
                    });
                    break;
                default:
                    $instance = $instance->where($row);
                    break;
            }
        }

        return $instance->get($columns);
    }

    /**
     * Get total number of records.
     *
     * @param  array|string $where Where clause
     * @param  array $join Tables and conditions for joining
     * @param  string $field Field to count
     * @return integer Total number of records
     */
    public function getTotalBy($where = array(), $join = [])
    {
        $instance = $this->getNewInstance();
        $instance = $instance->select('COUNT(id) total');

        if (! empty($where)) {
            foreach ($where as $i => $row) {
                switch ($i) {
                    case 'or':
                        foreach ($row as $key) {
                            if (! isset($key['operator'])) $key['operator'] = '=';
                            $instance = $instance->orWhere($key['field'], $key['operator'], $key['value']);
                        }
                        break;

                    case 'between':
                        foreach ($row as $field => $values) {
                            $instance = $instance->whereBetween($field, $values);
                        }
                        break;

                    case 'and':
                        $instance = $instance->where(function ($query) use ($row){
                            foreach ($row as $key) {

                                if (isset($key['raw']))
                                {
                                    $query->whereRaw($key['raw']);
                                    continue;
                                }

                                if (! isset($key['operator'])) $key['operator'] = '=';
                                $query->where($key['field'], $key['operator'], $key['value']);
                            }
                        });

                        break;
                    default:
                        $instance = $instance->where($row);
                        break;
                }
            }
        }

        if ( ! empty($join))
        {
            foreach ($join as $row)
            {
                if (empty($row['operator'])) $row['operator'] = '=';
                switch ($row['join']) {
                    case 'left':
                        $instance = $instance->leftJoin($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;

                    default:
                        $instance = $instance->join($row['table'], $row['one'], $row['operator'], $row['two']);
                        break;
                }
            }
        }

        return $instance->count();
    }

    /**
     * Get the User Status - A, I
     *
     * @access public
     * @return void
     */
    public function getAIStatus()
    {
        $instance = $this->getNewInstance();
        $instance = $instance->where('type', 'status');
        $instance = $instance->where('value', '<>', 'B');
        return $instance->get();
    }

    /**
    * Insert data in the database
    *
    * @access public
    * @params array | data
    * @return void
    */
    public function store(array $data)
    {
        $instance = $this->getNewInstance();
        return $instance->create($data);
    }

    /**
    * Update record by using primary id
    *
    * @access public
    * @params int | id
    * @params array | data
    * @return void
    */
    public function update($id = 0, array $data)
    {
        $instance = $this->getNewInstance()->find($id);
        return $instance->update($data);
    }

    /**
    * Update record by using where clause
    *
    * @access public
    * @params array | fieldValue
    * @params array | data
    * @return void
    */
    public function updateBy(array $fieldValue, array $data)
    {
        $instance = $this->getNewInstance();

        foreach($fieldValue as $field => $value) {

            switch ($field) {
                case 'and':
                    $instance = $instance->where(function ($query) use ($value){
                        foreach ($value as $key) {
                            if (isset($key['raw']))
                            {
                                $query->whereRaw($key['raw']);
                                continue;
                            }
                        }
                    });
                    break;

                default:
                    $instance = $instance->where($field, $value);
                    break;
            }
        }

        return $instance->update($data);
    }

    /**
     * Delete table record by its primary key.
     *
     * @param  integer $id Record ID
     * @return boolean|integer False for failed delete or number of affected rows
     */
    public function delete($id = 0)
    {
        $instance = $this->getNewInstance();
        $instance = $instance->find($id);
        return $instance->delete();
    }
}
