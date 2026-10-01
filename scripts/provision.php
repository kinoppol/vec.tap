<?php
declare(strict_types=1);

$root = $argv[1] ?? getcwd();
$root = rtrim($root, '/');
if (!is_file($root . '/app/bootstrap.php')) {
    fwrite(STDERR, "vec-tap: repository root not found at {$root}\n");
    exit(1);
}

$secretPath = '/opt/vec-tap/secrets.env';
$env = [];
if (is_file($secretPath)) {
    foreach (file($secretPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[$key] = $value;
    }
}

$required = ['DB_NAME', 'DB_USER', 'DB_PASS', 'ADMIN_USER', 'ADMIN_PASS'];
$missing = false;
foreach ($required as $key) {
    if (!isset($env[$key]) || $env[$key] === '') {
        $missing = true;
    }
}
if ($missing) {
    $env = [
        'DB_NAME' => 'vec_tap',
        'DB_USER' => 'vec_tap',
        'DB_PASS' => bin2hex(random_bytes(16)),
        'ADMIN_USER' => 'admin@vec.local',
        'ADMIN_PASS' => bin2hex(random_bytes(16)),
    ];
    $lines = [];
    foreach ($env as $key => $value) {
        $lines[] = $key . '=' . $value;
    }
    if (file_put_contents($secretPath, implode("\n", $lines) . "\n") === false) {
        fwrite(STDERR, "vec-tap: could not write {$secretPath}\n");
        exit(1);
    }
    chmod($secretPath, 0600);
}

foreach (['DB_NAME' => $env['DB_NAME'], 'DB_USER' => $env['DB_USER']] as $label => $value) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        fwrite(STDERR, "vec-tap: invalid {$label}\n");
        exit(1);
    }
}

$sql = <<<SQL
CREATE DATABASE IF NOT EXISTS `{$env['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '{$env['DB_USER']}'@'127.0.0.1' IDENTIFIED BY '{$env['DB_PASS']}';
ALTER USER '{$env['DB_USER']}'@'127.0.0.1' IDENTIFIED BY '{$env['DB_PASS']}';
GRANT ALL PRIVILEGES ON `{$env['DB_NAME']}`.* TO '{$env['DB_USER']}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open(
    ['sudo', 'mysql', '--protocol=socket', '--socket=/run/mysqld/mysqld.sock', '--batch'],
    $descriptors,
    $pipes
);
if (!is_resource($process)) {
    fwrite(STDERR, "vec-tap: could not run mysql\n");
    exit(1);
}
fwrite($pipes[0], $sql);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($process);
if ($code !== 0) {
    fwrite(STDERR, "vec-tap: mysql setup failed\n" . $stderr . $stdout);
    exit(1);
}

require $root . '/app/bootstrap.php';
require $root . '/database/seed.php';

$db = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => $env['DB_NAME'],
    'user' => $env['DB_USER'],
    'pass' => $env['DB_PASS'],
    'charset' => 'utf8mb4',
];
$pdo = Database::connect($db, true);
$version = Database::assertMariaDb($pdo);
$ran = Migrator::run($pdo);

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
if ($adminCount === 0) {
    Auth::insert($pdo, null, null, $env['ADMIN_USER'], $env['ADMIN_PASS'], 'ผู้ดูแลระบบ', 'superadmin');
    echo "vec-tap: created superadmin {$env['ADMIN_USER']}\n";
} else {
    echo "vec-tap: superadmin already present\n";
}

$schoolCount = (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();
if ($schoolCount === 0) {
    seed_sample($pdo);
    echo "vec-tap: seeded sample schools\n";
} else {
    echo "vec-tap: sample data already present ({$schoolCount} schools)\n";
}

$configFile = $root . '/config/config.php';
$appKey = Crypto::generateKey();
if (is_file($configFile)) {
    $existing = require $configFile;
    if (is_array($existing) && !empty($existing['app_key']) && is_string($existing['app_key'])) {
        $appKey = $existing['app_key'];
    }
}
$config = [
    'db' => $db,
    'app_key' => $appKey,
    'installed_at' => date('c'),
];
$content = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($configFile, $content) === false) {
    fwrite(STDERR, "vec-tap: could not write config.php\n");
    exit(1);
}
chmod($configFile, 0600);

$lock = $root . '/storage/installed.lock';
if (!is_file($lock) && file_put_contents($lock, date('c') . PHP_EOL) === false) {
    fwrite(STDERR, "vec-tap: could not write installed.lock\n");
    exit(1);
}

echo 'vec-tap: MariaDB ' . $version . "\n";
echo 'vec-tap: migrations applied this run: ' . count($ran) . "\n";
echo "vec-tap: credentials at {$secretPath}\n";
