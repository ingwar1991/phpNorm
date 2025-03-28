CREATE DATABASE IF NOT EXISTS norm_test_db;
USE norm_test_db;

CREATE TABLE IF NOT EXISTS authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    birth_year INT
);

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author_id INT,
    published_year INT,
    genre VARCHAR(100),
    FOREIGN KEY (author_id) REFERENCES authors(id),
    CONSTRAINT unique_title_author UNIQUE (title, author_id)
);

INSERT INTO authors (first_name, last_name, birth_year) VALUES
('George', 'Orwell', 1903),
('Jane', 'Austen', 1775),
('J.K.', 'Rowling', 1965),
('F. Scott', 'Fitzgerald', 1896),
('Harper', 'Lee', 1926),
('Leo', 'Tolstoy', 1828),
('Mark', 'Twain', 1835),
('Charles', 'Dickens', 1812),
('Agatha', 'Christie', 1890),
('Ernest', 'Hemingway', 1899),  
('Fyodor', 'Dostoevsky', 1821), 
('J.R.R.', 'Tolkien', 1892),    
('Ayn', 'Rand', 1905),          
('Joseph', 'Heller', 1923),     
('J.D.', 'Salinger', 1919),     
('Oscar', 'Wilde', 1854),       
('Mary', 'Shelley', 1797),      
('Aldous', 'Huxley', 1894); 

INSERT INTO books (title, author_id, published_year, genre) VALUES
('1984', 1, 1949, 'Dystopian'),
('Pride and Prejudice', 2, 1813, 'Romance'),
('Harry Potter and the Sorcerer\'s Stone', 3, 1997, 'Fantasy'),
('The Great Gatsby', 4, 1925, 'Tragedy'),
('To Kill a Mockingbird', 5, 1960, 'Southern Gothic'),
('War and Peace', 6, 1869, 'Historical Fiction'),
('The Adventures of Tom Sawyer', 7, 1876, 'Adventure'),
('A Tale of Two Cities', 8, 1859, 'Historical Fiction'),
('Murder on the Orient Express', 9, 1934, 'Crime Fiction'),
('Animal Farm', 1, 1945, 'Political Allegory'),
('Emma', 2, 1815, 'Romance'),
('The Casual Vacancy', 3, 2012, 'Contemporary Fiction'),
('The Old Man and the Sea', 10, 1952, 'Literary Fiction'),  
('David Copperfield', 8, 1850, 'Bildungsroman'),
('The Brothers Karamazov', 11, 1880, 'Philosophical Fiction'), 
('The Hobbit', 12, 1937, 'Fantasy'),                        
('The Fountainhead', 13, 1943, 'Philosophical Fiction'),     
('Catch-22', 14, 1961, 'Satire'),                            
('The Catcher in the Rye', 15, 1951, 'Realistic Fiction'),   
('Crime and Punishment', 11, 1866, 'Psychological Fiction'), 
('The Picture of Dorian Gray', 16, 1890, 'Gothic Fiction'),  
('Frankenstein', 17, 1818, 'Gothic Fiction'),                
('Brave New World', 18, 1932, 'Science Fiction');
