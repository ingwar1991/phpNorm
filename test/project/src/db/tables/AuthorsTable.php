<?php

namespace norm_test\db\tables;

use ingwar1991\Norm\TableReadable;


class AuthorsTable extends TableReadable {
    protected $dbName = "norm_test_db";

    protected $tableName = "authors";
    protected $tableAlias = "aut";

    protected $tableFields = [
        'author_id' => 'id',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'birth_year' => 'birth_year',
    ];
    protected $requiredFields = [
        'author_id',
        'first_name',
        'last_name',
    ];
    protected $tableIndexField = "author_id";

    protected function createFieldsArray(&$searchData, $alias = false) {
        parent::createFieldsArray($searchData, $alias);

        if (in_array('name', $searchData['fields'])) {
            $searchData['fields'] = array_merge($searchData['fields'], ['name']);
        } 
    }

    public function hydrateResultInitial(&$result, $requiredFields = [], $onlyExistingFields = false) {
        parent::hydrateResultInitial($result, $requiredFields, $onlyExistingFields);

        if (in_array('name', $requiredFields)) {
            foreach($result as $key => $val) {
                $val['name'] = implode(' ', [
                    $val['first_name'],
                    $val['last_name'],
                ]);
                unset(
                    $val['first_name'],
                    $val['last_name'],
                );

                $result[$key] = $val; 
            }
        }
    }
}
