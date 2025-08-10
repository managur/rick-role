<?php

declare(strict_types=1);

namespace RickRole\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add role extensions table for hierarchical roles
 */
final class Version20240803144500_AddRoleExtensionsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role extensions table for hierarchical roles support';
    }

    public function up(Schema $schema): void
    {
        // Create the role extensions junction table using Doctrine's Schema API
        $table = $schema->createTable('rick_role_role_extensions');
        
        // Add columns with proper UUID type that works across all databases
        $table->addColumn('role_id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('extended_role_id', 'string', ['length' => 36, 'notnull' => true]);
        
        // Set primary key
        $table->setPrimaryKey(['role_id', 'extended_role_id']);
        
        // Add foreign key constraints
        $table->addForeignKeyConstraint(
            'rick_role_roles',
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            'rick_role_roles',
            ['extended_role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        
        // Add indexes for better performance
        $table->addIndex(['role_id'], 'IDX_ROLE_EXTENSIONS_ROLE_ID');
        $table->addIndex(['extended_role_id'], 'IDX_ROLE_EXTENSIONS_EXTENDED_ROLE_ID');
    }

    public function down(Schema $schema): void
    {
        // Drop the role extensions table
        $schema->dropTable('rick_role_role_extensions');
    }
} 