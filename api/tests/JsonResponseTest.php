<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class JsonResponseTest extends TestCase
{
    /** @return array{data: array<string, mixed>, status: int} */
    private static function buildJsonResponse(array $data, int $status = 200): array
    {
        $response = new Response($status);
        $response->getBody()->write(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $response = $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        $response->getBody()->rewind();
        $body = json_decode((string) $response->getBody(), true);

        return ['data' => $body, 'status' => $response->getStatusCode()];
    }

    #[Test]
    public function jsonResponseHasCorrectContentType(): void
    {
        $response = new Response();
        $response->getBody()->write((string) json_encode(['ok' => true]));
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        $this->assertSame(
            'application/json; charset=utf-8',
            $response->getHeaderLine('Content-Type'),
        );
    }

    #[Test]
    public function jsonResponseHasNoCacheHeader(): void
    {
        $response = new Response();
        $response = $response->withHeader('Cache-Control', 'no-store');

        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    #[Test]
    public function jsonResponseEncodesDataCorrectly(): void
    {
        $result = self::buildJsonResponse(['status' => 'ok', 'count' => 42]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('ok', $result['data']['status']);
        $this->assertSame(42, $result['data']['count']);
    }

    #[Test]
    public function jsonResponsePreservesUnicode(): void
    {
        $data = ['name' => 'Тест'];
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Тест', (string) $encoded);
        $this->assertStringNotContainsString('\u', (string) $encoded);
    }

    #[Test]
    public function jsonResponsePreservesSlashes(): void
    {
        $data = ['url' => 'https://example.com/path'];
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('https://example.com/path', (string) $encoded);
    }

    #[Test]
    public function jsonResponseReturnsCustomStatusCode(): void
    {
        $result = self::buildJsonResponse(['error' => 'Not found'], 404);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Not found', $result['data']['error']);
    }

    #[Test]
    #[DataProvider('statusCodeProvider')]
    public function jsonResponseSupportsVariousStatusCodes(int $code): void
    {
        $result = self::buildJsonResponse(['ok' => true], $code);

        $this->assertSame($code, $result['status']);
    }

    /** @return iterable<string, array{int}> */
    public static function statusCodeProvider(): iterable
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '400 Bad Request' => [400];
        yield '404 Not Found' => [404];
        yield '500 Internal Server Error' => [500];
    }
}
