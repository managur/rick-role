# ![Managur](https://static.managur.com/images/managur_logo.png)<br>Rick-Role

> The Role Based Access Control library that's never gonna give you up, never gonna let you down, never run around and
> desert you

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## What is Rick-Role?

Rick-Role is a powerful, flexible Role Based Access Control (RBAC) library for PHP 8.4+ that provides a robust
permission system through a voter-based architecture. It allows you to define complex permission rules whilst maintaining
simplicity and performance.

### Core Concepts

**RBAC Fundamentals**: In Rick-Role, roles are containers that hold permissions. User IDs are assigned roles, and
through those roles, users gain access to specific permissions. Rick-Role does not store or manage user profile
data—only the mapping of user IDs to roles and the permissions those roles grant. All user information (name, email,
etc.) is managed by your application's own user system or IdP.

**Voter Architecture**: While simple permission checks are supported, Rick-Role offers you the opportunity to utilise a
stack of "voters" that each make decisions about permissions. Each voter can allow, deny, or abstain from a decision.
The final result is determined by a configurable strategy.

**Voting Strategies**: Rick-Role supports two strategies for combining voter decisions out of the box:
- **DenyWinsStrategy** (default): Deny decisions override allow decisions
- **AllowWinsStrategy**: Allow decisions override deny decisions

**Hierarchical Roles**: Rick-Role supports hierarchical roles where one role can extend another role to inherit its permissions. This allows you to create specialised roles that build upon existing ones. When evaluating access, permissions from direct and inherited roles are pooled, and the configured strategy (DenyWins or AllowWins) determines the outcome when there are conflicts.

### Attribute Based Access Control (ABAC)

Whilst Rick-Role is a Role Based Access Control system at its core, the voter system allows decisions to be made based on
other attributes, such as object state. This is implemented via your own voters which vote based not on whether a user
has a given permission, but based on the subject passed in through the `$onThis` parameter.

## Installation & Requirements

### Requirements
- PHP 8.4+
- Composer

### Installation

Install Rick-Role via Composer:

```bash
composer require managur/rick-role
```

## Quick Start

Here's a minimal example to get you started using a SQLite database:

```php
<?php

use RickRole\Rick;
use RickRole\Configuration;
use RickRole\Voter\DoctrineDefaultVoter;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

// Set up Doctrine with SQLite (simplest setup)
$paths = [__DIR__ . '/src/Entity'];
$isDevMode = true;

$dbParams = [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/database.sqlite',
];

$doctrineConfig = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
$connection = DriverManager::getConnection($dbParams, $doctrineConfig);
$entityManager = new EntityManager($connection, $doctrineConfig);

// Configure Rick with a default voter
$rickConfig = new Configuration();
$rickConfig->addVoter(new DoctrineDefaultVoter($entityManager));

$rick = new Rick($rickConfig);

// Check if a user is allowed to create a post
$userId = 123; // This is just the user ID from your application
$permission = 'create post';
$post = new BlogPost();

if ($rick->allows($userId, $permission, $post)) {
    echo "Permission granted!";
} else {
    echo "Access denied!";
}
```

