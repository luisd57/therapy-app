.PHONY: help build up down restart logs shell composer sf db-create db-migrate db-diff cache-clear test test-unit test-integration test-db-setup init

# Default target
help:
	@echo "Available commands:"
	@echo "  make build       - Build Docker containers"
	@echo "  make up          - Start Docker containers"
	@echo "  make down        - Stop Docker containers"
	@echo "  make restart     - Restart Docker containers"
	@echo "  make logs        - View Docker logs"
	@echo "  make shell       - Access PHP container shell"
	@echo "  make composer    - Run Composer command (use: make composer c='require package')"
	@echo "  make sf          - Run Symfony console command (use: make sf c='cache:clear')"
	@echo "  make db-create   - Create database"
	@echo "  make db-migrate  - Run database migrations"
	@echo "  make db-diff     - Generate migration from entities"
	@echo "  make cache-clear - Clear Symfony cache"
	@echo "  make test        - Run PHPUnit tests"
	@echo "  make init        - Initialize new Symfony project"

# Docker commands
build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

logs:
	docker-compose logs -f

shell:
	docker-compose exec php bash

# Composer command
composer:
	docker-compose exec php composer $(c)

# Symfony console command
sf:
	docker-compose exec php php bin/console $(c)

# Database commands
db-create:
	docker-compose exec php php bin/console doctrine:database:create --if-not-exists

db-migrate:
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

db-diff:
	docker-compose exec php php bin/console doctrine:migrations:diff

# Cache
cache-clear:
	docker-compose exec php php bin/console cache:clear

# Tests
test:
	docker-compose exec php vendor/bin/phpunit

test-unit:
	docker-compose exec php vendor/bin/phpunit --testsuite=Unit

test-integration:
	docker-compose exec php vendor/bin/phpunit --testsuite=Integration

test-db-create:
	docker-compose exec php php bin/console doctrine:database:create --env=test --if-not-exists

test-db-migrate:
	docker-compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

test-db-setup: test-db-create test-db-migrate

# Initialize project
init:
	docker-compose exec php bash /var/www/html/docker/scripts/init-project.sh
