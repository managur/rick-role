# Rick-Role CLI Usage Guide

Rick-Role provides a flexible command-line interface for managing roles, permissions, and user-role assignments. This guide explains how to use the CLI effectively.

## Available Commands

The CLI provides the following main commands:

- `role` - Manage roles (list, create, delete, show, users, rename, extend, unextend)
- `permission` - Manage permissions (list, add, remove, allow, deny, toggle)
- `user` - Manage user-role assignments (assign, remove, roles, users)

Each command has several subcommands. Use the `--help` option to see available options for each command.

> **Note:** Role names must be unique. Attempting to create a role with an existing name will result in an error.

## Usage

Use `./rick` in this repository, or `vendor/bin/rick` when used as a dependency in another app:

```bash
# In this repository
./rick role list
./rick permission list admin
./rick user roles user123

# In a consuming app
vendor/bin/rick role list
vendor/bin/rick permission list admin
vendor/bin/rick user roles user123
```

Tip: In a consuming app, you can add a Composer script alias to shorten commands:

```json
{
  "scripts": {
    "rick": "@php vendor/bin/rick"
  }
}
```

Then run:

```bash
composer run rick -- role list
```

### Getting Help

```bash
# Show general usage
./rick

# Show available actions for each command type
./rick role
./rick permission
./rick user
```

Each command will show its available actions when run without arguments.

## Examples

### Role Management

```bash
# List all roles
./rick role list

# Create a new role
./rick role create -r admin

# Show role details
./rick role show -r admin

# Delete a role
./rick role delete -r admin

# Rename a role
./rick role rename -r admin -w administrator

# Show users assigned to a role
./rick role users -r admin
```

### Hierarchical Role Management

```bash
# Make a role extend another role (inherit permissions)
./rick role extend -r probationary-admin -e admin

# Remove role extension
./rick role unextend -r probationary-admin -e admin

# Show role with inherited permissions
./rick role show -r probationary-admin
```

### Permission Management

```bash
# List permissions for a role
./rick permission list -r admin

# Add permission to a role
./rick permission add -r admin -p create_user

# Remove permission from a role
./rick permission remove -r admin -p old_permission

# Toggle permission for a role
./rick permission toggle -r admin -p edit_post
```

### User Management

```bash
# Show roles for a user
./rick user roles -u user123

# Assign role to a user
./rick user assign -u user123 -r admin

# Remove role from a user
./rick user remove -u user123 -r admin

# Show all users assigned to a role
./rick user users -r admin
```

## Advanced Usage

The CLI supports additional options for more advanced scenarios. See the sections below for detailed examples of optional arguments and advanced features.

### Role Management with Descriptions

```bash
# Create a role with a description
./rick role create -r admin -d "Administrator role with full access"

# Create a role without description
./rick role create -r moderator
```

### Hierarchical Role Management

Rick-Role supports hierarchical roles where one role can extend another role to inherit its permissions. This is useful for creating specialized roles that build upon existing ones.

#### Creating Hierarchical Roles

```bash
# Create base admin role with full permissions
./rick role create -r admin -d "Full administrator access"
./rick permission add -r admin -p create_user -d allow
./rick permission add -r admin -p delete_user -d allow
./rick permission add -r admin -p manage_system -d allow

# Create probationary admin role that extends admin
./rick role create -r probationary-admin -d "Probationary admin with limited access"
./rick role extend -r probationary-admin -e admin

# Add specific restrictions to probationary admin
./rick permission add -r probationary-admin -p delete_user -d deny
./rick permission add -r probationary-admin -p manage_system -d deny
```

#### Managing Role Extensions

```bash
# Add multiple role extensions
./rick role extend -r manager -e admin
./rick role extend -r manager -e supervisor

# Remove a role extension
./rick role unextend -r manager -e supervisor

# Show role hierarchy
./rick role show -r manager
```

#### Permission Inheritance Rules

- **Multiple inheritance**: A role can extend multiple other roles
- **Strategy-based resolution**: When multiple inherited roles have conflicting permissions, the current strategy (DenyWinsStrategy or AllowWinsStrategy) determines the outcome

### Permission Management with Decisions

```bash
# Add a permission with explicit ALLOW decision
./rick permission add -r admin -p create_user -d allow

# Add a permission with explicit DENY decision
./rick permission add -r admin -p admin_access -d deny

# Toggle a permission (switches between ALLOW and DENY)
./rick permission toggle -r admin -p edit_post
```

### User Management with Expiration

