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
            'teachers' => $count('SELECT COUNT(*) FROM teachers WHERE school_id = :school_id AND is_active = 1'),
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

    public static function terms(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT t.id, t.label, t.is_current, t.start_date, t.end_date,
                    (SELECT COUNT(*) FROM student_groups g WHERE g.term_id = t.id) AS group_count
             FROM terms t
             WHERE t.school_id = :school_id
             ORDER BY t.is_current DESC, t.start_date IS NULL, t.start_date DESC, t.id DESC'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function teachers(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM teachers WHERE school_id = :school_id AND is_active = 1 ORDER BY id');
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

    public static function addTeacherSkill(int $schoolId, int $teacherId, string $skill): void
    {
        $skill = trim($skill);
        if ($skill === '') {
            throw new RuntimeException('กรอกทักษะการสอน');
        }
        if (mb_strlen($skill) > 128) {
            throw new RuntimeException('ทักษะการสอนยาวเกิน 128 ตัวอักษร');
        }
        if (self::teacherId($schoolId, $teacherId) === null) {
            throw new RuntimeException('ไม่พบครูผู้สอน');
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM teacher_skills WHERE teacher_id = :teacher_id AND skill = :skill LIMIT 1');
        $exists->execute(['teacher_id' => $teacherId, 'skill' => $skill]);
        if ((int) $exists->fetchColumn() > 0) {
            throw new RuntimeException('มีทักษะนี้อยู่แล้ว');
        }
        $pdo->prepare('INSERT INTO teacher_skills (teacher_id, skill) VALUES (:teacher_id, :skill)')
            ->execute(['teacher_id' => $teacherId, 'skill' => $skill]);
    }

    public static function removeTeacherSkill(int $schoolId, int $teacherId, string $skill): void
    {
        if (self::teacherId($schoolId, $teacherId) === null) {
            throw new RuntimeException('ไม่พบครูผู้สอน');
        }
        Database::pdo()->prepare('DELETE FROM teacher_skills WHERE teacher_id = :teacher_id AND skill = :skill')
            ->execute(['teacher_id' => $teacherId, 'skill' => trim($skill)]);
    }

    public static function teacherSkillExportRows(int $schoolId): array
    {
        $rows = [];
        foreach (self::teachers($schoolId) as $teacher) {
            $skills = $teacher['skills'];
            if ($skills === []) {
                $rows[] = [$teacher['name'], ''];
                continue;
            }
            foreach ($skills as $skill) {
                $rows[] = [$teacher['name'], $skill];
            }
        }
        return $rows;
    }

    public static function importTeacherSkillRows(int $schoolId, array $rows): int
    {
        if (isset($rows[0][0]) && mb_stripos((string) $rows[0][0], 'ชื่อ') !== false) {
            array_shift($rows);
        }
        $byName = [];
        foreach (self::teachers($schoolId) as $teacher) {
            $name = (string) $teacher['name'];
            if (!isset($byName[$name])) {
                $byName[$name] = (int) $teacher['id'];
            }
        }
        $incoming = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }
            if (!isset($byName[$name])) {
                throw new RuntimeException('ไม่พบครูชื่อ ' . $name);
            }
            if (!isset($incoming[$name])) {
                $incoming[$name] = [];
            }
            foreach (preg_split('/\|/u', (string) ($row[1] ?? '')) ?: [] as $part) {
                $skill = trim($part);
                if ($skill === '') {
                    continue;
                }
                if (mb_strlen($skill) > 128) {
                    throw new RuntimeException('ทักษะของ ' . $name . ' ยาวเกิน 128 ตัวอักษร');
                }
                if (!in_array($skill, $incoming[$name], true)) {
                    $incoming[$name][] = $skill;
                }
            }
        }
        if ($incoming === []) {
            throw new RuntimeException('ไฟล์ไม่มีชื่อครู');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM teacher_skills WHERE teacher_id = :teacher_id');
            $insert = $pdo->prepare('INSERT INTO teacher_skills (teacher_id, skill) VALUES (:teacher_id, :skill)');
            foreach ($incoming as $name => $skills) {
                $teacherId = $byName[$name];
                $delete->execute(['teacher_id' => $teacherId]);
                foreach ($skills as $skill) {
                    $insert->execute(['teacher_id' => $teacherId, 'skill' => $skill]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return count($incoming);
    }

    public static function teacher(int $schoolId, int $teacherId): ?array
    {
        if ($teacherId <= 0) {
            return null;
        }
        $statement = Database::pdo()->prepare(
            'SELECT * FROM teachers WHERE school_id = :school_id AND id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'id' => $teacherId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public static function setTeacherHours(int $schoolId, int $teacherId, int $minHours, int $maxHours): void
    {
        if (self::teacher($schoolId, $teacherId) === null) {
            throw new RuntimeException('ไม่พบครูผู้สอน');
        }
        if ($minHours < 0 || $maxHours < 0 || $minHours > 50 || $maxHours > 50) {
            throw new RuntimeException('ชั่วโมงสอนต้องอยู่ระหว่าง 0 ถึง 50 ต่อสัปดาห์');
        }
        if ($minHours > $maxHours) {
            throw new RuntimeException('ชั่วโมงต่ำสุดต้องไม่เกินชั่วโมงสูงสุด');
        }
        Database::pdo()->prepare(
            'UPDATE teachers SET min_hours = :min_hours, max_hours = :max_hours WHERE id = :id AND school_id = :school_id'
        )->execute([
            'min_hours' => $minHours,
            'max_hours' => $maxHours,
            'id' => $teacherId,
            'school_id' => $schoolId,
        ]);
    }

    public static function teachingHours(int $schoolId, ?int $exceptGroupId = null, ?int $termId = null): array
    {
        $sql = 'SELECT s.teacher_id, COALESCE(SUM(e.length_periods), 0) AS hours
                FROM timetable_entries e
                JOIN subjects s ON s.id = e.subject_id
                JOIN student_groups g ON g.id = e.group_id
                WHERE e.school_id = :school_id AND s.teacher_id IS NOT NULL';
        $params = ['school_id' => $schoolId];
        if ($exceptGroupId !== null) {
            $sql .= ' AND e.group_id <> :group_id';
            $params['group_id'] = $exceptGroupId;
        }
        if ($termId !== null && $termId > 0) {
            $sql .= ' AND g.term_id = :term_id';
            $params['term_id'] = $termId;
        }
        $sql .= ' GROUP BY s.teacher_id';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);
        $hours = [];
        foreach ($statement->fetchAll() as $row) {
            $hours[(int) $row['teacher_id']] = (int) $row['hours'];
        }
        return $hours;
    }

    public static function placedHours(int $schoolId, int $subjectId): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(length_periods), 0) FROM timetable_entries
             WHERE school_id = :school_id AND subject_id = :subject_id'
        );
        $statement->execute(['school_id' => $schoolId, 'subject_id' => $subjectId]);
        return (int) $statement->fetchColumn();
    }

    public static function groups(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT g.*, t.name AS advisor_name, p.name AS plan_name, tm.label AS term_label
             FROM student_groups g
             LEFT JOIN teachers t ON t.id = g.advisor_id
             LEFT JOIN study_plans p ON p.id = g.plan_id
             LEFT JOIN terms tm ON tm.id = g.term_id
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

    public static function scheduleTeacherId(int $schoolId, array $user): int
    {
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if ($teacherId > 0 && self::teacher($schoolId, $teacherId) !== null) {
            return $teacherId;
        }
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($schoolId <= 0 || $name === '') {
            return 0;
        }
        $statement = Database::pdo()->prepare(
            'SELECT id FROM teachers WHERE school_id = :school_id AND name = :name AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'name' => $name]);
        return (int) $statement->fetchColumn();
    }

    public static function groupSchedulers(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT DISTINCT g2.id AS group_id, gs.teacher_id, t.name
             FROM group_schedulers gs
             JOIN teachers t ON t.id = gs.teacher_id
             JOIN student_groups g ON g.id = gs.group_id AND g.school_id = gs.school_id
             JOIN student_groups g2 ON g2.school_id = g.school_id AND ' . self::sameGroupSql('g', 'g2') . '
             WHERE gs.school_id = :school_id
             ORDER BY t.name, g2.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $groupId = (int) $row['group_id'];
            $teacherId = (int) $row['teacher_id'];
            foreach ($map[$groupId] ?? [] as $existing) {
                if ($existing['teacher_id'] === $teacherId) {
                    continue 2;
                }
            }
            $map[$groupId][] = [
                'teacher_id' => $teacherId,
                'name' => (string) $row['name'],
            ];
        }
        return $map;
    }

    public static function schedulerGroupIds(int $schoolId, int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }
        $statement = Database::pdo()->prepare(
            'SELECT DISTINCT g2.id
             FROM group_schedulers gs
             JOIN student_groups g ON g.id = gs.group_id AND g.school_id = gs.school_id
             JOIN student_groups g2 ON g2.school_id = g.school_id AND ' . self::sameGroupSql('g', 'g2') . '
             WHERE gs.school_id = :school_id AND gs.teacher_id = :teacher_id
             ORDER BY g2.id'
        );
        $statement->execute(['school_id' => $schoolId, 'teacher_id' => $teacherId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function assignGroupScheduler(int $schoolId, int $groupId, int $teacherId): void
    {
        if (self::teacher($schoolId, $teacherId) === null) {
            throw new RuntimeException('ไม่พบครูผู้สอน');
        }
        $groupIds = self::siblingGroupIds($schoolId, $groupId);
        if ($groupIds === []) {
            throw new RuntimeException('ไม่พบกลุ่มผู้เรียน');
        }
        $insert = Database::pdo()->prepare(
            'INSERT INTO group_schedulers (school_id, group_id, teacher_id)
             VALUES (:school_id, :group_id, :teacher_id)'
        );
        $inserted = 0;
        foreach ($groupIds as $id) {
            try {
                $insert->execute([
                    'school_id' => $schoolId,
                    'group_id' => $id,
                    'teacher_id' => $teacherId,
                ]);
                $inserted++;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
            }
        }
        if ($inserted === 0) {
            throw new RuntimeException('มอบหมายครูคนนี้ไว้แล้ว');
        }
    }

    public static function removeGroupScheduler(int $schoolId, int $groupId, int $teacherId): void
    {
        $groupIds = self::siblingGroupIds($schoolId, $groupId);
        if ($groupIds === []) {
            throw new RuntimeException('ไม่พบกลุ่มผู้เรียน');
        }
        $params = ['school_id' => $schoolId, 'teacher_id' => $teacherId];
        $holders = [];
        foreach ($groupIds as $index => $id) {
            $key = 'group_id_' . $index;
            $holders[] = ':' . $key;
            $params[$key] = $id;
        }
        $statement = Database::pdo()->prepare(
            'DELETE FROM group_schedulers
             WHERE school_id = :school_id AND teacher_id = :teacher_id AND group_id IN (' . implode(', ', $holders) . ')'
        );
        $statement->execute($params);
        if ($statement->rowCount() < 1) {
            throw new RuntimeException('ไม่พบการมอบหมายนี้');
        }
    }

    private static function siblingGroupIds(int $schoolId, int $groupId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT g2.id
             FROM student_groups g
             JOIN student_groups g2 ON g2.school_id = g.school_id AND ' . self::sameGroupSql('g', 'g2') . '
             WHERE g.school_id = :school_id AND g.id = :id
             ORDER BY g2.id'
        );
        $statement->execute(['school_id' => $schoolId, 'id' => $groupId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function sameGroupSql(string $left, string $right): string
    {
        return '((NULLIF(' . $left . '.rms_group_code, \'\') IS NOT NULL AND ' . $right . '.rms_group_code = ' . $left . '.rms_group_code)
            OR (NULLIF(' . $left . '.rms_group_code, \'\') IS NULL AND NULLIF(' . $right . '.rms_group_code, \'\') IS NULL AND ' . $right . '.name = ' . $left . '.name))';
    }

    public static function plans(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT p.*, tm.label AS term_label,
                (SELECT COUNT(*) FROM subjects s WHERE s.plan_id = p.id) AS subject_count,
                (SELECT COALESCE(SUM(s.theory + s.practice), 0) FROM subjects s WHERE s.plan_id = p.id) AS hours_per_week
             FROM study_plans p
             LEFT JOIN terms tm ON tm.id = p.term_id
             WHERE p.school_id = :school_id
             ORDER BY p.term_id, p.name, p.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $plans = $statement->fetchAll();
        $groups = Database::pdo()->prepare(
            'SELECT name, rms_group_code FROM student_groups WHERE school_id = :school_id AND plan_id = :plan_id ORDER BY id'
        );
        foreach ($plans as &$plan) {
            $groups->execute(['school_id' => $schoolId, 'plan_id' => $plan['id']]);
            $plan['groups'] = $groups->fetchAll();
        }
        unset($plan);
        return $plans;
    }

    public static function planDetail(int $schoolId, int $planId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT p.*, tm.label AS term_label
             FROM study_plans p
             LEFT JOIN terms tm ON tm.id = p.term_id
             WHERE p.school_id = :school_id AND p.id = :id
             LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'id' => $planId]);
        $plan = $statement->fetch();
        if (!$plan) {
            return null;
        }
        $groups = Database::pdo()->prepare(
            'SELECT id, name, rms_group_code FROM student_groups WHERE school_id = :school_id AND plan_id = :plan_id ORDER BY id'
        );
        $groups->execute(['school_id' => $schoolId, 'plan_id' => $planId]);
        $plan['groups'] = $groups->fetchAll();
        $plan['subjects'] = self::subjectsForPlan($schoolId, $planId);
        return $plan;
    }

    public static function ensureGroupPlan(int $schoolId, int $groupId): int
    {
        $group = self::group($schoolId, $groupId);
        if ($group === null) {
            throw new RuntimeException('ไม่พบกลุ่มผู้เรียนนี้');
        }
        $planId = (int) ($group['plan_id'] ?? 0);
        if ($planId > 0 && self::planDetail($schoolId, $planId) !== null) {
            return $planId;
        }
        $name = trim((string) $group['level'] . ' ' . (string) $group['name']);
        if ($name === '') {
            $name = 'แผนกลุ่ม ' . $groupId;
        }
        $insert = Database::pdo()->prepare(
            'INSERT INTO study_plans (school_id, term_id, name, credits) VALUES (:school_id, :term_id, :name, 0)'
        );
        $insert->execute([
            'school_id' => $schoolId,
            'term_id' => (int) $group['term_id'],
            'name' => mb_substr($name, 0, 255),
        ]);
        $planId = (int) Database::pdo()->lastInsertId();
        Database::pdo()->prepare(
            'UPDATE student_groups SET plan_id = :plan_id WHERE id = :id AND school_id = :school_id'
        )->execute(['plan_id' => $planId, 'id' => $groupId, 'school_id' => $schoolId]);
        return $planId;
    }

    public static function renamePlan(int $schoolId, int $planId, string $name): void
    {
        self::assertPlan($schoolId, $planId);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('ชื่อแผนต้องมีอย่างน้อย 1 ตัวอักษรและไม่เกิน 255 ตัว');
        }
        Database::pdo()->prepare(
            'UPDATE study_plans SET name = :name WHERE id = :id AND school_id = :school_id'
        )->execute(['name' => $name, 'id' => $planId, 'school_id' => $schoolId]);
    }

    public static function addPlanSubject(int $schoolId, int $planId, string $code, string $name, int $theory, int $practice, int $extra): int
    {
        self::assertPlan($schoolId, $planId);
        [$code, $name] = self::planSubjectText($code, $name);
        self::assertCodeHours($schoolId, $code, $theory, $practice);
        $sort = Database::pdo()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM subjects WHERE plan_id = :plan_id');
        $sort->execute(['plan_id' => $planId]);
        try {
            Database::pdo()->prepare(
                'INSERT INTO subjects (school_id, plan_id, code, name, theory, practice, extra, sort_order)
                 VALUES (:school_id, :plan_id, :code, :name, :theory, :practice, :extra, :sort_order)'
            )->execute([
                'school_id' => $schoolId,
                'plan_id' => $planId,
                'code' => $code,
                'name' => $name,
                'theory' => $theory,
                'practice' => $practice,
                'extra' => $extra,
                'sort_order' => (int) $sort->fetchColumn(),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('รหัสวิชานี้มีในแผนแล้ว');
            }
            throw $exception;
        }
        $subjectId = (int) Database::pdo()->lastInsertId();
        self::syncSubjectHours($schoolId, $code, $theory, $practice, $extra);
        return $subjectId;
    }

    public static function updatePlanSubject(int $schoolId, int $planId, int $subjectId, string $code, string $name, int $theory, int $practice, int $extra): void
    {
        self::assertPlan($schoolId, $planId);
        [$code, $name] = self::planSubjectText($code, $name);
        if ($theory + $practice < self::placedHours($schoolId, $subjectId)) {
            throw new RuntimeException('ชั่วโมงทฤษฎีกับปฏิบัติรวมกันน้อยกว่าคาบที่ลงไว้แล้ว ลบคาบออกก่อนแล้วค่อยลดชั่วโมง');
        }
        self::assertCodeHours($schoolId, $code, $theory, $practice);
        try {
            $update = Database::pdo()->prepare(
                'UPDATE subjects SET code = :code, name = :name
                 WHERE id = :id AND school_id = :school_id AND plan_id = :plan_id'
            );
            $update->execute([
                'code' => $code,
                'name' => $name,
                'id' => $subjectId,
                'school_id' => $schoolId,
                'plan_id' => $planId,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('รหัสวิชานี้มีในแผนแล้ว');
            }
            throw $exception;
        }
        if ($update->rowCount() === 0 && !self::planSubjectExists($schoolId, $planId, $subjectId)) {
            throw new RuntimeException('ไม่พบรายวิชานี้ในแผน');
        }
        self::syncSubjectHours($schoolId, $code, $theory, $practice, $extra);
    }

    public static function deletePlanSubject(int $schoolId, int $planId, int $subjectId): int
    {
        self::assertPlan($schoolId, $planId);
        if (!self::planSubjectExists($schoolId, $planId, $subjectId)) {
            throw new RuntimeException('ไม่พบรายวิชานี้ในแผน');
        }
        $placed = self::placedHours($schoolId, $subjectId);
        Database::pdo()->prepare(
            'DELETE FROM subjects WHERE id = :id AND school_id = :school_id AND plan_id = :plan_id'
        )->execute(['id' => $subjectId, 'school_id' => $schoolId, 'plan_id' => $planId]);
        self::refreshPlanCredits($schoolId, $planId);
        return $placed;
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

    public static function setSubjectHours(int $schoolId, int $planId, int $subjectId, int $theory, int $practice, int $extra): void
    {
        if ($planId <= 0 || $subjectId <= 0) {
            throw new RuntimeException('กลุ่มนี้ยังไม่มีแผนการเรียน จึงปรับ ท-ป-น ไม่ได้');
        }
        $code = Database::pdo()->prepare(
            'SELECT code FROM subjects WHERE id = :id AND school_id = :school_id AND plan_id = :plan_id LIMIT 1'
        );
        $code->execute(['id' => $subjectId, 'school_id' => $schoolId, 'plan_id' => $planId]);
        $subjectCode = $code->fetchColumn();
        if ($subjectCode === false || $subjectCode === '') {
            throw new RuntimeException('ไม่พบรายวิชานี้ในแผนของกลุ่มที่กำลังจัด');
        }
        self::syncSubjectHours($schoolId, (string) $subjectCode, $theory, $practice, $extra);
    }

    private static function syncSubjectHours(int $schoolId, string $code, int $theory, int $practice, int $extra): void
    {
        self::assertCodeHours($schoolId, $code, $theory, $practice);
        $pdo = Database::pdo();
        $plans = $pdo->prepare('SELECT DISTINCT plan_id FROM subjects WHERE school_id = :school_id AND code = :code');
        $plans->execute(['school_id' => $schoolId, 'code' => $code]);
        $planIds = $plans->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare(
            'UPDATE subjects SET theory = :theory, practice = :practice, extra = :extra
             WHERE school_id = :school_id AND code = :code'
        )->execute([
            'theory' => $theory,
            'practice' => $practice,
            'extra' => $extra,
            'school_id' => $schoolId,
            'code' => $code,
        ]);
        foreach ($planIds as $planId) {
            self::refreshPlanCredits($schoolId, (int) $planId);
        }
    }

    private static function assertCodeHours(int $schoolId, string $code, int $theory, int $practice): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT s.id
             FROM subjects s
             LEFT JOIN timetable_entries e ON e.subject_id = s.id AND e.school_id = s.school_id
             WHERE s.school_id = :school_id AND s.code = :code
             GROUP BY s.id
             HAVING COALESCE(SUM(e.length_periods), 0) > :need
             LIMIT 1'
        );
        $statement->execute([
            'school_id' => $schoolId,
            'code' => $code,
            'need' => $theory + $practice,
        ]);
        $subjectId = (int) $statement->fetchColumn();
        if ($subjectId <= 0) {
            return;
        }
        $group = Database::pdo()->prepare(
            'SELECT g.name
             FROM student_groups g
             JOIN subjects s ON s.plan_id = g.plan_id AND s.school_id = g.school_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $group->execute(['id' => $subjectId]);
        $name = trim((string) $group->fetchColumn());
        $where = $name !== '' ? 'ในกลุ่ม ' . $name . ' ' : '';
        throw new RuntimeException('ชั่วโมงทฤษฎีกับปฏิบัติรวมกันน้อยกว่าคาบที่ลงไว้แล้ว' . $where . 'ลบคาบออกก่อนแล้วค่อยลดชั่วโมง');
    }

    private static function refreshPlanCredits(int $schoolId, int $planId): void
    {
        Database::pdo()->prepare(
            'UPDATE study_plans p
             SET credits = (SELECT COALESCE(SUM(s.theory + s.practice), 0) FROM subjects s WHERE s.plan_id = p.id)
             WHERE p.id = :id AND p.school_id = :school_id'
        )->execute(['id' => $planId, 'school_id' => $schoolId]);
    }

    private static function assertPlan(int $schoolId, int $planId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id FROM study_plans WHERE id = :id AND school_id = :school_id LIMIT 1'
        );
        $statement->execute(['id' => $planId, 'school_id' => $schoolId]);
        if (!$statement->fetchColumn()) {
            throw new RuntimeException('ไม่พบแผนการเรียนนี้ในสถานศึกษาปัจจุบัน');
        }
    }

    /** @return array{0: string, 1: string} */
    private static function planSubjectText(string $code, string $name): array
    {
        $code = trim($code);
        $name = trim($name);
        if ($code === '' || mb_strlen($code) > 32) {
            throw new RuntimeException('รหัสวิชาต้องมีอย่างน้อย 1 ตัวอักษรและไม่เกิน 32 ตัว');
        }
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('ชื่อวิชาต้องมีอย่างน้อย 1 ตัวอักษรและไม่เกิน 255 ตัว');
        }
        return [$code, $name];
    }

    private static function planSubjectExists(int $schoolId, int $planId, int $subjectId): bool
    {
        $exists = Database::pdo()->prepare(
            'SELECT id FROM subjects WHERE id = :id AND school_id = :school_id AND plan_id = :plan_id LIMIT 1'
        );
        $exists->execute(['id' => $subjectId, 'school_id' => $schoolId, 'plan_id' => $planId]);
        return (bool) $exists->fetchColumn();
    }

    public static function buildings(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM buildings WHERE school_id = :school_id ORDER BY id');
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function rooms(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT r.*, b.name AS building_name, b.short_name AS building_short
             FROM rooms r
             LEFT JOIN buildings b ON b.id = r.building_id
             WHERE r.school_id = :school_id
             ORDER BY r.code'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function placedLessons(int $schoolId, ?int $termId = null): array
    {
        $sql = 'SELECT e.day_index, e.start_period, e.length_periods,
                    g.id AS group_id, g.name AS group_name, g.term_id,
                    s.code AS subject_code, s.name AS subject_name,
                    t.id AS teacher_id, t.name AS teacher_name,
                    r.id AS room_id, r.code AS room_code
             FROM timetable_entries e
             JOIN student_groups g ON g.id = e.group_id
             JOIN subjects s ON s.id = e.subject_id
             LEFT JOIN teachers t ON t.id = s.teacher_id
             LEFT JOIN rooms r ON r.id = s.room_id
             WHERE e.school_id = :school_id';
        $params = ['school_id' => $schoolId];
        if ($termId !== null && $termId > 0) {
            $sql .= ' AND g.term_id = :term_id';
            $params['term_id'] = $termId;
        }
        $sql .= ' ORDER BY e.day_index, e.start_period, e.id';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function buildingExportRows(int $schoolId): array
    {
        $rows = [];
        foreach (self::buildings($schoolId) as $building) {
            $rows[] = [
                $building['name'],
                $building['short_name'],
                $building['campus'],
                $building['lat'],
                $building['lng'],
                $building['dist_label'],
            ];
        }
        return $rows;
    }

    public static function roomExportRows(int $schoolId): array
    {
        $rows = [];
        foreach (self::rooms($schoolId) as $room) {
            $rows[] = [
                $room['code'],
                (string) ($room['building_name'] ?? ''),
                $room['room_type'],
                (string) (int) $room['capacity'],
            ];
        }
        return $rows;
    }

    public static function facilityFileKind(array $rows): string
    {
        foreach (array_slice($rows, 0, 3) as $row) {
            $kind = self::facilityRowKind($row);
            if ($kind !== '') {
                return $kind;
            }
        }
        return '';
    }

    private static function facilityRowKind(array $row): string
    {
        $first = trim((string) ($row[0] ?? ''));
        $second = trim((string) ($row[1] ?? ''));
        if (in_array($first, ['รหัส', 'รหัสห้อง', 'รหัสห้องเรียน'], true)) {
            return 'rooms';
        }
        if (in_array($first, ['ชื่อ', 'ชื่ออาคาร'], true) || $second === 'ชื่อย่อ') {
            return 'buildings';
        }
        return '';
    }

    private static function dropFacilityHeader(array $rows, string $kind): array
    {
        foreach ($rows as $index => $row) {
            if (self::facilityRowKind($row) === $kind) {
                return array_values(array_slice($rows, $index + 1));
            }
        }
        return $rows;
    }

    public static function importBuildingRows(int $schoolId, array $rows): int
    {
        if (self::facilityFileKind($rows) === 'rooms') {
            throw new RuntimeException('ไฟล์นี้เป็นข้อมูลห้องเรียน ให้ใช้ปุ่มนำเข้าห้องเรียน');
        }
        $rows = self::dropFacilityHeader($rows, 'buildings');
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id, lat, lng FROM buildings WHERE school_id = :school_id AND name = :name LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO buildings (school_id, name, short_name, campus, lat, lng, dist_label)
             VALUES (:school_id, :name, :short_name, :campus, :lat, :lng, :dist_label)'
        );
        $update = $pdo->prepare(
            'UPDATE buildings
             SET short_name = :short_name, campus = :campus, lat = :lat, lng = :lng, dist_label = :dist_label
             WHERE id = :id AND school_id = :school_id'
        );
        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $name = trim((string) ($row[0] ?? ''));
                if ($name === '') {
                    continue;
                }
                $name = mb_substr($name, 0, 255);
                $shortName = trim((string) ($row[1] ?? ''));
                if ($shortName === '') {
                    $shortName = $name;
                }
                $point = self::optionalCoordinates((string) ($row[3] ?? ''), (string) ($row[4] ?? ''));
                $fields = [
                    'short_name' => mb_substr($shortName, 0, 64),
                    'campus' => mb_substr(trim((string) ($row[2] ?? '')), 0, 255),
                    'dist_label' => mb_substr(trim((string) ($row[5] ?? '')), 0, 64),
                ];
                $find->execute(['school_id' => $schoolId, 'name' => $name]);
                $current = $find->fetch();
                if ($current) {
                    $fields['lat'] = $point === null ? (string) $current['lat'] : $point['lat'];
                    $fields['lng'] = $point === null ? (string) $current['lng'] : $point['lng'];
                    $fields['id'] = (int) $current['id'];
                    $fields['school_id'] = $schoolId;
                    $update->execute($fields);
                } else {
                    $insert->execute([
                        'school_id' => $schoolId,
                        'name' => $name,
                        'short_name' => $fields['short_name'],
                        'campus' => $fields['campus'],
                        'lat' => $point === null ? '' : $point['lat'],
                        'lng' => $point === null ? '' : $point['lng'],
                        'dist_label' => $fields['dist_label'],
                    ]);
                }
                $count++;
            }
            if ($count === 0) {
                throw new RuntimeException('ไฟล์ไม่มีชื่ออาคาร');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return $count;
    }

    public static function importRoomRows(int $schoolId, array $rows): int
    {
        if (self::facilityFileKind($rows) === 'buildings') {
            throw new RuntimeException('ไฟล์นี้เป็นข้อมูลอาคาร ให้ใช้ปุ่มนำเข้าอาคาร');
        }
        $rows = self::dropFacilityHeader($rows, 'rooms');
        $buildings = [];
        $ambiguous = [];
        foreach (self::buildings($schoolId) as $building) {
            foreach ([(string) $building['name'], (string) $building['short_name']] as $label) {
                $label = trim($label);
                if ($label === '') {
                    continue;
                }
                if (isset($buildings[$label]) && $buildings[$label] !== (int) $building['id']) {
                    $ambiguous[$label] = true;
                    continue;
                }
                $buildings[$label] = (int) $building['id'];
            }
        }
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id FROM rooms WHERE school_id = :school_id AND code = :code LIMIT 1');
        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $code = mb_substr(trim((string) ($row[0] ?? '')), 0, 32);
                if ($code === '') {
                    continue;
                }
                $buildingName = trim((string) ($row[1] ?? ''));
                $buildingId = null;
                if ($buildingName !== '') {
                    if (isset($ambiguous[$buildingName])) {
                        throw new RuntimeException('ชื่อ «' . $buildingName . '» ตรงกับอาคารมากกว่าหนึ่งหลัง ใส่ชื่ออาคารแบบเต็ม');
                    }
                    if (!isset($buildings[$buildingName])) {
                        throw new RuntimeException('ไม่พบอาคารชื่อ ' . $buildingName);
                    }
                    $buildingId = $buildings[$buildingName];
                }
                $capacityText = trim((string) ($row[3] ?? ''));
                if ($capacityText === '') {
                    $capacity = 0;
                } elseif (!is_numeric(str_replace(',', '.', $capacityText))) {
                    throw new RuntimeException('ความจุของห้อง ' . $code . ' ต้องเป็นจำนวนเต็ม');
                } else {
                    $capacity = (int) round((float) str_replace(',', '.', $capacityText));
                }
                if ($capacity > 999) {
                    throw new RuntimeException('ความจุของห้อง ' . $code . ' ต้องไม่เกิน 999');
                }
                $roomType = mb_substr(trim((string) ($row[2] ?? '')), 0, 255);
                $find->execute(['school_id' => $schoolId, 'code' => $code]);
                $roomId = (int) $find->fetchColumn();
                if ($roomId > 0) {
                    $update = $pdo->prepare(
                        'UPDATE rooms SET building_id = :building_id, room_type = :room_type, capacity = :capacity
                         WHERE id = :id AND school_id = :school_id'
                    );
                    $update->bindValue(':building_id', $buildingId, $buildingId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $update->bindValue(':room_type', $roomType);
                    $update->bindValue(':capacity', $capacity, PDO::PARAM_INT);
                    $update->bindValue(':id', $roomId, PDO::PARAM_INT);
                    $update->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
                    $update->execute();
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO rooms (school_id, building_id, code, room_type, capacity)
                         VALUES (:school_id, :building_id, :code, :room_type, :capacity)'
                    );
                    $insert->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
                    $insert->bindValue(':building_id', $buildingId, $buildingId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insert->bindValue(':code', $code);
                    $insert->bindValue(':room_type', $roomType);
                    $insert->bindValue(':capacity', $capacity, PDO::PARAM_INT);
                    $insert->execute();
                }
                $count++;
            }
            if ($count === 0) {
                throw new RuntimeException('ไฟล์ไม่มีรหัสห้อง');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return $count;
    }

    public static function addBuilding(int $schoolId, string $name, string $shortName, string $campus, string $distLabel, string $lat, string $lng): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('กรอกชื่ออาคาร');
        }
        $name = mb_substr($name, 0, 255);
        $shortName = trim($shortName);
        if ($shortName === '') {
            $shortName = $name;
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM buildings WHERE school_id = :school_id AND name = :name LIMIT 1');
        $exists->execute(['school_id' => $schoolId, 'name' => $name]);
        if ((int) $exists->fetchColumn() > 0) {
            throw new RuntimeException('มีอาคารชื่อนี้อยู่แล้ว');
        }
        $point = self::coordinates($lat, $lng);
        $pdo->prepare(
            'INSERT INTO buildings (school_id, name, short_name, campus, lat, lng, dist_label)
             VALUES (:school_id, :name, :short_name, :campus, :lat, :lng, :dist_label)'
        )->execute([
            'school_id' => $schoolId,
            'name' => $name,
            'short_name' => mb_substr($shortName, 0, 64),
            'campus' => mb_substr(trim($campus), 0, 255),
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'dist_label' => mb_substr(trim($distLabel), 0, 64),
        ]);
    }

    public static function setBuildingPoint(int $schoolId, int $buildingId, string $lat, string $lng): void
    {
        if (self::buildingId($schoolId, $buildingId) === null) {
            throw new RuntimeException('ไม่พบอาคารนี้');
        }
        $point = self::coordinates($lat, $lng);
        Database::pdo()->prepare(
            'UPDATE buildings SET lat = :lat, lng = :lng WHERE id = :id AND school_id = :school_id'
        )->execute([
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'id' => $buildingId,
            'school_id' => $schoolId,
        ]);
    }

    public static function addRoom(int $schoolId, string $code, int $buildingId, string $roomType, int $capacity): void
    {
        $code = mb_substr(trim($code), 0, 32);
        if ($code === '') {
            throw new RuntimeException('กรอกรหัสห้อง');
        }
        $buildingId = self::buildingId($schoolId, $buildingId);
        try {
            $insert = Database::pdo()->prepare(
                'INSERT INTO rooms (school_id, building_id, code, room_type, capacity)
                 VALUES (:school_id, :building_id, :code, :room_type, :capacity)'
            );
            $insert->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
            if ($buildingId === null) {
                $insert->bindValue(':building_id', null, PDO::PARAM_NULL);
            } else {
                $insert->bindValue(':building_id', $buildingId, PDO::PARAM_INT);
            }
            $insert->bindValue(':code', $code);
            $insert->bindValue(':room_type', mb_substr(trim($roomType), 0, 255));
            $insert->bindValue(':capacity', max(0, $capacity), PDO::PARAM_INT);
            $insert->execute();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('รหัสห้องนี้มีอยู่แล้ว');
            }
            throw $exception;
        }
    }

    public static function ensureTeacherId(int $schoolId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $name = mb_substr($name, 0, 255);
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id FROM teachers WHERE school_id = :school_id AND name = :name LIMIT 1');
        $find->execute(['school_id' => $schoolId, 'name' => $name]);
        $id = (int) $find->fetchColumn();
        if ($id > 0) {
            $pdo->prepare('UPDATE teachers SET is_active = 1 WHERE id = :id AND school_id = :school_id')
                ->execute(['id' => $id, 'school_id' => $schoolId]);
            return $id;
        }
        $pdo->prepare(
            'INSERT INTO teachers (school_id, name, dept, degree, max_hours, is_active)
             VALUES (:school_id, :name, \'\', \'\', 18, 1)'
        )->execute(['school_id' => $schoolId, 'name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    public static function ensureRoomId(int $schoolId, string $code): ?int
    {
        $code = mb_substr(trim($code), 0, 32);
        if ($code === '') {
            return null;
        }
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id FROM rooms WHERE school_id = :school_id AND code = :code LIMIT 1');
        $find->execute(['school_id' => $schoolId, 'code' => $code]);
        $id = (int) $find->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $pdo->prepare(
            'INSERT INTO rooms (school_id, building_id, code, room_type, capacity)
             VALUES (:school_id, NULL, :code, \'\', 0)'
        )->execute(['school_id' => $schoolId, 'code' => $code]);
        return (int) $pdo->lastInsertId();
    }

    private static function optionalCoordinates(string $lat, string $lng): ?array
    {
        $lat = trim($lat);
        $lng = trim($lng);
        if ($lat === '' && $lng === '') {
            return null;
        }
        if ($lat === '' || $lng === '') {
            throw new RuntimeException('ละติจูดและลองจิจูดต้องกรอกคู่กัน');
        }
        return self::coordinates($lat, $lng);
    }

    private static function coordinates(string $lat, string $lng): array
    {
        return [
            'lat' => self::coordinate($lat, -90, 90, 'ละติจูด'),
            'lng' => self::coordinate($lng, -180, 180, 'ลองจิจูด'),
        ];
    }

    private static function coordinate(string $value, float $min, float $max, string $label): string
    {
        $value = trim($value);
        if (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }
        if ($value === '' || !is_numeric($value)) {
            throw new RuntimeException('กรอก' . $label . 'ของอาคาร');
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            throw new RuntimeException($label . 'ต้องอยู่ระหว่าง ' . $min . ' ถึง ' . $max);
        }
        return number_format($number, 6, '.', '');
    }

    private static function teacherId(int $schoolId, int $teacherId): ?int
    {
        if ($teacherId <= 0) {
            return null;
        }
        $statement = Database::pdo()->prepare(
            'SELECT id FROM teachers WHERE school_id = :school_id AND id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'id' => $teacherId]);
        $id = (int) $statement->fetchColumn();
        return $id > 0 ? $id : null;
    }

    private static function buildingId(int $schoolId, int $buildingId): ?int
    {
        if ($buildingId <= 0) {
            return null;
        }
        $statement = Database::pdo()->prepare('SELECT id FROM buildings WHERE id = :id AND school_id = :school_id');
        $statement->execute(['id' => $buildingId, 'school_id' => $schoolId]);
        return (int) $statement->fetchColumn() > 0 ? $buildingId : null;
    }

    public static function policies(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM policies WHERE school_id = :school_id ORDER BY sort_order, id'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function mergeConsecutive(int $schoolId, int $groupId): void
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            'SELECT id, subject_id, day_index, start_period, length_periods, is_manual, warning, moved
             FROM timetable_entries
             WHERE school_id = :school_id AND group_id = :group_id
             ORDER BY day_index, subject_id, start_period, id'
        );
        $statement->execute(['school_id' => $schoolId, 'group_id' => $groupId]);
        $buckets = [];
        foreach ($statement->fetchAll() as $row) {
            $buckets[$row['day_index'] . ':' . $row['subject_id']][] = $row;
        }
        $update = $pdo->prepare(
            'UPDATE timetable_entries
             SET start_period = :start_period, length_periods = :length_periods, is_manual = :is_manual
             WHERE id = :id AND school_id = :school_id AND group_id = :group_id'
        );
        $delete = $pdo->prepare(
            'DELETE FROM timetable_entries WHERE id = :id AND school_id = :school_id AND group_id = :group_id'
        );
        foreach ($buckets as $rows) {
            $keep = null;
            foreach ($rows as $row) {
                $start = (int) $row['start_period'];
                $end = $start + (int) $row['length_periods'] - 1;
                if ($keep === null) {
                    $keep = [
                        'id' => (int) $row['id'],
                        'start' => $start,
                        'end' => $end,
                        'manual' => (int) $row['is_manual'] === 1,
                        'dirty' => false,
                    ];
                    continue;
                }
                if ($start > $keep['end'] + 1) {
                    if ($keep['dirty']) {
                        $update->execute([
                            'start_period' => $keep['start'],
                            'length_periods' => $keep['end'] - $keep['start'] + 1,
                            'is_manual' => $keep['manual'] ? 1 : 0,
                            'id' => $keep['id'],
                            'school_id' => $schoolId,
                            'group_id' => $groupId,
                        ]);
                    }
                    $keep = [
                        'id' => (int) $row['id'],
                        'start' => $start,
                        'end' => $end,
                        'manual' => (int) $row['is_manual'] === 1,
                        'dirty' => false,
                    ];
                    continue;
                }
                $keep['end'] = max($keep['end'], $end);
                $keep['manual'] = $keep['manual'] || (int) $row['is_manual'] === 1;
                $keep['dirty'] = true;
                $delete->execute(['id' => (int) $row['id'], 'school_id' => $schoolId, 'group_id' => $groupId]);
            }
            if ($keep !== null && $keep['dirty']) {
                $update->execute([
                    'start_period' => $keep['start'],
                    'length_periods' => $keep['end'] - $keep['start'] + 1,
                    'is_manual' => $keep['manual'] ? 1 : 0,
                    'id' => $keep['id'],
                    'school_id' => $schoolId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }

    public static function entries(int $schoolId, int $groupId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT e.*, s.demo_key, s.code, s.name, s.theory, s.practice, s.extra, s.teacher_id,
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

    public static function saveAiPermissions(int $schoolId, array $flags, int $userId): void
    {
        $current = self::ai($schoolId);
        if ($current === null) {
            insert_default_ai(Database::pdo(), $schoolId);
        }
        $statement = Database::pdo()->prepare(
            'UPDATE ai_settings SET
                allow_act = :allow_act,
                require_confirm = :require_confirm,
                suggest_contact = :suggest_contact,
                log_actions = :log_actions,
                updated_by = :updated_by,
                updated_at = NOW()
             WHERE school_id = :school_id'
        );
        $statement->execute([
            'allow_act' => $flags['allow_act'],
            'require_confirm' => $flags['require_confirm'],
            'suggest_contact' => $flags['suggest_contact'],
            'log_actions' => $flags['log_actions'],
            'updated_by' => $userId,
            'school_id' => $schoolId,
        ]);
    }

    public static function credentialsWithModels(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, label, provider, base_url, api_key_hint, last_test_at, last_test_status, last_test_ms
             FROM ai_credentials WHERE school_id = :school_id ORDER BY id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $rows = $statement->fetchAll();
        $models = Database::pdo()->prepare(
            'SELECT id, credential_id, model_name, enabled FROM ai_models WHERE school_id = :school_id ORDER BY model_name'
        );
        $models->execute(['school_id' => $schoolId]);
        $grouped = [];
        foreach ($models->fetchAll() as $model) {
            $grouped[(int) $model['credential_id']][] = $model;
        }
        foreach ($rows as &$row) {
            $row['models'] = $grouped[(int) $row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    public static function enabledModels(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT m.id, m.model_name, m.credential_id, c.label, c.provider, c.last_test_status
             FROM ai_models m
             JOIN ai_credentials c ON c.id = m.credential_id AND c.school_id = m.school_id
             WHERE m.school_id = :school_id AND m.enabled = 1
             ORDER BY c.label, m.model_name'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function resolveModel(int $schoolId, int $preferredId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT m.id, m.model_name, c.provider, c.base_url, c.api_key_encrypted, c.last_test_status, c.label
             FROM ai_models m
             JOIN ai_credentials c ON c.id = m.credential_id AND c.school_id = m.school_id
             WHERE m.school_id = :school_id AND m.enabled = 1
             ORDER BY m.id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            return null;
        }
        $settings = self::ai($schoolId);
        $defaultId = (int) ($settings['working_model_id'] ?? 0);
        $fallback = null;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($preferredId > 0 && $id === $preferredId) {
                return $row;
            }
            if ($fallback === null && $id === $defaultId) {
                $fallback = $row;
            }
        }
        return $fallback ?? $rows[0];
    }

    public static function addCredential(int $schoolId, array $fields, array $models, int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO ai_credentials (
                    school_id, label, provider, base_url, api_key_encrypted, api_key_hint,
                    last_test_at, last_test_status, last_test_ms, updated_by
                 ) VALUES (
                    :school_id, :label, :provider, :base_url, :api_key_encrypted, :api_key_hint,
                    NOW(), \'ok\', :last_test_ms, :updated_by
                 )'
            );
            $statement->execute([
                'school_id' => $schoolId,
                'label' => $fields['label'],
                'provider' => $fields['provider'],
                'base_url' => $fields['base_url'],
                'api_key_encrypted' => $fields['api_key_encrypted'],
                'api_key_hint' => $fields['api_key_hint'],
                'last_test_ms' => $fields['last_test_ms'],
                'updated_by' => $userId,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::insertModels($pdo, $schoolId, $id, $models, []);
            $pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function refreshModels(int $schoolId, int $credentialId, array $models, int $ms): void
    {
        $pdo = Database::pdo();
        $current = self::credentialKey($schoolId, $credentialId);
        if ($current === null) {
            throw new RuntimeException('ไม่พบชุด API Key ของสถานศึกษานี้');
        }
        $existing = $pdo->prepare(
            'SELECT model_name, enabled FROM ai_models WHERE school_id = :school_id AND credential_id = :credential_id'
        );
        $existing->execute(['school_id' => $schoolId, 'credential_id' => $credentialId]);
        $enabled = [];
        foreach ($existing->fetchAll() as $row) {
            if ((int) $row['enabled'] === 1) {
                $enabled[(string) $row['model_name']] = true;
            }
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ai_models WHERE school_id = :school_id AND credential_id = :credential_id')
                ->execute(['school_id' => $schoolId, 'credential_id' => $credentialId]);
            self::insertModels($pdo, $schoolId, $credentialId, $models, $enabled);
            $pdo->prepare(
                'UPDATE ai_credentials SET last_test_at = NOW(), last_test_status = \'ok\', last_test_ms = :ms WHERE id = :id AND school_id = :school_id'
            )->execute(['ms' => $ms, 'id' => $credentialId, 'school_id' => $schoolId]);
            self::repairWorkingModel($pdo, $schoolId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function markCredentialTest(int $schoolId, int $credentialId, string $status, ?int $ms): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE ai_credentials SET last_test_at = NOW(), last_test_status = :status, last_test_ms = :ms
             WHERE id = :id AND school_id = :school_id'
        );
        $statement->execute([
            'status' => $status,
            'ms' => $ms,
            'id' => $credentialId,
            'school_id' => $schoolId,
        ]);
    }

    public static function credentialKey(int $schoolId, int $credentialId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, label, provider, base_url, api_key_encrypted FROM ai_credentials WHERE id = :id AND school_id = :school_id'
        );
        $statement->execute(['id' => $credentialId, 'school_id' => $schoolId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public static function saveEnabledModels(int $schoolId, int $credentialId, array $enabledIds, int $defaultId): void
    {
        $pdo = Database::pdo();
        if (self::credentialKey($schoolId, $credentialId) === null) {
            throw new RuntimeException('ไม่พบชุด API Key ของสถานศึกษานี้');
        }
        $enabledIds = array_values(array_unique(array_filter(array_map('intval', $enabledIds))));
        if ($defaultId > 0 && !in_array($defaultId, $enabledIds, true)) {
            $enabledIds[] = $defaultId;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE ai_models SET enabled = 0 WHERE school_id = :school_id AND credential_id = :credential_id')
                ->execute(['school_id' => $schoolId, 'credential_id' => $credentialId]);
            if ($enabledIds !== []) {
                $marks = implode(',', array_fill(0, count($enabledIds), '?'));
                $statement = $pdo->prepare(
                    'UPDATE ai_models SET enabled = 1 WHERE school_id = ? AND credential_id = ? AND id IN (' . $marks . ')'
                );
                $statement->execute(array_merge([$schoolId, $credentialId], $enabledIds));
            }
            if ($defaultId > 0) {
                $check = $pdo->prepare(
                    'SELECT id FROM ai_models WHERE id = :id AND school_id = :school_id AND enabled = 1'
                );
                $check->execute(['id' => $defaultId, 'school_id' => $schoolId]);
                if ($check->fetchColumn()) {
                    $pdo->prepare('UPDATE ai_settings SET working_model_id = :id WHERE school_id = :school_id')
                        ->execute(['id' => $defaultId, 'school_id' => $schoolId]);
                }
            }
            self::repairWorkingModel($pdo, $schoolId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function deleteCredential(int $schoolId, int $credentialId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM ai_credentials WHERE id = :id AND school_id = :school_id')
            ->execute(['id' => $credentialId, 'school_id' => $schoolId]);
        self::repairWorkingModel($pdo, $schoolId);
    }

    private static function insertModels(PDO $pdo, int $schoolId, int $credentialId, array $models, array $enabled): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO ai_models (credential_id, school_id, model_name, enabled)
             VALUES (:credential_id, :school_id, :model_name, :enabled)'
        );
        foreach ($models as $name) {
            $statement->execute([
                'credential_id' => $credentialId,
                'school_id' => $schoolId,
                'model_name' => $name,
                'enabled' => isset($enabled[$name]) ? 1 : 0,
            ]);
        }
    }

    private static function repairWorkingModel(PDO $pdo, int $schoolId): void
    {
        $current = $pdo->prepare('SELECT working_model_id FROM ai_settings WHERE school_id = :school_id');
        $current->execute(['school_id' => $schoolId]);
        $workingId = (int) $current->fetchColumn();
        if ($workingId > 0) {
            $still = $pdo->prepare('SELECT id FROM ai_models WHERE id = :id AND school_id = :school_id AND enabled = 1');
            $still->execute(['id' => $workingId, 'school_id' => $schoolId]);
            if ($still->fetchColumn()) {
                return;
            }
        }
        $next = $pdo->prepare('SELECT id FROM ai_models WHERE school_id = :school_id AND enabled = 1 ORDER BY id LIMIT 1');
        $next->execute(['school_id' => $schoolId]);
        $id = $next->fetchColumn();
        $pdo->prepare('UPDATE ai_settings SET working_model_id = :id WHERE school_id = :school_id')
            ->execute(['id' => $id ? (int) $id : null, 'school_id' => $schoolId]);
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

    public static function teacherAccounts(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT teacher_id, username FROM users WHERE school_id = :school_id AND teacher_id IS NOT NULL ORDER BY id'
        );
        $statement->execute(['school_id' => $schoolId]);
        $accounts = [];
        foreach ($statement->fetchAll() as $row) {
            $teacherId = (int) $row['teacher_id'];
            if (!isset($accounts[$teacherId])) {
                $accounts[$teacherId] = (string) $row['username'];
            }
        }
        return $accounts;
    }

    public static function createTeacherUser(int $schoolId, int $teacherId, string $password, string $citizenId = ''): void
    {
        $teacher = self::teacher($schoolId, $teacherId);
        if ($teacher === null) {
            throw new RuntimeException('ไม่พบครูผู้สอน');
        }
        if (isset(self::teacherAccounts($schoolId)[$teacherId])) {
            throw new RuntimeException('ครูคนนี้มีบัญชีอยู่แล้ว');
        }
        $stored = preg_replace('/\D/', '', (string) ($teacher['rms_people_id'] ?? '')) ?? '';
        $typed = preg_replace('/\D/', '', $citizenId) ?? '';
        if (preg_match('/^\d{13}$/', $stored) === 1) {
            $username = $stored;
        } elseif (preg_match('/^\d{13}$/', $typed) === 1) {
            $username = $typed;
            try {
                Database::pdo()->prepare(
                    'UPDATE teachers SET rms_people_id = :people_id
                     WHERE id = :id AND school_id = :school_id AND (rms_people_id IS NULL OR rms_people_id = \'\')'
                )->execute([
                    'people_id' => $username,
                    'id' => $teacherId,
                    'school_id' => $schoolId,
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    throw new RuntimeException('มีเลขประจำตัวนี้ในสถานศึกษาแล้ว');
                }
                throw $exception;
            }
        } else {
            throw new RuntimeException('เลขประจำตัวประชาชนต้องเป็นตัวเลข 13 หลัก');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัว');
        }
        try {
            Auth::insert(
                Database::pdo(),
                $schoolId,
                $teacherId,
                $username,
                $password,
                (string) $teacher['name'],
                'teacher'
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('มีชื่อผู้ใช้นี้แล้ว');
            }
            throw $exception;
        }
    }
}
