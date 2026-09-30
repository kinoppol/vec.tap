<?php
declare(strict_types=1);

return [
    'name' => 'ค่าเชื่อมต่อผู้ช่วย AI แยกตามสถานศึกษา',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS ai_settings (
            school_id INT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            api_key_encrypted TEXT NULL,
            api_key_hint VARCHAR(8) NULL,
            model VARCHAR(128) NOT NULL,
            allow_act TINYINT(1) NOT NULL DEFAULT 1,
            require_confirm TINYINT(1) NOT NULL DEFAULT 1,
            suggest_contact TINYINT(1) NOT NULL DEFAULT 1,
            log_actions TINYINT(1) NOT NULL DEFAULT 1,
            last_test_at DATETIME NULL,
            last_test_status VARCHAR(16) NULL,
            last_test_ms INT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (school_id),
            CONSTRAINT fk_ai_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS ai_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            action VARCHAR(64) NOT NULL,
            detail TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ai_logs_school (school_id, created_at),
            CONSTRAINT fk_ai_logs_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
            CONSTRAINT fk_ai_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
