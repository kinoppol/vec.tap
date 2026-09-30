<?php
declare(strict_types=1);

return [
    'name' => 'ขยายชื่อผู้ใช้ให้ใช้อีเมลได้',
    'statements' => [
        'ALTER TABLE users MODIFY username VARCHAR(254) NOT NULL',
    ],
];
