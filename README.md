# norm 

Norm is a PHP library that provides a convenient way to represent SQL tables as PHP classes, 
with built-in methods for performing common database operations like SELECT, INSERT, UPDATE, and DELETE. 

This library simplifies interaction with your database by providing intuitive methods for handling CRUD (Create, Read, Update, Delete) 
operations without writing boilerplate SQL code.

### Key Features:
* **CRUD Operations**: Built-in methods for SELECT, INSERT, UPDATE, and DELETE operations, reducing repetitive code.
* **Flexible Querying**: Support for custom queries and filtering, so you can extend functionality as needed.
* **Easy Integration**: Seamlessly integrates into your existing PHP project for faster development.

### Available Conditions 
Here's the list of the various conditions supported by the library for filtering and querying data.

#### Equality Conditions
- **`!`**: **Not Equal To** (`<>`)
    - Matches values that are not equal to the specified value.
    - **Example**: ```"column" => "`!`value"``` finds values that are not equal to `"value"`.

- **`>`**: **Greater Than**
    - Matches values greater than the specified value.
    - **Example**: ```"column" => "`>`10"``` finds values greater than `10`.

- **`<`**: **Less Than**
    - Matches values less than the specified value.
    - **Example**: ```"column" => "`<`10"``` finds values less than `10`.

- **`>=`**, **`=>`**: **Greater Than or Equal To**
    - Matches values greater than or equal to the specified value.
    - **Example**: ```"column" => "`>=`10"``` or `"column" => 10` finds values greater than or equal to `10`.

- **`<=`**, **`=<`**: **Less Than or Equal To**
    - Matches values less than or equal to the specified value.
    - **Example**: ```"column" => "`<=`10"``` or `"column" =< 10` finds values less than or equal to `10`.

#### Range Conditions
- **`<<`**, **`>>`**: **BETWEEN**
    - Matches values that fall within a specified range.
    - **Example**: ```"column" => "`<<`5,10"``` finds values between `5` and `10` inclusive.

#### In Conditions
- **`[]`**: **IN**
    - Matches values that are within a specified set.
    - **Example**: ```"column" => "`[]`pattern1,pattern2,pattern3"``` finds values that are either `pattern1`, `pattern2`, or `pattern3`.

- **`![]`**: **NOT IN**
    - Matches values that are not within a specified set.
    - **Example**: ```"column" => "`![]`pattern1,pattern2,pattern3"``` finds values that are neither `pattern1`, `pattern2`, nor `pattern3`.

#### Null Conditions
- **`is_null`**: **IS NULL**
    - Matches values that are `NULL`.
    - **Example**: ```"column" => "`is_null`"``` finds rows where `"column"` is `NULL`.

- **`not_null`**: **IS NOT NULL**
    - Matches values that are not `NULL`.
    - **Example**: ```"column" => "`not_null`"``` finds rows where `"column"` is not `NULL`.

#### Like Conditions
- **`~`**: **LIKE**
    - Matches values that are similar to the specified string.
    - **Example**: ```"column" => "`~`pattern"``` finds values that contain `"pattern"`.

- **`!~`**: **NOT LIKE**
    - Matches values that do not match the specified string.
    - **Example**: ```"column" => "`!~`pattern"``` finds values that do not contain `"pattern"`.

#### Bulk Like Conditions
- **`~[]`**: **Bulk LIKE** (OR condition)
    - Matches values that are similar to any of the specified patterns.
    - Example: ```"column" => "`~[]`pattern1,pattern2,pattern3"``` finds values that contain either of `"pattern1"`, `"pattern2"`, `"pattern3"`.

- **`!~[]`**: **Bulk NOT LIKE** (OR condition)
  - Matches values that do not match any of the specified patterns.
  - Example: ```"column" => "`!~[]`pattern1,pattern2,pattern3"``` finds values that contain neither of `"pattern1"`, `"pattern2"`, `"pattern3"`.

##### Like Conditions Note:
Every `LIKE` condition (`~`, `!~`, `~[]`, `!~[]`) can accept `%` at the beginning and end of the value. 
If not provided, the library will automatically add `%` both at the start and end of the value.
**Example**:
  - ```"column" => "`~`pattern"``` || ```"column" => "`~`%pattern%"``` would behave like `column LIKE "%pattern%"`
  - ```"column" => "`~`pattern%"``` would behave like `column LIKE "pattern%"`
  - ```"column" => "`~`%pattern"``` would behave like `column LIKE "%pattern"`

## Tests (Demo)
The `/test` directory is not part of the core library but serves as a demonstration and testing ground for its functionality. 
It also provides an example of how to integrate and use the library in your own projects.

### Running Tests
To run the tests, you need to have Docker installed. Follow these steps:

1. Navigate to the `/test` directory.
2. Run the following command to start the containers and execute the tests:

```bash
./start.sh -t
```

#### `start.sh` Parameters
The `start.sh` script supports the following options:

- `-d`: Start the containers and detach from the terminal.
- `-b`: Start the containers and open a bash shell inside the PHP container.
- `-t`: Start the containers, run the tests, and stop the containers on success. If an error occurs, the script will drop you into the PHP container’s bash shell.
- `-s`: Stop the running containers.

### Library Usage Examples
You can refer to the `test/project/src` directory for examples of how to use the 
`ingwar1991/php_db_connections` and `ingwar1991/php_norm` libraries.

- `test/project/src/db/Connection.php`: Demonstrates a simple **MySQL** database connection.

- `test/project/src/db/tables/AuthorsTable.php`: Example of a **MySQL** table with **read-only** functionality. 
It includes custom **hydration** logic that adds a non-existing field `name`, constructed from the `first_name` and `last_name` fields.

- `test/project/src/db/tables/BooksTable.php`: A full **CRUD** example for a **MySQL** table, with an overloaded **GET** method that 
performs a join with the `authors` table, enabling queries by its fields. Additionally, the **hydration** logic is customized to group 
the `authors` data into a separate key within the resulting structure.
