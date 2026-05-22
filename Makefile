SHELL := /usr/bin/env bash

# Convenience entry points. The `compose` indirection lets us swap to
# `docker compose -f docker-compose.prod.yml ...` later without touching
# the agent slash commands that reference these targets.

COMPOSE ?= docker compose

.PHONY: help up down build logs restart shell-backend shell-frontend test typecheck reseed reindex pint fmt verify

help:
	@awk 'BEGIN{FS=":.*##"; printf "Tours dev shortcuts:\n\n"} /^[a-zA-Z_-]+:.*?##/{printf "  %-18s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Bring the full stack up (postgres + embeddings + backend + frontend)
	$(COMPOSE) up -d --build

down: ## Stop the stack (preserve volumes)
	$(COMPOSE) down

build: ## Rebuild images without starting
	$(COMPOSE) build

logs: ## Tail backend / frontend / embeddings logs
	$(COMPOSE) logs -f backend frontend embeddings

restart: ## Restart all services
	$(COMPOSE) restart

shell-backend: ## Drop into the backend container
	$(COMPOSE) exec backend bash

shell-frontend: ## Drop into the frontend container
	$(COMPOSE) exec frontend sh

test: ## Run backend PHPUnit suite
	$(COMPOSE) exec -T backend php artisan test

typecheck: ## Run frontend TypeScript check
	$(COMPOSE) exec -T frontend npm run typecheck

reseed: ## Wipe and re-seed the database, then reindex
	$(COMPOSE) exec -T backend php artisan migrate:fresh --seed --force
	$(COMPOSE) exec -T backend php artisan tours:reindex

reindex: ## Re-compute embeddings for all tours
	$(COMPOSE) exec -T backend php artisan tours:reindex

pint: ## Format PHP with Pint
	$(COMPOSE) exec -T backend ./vendor/bin/pint

fmt: pint ## Alias for pint

verify: ## Smoke gates: backend tests + frontend typecheck + http probes
	@set -e; \
	$(COMPOSE) exec -T backend php artisan test --without-tty; \
	$(COMPOSE) exec -T frontend npm run typecheck --silent; \
	curl -fsS -o /dev/null -w 'api:%{http_code}\n' http://localhost:8000/api/health; \
	curl -fsS -o /dev/null -w 'web:%{http_code}\n' http://localhost:3000/; \
	echo "All gates green."
