<?php
declare(strict_types=1);

return [
    'name' => 'อาคารเรียนและห้องเรียน',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS buildings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            short_name VARCHAR(64) NOT NULL,
            campus VARCHAR(255) NOT NULL DEFAULT '',
            lat VARCHAR(32) NOT NULL DEFAULT '',
            lng VARCHAR(32) NOT NULL DEFAULT '',
            dist_label VARCHAR(64) NOT NULL DEFAULT '',
            map_x DECIMAL(5,2) NOT NULL DEFAULT 50,
            map_y DECIMAL(5,2) NOT NULL DEFAULT 50,
            dot_color VARCHAR(16) NOT NULL DEFAULT '#D63384',
            badge_bg VARCHAR(16) NOT NULL DEFAULT '#F3EEF4',
            badge_fg VARCHAR(16) NOT NULL DEFAULT '#6E6473',
            PRIMARY KEY (id),
            KEY idx_buildings_school (school_id),
            CONSTRAINT fk_buildings_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS rooms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            building_id INT UNSIGNED NULL,
            code VARCHAR(32) NOT NULL,
            room_type VARCHAR(255) NOT NULL DEFAULT '',
            capacity INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rooms_school_code (school_id, code),
            CONSTRAINT fk_rooms_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_rooms_building FOREIGN KEY (building_id) REFERENCES buildings (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
