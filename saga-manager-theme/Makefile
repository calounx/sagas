# Saga Manager Theme - Test Environment Makefile
#
# Quick commands for managing Docker test environment and running tests

.PHONY: help build up down restart logs shell test test-unit test-integration test-coverage clean install

# Default target
.DEFAULT_GOAL := help

# Colors for output
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

## help: Display this help message
help:
	@echo "$(CYAN)Saga Manager Theme - Test Environment$(NC)"
	@echo ""
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^## ' $(MAKEFILE_LIST) | sed 's/## /  /' | column -t -s ':'

## build: Build Docker containers
build:
	@echo "$(CYAN)Building Docker containers...$(NC)"
	docker-compose -f docker-compose.test.yml build

## up: Start Docker containers
up:
	@echo "$(CYAN)Starting Docker containers...$(NC)"
	docker-compose -f docker-compose.test.yml up -d
	@echo "$(GREEN)Containers started!$(NC)"
	@echo "WordPress: http://localhost:8080"
	@echo "Database: localhost:3307"

## down: Stop Docker containers
down:
	@echo "$(CYAN)Stopping Docker containers...$(NC)"
	docker-compose -f docker-compose.test.yml down

## restart: Restart Docker containers
restart: down up

## logs: View container logs
logs:
	docker-compose -f docker-compose.test.yml logs -f

## shell: Open shell in WordPress container
shell:
	docker-compose -f docker-compose.test.yml exec wordpress bash

## shell-phpunit: Open shell in PHPUnit container
shell-phpunit:
	docker-compose -f docker-compose.test.yml exec phpunit bash

## install: Install WordPress test suite and dependencies
install:
	@echo "$(CYAN)Installing dependencies...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit composer install
	@echo "$(CYAN)Installing WordPress test suite...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit bash -c "cd /tmp && install-wp-tests.sh wordpress_test wordpress wordpress db latest"
	@echo "$(GREEN)Installation complete!$(NC)"

## test: Run all tests
test:
	@echo "$(CYAN)Running all tests...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpunit

## test-unit: Run unit tests only
test-unit:
	@echo "$(CYAN)Running unit tests...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpunit --testsuite=unit

## test-integration: Run integration tests only
test-integration:
	@echo "$(CYAN)Running integration tests...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpunit --testsuite=integration

## test-coverage: Run tests with coverage report
test-coverage:
	@echo "$(CYAN)Running tests with coverage...$(NC)"
	docker-compose -f docker-compose.test.yml exec -e XDEBUG_MODE=coverage phpunit vendor/bin/phpunit --coverage-html tests/results/coverage-html
	@echo "$(GREEN)Coverage report: tests/results/coverage-html/index.html$(NC)"

## test-watch: Run tests in watch mode (requires entr)
test-watch:
	@echo "$(CYAN)Watching for file changes...$(NC)"
	find inc tests -name '*.php' | entr -c make test-unit

## lint: Run PHP CodeSniffer
lint:
	@echo "$(CYAN)Running PHP CodeSniffer...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpcs --standard=WordPress inc/

## lint-fix: Fix code style issues automatically
lint-fix:
	@echo "$(CYAN)Fixing code style issues...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpcbf --standard=WordPress inc/

## analyse: Run PHPStan static analysis
analyse:
	@echo "$(CYAN)Running PHPStan analysis...$(NC)"
	docker-compose -f docker-compose.test.yml exec phpunit vendor/bin/phpstan analyse inc/ --level=5

## clean: Clean up containers and volumes
clean:
	@echo "$(YELLOW)Warning: This will remove all containers and volumes!$(NC)"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker-compose -f docker-compose.test.yml down -v; \
		rm -rf tests/results/*; \
		echo "$(GREEN)Cleanup complete!$(NC)"; \
	fi

## reset-db: Reset test database
reset-db:
	@echo "$(CYAN)Resetting test database...$(NC)"
	docker-compose -f docker-compose.test.yml exec db mysql -uwordpress -pwordpress -e "DROP DATABASE IF EXISTS wordpress_test; CREATE DATABASE wordpress_test;"
	@echo "$(GREEN)Database reset!$(NC)"

## db-shell: Open MySQL shell
db-shell:
	docker-compose -f docker-compose.test.yml exec db mysql -uwordpress -pwordpress wordpress_test

## status: Show container status
status:
	@echo "$(CYAN)Container Status:$(NC)"
	docker-compose -f docker-compose.test.yml ps

## init: Complete setup (build, up, install, test)
init: build up install test
	@echo "$(GREEN)Environment ready! Run 'make test' to run tests.$(NC)"
