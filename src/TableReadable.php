<?php
/**
 * Author: ingwar1991
 */

namespace ingwar1991\Norm;


use ingwar1991\DBConnections\sql\SqlConnectionBase as Connection;

use ingwar1991\TimeProcessors\Timeframe;
use ingwar1991\TimeProcessors\Timezone;


abstract class TableReadable {
    protected $conn;
    protected $dbName = null;
    protected $isChecked = false;

    protected $tableName = null;
    protected $tableAlias = null;

    protected $tableFields = [];
    protected $tableIndexField = null;
    protected $requiredFields = [];
    protected $excludedFields = [];

    protected $tableTimeField = null;
    protected $tableTimeStartField = null;
    protected $tableTimeEndField = null;

    protected $tableTimeFields = [];

    protected $lastError = [];

    private $timeframe = null;
    private $timezone = null;

    private $userTimezone = null;

    public function __construct(Connection $connection, $userTimezone = false) {
        $this->conn = $connection;

        if ($userTimezone) {
            $this->userTimezone = $userTimezone;
        }
    }

    protected function getTableIndexField() {
        return $this->tableIndexField;
    }

    protected function getUserTimezone() {
        return $this->userTimezone;
    }

    protected function parseDatetimeValue($value) {
        if (!$this->getUserTimezone()) {
            return $this->timezone()->serverDate($value);
        }

        return $this->timezone($this->getUserTimezone())->toServerDate($value);
    }

    protected function checkConnection() {
        if ($this->isChecked) {
            return true;
        }

        if (!$this->conn) {
            throw new \Exception('Required connection not found');
        }
        if (!$this->dbName) {
            throw new \Exception('No database name specified');
        }

        $dbName = $this->conn->exec('select database()')->fetchColumn();
        if ($this->dbName != $dbName) {
            throw new \Exception('Wrong database connection transmitted');
        }

        $this->isChecked = true;
    }

    protected function getConnection() {
        $this->checkConnection();

        return $this->conn;
    }

    /**
     * @return \PDOStatement
     */
    protected function exec($sql, $params = []) {
        return $this->getConnection()->exec($sql, $params);
    }

    protected function getTableName() {
        if (!$this->tableName) {
            throw new \Exception("No table name specified");
        }

        return $this->tableName;
    }

    protected function getTableAlias($withDot = false) {
        if (!$this->tableAlias) {
            throw new \Exception("No table alias specified");
        }

        return $withDot
            ? $this->tableAlias . '.'
            : $this->tableAlias;
    }

    protected function processTableAlias($alias = false) {
        if (!is_string($alias)) {
            $alias = $this->getTableAlias(true);
        } elseif (substr($alias, -1) != '.') {
            $alias .= '.';
        }

        return $alias;
    }

    protected function getTableFieldAliasIfRequired($field, $withDot = false) {
        return $this->checkIfTableAliasIsRequiredToField($field)
            ? $this->getTableAlias($withDot)
            : '';
    }

    protected function removeAliasFromField($fieldName, $alias = false) {
        if (!$alias) {
            return preg_replace('/(.)+(\.){1}/', '', $fieldName);
        }

        return str_replace("{$alias}.", '', $fieldName);
    }

    protected function checkAlias($alias) {
        return $alias && substr($alias, -1) != '.'
            ? "{$alias}."
            : $alias;
    }

    protected function getTotalFound() {
        $totalFound = $this->exec('select found_rows()')->fetchColumn();
        return $totalFound
            ? $totalFound
            : 0;
    }

    protected function getTotalFoundIfRequired(&$result, $requiredFields) {
        return count($result) && $this->checkIfTotalFoundIsRequired($requiredFields)
            ? $this->getTotalFound()
            : false;
    }

    protected function addTotalFoundIfRequired(&$result, $requiredFields, $totalFound = null) {
        $totalFound = $totalFound !== null
            ? $totalFound
            : $this->getTotalFoundIfRequired($result, $requiredFields);

        if ($totalFound !== false) {
            $result['total_found'] = $totalFound
                ? $totalFound
                : 0;
        }
    }

    protected function getEntityArray($entityVals, $alwaysArray = false){
        if (is_array($entityVals)) {
            return $entityVals;
        }

        if ($entityVals === null || !strstr($entityVals, ',')) {
            if ($alwaysArray) {
                return $entityVals === null
                    ? []
                    : [$entityVals];
            }
            else {
                return $entityVals;
            }
        }

        $entityVals = explode(',', $entityVals);
        foreach($entityVals as $key => $entity_val) {
            $entity_val = trim($entity_val);
            if (empty($entity_val)) {
                unset($entityVals[$key]);
            }
            else {
                $entityVals[$key] = $entity_val;
            }
        }

        return $entityVals;
    }

    protected function getTableTimeField($alias = false) {
        $alias = $this->processTableAlias($alias);

        return $this->tableTimeField
            ? $alias . $this->tableTimeField
            : false;
    }

