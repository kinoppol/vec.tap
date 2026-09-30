<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/database/seed.php';

$requirements = installer_requirements();
$requirementsOk = $requirements['php']['ok']
    && !in_array(false, array_column($requirements['extensions'], 'ok'), true)
    && !in_array(false, array_column($requirements['dirs'], 'ok'), true);

$error = null;
$done = null;
if (!empty($_SESSION['install_done']) && is_array($_SESSION['install_done'])) {
    $done = $_SESSION['install_done'];
    unset($_SESSION['install_done']);
}

$mode = 'fresh';
if (is_file(config_path())) {
    $mode = installer_mode();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::check();
    $action = post_string('action');
    try {
        if (!$requirementsOk) {
            throw new RuntimeException('ยังไม่ผ่านการตรวจสภาพแวดล้อม');
        }
        if ($action === 'save_db') {
            $db = installer_db_from_post();
            $pdo = installer_connect($db);
            Migrator::run($pdo);
            $_SESSION['install_db'] = $db;
            $_SESSION['install_version'] = Database::assertMariaDb($pdo);
            $_SESSION['install_step'] = 'admin';
            unset($_SESSION['reinstall_ok']);
            redirect('/install.php');
        } elseif ($action === 'reset_password') {
            installer_verify_admin(post_string('username'), (string) ($_POST['current_password'] ?? ''));
            $password = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if (strlen($password) < 8 || $password !== $confirm) {
                throw new RuntimeException('รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวและตรงกันทั้งสองช่อง');
            }
            $statement = Database::pdo()->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE username = :username AND role = \'superadmin\''
            );
            $statement->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'username' => post_string('username'),
            ]);
            flash('ตั้งรหัสผ่านผู้ดูแลระบบใหม่แล้ว');
            redirect('/install.php');
        } elseif ($action === 'migrate') {
            installer_verify_admin(post_string('username'), (string) ($_POST['password'] ?? ''));
            $ran = Migrator::run(Database::pdo());
            flash($ran === [] ? 'ไม่มีโครงสร้างที่ต้องปรับปรุง' : 'ปรับปรุงฐานข้อมูลแล้ว ' . count($ran) . ' รายการ');
            redirect('/install.php');
        } elseif ($action === 'reinstall') {
            if (post_string('confirm') !== 'REINSTALL') {
                throw new RuntimeException('พิมพ์ REINSTALL เพื่อยืนยันการติดตั้งใหม่');
            }
            $_SESSION['reinstall_ok'] = true;
            $_SESSION['install_step'] = 'admin';
            redirect('/install.php');
        } elseif ($action === 'cancel_reinstall') {
            unset($_SESSION['reinstall_ok'], $_SESSION['install_step']);
            redirect('/install.php');
        } elseif ($action === 'finish') {
            $donePayload = installer_finish();
            $_SESSION['install_done'] = $donePayload;
            redirect('/install.php');
        } else {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$notice = flash();
$phase = 'db';
if ($done) {
    $phase = 'done';
} elseif (!empty($_SESSION['reinstall_ok']) || (($_SESSION['install_step'] ?? '') === 'admin') || $mode === 'need_admin') {
    $phase = 'admin';
} elseif ($mode === 'installed') {
    $phase = 'manage';
}

$savedDb = $_SESSION['install_db'] ?? (config()['db'] ?? [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'vec_tap',
    'user' => 'root',
    'pass' => '',
]);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php require app_root() . '/views/partials/head.php'; ?>
<title>ติดตั้งระบบจัดตารางเรียนอาชีวศึกษา</title>
</head>
<body class="install-body">
<main class="install-wrap">
    <header class="install-brand">
        <span class="logo-mark">สอศ.</span>
        <div>
            <strong>ติดตั้งระบบจัดตารางเรียนอาชีวศึกษา</strong>
            <span>VEC SMART TIMETABLE · PHP 8 · MariaDB 10</span>
        </div>
    </header>

    <?php if ($error): ?><div class="banner err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="banner <?= e($notice['type'] === 'err' ? 'err' : 'ok') ?>"><?= e($notice['text']) ?></div><?php endif; ?>

    <section class="card">
        <h1>ตรวจสภาพแวดล้อม</h1>
        <ul class="check-list">
            <li class="<?= $requirements['php']['ok'] ? 'pass' : 'fail' ?>">
                <i class="bi <?= $requirements['php']['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                <span><?= e($requirements['php']['label']) ?></span>
                <em><?= e($requirements['php']['detail']) ?></em>
            </li>
            <?php foreach ($requirements['extensions'] as $item): ?>
                <li class="<?= $item['ok'] ? 'pass' : 'fail' ?>">
                    <i class="bi <?= $item['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                    <span><?= e($item['label']) ?></span>
                    <em><?= e($item['detail']) ?></em>
                </li>
            <?php endforeach; ?>
            <?php foreach ($requirements['dirs'] as $item): ?>
                <li class="<?= $item['ok'] ? 'pass' : 'fail' ?>">
                    <i class="bi <?= $item['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                    <span><?= e($item['label']) ?></span>
                    <em><?= e($item['detail']) ?></em>
                </li>
            <?php endforeach; ?>
            <?php foreach ($requirements['optional'] as $item): ?>
                <li class="<?= $item['ok'] ? 'pass' : 'warn' ?>">
                    <i class="bi <?= $item['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                    <span><?= e($item['label']) ?></span>
                    <em><?= e($item['detail']) ?></em>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($phase === 'done' && $done): ?>
        <section class="card">
            <h1>ติดตั้งเสร็จแล้ว</h1>
            <p class="lead">ผู้ดูแลระบบชื่อ <strong><?= e($done['username']) ?></strong> พร้อมเข้าสู่ระบบ รหัสผ่านคือรหัสที่กรอกในขั้นตอนก่อนหน้า</p>
            <?php if ($done['accounts']): ?>
                <p class="lead">บัญชีตัวอย่างด้านล่างแสดงครั้งเดียว จดไว้ก่อนออกจากหน้านี้</p>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>สถานศึกษา</th><th>ชื่อ</th><th>บทบาท</th><th>ชื่อผู้ใช้</th><th>รหัสผ่าน</th></tr></thead>
                        <tbody>
                        <?php foreach ($done['accounts'] as $account): ?>
                            <tr>
                                <td><?= e($account['school']) ?></td>
                                <td><?= e($account['name']) ?></td>
                                <td><?= e($account['role']) ?></td>
                                <td><code><?= e($account['username']) ?></code></td>
                                <td><code><?= e($account['password']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($done['seed_note'])): ?><p class="lead"><?= e($done['seed_note']) ?></p><?php endif; ?>
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">ไปหน้าเข้าสู่ระบบ</a>
        </section>
    <?php elseif ($phase === 'manage'): ?>
        <section class="card">
            <h1>ระบบติดตั้งแล้ว</h1>
            <p class="lead">ตั้งรหัสผ่านหรือรัน migrations ด้วยบัญชีผู้ดูแลระบบเดิม การติดตั้งใหม่ทั้งระบบให้พิมพ์ REINSTALL แล้วตั้งชื่อผู้ใช้และรหัสผ่านใหม่ในขั้นถัดไป</p>
            <div class="install-grid">
                <form method="post" class="stack">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <h2>ตั้งรหัสผ่านใหม่</h2>
                    <label>ชื่อผู้ใช้<input name="username" required autocomplete="username"></label>
                    <label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" required autocomplete="current-password"></label>
                    <label>รหัสผ่านใหม่<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
                    <label>ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
                    <button class="btn btn-primary" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>บันทึกรหัสผ่าน</button>
                </form>
                <form method="post" class="stack">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="migrate">
                    <h2>รัน migrations ที่ค้าง</h2>
                    <label>ชื่อผู้ใช้<input name="username" required autocomplete="username"></label>
                    <label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password"></label>
                    <button class="btn btn-primary" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>ปรับปรุงโครงสร้าง</button>
                </form>
                <form method="post" class="stack">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="reinstall">
                    <h2>ติดตั้งใหม่ทั้งระบบ</h2>
                    <p class="hint">ลบตารางทั้งหมดแล้วสร้างใหม่ พิมพ์ REINSTALL จากนั้นตั้งชื่อผู้ใช้และรหัสผ่านใหม่</p>
                    <label>ยืนยัน<input name="confirm" placeholder="REINSTALL" required autocomplete="off"></label>
                    <button class="btn btn-danger" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>เริ่มติดตั้งใหม่</button>
                </form>
            </div>
            <p class="hint"><a href="<?= e(url('/login')) ?>">กลับไปเข้าสู่ระบบ</a></p>
        </section>
    <?php elseif ($phase === 'admin'): ?>
        <section class="card">
            <h1>ตั้งชื่อผู้ใช้และรหัสผ่านผู้ดูแลระบบ</h1>
            <?php if (!empty($_SESSION['reinstall_ok'])): ?>
                <div class="banner warn">ยืนยันติดตั้งใหม่แล้ว ข้อมูลเดิมจะถูกลบเมื่อกดบันทึกด้านล่าง</div>
            <?php endif; ?>
            <form method="post" class="stack narrow">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="finish">
                <label>ชื่อที่แสดง<input name="display_name" value="ผู้ดูแลระบบ" required></label>
                <label>ชื่อผู้ใช้หรืออีเมล<input name="username" required maxlength="254" pattern="[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}|[A-Za-z0-9._-]{3,64}" autocomplete="username"></label>
                <label>รหัสผ่าน<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label>ยืนยันรหัสผ่าน<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
                <label class="check"><input type="checkbox" name="seed" value="1" checked> ติดตั้งข้อมูลตัวอย่าง 3 สถานศึกษา</label>
                <div class="row-actions">
                    <button class="btn btn-primary" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>บันทึกและเสร็จสิ้น</button>
                    <?php if (!empty($_SESSION['reinstall_ok'])): ?>
                        <button class="btn" type="submit" name="action" value="cancel_reinstall" formnovalidate>ยกเลิก</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="card">
            <h1>เชื่อมต่อ MariaDB</h1>
            <p class="lead">ตัวติดตั้งจะสร้างฐานข้อมูลถ้ายังไม่มี แล้วตรวจว่าเป็น MariaDB 10 ขึ้นไป</p>
            <form method="post" class="stack narrow">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_db">
                <label>โฮสต์<input name="db_host" value="<?= e((string) $savedDb['host']) ?>" required></label>
                <label>พอร์ต<input name="db_port" type="number" value="<?= e((string) $savedDb['port']) ?>" required></label>
                <label>ชื่อฐานข้อมูล<input name="db_name" value="<?= e((string) $savedDb['name']) ?>" required pattern="[A-Za-z0-9_]+"></label>
                <label>ผู้ใช้<input name="db_user" value="<?= e((string) $savedDb['user']) ?>" required autocomplete="username"></label>
                <label>รหัสผ่าน<input type="password" name="db_pass" value="" autocomplete="current-password"></label>
                <button class="btn btn-primary" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>ตรวจสอบและสร้างตาราง</button>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
<?php

function installer_requirements(): array
{
    $dirs = [
        'config' => app_root() . '/config',
        'storage/logs' => app_root() . '/storage/logs',
        'storage/cache' => app_root() . '/storage/cache',
        'storage/uploads' => app_root() . '/storage/uploads',
    ];
    $dirRows = [];
    foreach ($dirs as $label => $path) {
        $ok = dir_is_writable($path);
        $dirRows[] = [
            'ok' => $ok,
            'label' => 'เขียนและอ่านได้: ' . $label,
            'detail' => $ok ? 'ผ่าน' : 'ไม่มีสิทธิ์เขียนไฟล์',
        ];
    }
    $extensions = ['pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo', 'session', 'curl'];
    $extensionRows = [];
    foreach ($extensions as $extension) {
        $ok = extension_loaded($extension);
        $extensionRows[] = [
            'ok' => $ok,
            'label' => 'ส่วนขยาย ' . $extension,
            'detail' => $ok ? 'พร้อมใช้งาน' : 'ยังไม่ได้เปิด',
        ];
    }
    return [
        'php' => [
            'ok' => PHP_VERSION_ID >= 80000,
            'label' => 'PHP 8.0 ขึ้นไป',
            'detail' => PHP_VERSION,
        ],
        'extensions' => $extensionRows,
        'dirs' => $dirRows,
        'optional' => [[
            'ok' => class_exists(ZipArchive::class),
            'label' => 'ZipArchive สำหรับไฟล์ Excel .xlsx',
            'detail' => class_exists(ZipArchive::class) ? 'พร้อมใช้งาน' : 'ไม่มีก็ยังนำเข้า CSV ได้',
        ]],
    ];
}

function installer_mode(): string
{
    try {
        $pdo = Database::pdo();
        Database::assertMariaDb($pdo);
        $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
        return $count > 0 ? 'installed' : 'need_admin';
    } catch (Throwable) {
        return 'need_db';
    }
}

function installer_db_from_post(): array
{
    $name = post_string('db_name');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('ชื่อฐานข้อมูลใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข และขีดล่าง');
    }
    $port = (int) post_string('db_port');
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('พอร์ตไม่ถูกต้อง');
    }
    return [
        'host' => post_string('db_host') !== '' ? post_string('db_host') : '127.0.0.1',
        'port' => $port,
        'name' => $name,
        'user' => post_string('db_user'),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
        'charset' => 'utf8mb4',
    ];
}

