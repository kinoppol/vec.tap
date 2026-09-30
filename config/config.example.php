<?php
declare(strict_types=1);

/**
 * คัดลอกเป็น config.php ผ่านตัวติดตั้ง install.php
 * ไฟล์นี้เป็นเพียงตัวอย่างรูปร่างของการตั้งค่า
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'vec_tap',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app_key' => 'base64:ใส่คีย์ที่ตัวติดตั้งสร้างให้',
    'installed_at' => '',
];
