<?php declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use norm_test\db\Connection;
use norm_test\db\tables\AuthorsTable;

use PHPUnit\Framework\TestCase;


final class AuthorsTableTest extends TestCase {
    private $table;
    
    private function getTable() {
        if (empty($this->table)) {
            $this->table = new AuthorsTable(Connection::getInstance()); 
        }

        return $this->table;
    }

    public function testGetFirstAuthor(): void {
        $actual = $this->getTable()->get([
            'limit' => 1,
            'fields' => 'name,total_found',
        ]);        

        $expected = [
            1 => [
                'author_id' => 1,
                'name' => 'George Orwell',
            ],
            'total_found' => 18,
        ];
        $this->assertSame($expected, $actual);
    }
}
