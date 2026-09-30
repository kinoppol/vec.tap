<?php
declare(strict_types=1);

final class Crypto
{
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function encrypt(string $plain): string
    {
        $key = self::rawKey();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('เข้ารหัสไม่สำเร็จ');
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new RuntimeException('ข้อมูลเข้ารหัสไม่ถูกต้อง');
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::rawKey(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('ถอดรหัสไม่สำเร็จ');
        }
        return $plain;
    }

    public static function hint(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        return mb_substr($secret, -4);
    }

    private static function rawKey(): string
    {
        $config = config();
        $stored = is_array($config) ? (string) ($config['app_key'] ?? '') : '';
        if (str_starts_with($stored, 'base64:')) {
            $decoded = base64_decode(substr($stored, 7), true);
            if ($decoded !== false && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
        }
        if ($stored === '') {
            throw new RuntimeException('ไม่พบคีย์เข้ารหัสของระบบ');
        }
        return substr(hash('sha256', $stored, true), 0, 32);
    }
}
