<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $config = config();
        if ($config === null || empty($config['db']) || !is_array($config['db'])) {
            throw new RuntimeException('ยังไม่ได้ตั้งค่าการเชื่อมต่อฐานข้อมูล');
        }
        self::$pdo = self::connect($config['db'], true);
        return self::$pdo;
    }

    public static function connect(array $db, bool $withDatabase = true): PDO
    {
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $name = (string) ($db['name'] ?? '');
        $charset = (string) ($db['charset'] ?? 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        if ($withDatabase && $name !== '') {
            $dsn .= ';dbname=' . $name;
        }
        return new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function assertMariaDb(PDO $pdo): string
    {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!preg_match('/mariadb/i', $version)) {
            throw new RuntimeException('ต้องใช้ MariaDB 10 ขึ้นไป (พบ ' . $version . ')');
        }
        if (!preg_match('/(\d+)\.(\d+)/', $version, $match) || (int) $match[1] < 10) {
            throw new RuntimeException('ต้องใช้ MariaDB 10 ขึ้นไป (พบ ' . $version . ')');
        }
        return $version;
    }

    public static function createDatabase(PDO $pdo, string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('ชื่อฐานข้อมูลใช้ได้เฉพาะตัวอักษร ตัวเลข และขีดล่าง');
        }
        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $pdo->exec('USE `' . $name . '`');
    }
}
