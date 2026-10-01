<?php
declare(strict_types=1);

final class Rms
{
    private const DATASETS = [
        'people' => 'people',
        'terms' => 'dateedu',
        'holidays' => 'stopday',
        'groups' => 'std2018_studentgroup',
        'plans' => 'std2018_curi_plan',
        'students' => 'std2018_student',
        'schedules' => 'studing',
    ];

    public static function baseUrl(int $schoolId): string
    {
        $statement = Database::pdo()->prepare('SELECT base_url FROM rms_settings WHERE school_id = :school_id');
        $statement->execute(['school_id' => $schoolId]);
        return (string) ($statement->fetchColumn() ?: '');
    }

    public static function saveBaseUrl(int $schoolId, string $url): void
    {
        $url = rtrim(trim($url), '/');
        if (!preg_match('#^https?://#i', $url) || strlen($url) > 255 || preg_match('/\s/', $url)) {
            throw new RuntimeException('URL ของ RMS ต้องขึ้นต้นด้วย http:// หรือ https://');
        }
        $statement = Database::pdo()->prepare(
            'INSERT INTO rms_settings (school_id, base_url) VALUES (:school_id, :base_url)
             ON DUPLICATE KEY UPDATE base_url = VALUES(base_url)'
        );
        $statement->execute(['school_id' => $schoolId, 'base_url' => $url]);
    }

    public static function fetch(int $schoolId, string $dataset, array $params = []): array
    {
        if (!isset(self::DATASETS[$dataset])) {
            throw new RuntimeException('ชุดข้อมูลไม่ถูกต้อง');
        }
        $base = self::baseUrl($schoolId);
        if ($base === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า URL ของระบบ RMS สำหรับสถานศึกษานี้');
        }
        $query = 'data=' . rawurlencode(self::DATASETS[$dataset]);
        foreach ($params as $key => $value) {
            if (!in_array($key, ['count', 'limit', 'semes'], true)) {
                continue;
            }
            $query .= '&' . $key . '=' . rawurlencode((string) $value);
        }
        $url = $base . '/api_connection.php?app_name=nutty&' . $query;
        if (!function_exists('curl_init')) {
            throw new RuntimeException('เซิร์ฟเวอร์ไม่มีส่วนขยาย cURL');
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false) {
            throw new RuntimeException('เชื่อมต่อ RMS ไม่สำเร็จ: ' . $error);
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('ข้อมูลจาก RMS ไม่อยู่ในรูปแบบ JSON ที่ถูกต้อง');
        }
        return $data;
    }

    public static function countStudents(int $schoolId): int
    {
        $rows = self::fetch($schoolId, 'students', ['count' => 'yes']);
        $first = $rows[0] ?? [];
        return (int) ($first['c'] ?? $first['count'] ?? 0);
    }

    public static function sync(int $schoolId, string $dataset, int $offset, int $row): array
    {
        $row = max(1, min(1000, $row));
        $offset = max(0, $offset);
        return match ($dataset) {
            'people' => self::syncPeople($schoolId),
            'terms' => self::syncTerms($schoolId),
            'holidays' => self::syncHolidays($schoolId),
            'groups' => self::syncGroups($schoolId),
            'plans' => self::syncPlans($schoolId, $offset, $row),
            'students' => self::syncStudents($schoolId, $offset, $row),
            'schedules' => self::syncSchedules($schoolId, $offset, $row),
            default => throw new RuntimeException('ชุดข้อมูลไม่ถูกต้อง'),
        };
    }

    public static function setCurrentTerm(int $schoolId, int $termId): void
    {
        $pdo = Database::pdo();
        $owns = $pdo->prepare('SELECT id FROM terms WHERE id = :id AND school_id = :school_id');
        $owns->execute(['id' => $termId, 'school_id' => $schoolId]);
        if (!$owns->fetchColumn()) {
            throw new RuntimeException('ไม่พบภาคเรียนของสถานศึกษานี้');
        }
        $pdo->prepare('UPDATE terms SET is_current = 0 WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        $pdo->prepare('UPDATE terms SET is_current = 1 WHERE id = :id AND school_id = :school_id')
            ->execute(['id' => $termId, 'school_id' => $schoolId]);
    }

    public static function terms(int $schoolId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, label, rms_key, is_current, start_date, end_date FROM terms WHERE school_id = :school_id ORDER BY id DESC'
        );
        $statement->execute(['school_id' => $schoolId]);
        return $statement->fetchAll();
    }

    public static function counts(int $schoolId): array
    {
        $pdo = Database::pdo();
        $one = static function (string $sql) use ($pdo, $schoolId): int {
            $statement = $pdo->prepare($sql);
            $statement->execute(['school_id' => $schoolId]);
            return (int) $statement->fetchColumn();
        };
        return [
            'teachers' => $one('SELECT COUNT(*) FROM teachers WHERE school_id = :school_id AND rms_people_id IS NOT NULL AND is_active = 1'),
            'terms' => $one('SELECT COUNT(*) FROM terms WHERE school_id = :school_id AND rms_key IS NOT NULL'),
            'holidays' => $one('SELECT COUNT(*) FROM holidays WHERE school_id = :school_id'),
            'groups' => $one('SELECT COUNT(*) FROM student_groups WHERE school_id = :school_id AND rms_group_code IS NOT NULL'),
            'plans' => $one('SELECT COUNT(*) FROM study_plans WHERE school_id = :school_id AND rms_key IS NOT NULL'),
            'students' => $one('SELECT COUNT(*) FROM students WHERE school_id = :school_id'),
            'schedules' => $one('SELECT COUNT(*) FROM rms_schedules WHERE school_id = :school_id'),
        ];
    }