```bash
# Assign a role with expiration date
./rick user assign -u user123 -r admin -e "2024-12-31"

# Assign a role with specific expiration date and time
./rick user assign -u user123 -r moderator -e "2024-12-31 23:59:59"

# Remove a role assignment
./rick user remove -u user123 -r admin
```

### Optional Arguments Reference

#### Role Commands
- `-r, --name NAME` - Specify role name (required for create, delete, show, users, rename, extend, unextend actions)
- `-d, --description DESCRIPTION` - Set a description for the role
- `-w, --new-name NEW-NAME` - Specify new role name (required for rename action)
- `-e, --extends ROLE-NAME` - Specify role to extend (required for extend/unextend actions)

#### Permission Commands  
- `-r, --role ROLE` - Specify role name (required for all actions)
- `-p, --permission PERMISSION` - Specify permission name (required for add, remove, toggle, allow, deny actions)
- `-d, --decision DECISION` - Set the permission decision (allow/deny)

#### User Commands
- `-u, --user-id USER-ID` - Specify user ID (required for assign, remove, roles actions)
- `-r, --role ROLE` - Specify role name (required for assign, remove, users actions)
- `-e, --expires-at EXPIRES-AT` - Set expiration date for role assignment (format: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)

### Date Format Examples

```bash
# Date only (expires at midnight)
./rick user assign -u user123 -r admin -e "2024-12-31"

# Date and time
./rick user assign -u user123 -r admin -e "2024-12-31 23:59:59"

# Current date plus 30 days
./rick user assign -u user123 -r admin -e "$(date -d '+30 days' +%Y-%m-%d)"
```

## Hierarchical Role Examples

### Example 1: Probationary Admin

```bash
# Create base admin role
./rick role create -r admin -d "Full administrator access"
./rick permission add -r admin -p user_management -d allow
./rick permission add -r admin -p system_config -d allow
./rick permission add -r admin -p data_export -d allow

# Create probationary admin that extends admin but with restrictions
./rick role create -r probationary-admin -d "Probationary admin with limited access"
./rick role extend -r probationary-admin -e admin
./rick permission add -r probationary-admin -p data_export -d deny
./rick permission add -r probationary-admin -p system_config -d deny

# Assign probationary role to user
./rick user assign -u newadmin -r probationary-admin -e "2024-12-31"
```

### Example 2: Multi-Level Hierarchy

```bash
# Create base roles
./rick role create -r basic-user -d "Basic user permissions"
./rick permission add -r basic-user -p read_content -d allow

./rick role create -r content-creator -d "Content creation permissions"
./rick role extend -r content-creator -e basic-user
./rick permission add -r content-creator -p create_content -d allow
./rick permission add -r content-creator -p edit_own_content -d allow

./rick role create -r moderator -d "Moderation permissions"
./rick role extend -r moderator -e content-creator
./rick permission add -r moderator -p edit_any_content -d allow
./rick permission add -r moderator -p delete_content -d allow

./rick role create -r admin -d "Full administrative access"
./rick role extend -r admin -e moderator
./rick permission add -r admin -p user_management -d allow
./rick permission add -r admin -p system_config -d allow
```

### Example 3: Temporary Override

```bash
# Create base role
./rick role create -r developer -d "Developer permissions"
./rick permission add -r developer -p read_code -d allow
./rick permission add -r developer -p write_code -d allow
./rick permission add -r developer -p deploy_staging -d allow

# Create temporary role for production access
./rick role create -r production-access -d "Temporary production access"
./rick role extend -r production-access -e developer
./rick permission add -r production-access -p deploy_production -d allow

# Assign with short expiration
./rick user assign -u developer123 -r production-access -e "2024-01-15"
```

## Best Practices

### Role Design
- **Keep roles focused**: Each role should have a clear, specific purpose
- **Use inheritance wisely**: Don't create deep inheritance chains that are hard to understand
- **Document role purposes**: Use descriptions to explain what each role is for
- **Test role combinations**: Verify that inherited permissions work as expected

### Permission Management
- **Use descriptive permission names**: Use names like `user:create` instead of just `create`
- **Be explicit about denies**: Use explicit DENY permissions to override inherited ALLOW permissions
- **Consider final decisions**: Explicit permissions can result in decisions that are cannot be overridden (such as a DENY in with the default strategy)
- **Review inherited permissions**: Regularly check what permissions roles actually have

### User Assignment
- **Use expiration dates**: Set appropriate expiration dates for temporary roles
- **Monitor role usage**: Regularly review which users have which roles
- **Clean up unused roles**: Remove role assignments that are no longer needed