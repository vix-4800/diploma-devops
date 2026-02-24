<?php

declare(strict_types=1);

use App\Db;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__, 2);

if (is_file($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

$logDir = getenv('APP_LOG_DIR') ?: (dirname(__DIR__) . '/logs');
$logFile = getenv('APP_LOG_FILE') ?: ($logDir . '/app.log');

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$levelName = (string) (getenv('APP_LOG_LEVEL') ?: 'info');
try {
    $level = Level::fromName($levelName);
} catch (Throwable) {
    $level = Level::Info;
}

$logger = new Logger('api');
$logger->pushProcessor(new PsrLogMessageProcessor());
$logger->pushHandler(new StreamHandler($logFile, $level));

ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$app = AppFactory::create();

$app->add(static function (ServerRequest $request, RequestHandlerInterface $handler) use ($logger): Response {
    $start = microtime(true);

    $status = 500;

    try {
        $response = $handler->handle($request);
        $status = $response->getStatusCode();

        return $response;
    } finally {
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $requestId = (string) ($request->getAttribute('requestId') ?? '');
        $path = $request->getUri()->getPath();

        $logger->info('request {method} {path} {status} {duration}ms', [
            'requestId' => $requestId,
            'method' => $request->getMethod(),
            'path' => $path,
            'status' => $status,
            'duration' => $durationMs,
        ]);
    }
});

$app->add(static function (ServerRequest $request, RequestHandlerInterface $handler): Response {
    $requestId = bin2hex(random_bytes(8));
    $request = $request->withAttribute('requestId', $requestId);
    $response = $handler->handle($request);

    return $response->withHeader('X-Request-Id', $requestId);
});

$json = static function (array $data, int $status = 200): Response {
    $response = new SlimResponse($status);
    $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withHeader('Cache-Control', 'no-store');
};

$app->get('/health', static function (ServerRequest $request) use ($json): Response {
    $requestId = (string) ($request->getAttribute('requestId') ?? '');

    return $json([
        'status' => 'ok',
        'time' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'requestId' => $requestId,
    ]);
});

$app->get('/db/ping', static function () use ($json): Response {
    $pdo = Db::pdo();
    $pdo->query('SELECT 1');

    return $json(['status' => 'ok']);
});

$app->get('/products', static function () use ($json): Response {
    $pdo = Db::pdo();
    $stmt = $pdo->query('SELECT id, name, price FROM products ORDER BY id');

    return $json(['items' => $stmt->fetchAll()]);
});

$app->get('/products/{id}', static function (ServerRequest $request, array $args) use ($json): Response {
    $id = (int) ($args['id'] ?? 0);

    if ($id <= 0) {
        throw new HttpBadRequestException($request, 'Invalid id');
    }

    $pdo = Db::pdo();
    $stmt = $pdo->prepare('SELECT id, name, price FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        return $json(['error' => 'Not found'], 404);
    }

    return $json($row);
});

$app->post('/products', static function (ServerRequest $request) use ($json): Response {
    $payload = json_decode((string) $request->getBody(), true);

    if (!is_array($payload)) {
        $payload = [];
    }

    $name = is_string($payload['name'] ?? null) ? trim($payload['name']) : '';
    $price = $payload['price'] ?? null;

    if ($name === '' || !is_numeric($price)) {
        return $json(['error' => 'Invalid payload'], 400);
    }

    $pdo = Db::pdo();
    $stmt = $pdo->prepare('INSERT INTO products (name, price) VALUES (:name, :price) RETURNING id');
    $stmt->execute([':name' => $name, ':price' => (float) $price]);
    $id = (int) $stmt->fetchColumn();

    return $json(['id' => $id, 'name' => $name, 'price' => (float) $price], 201);
});

$app->put('/products/{id}', static function (ServerRequest $request, array $args) use ($json): Response {
    $id = (int) ($args['id'] ?? 0);

    if ($id <= 0) {
        throw new HttpBadRequestException($request, 'Invalid id');
    }

    $payload = json_decode((string) $request->getBody(), true);

    if (!is_array($payload)) {
        $payload = [];
    }

    $name = array_key_exists('name', $payload) ? (is_string($payload['name']) ? trim($payload['name']) : '') : null;
    $price = array_key_exists('price', $payload) ? $payload['price'] : null;

    if ($name === null && $price === null) {
        return $json(['error' => 'Nothing to update'], 400);
    }

    if ($name !== null && $name === '') {
        return $json(['error' => 'Invalid name'], 400);
    }

    if ($price !== null && !is_numeric($price)) {
        return $json(['error' => 'Invalid price'], 400);
    }

    $pdo = Db::pdo();

    $exists = $pdo->prepare('SELECT id FROM products WHERE id = :id');
    $exists->execute([':id' => $id]);

    if ($exists->fetchColumn() === false) {
        return $json(['error' => 'Not found'], 404);
    }

    $fields = [];
    $bind = [':id' => $id];

    if ($name !== null) {
        $fields[] = 'name = :name';
        $bind[':name'] = $name;
    }

    if ($price !== null) {
        $fields[] = 'price = :price';
        $bind[':price'] = (float) $price;
    }

    $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $update = $pdo->prepare($sql);
    $update->execute($bind);

    $out = $pdo->prepare('SELECT id, name, price FROM products WHERE id = :id');
    $out->execute([':id' => $id]);

    return $json($out->fetch() ?: ['id' => $id]);
});

$app->delete('/products/{id}', static function (ServerRequest $request, array $args) use ($json): Response {
    $id = (int) ($args['id'] ?? 0);

    if ($id <= 0) {
        throw new HttpBadRequestException($request, 'Invalid id');
    }

    $pdo = Db::pdo();
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        return $json(['error' => 'Not found'], 404);
    }

    return $json(['status' => 'deleted']);
});

$displayErrorDetails = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->setDefaultErrorHandler(static function (
    ServerRequest $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($json, $logger): Response {
    $requestId = (string) ($request->getAttribute('requestId') ?? '');

    $status = 500;

    if ($exception instanceof HttpNotFoundException) {
        $status = 404;
    }

    if ($exception instanceof HttpBadRequestException) {
        $status = 400;
    }

    $message = $displayErrorDetails ? $exception->getMessage() : 'Internal error';

    if ($status === 404) {
        $message = 'Not found';
    }

    if ($status === 400 && !$displayErrorDetails) {
        $message = 'Bad request';
    }

    $payload = ['error' => $message];

    if ($requestId !== '') {
        $payload['requestId'] = $requestId;
    }

    $logger->error('unhandled exception: {message}', [
        'requestId' => $requestId,
        'method' => $request->getMethod(),
        'path' => $request->getUri()->getPath(),
        'message' => $exception->getMessage(),
        'exception' => $exception,
    ]);

    return $json($payload, $status);
});

$app->run();
