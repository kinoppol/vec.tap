<?php
declare(strict_types=1);

return [
    'name' => 'จับกลุ่มผู้เรียนระดับ ปวส. เป็นกลุ่มแฝด',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS group_twins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_group_twins_term (school_id, term_id),
            CONSTRAINT fk_group_twins_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_group_twins_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS group_twin_members (
            twin_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (group_id),
            KEY idx_group_twin_members_twin (twin_id),
            CONSTRAINT fk_group_twin_members_twin FOREIGN KEY (twin_id) REFERENCES group_twins (id) ON DELETE CASCADE,
            CONSTRAINT fk_group_twin_members_group FOREIGN KEY (group_id) REFERENCES student_groups (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
