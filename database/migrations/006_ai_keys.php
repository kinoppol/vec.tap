<?php
declare(strict_types=1);

return [
    'name' => 'ชุด API Key หลายชุดและโมเดลที่เปิดใช้',
    'statements' => [
        "CREATE TABLE IF NOT EXISTS ai_credentials (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id INT UNSIGNED NOT NULL,
            label VARCHAR(128) NOT NULL,
            provider VARCHAR(32) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            api_key_encrypted TEXT NOT NULL,
            api_key_hint VARCHAR(8) NULL,
            last_test_at DATETIME NULL,
            last_test_status VARCHAR(16) NULL,
            last_test_ms INT NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ai_credentials_school (school_id),
            CONSTRAINT fk_ai_credentials_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS ai_models (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            credential_id INT UNSIGNED NOT NULL,
            school_id INT UNSIGNED NOT NULL,
            model_name VARCHAR(191) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ai_models_name (credential_id, model_name),
            KEY idx_ai_models_school (school_id, enabled),
            CONSTRAINT fk_ai_models_credential FOREIGN KEY (credential_id) REFERENCES ai_credentials (id) ON DELETE CASCADE,
            CONSTRAINT fk_ai_models_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'ALTER TABLE ai_settings ADD COLUMN IF NOT EXISTS working_model_id INT UNSIGNED NULL',

        "INSERT INTO ai_credentials (
            school_id, label, provider, base_url, api_key_encrypted, api_key_hint,
            last_test_at, last_test_status, last_test_ms, updated_by
         )
         SELECT school_id,
            CASE provider
                WHEN 'google' THEN 'Google AI Studio'
                WHEN 'custom' THEN 'Server LLM'
                ELSE 'OpenRouter'
            END,
            provider, base_url, api_key_encrypted, api_key_hint,
            last_test_at, last_test_status, last_test_ms, updated_by
         FROM ai_settings
         WHERE api_key_encrypted IS NOT NULL AND api_key_encrypted <> ''",

        "INSERT INTO ai_models (credential_id, school_id, model_name, enabled)
         SELECT c.id, s.school_id, LEFT(s.model, 191), 1
         FROM ai_settings s
         JOIN ai_credentials c ON c.school_id = s.school_id
         WHERE s.api_key_encrypted IS NOT NULL AND s.api_key_encrypted <> '' AND s.model <> ''",

        "UPDATE ai_settings s
         JOIN ai_models m ON m.school_id = s.school_id AND m.enabled = 1
         SET s.working_model_id = m.id
         WHERE s.working_model_id IS NULL",
    ],
];
