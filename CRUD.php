<?php
require_once dirname(__FILE__) . "/MysqliDb.php";

class CRUD {

    public $id;

    public static $relations;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param bool $useTransaction
     * @throws Exception
     */
    function save($useTransaction = true) {
        $db = MysqliDb::getInstance();
        $tableName = get_class($this);
        $data = array();
        $props = get_object_vars($this);
        if($useTransaction) {
            $db->startTransaction();
        }
        foreach ($props as $prop => $prop_value) {
            if($prop_value instanceof CRUD)
            {
                $className = get_class($prop_value);
                if(isset(static::$relations[$className]))
                {
                    if(empty($prop_value->{static::$relations[$className][0]})) {
                        if(!empty($props[static::$relations[$className][1]])) {
                            $prop_value->{static::$relations[$className][0]} = $props[static::$relations[$className][1]];
                        }
                    }
                    $prop_value->save(false);
                    $this->{static::$relations[$className][1]} = $prop_value->{static::$relations[$className][0]};
                }
            }
        }
        $props = get_object_vars($this);
        foreach ($props as $prop => $prop_value) {
            if($prop != "id" && isset($prop_value) && !$prop_value instanceof CRUD) {
                $data[$prop] = $prop_value;
            }
        }
        if(count($data) > 0) {
            $success = false;
            if(empty($this->id)) {
                $id = $db->insert($tableName, $data);
                if ($id) {
                    $success = true;
                    $this->id = $id;
                }
            } else {
                $db->where('id', $this->getId());
                $success = $db->update($tableName, $data);
            }

            if($useTransaction) {
                if($success) {
                    $db->commit();
                } else {
                    $db->rollback();
                }
            }
            if(!$success) {
                throw new Exception($db->getLastError(), $db->getLastErrno());
            }
        }
    }



    /**
     * @param object $object
     * @param bool $includeWhere
     * @return array
     */
    private static function getRelatedObjects($object, $includeWhere = true) {
        $cols = array();
        $db = MysqliDb::getInstance();
        $props = get_object_vars($object);
        $className = get_class($object);
        foreach ($props as $prop => $prop_value) {
            if(isset($prop_value)) {
                if($prop_value instanceof CRUD) {
                    $cols = array_merge($cols , self::getRelatedObjects($prop_value, $includeWhere));
                } else if($includeWhere) {
                    $db->where($className . '.' . $prop, $prop_value);
                }
            }
            if(!$prop_value instanceof CRUD) {
                array_push($cols, $className . '.' . $prop . ' as ' . $className . $prop);
            }
        }
        return $cols;
    }

    /**
     * @param array $data
     */
    private function fillObjects($data) {
        $props = get_object_vars($this);
        $className = get_class($this);
        foreach ($props as $prop => $prop_value) {
            if($prop_value instanceof CRUD) {
                $prop_value->fillObjects($data);
            } else {
                $this->{$prop} = $data[$className . $prop];
            }
        }
    }

    /**
     * @param string $className
     * @throws Exception
     */
    private static function getJoins($className) {
        $props = get_class_vars($className);
        $db = MysqliDb::getInstance();
        if(isset($props["relations"])) {
            foreach ($props["relations"] as $joinTable => $joinCondition) {
                static::getJoins($joinTable);
                $db->join($joinTable, $className . '.' .$joinCondition[1] . '=' . $joinTable . '.' .$joinCondition[0]);
            }
        }
    }

    /**
     * @throws Exception
     */
    function read() {
        $db = MysqliDb::getInstance();
        $tableName = get_class($this);
        $cols = $this->getRelatedObjects($this);
        $this->getJoins($tableName);
        $result = $db->getOne($tableName, $cols);
        if (empty($db->getLastErrno())) {
            $this->fillObjects($result);
        }
        else {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
    }

    /**
     * @throws Exception
     */
    function delete() {
        $db = MysqliDb::getInstance();
        $tableName = get_class($this);
        $db->where('id', $this->getId());
        $result = $db->delete($tableName);
        if(!$result) {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
    }

    /**
     * @param string $whereProp
     * @param string $whereValue
     * @param string $operator
     * @param bool $checkEmpty
     * @param string $cond
     * @return $this
     */
    static function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $checkEmpty = true, $cond = 'AND') {
        if($checkEmpty && empty($whereValue)) {
            return new static;
        }
        MysqliDb::getInstance()->where($whereProp, $whereValue, $operator, $cond);
        return new static;
    }

    /**
     * @param string $whereProp
     * @param string $whereValue
     * @param string $operator
     * @param bool $checkEmpty
     * @return $this
     */
    static function orWhere($whereProp, $whereValue = 'DBNULL', $operator = '=', $checkEmpty = true) {
        return self::where($whereProp, $whereValue, $operator, $checkEmpty, 'OR');
    }

    /**
     * @param string $orderByField
     * @param string $orderByDirection
     * @param null $customFieldsOrRegExp
     * @return static
     * @throws Exception
     */
    static function orderBy($orderByField, $orderByDirection = "DESC", $customFieldsOrRegExp = null) {
        MysqliDb::getInstance()->orderBy($orderByField, $orderByDirection, $customFieldsOrRegExp);
        return new static;
    }

    /**
     * @return $this[]
     * @throws Exception
     */
    static function read_all() {
        $db = MysqliDb::getInstance();
        $classObjects = array();
        $tableName = get_called_class();
        $r = new ReflectionClass($tableName);
        $object = $r->newInstanceArgs();
        $cols = self::getRelatedObjects($object, false);
        self::getJoins($tableName);
        $results = $db->get($tableName, null, $cols);
        if (empty($db->getLastErrno())) {
            foreach ($results as $row) {
                $objInstance = $r->newInstanceArgs();
                $objInstance->fillObjects($row);
                array_push($classObjects, $objInstance);
            }
        } else {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
        return $classObjects;
    }

    /**
     * @throws Exception
     */
    static function delete_all() {
        $db = MysqliDb::getInstance();
        $tableName = get_called_class();
        $result = $db->delete($tableName);
        if(!$result) {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
    }

    /**
     * @return int
     * @throws Exception
     */
    static function count(){
        $db = MysqliDb::getInstance();
        $tableName = get_called_class();
        $count = $db->getValue($tableName, "count(*)");
        if(!empty($db->getLastErrno())) {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
        return $count;
    }

    /**
     * @param string $field
     * @return int
     * @throws Exception
     */
    static function sum($field){
        $db = MysqliDb::getInstance();
        $tableName = get_called_class();
        $result = $db->getValue($tableName, "sum({$field})");
        if(!empty($db->getLastErrno())) {
            throw new Exception($db->getLastError(), $db->getLastErrno());
        }
        return $result;
    }
}