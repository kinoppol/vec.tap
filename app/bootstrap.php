<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Migrator.php';
require_once __DIR__ . '/Defaults.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/SchoolContext.php';
require_once __DIR__ . '/Repo.php';
require_once __DIR__ . '/ScheduleEngine.php';
require_once __DIR__ . '/ScheduleActions.php';
require_once __DIR__ . '/Skills.php';
require_once __DIR__ . '/AiClient.php';
require_once __DIR__ . '/Assistant.php';
require_once __DIR__ . '/Spreadsheet.php';
require_once __DIR__ . '/Rms.php';
require_once __DIR__ . '/Ui.php';

boot_session();
send_security_headers();

set_exception_handler(static function (Throwable $exception): void {
    app_log($exception::class . ' ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
    if (wants_json()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาดภายในระบบ'], JSON_UNESCAPED_UNICODE);
        return;
    }
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>ข้อผิดพลาด</title><p style="font-family:sans-serif">เกิดข้อผิดพลาดภายในระบบ</p>';
});
