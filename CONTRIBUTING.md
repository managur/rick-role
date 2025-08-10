# ![Managur](https://static.managur.com/images/managur_logo.png)<br>Contributing to Rick-Role

Thank you for considering contributing to Rick-Role! We're excited to collaborate with you to make this library even
better.

## Code of Conduct

This project adheres to a Code of Conduct. By participating, you are expected to uphold this code.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, please
include:

- A clear and descriptive title
- Steps to reproduce the behaviour
- Expected behaviour
- Actual behaviour
- PHP version and environment details
- Code samples that demonstrate the issue

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- A clear and descriptive title
- A detailed description of the proposed enhancement
- Explanation of why this enhancement would be useful
- Code examples if applicable

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes
5. **Ensure all tests pass in Docker** (`make test`)
6. Ensure code coverage remains at 100% (`make test-coverage`)
7. Run code quality checks (`make analyse`, `make lint`)
8. Run security check (`make security-check`)
9. Commit your changes (`git commit -m 'Add amazing feature'`)
10. Push to the branch (`git push origin feature/amazing-feature`)
11. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 8.4+
- Docker (required for consistent testing)
- Make
- Git

### Setting Up Development Environment

1. Clone the repository:
   ```bash
   git clone https://github.com/managur/rick-role.git
   cd rick-role
   ```

2. Start the development environment:
   ```bash
   make up
   ```

3. Install dependencies:
   ```bash
   make install
   ```

4. Run the test suite to ensure everything is working:
   ```bash
   make test
   ```

### Available Make Commands

- `make up` - Start Docker development environment
- `make down` - Stop Docker development environment
- `make install` - Install Composer dependencies
- `make test` - Run PHPUnit tests in Docker
- `make test-coverage` - Run tests with coverage report in Docker
- `make test-unit` - Run unit tests only in Docker
- `make test-integration` - Run integration tests only in Docker
- `make test-unit ARGS="--filter=pattern"` - Run unit tests with filter
- `make analyse` - Run PHPStan static analysis in Docker
- `make lint` - Check code style in Docker
- `make lint-fix` - Fix code style issues in Docker
- `make quality` - Run all quality checks (analyse + lint)
- `make migrate` - Run database migrations in Docker
- `make migrate-status` - Show migration status in Docker
- `make security-check` - Run security vulnerability check
- `make workflow-ci` - Run CI workflow locally using act
- `make workflow-ci-full` - Run full CI workflow locally
- `make setup-act` - Set up act for local workflow testing

## Coding Standards

### PHP Standards

- Follow PSR-12 coding standards
- Use PHP 8.4+ features appropriately
- Type hints are required for all method parameters and return types
- Use strict types declaration (`declare(strict_types=1);`)
- Use British English spelling (e.g., "behaviour", "specialised", "whilst")

### Documentation Standards

- All public methods must have complete PHPDoc comments
- Include `@param` and `@return` annotations
- Document thrown exceptions with `@throws`
- Provide usage examples for complex functionality
- Use British English in all documentation

### Testing Standards

- **All tests must be run in Docker for consistency**
- Write tests for all new functionality
- Maintain 100% code coverage
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern
- Write both unit tests and integration tests where appropriate

### Database Standards

- Always create migrations for schema changes
- Use Doctrine annotations/attributes for entity mapping
- Follow naming conventions for tables and columns
- Add indexes for commonly queried columns

## Code Quality and Linting

- Always run `make lint` before committing any changes. This ensures code style and standards are enforced before code review or CI.
- Use the provided pre-commit git hook to automatically run `make lint`, `make quality`, and the local CI workflow via Act. Commits will be blocked if any of these checks fail.
- To install the hooks, run:

```bash
make install-hooks
``` 

## Testing

### Running Tests

**ALL TESTS MUST BE RUN IN DOCKER:**

```bash
# Run all tests in Docker
make test

# Run with coverage in Docker
make test-coverage

# Run specific test suite in Docker
make test-unit
make test-integration

# Run specific test file in Docker
docker-compose exec lib vendor/bin/phpunit tests/Unit/RickTest.php

# Run with filter in Docker
make test-unit ARGS="--filter=testCanMethodReturnsTrue"
```

### Writing Tests

#### Unit Tests

Place unit tests in `tests/Unit/`. Unit tests should:

- Test individual methods/classes in isolation
- Use mocks for dependencies
- Be fast and independent
- Test the Rick class and its interactions

Example:
```php
<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RickRole\Rick;
use RickRole\Configuration;

final class RickTest extends TestCase
{
    #[\Override]
    public function it_returns_true(): void
    {
        $config = $this->createMock(Configuration::class);
        $rick = new Rick($config);
        
        // Test implementation here
        $this->assertTrue($rick->allows(123, 'test permission'));
    }
}
```

