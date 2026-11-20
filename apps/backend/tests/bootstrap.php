<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// PHPUnit must always boot the kernel with the test environment, even when
// the Docker container has APP_ENV=dev in its inherited environment.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
putenv('APP_ENV=test');
putenv('APP_DEBUG=1');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

/*
 * Doctrine appends "_test" to DATABASE_URL's database name in the test
 * environment. Create that database on demand so local PHPUnit runs work
 * with an existing Docker volume as well as on a fresh CI database.
 */
$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
$database = is_string($databaseUrl) ? parse_url($databaseUrl) : false;

if (is_array($database) && ($database['scheme'] ?? null) === 'postgresql' && !empty($database['path'])) {
    $baseDatabase = ltrim($database['path'], '/');
    $testDatabase = $baseDatabase.'_test'.(getenv('TEST_TOKEN') ?: '');
    $host = $database['host'] ?? '127.0.0.1';
    $port = $database['port'] ?? 5432;
    $user = rawurldecode($database['user'] ?? '');
    $password = rawurldecode($database['pass'] ?? '');
    $query = [];
    parse_str($database['query'] ?? '', $query);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $testDatabase)) {
        throw new RuntimeException('The generated PHPUnit database name contains unsupported characters.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $baseDatabase);
    if (isset($query['sslmode'])) {
        $dsn .= ';sslmode='.$query['sslmode'];
    }

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $statement = $connection->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
    $statement->execute(['database' => $testDatabase]);

    if ($statement->fetchColumn() === false) {
        $connection->exec(sprintf('CREATE DATABASE "%s"', $testDatabase));
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
