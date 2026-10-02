<?php
declare(strict_types=1);

return [
    'name' => 'เก็บชั่วโมง ท-ป-น ของบัญชีรายวิชาจาก RMS',
    'statements' => [
        "ALTER TABLE rms_subject_catalog ADD COLUMN IF NOT EXISTS theory TINYINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE rms_subject_catalog ADD COLUMN IF NOT EXISTS practice TINYINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE rms_subject_catalog ADD COLUMN IF NOT EXISTS extra TINYINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE rms_subject_catalog ADD COLUMN IF NOT EXISTS hours_known TINYINT(1) NOT NULL DEFAULT 0",
    ],
];
