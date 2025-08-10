<?php

declare(strict_types=1);

return [
    'table_storage' => [
        'table_name' => 'rick_role_migrations',
        'version_column_name' => 'version',
        'version_column_length' => 191,
        'executed_at_column_name' => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],
    'migrations_paths' => [
        'RickRole\Migrations' => __DIR__ . '/migrations',
    ],
    'all_or_nothing' => true,
    'transactional' => true,
    'check_database_platform' => false,
    'organize_migrations' => 'none',
    'connection' => null,
    'em' => null,
]; 