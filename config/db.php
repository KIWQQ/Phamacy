<?php
// config/db.php

function getDbConfig()
{
    return [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'dbname'  => getenv('DB_NAME') ?: 'pharmacy',
        'user'    => getenv('DB_USER') ?: 'root',
        'password'=> getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4'
    ];
}

function getPDO()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = getDbConfig();

    $dsn = "mysql:host={$config['host']};"
         . "port={$config['port']};"
         . "dbname={$config['dbname']};"
         . "charset={$config['charset']}";

    try {
        $pdo = new PDO(
            $dsn,
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => 'Database connection failed',
            'details' => $e->getMessage()
        ]);

        exit;
    }
}

function getMySQLi()
{
    static $mysqli = null;

    if ($mysqli !== null) {
        return $mysqli;
    }

    $config = getDbConfig();

    $mysqli = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['dbname'],
        $config['port']
    );

    if ($mysqli->connect_errno) {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => 'Database connection failed',
            'details' => $mysqli->connect_error
        ]);

        exit;
    }

    $mysqli->set_charset($config['charset']);

    return $mysqli;
}
