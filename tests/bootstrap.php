<?php

declare(strict_types=1);

// Set timezone to avoid warnings
date_default_timezone_set('UTC');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set up test environment
$_ENV['APP_ENV'] = 'test';
$_ENV['DATABASE_URL'] = 'sqlite:///:memory:';

// Initialize test database if needed
if (!defined('RICKROLE_TEST_DB_INITIALIZED')) {
    define('RICKROLE_TEST_DB_INITIALIZED', true);

    // Additional test setup can go here
    // - Database schema creation
    // - Test data seeding
    // - Mock configurations
}
