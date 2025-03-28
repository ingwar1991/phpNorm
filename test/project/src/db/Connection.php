<?php

namespace norm_test\db;

use ingwar1991\DBConnections\sql\MySqlConnection;


class Connection extends MySqlConnection {
    private static $instance;
    private static $dbConnInfo;

    public static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new static(
                self::getDBConnInfo(),
                getenv('MYSQL_DB'),                 
            );
        }

        return self::$instance;
    }

    private static function getDBConnInfo() {
        if (empty(self::$dbConnInfo)) {
            self::$dbConnInfo = [
                'host' => getenv('MYSQL_HOST'),
                'name' => getenv('MYSQL_DB'),
                'port' => getenv('MYSQL_PORT'),
                'user' => getenv('MYSQL_USER'),
                'password' => getenv('MYSQL_PASS'),
            ];
        }

        return self::$dbConnInfo;
    }
}
