<?php

namespace App\FileEditors\ConfigEditor;

class ConfigDefaults
{
    public static function mysql(): array
    {
        return [
            'driver' => 'mysql',
            'url' => "env('DATABASE_URL')",
            'host' => "env('DB_HOST', '127.0.0.1')",
            'port' => "env('DB_PORT', '3306')",
            'database' => "env('DB_DATABASE', 'forge')",
            'username' => "env('DB_USERNAME', 'forge')",
            'password' => "env('DB_PASSWORD', '')",
            'unix_socket' => "env('DB_SOCKET', '')",
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => "extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : []",
        ];
    }

    public static function pgsql(): array
    {
        return [
            'driver' => 'pgsql',
            'url' => "env('DATABASE_URL')",
            'host' => "env('DB_HOST', '127.0.0.1')",
            'port' => "env('DB_PORT', '5432')",
            'database' => "env('DB_DATABASE', 'forge')",
            'username' => "env('DB_USERNAME', 'forge')",
            'password' => "env('DB_PASSWORD', '')",
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];
    }
}
