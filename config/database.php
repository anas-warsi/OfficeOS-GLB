<?php
declare(strict_types=1);

function officeos_db_connection(): ?mysqli
{
    $host = getenv('OFFICEOS_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('OFFICEOS_DB_PORT') ?: '3306');
    $user = getenv('OFFICEOS_DB_USER') ?: 'root';
    $password = getenv('OFFICEOS_DB_PASS') ?: '';

    $candidates = [];
    $configuredName = trim((string) (getenv('OFFICEOS_DB_NAME') ?: ''));

    if ($configuredName !== '') {
        $candidates[] = $configuredName;
    }

    $candidates[] = 'officeos_db';
    $candidates[] = 'office_os';
    $candidates[] = 'officeos';

    mysqli_report(MYSQLI_REPORT_OFF);

    foreach (array_unique($candidates) as $database) {
        $connection = @new mysqli($host, $user, $password, $database, $port);

        if ($connection instanceof mysqli && $connection->connect_errno === 0) {
            $connection->set_charset('utf8mb4');
            return $connection;
        }
    }

    return null;
}
