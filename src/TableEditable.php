<?php
/**
 * Author: ingwar1991
 */

namespace ingwar1991\Norm;


abstract class TableEditable extends TableWritable {
    protected function getTableUpdateStatements($searchData, $alias = false, $loopKey = false) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();
        $loopKey = $loopKey !== false
            ? $loopKey + 0
            : false;
        $availableFields = array_map(
            function($element) use ($alias) {
                return str_replace("{$alias}.", '', $element);
            }, 
            $this->getTableFields(false, $alias)
       );

        $updateStmt = [];
        $params = [];
        foreach($searchData as $fieldName => $fieldValue) {
            if (isset($availableFields[$fieldName]) && $fieldValue !== false) {
                if ($fieldValue === null || $fieldValue === '') {
                    $updateStmt[] = "{$availableFields[$fieldName]} = null";
                }
                else {
                    $fieldValName = $this->getFieldValueName($fieldName, $params);
                    $fieldValName = !$loopKey
                        ? $fieldValName
                        : $fieldValName . '_' . $loopKey;

                    $updateStmt[] = "{$availableFields[$fieldName]} = :{$fieldValName}";
                    $params[$fieldValName] = $fieldValue;
                }
            }
        }

        return [
            'statements' => count($updateStmt)
                ? ' ' . implode(',', $updateStmt) . ' '
                : false,
            'params' => $params
       ];
    }

    protected function addTableUpdateStatements(&$sql, $searchData, &$params, $alias = false, $loopKey = false) {
        $updateStmts = $this->getTableUpdateStatements($searchData, $alias, $loopKey);
        if (empty($updateStmts['statements'])) {
            return false;
        }

        $sql .= $updateStmts['statements'];
        $params = array_merge($params, $updateStmts['params']);

        return true;
    }

    public function update($entityData) {
        $this->checkEntityRequiredFields($entityData);

        $indexField = $this->getTableIndexField();
        $params = [
            $indexField => $entityData[$indexField]
        ];

        $sql = <<<SQL
            update
                {$this->getTableName()} 
            set
SQL;
        if (!$this->addTableUpdateStatements($sql, $entityData, $params)) {
            return false;
        }

        $sql .= "
            where
                {$indexField} = :{$indexField}
        ";

        if ($this->exec($sql, $params)->errorCode() > 0) {
            return false;
        }

        return $entityData;
    }

    public function set($entityData) {
        $indexField = $this->getTableIndexField();

        $updEntity = empty($entityData[$indexField])
            ? $this->add($entityData)
            : $this->update($entityData);

        return !empty($updEntity[$indexField]);
    }

    public function remove($entityData) {
        $indexField = $this->getTableIndexField();
        if (empty($entityData[$indexField])) {
            throw new \Exception("Can't remove entity without index field specified");
        }

        $params = [
            $indexField => $entityData[$indexField],
        ];

        $sql = <<<SQL
            delete from
                {$this->getTableName()} {$this->getTableAlias()}
            where
                {$this->getTableFields($indexField)} = :{$indexField}
SQL;
        $stmt = $this->exec($sql, $params);
        if ($stmt->errorCode() > 0) {
            return false;
        }

        return $stmt->rowCount();
    }
}
