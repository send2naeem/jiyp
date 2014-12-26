<?php

class BaseModel
{

    protected static $db;
    protected static $instances = array();

    public function __construct($db)
    {
        if (!empty($db))
            self::$db = $db;
        else if (empty(self::$db))
            self::$db = getDB();
    }

    public static function setDB($db)
    {
        self::$db = $db;
    }

    public static function model()
    {
        $modelClass = get_called_class();
        if (!key_exists($modelClass, self::$instances))
            self::$instances[$modelClass] = new $modelClass();

        return self::$instances[$modelClass];
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    final public function findByAttributes($attributes, $orderORfieldsToFind = NULL, $fieldsToFind = NULL)
    {
        $queryResponse = $this->createSql("SELECT", $attributes, $orderORfieldsToFind, 1, 0, $fieldsToFind);
        return $this->findBySql($queryResponse['sql'], $queryResponse['params']);
    }

    final public function findAllByAttributes($attributes, $orderORfieldsToFind = NULL, $limit = NULL, $offset = 0, $fieldsToFind = NULL)
    {
        $queryResponse = $this->createSql("SELECT", $attributes, $orderORfieldsToFind, $limit, $offset, $fieldsToFind);
        return $this->findAllBySql($queryResponse['sql'], $queryResponse['params']);
    }

    final public function findBySql($sql, $params = array())
    {
        return $this->executeSql($sql, $params, true, true);
    }

    final public function findAllBySql($sql, $params = array())
    {
        return $this->executeSql($sql, $params, true);
    }

    final public function findAll($orderORfieldsToFind = NULL, $limit = NULL, $offset = 0, $fieldsToFind = NULL)
    {
        return $this->findAllByAttributes(NULL, $orderORfieldsToFind, $limit, $offset, $fieldsToFind);
    }

    final public function executeSql($query, $params = array(), $fetchRecord = true, $fetchSingle = false, $fetchMod = \PDO::FETCH_ASSOC)
    {
        try
        {
            $statement = self::$db->prepare($query);
            $statement->execute($params);
            $errorInfo = $statement->errorInfo();

            if (!empty($errorInfo[1]))
                return array('error' => array('errorInfo' => $errorInfo, 'query' => $query, 'params' => $params));

            if ($fetchRecord)
            {
                if ($fetchSingle)
                {
                    $row = $statement->fetch($fetchMod);
                    return $row ? $row : NULL;
                }

                $records = array();

                while ($row = $statement->fetch($fetchMod))
                {
                    $records[] = $row;
                }

                return $records;
            }

            $query = trim(strtolower($query));
            if (strrpos($query, 'insert', -strlen($query)) !== false)
                return self::$db->lastInsertId();

            return $statement->rowCount();
        }
        catch (PDOException $exception)
        {
            throw $exception;
        }
    }

    public function delete($attributes = NULL, $softDelete = false)
    {
        if ($softDelete)
        {
            return $this->update(array('trashed' => 1), $attributes);
        }

        $queryResponse = $this->createSql('DELETE', $attributes);
        return $this->executeSql($queryResponse['sql'], $queryResponse['params'], false);
    }

    public function add($values)
    {
        $queryResponse = $this->createSql('INSERT', NULL, $values);
        return $this->executeSql($queryResponse['sql'], $queryResponse['params'], false);
    }

    public function update($values, $attributes = NULL)
    {
        $queryResponse = $this->createSql('UPDATE', $attributes, $values);
        return $this->executeSql($queryResponse['sql'], $queryResponse['params'], false);
    }

    private function createSql($clause, $attributes, $orderORfieldsToFind = NULL, $limit = NULL, $offset = 0, $fieldsToFind = NULL)
    {
        if (is_array($orderORfieldsToFind))
        {
            $fieldsToFind = $orderORfieldsToFind;
            $order = NULL;
        }
        else
        {
            $order = $orderORfieldsToFind;
        }
        if (empty($fieldsToFind))
        {
            $fieldsToFind = array_keys($this->relationMap);
        }
        $params = array();
        if (is_array($attributes))
        {
            $condition = "";
            foreach ($attributes as $attr => $value)
            {
                $condition .= " `" . $attr . "` ";
                if (is_array($value))
                {
                    $condition .= "IN (";
                    foreach ($value as $index => $attrVal)
                    {
                        $condition .= ":{$attr}_{$index}, ";
                        $params[":{$attr}_{$index}"] = $attrVal;
                    }
                    $condition = rtrim($condition, ', ');
                    $condition .= ")";
                }
                else if ($value === NULL)
                {
                    $condition .= " IS NULL ";
                }
                else
                {
                    $condition .= " = :{$attr} ";
                    $params[":{$attr}"] = $value;
                }

                $condition .= " AND ";
            }
            $condition = substr($condition, 0, strlen($condition) - 4);
        }

        switch (strtoupper($clause))
        {
            case 'SELECT' :
                $sql = "SELECT ";

                if (is_array($fieldsToFind))
                {
                    foreach ($fieldsToFind as $fieldName)
                    {
                        $fieldNameAndAlias = explode(' as ', $fieldName);
                        $fieldName = $fieldNameAndAlias[0];
                        @$fieldAlias = $fieldNameAndAlias[1];
                        $sql .= "`" . $fieldName . "` " . (empty($fieldAlias) ? "" : " as `" . $fieldAlias . "`") . ", ";
                    }
                    $sql = substr($sql, 0, strlen($sql) - 2);
                }
                else
                {
                    $sql .= $fieldsToFind;
                }

                $sql .= " FROM `" . $this->tableName . "` ";
                if (!empty($limit))
                {
                    $limitStr = " LIMIT {$limit} OFFSET {$offset} ";
                }
                if ($order)
                {
                    $orderStr = " ORDER BY {$order} ";
                }
                break;

            case 'DELETE' :
                $sql = "DELETE FROM `{$this->tableName}` ";
                break;

            case 'UPDATE' :
                $sql = "UPDATE `{$this->tableName}` SET ";
                $fieldsToUpdate = $fieldsToFind;
                foreach ($fieldsToUpdate as $fieldName => $value)
                {
                    if ($value === NULL)
                    {
                        $sql .= "`{$fieldName}` = NULL ";
                    }
                    else
                    {
                        $sql .= "`{$fieldName}` = :{$fieldName} ";
                        $params[":{$fieldName}"] = $value;
                    }
                    $sql .= ", ";
                }
                $sql = substr($sql, 0, strlen($sql) - 2);
                break;

            case 'INSERT' :
                $sql = "INSERT INTO `{$this->tableName}` (";
                $valuesString = "";
                $fieldsToAdd = $fieldsToFind;
                foreach ($fieldsToAdd as $fieldName => $value)
                {
                    $params[":{$fieldName}"] = $value;
                    $sql .= "`{$fieldName}`, ";
                    $valuesString .= ":{$fieldName}, ";
                }
                $sql = substr($sql, 0, strlen($sql) - 2);
                $valuesString = substr($valuesString, 0, strlen($valuesString) - 2);
                $sql .= ") VALUES({$valuesString})";
                break;
        }
        if (isset($condition))
            $sql .= " WHERE " . $condition;
        if (isset($orderStr))
        {
            $sql .= $orderStr;
        }
        if (isset($limitStr))
        {
            $sql .= $limitStr;
        }

        return array('sql' => $sql, 'params' => $params);
    }

    public function isValidFile($file, $validators, &$errors)
    {
        $errors = array();
        $allowedTypes = getParam('allowedTypes', array(), $validators);
        $allowedExtensions = getParam('allowedExtensions', array(), $validators);
        $allowedMinSize = getParam('allowedMinSize', NULL, $validators);
        $allowedMaxSize = getParam('allowedMaxSize', NULL, $validators);
        $fileName = getParam('name', '', $file);
        $fileTempName = getParam('tmp_name', '', $file);
        $fileSize = getParam('size', NULL, $file);

        if (empty($fileName) || empty($fileSize) || empty($fileTempName))
        {
            $errors['noFileProvided'] = 'No file provided';
            return false;
        }

        $fileType = strtolower(mime_content_type($fileTempName));
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!empty($allowedExtensions) && !in_array($fileExtension, $allowedExtensions))
            $errors['extension'] = 'File extension \'.' . $fileExtension . '\' is not acceptable';

        if (!empty($allowedTypes) && !in_array($fileType, $allowedTypes))
            $errors['type'] = 'File type \'' . $fileType . '\' is not acceptable';

        if ($allowedMinSize !== NULL && $fileSize < $allowedMinSize)
            $errors['minSize'] = 'Minimum acceptable size is ' . ($allowedMinSize / 1024) . ' MB';

        if ($allowedMaxSize !== NULL && $fileSize > $allowedMaxSize)
            $errors['maxSize'] = 'Maximum acceptable size is ' . ($allowedMaxSize / 1024) . ' MB';

        return empty($errors);
    }

    public function saveUploadedFile($file, $filters = NULL)
    {
        $fileTempName = getParam('tmp_name', '', $file);
        $fileName = getParam('name', '', $file);
        $fileName = getParam('rename', $fileName, $filters);
        $maxNameLength = getParam('limitFileName', NULL, $filters);
        if ($maxNameLength !== NULL)
            $fileName = shortenFileName($fileName, $maxNameLength);
        $uploadDirectory = getParam('uploadDirectory', UPLOAD_DIRECTORY, $filters);
        $destination = $uploadDirectory . DS . $fileName;
        if (makePath($destination))
        {
            if (move_uploaded_file($fileTempName, $destination))
                return $fileName;
        }

        return false;
    }

}

?>
