<?php

declare(strict_types=1);

namespace App\Tests;

use App\Db;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DbTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Db::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);
    }

    #[Test]
    public function throwsWhenDsnIsEmpty(): void
    {
        putenv('DB_DSN=');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('DB_DSN is required');

        Db::pdo();
    }

    #[Test]
    public function throwsOnInvalidDsn(): void
    {
        putenv('DB_DSN=invalid:host=nowhere;dbname=none');
        putenv('DB_USER=nobody');
        putenv('DB_PASSWORD=wrong');

        $this->expectException(PDOException::class);

        Db::pdo();
    }

    #[Test]
    public function returnsSameInstanceOnSubsequentCalls(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');

        $first = Db::pdo();
        $second = Db::pdo();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function configuresPdoWithCorrectAttributes(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');

        $pdo = Db::pdo();

        $this->assertSame(
            \PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(\PDO::ATTR_ERRMODE),
        );
        $this->assertSame(
            \PDO::FETCH_ASSOC,
            $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }
}
