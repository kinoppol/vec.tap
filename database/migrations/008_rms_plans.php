<?php
declare(strict_types=1);

return [
    'name' => 'แผนการเรียนที่นำเข้าจาก RMS',
    'statements' => [
        'ALTER TABLE study_plans ADD COLUMN IF NOT EXISTS rms_key VARCHAR(80) NULL',
        'ALTER TABLE study_plans ADD UNIQUE KEY IF NOT EXISTS uq_plans_rms (school_id, term_id, rms_key)',
    ],
];
