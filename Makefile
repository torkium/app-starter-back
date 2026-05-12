COMPOSE = docker compose

ensure-env:
	@if [ ! -f .env ]; then \
		echo "The .env file is missing."; \
		echo "Have you initialized the project with make init?"; \
		exit 1; \
	fi

init:
	./scripts/init.sh

up: ensure-env
	$(COMPOSE) up --build -d

down: ensure-env
	$(COMPOSE) down

restart: ensure-env
	$(COMPOSE) up -d --force-recreate

ps: ensure-env
	$(COMPOSE) ps

config: ensure-env
	$(COMPOSE) config

logs: ensure-env
	$(COMPOSE) logs -f app

logs-workers: ensure-env
	$(COMPOSE) logs -f worker_default worker_mail worker_outbox outbox_dispatcher

sh: ensure-env
	$(COMPOSE) exec app sh

migrate: ensure-env
	$(COMPOSE) exec app php bin/console doctrine:migrations:migrate --no-interaction

messenger-setup: ensure-env
	$(COMPOSE) exec app php bin/console messenger:setup-transports --no-interaction

health: ensure-env
	curl -fsS http://localhost:8080/api/health

test: ensure-env
	$(COMPOSE) -f docker-compose.test.yml build app
	$(COMPOSE) -f docker-compose.test.yml run --rm app php bin/phpunit

build: ensure-env
	$(COMPOSE) build app

cc: ensure-env
	$(COMPOSE) exec app php bin/console cache:clear

outbox-consume: ensure-env
	$(COMPOSE) exec app php bin/console app:outbox:consume --batch=50

jwt-keys: ensure-env
	$(COMPOSE) exec app sh -c "mkdir -p config/jwt && openssl genrsa -out config/jwt/private.pem 4096 && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem"

.PHONY: ensure-env init up down restart ps config logs logs-workers sh migrate messenger-setup health test build cc outbox-consume jwt-keys
