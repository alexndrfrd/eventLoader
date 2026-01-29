.PHONY: help build up down restart logs shell test test-coverage install clean

# Colors for output
GREEN  := \033[0;32m
YELLOW := \033[0;33m
NC     := \033[0m # No Color

help: ## Display this help message
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'

build: ## Build Docker containers
	docker-compose build

up: ## Start Docker containers
	docker-compose up -d

down: ## Stop Docker containers
	docker-compose down

restart: down up ## Restart Docker containers

logs: ## Show Docker logs
	docker-compose logs -f

shell: ## Access PHP container shell
	docker-compose exec php sh

test: ## Run PHPUnit tests
	docker-compose exec php bin/phpunit --testdox

test-coverage: ## Run tests with coverage report
	docker-compose exec php bin/phpunit --coverage-html coverage

test-unit: ## Run unit tests only
	docker-compose exec php bin/phpunit --testsuite Unit --testdox

test-integration: ## Run integration tests only
	docker-compose exec php bin/phpunit --testsuite Integration --testdox

install: ## Install composer dependencies
	docker-compose exec php composer install

composer-update: ## Update composer dependencies
	docker-compose exec php composer update

db-create: ## Create database
	docker-compose exec php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Run database migrations
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reset database (drop and recreate)
	docker-compose exec php bin/console doctrine:database:drop --force --if-exists
	docker-compose exec php bin/console doctrine:database:create
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

cache-clear: ## Clear Symfony cache
	docker-compose exec php bin/console cache:clear

clean: ## Clean up generated files
	rm -rf var/cache/* var/log/* coverage/

ps: ## Show running containers
	docker-compose ps

rebuild: down build up ## Rebuild and restart containers

fresh: down build up install db-reset ## Fresh installation (rebuild, install, reset DB)

loader: ## Run event loader (local mode)
	docker-compose exec php php bin/console app:event-loader

loader-distributed: ## Run event loader in distributed mode
	docker-compose exec php php bin/console app:event-loader --distributed

loader-distributed-sources: ## Run distributed loader with 3 sources
	docker-compose exec php php bin/console app:event-loader --distributed --sources=3

loader-with-data: ## Create events and run loader in same process (RECOMMENDED for testing)
	docker-compose exec php php bin/console app:loader-with-data

loader-with-data-distributed: ## Create events and run distributed loader in same process
	docker-compose exec php php bin/console app:loader-with-data --distributed --events=200 --sources=5 --iterations=20

clear-loader-state: ## Clear loader state from Redis
	docker-compose exec php php bin/console app:clear-loader-state

redis-cli: ## Access Redis CLI
	docker-compose exec redis redis-cli

redis-ping: ## Test Redis connection
	docker-compose exec redis redis-cli ping
