<?php

declare(strict_types=1);

if (!function_exists('scripts_load_env_file')) {
    function scripts_load_env_file(string $envPath): void
    {
        if (!is_file($envPath)) {
            return;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

scripts_load_env_file(dirname(__DIR__) . '/.env');

$host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('DB_PORT') ?: 3306);
$database = (string) (getenv('DB_DATABASE') ?: '');
$username = (string) (getenv('DB_USERNAME') ?: '');
$password = (string) (getenv('DB_PASSWORD') ?: '');

if ($database === '' || $username === '') {
    throw new RuntimeException('Missing DB settings. Set DB_DATABASE and DB_USERNAME in .env.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_errno) {
    throw new RuntimeException('MySQL connect failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
