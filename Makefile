DC      := docker compose
DC_PROD := docker compose -f compose.prod.yaml

.DEFAULT_GOAL := help
.PHONY: help up down restart build logs shell psql redis test pint fresh horizon prod-up prod-down prod-logs

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Start the dev stack
	$(DC) up -d

down: ## Stop the dev stack
	$(DC) down

restart: ## Restart the dev stack
	$(DC) restart

build: ## Rebuild images from scratch
	$(DC) build --no-cache

logs: ## Tail all logs
	$(DC) logs -f --tail=100

shell: ## Shell into the app container
	$(DC) exec app bash

psql: ## Open psql against the dev database
	$(DC) exec postgres psql -U $${DB_USERNAME:-vantage} -d $${DB_DATABASE:-vantage}

redis: ## Open redis-cli
	$(DC) exec redis redis-cli

horizon: ## Tail the queue worker
	$(DC) logs -f worker

test: ## Run the test suite in the container
	$(DC) exec app php artisan test

pint: ## Check formatting
	$(DC) exec app vendor/bin/pint --test

fresh: ## Drop, migrate and seed the dev database
	$(DC) exec app php artisan migrate:fresh --seed

prod-up: ## Deploy the production stack
	$(DC_PROD) up -d --build

prod-down: ## Stop the production stack
	$(DC_PROD) down

prod-logs: ## Tail production logs
	$(DC_PROD) logs -f --tail=100
