<?php
declare(strict_types=1);

return [
    'name' => 'รวมภาคเรียนที่ป้ายหรือรหัสเดียวกันให้เหลือแถวเดียว',
    'statements' => [
        "CREATE TEMPORARY TABLE term_merge_keepers AS
         SELECT school_id, term_key, keep_id, start_date, end_date, is_current
         FROM (
            SELECT
                ranked.school_id,
                ranked.term_key,
                ranked.id AS keep_id,
                ROW_NUMBER() OVER (
                    PARTITION BY ranked.school_id, ranked.term_key
                    ORDER BY ranked.group_count DESC, ranked.has_key DESC, ranked.id ASC
                ) AS rn,
                MIN(ranked.start_date) OVER (PARTITION BY ranked.school_id, ranked.term_key) AS start_date,
                MAX(ranked.end_date) OVER (PARTITION BY ranked.school_id, ranked.term_key) AS end_date,
                MAX(ranked.is_current) OVER (PARTITION BY ranked.school_id, ranked.term_key) AS is_current
            FROM (
                SELECT
                    t.id,
                    t.school_id,
                    t.start_date,
                    t.end_date,
                    t.is_current,
                    COALESCE(NULLIF(TRIM(t.rms_key), ''), TRIM(SUBSTRING_INDEX(t.label, ' ', -1))) AS term_key,
                    (t.rms_key IS NOT NULL AND TRIM(t.rms_key) <> '') AS has_key,
                    (SELECT COUNT(*) FROM student_groups g WHERE g.term_id = t.id) AS group_count
                FROM terms t
            ) ranked
            WHERE ranked.term_key REGEXP '^[0-9]+/[0-9]+$'
         ) picked
         WHERE rn = 1",

        "UPDATE terms loser
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND TRIM(loser.rms_key) = k.term_key
         SET loser.rms_key = NULL",

        "UPDATE terms keeper
         JOIN term_merge_keepers k ON k.keep_id = keeper.id
         SET keeper.rms_key = IF(keeper.rms_key IS NULL OR TRIM(keeper.rms_key) = '', k.term_key, keeper.rms_key),
             keeper.start_date = COALESCE(keeper.start_date, k.start_date),
             keeper.end_date = COALESCE(keeper.end_date, k.end_date),
             keeper.is_current = IF(k.is_current = 1, 1, keeper.is_current)",

        "DELETE g FROM student_groups g
         JOIN terms loser ON loser.id = g.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         JOIN student_groups taken
           ON taken.school_id = g.school_id
          AND taken.term_id = k.keep_id
          AND g.rms_group_code IS NOT NULL
          AND g.rms_group_code <> ''
          AND taken.rms_group_code = g.rms_group_code",

        "UPDATE student_groups g
         JOIN terms loser ON loser.id = g.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         SET g.term_id = k.keep_id",

        "DELETE p FROM study_plans p
         JOIN terms loser ON loser.id = p.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         JOIN study_plans taken
           ON taken.school_id = p.school_id
          AND taken.term_id = k.keep_id
          AND p.rms_key IS NOT NULL
          AND p.rms_key <> ''
          AND taken.rms_key = p.rms_key",

        "UPDATE study_plans p
         JOIN terms loser ON loser.id = p.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         SET p.term_id = k.keep_id",

        "DELETE h FROM holidays h
         JOIN terms loser ON loser.id = h.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         JOIN holidays taken
           ON taken.school_id = h.school_id
          AND taken.term_id = k.keep_id
          AND taken.holiday_date = h.holiday_date",

        "UPDATE holidays h
         JOIN terms loser ON loser.id = h.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         SET h.term_id = k.keep_id",

        "UPDATE group_twins tw
         JOIN terms loser ON loser.id = tw.term_id
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key
         SET tw.term_id = k.keep_id",

        "DELETE loser FROM terms loser
         JOIN term_merge_keepers k
           ON k.school_id = loser.school_id
          AND k.keep_id <> loser.id
          AND COALESCE(NULLIF(TRIM(loser.rms_key), ''), TRIM(SUBSTRING_INDEX(loser.label, ' ', -1))) = k.term_key",

        "DROP TEMPORARY TABLE term_merge_keepers",
    ],
];
