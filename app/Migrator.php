<?php
declare(strict_types=1);

final class Migrator
{
    public static function directory(): string
    {
        return app_root() . '/database/migrations';
    }

    public static function ensureTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                version VARCHAR(191) NOT NULL,
                name VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_schema_migrations_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function files(): array
    {
        $paths = glob(self::directory() . '/*.php') ?: [];
        sort($paths, SORT_STRING);
        return array_values($paths);
    }

    public static function applied(PDO $pdo): array
    {
        self::ensureTable($pdo);
        $rows = $pdo->query('SELECT version, name, checksum, applied_at FROM schema_migrations ORDER BY version')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['version']] = $row;
        }
        return $map;
    }

    public static function catalog(PDO $pdo): array
    {
        $applied = self::applied($pdo);
        $items = [];
        foreach (self::files() as $path) {
            $version = basename($path);
            $meta = self::load($path);
            $checksum = hash_file('sha256', $path) ?: '';
            $row = $applied[$version] ?? null;
            $items[] = [
                'version' => $version,
                'name' => $meta['name'],
                'statements' => $meta['statements'],
                'checksum' => $checksum,
                'applied' => $row !== null,
                'applied_at' => $row['applied_at'] ?? null,
                'checksum_match' => $row === null || hash_equals((string) $row['checksum'], $checksum),
            ];
        }
        return $items;
    }

    public static function pending(PDO $pdo): array
    {
        return array_values(array_filter(
            self::catalog($pdo),
            static fn (array $item): bool => !$item['applied']
        ));
    }

    public static function run(PDO $pdo, ?string $version = null): array
    {
        self::ensureTable($pdo);
        $ran = [];
        foreach (self::catalog($pdo) as $item) {
            if ($item['applied']) {
                continue;
            }
            if ($version !== null && $item['version'] !== $version) {
                continue;
            }
            foreach ($item['statements'] as $sql) {
                $pdo->exec($sql);
            }
            $insert = $pdo->prepare(
                'INSERT INTO schema_migrations (version, name, checksum, applied_at) VALUES (:version, :name, :checksum, NOW())'
            );
            $insert->execute([
                'version' => $item['version'],
                'name' => $item['name'],
                'checksum' => $item['checksum'],
            ]);
            $ran[] = $item['version'];
            if ($version !== null) {
                break;
            }
        }
        if ($version !== null && $ran === []) {
            throw new RuntimeException('ไม่พบรายการปรับปรุงที่ยังไม่ได้รันชื่อ ' . $version);
        }
        return $ran;
    }

    private static function load(string $path): array
    {
        $real = realpath($path);
        $dir = realpath(self::directory());
        if ($real === false || $dir === false || !str_starts_with($real, $dir)) {
            throw new RuntimeException('ไฟล์ migration ไม่ถูกต้อง');
        }
        $data = require $real;
        if (!is_array($data) || !isset($data['name'], $data['statements']) || !is_array($data['statements'])) {
            throw new RuntimeException('ไฟล์ migration รูปแบบไม่ถูกต้อง: ' . basename($path));
        }
        return [
            'name' => (string) $data['name'],
            'statements' => array_values(array_map('strval', $data['statements'])),
        ];
    }
}