    public static function browse(int $schoolId, string $resource, string $query, int $page): array
    {
        $defs = [
            'students' => [
                'select' => 'student_code, firstname, surname, group_name, grade_name, major_name, status_name, gpax',
                'from' => 'students',
                'search' => ['firstname', 'surname', 'student_code', 'idcard', 'group_name', 'major_name'],
                'order' => 'group_name, surname, firstname',
            ],
            'groups' => [
                'select' => 'g.name AS name, g.level AS level, g.rms_group_code AS code, g.student_count AS students, t.label AS term_label',
                'from' => 'student_groups g JOIN terms t ON t.id = g.term_id',
                'where' => 'g.school_id = :school_id AND g.rms_group_code IS NOT NULL',
                'search' => ['g.name', 'g.rms_group_code', 'g.level'],
                'order' => 't.id DESC, g.name',
            ],
            'plans' => [
                'select' => 'p.name AS plan_name, t.label AS term_label, s.code AS code, s.name AS subject_name, s.theory AS theory, s.practice AS practice, s.extra AS extra',
                'from' => 'study_plans p JOIN terms t ON t.id = p.term_id LEFT JOIN subjects s ON s.plan_id = p.id',
                'where' => 'p.school_id = :school_id AND p.rms_key IS NOT NULL',
                'search' => ['p.name', 's.code', 's.name', 't.label'],
                'order' => 't.id DESC, p.name, s.code',
            ],
            'holidays' => [
                'select' => 'h.name AS name, h.holiday_date AS holiday_date, t.label AS term_label',
                'from' => 'holidays h JOIN terms t ON t.id = h.term_id',
                'where' => 'h.school_id = :school_id',
                'search' => ['h.name', 't.label'],
                'order' => 'h.holiday_date DESC',
            ],
            'schedules' => [
                'select' => 'subject_id, subject_name, student_group_id, teacher_name, day_name, time_range, periods, room, building, semes',
                'from' => 'rms_schedules',
                'where' => "school_id = :school_id AND student_group_id <> '00000000'",
                'search' => ['subject_name', 'subject_id', 'teacher_name', 'student_group_id', 'room', 'day_name'],
                'order' => 'semes, student_group_id, day_name, time_range',
            ],
        ];
        if (!isset($defs[$resource])) {
            $resource = 'students';
        }
        $def = $defs[$resource];
        $where = $def['where'] ?? 'school_id = :school_id';
        $params = ['school_id' => $schoolId];
        if ($query !== '') {
            $likes = [];
            foreach ($def['search'] as $index => $column) {
                $key = 'q' . $index;
                $likes[] = $column . ' LIKE :' . $key;
                $params[$key] = '%' . $query . '%';
            }
            $where .= ' AND (' . implode(' OR ', $likes) . ')';
        }
        $page = max(1, $page);
        $per = 20;
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . $def['from'] . ' WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $params['limit'] = $per;
        $params['offset'] = ($page - 1) * $per;
        $rows = $pdo->prepare(
            'SELECT ' . $def['select'] . ' FROM ' . $def['from'] . ' WHERE ' . $where . ' ORDER BY ' . $def['order'] . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $type = in_array($key, ['limit', 'offset', 'school_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $rows->bindValue(':' . $key, $value, $type);
        }
        $rows->execute();
        return [
            'resource' => $resource,
            'rows' => $rows->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
        ];
    }

    public static function dayIndex(string $value): ?int
    {
        $value = mb_strtolower(trim($value));
        $map = [
            'จันทร์' => 0, 'จัน' => 0, 'จ.' => 0, 'จ' => 0, 'mon' => 0, 'monday' => 0,
            'อังคาร' => 1, 'อ.' => 1, 'อ' => 1, 'tue' => 1, 'tuesday' => 1,
            'พุธ' => 2, 'พ.' => 2, 'พ' => 2, 'wed' => 2, 'wednesday' => 2,
            'พฤหัสบดี' => 3, 'พฤหัส' => 3, 'พฤ.' => 3, 'thu' => 3, 'thursday' => 3,
            'ศุกร์' => 4, 'ศ.' => 4, 'ศ' => 4, 'fri' => 4, 'friday' => 4,
        ];
        if (isset($map[$value])) {
            return $map[$value];
        }
        if (preg_match('/^[1-5]$/', $value)) {
            return (int) $value - 1;
        }
        return null;
    }

    public static function periodSpan(string $range, ?int $periods): ?array
    {
        $range = trim($range);
        $start = null;
        $length = null;
        if (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–ถึงto]+\s*(\d{1,2})[:.](\d{2})/iu', $range, $match)) {
            $start = (int) $match[1] - 7;
            $end = (int) $match[3] - 7;
            if ((int) $match[4] > 0) {
                $end++;
            }
            $length = max(1, $end - $start);
        } elseif (preg_match('/(\d{1,2})\s*[-–]\s*(\d{1,2})/u', $range, $match)) {
            $start = (int) $match[1];
            $length = (int) $match[2] - $start + 1;
        } elseif (preg_match('/^\d{1,2}$/', $range)) {
            $start = (int) $range;
            $length = 1;
        }
        if ($periods !== null && $periods > 0) {
            $length = $periods;
        }
        if ($start === null || $length === null || $start < 1 || $start > 9 || $length < 1) {
            return null;
        }
        if ($start + $length > 10) {
            $length = 10 - $start;
        }
        return [$start, $length];
    }

    private static function syncPeople(int $schoolId): array
    {
        $rows = self::fetch($schoolId, 'people');
        $pdo = Database::pdo();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seen = [];
        $findId = $pdo->prepare('SELECT id FROM teachers WHERE school_id = :school_id AND rms_people_id = :people_id LIMIT 1');
        $findName = $pdo->prepare('SELECT id FROM teachers WHERE school_id = :school_id AND name = :name AND rms_people_id IS NULL LIMIT 1');
        $update = $pdo->prepare('UPDATE teachers SET name = :name, is_active = 1, rms_people_id = :people_id WHERE id = :id AND school_id = :school_id');
        $insert = $pdo->prepare(
            'INSERT INTO teachers (school_id, name, dept, degree, max_hours, rms_people_id, is_active)
             VALUES (:school_id, :name, \'\', \'\', 18, :people_id, 1)'
        );
        foreach ($rows as $row) {
            if (!is_array($row) || trim((string) ($row['people_exit'] ?? '')) !== '0') {
                $skipped++;
                continue;
            }
            $peopleId = trim((string) ($row['people_id'] ?? ''));
            $name = trim(trim((string) ($row['people_name'] ?? '')) . ' ' . trim((string) ($row['people_surname'] ?? '')));
            if ($peopleId === '' || $name === '') {
                $skipped++;
                continue;
            }
            $seen[$peopleId] = true;
            $findId->execute(['school_id' => $schoolId, 'people_id' => $peopleId]);
            $id = (int) $findId->fetchColumn();
            if ($id <= 0) {
                $findName->execute(['school_id' => $schoolId, 'name' => $name]);
                $id = (int) $findName->fetchColumn();
            }
            if ($id > 0) {
                $update->execute(['name' => $name, 'people_id' => $peopleId, 'id' => $id, 'school_id' => $schoolId]);
                $updated++;
            } else {
                $insert->execute(['school_id' => $schoolId, 'name' => $name, 'people_id' => $peopleId]);
                $created++;
            }
        }
        $deactivated = 0;
        if ($seen !== []) {
            $marks = implode(',', array_fill(0, count($seen), '?'));
            $statement = $pdo->prepare(
                'UPDATE teachers SET is_active = 0 WHERE school_id = ? AND rms_people_id IS NOT NULL AND rms_people_id NOT IN (' . $marks . ')'
            );
            $statement->execute(array_merge([$schoolId], array_keys($seen)));
            $deactivated = $statement->rowCount();
        }
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'deactivated' => $deactivated, 'fetched' => count($rows)];
    }

    private static function syncTerms(int $schoolId): array
    {
        $rows = self::fetch($schoolId, 'terms');
        $pdo = Database::pdo();
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $find = $pdo->prepare('SELECT id FROM terms WHERE school_id = :school_id AND rms_key = :rms_key LIMIT 1');
        $update = $pdo->prepare(
            'UPDATE terms SET label = :label, start_date = :start_date, end_date = :end_date WHERE id = :id AND school_id = :school_id'
        );
        $insert = $pdo->prepare(
            'INSERT INTO terms (school_id, label, is_current, rms_key, start_date, end_date)
             VALUES (:school_id, :label, 0, :rms_key, :start_date, :end_date)'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $key = trim((string) ($row['dateedu_eduyear'] ?? ''));
            $parts = explode('/', $key);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                $skipped++;
                continue;
            }
            $label = 'ภาคเรียนที่ ' . trim($parts[0]) . '/' . trim($parts[1]);
            $start = self::dateOrNull($row['dateedu_start'] ?? null);
            $end = self::dateOrNull($row['dateedu_end'] ?? null);
            $find->execute(['school_id' => $schoolId, 'rms_key' => $key]);
            $id = (int) $find->fetchColumn();
            if ($id > 0) {
                $update->execute([
                    'label' => $label,
                    'start_date' => $start,
                    'end_date' => $end,
                    'id' => $id,
                    'school_id' => $schoolId,
                ]);
                $updated++;
            } else {
                $insert->execute([
                    'school_id' => $schoolId,
                    'label' => $label,
                    'rms_key' => $key,
                    'start_date' => $start,
                    'end_date' => $end,
                ]);
                $added++;
            }
        }
        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncHolidays(int $schoolId): array
    {
        $rows = self::fetch($schoolId, 'holidays');
        $map = self::termMap($schoolId);
        $pdo = Database::pdo();
        $added = 0;
        $duplicated = 0;
        $skipped = 0;
        $exists = $pdo->prepare(
            'SELECT id FROM holidays WHERE school_id = :school_id AND term_id = :term_id AND holiday_date = :holiday_date'
        );
        $insert = $pdo->prepare(
            'INSERT INTO holidays (school_id, term_id, holiday_date, name) VALUES (:school_id, :term_id, :holiday_date, :name)'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $key = trim((string) ($row['stopday_eduyear'] ?? ''));
            $date = self::dateOrNull($row['stopday_date'] ?? null);
            $name = trim((string) ($row['stopday_name'] ?? ''));
            $termId = $map[$key] ?? 0;
            if ($termId <= 0 || $date === null || $name === '') {
                $skipped++;
                continue;
            }
            $exists->execute(['school_id' => $schoolId, 'term_id' => $termId, 'holiday_date' => $date]);
            if ($exists->fetchColumn()) {
                $duplicated++;
                continue;
            }
            $insert->execute(['school_id' => $schoolId, 'term_id' => $termId, 'holiday_date' => $date, 'name' => $name]);
            $added++;
        }
        return ['added' => $added, 'duplicated' => $duplicated, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncGroups(int $schoolId): array
    {
        $rows = self::fetch($schoolId, 'groups');
        $pdo = Database::pdo();
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $find = $pdo->prepare(
            'SELECT id FROM student_groups WHERE school_id = :school_id AND term_id = :term_id AND rms_group_code = :code LIMIT 1'
        );
        $update = $pdo->prepare(
            'UPDATE student_groups SET name = :name, level = :level, advisor_id = :advisor_id
             WHERE id = :id AND school_id = :school_id'
        );
        $insert = $pdo->prepare(
            'INSERT INTO student_groups (school_id, term_id, plan_id, name, level, student_count, advisor_id, rms_group_code)
             VALUES (:school_id, :term_id, NULL, :name, :level, 0, :advisor_id, :code)'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $year = trim((string) ($row['academicYear'] ?? ''));
            $semester = trim((string) ($row['semester'] ?? ''));
            $code = trim((string) ($row['groupCode'] ?? ''));
            if ($year === '' || $semester === '' || $code === '') {
                $skipped++;
                continue;
            }
            $termId = self::ensureTerm($schoolId, $semester . '/' . $year);
            $name = trim((string) ($row['groupName'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['groupAbbr'] ?? ''));
            }
            if ($name === '') {
                $name = $code;
            }
            $level = self::level((string) ($row['grade'] ?? ''), $name);
            $advisor = self::teacherId($schoolId, (string) ($row['teacherIdcard'] ?? ''), trim(
                trim((string) ($row['teacherFirstname'] ?? '')) . ' ' . trim((string) ($row['teacherLastname'] ?? ''))
            ));
            $find->execute(['school_id' => $schoolId, 'term_id' => $termId, 'code' => $code]);
            $id = (int) $find->fetchColumn();
            if ($id > 0) {
                $update->execute([
                    'name' => $name,
                    'level' => $level,
                    'advisor_id' => $advisor,
                    'id' => $id,
                    'school_id' => $schoolId,
                ]);
                $updated++;
            } else {
                $insert->execute([
                    'school_id' => $schoolId,
                    'term_id' => $termId,
                    'name' => $name,
                    'level' => $level,
                    'advisor_id' => $advisor,
                    'code' => $code,
                ]);
                $added++;
            }
        }
        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncPlans(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'plans', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        $plansCreated = 0;
        $subjectsCreated = 0;
        $subjectsUpdated = 0;
        $skipped = 0;
        $findPlan = $pdo->prepare(
            'SELECT id FROM study_plans WHERE school_id = :school_id AND term_id = :term_id AND rms_key = :rms_key LIMIT 1'
        );
        $insertPlan = $pdo->prepare(
            'INSERT INTO study_plans (school_id, term_id, name, credits, rms_key) VALUES (:school_id, :term_id, :name, 0, :rms_key)'
        );
        $renamePlan = $pdo->prepare(
            'UPDATE study_plans SET name = :name WHERE id = :id AND school_id = :school_id AND name <> :name_same'
        );
        $findSubject = $pdo->prepare(
            'SELECT id FROM subjects WHERE school_id = :school_id AND plan_id = :plan_id AND code = :code LIMIT 1'
        );
        $updateSubject = $pdo->prepare(
            'UPDATE subjects SET name = :name, theory = :theory, practice = :practice, extra = :extra
             WHERE id = :id AND school_id = :school_id'
        );
        $insertSubject = $pdo->prepare(
            'INSERT INTO subjects (school_id, plan_id, code, name, theory, practice, extra, sort_order)
             VALUES (:school_id, :plan_id, :code, :name, :theory, :practice, :extra, :sort_order)'
        );
        $link = $pdo->prepare(
            'UPDATE student_groups SET plan_id = :plan_id
             WHERE school_id = :school_id AND term_id = :term_id AND rms_group_code = :code'
        );
        $groupName = $pdo->prepare(
            'SELECT name, level FROM student_groups WHERE school_id = :school_id AND term_id = :term_id AND rms_group_code = :code LIMIT 1'
        );
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $year = self::field($row, ['academicYear', 'eduYear', 'edu_year', 'year', 'aca_year']);
            $semester = self::field($row, ['semester', 'semes', 'term', 'edu_term']);
            $combined = self::field($row, ['dateedu_eduyear', 'eduyear', 'eduYear']);
            if ($combined !== '' && str_contains($combined, '/')) {
                $termKey = $combined;
            } elseif ($semester !== '' && $year !== '') {
                $termKey = $semester . '/' . $year;
            } else {
                $skipped++;
                continue;
            }
            $code = self::field($row, ['subjectCode', 'subject_code', 'real_subject_id', 'subject_id', 'subjCode', 'curiCode', 'courseCode', 'รหัสวิชา']);
            $name = self::field($row, ['subjectName', 'subject_name', 'subjName', 'curiName', 'courseName', 'ชื่อวิชา']);
            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }
            $code = mb_substr($code, 0, 32);
            $name = mb_substr($name, 0, 255);
            [$theory, $practice, $extra] = self::subjectHours($row);
            $groupCode = self::field($row, ['groupCode', 'group_code', 'student_group_id', 'studentGroupId', 'groupId', 'รหัสกลุ่ม']);
            $planCode = self::field($row, ['planCode', 'plan_code', 'curiPlanCode', 'curriculumCode', 'รหัสแผน']);
            $planName = self::field($row, ['planName', 'plan_name', 'curiPlanName', 'curriculumName', 'majorName', 'majorNameTh', 'ชื่อแผน']);
            $termId = self::ensureTerm($schoolId, $termKey);
            if ($groupCode !== '') {
                $rmsKey = mb_substr('g:' . $groupCode, 0, 80);
                $groupName->execute(['school_id' => $schoolId, 'term_id' => $termId, 'code' => $groupCode]);
                $group = $groupName->fetch();
                if ($planName === '' && $group) {
                    $planName = trim((string) $group['level'] . ' ' . (string) $group['name']);
                }
                if ($planName === '') {
                    $planName = 'แผน ' . $groupCode;
                }
            } else {
                $rmsKey = mb_substr('p:' . ($planCode !== '' ? $planCode : $planName), 0, 80);
                if ($planName === '') {
                    $planName = $planCode !== '' ? 'แผน ' . $planCode : 'แผนจาก RMS ' . $termKey;
                }
            }
            $planName = mb_substr($planName, 0, 255);
            $findPlan->execute(['school_id' => $schoolId, 'term_id' => $termId, 'rms_key' => $rmsKey]);
            $planId = (int) $findPlan->fetchColumn();
            if ($planId <= 0) {
                $insertPlan->execute([
                    'school_id' => $schoolId,
                    'term_id' => $termId,
                    'name' => $planName,
                    'rms_key' => $rmsKey,
                ]);
                $planId = (int) $pdo->lastInsertId();
                $plansCreated++;
            } else {
                $renamePlan->execute([
                    'name' => $planName,
                    'id' => $planId,
                    'school_id' => $schoolId,
                    'name_same' => $planName,
                ]);
            }
            $findSubject->execute(['school_id' => $schoolId, 'plan_id' => $planId, 'code' => $code]);
            $subjectId = (int) $findSubject->fetchColumn();
            if ($subjectId > 0) {
                $updateSubject->execute([
                    'name' => $name,
                    'theory' => $theory,
                    'practice' => $practice,
                    'extra' => $extra,
                    'id' => $subjectId,
                    'school_id' => $schoolId,
                ]);
                $subjectsUpdated++;
            } else {
                $insertSubject->execute([
                    'school_id' => $schoolId,
                    'plan_id' => $planId,
                    'code' => $code,
                    'name' => $name,
                    'theory' => $theory,
                    'practice' => $practice,
                    'extra' => $extra,
                    'sort_order' => $offset + $index,
                ]);
                $subjectsCreated++;
            }
            if ($groupCode !== '') {
                $link->execute([
                    'plan_id' => $planId,
                    'school_id' => $schoolId,
                    'term_id' => $termId,
                    'code' => $groupCode,
                ]);
            }
        }
        $pdo->prepare(
            'UPDATE study_plans p
             SET credits = (
                SELECT COALESCE(SUM(s.theory + s.practice), 0) FROM subjects s WHERE s.plan_id = p.id
             )
             WHERE p.school_id = :school_id AND p.rms_key IS NOT NULL'
        )->execute(['school_id' => $schoolId]);
        return [
            'added' => $plansCreated,
            'updated' => $subjectsUpdated,
            'inserted' => $subjectsCreated,
            'skipped' => $skipped,
            'fetched' => count($rows),
        ];
    }

    private static function syncStudents(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'students', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $find = $pdo->prepare('SELECT id FROM students WHERE school_id = :school_id AND student_id = :student_id LIMIT 1');
        $update = $pdo->prepare(
            'UPDATE students SET student_code = :student_code, idcard = :idcard, firstname = :firstname, surname = :surname,
                gender = :gender, group_code = :group_code, group_name = :group_name, group_abbr = :group_abbr,
                grade_name = :grade_name, major_name = :major_name, status_code = :status_code, status_name = :status_name,
                entrance_year = :entrance_year, entrance_semester = :entrance_semester, email = :email, tel = :tel, gpax = :gpax
             WHERE id = :id AND school_id = :school_id'
        );
        $insert = $pdo->prepare(
            'INSERT INTO students (
                school_id, student_id, student_code, idcard, firstname, surname, gender, group_code, group_name, group_abbr,
                grade_name, major_name, status_code, status_name, entrance_year, entrance_semester, email, tel, gpax
             ) VALUES (
                :school_id, :student_id, :student_code, :idcard, :firstname, :surname, :gender, :group_code, :group_name, :group_abbr,
                :grade_name, :major_name, :status_code, :status_name, :entrance_year, :entrance_semester, :email, :tel, :gpax
             )'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $studentId = trim((string) ($row['studentID'] ?? ''));
            if ($studentId === '') {
                $skipped++;
                continue;
            }
            $gpax = trim((string) ($row['gpax'] ?? ''));
            $fields = [
                'student_code' => self::textOrNull($row['studentCode'] ?? null),
                'idcard' => self::textOrNull($row['idcard'] ?? null),
                'firstname' => self::textOrNull($row['firstname'] ?? null),
                'surname' => self::textOrNull($row['surname'] ?? null),
                'gender' => self::textOrNull($row['gender'] ?? null),
                'group_code' => self::textOrNull($row['groupCode'] ?? null),
                'group_name' => self::textOrNull($row['groupName'] ?? null),
                'group_abbr' => self::textOrNull($row['groupAbbr'] ?? null),
                'grade_name' => self::textOrNull($row['gradeNameTh'] ?? null),
                'major_name' => self::textOrNull($row['majorNameTh'] ?? null),
                'status_code' => self::textOrNull($row['studentStatusCode'] ?? null),
                'status_name' => self::textOrNull($row['studentStatusName'] ?? null),
                'entrance_year' => self::intOrNull($row['entranceYear'] ?? null),
                'entrance_semester' => self::intOrNull($row['entranceSemester'] ?? null),
                'email' => self::textOrNull($row['email'] ?? null),
                'tel' => self::textOrNull($row['tel'] ?? null),
                'gpax' => is_numeric($gpax) ? $gpax : null,
            ];
            $find->execute(['school_id' => $schoolId, 'student_id' => $studentId]);
            $id = (int) $find->fetchColumn();
            if ($id > 0) {
                $update->execute($fields + ['id' => $id, 'school_id' => $schoolId]);
                $updated++;
            } else {
                $insert->execute($fields + ['school_id' => $schoolId, 'student_id' => $studentId]);
                $added++;
            }
        }
        self::recountGroups($schoolId);
        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncSchedules(int $schoolId, int $offset, int $row): array
    {
        $term = self::currentTerm($schoolId);
        if ($term === null || empty($term['rms_key'])) {
            throw new RuntimeException('ยังไม่ได้ตั้งภาคเรียนปัจจุบันที่มีรหัสจาก RMS');
        }
        $semes = (string) $term['rms_key'];
        $rows = self::fetch($schoolId, 'schedules', ['semes' => $semes, 'limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        if ($offset === 0) {
            $pdo->prepare('DELETE FROM rms_schedules WHERE school_id = :school_id AND semes = :semes')
                ->execute(['school_id' => $schoolId, 'semes' => $semes]);
            $pdo->prepare(
                'DELETE e FROM timetable_entries e
                 JOIN student_groups g ON g.id = e.group_id
                 WHERE e.school_id = :school_id AND e.source = \'rms\' AND g.term_id = :term_id'
            )->execute(['school_id' => $schoolId, 'term_id' => (int) $term['id']]);
            $planId = self::ensurePlan($schoolId, (int) $term['id'], 'แผนจาก RMS ' . $semes);
            $pdo->prepare('UPDATE subjects SET theory = 0, practice = 0 WHERE school_id = :school_id AND plan_id = :plan_id')
                ->execute(['school_id' => $schoolId, 'plan_id' => $planId]);
        }
        $inserted = 0;
        $placed = 0;
        $skipped = 0;
        $insert = $pdo->prepare(
            'INSERT INTO rms_schedules (
                school_id, semes, subject_id, subject_name, real_subject_id, student_group_id, teacher_id, teacher_name,
                day_name, time_range, periods, room, building, timetable_id, timetable_sub_id
             ) VALUES (
                :school_id, :semes, :subject_id, :subject_name, :real_subject_id, :student_group_id, :teacher_id, :teacher_name,
                :day_name, :time_range, :periods, :room, :building, :timetable_id, :timetable_sub_id
             )'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $groupCode = trim((string) ($row['student_group_id'] ?? ''));
            $periods = self::intOrNull($row['dpr4'] ?? null);
            $insert->execute([
                'school_id' => $schoolId,
                'semes' => $semes,
                'subject_id' => self::textOrNull($row['subject_id'] ?? null),
                'subject_name' => self::textOrNull($row['subject_name'] ?? null),
                'real_subject_id' => self::textOrNull($row['real_subject_id'] ?? null),
                'student_group_id' => $groupCode !== '' ? $groupCode : null,
                'teacher_id' => self::textOrNull($row['teacher_id'] ?? null),
                'teacher_name' => self::textOrNull($row['teacher_name'] ?? null),
                'day_name' => self::textOrNull($row['dpr2'] ?? null),
                'time_range' => self::textOrNull($row['dpr3'] ?? null),
                'periods' => $periods,
                'room' => self::textOrNull($row['roomName'] ?? null),
                'building' => self::textOrNull($row['ucode'] ?? null),
                'timetable_id' => self::textOrNull($row['timeTableID'] ?? null),
                'timetable_sub_id' => self::textOrNull($row['timeTableSubID'] ?? null),
            ]);
            $inserted++;
            if ($groupCode === '' || $groupCode === '00000000') {
                $skipped++;
                continue;
            }
            if (self::placeSchedule($schoolId, $term, $row, $periods)) {
                $placed++;
            } else {
                $skipped++;
            }
        }
        return ['semes' => $semes, 'inserted' => $inserted, 'placed' => $placed, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function placeSchedule(int $schoolId, array $term, array $row, ?int $periods): bool
    {
        $day = self::dayIndex((string) ($row['dpr2'] ?? ''));
        $span = self::periodSpan((string) ($row['dpr3'] ?? ''), $periods);
        if ($day === null || $span === null) {
            return false;
        }
        $groupCode = trim((string) ($row['student_group_id'] ?? ''));
        $pdo = Database::pdo();
        $group = $pdo->prepare(
            'SELECT id, level, plan_id FROM student_groups WHERE school_id = :school_id AND term_id = :term_id AND rms_group_code = :code LIMIT 1'
        );
        $group->execute(['school_id' => $schoolId, 'term_id' => (int) $term['id'], 'code' => $groupCode]);
        $groupRow = $group->fetch();
        if (!$groupRow) {
            return false;
        }
        $code = trim((string) ($row['real_subject_id'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($row['subject_id'] ?? ''));
        }
        $name = trim((string) ($row['subject_name'] ?? ''));
        if ($code === '' || $name === '') {
            return false;
        }
        $code = mb_substr($code, 0, 32);
        $groupPlanId = (int) ($groupRow['plan_id'] ?? 0);
        $keepCurriculumHours = $groupPlanId > 0;
        $planId = $keepCurriculumHours
            ? $groupPlanId
            : self::ensurePlan($schoolId, (int) $term['id'], 'แผนจาก RMS ' . $term['rms_key']);
        $teacherId = self::teacherId($schoolId, (string) ($row['teacher_id'] ?? ''), (string) ($row['teacher_name'] ?? ''));
        $roomId = self::ensureRoom($schoolId, (string) ($row['roomName'] ?? ''), (string) ($row['ucode'] ?? ''));
        $subject = $pdo->prepare('SELECT id, theory FROM subjects WHERE school_id = :school_id AND plan_id = :plan_id AND code = :code LIMIT 1');
        $subject->execute(['school_id' => $schoolId, 'plan_id' => $planId, 'code' => $code]);
        $subjectRow = $subject->fetch();
        $exists = $pdo->prepare(
            'SELECT id FROM timetable_entries WHERE school_id = :school_id AND group_id = :group_id AND day_index = :day_index AND start_period = :start_period LIMIT 1'
        );
        $exists->execute([
            'school_id' => $schoolId,
            'group_id' => (int) $groupRow['id'],
            'day_index' => $day,
            'start_period' => $span[0],
        ]);
        if ($exists->fetchColumn()) {
            return true;
        }
        if ($subjectRow) {
            $subjectId = (int) $subjectRow['id'];
            if ($keepCurriculumHours) {
                $pdo->prepare(
                    'UPDATE subjects SET name = :name, teacher_id = COALESCE(teacher_id, :teacher_id), room_id = COALESCE(room_id, :room_id)
                     WHERE id = :id AND school_id = :school_id'
                )->execute([
                    'name' => $name,
                    'teacher_id' => $teacherId,
                    'room_id' => $roomId,
                    'id' => $subjectId,
                    'school_id' => $schoolId,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE subjects SET name = :name, theory = theory + :hours, teacher_id = COALESCE(teacher_id, :teacher_id), room_id = COALESCE(room_id, :room_id)
                     WHERE id = :id AND school_id = :school_id'
                )->execute([
                    'name' => $name,
                    'hours' => $span[1],
                    'teacher_id' => $teacherId,
                    'room_id' => $roomId,
                    'id' => $subjectId,
                    'school_id' => $schoolId,
                ]);
            }
        } else {
            $pdo->prepare(
                'INSERT INTO subjects (school_id, plan_id, code, name, theory, practice, extra, sort_order, teacher_id, room_id)
                 VALUES (:school_id, :plan_id, :code, :name, :theory, 0, 0, 0, :teacher_id, :room_id)'
            )->execute([
                'school_id' => $schoolId,
                'plan_id' => $planId,
                'code' => $code,
                'name' => $name,
                'theory' => $span[1],
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
            ]);
            $subjectId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('UPDATE student_groups SET plan_id = :plan_id WHERE id = :id AND school_id = :school_id AND plan_id IS NULL')
            ->execute(['plan_id' => $planId, 'id' => (int) $groupRow['id'], 'school_id' => $schoolId]);
        $pdo->prepare(
            'INSERT INTO timetable_entries (school_id, group_id, subject_id, day_index, start_period, length_periods, is_manual, source)
             VALUES (:school_id, :group_id, :subject_id, :day_index, :start_period, :length_periods, 1, \'rms\')'
        )->execute([
            'school_id' => $schoolId,
            'group_id' => (int) $groupRow['id'],
            'subject_id' => $subjectId,
            'day_index' => $day,
            'start_period' => $span[0],
            'length_periods' => $span[1],
        ]);
        return true;
    }

    private static function ensurePlan(int $schoolId, int $termId, string $name): int
    {
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id FROM study_plans WHERE school_id = :school_id AND term_id = :term_id AND name = :name LIMIT 1');
        $find->execute(['school_id' => $schoolId, 'term_id' => $termId, 'name' => $name]);
        $id = (int) $find->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $pdo->prepare('INSERT INTO study_plans (school_id, term_id, name, credits) VALUES (:school_id, :term_id, :name, 0)')
            ->execute(['school_id' => $schoolId, 'term_id' => $termId, 'name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    private static function ensureRoom(int $schoolId, string $room, string $building): ?int
    {
        $room = trim($room);
        if ($room === '') {
            return null;
        }
        $code = mb_substr($room, 0, 32);
        $buildingId = null;
        $building = trim($building);
        $pdo = Database::pdo();
        if ($building !== '') {
            $findBuilding = $pdo->prepare('SELECT id FROM buildings WHERE school_id = :school_id AND name = :name LIMIT 1');
            $findBuilding->execute(['school_id' => $schoolId, 'name' => $building]);
            $buildingId = (int) $findBuilding->fetchColumn();
            if ($buildingId <= 0) {
                $pdo->prepare(
                    'INSERT INTO buildings (school_id, name, short_name) VALUES (:school_id, :name, :short_name)'
                )->execute([
                    'school_id' => $schoolId,
                    'name' => $building,
                    'short_name' => mb_substr($building, 0, 64),
                ]);
                $buildingId = (int) $pdo->lastInsertId();
            }
        }
        $findRoom = $pdo->prepare('SELECT id FROM rooms WHERE school_id = :school_id AND code = :code LIMIT 1');
        $findRoom->execute(['school_id' => $schoolId, 'code' => $code]);
        $roomId = (int) $findRoom->fetchColumn();
        if ($roomId > 0) {
            if ($buildingId) {
                $pdo->prepare('UPDATE rooms SET building_id = COALESCE(building_id, :building_id) WHERE id = :id AND school_id = :school_id')
                    ->execute(['building_id' => $buildingId, 'id' => $roomId, 'school_id' => $schoolId]);
            }
            return $roomId;
        }
        $pdo->prepare('INSERT INTO rooms (school_id, building_id, code, room_type, capacity) VALUES (:school_id, :building_id, :code, \'\', 0)')
            ->execute(['school_id' => $schoolId, 'building_id' => $buildingId, 'code' => $code]);
        return (int) $pdo->lastInsertId();
    }

    private static function teacherId(int $schoolId, string $peopleId, string $name): ?int
    {
        $pdo = Database::pdo();
        $peopleId = trim($peopleId);
        if ($peopleId !== '') {
            $statement = $pdo->prepare('SELECT id FROM teachers WHERE school_id = :school_id AND rms_people_id = :people_id LIMIT 1');
            $statement->execute(['school_id' => $schoolId, 'people_id' => $peopleId]);
            $id = (int) $statement->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $statement = $pdo->prepare('SELECT id FROM teachers WHERE school_id = :school_id AND name = :name LIMIT 1');
        $statement->execute(['school_id' => $schoolId, 'name' => $name]);
        $id = (int) $statement->fetchColumn();
        return $id > 0 ? $id : null;
    }

    private static function ensureTerm(int $schoolId, string $key): int
    {
        $map = self::termMap($schoolId);
        if (isset($map[$key])) {
            return $map[$key];
        }
        $parts = explode('/', $key);
        $label = 'ภาคเรียนที่ ' . ($parts[0] ?? '') . '/' . ($parts[1] ?? '');
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO terms (school_id, label, is_current, rms_key) VALUES (:school_id, :label, 0, :rms_key)')
            ->execute(['school_id' => $schoolId, 'label' => $label, 'rms_key' => $key]);
        return (int) $pdo->lastInsertId();
    }

    private static function termMap(int $schoolId): array
    {
        $statement = Database::pdo()->prepare('SELECT id, rms_key FROM terms WHERE school_id = :school_id AND rms_key IS NOT NULL');
        $statement->execute(['school_id' => $schoolId]);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(string) $row['rms_key']] = (int) $row['id'];
        }
        return $map;
    }

    private static function currentTerm(int $schoolId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, label, rms_key FROM terms WHERE school_id = :school_id AND is_current = 1 ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private static function recountGroups(int $schoolId): void
    {
        Database::pdo()->prepare(
            'UPDATE student_groups g
             LEFT JOIN (
                SELECT group_code, COUNT(*) AS total FROM students WHERE school_id = :school_id GROUP BY group_code
             ) s ON s.group_code = g.rms_group_code
             SET g.student_count = COALESCE(s.total, 0)
             WHERE g.school_id = :school_id_2 AND g.rms_group_code IS NOT NULL'
        )->execute(['school_id' => $schoolId, 'school_id_2' => $schoolId]);
    }

    private static function level(string $grade, string $name): string
    {
        $text = $grade . ' ' . $name;
        if (str_contains($text, 'ปวช')) {
            return 'ปวช.';
        }
        if (str_contains($text, 'ปวส')) {
            return 'ปวส.';
        }
        if (str_contains($text, 'ปริญญา') || str_contains($text, 'ป.ตรี')) {
            return 'ป.ตรี';
        }
        return trim($grade);
    }

    private static function field(array $row, array $keys): string
    {
        $lower = [];
        foreach ($row as $key => $value) {
            $lower[strtolower((string) $key)] = $value;
        }
        foreach ($keys as $key) {
            $value = $lower[strtolower($key)] ?? null;
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function subjectHours(array $row): array
    {
        $theory = self::intOrNull(self::field($row, ['theory', 'theo', 'hour_theory', 'hourT', 'theoryHour', 'credit_t', 'ท']));
        $practice = self::intOrNull(self::field($row, ['practice', 'prac', 'hour_practice', 'hourP', 'practiceHour', 'credit_p', 'ป']));
        $extra = self::intOrNull(self::field($row, ['extra', 'self', 'selfStudy', 'hour_self', 'hourN', 'selfHour', 'credit_n', 'น']));
        $blob = self::field($row, ['credit', 'credits', 'unit', 'creditHour', 'tpn', 'hour', 'หน่วยกิต']);
        if ($theory === null && $practice === null && preg_match('/(\d+)\s*[-–]\s*(\d+)\s*[-–]\s*(\d+)/u', $blob, $match)) {
            return [(int) $match[1], (int) $match[2], (int) $match[3]];
        }
        $theory = $theory ?? 0;
        $practice = $practice ?? 0;
        $extra = $extra ?? 0;
        if ($theory === 0 && $practice === 0 && $extra === 0 && preg_match('/^\d+$/', $blob)) {
            $theory = (int) $blob;
        }
        return [$theory, $practice, $extra];
    }

    private static function textOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);
        return $value !== '' && is_numeric($value) ? (int) $value : null;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }
        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : null;
    }
}