**Note**: This example uses SQLite for simplicity. For production use with MySQL, PostgreSQL, or any other
Doctrine-supported DB, see the [Configuration](#configuration) section for database setup details.

This final example reads as "if Rick allows userId permission [on this] post". If `$permission` is a literal, or
named accordingly, this can be as clear as possible for anyone reviewing the code later.

## Core Concepts in Detail

### RBAC Fundamentals

Rick-Role follows a standard RBAC model:

1. **Permissions** are individual actions (e.g., 'create post', 'delete user')
2. **Roles** are containers that hold multiple permissions
3. **User IDs** are assigned one or more roles (Rick-Role does not store user profiles—just the userId-to-role mapping)
4. **Access** is granted through the role-permission relationship

> **Note:** Rick-Role does not manage user accounts or profiles. It only cares about the user ID (string or int) and
> which roles are assigned to that ID. All user information is managed by your application or IdP.

### Voter Architecture

Voters are the decision-makers in Rick-Role. Each voter can:

- **Allow**: Grant permission for the action
- **Deny**: Block permission for the action  
- **Abstain**: Decline to make a decision

Multiple voters form a "stack" that processes permission requests in order. The final decision depends on a combination
of the configured stack and your chosen strategy.

Abstention should be considered the default response unless an absolute allow or deny is necessary. This allows later
voters in the stack to make the appropriate decision instead.

It is recommended to run the `DoctrineDefaultVoter` (or equivalent) at the top—especially when using the default
`DenyWinsStrategy`, as this ensures that the user actually has the underlying permission, regardless of other checks
performed by other voters.

### Voting Strategies

#### DenyWinsStrategy (Default)

With the default strategy, deny decisions override allow decisions, meaning that later voters will not vote:

```php
// Example: 1 abstention, 1 allow, 1 deny, 1 more abstention = DENY (deny wins)
$config->setStrategy(new DenyWinsStrategy());

// Voter 1: Abstains
// Voter 2: Allows
// Voter 3: Denies (early return)
// Voter 4: Doesn't vote but would've abstained
// Result: DENY (deny overrides allow)
```

#### AllowWinsStrategy

With the allow-wins strategy, allow decisions override deny decisions and will also immediately return:

```php
// Example: 1 abstention, 1 allow, 1 deny, 1 more abstention = ALLOW (allow wins)
$config->setStrategy(new AllowWinsStrategy());

// Voter 1: Abstains
// Voter 2: Allows (early return)
// Voter 3: Doesn't vote but would've denied 
// Voter 4: Doesn't vote but would've abstained
// Result: ALLOW (allow overrides deny)
```

### Reason Objects

Rick-Role provides detailed information about permission decisions through reason objects:

```php
$reasons = null;
$allowed = $rick->allows(userId: 123, to: 'edit post', onThis: $post, because: $reasons);
//--------- More on these ☝️ named parameters later! ---------//

echo $reasons->permission;    // 'edit post'
echo $reasons->userId;        // 123
echo $reasons->subject;       // $post object
echo $reasons->voter;         // Voter class name
echo $reasons->decision;      // 'ALLOW', 'DENY', or 'ABSTAIN'
echo $reasons->message;       // Human-readable reason

// Access the complete decision chain
$current = $reasons;
while ($current) {
    echo "Voter: " . $current->voter . " - " . $current->message;
    $current = $current->previous;
}
```

**Note:** The `$reasons` parameter contains a chain of `Reason` objects, where each object represents a decision from a
voter in the stack. The `previous` property links to the previous voter's decision, creating a complete audit trail of
the permission check.

## Configuration

### Basic Setup

```php
use RickRole\Configuration;
use RickRole\Rick;
use RickRole\Voter\DoctrineDefaultVoter;
use RickRole\Strategy\DenyWinsStrategy;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = new Configuration();

$entityManager = YOUR_DOCTRINE_ENTITY_MANAGER_SETUP_CODE;

// Add voters to the stack
$config->addVoter(new DoctrineDefaultVoter($entityManager));

// Configure strategy (optional - DenyWinsStrategy is default)
$config->setStrategy(new DenyWinsStrategy());

// Configure logging (optional - NullLogger is used by default)
$logger = new Logger('rick-role');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$config->setLogger($logger);

$rick = new Rick($config);
```

### Database Setup

Rick-Role uses Doctrine ORM for database operations. Here are some setup examples:

#### SQLite (Better for development/testing)
```php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$paths = [__DIR__ . '/src/Entity'];
$isDevMode = true;

$dbParams = [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/database.sqlite',
];

$config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
$connection = DriverManager::getConnection($dbParams, $config);
$entityManager = new EntityManager($connection, $config);
```

#### MySQL (Better for production)
```php
$dbParams = [
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'rick_role',
    'user' => 'username',
    'password' => 'password',
    'charset' => 'utf8mb4',
];
```

#### PostgreSQL (Better for production)
```php
$dbParams = [
    'driver' => 'pdo_pgsql',
    'host' => 'localhost',
    'port' => 5432,
    'dbname' => 'rick_role',
    'user' => 'username',
    'password' => 'password',
    'charset' => 'utf8',
];
```

Then run migrations:
```bash
# Run migrations
vendor/bin/doctrine-migrations migrate --configuration=migrations.php
```

### Migration System

Rick-Role uses Doctrine Migrations for database schema management. The migration system includes:

- **Database-agnostic migrations**: Work across all supported database platforms
- **Proper naming convention**: `VersionYYYYMMDDHHMMSS_Description.php`
- **Rollback support**: Each migration includes proper `down()` methods
- **Configuration files**: `migrations.php` and `migrations-db.php` for setup

**Note:** If you are already using Doctrine migrations you may wish to merge our migrations into your own. If you do
not do this, you may need to run additional migrations in future if you update your Rick-Role installation.

**Available Migration Commands:**

From within the Rick-Role directory:
```bash
# Check migration status
vendor/bin/doctrine-migrations status --configuration=migrations.php

# Run migrations
vendor/bin/doctrine-migrations migrate --configuration=migrations.php

# Generate migration diff (shows SQL changes)
vendor/bin/doctrine-migrations diff --configuration=migrations.php
```

## Usage Examples

### Basic Permission Checks

```php
// Simple permission check
if ($rick->allows($userId, 'view dashboard')) {
    // Show dashboard
}

// Permission check with subject context
if ($rick->allows($userId, 'edit post', $post)) {
    // Allow editing this specific post
}

// Inverse check
if ($rick->disallows($userId, 'delete user')) {
    // User cannot delete users
}
```

### Named Arguments

The arguments in the `allows()` and `disallows()` methods are named in such a way that we believe they make your code
even more readable, and we recommend their use, though they are of course entirely optional. Compare these examples:

```php
// Not using named arguments
if ($rick->allows($userId, $permission, $post)) {
    echo "Permission granted!";
}

// Using named arguments
if ($rick->allows(userId: $userId, to: $permission, onThis: $post)) {
    echo "Permission granted!";
}

// Mixing named arguments
if ($rick->allows($userId, to: $permission, onThis: $post)) {
    echo "Permission granted!";
}

// Mixing named arguments further
if ($rick->allows($userId, $permission, onThis: $post)) {
    echo "Permission granted!";
}
```

Don't forget that the optional _reasons_ out-parameter is also available on the `because` named argument.

### Hierarchical Roles

Rick-Role supports hierarchical roles where one role can extend another role to inherit its permissions.

Here is an example using the Rick-Role CLI:

```bash
# Create a base admin role with full permissions
./rick role create -r admin -d "Full administrative access"
./rick permission add -r admin -p user_management -d allow
./rick permission add -r admin -p system_config -d allow
./rick permission add -r admin -p data_export -d allow

# Create a probationary admin role that extends admin and denies some permissions
./rick role create -r probationary-admin -d "Probationary admin with limited access"
./rick role extend -r probationary-admin -e admin
./rick permission add -r probationary-admin -p data_export -d deny
./rick permission add -r probationary-admin -p system_config -d deny
```

For more commands and options, see the CLI guide: [CLI.md](CLI.md).

**Hierarchical Role Rules:**
- Multiple inheritance is supported (a role can extend multiple roles)
- The current voting strategy applies when resolving allow/deny conflicts

### Custom Voters

Create custom voters for specialised permission logic:

```php
use RickRole\Voter\VoterInterface;
use RickRole\Voter\VoteResult;

class BusinessHoursVoter implements VoterInterface
{
    public function vote(
        string|int $userId,
        string $permission,
        mixed $subject = null
    ): VoteResult {
        // Only allow access during business hours
        $hour = (int) date('H');
        
        if ($hour >= 9 && $hour <= 17) {
            return VoteResult::allow('Access granted during business hours');
        }
        
        return VoteResult::deny('Access denied outside business hours');
    }
}

// Add to configuration
$config->addVoter(new BusinessHoursVoter());
```

Voters can optionally check the provided `$permission` and `$subject` to see whether they are designed to actually
perform the requested check. If not, they should return a `VoteResult::abstain()` to delegate to a different voter
within the configured stack.

### Complex Permission Scenarios

It's discouraged to check multiple permissions for a single operation, but we understand that it is sometimes necessary.
If that is the case, simply query Rick-Role for all relevant permissions and check that both are allowed:

```php
// Check multiple permissions
$canManageUsers = $rick->allows(userId: $userId, to: 'manage users');
$canDeleteUsers = $rick->allows(userId: $userId, to: 'delete user', onThis: $targetUser);

if ($canManageUsers && $canDeleteUsers) {
    // User can delete this specific user
}
```

You may also wish to perform an action based on the reason why the user was allowed (or denied), which you can do by
interrogating the out-parameter `$reasons` object in this example:
```php
// Get detailed reasoning for debugging
$reasons = null;
$canEdit = $rick->allows(userId: $userId, to: 'edit post', onThis: $post, because: $reasons);

if ($canEdit === false) {
    logPermissionDenial($reasons);
}
```

## API Reference

### Configuration Methods

#### `addVoter(VoterInterface $voter): self`

Add a single voter to the stack in order for it to assist Rick in making decisions.

#### `setVoters(array $voters): self`

Empty the voter stack and replace it with this array of voters.

#### `setStrategy(StrategyInterface $strategy): self`

Determine whether Rick should consider an allow to be more significant than a deny, or vice versa.

#### `setLogger(LoggerInterface $logger): self`

Configure a PSR-3 logger for audit logging. When a logger is provided, Rick-Role will log all permission checks with
detailed context.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('rick-role');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$config = new Configuration();
$config->setLogger($logger);
```

**Log Levels Used:**
- **INFO**: Final permission decisions (allow/deny)
- **DEBUG**: Individual voter decisions and reasoning chains
- **WARNING**: Denied permissions

### Rick Methods

#### `allows(string|int $userId, string|\Stringable $to, mixed $onThis = null, ?Reason &$because = null): bool`

Checks if a user has permission to perform an action.

- `$userId`: User identifier (string or integer)
- `$to`: Permission string (e.g., 'create post', 'delete user')
- `$onThis`: Optional subject context
- `$because`: Optional reason object passed by reference for detailed decision info

#### `disallows(string|int $userId, string|\Stringable $to, mixed $onThis = null, ?Reason &$because = null): bool`

Checks if a user does not have permission to perform an action.

#### `doesNotAllow(string|int $userId, string|\Stringable $to, mixed $onThis = null, ?Reason &$because = null): bool`

Alias for `disallows()` - alternative reading preference.

### Reason Object

The reason object provides detailed information about permission decisions:

```php
$reasons = null;
$allowed = $rick->allows(userId: 123, to: 'edit post', onThis: $post, because: $reasons);

echo $reasons->permission;    // 'edit post'
echo $reasons->userId;        // 123
echo $reasons->subject;       // $post object
echo $reasons->voter;         // Voter class name
echo $reasons->decision;      // 'ALLOW', 'DENY', or 'ABSTAIN'
echo $reasons->message;       // Human-readable reason

// Access the complete decision chain
$current = $reasons;
while ($current) {
    echo "Voter: " . $current->voter . " - " . $current->message;
    $current = $current->previous;
}
```

**Note:** The `$reasons` parameter contains a chain of `Reason` objects, where each object represents a decision from a
voter in the stack. The `previous` property links to the previous voter's decision, creating a complete audit trail of
the permission check.

## Permission Parameter Type

The `$permission` parameter in all permission-checking methods (e.g., `Rick::allows()`, `Rick::disallows()`, and all
`VoterInterface::vote()` implementations) accepts either a `string` or any object implementing the `Stringable`
interface.

### Example: Using a Stringable Permission

```php
use RickRole\Rick;
use RickRole\Configuration;

class CustomPermission implements Stringable {
    public function __toString(): string {
        return 'custom-permission';
    }
}

$config = new Configuration();
$rick = new Rick($config);
$permission = new CustomPermission();

if ($rick->allows(123, $permission)) {
    // User has the custom permission
}
```

This allows for more expressive permission objects, such as enums or value objects, as long as they implement
`__toString()`.

## Audit Logging

Rick-Role provides comprehensive audit logging through PSR-3 compatible loggers. When a logger is configured, Rick-Role
will log all permission checks with detailed context:

**Log Levels:**
- **INFO**: Final permission decisions (allow/deny) - useful for production monitoring
- **DEBUG**: Individual voter decisions and reasoning chains - useful for debugging
- **WARNING**: Denied permissions - useful for security monitoring

**Example with Monolog:**

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Create logger with multiple handlers
$logger = new Logger('rick-role');

// Log all levels to rotating file for debugging
$logger->pushHandler(new RotatingFileHandler(
    __DIR__ . '/logs/rick-role.log',
    30, // Keep 30 days of logs
    Logger::DEBUG
));

// Log only INFO and above to stdout for production
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$config = new Configuration();
$config->setLogger($logger);
$rick = new Rick($config);

// Now all permission checks will be logged
$rick->allows(123, 'create post', $post);
```

**Log Output Example:**

```
[2025-01-15 10:30:45] rick-role.DEBUG: Voter decision {"user_id":123,"permission":"create post","voter":"RickRole\\Voter\\DefaultVoter","decision":"allow","message":"User has permission through role: editor"}
[2025-01-15 10:30:45] rick-role.INFO: Permission check completed {"user_id":123,"permission":"create post","subject":"Post#123","decision":"allow","allowed":true,"duration_ms":2.45,"voter_count":1,"strategy":"RickRole\\Strategy\\DenyWinsStrategy","reason":"No deny votes found - User has permission through role: editor"}
```

**Log Context Fields:**
- DEBUG (per-voter "Voter decision"):
  - `user_id`
  - `permission`
  - `voter`
  - `decision`
  - `message`

- INFO (final "Permission check completed"):
  - `user_id`
  - `permission`
  - `subject`
  - `decision`
  - `allowed`
  - `duration_ms`
  - `voter_count`
  - `strategy`
  - `reason`

- Optional (DEBUG aggregate):
  - `voter_decisions`: Array of individual voter decisions

---

## CLI Usage

Rick-Role includes a command-line interface for managing roles, permissions, and user assignments. 

For detailed CLI usage instructions, please see [CLI.md](CLI.md).

## Development

For information about contributing to Rick-Role, setting up a development environment, and running tests, please see [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

If you discover any security-related issues, please see [SECURITY.md](SECURITY.md) for information on how to report them.

## License

Rick-Role is open-sourced software licensed under the [MIT license](LICENSE).

## Changelog

Please see the [GitHub releases](https://github.com/managur/rick-role/releases) for information on what has changed recently.

*Rick-Role: Because your application's security should never give you up, never let you down, never run around and desert you.* 