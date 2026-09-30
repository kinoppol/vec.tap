<?php
declare(strict_types=1);

function app_root(): string
{
    return dirname(__DIR__);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');
    return ($dir === '' || $dir === '/' || $dir === '.') ? '' : $dir;
}

function url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

function request_path(): string
{
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $uri = is_string($uri) ? $uri : '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . trim($uri, '/');
    if ($uri === '/index.php') {
        return '/';
    }
    return $uri;
}

function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = base_path();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path === '' ? '/' : $path,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function config_path(): string
{
    return app_root() . '/config/config.php';
}

function lock_path(): string
{
    return app_root() . '/storage/installed.lock';
}

function config(): ?array
{
    static $loaded = false;
    static $value = null;
    if ($loaded) {
        return $value;
    }
    $loaded = true;
    $file = config_path();
    if (!is_file($file)) {
        return null;
    }
    $data = require $file;
    $value = is_array($data) ? $data : null;
    return $value;
}

function installed(): bool
{
    return is_file(lock_path()) && is_file(config_path());
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(?string $text = null, string $type = 'ok'): ?array
{
    if ($text !== null) {
        $_SESSION['_flash'] = ['type' => $type, 'text' => $text];
        return null;
    }
    if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }
    $message = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $message;
}

function app_log(string $message): void
{
    $dir = app_root() . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/app.log', date('c') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

function render(string $template, array $data = [], string $layout = 'app'): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    $data['currentPage'] = $data['currentPage'] ?? '';
    extract($data, EXTR_SKIP);
    ob_start();
    require app_root() . '/views/' . $template . '.php';
    $content = ob_get_clean();
    if ($layout === 'blank') {
        echo $content;
        return;
    }
    require app_root() . '/views/layouts/' . $layout . '.php';
}

function role_label(string $role): string
{
    return match ($role) {
        'superadmin' => 'ผู้ดูแลระบบ',
        'school_admin' => 'ผู้ดูแลระบบสถานศึกษา',
        'scheduler' => 'ผู้จัดตาราง (งานวิชาการ)',
        'teacher' => 'ครูผู้สอน',
        default => $role,
    };
}

function user_initial(string $name): string
{
    $trimmed = preg_replace('/^(ครู|นาย|นางสาว|นาง)/u', '', $name) ?? $name;
    return mb_substr($trimmed, 0, 1);
}

function thai_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);
    if ($time === false) {
        return $value;
    }
    return date('d/m/', $time) . ((int) date('Y', $time) + 543) . date(' H:i', $time);
}

function dir_is_writable(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $probe = $dir . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($probe, 'ok') !== false;
    if ($ok) {
        @unlink($probe);
    }
    return $ok && is_readable($dir);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function valid_username(string $username): bool
{
    $length = strlen($username);
    if ($length < 3 || $length > 254) {
        return false;
    }
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    return (bool) preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username);
}

function username_error(): string
{
    return 'ชื่อผู้ใช้เป็นอีเมล หรือตัวอักษรภาษาอังกฤษ ตัวเลข จุด ขีด ยาว 3–64 ตัว';
}

function wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'application/json');
}