function installer_connect(array $db): PDO
{
    $pdo = Database::connect($db, false);
    Database::assertMariaDb($pdo);
    Database::createDatabase($pdo, $db['name']);
    return $pdo;
}

function installer_verify_admin(string $username, string $password): void
{
    $statement = Database::pdo()->prepare(
        'SELECT password_hash FROM users WHERE username = :username AND role = \'superadmin\' LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $hash = $statement->fetchColumn();
    if (!$hash || !password_verify($password, (string) $hash)) {
        throw new RuntimeException('ชื่อผู้ใช้หรือรหัสผ่านของผู้ดูแลระบบไม่ถูกต้อง');
    }
}

function installer_finish(): array
{
    $reinstall = !empty($_SESSION['reinstall_ok']);
    $username = post_string('username');
    $display = post_string('display_name');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (!valid_username($username)) {
        throw new RuntimeException(username_error());
    }
    if ($display === '') {
        throw new RuntimeException('กรอกชื่อที่แสดง');
    }
    if (strlen($password) < 8 || $password !== $confirm) {
        throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวและตรงกันทั้งสองช่อง');
    }

    $existing = config();
    if ($reinstall) {
        if (!is_array($existing) || empty($existing['db'])) {
            throw new RuntimeException('ไม่พบค่าเชื่อมต่อเดิมสำหรับติดตั้งใหม่');
        }
        $db = $existing['db'];
        $appKey = Crypto::generateKey();
        $pdo = installer_connect($db);
        installer_drop_all($pdo);
        Migrator::run($pdo);
    } else {
        $db = $_SESSION['install_db'] ?? ($existing['db'] ?? null);
        if (!is_array($db)) {
            throw new RuntimeException('ยังไม่ได้เชื่อมต่อฐานข้อมูล');
        }
        $appKey = is_array($existing) && !empty($existing['app_key']) ? (string) $existing['app_key'] : Crypto::generateKey();
        $pdo = installer_connect($db);
        Migrator::run($pdo);
    }

    $exists = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $exists->execute(['username' => $username]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException('มีชื่อผู้ใช้นี้แล้ว เลือกชื่ออื่น');
    }
    Auth::insert($pdo, null, null, $username, $password, $display, 'superadmin');
    $accounts = [];
    $seedNote = '';
    $schoolCount = (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();
    if (!empty($_POST['seed'])) {
        if ($schoolCount === 0) {
            $accounts = seed_sample($pdo);
        } else {
            $seedNote = 'มีสถานศึกษาอยู่แล้ว จึงไม่ได้ใส่ข้อมูลตัวอย่างซ้ำ';
        }
    }

    installer_write_config($db, $appKey);
    Database::disconnect();
    if (file_put_contents(lock_path(), date('c') . PHP_EOL) === false) {
        throw new RuntimeException('เขียนไฟล์ storage/installed.lock ไม่ได้');
    }
    unset($_SESSION['install_db'], $_SESSION['install_step'], $_SESSION['reinstall_ok'], $_SESSION['install_version']);
    return [
        'username' => $username,
        'accounts' => $accounts,
        'seed_note' => $seedNote,
    ];
}

function installer_drop_all(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
            $pdo->exec('DROP TABLE `' . $table . '`');
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function installer_write_config(array $db, string $appKey): void
{
    $config = [
        'db' => [
            'host' => (string) $db['host'],
            'port' => (int) $db['port'],
            'name' => (string) $db['name'],
            'user' => (string) $db['user'],
            'pass' => (string) ($db['pass'] ?? ''),
            'charset' => 'utf8mb4',
        ],
        'app_key' => $appKey,
        'installed_at' => date('c'),
    ];
    $content = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(config_path(), $content) === false) {
        throw new RuntimeException('เขียนไฟล์ config/config.php ไม่ได้');
    }
}
