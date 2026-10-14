# ============================================================================
#  Penderie — Makefile
# ----------------------------------------------------------------------------
#  Single entry point for the development stack and the quality toolchain.
#  Everything runs inside Docker: only Docker + Docker Compose v2 are needed
#  on the host.
#
#  Run `make` (or `make help`) to list every target.
# ============================================================================

SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE ?= docker compose
API     := $(COMPOSE) exec -T api
FE      := $(COMPOSE) exec -T frontend

# ----------------------------------------------------------------------------
#  Help
# ----------------------------------------------------------------------------

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z0-9_-]+:.*?##/ {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ----------------------------------------------------------------------------
#  Stack lifecycle
# ----------------------------------------------------------------------------

.PHONY: up
up: ## Build and start the development stack
	$(COMPOSE) up -d --build

.PHONY: down
down: ## Stop the stack (keeps the database volume)
	$(COMPOSE) down

.PHONY: restart
restart: ## Restart the stack without rebuilding
	$(COMPOSE) restart

.PHONY: ps
ps: ## Show the status of every service
	$(COMPOSE) ps

.PHONY: logs
logs: ## Tail the logs of every service
	$(COMPOSE) logs -f --tail=100

.PHONY: clean
clean: ## Stop the stack AND delete the database volume (data loss!)
	$(COMPOSE) down -v --remove-orphans

.PHONY: shell
shell: ## Open a shell in the API container
	$(COMPOSE) exec api bash

.PHONY: shell-fe
shell-fe: ## Open a shell in the frontend container
	$(COMPOSE) exec frontend bash

# ----------------------------------------------------------------------------
#  Guards — fail early with a readable message when the stack is not running
# ----------------------------------------------------------------------------

.PHONY: api-running
api-running:
	@$(COMPOSE) ps -q api | grep -q . || { \
		echo "The API container is not running. Start the stack with:  make up"; exit 1; }

.PHONY: fe-running
fe-running:
	@$(COMPOSE) ps -q frontend | grep -q . || { \
		echo "The frontend container is not running. Start the stack with:  make up"; exit 1; }

# ----------------------------------------------------------------------------
#  Install / database
# ----------------------------------------------------------------------------

.PHONY: install
install: ## Install backend and frontend dependencies
	$(API) composer install
	$(FE) npm install --no-audit --no-fund

.PHONY: migrate
migrate: | api-running ## Apply pending Doctrine migrations
	$(API) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: schema
schema: | api-running ## Validate the Doctrine mapping against the database
	$(API) php bin/console doctrine:schema:validate

# ----------------------------------------------------------------------------
#  Backend quality
# ----------------------------------------------------------------------------

.PHONY: cs
cs: | api-running ## Check the coding standard (PHP-CS-Fixer, dry run)
	$(API) composer cs:check

.PHONY: cs-fix
cs-fix: | api-running ## Fix the coding standard (PHP-CS-Fixer)
	$(API) composer cs:fix

.PHONY: stan
stan: | api-running ## Static analysis (PHPStan level 6)
	$(API) php bin/console cache:warmup --env=test -q
	$(API) composer stan

.PHONY: test
test: | api-running ## Backend unit and API tests (PHPUnit)
	$(API) composer test

.PHONY: coverage
coverage: | api-running ## Backend test coverage report (text)
	$(API) vendor/bin/phpunit --colors=never --coverage-text

.PHONY: audit
audit: | api-running ## Dependency vulnerabilities (Composer audit)
	$(API) composer audit --no-interaction

.PHONY: lint-symfony
lint-symfony: | api-running ## Symfony linters: container + YAML config
	$(API) php bin/console lint:container
	$(API) php bin/console lint:yaml config

.PHONY: validate
validate: | api-running ## Symfony validators: YAML + container + routing
	$(API) php bin/console lint:yaml config
	$(API) php bin/console lint:container
	$(API) php bin/console debug:router > /dev/null

# ----------------------------------------------------------------------------
#  Frontend quality
# ----------------------------------------------------------------------------

.PHONY: fe-lint
fe-lint: | fe-running ## Frontend lint (oxlint)
	$(FE) npm run lint

.PHONY: fe-typecheck
fe-typecheck: | fe-running ## Frontend type check (tsc)
	$(FE) npm run typecheck

.PHONY: fe-test
fe-test: | fe-running ## Frontend unit tests (Vitest)
	$(FE) npm test

.PHONY: fe-build
fe-build: | fe-running ## Frontend production build (tsc + vite)
	$(FE) npm run build

.PHONY: fe-format
fe-format: | fe-running ## Format the frontend (Prettier)
	$(FE) npm run format

.PHONY: fe-format-check
fe-format-check: | fe-running ## Check the frontend formatting (Prettier)
	$(FE) npm run format:check

.PHONY: lighthouse
lighthouse: fe-build ## Lighthouse audit: performance, accessibility, best practices, SEO
	$(FE) npx lhci collect
	$(FE) npx lhci assert
	$(FE) npx lhci upload
	@echo "HTML report: frontend/lighthouse-report/"

# ----------------------------------------------------------------------------
#  Aggregates
# ----------------------------------------------------------------------------

.PHONY: qa
qa: cs stan lint-symfony test audit fe-lint fe-typecheck fe-test ## Run the whole quality gate (mirrors CI)

.PHONY: fix
fix: cs-fix fe-format ## Auto-fix what the quality gate can fix

.PHONY: ci
ci: qa ## Alias of `qa` (what GitHub Actions runs)
