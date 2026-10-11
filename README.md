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
use RickRole\Strategy\DenyWinsStrategy;
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
$rickConfig->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

$rick = new Rick($rickConfig);

// Check if a user is allowed to create a post
$userId = 123; // This is just the user ID from your application
$permission = 'create post';

if ($rick->allows($userId, $permission)) {
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

Every voter is allowed to abstain, but the final decision always allows or denies. If every voter abstains, both built-in
strategies deny (fail secure).

#### Custom Strategies

A strategy implements `StrategyInterface`. `decide()` gets the vote results in vote order and returns a `Decision`, which
always allows or denies. There are two ways to make one:

- `Decision::byVote($voteResult, $message)`: one of the votes decided. Pass that vote result itself. The decision and
  code are taken from it, and the reason names its voter. The message is optional and defaults to the vote's own.
- `Decision::byStrategy($voteResult)`: no single vote decided, for example because every voter abstained. The result
  must allow or deny.

```php
use RickRole\Strategy\Decision;
use RickRole\Strategy\StrategyInterface;
use RickRole\Voter\VoteResult;

final class LastDenyWinsStrategy implements StrategyInterface
{
    public function decide(array $voteResults): Decision
    {
        $deciding = null;
        foreach ($voteResults as $voteResult) {
            if ($voteResult->isDeny() || ($voteResult->isAllow() && $deciding === null)) {
                $deciding = $voteResult;
            }
        }

        if ($deciding === null) {
            return Decision::byStrategy(VoteResult::deny('No voter allowed or denied'));
        }

        return Decision::byVote($deciding);
    }

    public function shouldStopVoting(VoteResult $voteResult): bool
    {
        return false;
    }
}
```

`Rick` finds the deciding voter by the vote result's identity, so a strategy can filter or sort the vote results first.
`Decision::byVote()` throws an `InvalidArgumentException` for a vote that abstained, and `Rick` throws a
`LogicException` for a vote result that was not cast in this check, such as one the strategy made itself.

A voter can also use a strategy to combine its own results, as `DoctrineDefaultVoter` does with role permissions. In
that case, return `$decision->result` as the vote, or abstain when `$decision->hasNoDecidingVote()` to leave the
permission to other voters, as `DoctrineDefaultVoter` does.

### Reason Objects

Rick-Role provides detailed information about permission decisions through reason objects:

```php
// $post is any subject from your application
$reason = null;
$allowed = $rick->allows(userId: 123, to: 'edit post', onThis: $post, because: $reason);
//--------- More on these ☝️ named parameters later! ---------//

echo $reason->permission;    // 'edit post'
echo $reason->userId;        // 123
$reason->subject;            // $post object
echo $reason->decision;      // 'allow' or 'deny'
echo $reason->message;       // Human-readable reason from the strategy
$reason->code;               // Programmatic code from the deciding vote, or null
echo $reason->given();       // Message given by the deciding vote
$reason->decidingVote;       // The Vote that decided, or null if the strategy decided by itself

// Every vote that was cast, in vote order
foreach ($reason->votes as $vote) {
    echo $vote->voter . ': [' . $vote->decision . '] ' . $vote->message . "\n";
}

// Or all at once, one line per vote and then the final decision
echo $reason->trace();
```

Each check gives you a new reason, so reusing a `$reason` variable for several checks never mixes them up.

Voters can attach a code to their vote, so that you can act on why a permission was decided without parsing the
message. A code can be a string or a backed enum. The examples below use this enum:

```php
enum MfaReason: string
{
    case Mandatory = 'mandatory';
    case Cleanup = 'cleanup';
}
```

```php
if ($rick->disallows(userId: $userId, to: 'mfa.disable.submit', because: $reason)) {
    if ($reason->is(MfaReason::Mandatory)) {
        // Show the control disabled, with an explanation
    }
}

// Without named parameters
if ($rick->disallows($userId, 'mfa.disable.submit', null, $reason)) {
    if ($reason->wasDecidedBy(MfaVoter::class)) {
        echo $reason->given();
    }
}
```

`is()` only checks the final code, which is the deciding vote's code. Both built-in strategies choose the first vote
that matches the decision, so it depends on the order of your voters. To ask whether *any* voter gave a code, whatever
the order, use `has()`:

```php
if ($rick->allows(userId: $userId, to: 'mfa.disable.submit', because: $reason)) {
    if ($reason->has(MfaReason::Cleanup)) {
        // MfaVoter allowed this for cleanup, even if another voter allowed it first
    }
}
```

Voting stops early when the strategy has reached a decision that cannot change: on the first deny with
`DenyWinsStrategy`, and on the first allow with `AllowWinsStrategy`. Voters after that point never vote, so `has()` can't
see their codes. `wasComplete()` tells you whether every voter voted:

| Strategy            | Outcome                            | Every voter voted?                            |
|---------------------|------------------------------------|-----------------------------------------------|
| `DenyWinsStrategy`  | Allow                              | Always                                        |
| `DenyWinsStrategy`  | Deny because every voter abstained | Always                                        |
| `DenyWinsStrategy`  | Deny because a voter denied        | Only if the last voter was the first to deny  |
| `AllowWinsStrategy` | Deny                               | Always                                        |
| `AllowWinsStrategy` | Allow                              | Only if the last voter was the first to allow |

Because voting stops at the first deny, a decision made by `Rick` with `DenyWinsStrategy` never holds more than one deny.
When you call a strategy's `decide()` yourself with several denies, the first deny's code is used.

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

// $entityManager is your Doctrine EntityManager (see Database Setup below)

// Add voters to the stack
$config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

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

Don't forget that the optional _reason_ out-parameter is also available on the `because` named argument.

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
        string|\Stringable $permission,
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

Voters can also give their vote a code, which is passed through to the reason object. Using the `MfaReason` enum from
[Reason Objects](#reason-objects):

```php
return VoteResult::deny('MFA is mandatory, so it cannot be disabled', code: MfaReason::Mandatory);

// Without named parameters
return VoteResult::deny('MFA is mandatory, so it cannot be disabled', null, MfaReason::Mandatory);
```

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
interrogating the out-parameter `$reason` object in this example:
```php
// Get detailed reasoning for debugging
$reason = null;
$canEdit = $rick->allows(userId: $userId, to: 'edit post', onThis: $post, because: $reason);

if ($canEdit === false) {
    error_log((string) $reason);
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

The reason object explains a permission decision. See [Reason Objects](#reason-objects) for an example.

| Property       | Type                        | Description                                                         |
|----------------|-----------------------------|---------------------------------------------------------------------|
| `userId`       | `string\|int`               | The user that was checked                                           |
| `permission`   | `string`                    | The permission that was checked                                     |
| `subject`      | `mixed`                     | The subject passed as `onThis`                                      |
| `decision`     | `string`                    | `'allow'` or `'deny'`, never `'abstain'`                            |
| `message`      | `string`                    | The strategy's message for the decision                             |
| `code`         | `string\|\BackedEnum\|null` | The deciding vote's code, or the strategy's own code                |
| `votes`        | `list<Vote>`                | Every vote that was cast, in vote order                             |
| `decidingVote` | `?Vote`                     | The vote that decided, or `null` when the strategy decided by itself |

Each `Vote` has `voter` (the voter's class name), `decision` (`'allow'`, `'deny'` or `'abstain'`), `message` and
`code`, plus `isAllow()`, `isDeny()` and `isAbstain()`.

#### `isAllow(): bool` and `isDeny(): bool`

Check the final decision.

#### `is(string|\BackedEnum $code): bool`

Checks if the final code is this code. Codes are compared strictly, so a backed enum does not match its own string
value.

#### `isNot(string|\BackedEnum $code): bool`

The inverse of `is()`.

#### `has(string|\BackedEnum $code): bool`

Checks if the final code, or the code of any vote in this decision, is this code. A vote's code counts whether that vote
allowed, denied or abstained. Unlike `is()`, the result does not depend on voter order, but voters that did not vote
because voting stopped early are not seen (see `wasComplete()`). Codes are compared strictly, like `is()`. If `is()`
is true for a code, `has()` is too.

#### `hasNot(string|\BackedEnum $code): bool`

The inverse of `has()`.

#### `codes(): list<string|\BackedEnum>`

The codes given by the votes in this decision, in vote order. Votes without a code are skipped.

#### `wasComplete(): bool`

Checks if every configured voter voted, so that `has()`, `codes()` and `votes` cover the whole voter stack. It is false
when voting stopped early.

#### `wasNotComplete(): bool`

The inverse of `wasComplete()`.

#### `wasDecidedBy(string $voter): bool`

Checks if this voter class cast the deciding vote. The strategy chooses the deciding vote; both built-in strategies
choose the first vote that matches the final decision. It is `false` for every voter when the strategy decided by
itself, for example because every voter abstained.

#### `wasNotDecidedBy(string $voter): bool`

The inverse of `wasDecidedBy()`.

#### `given(): string`

Returns the message given by the deciding vote, or the strategy's message when no vote decided.

#### `trace(): string`

Returns one line per vote, in vote order, followed by a line for the final decision. Each line shows the decision, the
voter class, the message and the code, if there is one.

#### `toArray(): array`

Returns the decision and every vote as an array, which is useful for logging. It has the keys `user_id`, `permission`,
`decision`, `message`, `code`, `decided_by` (the deciding voter's class, or `null`), `complete` and `votes`. Each vote
has `voter`, `decision`, `message` and `code`.

## Permission Parameter Type

The `$permission` parameter in all permission-checking methods (e.g., `Rick::allows()`, `Rick::disallows()`, and all
`VoterInterface::vote()` implementations) accepts either a `string` or any object implementing the `Stringable`
interface.

### Example: Using a Stringable Permission

```php
use RickRole\Rick;
use RickRole\Configuration;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\DoctrineDefaultVoter;

class CustomPermission implements Stringable {
    public function __toString(): string {
        return 'custom-permission';
    }
}

// $entityManager is your Doctrine EntityManager (see Database Setup)
$config = new Configuration();
$config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));
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
use RickRole\Configuration;
use RickRole\Rick;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\DoctrineDefaultVoter;

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

// $entityManager is your Doctrine EntityManager (see Database Setup)
$config = new Configuration();
$config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));
$config->setLogger($logger);
$rick = new Rick($config);

// Now all permission checks will be logged
$rick->allows(123, 'create post');
```

**Log Output Example:**

```
[2025-01-15 10:30:45] rick-role.DEBUG: Voter decision {"user_id":123,"permission":"create post","voter":"RickRole\\Voter\\DefaultVoter","decision":"allow","message":"User has permission through role: editor","code":null}
[2025-01-15 10:30:45] rick-role.INFO: Permission check completed {"user_id":123,"permission":"create post","subject":"Post#123","decision":"allow","allowed":true,"duration_ms":2.45,"voter_count":1,"strategy":"RickRole\\Strategy\\DenyWinsStrategy","reason":"No deny votes found - User has permission through role: editor","code":null}
```

**Log Context Fields:**
- DEBUG (per-voter "Voter decision"):
  - `user_id`
  - `permission`
  - `voter`
  - `decision`
  - `message`
  - `code`

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
  - `code`

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