#### Integration Tests

Place integration tests in `tests/Integration/`. Integration tests should:

- Test complete workflows
- Use real database connections (SQLite for CI)
- Test voter behaviour with actual data

### Code Coverage

We maintain 100% code coverage. Check coverage with:

```bash
make test-coverage
```

Open `coverage/index.html` to view the detailed coverage report.

## Git Workflow

### Branching Strategy

- `main` - Stable release branch
- `develop` - Development branch
- `feature/*` - Feature branches
- `bugfix/*` - Bug fix branches
- `hotfix/*` - Critical fixes for production

### Commit Messages

Use clear, descriptive commit messages:

```
Add user role assignment functionality

- Implement UserRole entity
- Add role assignment methods to User entity
- Include migration for user_roles table
- Add tests for role assignment logic
```

### Git Hooks

We use git hooks to maintain code quality:

- **pre-commit**: Runs code style checks and static analysis in Docker
- **pre-push**: Runs full test suite in Docker

Hooks are automatically installed when you run `make install-hooks`.

## Database Migrations

### Creating Migrations

```bash
# Generate migration for entity changes (in Docker)
make migrate-diff

# Create empty migration (in Docker)
docker-compose exec lib vendor/bin/doctrine-migrations generate
```

### Migration Best Practices

- Always review generated migrations before committing
- Include both up and down migration paths
- Test migrations on a copy of production data
- Document complex migrations
- Follow the naming convention: `VersionYYYYMMDDHHMMSS_Description.php`

## Documentation Requirements

- **All example code must work and be tested in Docker**
- Update README for new features
- Maintain API documentation
- Document breaking changes
- Provide usage examples
- Keep changelog current
- Document migration procedures
- Use British English spelling throughout

## Testing Philosophy

- **Docker-first**: All tests run in Docker for consistency
- **Coverage-driven**: Maintain 100% test coverage
- **Documentation-driven**: All examples must work
- **Security-focused**: Test all security scenarios

## Development Environment Details

### Docker Services

The development environment includes:

- **PHP 8.4** with Xdebug 3.4.5
- **MySQL 8.0** for primary database testing
- **PostgreSQL 15** for alternative database testing
- **SQLite 3** for fast unit testing
- **Nginx** for web server testing

### Environment Variables

Key environment variables for development:

```bash
# Database configuration
DB_HOST=mysql
DB_PORT=3306
DB_NAME=rick_role
DB_USER=rick_role
DB_PASS=secure_password

# Testing
TEST_DB_HOST=mysql
TEST_DB_NAME=rick_role_test
XDEBUG_MODE=coverage,debug
```

### Quality Gates

All code must pass these checks before commit:

```bash
make quality-gates  # Runs all quality checks

# Individual checks
make test           # 100% test coverage required
make analyse        # Level 8 analysis  
make lint           # PSR-12 compliance
make security-check # No vulnerabilities
```

## Getting Help

- Open an issue for bug reports or feature requests
- Join our discussions for questions and ideas
- Check existing documentation and tests for examples
- Contact us at developers@managur.io

## Release Process

### Pre-release Checklist

1. **Quality Gates**: All quality checks must pass
2. **Test Coverage**: 100% coverage maintained
3. **Documentation**: All examples tested and current
4. **Migration Testing**: All migrations tested on multiple databases
5. **Security Scan**: No vulnerabilities detected

### Version Management

```bash
# Bump version numbers
make version-bump

# Update changelog
make changelog

# Create Git tag
make tag-release
```

### Release Commands

```bash
# Pre-release testing
make quality-gates
make test-full
make build-release

# Create release
make tag
make package
```

## Troubleshooting

### Common Issues

**Docker Issues:**
```bash
make down && make clean  # Reset environment
make build --no-cache    # Rebuild from scratch
make logs service=php    # Check specific service logs
```

**Xdebug Issues:**
```bash
make xdebug-info        # Check Xdebug configuration
make php-info           # Full PHP information
```

**Database Issues:**
```bash
make db-reset           # Reset database completely
make db-health          # Check database connectivity
```

**Test Issues:**
```bash
make test-watch         # Watch for changes and re-run tests
make test-performance   # Performance benchmarks
```

## Performance Monitoring

```bash
make profile           # Generate performance profile
make benchmark         # Run performance benchmarks
make memory-check      # Check memory usage
```

## Security Practices

- Never commit secrets or credentials
- Use Docker secrets for sensitive data
- Regular security scans via `make security-check`
- Update dependencies regularly
- Test with multiple PHP versions

## Organisational Standards

- Organisation: Managur
- Repository: managur/rick-role
- Main class: Rick (final class)
- Contact: developers@managur.io
- Domain: managur.com
- All development in Docker containers
- 100% test coverage requirement
- Current documentation requirement
- British English spelling throughout
