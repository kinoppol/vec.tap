<?php
declare(strict_types=1);

return [
    'name' => 'สถานศึกษา ผู้ใช้ ภาคเรียน ครู แผนการเรียน และกลุ่มผู้เรียน',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS schools (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            skills_ready TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS terms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            label VARCHAR(64) NOT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_terms_school (school_id),
            CONSTRAINT fk_terms_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS teachers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            dept VARCHAR(255) NOT NULL DEFAULT '',
            degree VARCHAR(255) NOT NULL DEFAULT '',
            max_hours INT UNSIGNED NOT NULL DEFAULT 18,
            PRIMARY KEY (id),
            KEY idx_teachers_school (school_id),
            CONSTRAINT fk_teachers_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS teacher_skills (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT UNSIGNED NOT NULL,
            skill VARCHAR(128) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_teacher_skills_teacher (teacher_id),
            CONSTRAINT fk_teacher_skills_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NULL,
            teacher_id INT UNSIGNED NULL,
            username VARCHAR(64) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role ENUM('superadmin','school_admin','scheduler','teacher') NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_username (username),
            KEY idx_users_school (school_id),
            CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_users_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS study_plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            credits INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_plans_school (school_id),
            CONSTRAINT fk_plans_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_plans_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS subjects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            demo_key VARCHAR(32) NULL,
            code VARCHAR(32) NOT NULL,
            name VARCHAR(255) NOT NULL,
            theory TINYINT UNSIGNED NOT NULL DEFAULT 0,
            practice TINYINT UNSIGNED NOT NULL DEFAULT 0,
            extra TINYINT UNSIGNED NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            teacher_id INT UNSIGNED NULL,
            room_id INT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_subjects_plan_code (plan_id, code),
            KEY idx_subjects_school (school_id),
            CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_subjects_plan FOREIGN KEY (plan_id) REFERENCES study_plans (id) ON DELETE CASCADE,
            CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS student_groups (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            level VARCHAR(32) NOT NULL DEFAULT '',
            student_count INT UNSIGNED NOT NULL DEFAULT 0,
            advisor_id INT UNSIGNED NULL,
            note VARCHAR(255) NULL,
            note_tone VARCHAR(16) NULL,
            PRIMARY KEY (id),
            KEY idx_groups_school (school_id),
            CONSTRAINT fk_groups_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_groups_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
            CONSTRAINT fk_groups_plan FOREIGN KEY (plan_id) REFERENCES study_plans (id) ON DELETE SET NULL,
            CONSTRAINT fk_groups_advisor FOREIGN KEY (advisor_id) REFERENCES teachers (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
