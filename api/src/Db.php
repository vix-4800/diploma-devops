<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = getenv('DB_DSN') ?: '';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';

        if ($dsn === '') {
            throw new PDOException('DB_DSN is required');
        }

        self::$pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}
