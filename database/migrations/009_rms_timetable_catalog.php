<?php
declare(strict_types=1);

return [
    'name' => 'ข้อมูลจัดตารางเพิ่มจาก RMS',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS rms_majors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            major_id VARCHAR(30) NOT NULL,
            code VARCHAR(40) NULL,
            name_th VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rms_majors (school_id, major_id),
            CONSTRAINT fk_rms_majors_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_minors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            minor_id VARCHAR(30) NOT NULL,
            code VARCHAR(40) NULL,
            name_th VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rms_minors (school_id, minor_id),
            CONSTRAINT fk_rms_minors_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_subject_types (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            subject_type_id VARCHAR(30) NOT NULL,
            code VARCHAR(40) NULL,
            name_th VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rms_subject_types (school_id, subject_type_id),
            CONSTRAINT fk_rms_subject_types_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_curricula (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            curriculum_year VARCHAR(10) NOT NULL,
            degree_level_id VARCHAR(20) NOT NULL DEFAULT '',
            subject_type_id VARCHAR(30) NOT NULL DEFAULT '',
            major_id VARCHAR(30) NOT NULL DEFAULT '',
            minor_id VARCHAR(30) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY uq_rms_curricula (school_id, curriculum_year, degree_level_id, subject_type_id, major_id, minor_id),
            CONSTRAINT fk_rms_curricula_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_subject_catalog (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rms_subject_catalog (school_id, code),
            CONSTRAINT fk_rms_subject_catalog_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_timetables (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_key VARCHAR(20) NOT NULL,
            group_code VARCHAR(50) NULL,
            subject_code VARCHAR(50) NULL,
            subject_name VARCHAR(255) NULL,
            building_name VARCHAR(150) NULL,
            room_name VARCHAR(80) NULL,
            day_code VARCHAR(10) NULL,
            time_from_name VARCHAR(20) NULL,
            time_to_name VARCHAR(20) NULL,
            teacher_id VARCHAR(30) NULL,
            teacher_name VARCHAR(150) NULL,
            teacher_type VARCHAR(10) NULL,
            timetable_type VARCHAR(10) NULL,
            timetable_id VARCHAR(40) NULL,
            timetable_sub_id VARCHAR(40) NULL,
            class_room_extra VARCHAR(5) NULL,
            PRIMARY KEY (id),
            KEY idx_rms_timetables_term (school_id, term_key),
            KEY idx_rms_timetables_tid (school_id, timetable_id),
            CONSTRAINT fk_rms_timetables_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_blockcourses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_key VARCHAR(20) NOT NULL,
            group_code VARCHAR(50) NULL,
            day_code VARCHAR(10) NULL,
            time_from_name VARCHAR(20) NULL,
            time_to_name VARCHAR(20) NULL,
            teacher_id VARCHAR(30) NULL,
            timetable_id VARCHAR(40) NULL,
            timetable_sub_id VARCHAR(40) NULL,
            PRIMARY KEY (id),
            KEY idx_rms_blocks_tid (school_id, timetable_id),
            CONSTRAINT fk_rms_blocks_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rms_enrollments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_key VARCHAR(20) NOT NULL,
            enroll_id VARCHAR(40) NULL,
            student_code VARCHAR(30) NULL,
            firstname VARCHAR(100) NULL,
            surname VARCHAR(100) NULL,
            idcard VARCHAR(20) NULL,
            timetable_id VARCHAR(40) NULL,
            PRIMARY KEY (id),
            KEY idx_rms_enroll_term (school_id, term_key),
            KEY idx_rms_enroll_student (school_id, student_code),
            CONSTRAINT fk_rms_enroll_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
