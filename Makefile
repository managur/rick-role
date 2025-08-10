.PHONY: help up down build install test test-coverage test-unit test-integration analyse cs-check cs-fix migrate migrate-diff clean logs shell

# Colors for output
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

# Default target
help: ## Show this help message
	@echo "${GREEN}Rick-Role Development Commands${NC}"
	@echo "================================="
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "${YELLOW}%-20s${NC} %s\n", $$1, $$2}' $(MAKEFILE_LIST)



# Docker commands
up: ## Start development environment
	@echo "${GREEN}Starting Rick-Role development environment...${NC}"
	docker-compose up -d
	@echo "${GREEN}Environment is ready! 🎵${NC}"
	@echo "MySQL: localhost:3306"
	@echo "PostgreSQL: localhost:5432"

down: ## Stop development environment
	@echo "${YELLOW}Stopping Rick-Role development environment...${NC}"
	docker-compose down

build: ## Build Docker containers
	@echo "${GREEN}Building Rick-Role containers...${NC}"
	docker-compose build --no-cache

rebuild: down build up ## Rebuild and restart development environment

logs: ## Show container logs
	docker-compose logs -f

shell: ## Open shell in lib container
	docker-compose exec lib sh

# Dependency management
install: ## Install Composer dependencies
	@echo "${GREEN}Installing dependencies...${NC}"
	docker-compose run --rm composer install

update: ## Update Composer dependencies
	docker-compose run --rm composer update

composer-install: install ## Alias for install command

composer: ## Run composer with custom arguments (e.g., make composer ARGS="require symfony/console")
	docker-compose run --rm composer $(ARGS)

# Testing commands
test: ## Run all tests (use ARGS="--filter=pattern" for filtering)
	@echo "${GREEN}Running all tests...${NC}"
	docker-compose exec lib vendor/bin/phpunit $(ARGS)

test-coverage: ## Run tests with coverage report (use ARGS="--filter=pattern" for filtering)
	@echo "${GREEN}Running tests with coverage...${NC}"
	docker-compose exec lib vendor/bin/phpunit --coverage-html coverage --coverage-text $(ARGS)
	@echo "${GREEN}Coverage report generated in coverage/index.html${NC}"

test-unit: ## Run unit tests only (use ARGS="--filter=pattern" for filtering)
	@echo "${GREEN}Running unit tests...${NC}"
	docker-compose exec lib vendor/bin/phpunit tests/Unit $(ARGS)

test-integration: ## Run integration tests only (use ARGS="--filter=pattern" for filtering)
	@echo "${GREEN}Running integration tests...${NC}"
	docker-compose exec lib vendor/bin/phpunit tests/Integration $(ARGS)



test-watch: ## Run tests in watch mode
	docker-compose exec lib vendor/bin/phpunit --watch

# Code quality commands
analyse: ## Run PHPStan static analysis (strictest settings)
	@echo "${GREEN}Running PHPStan analysis (max level)...${NC}"
	docker-compose exec lib vendor/bin/phpstan analyse $(ARGS) --configuration=phpstan.neon --memory-limit 512M

phpstan: analyse ## Alias for analyse command

phpstan-baseline: ## Generate PHPStan baseline for existing issues
	@echo "${GREEN}Generating PHPStan baseline...${NC}"
	docker-compose exec lib vendor/bin/phpstan analyse --configuration=phpstan.neon --generate-baseline

phpstan-clear: ## Clear PHPStan result cache
	@echo "${GREEN}Clearing PHPStan cache...${NC}"
	docker-compose exec lib vendor/bin/phpstan clear-result-cache

# psalm: ## Run Psalm static analysis (disabled - no PHP 8.4 support yet)
#	@echo "${GREEN}Running Psalm analysis...${NC}"
#	docker-compose exec lib vendor/bin/psalm

lint: ## Run PHP_CodeSniffer (lint)
	docker-compose exec lib vendor/bin/phpcs

lint-fix: ## Auto-fix fixable issues with PHP Code Beautifier and Fixer
	docker-compose exec lib vendor/bin/phpcbf

quality: analyse lint ## Run all quality checks

# Database commands
migrate: ## Run database migrations
	@echo "${GREEN}Running database migrations...${NC}"
	docker-compose exec lib vendor/bin/doctrine-migrations migrate --no-interaction --configuration=migrations.php --db-configuration=migrations-db.php

migrate-status: ## Show migration status
	docker-compose exec lib vendor/bin/doctrine-migrations status --configuration=migrations.php --db-configuration=migrations-db.php

setup-db: migrate ## Setup database schema (create tables via migrations)

# Development helpers
clean: ## Clean up cache and generated files
	@echo "${GREEN}Cleaning up...${NC}"
	docker-compose exec lib rm -rf coverage/
	docker-compose exec lib rm -rf .phpunit.result.cache
	docker-compose exec lib composer clear-cache

reset-db: ## Reset database (DROP and re-run migrations; destructive)
	@echo "${RED}This will DROP ORM-managed tables.${NC}"	
	@read -p "Continue? (y/N): " confirm; \
	if [ "$$confirm" = "y" ] || [ "$$confirm" = "Y" ]; then \
		echo "${RED}Resetting database...${NC}"; \
		docker-compose exec lib php bin/reset-database.php; \
		$(MAKE) migrate; \
	else \
		echo "${YELLOW}Cancelled.${NC}"; \
	fi

