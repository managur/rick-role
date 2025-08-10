<?php

declare(strict_types=1);

namespace RickRole\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create initial database schema for Rick-Role RBAC system
 */
final class Version20240803143000_CreateInitialSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial database schema for Rick-Role RBAC system including roles, users, and user roles tables';
    }

    public function up(Schema $schema): void
    {
        // Create roles table
        $rolesTable = $schema->createTable('rick_role_roles');
        $rolesTable->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $rolesTable->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $rolesTable->addColumn('description', 'text', ['notnull' => false]);
        $rolesTable->addColumn('permissions', 'json', ['notnull' => true]);
        $rolesTable->setPrimaryKey(['id']);
        $rolesTable->addUniqueIndex(['name'], 'UNIQ_F855E1595E237E06');

        // Create user roles table
        $userRolesTable = $schema->createTable('rick_role_user_roles');
        $userRolesTable->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $userRolesTable->addColumn('user_id', 'string', ['length' => 255, 'notnull' => true]);
        $userRolesTable->addColumn('role_id', 'string', ['length' => 36, 'notnull' => true]);
        $userRolesTable->addColumn('assigned_at', 'datetime_immutable', ['notnull' => true]);
        $userRolesTable->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $userRolesTable->setPrimaryKey(['id']);
        $userRolesTable->addIndex(['user_id'], 'IDX_USER_ROLES_USER_ID');
        $userRolesTable->addIndex(['role_id'], 'IDX_USER_ROLES_ROLE_ID');
        $userRolesTable->addIndex(['expires_at'], 'IDX_USER_ROLES_EXPIRES_AT');
        
        // Add foreign key constraint for user roles
        $userRolesTable->addForeignKeyConstraint(
            'rick_role_roles',
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $schema->dropTable('rick_role_user_roles');
        $schema->dropTable('rick_role_roles');
    }
} 