    protected function getTableTimeStartField($alias = false) {
        $alias = $this->processTableAlias($alias);

        $startField = $this->tableTimeStartField
            ? $this->tableTimeStartField
            : $this->getTableFields('start_date');
        if ($startField) {
            $startField = $this->removeAliasFromField($startField);
        }

        return $startField
            ? $alias . $startField
            : false;
    }

    protected function getTableTimeEndField($alias = false) {
        $alias = $this->processTableAlias($alias);

        $endField = $this->tableTimeEndField
            ? $this->tableTimeEndField
            : $this->getTableFields('end_date');
        if ($endField) {
            $endField = $this->removeAliasFromField($endField);
        }

        return $endField
            ? $alias . $endField
            : false;
    }

    protected function getTableTimeFields($alias = false) {
        $tableTimeFields = $this->tableTimeFields;
        if ($alias) {
            $tableTimeFields = array_map(function($el) use ($alias) {
                return $this->getTableFields($el, $alias);
            }, $this->tableTimeFields);
        }

        if (empty($tableTimeFields)) {
            $tableTimeFields = [];

            if ($this->getTableTimeField($alias)) {
                $tableTimeFields[] = $this->getTableTimeField($alias);
            }
            if ($this->getTableTimeStartField($alias)) {
                $tableTimeFields[] = $this->getTableTimeStartField($alias);
            }
            if ($this->getTableTimeEndField($alias)) {
                $tableTimeFields[] = $this->getTableTimeEndField($alias);
            }
        }

        return $tableTimeFields;
    }

    protected function isTableTimeField($fieldName) {
        return in_array($fieldName, $this->getTableTimeFields());
    }

    protected function checkForTimeParams($searchData) {
        return (
               (isset($searchData['timeframe']) && $searchData['timeframe'])
            || (isset($searchData['start_date']) && $searchData['start_date'])
            || (isset($searchData['end_date']) && $searchData['end_date'])
            || (isset($searchData['at_date']) && $searchData['at_date'])
     )
            ? true
            : false;
    }

    protected function parseTimeFieldValue($value, $condition, $isTimeframe = false) {
        // if we have [`<<`,`>>`] - between condition - we expect to have 2 datetime values, separated by comma
        if (in_array($condition, ['<<', '>>'])) {
            $value = $this->getEntityArray($value, true);
            foreach($value as $key => $val) {
                $value[$key] = $isTimeframe
                    ? $this->Timeframe()->getDateFromTimeFrame($val, Timeframe::DATE_FORMAT_TEXT)
                    : $this->parseDatetimeValue($val);
            }
        } else {
            $value = $isTimeframe
                ? $this->Timeframe()->getDateFromTimeFrame($value, Timeframe::DATE_FORMAT_TEXT)
                : $this->parseDatetimeValue($value);
        }

        return $value;
    }

    protected function addSelectConditions(&$sql, &$searchData, &$params, $alias = false, $excludeTimeFields = true) {
        $selectConditions = $this->getTableSelectConditions($searchData, $params, $alias, $excludeTimeFields);
        if (!$selectConditions) {
            return $selectConditions;
        }

        $sql .= $selectConditions;

        return true;
    }

    protected function getTableSelectConditionsList(&$searchData, &$params, $alias = false, $excludeTimeFields = true) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();
        $availableFields = $this->getTableFields(false, $alias);

        $selectConditions = [];
        foreach($searchData as $fieldName => $fieldValue) {
            if (
                isset($availableFields[$fieldName])
                && (!$excludeTimeFields || !$this->isTableTimeField($fieldName))
         ) {
                $this->createTableSelectCondition(
                    $selectConditions,
                    $params,
                    [
                        'availableFields' => $availableFields,
                        'fieldName' => $fieldName,
                        'fieldValue' => $fieldValue
                    ]
             );

                unset($searchData[$fieldName]);
            } elseif ($fieldName == '`or`') { // add fields with OR relations
                $orStmts = $this->isJson($fieldValue)
                    ? json_decode($fieldValue, true)
                    : [$fieldValue];
                foreach($orStmts as $orStmt) {
                    $orConditions = $this->getTableSelectInlineConditionsList($orStmt, $params, $availableFields);
                    if (count($orConditions)) {
                        $selectConditions[] = ' (' . implode(' or ', $orConditions) . ') ';
                    }
                }
            } elseif ($fieldName == '`and`') { // add fields with AND relations. Is needed when the same field has several conditions
                $andStmts = $this->isJson($fieldValue)
                    ? json_decode($fieldValue, true)
                    : [$fieldValue];
                foreach($andStmts as $andStmt) {
                    $andConditions = $this->getTableSelectInlineConditionsList($andStmt, $params, $availableFields);
                    if (count($andConditions)) {
                        $selectConditions[] = ' (' . implode(' and ', $andConditions) . ') ';
                    }
                }
            }
        }