# Git hooks
install-hooks: ## Install git hooks
	@echo "${GREEN}Installing git hooks...${NC}"
	cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit
	@echo "${GREEN}Git hooks installed! 🎵${NC}"

# CI/CD simulation
ci: install quality test-coverage ## Run CI pipeline locally

# GitHub Actions local testing with act
workflow-ci: ## Run CI workflow locally using act
	@echo "${GREEN}Running CI workflow locally with act...${NC}"
	@if [ -f "./bin/act" ]; then \
		./bin/act --job test --env-file env.act; \
	elif command -v act >/dev/null 2>&1; then \
		act --job test --env-file env.act; \
	else \
		echo "${RED}act is not installed. Run 'make setup-act' first.${NC}"; \
		exit 1; \
	fi

workflow-ci-full: ## Run full CI workflow locally (all jobs)
	@echo "${GREEN}Running full CI workflow locally with act...${NC}"
	@if [ -f "./bin/act" ]; then \
		./bin/act --env-file env.act; \
	elif command -v act >/dev/null 2>&1; then \
		act --env-file env.act; \
	else \
		echo "${RED}act is not installed. Run 'make setup-act' first.${NC}"; \
		exit 1; \
	fi

workflow-ci-dry: ## Run CI workflow in dry-run mode to see what would happen
	@echo "${GREEN}Running CI workflow in dry-run mode...${NC}"
	@if [ -f "./bin/act" ]; then \
		./bin/act --dryrun --env-file env.act; \
	elif command -v act >/dev/null 2>&1; then \
		act --dryrun --env-file env.act; \
	else \
		echo "${RED}act is not installed. Run 'make setup-act' first.${NC}"; \
		exit 1; \
	fi

install-act: ## Install act for local workflow testing
	@echo "${GREEN}Installing act for local workflow testing...${NC}"
	@if curl -s https://raw.githubusercontent.com/nektos/act/master/install.sh | bash; then \
		echo "${GREEN}act installed successfully! 🎵${NC}"; \
	else \
		echo "${YELLOW}Local installation failed. Trying direct download...${NC}"; \
		mkdir -p bin; \
		curl -L https://github.com/nektos/act/releases/latest/download/act_Linux_x86_64.tar.gz | tar -xz -C bin/ act; \
		chmod +x bin/act; \
		echo "${GREEN}act installed to ./bin/act! 🎵${NC}"; \
	fi
	@echo "Usage: make workflow-ci"

setup-act: ## Set up act with full configuration and testing
	@echo "${GREEN}Setting up act with full configuration...${NC}"
	./scripts/act-setup.sh

# Debugging
debug-config: ## Show current configuration
	@echo "${GREEN}Current configuration:${NC}"
	docker-compose exec lib php -i | grep -E "(xdebug|memory_limit|max_execution_time)"

xdebug-on: ## Enable Xdebug (coverage mode only)
	docker-compose exec lib sed -i 's/;zend_extension=xdebug/zend_extension=xdebug/' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
	docker-compose restart lib

xdebug-off: ## Disable Xdebug
	docker-compose exec lib sed -i 's/zend_extension=xdebug/;zend_extension=xdebug/' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
	docker-compose restart lib

xdebug-debug: ## Enable Xdebug debug mode for development
	docker-compose exec lib sh -c "echo 'xdebug.mode=coverage,debug' > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo 'xdebug.start_with_request=yes' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo 'xdebug.client_host=host.docker.internal' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo 'xdebug.client_port=9003' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
	docker-compose restart lib

# Security
security-check: ## Run security vulnerability check
	@echo "${GREEN}Checking for security vulnerabilities...${NC}"
	docker-compose exec lib composer audit

# Package management
package: ## Build package for distribution
	@echo "${GREEN}Building package...${NC}"
	rm -rf build/
	mkdir -p build/
	rsync -av --exclude-from=.gitignore --exclude='.git' --exclude='build' . build/rick-role/
	cd build && tar -czf rick-role.tar.gz rick-role/
	@echo "${GREEN}Package built: build/rick-role.tar.gz${NC}"

# Release helpers
tag: ## Create a new version tag
	@read -p "Enter version (e.g., v1.0.0): " version; \
	git tag -a $$version -m "Release $$version"; \
	git push origin $$version; \
	echo "${GREEN}Tagged version $$version${NC}"

release-notes: ## Generate release notes
	@echo "${GREEN}Generating release notes...${NC}"
	git log --pretty=format:"- %s" $(shell git describe --tags --abbrev=0)..HEAD > RELEASE_NOTES.md

# Backup and restore
backup-db: ## Backup database
	@echo "${GREEN}Backing up database...${NC}"
	docker-compose exec mysql mysqldump -u rickrole -prickrole_pass rickrole_test > backup.sql

restore-db: ## Restore database from backup
	@echo "${GREEN}Restoring database...${NC}"
	docker-compose exec -T mysql mysql -u rickrole -prickrole_pass rickrole_test < backup.sql

# CLI Management - Use ./rick instead of make targets
# Examples:
#   ./rick role list
#   ./rick role show admin
#   ./rick role create admin
#   ./rick permission list admin
#   ./rick permission add admin create_user
#   ./rick user roles user123
#   ./rick user assign admin user123




