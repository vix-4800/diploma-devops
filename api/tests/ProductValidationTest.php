<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductValidationTest extends TestCase
{
    /**
     * Replicates the validation logic from POST /products.
     *
     * @return array{valid: bool, name: string, price: float}
     */
    private static function validateProductPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            $payload = [];
        }

        $name = is_string($payload['name'] ?? null) ? trim($payload['name']) : '';
        $price = $payload['price'] ?? null;

        if ($name === '' || !is_numeric($price)) {
            return ['valid' => false, 'name' => $name, 'price' => 0.0];
        }

        return ['valid' => true, 'name' => $name, 'price' => (float) $price];
    }

    #[Test]
    public function validPayloadPassesValidation(): void
    {
        $result = self::validateProductPayload(['name' => 'Widget', 'price' => 9.99]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Widget', $result['name']);
        $this->assertSame(9.99, $result['price']);
    }

    #[Test]
    public function missingNameFailsValidation(): void
    {
        $result = self::validateProductPayload(['price' => 10.0]);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function emptyNameFailsValidation(): void
    {
        $result = self::validateProductPayload(['name' => '', 'price' => 10.0]);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function whitespaceOnlyNameFailsValidation(): void
    {
        $result = self::validateProductPayload(['name' => '   ', 'price' => 10.0]);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function missingPriceFailsValidation(): void
    {
        $result = self::validateProductPayload(['name' => 'Widget']);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function nonNumericPriceFailsValidation(): void
    {
        $result = self::validateProductPayload(['name' => 'Widget', 'price' => 'free']);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function nullPayloadFailsValidation(): void
    {
        $result = self::validateProductPayload(null);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function stringPricePassesIfNumeric(): void
    {
        $result = self::validateProductPayload(['name' => 'Widget', 'price' => '19.99']);

        $this->assertTrue($result['valid']);
        $this->assertSame(19.99, $result['price']);
    }

    #[Test]
    public function integerPricePassesValidation(): void
    {
        $result = self::validateProductPayload(['name' => 'Widget', 'price' => 10]);

        $this->assertTrue($result['valid']);
        $this->assertSame(10.0, $result['price']);
    }

    #[Test]
    public function nameTrimming(): void
    {
        $result = self::validateProductPayload(['name' => '  Widget  ', 'price' => 5.0]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Widget', $result['name']);
    }

    #[Test]
    #[DataProvider('invalidPayloadsProvider')]
    public function invalidPayloadsAllFail(mixed $payload): void
    {
        $result = self::validateProductPayload($payload);

        $this->assertFalse($result['valid']);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidPayloadsProvider(): iterable
    {
        yield 'empty array' => [[]];
        yield 'null' => [null];
        yield 'string' => ['not-an-array'];
        yield 'numeric name' => [['name' => 123, 'price' => 10]];
        yield 'null price' => [['name' => 'Widget', 'price' => null]];
        yield 'boolean price' => [['name' => 'Widget', 'price' => true]];
    }
}
