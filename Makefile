# Mosannen — common operations.
# All application commands run inside the app container.

DC := docker compose
EXEC := $(DC) exec -T app

.DEFAULT_GOAL := help
.PHONY: help up down restart logs shell install migrate fresh seed test lint \
        build assets backup import import-dry ps

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Start the stack
	$(DC) up -d
	@echo "→ http://localhost:$${APP_PORT:-8080}"

down: ## Stop the stack
	$(DC) down

restart: ## Restart the stack
	$(DC) restart

ps: ## Show container status
	$(DC) ps

logs: ## Tail application logs
	$(DC) logs -f app queue scheduler

shell: ## Open a shell in the app container
	$(DC) exec app bash

install: ## First-time setup
	cp -n .env.example .env || true
	# The app image runs as www-data (uid 33).
	sudo chown -R 33:33 storage bootstrap/cache
	$(DC) up -d
	$(EXEC) php artisan key:generate
	$(EXEC) php artisan migrate --force
	$(EXEC) php artisan db:seed --force
	$(DC) --profile dev run --rm vite npm install
	$(DC) --profile dev run --rm vite npm run build
	@echo "→ http://localhost:$${APP_PORT:-8080}"

migrate: ## Run pending migrations
	$(EXEC) php artisan migrate

fresh: ## Drop everything and rebuild the database with demo data
	$(EXEC) php artisan migrate:fresh --seed

seed: ## Re-run seeders
	$(EXEC) php artisan db:seed

import-dry: ## Rehearse the legacy import, writing nothing
	$(EXEC) php artisan legacy:import --dry-run

import: ## Import the legacy SQL Server database
	$(DC) exec app php artisan legacy:import

backup: ## Write a database dump to storage/app/backups
	$(EXEC) php artisan clinic:backup

test: ## Run the test suite
	$(EXEC) php artisan test

lint: ## Format PHP with Pint
	$(EXEC) ./vendor/bin/pint

assets: ## Rebuild frontend assets
	$(DC) --profile dev run --rm vite npm run build

build: assets ## Alias for assets
