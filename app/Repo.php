<?php
declare(strict_types=1);

final class Repo
{
    public static function schools(): array
    {
        return Database::pdo()->query('SELECT id, name FROM schools ORDER BY id')->fetchAll();
    }

    public static function counts(int $schoolId): array
    {
        $pdo = Database::pdo();
        $count = static function (string $sql) use ($pdo, $schoolId): int {
            $statement = $pdo->prepare($sql);
            $statement->execute(['school_id' => $schoolId]);
            return (int) $statement->fetchColumn();
        };
        return [
            'teachers' => $count('SELECT COUNT(*) FROM teachers WHERE school_id = :school_id'),
            'groups' => $count('SELECT COUNT(*) FROM student_groups WHERE school_id = :school_id'),
            'subjects' => $count('SELECT COUNT(*) FROM subjects WHERE school_id = :school_id'),
            'rooms' => $count('SELECT COUNT(*) FROM rooms WHERE school_id = :school_id'),
            'campuses' => $count('SELECT COUNT(DISTINCT campus) FROM buildings WHERE school_id = :school_id AND campus <> \'\''),
        ];
    }

    public static function term(int $schoolId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM terms WHERE school_id = :school_id ORDER BY is_current DESC, id LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public static function teachers(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM teachers WHERE school_id = :school_id ORDER BY id');
        $statement->execute(['school_id' => $schoolId]);
        $teachers = $statement->fetchAll();
        $skills = Database::pdo()->prepare('SELECT skill FROM teacher_skills WHERE teacher_id = :teacher_id ORDER BY id');
        foreach ($teachers as &$teacher) {
            $skills->execute(['teacher_id' => $teacher['id']]);
            $teacher['skills'] = $skills->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($teacher);
        return $teachers;
    }

    public static function groups(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT g.*, t.name AS advisor_name, p.name AS plan_name
             FROM student_groups g
             LEFT JOIN teachers t ON t.id = g.advisor_id
             LEFT JOIN study_plans p ON p.id = g.plan_id
             WHERE g.school_id = :school_id
             ORDER BY g.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function group(int $schoolId, int $groupId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT g.*, t.name AS advisor_name
             FROM student_groups g
             LEFT JOIN teachers t ON t.id = g.advisor_id
             WHERE g.school_id = :school_id AND g.id = :id'
        );
        $statement->execute(['school_id' => $schoolId, 'id' => $groupId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public static function plans(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT p.*,
                (SELECT COUNT(*) FROM subjects s WHERE s.plan_id = p.id) AS subject_count,
                (SELECT COALESCE(SUM(s.theory + s.practice), 0) FROM subjects s WHERE s.plan_id = p.id) AS hours_per_week
             FROM study_plans p
             WHERE p.school_id = :school_id
             ORDER BY p.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $plans = $statement->fetchAll();
        $groups = Database::pdo()->prepare(
            'SELECT name FROM student_groups WHERE plan_id = :plan_id ORDER BY id'
        );
        $term = self::term($schoolId);
        foreach ($plans as &$plan) {
            $groups->execute(['plan_id' => $plan['id']]);
            $plan['groups'] = $groups->fetchAll(PDO::FETCH_COLUMN);
            $plan['term_label'] = $term['label'] ?? '';
        }
        unset($plan);
        return $plans;
    }

    public static function subjects(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT s.*, p.name AS plan_name, t.name AS teacher_name, r.code AS room_code
             FROM subjects s
             JOIN study_plans p ON p.id = s.plan_id
             LEFT JOIN teachers t ON t.id = s.teacher_id
             LEFT JOIN rooms r ON r.id = s.room_id
             WHERE s.school_id = :school_id
             ORDER BY p.id, s.sort_order, s.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function subjectsForPlan(int $schoolId, int $planId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT s.*, t.name AS teacher_name, r.code AS room_code
             FROM subjects s
             LEFT JOIN teachers t ON t.id = s.teacher_id
             LEFT JOIN rooms r ON r.id = s.room_id
             WHERE s.school_id = :school_id AND s.plan_id = :plan_id
             ORDER BY s.sort_order, s.id'
        );
        $statement->execute(['school_id' => $schoolId, 'plan_id' => $planId]);
        return $statement->fetchAll();
    }

    public static function buildings(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM buildings WHERE school_id = :school_id ORDER BY id');
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function rooms(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM rooms WHERE school_id = :school_id ORDER BY code');
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function policies(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM policies WHERE school_id = :school_id ORDER BY sort_order, id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function entries(int $schoolId, int $groupId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT e.*, s.demo_key, s.code, s.name, s.theory, s.practice, s.extra,
                    t.name AS teacher_name, r.code AS room_code
             FROM timetable_entries e
             JOIN subjects s ON s.id = e.subject_id
             LEFT JOIN teachers t ON t.id = s.teacher_id
             LEFT JOIN rooms r ON r.id = s.room_id
             WHERE e.school_id = :school_id AND e.group_id = :group_id
             ORDER BY e.day_index, e.start_period, e.id'
        );
        $statement->execute(['school_id' => $schoolId, 'group_id' => $groupId]);
        return $statement->fetchAll();
    }

    public static function replaceEntries(int $schoolId, int $groupId, array $entries): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM timetable_entries WHERE school_id = :school_id AND group_id = :group_id')
            ->execute(['school_id' => $schoolId, 'group_id' => $groupId]);
        $insert = $pdo->prepare(
            'INSERT INTO timetable_entries
                (school_id, group_id, subject_id, day_index, start_period, length_periods, is_manual, warning, moved)
             VALUES
                (:school_id, :group_id, :subject_id, :day_index, :start_period, :length_periods, :is_manual, :warning, :moved)'
        );
        foreach ($entries as $entry) {
            $insert->execute([
                'school_id' => $schoolId,
                'group_id' => $groupId,
                'subject_id' => (int) $entry['subject_id'],
                'day_index' => (int) $entry['day'],
                'start_period' => (int) $entry['start'],
                'length_periods' => (int) $entry['length'],
                'is_manual' => !empty($entry['manual']) ? 1 : 0,
                'warning' => $entry['warning'] !== null && $entry['warning'] !== '' ? $entry['warning'] : null,
                'moved' => !empty($entry['moved']) ? 1 : 0,
            ]);
        }
    }

    public static function state(int $groupId): array
    {
        $statement = Database::pdo()->prepare('SELECT phase, applied FROM schedule_states WHERE group_id = :group_id');
        $statement->execute(['group_id' => $groupId]);
        $row = $statement->fetch();
        return [
            'phase' => $row['phase'] ?? 'manual',
            'applied' => $row['applied'] ?? null,
        ];
    }

    public static function saveState(int $groupId, string $phase, ?string $applied): void
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO schedule_states (group_id, phase, applied) VALUES (:group_id, :phase, :applied)
             ON DUPLICATE KEY UPDATE phase = VALUES(phase), applied = VALUES(applied)'
        );
        $statement->execute([
            'group_id' => $groupId,
            'phase' => $phase,
            'applied' => $applied,
        ]);
    }

    public static function setPolicyType(int $schoolId, string $code, string $type): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE policies SET policy_type = :policy_type WHERE school_id = :school_id AND code = :code'
        );
        $statement->execute([
            'policy_type' => $type,
            'school_id' => $schoolId,
            'code' => $code,
        ]);
    }

    public static function ai(int $schoolId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM ai_settings WHERE school_id = :school_id');
        $statement->execute(['school_id' => $schoolId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public static function saveAi(int $schoolId, array $fields): void
    {
        $current = self::ai($schoolId);
        if ($current === null) {
            insert_default_ai(Database::pdo(), $schoolId);
            $current = self::ai($schoolId);
        }
        $statement = Database::pdo()->prepare(
            'UPDATE ai_settings SET
                provider = :provider,
                base_url = :base_url,
                api_key_encrypted = :api_key_encrypted,
                api_key_hint = :api_key_hint,
                model = :model,
                allow_act = :allow_act,
                require_confirm = :require_confirm,
                suggest_contact = :suggest_contact,
                log_actions = :log_actions,
                last_test_at = :last_test_at,
                last_test_status = :last_test_status,
                last_test_ms = :last_test_ms,
                updated_by = :updated_by,
                updated_at = NOW()
             WHERE school_id = :school_id'
        );
        $statement->execute([
            'provider' => $fields['provider'],
            'base_url' => $fields['base_url'],
            'api_key_encrypted' => $fields['api_key_encrypted'],
            'api_key_hint' => $fields['api_key_hint'],
            'model' => $fields['model'],
            'allow_act' => $fields['allow_act'],
            'require_confirm' => $fields['require_confirm'],
            'suggest_contact' => $fields['suggest_contact'],
            'log_actions' => $fields['log_actions'],
            'last_test_at' => $fields['last_test_at'],
            'last_test_status' => $fields['last_test_status'],
            'last_test_ms' => $fields['last_test_ms'],
            'updated_by' => $fields['updated_by'],
            'school_id' => $schoolId,
        ]);
    }

    public static function markAiTest(int $schoolId, string $status, ?int $ms): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE ai_settings SET last_test_at = NOW(), last_test_status = :status, last_test_ms = :ms WHERE school_id = :school_id'
        );
        $statement->execute([
            'status' => $status,
            'ms' => $ms,
            'school_id' => $schoolId,
        ]);
    }

    public static function logAi(int $schoolId, ?int $userId, string $action, string $detail): void
    {
        $settings = self::ai($schoolId);
        if ($settings && (int) $settings['log_actions'] !== 1) {
            return;
        }
        $statement = Database::pdo()->prepare(
            'INSERT INTO ai_logs (school_id, user_id, action, detail) VALUES (:school_id, :user_id, :action, :detail)'
        );
        $statement->execute([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'action' => $action,
            'detail' => mb_substr($detail, 0, 4000),
        ]);
    }

    public static function suggestions(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT g.*, s.code, s.name, s.theory, s.practice, s.extra, t.name AS teacher_name
             FROM skill_suggestions g
             JOIN subjects s ON s.id = g.subject_id
             LEFT JOIN teachers t ON t.id = g.teacher_id
             WHERE g.school_id = :school_id
             ORDER BY s.sort_order, s.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function progress(int $schoolId): array
    {
        $groups = self::groups($schoolId);
        $rows = [];
        foreach ($groups as $group) {
            $need = 0;
            $placed = 0;
            if (!empty($group['plan_id'])) {
                $subjects = self::subjectsForPlan($schoolId, (int) $group['plan_id']);
                $entries = self::entries($schoolId, (int) $group['id']);
                foreach ($subjects as $subject) {
                    $subjectNeed = (int) $subject['theory'] + (int) $subject['practice'];
                    $need += $subjectNeed;
                    $used = 0;
                    foreach ($entries as $entry) {
                        if ((int) $entry['subject_id'] === (int) $subject['id']) {
                            $used += (int) $entry['length_periods'];
                        }
                    }
                    $placed += min($subjectNeed, $used);
                }
            }
            $rows[] = [
                'name' => $group['name'],
                'pct' => $need > 0 ? (int) round($placed / $need * 100) : 0,
            ];
        }
        return $rows;
    }

    public static function alerts(int $schoolId): array
    {
        $alerts = [];
        foreach (self::groups($schoolId) as $group) {
            if (($group['note_tone'] ?? '') === 'danger' && !empty($group['note'])) {
                $alerts[] = [
                    'icon' => 'bi-people-fill',
                    'color' => '#B02A37',
                    'title' => 'ห้องเรียนเล็กกว่าจำนวนผู้เรียน',
                    'text' => $group['name'] . ' · ' . $group['note'],
                ];
            }
        }
        foreach (self::buildings($schoolId) as $building) {
            if (str_contains((string) $building['dist_label'], 'กม.')) {
                $alerts[] = [
                    'icon' => 'bi-geo-alt-fill',
                    'color' => '#C28A17',
                    'title' => 'ระยะเดินทางระหว่างวิทยาเขต',
                    'text' => $building['name'] . ' อยู่' . $building['campus'] . ' ระยะ ' . $building['dist_label'],
                ];
                break;
            }
        }
        $school = SchoolContext::current();
        if ($school && (int) $school['skills_ready'] !== 1) {
            $unassigned = 0;
            foreach (self::subjects($schoolId) as $subject) {
                if (empty($subject['teacher_id'])) {
                    $unassigned++;
                }
            }
            if ($unassigned > 0) {
                $alerts[] = [
                    'icon' => 'bi-hourglass-split',
                    'color' => '#6E6473',
                    'title' => 'ยังไม่ได้แบ่งรายวิชา',
                    'text' => 'มี ' . $unassigned . ' รายวิชาที่ยังไม่มีครูผู้สอน ให้ AI วิเคราะห์ทักษะครูก่อนจัดตาราง',
                ];
            }
        }
        if ($alerts === []) {
            $alerts[] = [
                'icon' => 'bi-check-circle',
                'color' => '#198754',
                'title' => 'ยังไม่มีข้อสังเกตเร่งด่วน',
                'text' => 'ข้อมูลของสถานศึกษานี้ยังไม่พบรายการที่ต้องเตือน',
            ];
        }
        return $alerts;
    }

    public static function usersForSchool(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, username, display_name, role FROM users WHERE school_id = :school_id ORDER BY id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }
}
