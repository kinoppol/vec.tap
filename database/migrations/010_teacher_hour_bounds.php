<?php
declare(strict_types=1);

return [
    'name' => 'ชั่วโมงสอนต่ำสุดของครู และนโยบายช่วงชั่วโมง',
    'statements' => [
        'ALTER TABLE teachers ADD COLUMN IF NOT EXISTS min_hours INT UNSIGNED NOT NULL DEFAULT 0',
        "CREATE TEMPORARY TABLE tmp_teacher_hour_schools AS
         SELECT s.id AS school_id
         FROM schools s
         WHERE NOT EXISTS (
             SELECT 1 FROM policies p WHERE p.school_id = s.id AND p.code = 'teacher_hours'
         )",
        "INSERT INTO policies (school_id, sort_order, code, short_text, body, policy_type, enabled)
         SELECT school_id, 100, 'teacher_hours', 'ชั่วโมงสอนตามช่วงของครู',
                'ชั่วโมงสอนต่อสัปดาห์ของครูแต่ละคนควรอยู่ระหว่างค่าต่ำสุดและค่าสูงสุดที่กำหนดไว้ในข้อมูลครู หากนโยบายนี้เป็นข้อบังคับ จะลงคาบที่ทำให้เกินชั่วโมงสูงสุดไม่ได้ และครูที่ชั่วโมงยังไม่ถึงค่าต่ำสุดจะถือว่ายังไม่ครบตามนโยบาย',
                'recommended', 1
         FROM tmp_teacher_hour_schools",
        'DROP TEMPORARY TABLE IF EXISTS tmp_teacher_hour_schools',
    ],
];