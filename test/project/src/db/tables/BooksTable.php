<?php

namespace norm_test\db\tables;

use ingwar1991\Norm\TableEditable;


class BooksTable extends TableEditable {
    protected $dbName = "norm_test_db";

    protected $tableName = "books";
    protected $tableAlias = "bk";

    protected $tableFields = [
        'book_id' => 'id',
        'title' => 'title',
        'author_id' => 'author_id',
        'published_year' => 'published_year',
        'genre' => 'genre',
    ];
    protected $requiredFields = [
        'book_id',
        'title',
        'author_id',
    ];
    protected $tableIndexField = "book_id";

    public function hydrateResultInitial(&$result, $requiredFields = [], $onlyExistingFields = false) {
        parent::hydrateResultInitial($result, $requiredFields, $onlyExistingFields);

        $authorsTable = new AuthorsTable($this->getConnection(), $this->getUserTimezone());
        foreach ($result as $bookId => $book) {
            $author = [$book];
            $authorsTable->hydrateResultInitial($author, $requiredFields, true); 
            $author = reset($author);

            $book = array_diff_assoc($book, $author); 
            $book['author'] = $author; 

            $result[$bookId] = $book;
        }
    }

    // Example for customizing select method with joins
    public function get($searchData) {
        $searchData = is_array($searchData)
            ? $searchData
            : [];

        $this->createFieldsArray($searchData);

        $selectFields = $this->createTableSelectArray($searchData['fields']);

        $authorsTable = new AuthorsTable($this->getConnection(), $this->getUserTimezone());
        $authorsTable->complementTableSelectArray($selectFields, $authorsTable->getTableIndexField(), $searchData['fields']);

        $selectFields = $this->getSelectStringFromSelectArray($selectFields, $searchData['fields']);

        $sql = <<<SQL
            select
              {$selectFields}
            from
                {$this->getTableName()} {$this->getTableAlias()},
                {$authorsTable->getTableName()} {$authorsTable->getTableAlias()}
            where
                {$this->getTableFields(
                    $authorsTable->getTableIndexField(), 
                )} = {$authorsTable->getTableFields(
                    $authorsTable->getTableIndexField(), 
                )} 
SQL;

        $params = [];
        $this->addSelectConditions($sql, $searchData, $params);
        $authorsTable->addSelectConditions($sql, $searchData, $params);
        $this->addTimeFieldsConditions($sql, $searchData, $params);

        $this->addSqlFinalStmts($sql, $searchData, [
            'group_by' => $this->getTableIndexField(), 
        ]);

        $result = $this->exec($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        $this->hydrateResult($result, $searchData['fields']);

        return $result;
    }

    public function getUniqueGenres($searchData) {
        $searchData = is_array($searchData)
            ? $searchData
            : [];

        $this->createFieldsArray($searchData);
        $selectFields = ['genre', 'count(*) as count'];
        if ($this->checkIfTotalFoundIsRequired($searchData['fields'])) {
            $searchData['fields'] = ['total_found'];
        } 
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

        $this->addSqlFinalStmts($sql, $searchData, [
            'group_by' => 'genre', 
        ]);

        $result = $this->exec($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        $this->addTotalFoundIfRequired($result, $searchData['fields']);

        return $result;
    }
}
