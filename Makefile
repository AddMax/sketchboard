# Управление локальным окружением. Все команды выполняются в контейнерах.
DC := docker compose
PHP := $(DC) exec -T php
NODE := $(DC) exec -T frontend

.DEFAULT_GOAL := help
.PHONY: help init up down destroy restart build logs ps sh sh-node psql redis-cli migration migrate schema-validate cache-clear composer npm test test-back test-front health

help: ## Список доступных команд
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

init: ## Первый запуск: .env, сборка образов, старт, миграции
	@test -f .env || cp .env.example .env
	@sed -i "s/^UID=.*/UID=$$(id -u)/;s/^GID=.*/GID=$$(id -g)/" .env
	@# MTU берём у интерфейса, через который идёт трафик наружу:
	@# под VPN он меньше 1500, и с дефолтным MTU соединения из контейнеров зависают
	@MTU=$$(ip link show $$(ip route get 1.1.1.1 2>/dev/null | awk '{print $$5; exit}') 2>/dev/null | awk '{print $$5; exit}'); \
		case "$$MTU" in ''|*[!0-9]*) MTU=1420 ;; esac; \
		sed -i "s/^DOCKER_MTU=.*/DOCKER_MTU=$$MTU/" .env; \
		echo "MTU сети Docker: $$MTU"
	@# Секреты Centrifugo должны быть уникальными на каждой машине
	@grep -q '^CENTRIFUGO_API_KEY=замените' .env && \
		sed -i "s|^CENTRIFUGO_API_KEY=.*|CENTRIFUGO_API_KEY=$$(openssl rand -base64 36 | tr '+/' '-_' | tr -d '=')|" .env && \
		echo "сгенерирован CENTRIFUGO_API_KEY" || true
	@grep -q '^CENTRIFUGO_TOKEN_SECRET=замените' .env && \
		sed -i "s|^CENTRIFUGO_TOKEN_SECRET=.*|CENTRIFUGO_TOKEN_SECRET=$$(openssl rand -base64 36 | tr '+/' '-_' | tr -d '=')|" .env && \
		echo "сгенерирован CENTRIFUGO_TOKEN_SECRET" || true
	$(DC) build
	$(DC) up -d
	@echo "Приложение: http://localhost:$$(grep ^HTTP_PORT .env | cut -d= -f2)"

up: ## Поднять окружение
	$(DC) up -d

down: ## Остановить и удалить контейнеры
	$(DC) down

destroy: ## Остановить и удалить контейнеры вместе с данными
	$(DC) down -v

restart: ## Перезапустить окружение
	$(DC) restart

build: ## Пересобрать образы
	$(DC) build --no-cache

logs: ## Логи всех сервисов
	$(DC) logs -f --tail=100

ps: ## Состояние сервисов
	$(DC) ps

sh: ## Shell в php-контейнере
	$(DC) exec php bash

sh-node: ## Shell в контейнере фронтенда
	$(DC) exec frontend sh

psql: ## psql в контейнере postgres
	$(DC) exec postgres psql -U $$(grep ^POSTGRES_USER .env | cut -d= -f2) -d $$(grep ^POSTGRES_DB .env | cut -d= -f2)

redis-cli: ## redis-cli в контейнере redis
	$(DC) exec redis redis-cli

migration: ## Сгенерировать миграцию из изменений сущностей
	$(PHP) php bin/console doctrine:migrations:diff

migrate: ## Применить миграции
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

cache-clear: ## Сбросить кэш Symfony
	$(PHP) php bin/console cache:clear

composer: ## composer c=<аргументы>, например: make composer c="require symfony/mailer"
	$(PHP) composer $(c)

npm: ## npm c=<аргументы>, например: make npm c="install axios"
	$(NODE) npm $(c)

schema-validate: ## Сверить XML-маппинг домена со схемой БД
	$(PHP) php bin/console doctrine:schema:validate

test: test-back test-front ## Прогнать тесты домена на бэке и фронте

test-back: ## Юнит-тесты доменного слоя PHP (без БД и контейнера)
	$(PHP) vendor/bin/phpunit

test-front: ## Юнит-тесты доменного слоя фронтенда
	$(NODE) npm run test

health: ## Проверить связность стека через API
	@curl -sS http://localhost:$$(grep ^HTTP_PORT .env | cut -d= -f2)/api/health | tee /dev/null; echo
