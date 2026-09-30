<?php
declare(strict_types=1);

return [
    'name' => 'นโยบาย ตารางเรียน และผลการวิเคราะห์ทักษะ',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS policies (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            code VARCHAR(32) NULL,
            short_text VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            policy_type ENUM('required','recommended') NOT NULL DEFAULT 'recommended',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_policies_school (school_id, sort_order),
            CONSTRAINT fk_policies_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS timetable_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            day_index TINYINT UNSIGNED NOT NULL,
            start_period TINYINT UNSIGNED NOT NULL,
            length_periods TINYINT UNSIGNED NOT NULL,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            warning VARCHAR(500) NULL,
            moved TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_entries_group (group_id, day_index),
            CONSTRAINT fk_entries_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_entries_group FOREIGN KEY (group_id) REFERENCES student_groups (id) ON DELETE CASCADE,
            CONSTRAINT fk_entries_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS schedule_states (
            group_id INT UNSIGNED NOT NULL,
            phase VARCHAR(16) NOT NULL DEFAULT 'manual',
            applied VARCHAR(8) NULL,
            PRIMARY KEY (group_id),
            CONSTRAINT fk_states_group FOREIGN KEY (group_id) REFERENCES student_groups (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS skill_suggestions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            alt_text VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY uq_skill_subject (subject_id),
            KEY idx_skill_school (school_id),
            CONSTRAINT fk_skill_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_skill_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
            CONSTRAINT fk_skill_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
