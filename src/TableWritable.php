<?php
/**
 * Author: ingwar1991
 */

namespace ingwar1991\Norm;


abstract class TableWritable extends TableReadable {
    // for this type of tables we have to require index field
    protected function getTableIndexField() {
        if (!$this->tableIndexField) {
            throw new \Exception("No index field specified!");
        }

        return parent::getTableIndexField(); 
    }

    protected function getTableRequiredFields($forFieldsList = false, $alias = false) {
        $requiredFields = parent::getTableRequiredFields($forFieldsList, $alias);
        if (!count($requiredFields)) {
            throw new \Exception("TableWritable has to have required fields");
        }

        return $requiredFields;
    }

    protected function checkEntityRequiredFields($entityData, $ignoreIndexField = false) {
        foreach ($this->getTableRequiredFields() as $reqField) {
            if ($ignoreIndexField && $reqField == $this->getTableIndexField()) {
                continue;
            }

            if (empty($entityData[$reqField])) {
                throw new \Exception("Entity doesn't have all required fields");
            }
        }

        return true;
    }

    private function processTableInsertStmtValue($fieldName, $fieldValue, &$params, &$insertValuesStmt, $key = false) {
        $valueStmt = 'null';

        if ($fieldValue === null || $fieldValue === false || $fieldValue === '`null`') {
            if ($fieldName == $this->getTableIndexField()) { // if null transmitted for index field - skip
                return false;
            }
        } else {
            $fieldValName = $this->getFieldValueName($fieldName, $params);

            $params[$fieldValName] = $fieldValue;
            $valueStmt = ':' . $fieldValName;
        }

        if ($key !== false) {
            $insertValuesStmt[$key][] = $valueStmt;
        } else {
            $insertValuesStmt[] = $valueStmt;
        }

        return true;
    }

    // TODO: separate this into main and secondary method, that should be called in a loop at bunch=true case
    protected function getTableInsertStatements($searchData, $alias = false, $bunch = false) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();
        $availableFields = array_map(
            function($element) use ($alias) {
                return str_replace("{$alias}.", '', $element);
            }, 
            $this->getTableFields(false, $alias)
        );

        $fieldsList = [];
        $insertFieldsStmt = [];
        $insertValuesStmt = [];
        $params = [];

        if ($bunch) {
            $firstKey = array_keys($searchData);
            $firstKey = reset($firstKey);

            foreach($searchData as $key => $insertData) {
                $i = 0;
                foreach($insertData as $fieldName => $fieldValue) {
                    if (!isset($availableFields[$fieldName])) {
                        continue;
                    }

                    if ($key == $firstKey) {
                        $insertFieldsStmt[$i] = $availableFields[$fieldName];
                    } else if ($insertFieldsStmt[$i] != $availableFields[$fieldName]) {
                        // if insert statement structure differs from the first one - set all to NULL and stop processing
                        $insertFieldsStmt = [];
                        $insertValuesStmt = [];
                        $params = [];
                        $fieldsList = [];

                        break 2;
                    }

                    $this->processTableInsertStmtValue(
                        $fieldName,
                        $fieldValue,
                        $params,
                        $insertValuesStmt,
                        $key
                    );

                    $fieldsList[] = $this->getTableFields($fieldName, $alias);

                    $i++;
                }

                if (count($insertFieldsStmt) != count($insertValuesStmt[$key])) {
                    $insertFieldsStmt = [];
                    $insertValuesStmt = [];
                    $fieldsList = [];
                    $params = [];

                    break 1;
                }
            }
        } else {
            foreach($searchData as $fieldName => $fieldValue) {
                if (isset($availableFields[$fieldName])) {
                    $success = $this->processTableInsertStmtValue(
                        $fieldName,
                        $fieldValue,
                        $params,
                        $insertValuesStmt
                    );

                    if ($success) {
                        $insertFieldsStmt[] = $availableFields[$fieldName];
                        $fieldsList[] = $this->getTableFields($fieldName, $alias);
                    }
                }
            }
        }

        if ($bunch) {
            $insertValuesStmt = ' (' . implode('), (', array_map(function($el) {
                    return implode(',', $el);
                }, $insertValuesStmt)) . ') ';
        } else {
            $insertValuesStmt = ' (' . implode(',', $insertValuesStmt) . ') ';
        }

        return [
            'statements' => count($insertFieldsStmt)
                ? ' (' . implode(',', $insertFieldsStmt) . ') values ' . $insertValuesStmt
                : false,
            'params' => $params,
            'fields' => $fieldsList
       ];
    }

    protected function addTableInsertStatements(&$sql, $searchData, &$params, $alias = false, $bunch = false, $onDuplicateUpdate = false) {
        $insertStmts = $this->getTableInsertStatements($searchData, $alias, $bunch);
        if (!$insertStmts['statements']) {
            return false;
        }

        $sql .= $insertStmts['statements'];
        $params = array_merge($params, $insertStmts['params']);

        if ($onDuplicateUpdate && !empty($insertStmts['fields'])) {
            $dupUpdStmt = [];
            foreach($insertStmts['fields'] as $field) {
                $dupUpdStmt[] = "{$field} = values({$field})";
            }

            $sql .= '
                on duplicate key update
            ' . implode(",\n", $dupUpdStmt);
        }

        return true;
    }

    protected function getLastInsertedId() {
        return $this->exec('select last_insert_id();')->fetchColumn();
    }

    public function add($entityData) {
        $this->checkEntityRequiredFields($entityData, true);

        $params = [];

        $sql = <<<SQL
          insert into
            {$this->getTableName()} 
SQL;
        if (!$this->addTableInsertStatements($sql, $entityData, $params)) {
            return false;
        }

        if ($this->exec($sql, $params)->errorCode() > 0) {
            return false;
        }
        $entityData[$this->getTableIndexField()] = $this->getLastInsertedId();

        return $entityData;
    }
}
