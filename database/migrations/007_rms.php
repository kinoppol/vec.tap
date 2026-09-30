<?php
declare(strict_types=1);

return [
    'name' => 'นำเข้าข้อมูลจาก RMS แยกตามสถานศึกษา',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS rms_settings (
            school_id INT UNSIGNED NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (school_id),
            CONSTRAINT fk_rms_settings_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'ALTER TABLE teachers ADD COLUMN IF NOT EXISTS rms_people_id VARCHAR(30) NULL',
        'ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1',
        'ALTER TABLE teachers ADD UNIQUE KEY IF NOT EXISTS uq_teachers_rms (school_id, rms_people_id)',

        'ALTER TABLE terms ADD COLUMN IF NOT EXISTS rms_key VARCHAR(20) NULL',
        'ALTER TABLE terms ADD COLUMN IF NOT EXISTS start_date DATE NULL',
        'ALTER TABLE terms ADD COLUMN IF NOT EXISTS end_date DATE NULL',
        'ALTER TABLE terms ADD UNIQUE KEY IF NOT EXISTS uq_terms_rms (school_id, rms_key)',

        "CREATE TABLE IF NOT EXISTS holidays (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NOT NULL,
            holiday_date DATE NOT NULL,
            name VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_holidays (school_id, term_id, holiday_date),
            CONSTRAINT fk_holidays_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_holidays_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'ALTER TABLE student_groups ADD COLUMN IF NOT EXISTS rms_group_code VARCHAR(50) NULL',
        'ALTER TABLE student_groups ADD UNIQUE KEY IF NOT EXISTS uq_groups_rms (school_id, term_id, rms_group_code)',

        "CREATE TABLE IF NOT EXISTS students (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            student_id VARCHAR(30) NOT NULL,
            student_code VARCHAR(30) NULL,
            idcard VARCHAR(20) NULL,
            firstname VARCHAR(100) NULL,
            surname VARCHAR(100) NULL,
            gender VARCHAR(5) NULL,
            group_code VARCHAR(50) NULL,
            group_name VARCHAR(150) NULL,
            group_abbr VARCHAR(100) NULL,
            grade_name VARCHAR(100) NULL,
            major_name VARCHAR(150) NULL,
            status_code VARCHAR(10) NULL,
            status_name VARCHAR(100) NULL,
            entrance_year INT NULL,
            entrance_semester TINYINT NULL,
            email VARCHAR(150) NULL,
            tel VARCHAR(30) NULL,
            gpax DECIMAL(4,2) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_students (school_id, student_id),
            KEY idx_students_group (school_id, group_code),
            CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_schedules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            semes VARCHAR(20) NOT NULL,
            subject_id VARCHAR(50) NULL,
            subject_name VARCHAR(200) NULL,
            real_subject_id VARCHAR(50) NULL,
            student_group_id VARCHAR(50) NULL,
            teacher_id VARCHAR(30) NULL,
            teacher_name VARCHAR(150) NULL,
            day_name VARCHAR(30) NULL,
            time_range VARCHAR(50) NULL,
            periods INT NULL,
            room VARCHAR(50) NULL,
            building VARCHAR(100) NULL,
            timetable_id VARCHAR(30) NULL,
            timetable_sub_id VARCHAR(30) NULL,
            PRIMARY KEY (id),
            KEY idx_rms_schedules_semes (school_id, semes),
            KEY idx_rms_schedules_teacher (school_id, teacher_id),
            KEY idx_rms_schedules_group (school_id, student_group_id),
            CONSTRAINT fk_rms_schedules_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "ALTER TABLE timetable_entries ADD COLUMN IF NOT EXISTS source VARCHAR(8) NOT NULL DEFAULT 'app'",
    ],
];