        return $selectConditions;
    }

    private function getTableSelectInlineConditionsList($conditions, &$params, $availableFields) {
        $orFields = array_map(function($el) {
            $el = explode('=', $el);

            $fName = $el[0];
            array_shift($el);

            $fVal = implode('=', $el);

            return [
                $fName,
                $fVal
           ];
        }, explode('`|`', $conditions));

        $orConditions = [];
        foreach($orFields as $orField) {
            if (isset($availableFields[$orField[0]])) {
                $this->createTableSelectCondition(
                    $orConditions,
                    $params,
                    [
                        'availableFields' => $availableFields,
                        'fieldName' => $orField[0],
                        'fieldValue' => $orField[1]
                   ]
             );
            }
        }

        return $orConditions;
    }

    protected function getTableSelectConditions(&$searchData, &$params, $alias = false, $excludeTimeFields = true) {
        $selectConditions = $this->getTableSelectConditionsList($searchData, $params, $alias, $excludeTimeFields);

        return count($selectConditions)
            ? " and " . implode(" and ", $selectConditions)
            : '';
    }

    /**
     * Adds additional conditions if required
     *
     * Available conditions:
     *      * `~` - like
     *      * `!~` - not like
     *      * `~[]` - bulk like(like STR1 or like STR2)
     *      * `!~[]` - bulk not like(not like STR1 or not like STR2)
     *      * `!` - <>
     *      * `>` - >
     *      * `<` - <
     *      * [`>=`,`=>`] - >=
     *      * [`<=`,`=<`] - <=
     *      * [`<<`,`>>`] - between
     *      * `[]` - in ()
     *      * `![]` - not in ()
     *      * `is_null` - is null
     *      * `not_null` - is not null
     *
     * NOTE: Every like condition can also accept `%` stmt at the beggining and the end of the value,
     * Otherwise will add % both at the beginning and the end of value
     *
     * @param &$selectConditions
     * @param &$params
     * @param $selectConditionsData = [
     *      $availableFields,
     *      $fieldName,
     *      $fieldValue
     *]
     */
    private function createTableSelectCondition(&$selectConditions, &$params, $selectConditionsData) {
        if (
            empty($selectConditionsData['availableFields'])
            || empty($selectConditionsData['fieldName'])
            || !isset($selectConditionsData['fieldValue']) // here we can have '' or 0 sometimes
     ) {
            return false;
        }

        $availableFields = $selectConditionsData['availableFields'];
        $fieldName = $selectConditionsData['fieldName'];
        $fieldValue = $selectConditionsData['fieldValue'];

        $condition = $this->getFieldCondition($fieldValue);
        $processedCondition = $this->processFieldCondition($fieldName, $fieldValue, $availableFields, $condition, $params);

        if ($processedCondition) {
            $selectConditions[] = $processedCondition['stmt'];

            if (!empty($processedCondition['param'])) {
                $params = array_merge($params, $processedCondition['param']);
            }
        }
    }

    private function getFieldCondition(&$fieldValue) {
        $condition = false;

        $fieldConditionsPattern = '/^(?=`(~|!~|~\[\]|!~\[\]|=|>|>=|=>|<|<=|=<|<<|>>|!|\[\]|!\[\]|is_null|not_null){1}`){1}.*$/';

        $conditionsMatches = [];
        if (preg_match($fieldConditionsPattern, $fieldValue, $conditionsMatches)) {
            $condition = $conditionsMatches[1];
            $fieldValue = str_replace("`{$condition}`", '', $fieldValue);
        }

        return $condition;
    }

    protected function getPureFieldParam($fieldParam) {
        $fieldParam = explode('`', $fieldParam);

        return trim(end($fieldParam));
    }

    public function fieldHasCondition($fieldValue) {
        return $this->getFieldCondition($fieldValue)
            ? true
            : false;
    }

    private function processFieldCondition($fieldName, $fieldValue, $availableFields, $condition = false, $alreadyAddedParams = []) {
        $stmt = '';
        $param = [];

        // is used all the time, but here just to avoid pasting this code at every single value condition
        $fieldValName = $this->getFieldValueName($fieldName, $alreadyAddedParams);

        switch ($condition) {
            case '~' :
                $stmt = "{$availableFields[$fieldName]} like :{$fieldValName}";
                $param[$fieldValName] = $this->getFieldValueLikeStmt($fieldValue);

                break;
            case '!~' :
                $stmt = "{$availableFields[$fieldName]} not like :{$fieldValName}";
                $param[$fieldValName] = $this->getFieldValueLikeStmt($fieldValue);

                break;
            case '!' :
                $stmt = "{$availableFields[$fieldName]} <> :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
            case '[]' :
                $inStmtData = $this->createInStmt($fieldName, $fieldValue, $alreadyAddedParams);

                if (!empty($inStmtData['param'])) {
                    $stmt = "{$availableFields[$fieldName]} in " . $inStmtData['stmt'];
                    $param = $inStmtData['param'];
                }

                break;
            case '![]' :
                $inStmtData = $this->createInStmt($fieldName, $fieldValue, $alreadyAddedParams);

                if (!empty($inStmtData['param'])) {
                    $stmt = "{$availableFields[$fieldName]} not in " . $inStmtData['stmt'];
                    $param = $inStmtData['param'];
                }

                break;
            case '~[]' :
                $orStmtData = $this->createOrStmt(' like ', $fieldName, $fieldValue, $alreadyAddedParams);

                if (!empty($orStmtData['param'])) {
                    $stmt = $orStmtData['stmt'];
                    foreach($orStmtData['param'] as $orName => $orParam) {
                        $param[$orName] = $this->getFieldValueLikeStmt($orParam);
                    }
                }

                break;
            case '!~[]' :
                $orStmtData = $this->createOrStmt(' not like ', $fieldName, $fieldValue, $alreadyAddedParams);

                if (!empty($orStmtData['param'])) {
                    $stmt = $orStmtData['stmt'];
                    foreach($orStmtData['param'] as $orName => $orParam) {
                        $param[$orName] = $this->getFieldValueLikeStmt($orParam);
                    }
                }

                break;
            case 'is_null' :
                $stmt = "{$availableFields[$fieldName]} is null";

                break;
            case 'not_null' :
                $stmt = "{$availableFields[$fieldName]} is not null";

                break;
            case '>' :
                $stmt = "{$availableFields[$fieldName]} > :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
            case '<' :
                $stmt = "{$availableFields[$fieldName]} < :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
            case '>=' :
            case '=>' :
                $stmt = "{$availableFields[$fieldName]} >= :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
            case '<=' :
            case '=<' :
                $stmt = "{$availableFields[$fieldName]} <= :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
            case '<<' :
            case '>>' :
                $alreadyAddedParamsUpdated = $alreadyAddedParams;
                $alreadyAddedParamsUpdated[$fieldValName] = 'test';
                $fieldValName2 = $this->getFieldValueName($fieldName, $alreadyAddedParamsUpdated);

                $fieldValue = $this->getEntityArray($fieldValue, true);
                if (count($fieldValue) == 2) { // for this condition we need 2 values
                    if (in_array($availableFields[$fieldName], $this->getTableTimeFields(true))) {
                        // for dates we use between stmt
                        $stmt = "({$availableFields[$fieldName]} between :{$fieldValName} and :{$fieldValName2})";
                        $param[$fieldValName] = $fieldValue[0];
                        $param[$fieldValName2] = $fieldValue[1];
                    } else if ($condition == '>>') { // val1 > field > val2
                        $stmt = "({$availableFields[$fieldName]} > :{$fieldValName} and {$availableFields[$fieldName]} < :{$fieldValName2}) ";
                        $param[$fieldValName] = $fieldValue[0];
                        $param[$fieldValName2] = $fieldValue[1];
                    } else { // val1 < field < val2
                        $stmt = "({$availableFields[$fieldName]} > :{$fieldValName} and {$availableFields[$fieldName]} < :{$fieldValName2}) ";
                        $param[$fieldValName] = $fieldValue[0];
                        $param[$fieldValName2] = $fieldValue[1];
                    }
                }

                break;
            default :
                $stmt = "{$availableFields[$fieldName]} = :{$fieldValName}";
                $param[$fieldValName] = $fieldValue;

                break;
        }

        if (empty($stmt)) {
            return false;
        }

        return [
            'stmt' => $stmt,
            'param' => $param
       ];
    }

    protected function getFieldValueLikeStmt($fieldValue) {
        $hasStmts = false;

        if (substr($fieldValue, 0, 3) == '`%`') {
            $fieldValue = '%' . substr($fieldValue, 3);
            $hasStmts = true;
        }
        if (substr($fieldValue, -3) == '`%`') {
            $fieldValue = substr($fieldValue, 0, -3) . '%';
            $hasStmts = true;
        }

        if (!$hasStmts) {
            $fieldValue = '%' . $fieldValue . '%';
        }

        return $fieldValue;
    }

    protected function getFieldValueName($fieldName, $alreadyAddedParams, &$lastInIndex = 0) {
        $existingValueNames = array_keys($alreadyAddedParams);

        if (!in_array($fieldName, $existingValueNames)) {
            return $fieldName;
        }

        while(true) {
            $newFieldName = $fieldName . '_' . ++$lastInIndex;
            if (!in_array($newFieldName, $existingValueNames)) {
                return $newFieldName;
            }
        }
    }

    protected function createInStmt($fieldName, $fieldValues, $alreadyAddedParams=[]) {
        $fieldValues = $this->getEntityArray($fieldValues, true);
        if (!count($fieldValues) || !$fieldName) {
            return false;
        }

        $stmt = [];
        $params = [];
        $lastInIndex = 0;
        foreach($fieldValues as $fieldValue) {
            $fieldValName = $this->getFieldValueName($fieldName, $alreadyAddedParams, $lastInIndex);

            $stmt[] = ':' . $fieldValName;
            $params[$fieldValName] = $fieldValue;

            // for further check
            $alreadyAddedParams[$fieldValName] = $fieldValue;
        }

        return [
            'stmt' => ' (' . implode(",\n", $stmt) . ') ',
            'param' => $params
       ];
    }

    protected function createOrStmt($condition, $fieldName, $fieldValues, $alreadyAddedParams) {
        $stmtData = $this->createInStmt($fieldName, $fieldValues, $alreadyAddedParams);
        $stmtConditions = explode(',',
            trim(
                str_replace('(', '',
                    str_replace(')', '',
                        $stmtData['stmt']
                 )
             )
         )
     );

        $stmtData['stmt'] = " ({$fieldName} {$condition} " .
            implode(" or {$fieldName} {$condition} ", $stmtConditions) .
        ') ';

        return $stmtData;
    }

    protected function getTimeFieldsConditions($searchData, &$params, $necessarily = false, $alias = false, $timeFieldToUse = false) {
        if (!$this->getTableTimeField() && !$timeFieldToUse) {
            return false;
        }

        if (!$this->checkForTimeParams($searchData)) {
            if (!$necessarily) {
                return false;
            }

            $searchData['timeframe'] = $this->Timeframe()->getDefaultTimeFrame();
        }
        $timeFieldsConditions = [];


        if (isset($searchData['timeframe']) && $searchData['timeframe']) {
            $condition = $this->getFieldCondition($searchData['timeframe']);
            $condition = $condition
                ? $condition
                : '>=';

            $field = $timeFieldToUse
                ? $this->processTableAlias($alias) . $timeFieldToUse
                : $this->getTableTimeField($alias);

            $processedCondition = $this->processFieldCondition(
                'timeframe',
                $this->parseTimeFieldValue($searchData['timeframe'], $condition, true),
                ['timeframe' => $field],
                $condition,
                $params
         );

            if ($processedCondition) {
                $timeFieldsConditions[] = $processedCondition['stmt'];

                if (!empty($processedCondition['param'])) {
                    $params = $params + $processedCondition['param'];
                }
            }
        }


        if (isset($searchData['start_date']) && $searchData['start_date']) {
            $condition = $this->getFieldCondition($searchData['start_date']);
            $condition = $condition
                ? $condition
                : '>=';

            $field = $timeFieldToUse
                ? $this->processTableAlias($alias) . $timeFieldToUse
                : $this->getTableTimeField($alias);
            if ($this->getTableTimeStartField()) {
                $field = $this->getTableTimeStartField($alias);
            }

            $processedCondition = $this->processFieldCondition(
                'start_date',
                $this->parseTimeFieldValue($searchData['start_date'], $condition),
                ['start_date' => $field],
                $condition,
                $params
         );

            if ($processedCondition) {
                $timeFieldsConditions[] = $processedCondition['stmt'];

                if (!empty($processedCondition['param'])) {
                    $params = $params + $processedCondition['param'];
                }
            }
        }

        if (isset($searchData['end_date']) && $searchData['end_date']) {
            $condition = $this->getFieldCondition($searchData['end_date']);
            $condition = $condition
                ? $condition
                : '<=';

            $field = $timeFieldToUse
                ? $this->processTableAlias($alias) . $timeFieldToUse
                : $this->getTableTimeField($alias);
            if ($this->getTableTimeEndField()) {
                $field = $this->getTableTimeEndField($alias);
            }

            $processedCondition = $this->processFieldCondition(
                'end_date',
                $this->parseTimeFieldValue($searchData['end_date'], $condition),
                ['end_date' => $field],
                $condition,
                $params
         );

            if ($processedCondition) {
                $timeFieldsConditions[] = $processedCondition['stmt'];

                if (!empty($processedCondition['param'])) {
                    $params = $params + $processedCondition['param'];
                }
            }
        }

        if (isset($searchData['at_date']) && $searchData['at_date']) {
            $condition = $this->getFieldCondition($searchData['at_date']);

            $field = $timeFieldToUse
                ? $this->processTableAlias($alias) . $timeFieldToUse
                : $this->getTableTimeField($alias);

            $processedCondition = $this->processFieldCondition(
                'at_date',
                $this->parseTimeFieldValue($searchData['at_date'], $condition),
                ['at_date' => $field],
                $condition,
                $params
         );

            if ($processedCondition) {
                $timeFieldsConditions[] = $processedCondition['stmt'];

                if (!empty($processedCondition['param'])) {
                    $params = $params + $processedCondition['param'];
                }
            }
        }

        return count($timeFieldsConditions)
            ? ' and ' . implode(' and ', $timeFieldsConditions) . ' '
            : '';
    }

    public function addTimeFieldsConditions(&$sql, $searchData, &$params, $necessarily = false, $alias = false) {
        if (!($timeFieldsConditions = $this->getTimeFieldsConditions($searchData, $params, $necessarily, $alias))) {
            return false;
        }

        // adding conditions wrapped around with new lines
        $sql .= <<<SQL

            {$timeFieldsConditions}

SQL;

        return true;
    }

    protected function addOrderings(&$sql, $searchData) {
        $searchData['order_by'] = isset($searchData['order_by'])
            ? $this->getEntityArray($searchData['order_by'], true)
            : [];

        foreach($searchData['order_by'] as $key => $value) {
            $value = trim($value);
            // TODO: switch logic here to regexp
            $value = explode(' ', $value);
            $value[1] = isset($value[1]) && in_array($value[1], ['asc', 'desc'])
                ? $value[1]
                : 'asc';

            $searchData['order_by'][$key] = $value[0] . ' ' . $value[1];
        }

        if (count($searchData['order_by'])) {
            foreach($searchData['order_by'] as $key => $field) {
                if (!$field) {
                    unset($searchData['order_by'][$key]);
                }

                if ($this->checkIfTableAliasIsRequiredToField($field)) {
                    $searchData['order_by'][$key] = $this->getTableAlias(true) . $field;
                }
            }

            if (!count($searchData['order_by'])) {
                return;
            }

            $orderBy = implode(', ', array_unique($searchData['order_by']));
            $sql .= <<<SQL

            order by
              {$orderBy}

SQL;
        }
    }

    protected function addGroupings(&$sql, $searchData) {
        $searchData['group_by'] = isset($searchData['group_by'])
            ? $this->getEntityArray($searchData['group_by'], true)
            : [];

        if (count($searchData['group_by'])) {
            foreach($searchData['group_by'] as $key => $field) {
                if (!$field) {
                    unset($searchData['group_by'][$key]);
                }

                if ($this->checkIfTableAliasIsRequiredToField($field)) {
                    $searchData['group_by'][$key] = $this->getTableAlias(true) . $field;
                }
            }

            if (!count($searchData['group_by'])) {
                return;
            }

            $groupBy = implode(', ', array_unique($searchData['group_by']));
            $sql .= <<<SQL

            group by
              {$groupBy}

SQL;

        }
    }

    protected function addHavings(&$sql, $searchData) {
        $searchData['having'] = isset($searchData['having'])
            ? $this->getEntityArray($searchData['having'], true)
            : [];

        if (count($searchData['having'])) {
            foreach($searchData['having'] as $key => $field) {
                if (!$field) {
                    unset($searchData['having'][$key]);
                }

                if ($this->checkIfTableAliasIsRequiredToField($field)) {
                    $searchData['having'][$key] = $this->getTableAlias(true) . $field;
                }
            }

            if (!count($searchData['having'])) {
                return;
            }

            $having = implode(' and ', array_unique($searchData['having']));
            $sql .= <<<SQL

            having
              {$having}

SQL;

        }
    }

    protected function addLimitations(&$sql, $searchData) {
        if (!empty($searchData['limit']) && $searchData['limit'] == '`all`') {
            return;
        }

        $searchData['limit'] = isset($searchData['limit']) && $searchData['limit'] == ($searchData['limit'] + 0)
            ? $searchData['limit'] + 0
            : 100;
        $searchData['offset'] = isset($searchData['offset']) && $searchData['offset'] == ($searchData['offset'] + 0)
            ? $searchData['offset'] + 0
            : 0;

        $sql .= <<<SQL

            limit
                {$searchData['limit']}
            offset
                {$searchData['offset']}

SQL;
    }

    protected function addSqlFinalStmts(&$sql, $searchData, $prioritizedSettings = []) {
        if (isset($prioritizedSettings['group_by'])) {
            $prioritizedSettings['group_by'] = $this->getEntityArray($prioritizedSettings['group_by'], true);
            $searchData['group_by'] = isset($searchData['group_by'])
                ? array_merge(
                    $prioritizedSettings['group_by']
                    , $this->getEntityArray($searchData['group_by'], true)
             )
                : $prioritizedSettings['group_by'];
        }

        if (isset($prioritizedSettings['having'])) {
            $prioritizedSettings['having'] = $this->getEntityArray($prioritizedSettings['having'], true);
            $searchData['having'] = isset($searchData['having'])
                ? array_merge(
                    $prioritizedSettings['having']
                    , $this->getEntityArray($searchData['having'], true)
             )
                : $prioritizedSettings['having'];
        }

        if (isset($prioritizedSettings['order_by'])) {
            $prioritizedSettings['order_by'] = $this->getEntityArray($prioritizedSettings['order_by'], true);
            $searchData['order_by'] = isset($searchData['order_by'])
                ? array_merge(
                    $prioritizedSettings['order_by']
                    , $this->getEntityArray($searchData['order_by'], true)
             )
                : $prioritizedSettings['order_by'];
        }

        if (isset($prioritizedSettings['limit'])) {
            $searchData['limit'] = $prioritizedSettings['limit'];
        }

        if (isset($prioritizedSettings['offset'])) {
            $searchData['offset'] = $prioritizedSettings['offset'];
        }

        $this->addGroupings($sql, $searchData);
        $this->addHavings($sql, $searchData);
        $this->addOrderings($sql, $searchData);
        $this->addLimitations($sql, $searchData);
    }

    protected function checkIfTotalFoundIsRequired($requiredFields) {
        $isRequired = in_array('total_found', $requiredFields);
        if ($isRequired && !$this->getTableIndexField()) {
            throw new \Exception("Can't add total_found without specified index field");
        }

        return $isRequired;
    }

    protected function getSelectStringFromSelectArray($selectFields, $requiredFields) {
        // remove duplicates
        $selectFields = array_unique($selectFields);

        $selectFields = implode(',', $selectFields);

        if ($this->checkIfTotalFoundIsRequired($requiredFields)) {
            $selectFields = 'sql_calc_found_rows ' . $selectFields;
        }

        return $selectFields;
    }

    protected function isJson($string) {
        if (is_array($string)) {
            return false;
        }

        json_decode($string);

        return (json_last_error() == JSON_ERROR_NONE);
    }

    private function checkIfTableAliasIsRequiredToField($field) {
        if (strpos($field, '.')) {
            return false;
        }

        if ($this->getTableFields($field)) {
            return false;
        }

        return $this->isTableField($field)
            ? true
            : false;
    }

    protected function isTableField($field) {
        $field = $this->removeAliasFromField($field);

        return $this->getTableFields($field)
            ? true
            : false;
    }

    protected function getTableFields($fieldName = false, $alias = false) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();
        $alias = !$alias || substr($alias, -1) == '.'
            ? $alias
            : $alias . '.';

        if ($fieldName) {
            $fieldName = str_replace($alias, '', $fieldName);

            if (!isset($this->tableFields[$fieldName])) {
                return false;
            }

            return $alias . $this->tableFields[$fieldName];
        }

        $tableFields = $this->tableFields;
        foreach($tableFields as $key => $tableField) {
            $tableFields[$key] = $alias . $tableField;
        }

        return $tableFields;
    }

    protected function getTableRequiredFields($forFieldsList = false, $alias = false) {
        $fields = $this->requiredFields;
        if ($forFieldsList) {
            $fieldsHydrated = [];
            foreach($fields as $field) {
                if ($field) {
                    $fieldsHydrated[$field] = $this->getTableFields($field, $alias);
                }
            }

            $fields = $fieldsHydrated;
        }

        return $fields;
    }

    protected function fieldIsExcluded($fieldName) {
        return array_search($fieldName, $this->excludedFields) !== false;
    }

    protected function createTableSelectArray(&$requiredFields, $alias = false) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();

        // if `*` was transmitted - use all table fields
        $requiredFields = $this->getEntityArray($requiredFields, true);
        if (in_array('*', $requiredFields)) {
            $requiredFields = array_merge($requiredFields, array_keys($this->getTableFields()));
        }

        $requiredFields = array_merge($requiredFields, $this->getTableRequiredFields());

        $tableSelectFields = [];
        foreach($requiredFields as $key => $fieldName) {
            $fieldName = $this->removeAliasFromField($fieldName, $alias);

            if ($fieldName && ($fieldDef = $this->getTableFields($fieldName, $alias)) && !$this->fieldIsExcluded($fieldName)) {
                $tableSelectFields[] = $fieldDef . ' as ' . $fieldName;
                $requiredFields[$key] = $fieldDef;
            }
        }

        $tableSelectFields = array_unique($tableSelectFields);
        $requiredFields = array_unique($requiredFields);

        return $tableSelectFields;
    }

    public function complementTableSelectArray(&$tableSelectFields, $fieldToExclude, &$requiredFields, $alias = false) {
        $additionalTableSelectFields = $this->createTableSelectArray($requiredFields, $alias);
        
        $fieldToExcludeFinal = $this->getTableFields($fieldToExclude, $alias) . ' as ' . $fieldToExclude;
        foreach ($additionalTableSelectFields as $key => $field) {
            if ($field == $fieldToExcludeFinal) {
                unset($additionalTableSelectFields[$key]);
                break;
            } 
        }

        $tableSelectFields = array_merge(
            $tableSelectFields,
            $additionalTableSelectFields,
        ); 
    }

    protected function createFieldsArray(&$searchData, $alias = false) {
        $alias = $alias || $alias === ''
            ? $alias
            : $this->getTableAlias();

        $searchData['fields'] = isset($searchData['fields'])
            ? $searchData['fields']
            : [];
        $searchData['fields'] = $this->getEntityArray($searchData['fields'], true);
        if (!count($searchData['fields'])) {
            // if nothing was transmitted - use required table fields
            $searchData['fields'] = $this->getTableRequiredFields();
        } else if (in_array('*', $searchData['fields'])) {
            // `*` was transmitted - use all table fields
            $searchData['fields'] = array_merge($searchData['fields'], array_keys($this->getTableFields()));
        }
        $searchData['fields'] = count($searchData['fields'])
            ? array_merge($searchData['fields'], $this->getTableRequiredFields())
            : $searchData['fields'];

        $searchData['order_by'] = isset($searchData['order_by'])
            ? $this->getEntityArray($searchData['order_by'], true)
            : [];

        $searchData['group_by'] = isset($searchData['group_by'])
            ? $this->getEntityArray($searchData['group_by'], true)
            : [];

        $searchData['fields'] = array_merge(
            $searchData['fields'],
            $searchData['group_by'],
            array_map(
                function($element) {
                    $element = trim($element);
                    return str_replace(' asc', '', str_replace(' desc', '', $element));
                },
                $searchData['order_by']
         )
     );

        if (count($searchData['fields'])) {
            foreach($searchData['fields'] as $key => $field) {
                if ($this->checkIfTableAliasIsRequiredToField($field)) {
                    $searchData['fields'][$key] = "{$alias}.{$field}";
                }
            }
        }

        $searchData['fields'] = array_unique($searchData['fields']);
    }

    protected function hydrateResult(&$result, $requiredFields = []) {
        $this->hydrateResultInitial($result, $requiredFields);
        $this->addTotalFoundIfRequired($result, $requiredFields);
    }

    // This method is used for overloading & call from outside the table( for joins )
    public function hydrateResultInitial(&$result, $requiredFields = [], $onlyExistingFields = false) {
        $indexField = $this->getTableIndexField();
        if (empty($result) || empty($indexField)) {
            return $result;
        }

        $resultHydrated = [];
        foreach($result as $res) {
            if ($onlyExistingFields) {
                foreach($res as $field => $r) {
                    if (!$this->isTableField($field)) {
                        unset($res[$field]);
                    }
                }
            }

            $resultHydrated[$res[$indexField]] = $res;
        }
        
        $result = $resultHydrated;
    }

    public function isRequired($searchData, $tableFieldsCustomList = false) {
        $tableFields = is_array($tableFieldsCustomList) && count($tableFieldsCustomList)
            ? $tableFieldsCustomList
            : $this->tableFields;

        $isRequired = false;
        foreach($tableFields as $fieldPseudo => $fieldName) {
            $isRequired = in_array($fieldPseudo, $searchData);
            $isRequired = $isRequired
                ? $isRequired
                : in_array($fieldName, $searchData);

            $isRequired = $isRequired
                ? $isRequired
                : in_array($fieldPseudo, $searchData['fields']);
            $isRequired = $isRequired
                ? $isRequired
                : in_array($fieldName, $searchData['fields']);

            if ($isRequired) {
                break;
            }
        }

        return $isRequired;
    }

    protected function writeError($message, $additionalData = []) {
        if (empty($message)) {
            throw new \Exception('No error message specified');
        }

        $lastError = [
            'from' => get_class($this),
            'message' => $message,
            'time' => $this->timezone()->serverDate('now', 'Y-m-d H:i:s')
       ];
        if (!empty($additionalData)) {
            $lastError = array_merge($lastError, $additionalData);
        }

        $this->lastError = $lastError;

        return false;
    }

    public function getLastError($forDebug = false) {
        $lastError = $this->lastError;
        if (!$forDebug) {
            unset($lastError['from']);
            unset($lastError['time']);
        }

        return $lastError;
    }

    /**
     * @param string $userTimezone
     *
     * @return Timezone
     */
    protected function timezone($userTimezone = 'UTC') {
        if (!$this->timezone) {
            $this->timezone = new Timezone($userTimezone);
        }

        if ($this->timezone->userTimezone() != $userTimezone) {
            $this->timezone->changeUserTimezone($userTimezone);
        }

        return $this->timezone;
    }

    /**
     * @return Timeframe
     */
    protected function Timeframe() {
        if (!$this->timeframe) {
            $this->timeframe = new Timeframe();
        }

        return $this->timeframe;
    }

    protected function getAndRemoveFieldsByPrefix(array &$fields, string $prefix) {
        $withPrefix = array_filter($fields, function($el) use ($prefix) {
            return strpos($el, $prefix) === 0;
        });

        $fields = array_diff($fields, $withPrefix);

        return array_map(function($el) use ($prefix) {
            return substr($el, strlen($prefix) + 1);
        }, $withPrefix);
    }

    public function get($searchData) {
        $searchData = is_array($searchData)
            ? $searchData
            : [];

        $this->createFieldsArray($searchData);

        $selectFields = $this->createTableSelectArray($searchData['fields']);
        $selectFields = $this->getSelectStringFromSelectArray($selectFields, $searchData['fields']);

        $sql = <<<SQL
            select
              {$selectFields}
            from
                {$this->getTableName()} {$this->getTableAlias()}
            where
              1 = 1
SQL;

        $params = [];
        $this->addSelectConditions($sql, $searchData, $params);
        $this->addTimeFieldsConditions($sql, $searchData, $params);

        $this->addSqlFinalStmts($sql, $searchData);

        $result = $this->exec($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        $this->hydrateResult($result, $searchData['fields']);

        return $result;
    }
}
