COMPOSE = docker compose
COMPOSE_FILE = compose.yml
COMPOSE_NGINX = docker compose -f compose.nginx.yml

.PHONY: help up up-nginx down down-nginx build restart ps logs logs-nginx api-shell db-shell api-logs db-logs test-health test-db deps

help:
	@printf '%s\n' \
	  'Targets:' \
	  '  up         - build and start services' \
	  '  up-nginx   - build and start (via nginx)' \
	  '  down       - stop and remove services' \
	  '  down-nginx - stop and remove (via nginx)' \
	  '  build      - build api image' \
	  '  restart    - restart services' \
	  '  ps         - show containers' \
	  '  logs       - follow all logs' \
	  '  logs-nginx - follow logs (via nginx)' \
	  '  api-shell  - shell inside api container' \
	  '  db-shell   - psql shell inside db container' \
	  '  test-health - curl /health' \
	  '  test-db     - curl /db/ping' \
	  '  deps        - composer install (local)'

up:
	$(COMPOSE) -f $(COMPOSE_FILE) up -d --build

up-nginx:
	$(COMPOSE_NGINX) up -d --build

down:
	$(COMPOSE) -f $(COMPOSE_FILE) down

down-nginx:
	$(COMPOSE_NGINX) down

build:
	$(COMPOSE) -f $(COMPOSE_FILE) build api

restart:
	$(COMPOSE) -f $(COMPOSE_FILE) restart

ps:
	$(COMPOSE) -f $(COMPOSE_FILE) ps

logs:
	$(COMPOSE) -f $(COMPOSE_FILE) logs -f --tail=200

logs-nginx:
	$(COMPOSE_NGINX) logs -f --tail=200

api-shell:
	$(COMPOSE) -f $(COMPOSE_FILE) exec api sh

db-shell:
	$(COMPOSE) -f $(COMPOSE_FILE) exec db psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"

api-logs:
	$(COMPOSE) -f $(COMPOSE_FILE) logs -f --tail=200 api

db-logs:
	$(COMPOSE) -f $(COMPOSE_FILE) logs -f --tail=200 db

test-health:
	@curl -fsS http://localhost:8080/health > /dev/null
	@printf '%s\n' 'OK'

test-db:
	@curl -fsS http://localhost:8080/db/ping > /dev/null
	@printf '%s\n' 'OK'

deps:
	cd api && composer install
