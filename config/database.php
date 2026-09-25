<?php
declare(strict_types=1);

function officeos_db_connection(): ?mysqli
{
	$host = 'localhost';
	$user = 'root';
	$password = '';
	$databases = ['officeos_db', 'office_os'];

	foreach ($databases as $database) {
		try {
			$connection = @new mysqli($host, $user, $password, $database);

			if (!$connection->connect_errno) {
				$connection->set_charset('utf8mb4');
				return $connection;
			}
		} catch (Throwable $throwable) {
			continue;
		}
	}

	return null;
}