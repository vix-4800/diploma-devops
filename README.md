# New diploma

Цель этого диплома — по шагам собрать DevOps-проект (app → docker/compose → nginx → ansible → terraform/ci).

## Требования

- PHP 8.2+ (PDO)
- Доступная БД (например, PostgreSQL)

## Быстрый старт

Для запуска приложения одной командой можно использовать `make`:

```bash
make init
```

## Docker

Запуск API + PostgreSQL через `docker compose`:

```bash
cp .env.example .env
docker compose -f compose.yml up -d --build
```

То же самое через `make`:

```bash
make up
make test-health
make test-db
```

### Запуск через nginx

Nginx будет reverse proxy для API (порт на хосте остаётся `8080`, но наружу торчит nginx, а не контейнер API).

```bash
docker compose -f compose.nginx.yml up -d --build
```

То же самое через `make`:

```bash
make up-nginx
```

Проверка:

```bash
curl -s http://localhost:8080/health
curl -s http://localhost:8080/db/ping
curl -s http://localhost:8080/products
```

## API

Все ответы — JSON, `Content-Type: application/json`.

## Логи

Логи пишутся в `api/logs/app.log` (в Docker эта папка примонтирована в контейнер).

Переменные:

- `APP_LOG_LEVEL` (по умолчанию `info`)
- `APP_LOG_DIR` (по умолчанию `api/logs`)
- `APP_LOG_FILE` (по умолчанию `api/logs/app.log`)

### `GET /health`

200

```json
{
  "status": "ok",
  "time": "2026-02-19T15:59:12+00:00",
  "requestId": "1c134ae3a9eb2aab"
}
```

### `GET /db/ping`

200 (если подключение к БД успешно)

```json
{ "status": "ok" }
```

### `GET /products`

200

```json
{
  "items": [
    { "id": 1, "name": "Book", "price": 10.5 }
  ]
}
```

### `GET /products/{id}`

200

```json
{ "id": 1, "name": "Book", "price": 10.5 }
```

404

```json
{ "error": "Not found" }
```

### `POST /products`

Тело запроса (JSON):

```json
{ "name": "Book", "price": 10.5 }
```

201

```json
{ "id": 1, "name": "Book", "price": 10.5 }
```

400

```json
{ "error": "Invalid payload" }
```

### `PUT /products/{id}`

Тело запроса (JSON): можно передать `name`, `price` или оба поля.

200

```json
{ "id": 1, "name": "Book", "price": 11.0 }
```

400

```json
{ "error": "Nothing to update" }
```

404

```json
{ "error": "Not found" }
```

### `DELETE /products/{id}`

200

```json
{ "status": "deleted" }
```

404

```json
{ "error": "Not found" }
```
