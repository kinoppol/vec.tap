<?php
declare(strict_types=1);

return [
    'name' => 'มอบหมายครูเป็นผู้จัดตารางของกลุ่มผู้เรียน',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS group_schedulers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_group_scheduler (group_id, teacher_id),
            KEY idx_group_schedulers_teacher (school_id, teacher_id),
            CONSTRAINT fk_group_schedulers_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_group_schedulers_group FOREIGN KEY (group_id) REFERENCES student_groups (id) ON DELETE CASCADE,
            CONSTRAINT fk_group_schedulers_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];