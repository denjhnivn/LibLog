<?php
// Set LIBLOG_DB_PASSWORD in your web-server environment to the MySQL password.
function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        $password = getenv('LIBLOG_DB_PASSWORD');
        $connection = new PDO(
            'mysql:host=127.0.0.1;dbname=LibLog;charset=utf8mb4',
            'root',
            $password === false ? '' : $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    return $connection;
}
