<?php declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use norm_test\db\Connection;
use norm_test\db\tables\BooksTable;

use PHPUnit\Framework\TestCase;


final class BooksTableTest extends TestCase {
    const BOOK_TO_REMOVE_TITLE = 'War and Peace';
    const BOOK_TO_REMOVE_AUTHOR_ID = 6;
    const BOOK_TO_REMOVE_PUBLISHED_YEAR = 1869;
    const BOOK_TO_REMOVE_GENRE = 'Historical Fiction';

    private $table;
    
    private function getTable() {
        if (empty($this->table)) {
            $this->table = new BooksTable(Connection::getInstance()); 
        }

        return $this->table;
    }

    private function getBookToRemoveId() {
        $bookToRemove = $this->getTable()->get([
            'title' => self::BOOK_TO_REMOVE_TITLE,
            'limit' => 1,
        ]);        

        return reset($bookToRemove)['book_id'];
    }

    public function testGetFirstBook(): void {
        $actual = $this->getTable()->get([
            'limit' => 1,
            'fields' => 'total_found',
        ]);        

        $expected = [
            1 => [
                'book_id' => 1,
                'title' => '1984',
                'author' => [
                    'author_id' => 1,
                    'first_name' => 'George',
                    'last_name' => 'Orwell',
                ],
            ],
            'total_found' => 23,
        ];
        $this->assertSame($expected, $actual);
    }

    public function testGetBooksByAuthorsBornAfter1900(): void {
        $actual = $this->getTable()->get([
            'birth_year' => '`>=`' . 1900,
            'order_by' => 'author_id',
            'limit' => 1, 
            'fields' => 'birth_year,total_found',
        ]);        

        $expected = [
            1 => [
                'book_id' => 1,
                'title' => '1984',
                'author' => [
                    'author_id' => 1,
                    'birth_year' => 1903,
                    'first_name' => 'George',
                    'last_name' => 'Orwell',
                ],
            ],
            'total_found' => 8,
        ];
        $this->assertSame($expected, $actual);
    }

    public function testGetUniqueGenres(): void {
        $actual = $this->getTable()->getUniqueGenres([
            'limit' => 3,
            'fields' => 'total_found',
        ]);        

        $expected = [
            [
                'genre' => 'Dystopian',
                'count' => 1,
            ],
            [
                'genre' => 'Romance',
                'count' => 2,
            ],
            [
                'genre' => 'Fantasy',
                'count' => 2,
            ],
            'total_found' => 18,
        ];
        $this->assertSame($expected, $actual);
    }

    public function testGetUniqueGenresFrom1950(): void {
        $actual = $this->getTable()->getUniqueGenres([
            'published_year' => '`>=`' . 1950,
            'limit' => 2,
            'fields' => 'total_found',
        ]);        

        $expected = [
            [
                'genre' => 'Fantasy',
                'count' => 1,
            ],
            [
                'genre' => 'Southern Gothic',
                'count' => 1,
            ],
            'total_found' => 6,
        ];
        $this->assertSame($expected, $actual);
    }

    public function testGetBookToRemoveTotalFound($expectedTotalFound = 1): void {
        $actualTotalFound = $this->getTable()->get([
            'title' => self::BOOK_TO_REMOVE_TITLE,
            'fields' => 'total_found',
        ]);        
        $actualTotalFound = !empty($actualTotalFound['total_found']) 
            ? $actualTotalFound['total_found'] 
            : 0;

        $this->assertSame($expectedTotalFound, $actualTotalFound);
    }

    public function testRemoveBook(): void {
        // get book total_found
        $this->testGetBookToRemoveTotalFound();


        // remove book
        $actualRowCnt = $this->getTable()->remove([
            'book_id' => $this->getBookToRemoveId(),
        ]);

        $expectedRowCnt = 1;
        $this->assertSame($expectedRowCnt, $actualRowCnt);


        // get book total_found
        $this->testGetBookToRemoveTotalFound(0);
    }

    public function testAddBookBack(): void {
        // get book total_found
        $this->testGetBookToRemoveTotalFound(0);


        // add book back 
        $actualAddedSuccessfully = $this->getTable()->set([
            'title' => self::BOOK_TO_REMOVE_TITLE,
            'author_id' => self::BOOK_TO_REMOVE_AUTHOR_ID,
            'published_year' => self::BOOK_TO_REMOVE_PUBLISHED_YEAR,
            'genre' => self::BOOK_TO_REMOVE_GENRE,
        ]);        

        $expectedAddedSuccessfully = true;
        $this->assertSame($expectedAddedSuccessfully, $actualAddedSuccessfully);


        // get book total_found
        $this->testGetBookToRemoveTotalFound();
    }
}
