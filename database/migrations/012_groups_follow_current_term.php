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
         JOIN (
            SELECT src.school_id, src.rms_group_code, MIN(src.id) AS keep_id
            FROM student_groups src
            JOIN (
                SELECT school_id, MAX(id) AS id
                FROM terms
                WHERE is_current = 1
                GROUP BY school_id
            ) cur ON cur.school_id = src.school_id
            LEFT JOIN student_groups on_current
              ON on_current.school_id = src.school_id
             AND on_current.term_id = cur.id
             AND on_current.rms_group_code = src.rms_group_code
            WHERE src.rms_group_code IS NOT NULL
              AND src.rms_group_code <> ''
              AND on_current.id IS NULL
            GROUP BY src.school_id, src.rms_group_code
         ) chosen ON chosen.keep_id = g.id
         LEFT JOIN (
            SELECT school_id, term_id
            FROM student_groups
            WHERE rms_group_code IS NOT NULL AND rms_group_code <> ''
            GROUP BY school_id, term_id
         ) filled ON filled.school_id = g.school_id AND filled.term_id = current.id
         SET g.term_id = current.id
         WHERE g.term_id <> current.id
           AND filled.term_id IS NULL",
        "UPDATE study_plans p
         JOIN (
            SELECT school_id, MAX(id) AS id
            FROM terms
            WHERE is_current = 1
            GROUP BY school_id
         ) picked ON picked.school_id = p.school_id
         JOIN terms current ON current.id = picked.id
         JOIN (
            SELECT src.school_id, src.rms_key, MIN(src.id) AS keep_id
            FROM study_plans src
            JOIN (
                SELECT school_id, MAX(id) AS id
                FROM terms
                WHERE is_current = 1
                GROUP BY school_id
            ) cur ON cur.school_id = src.school_id
            LEFT JOIN study_plans on_current
              ON on_current.school_id = src.school_id
             AND on_current.term_id = cur.id
             AND on_current.rms_key = src.rms_key
            WHERE src.rms_key IS NOT NULL
              AND src.rms_key <> ''
              AND on_current.id IS NULL
            GROUP BY src.school_id, src.rms_key
         ) chosen ON chosen.keep_id = p.id
         LEFT JOIN (
            SELECT school_id, term_id
            FROM study_plans
            WHERE rms_key IS NOT NULL AND rms_key <> ''
            GROUP BY school_id, term_id
         ) filled ON filled.school_id = p.school_id AND filled.term_id = current.id
         SET p.term_id = current.id
         WHERE p.term_id <> current.id
           AND filled.term_id IS NULL",
    ],
];
