<?php
declare(strict_types=1);

return [
    'name' => 'ย้ายกลุ่มและแผนที่โอนจาก RMS เข้าภาคเรียนปัจจุบันเมื่อภาคเรียนนั้นยังว่าง',
    'statements' => [
        "UPDATE terms t
         JOIN (
            SELECT school_id, MAX(id) AS id
            FROM terms
            WHERE is_current = 1
            GROUP BY school_id
         ) picked ON picked.id = t.id
         LEFT JOIN terms taken
           ON taken.school_id = t.school_id
          AND taken.id <> t.id
          AND taken.rms_key = TRIM(SUBSTRING_INDEX(t.label, ' ', -1))
         SET t.rms_key = TRIM(SUBSTRING_INDEX(t.label, ' ', -1))
         WHERE (t.rms_key IS NULL OR t.rms_key = '')
           AND t.label REGEXP 'ภาคเรียนที่[[:space:]]+[0-9]+/[0-9]+'
           AND taken.id IS NULL",
        "UPDATE student_groups g
         JOIN (
            SELECT school_id, MAX(id) AS id
            FROM terms
            WHERE is_current = 1
            GROUP BY school_id
         ) picked ON picked.school_id = g.school_id
         JOIN terms current ON current.id = picked.id
         LEFT JOIN (
            SELECT school_id, term_id
            FROM student_groups
            WHERE rms_group_code IS NOT NULL AND rms_group_code <> ''
            GROUP BY school_id, term_id
         ) filled ON filled.school_id = g.school_id AND filled.term_id = current.id
         LEFT JOIN student_groups existing
           ON existing.school_id = g.school_id
          AND existing.term_id = current.id
          AND existing.rms_group_code = g.rms_group_code
         SET g.term_id = current.id
         WHERE g.rms_group_code IS NOT NULL
           AND g.rms_group_code <> ''
           AND g.term_id <> current.id
           AND filled.term_id IS NULL
           AND existing.id IS NULL",
        "UPDATE study_plans p
         JOIN (
            SELECT school_id, MAX(id) AS id
            FROM terms
            WHERE is_current = 1
            GROUP BY school_id
         ) picked ON picked.school_id = p.school_id
         JOIN terms current ON current.id = picked.id
         LEFT JOIN (
            SELECT school_id, term_id
            FROM study_plans
            WHERE rms_key IS NOT NULL AND rms_key <> ''
            GROUP BY school_id, term_id
         ) filled ON filled.school_id = p.school_id AND filled.term_id = current.id
         LEFT JOIN study_plans existing
           ON existing.school_id = p.school_id
          AND existing.term_id = current.id
          AND existing.rms_key = p.rms_key
         SET p.term_id = current.id
         WHERE p.rms_key IS NOT NULL
           AND p.rms_key <> ''
           AND p.term_id <> current.id
           AND filled.term_id IS NULL
           AND existing.id IS NULL",
    ],
];
