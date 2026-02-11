<?php
declare(strict_types=1);

if (!function_exists('schedule_db_connect')) {
    function schedule_db_connect(): ?PDO
    {
        $envFile = __DIR__ . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (getenv($name) === false) {
                    putenv($name . '=' . $value);
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }

        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: '';
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASS') ?: '';

        if ($dbName === '' || $dbUser === '') {
            return null;
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $dbUser, $dbPass, $options);
        } catch (Throwable $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            return null;
        }
    }
}

$pdo = schedule_db_connect();

return $pdo;