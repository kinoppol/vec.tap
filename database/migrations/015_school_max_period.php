<?php
declare(strict_types=1);

return [
    'name' => 'ชั่วโมงสูงสุดของตารางเรียนต่อวันของสถานศึกษา',
    'statements' => [
        "ALTER TABLE schools ADD COLUMN IF NOT EXISTS max_period TINYINT UNSIGNED NOT NULL DEFAULT 9",
    ],
];
