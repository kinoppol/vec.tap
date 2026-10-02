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
        'majors' => 'std2018_major',
        'minors' => 'std2018_minor',
        'subjecttypes' => 'std2018_subjecttype',
        'curricula' => 'std2018_curriculum',
        'catalog' => 'subject',
        'students' => 'std2018_student',
        'enrollments' => 'std2018_studentenroll',
        'timetables' => 'std2018_timetable',
        'blocks' => 'std2018_timetable_blockcourse',
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
            if (!in_array($key, ['count', 'limit', 'semes', 'academicYear', 'semester', 'timeTableID'], true)) {
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
        return self::remoteCount($schoolId, 'students');
    }

    public static function remoteCount(int $schoolId, string $dataset): int
    {
        if (!isset(self::DATASETS[$dataset])) {
            throw new RuntimeException('ชุดข้อมูลไม่ถูกต้อง');
        }
        $rows = self::fetch($schoolId, $dataset, ['count' => 'yes']);
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
            'majors' => self::syncDictionary($schoolId, 'majors', 'rms_majors', 'major_id', ['majorID'], ['majorCode'], ['majorNameTh', 'majorName'], ['majorNameEn']),
            'minors' => self::syncDictionary($schoolId, 'minors', 'rms_minors', 'minor_id', ['minorID'], ['minorCode'], ['minorNameTh', 'minorName'], ['minorNameEn']),
            'subjecttypes' => self::syncDictionary($schoolId, 'subjecttypes', 'rms_subject_types', 'subject_type_id', ['subjectTypeID'], ['subjectTypeCode'], ['subjectTypeNameTh', 'subjectTypeName'], ['subjectTypeNameEn']),
            'curricula' => self::syncCurricula($schoolId),
            'catalog' => self::syncCatalog($schoolId, $offset, $row),
            'students' => self::syncStudents($schoolId, $offset, $row),
            'enrollments' => self::syncEnrollments($schoolId, $offset, $row),
            'timetables' => self::syncTimetables($schoolId, $offset, $row),
            'blocks' => self::syncBlocks($schoolId, $offset, $row),
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
            'majors' => $one('SELECT COUNT(*) FROM rms_majors WHERE school_id = :school_id'),
            'minors' => $one('SELECT COUNT(*) FROM rms_minors WHERE school_id = :school_id'),
            'subjecttypes' => $one('SELECT COUNT(*) FROM rms_subject_types WHERE school_id = :school_id'),
            'curricula' => $one('SELECT COUNT(*) FROM rms_curricula WHERE school_id = :school_id'),
            'catalog' => $one('SELECT COUNT(*) FROM rms_subject_catalog WHERE school_id = :school_id'),
            'students' => $one('SELECT COUNT(*) FROM students WHERE school_id = :school_id'),
            'enrollments' => $one('SELECT COUNT(*) FROM rms_enrollments WHERE school_id = :school_id'),
            'timetables' => $one('SELECT COUNT(*) FROM rms_timetables WHERE school_id = :school_id'),
            'blocks' => $one('SELECT COUNT(*) FROM rms_blockcourses WHERE school_id = :school_id'),
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
            'catalog' => [
                'select' => 'code, name',
                'from' => 'rms_subject_catalog',
                'search' => ['code', 'name'],
                'order' => 'code',
            ],
            'curricula' => [
                'select' => 'c.curriculum_year AS curriculum_year, t.name_th AS subject_type, m.name_th AS major_name, n.name_th AS minor_name',
                'from' => 'rms_curricula c LEFT JOIN rms_subject_types t ON t.school_id = c.school_id AND t.subject_type_id = c.subject_type_id LEFT JOIN rms_majors m ON m.school_id = c.school_id AND m.major_id = c.major_id LEFT JOIN rms_minors n ON n.school_id = c.school_id AND n.minor_id = c.minor_id',
                'where' => 'c.school_id = :school_id',
                'search' => ['c.curriculum_year', 't.name_th', 'm.name_th', 'n.name_th'],
                'order' => 'c.curriculum_year, m.name_th',
            ],
            'timetables' => [
                'select' => 'term_key, group_code, subject_code, subject_name, teacher_name, day_code, time_from_name, time_to_name, room_name, building_name',
                'from' => 'rms_timetables',
                'search' => ['group_code', 'subject_code', 'subject_name', 'teacher_name', 'room_name', 'building_name'],
                'order' => 'term_key, group_code, day_code, time_from_name',
            ],
            'blocks' => [
                'select' => 'term_key, group_code, day_code, time_from_name, time_to_name, teacher_id, timetable_id',
                'from' => 'rms_blockcourses',
                'search' => ['group_code', 'teacher_id', 'timetable_id'],
                'order' => 'term_key, group_code, day_code',
            ],
            'enrollments' => [
                'select' => 'term_key, student_code, firstname, surname, timetable_id',
                'from' => 'rms_enrollments',
                'search' => ['student_code', 'firstname', 'surname', 'timetable_id'],
                'order' => 'term_key, surname, firstname',
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
        if ($start === null || $length === null || $start < 1 || $start > ScheduleEngine::LAST_PERIOD || $length < 1) {
            return null;
        }
        if ($start + $length > ScheduleEngine::LAST_PERIOD + 1) {
            $length = ScheduleEngine::LAST_PERIOD + 1 - $start;
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
        $termId = self::importTermId($schoolId);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $code = trim((string) ($row['groupCode'] ?? ''));
            if ($code === '') {
                $skipped++;
                continue;
            }
            $name = trim((string) ($row['groupName'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['groupAbbr'] ?? ''));
            }
            if ($name === '') {
                $name = $code;
            }
            $level = self::level((string) ($row['grade'] ?? ''), $name, $code);
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
        $renameSubject = $pdo->prepare(
            'UPDATE subjects SET name = :name WHERE id = :id AND school_id = :school_id'
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
        $termId = self::importTermId($schoolId);
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $code = self::field($row, ['subjectCode', 'subject_code', 'real_subject_id', 'subject_id', 'subjCode', 'curiCode', 'courseCode', 'รหัสวิชา']);
            $name = self::field($row, ['subjectNameTh', 'subjectName', 'subject_name', 'subjName', 'curiName', 'courseName', 'ชื่อวิชา']);
            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }
            $code = mb_substr($code, 0, 32);
            $name = mb_substr($name, 0, 255);
            $hours = self::parseHours($row) ?? self::catalogHours($schoolId, $code);
            $groupCode = self::field($row, ['GroupCode', 'groupCode', 'group_code', 'student_group_id', 'studentGroupId', 'groupId', 'รหัสกลุ่ม']);
            $planCode = self::field($row, ['planCode', 'plan_code', 'curiPlanCode', 'curriculumCode', 'รหัสแผน']);
            $planName = self::field($row, ['planName', 'plan_name', 'curiPlanName', 'curriculumName', 'majorName', 'majorNameTh', 'ชื่อแผน']);
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
                    $planName = $planCode !== '' ? 'แผน ' . $planCode : 'แผนจาก RMS';
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
                if ($hours === null) {
                    $renameSubject->execute(['name' => $name, 'id' => $subjectId, 'school_id' => $schoolId]);
                } else {
                    $updateSubject->execute([
                        'name' => $name,
                        'theory' => $hours[0],
                        'practice' => $hours[1],
                        'extra' => $hours[2],
                        'id' => $subjectId,
                        'school_id' => $schoolId,
                    ]);
                }
                $subjectsUpdated++;
            } else {
                $insertSubject->execute([
                    'school_id' => $schoolId,
                    'plan_id' => $planId,
                    'code' => $code,
                    'name' => $name,
                    'theory' => $hours[0] ?? 0,
                    'practice' => $hours[1] ?? 0,
                    'extra' => $hours[2] ?? 0,
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

    private static function placeSchedule(int $schoolId, array $term, array $row, ?int $periods, string $source = 'rms'): bool
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
        $groupId = (int) $groupRow['id'];
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
            ->execute(['plan_id' => $planId, 'id' => $groupId, 'school_id' => $schoolId]);
        $start = $span[0];
        $end = $start + $span[1] - 1;
        $neighbors = $pdo->prepare(
            'SELECT id, start_period, length_periods FROM timetable_entries
             WHERE school_id = :school_id AND group_id = :group_id AND subject_id = :subject_id AND day_index = :day_index
               AND start_period <= :touch_end AND start_period + length_periods >= :touch_start
             ORDER BY start_period, id'
        );
        $neighbors->execute([
            'school_id' => $schoolId,
            'group_id' => $groupId,
            'subject_id' => $subjectId,
            'day_index' => $day,
            'touch_end' => $end + 1,
            'touch_start' => $start,
        ]);
        $found = $neighbors->fetchAll();
        if ($found !== []) {
            $keepId = (int) $found[0]['id'];
            $mergedStart = $start;
            $mergedEnd = $end;
            foreach ($found as $neighbor) {
                $neighborStart = (int) $neighbor['start_period'];
                $neighborEnd = $neighborStart + (int) $neighbor['length_periods'] - 1;
                $mergedStart = min($mergedStart, $neighborStart);
                $mergedEnd = max($mergedEnd, $neighborEnd);
                if ((int) $neighbor['id'] !== $keepId) {
                    $pdo->prepare('DELETE FROM timetable_entries WHERE id = :id AND school_id = :school_id')
                        ->execute(['id' => (int) $neighbor['id'], 'school_id' => $schoolId]);
                }
            }
            $pdo->prepare(
                'UPDATE timetable_entries SET start_period = :start_period, length_periods = :length_periods
                 WHERE id = :id AND school_id = :school_id'
            )->execute([
                'start_period' => $mergedStart,
                'length_periods' => $mergedEnd - $mergedStart + 1,
                'id' => $keepId,
                'school_id' => $schoolId,
            ]);
            return true;
        }
        $pdo->prepare(
            'INSERT INTO timetable_entries (school_id, group_id, subject_id, day_index, start_period, length_periods, is_manual, source)
             VALUES (:school_id, :group_id, :subject_id, :day_index, :start_period, :length_periods, 1, :source)'
        )->execute([
            'school_id' => $schoolId,
            'group_id' => $groupId,
            'subject_id' => $subjectId,
            'day_index' => $day,
            'start_period' => $start,
            'length_periods' => $span[1],
            'source' => $source === 'std' ? 'std' : 'rms',
        ]);
        return true;
    }

    private static function syncDictionary(int $schoolId, string $dataset, string $table, string $idColumn, array $idKeys, array $codeKeys, array $nameKeys, array $englishKeys): array
    {
        $rows = self::fetch($schoolId, $dataset);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM ' . $table . ' WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        $insert = $pdo->prepare(
            'INSERT INTO ' . $table . ' (school_id, ' . $idColumn . ', code, name_th, name_en)
             VALUES (:school_id, :item_id, :code, :name_th, :name_en)'
        );
        $added = 0;
        $skipped = 0;
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $itemId = self::field($row, $idKeys);
            $name = self::field($row, $nameKeys);
            if ($itemId === '' || $name === '' || isset($seen[$itemId])) {
                $skipped++;
                continue;
            }
            $seen[$itemId] = true;
            $insert->execute([
                'school_id' => $schoolId,
                'item_id' => mb_substr($itemId, 0, 30),
                'code' => self::nullable(self::field($row, $codeKeys), 40),
                'name_th' => mb_substr($name, 0, 255),
                'name_en' => self::nullable(self::field($row, $englishKeys), 255),
            ]);
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncCurricula(int $schoolId): array
    {
        $rows = self::fetch($schoolId, 'curricula');
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM rms_curricula WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        $insert = $pdo->prepare(
            'INSERT INTO rms_curricula (school_id, curriculum_year, degree_level_id, subject_type_id, major_id, minor_id)
             VALUES (:school_id, :curriculum_year, :degree_level_id, :subject_type_id, :major_id, :minor_id)
             ON DUPLICATE KEY UPDATE curriculum_year = VALUES(curriculum_year)'
        );
        $added = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $year = self::field($row, ['curriculumYear', 'curriculum_year']);
            if ($year === '') {
                $skipped++;
                continue;
            }
            $insert->execute([
                'school_id' => $schoolId,
                'curriculum_year' => mb_substr($year, 0, 10),
                'degree_level_id' => mb_substr(self::field($row, ['degreeLevelID', 'degree_level_id']), 0, 20),
                'subject_type_id' => mb_substr(self::field($row, ['subjectTypeID', 'subject_type_id']), 0, 30),
                'major_id' => mb_substr(self::field($row, ['majorID', 'major_id']), 0, 30),
                'minor_id' => mb_substr(self::field($row, ['minorID', 'minor_id']), 0, 30),
            ]);
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncCatalog(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'catalog', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        if ($offset === 0) {
            $pdo->prepare('DELETE FROM rms_subject_catalog WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO rms_subject_catalog (school_id, code, name, theory, practice, extra, hours_known)
             VALUES (:school_id, :code, :name, :theory, :practice, :extra, :hours_known)
             ON DUPLICATE KEY UPDATE name = VALUES(name),
                theory = IF(VALUES(hours_known) = 1, VALUES(theory), theory),
                practice = IF(VALUES(hours_known) = 1, VALUES(practice), practice),
                extra = IF(VALUES(hours_known) = 1, VALUES(extra), extra),
                hours_known = IF(VALUES(hours_known) = 1, 1, hours_known)'
        );
        $added = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $code = self::field($row, ['subject_id', 'subjectID', 'subjectCode']);
            $name = self::field($row, ['subject_name', 'subjectName', 'subjectNameTh']);
            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }
            $hours = self::parseHours($row);
            $insert->execute([
                'school_id' => $schoolId,
                'code' => mb_substr($code, 0, 50),
                'name' => mb_substr($name, 0, 255),
                'theory' => $hours[0] ?? 0,
                'practice' => $hours[1] ?? 0,
                'extra' => $hours[2] ?? 0,
                'hours_known' => $hours === null ? 0 : 1,
            ]);
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncEnrollments(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'enrollments', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        if ($offset === 0) {
            $pdo->prepare('DELETE FROM rms_enrollments WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO rms_enrollments (school_id, term_key, enroll_id, student_code, firstname, surname, idcard, timetable_id)
             VALUES (:school_id, :term_key, :enroll_id, :student_code, :firstname, :surname, :idcard, :timetable_id)'
        );
        $added = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $termKey = self::termKey($row);
            $studentCode = self::field($row, ['studentCode', 'student_code', 'studentID']);
            if ($termKey === '' || $studentCode === '') {
                $skipped++;
                continue;
            }
            $insert->execute([
                'school_id' => $schoolId,
                'term_key' => $termKey,
                'enroll_id' => self::nullable(self::field($row, ['enrollID', 'enroll_id']), 40),
                'student_code' => mb_substr($studentCode, 0, 30),
                'firstname' => self::nullable(self::field($row, ['firstname', 'firstName']), 100),
                'surname' => self::nullable(self::field($row, ['surname', 'lastName']), 100),
                'idcard' => self::nullable(self::field($row, ['idcard', 'idCard']), 20),
                'timetable_id' => self::nullable(self::field($row, ['timeTableID', 'timetable_id']), 40),
            ]);
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncTimetables(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'timetables', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        if ($offset === 0) {
            $pdo->prepare('DELETE FROM rms_timetables WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
            $pdo->prepare('DELETE FROM timetable_entries WHERE school_id = :school_id AND source = \'std\'')
                ->execute(['school_id' => $schoolId]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO rms_timetables (
                school_id, term_key, group_code, subject_code, subject_name, building_name, room_name, day_code,
                time_from_name, time_to_name, teacher_id, teacher_name, teacher_type, timetable_type,
                timetable_id, timetable_sub_id, class_room_extra
             ) VALUES (
                :school_id, :term_key, :group_code, :subject_code, :subject_name, :building_name, :room_name, :day_code,
                :time_from_name, :time_to_name, :teacher_id, :teacher_name, :teacher_type, :timetable_type,
                :timetable_id, :timetable_sub_id, :class_room_extra
             )'
        );
        $added = 0;
        $placed = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $mapped = self::timetableRow($row);
            if ($mapped === null) {
                $skipped++;
                continue;
            }
            $insert->execute(['school_id' => $schoolId] + $mapped);
            $added++;
            self::rememberCatalogHours(
                $schoolId,
                (string) $mapped['subject_code'],
                (string) ($mapped['subject_name'] ?? ''),
                self::parseHours($row)
            );
            if ($mapped['class_room_extra'] === 'Y' || $mapped['time_from_name'] === null || $mapped['time_to_name'] === null) {
                $skipped++;
                continue;
            }
            if (self::placeMapped($schoolId, $mapped)) {
                $placed++;
            } else {
                $skipped++;
            }
        }
        return ['added' => $added, 'placed' => $placed, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function syncBlocks(int $schoolId, int $offset, int $row): array
    {
        $rows = self::fetch($schoolId, 'blocks', ['limit' => $offset . ',' . $row]);
        $pdo = Database::pdo();
        if ($offset === 0) {
            $pdo->prepare('DELETE FROM rms_blockcourses WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO rms_blockcourses (school_id, term_key, group_code, day_code, time_from_name, time_to_name, teacher_id, timetable_id, timetable_sub_id)
             VALUES (:school_id, :term_key, :group_code, :day_code, :time_from_name, :time_to_name, :teacher_id, :timetable_id, :timetable_sub_id)'
        );
        $parent = $pdo->prepare(
            'SELECT subject_code, subject_name, building_name, room_name, group_code, teacher_name
             FROM rms_timetables WHERE school_id = :school_id AND timetable_id = :timetable_id
             ORDER BY id LIMIT 1'
        );
        $added = 0;
        $placed = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $termKey = self::termKey($row);
            $timetableId = self::field($row, ['timeTableID']);
            if ($termKey === '' || $timetableId === '') {
                $skipped++;
                continue;
            }
            $from = self::clock(self::field($row, ['timeFromName']));
            $to = self::clock(self::field($row, ['timeToName']));
            $groupCode = self::field($row, ['classRoom1', 'groupCode']);
            $teacherId = self::field($row, ['teacherIdCard', 'teacher_id']);
            $insert->execute([
                'school_id' => $schoolId,
                'term_key' => $termKey,
                'group_code' => self::nullable($groupCode, 50),
                'day_code' => self::nullable(self::field($row, ['day']), 10),
                'time_from_name' => self::nullable($from, 20),
                'time_to_name' => self::nullable($to, 20),
                'teacher_id' => self::nullable($teacherId, 30),
                'timetable_id' => mb_substr($timetableId, 0, 40),
                'timetable_sub_id' => self::nullable(self::field($row, ['timeTableSubID']), 40),
            ]);
            $added++;
            if ($from === '' || $to === '') {
                $skipped++;
                continue;
            }
            $parent->execute(['school_id' => $schoolId, 'timetable_id' => $timetableId]);
            $base = $parent->fetch() ?: [];
            $mapped = [
                'term_key' => $termKey,
                'group_code' => $groupCode !== '' ? $groupCode : (string) ($base['group_code'] ?? ''),
                'subject_code' => (string) ($base['subject_code'] ?? ''),
                'subject_name' => (string) ($base['subject_name'] ?? ''),
                'building_name' => (string) ($base['building_name'] ?? ''),
                'room_name' => (string) ($base['room_name'] ?? ''),
                'day_code' => self::field($row, ['day']),
                'time_from_name' => $from,
                'time_to_name' => $to,
                'teacher_id' => $teacherId,
                'teacher_name' => (string) ($base['teacher_name'] ?? ''),
            ];
            if (self::placeMapped($schoolId, $mapped)) {
                $placed++;
            } else {
                $skipped++;
            }
        }
        return ['added' => $added, 'placed' => $placed, 'skipped' => $skipped, 'fetched' => count($rows)];
    }

    private static function timetableRow(array $row): ?array
    {
        $termKey = self::termKey($row);
        $subjectCode = self::field($row, ['subjectCode', 'subject_id']);
        if ($termKey === '' || $subjectCode === '') {
            return null;
        }
        $teacher = trim(self::field($row, ['teacherFirstname']) . ' ' . self::field($row, ['teacherSurname']));
        return [
            'term_key' => $termKey,
            'group_code' => self::nullable(self::field($row, ['classRoom1', 'groupCode']), 50),
            'subject_code' => mb_substr($subjectCode, 0, 50),
            'subject_name' => self::nullable(self::field($row, ['subjectName', 'subjectNameTh']), 255),
            'building_name' => self::nullable(self::field($row, ['buildingName']), 150),
            'room_name' => self::nullable(self::field($row, ['roomName']), 80),
            'day_code' => self::nullable(self::field($row, ['day']), 10),
            'time_from_name' => self::nullable(self::clock(self::field($row, ['timeFromName'])), 20),
            'time_to_name' => self::nullable(self::clock(self::field($row, ['timeToName'])), 20),
            'teacher_id' => self::nullable(self::field($row, ['teacherIdCard']), 30),
            'teacher_name' => self::nullable($teacher, 150),
            'teacher_type' => self::nullable(self::field($row, ['teacherType']), 10),
            'timetable_type' => self::nullable(self::field($row, ['timeTableType']), 10),
            'timetable_id' => self::nullable(self::field($row, ['timeTableID']), 40),
            'timetable_sub_id' => self::nullable(self::field($row, ['timeTableSubID']), 40),
            'class_room_extra' => self::nullable(self::field($row, ['classRoomExtra']), 5),
        ];
    }

    private static function placeMapped(int $schoolId, array $mapped): bool
    {
        $term = self::currentTerm($schoolId);
        if ($term === null) {
            return false;
        }
        $from = (string) ($mapped['time_from_name'] ?? '');
        $to = (string) ($mapped['time_to_name'] ?? '');
        $periods = null;
        if (preg_match('/^(\d{1,2})[:.](\d{2})$/', $from, $start) && preg_match('/^(\d{1,2})[:.](\d{2})$/', $to, $end)) {
            $minutes = ((int) $end[1] * 60 + (int) $end[2]) - ((int) $start[1] * 60 + (int) $start[2]);
            if ($minutes > 0) {
                $periods = max(1, (int) round($minutes / 60));
            }
        }
        return self::placeSchedule($schoolId, $term, [
            'student_group_id' => (string) ($mapped['group_code'] ?? ''),
            'real_subject_id' => (string) ($mapped['subject_code'] ?? ''),
            'subject_name' => (string) ($mapped['subject_name'] ?? ''),
            'dpr2' => self::dayName((string) ($mapped['day_code'] ?? '')),
            'dpr3' => $from . '-' . $to,
            'teacher_id' => (string) ($mapped['teacher_id'] ?? ''),
            'teacher_name' => (string) ($mapped['teacher_name'] ?? ''),
            'roomName' => (string) ($mapped['room_name'] ?? ''),
            'ucode' => (string) ($mapped['building_name'] ?? ''),
        ], $periods, 'std');
    }

    private static function termKey(array $row): string
    {
        $combined = self::field($row, ['dateedu_eduyear', 'eduyear']);
        if ($combined !== '' && str_contains($combined, '/')) {
            return mb_substr($combined, 0, 20);
        }
        $year = self::field($row, ['academicYear', 'eduYear', 'year']);
        $semester = self::field($row, ['semester', 'semes']);
        if ($year === '' || $semester === '') {
            return '';
        }
        return mb_substr($semester . '/' . $year, 0, 20);
    }

    private static function dayName(string $day): string
    {
        return match ($day) {
            '1' => 'จันทร์',
            '2' => 'อังคาร',
            '3' => 'พุธ',
            '4' => 'พฤหัส',
            '5' => 'ศุกร์',
            '6' => 'เสาร์',
            '7' => 'อาทิตย์',
            default => $day,
        };
    }

    private static function clock(string $value): string
    {
        return str_replace(':', '.', trim($value));
    }

    private static function nullable(string $value, int $length): ?string
    {
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $length);
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

    private static function importTermId(int $schoolId): int
    {
        $term = self::currentTerm($schoolId);
        if ($term === null) {
            throw new RuntimeException('ยังไม่ได้ตั้งภาคเรียนปัจจุบัน ตั้งภาคเรียนก่อนโอนข้อมูล');
        }
        return (int) $term['id'];
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

    private static function level(string $grade, string $name, string $code = ''): string
    {
        $base = trim((string) preg_replace('/\s*\(.*/u', '', $name));
        if (str_starts_with($base, 'ทล.บ')) {
            return 'ป.ตรี';
        }
        $digit = strlen($code) >= 3 ? $code[2] : '';
        if ($digit === '2') {
            return 'ปวช.';
        }
        if ($digit === '3') {
            return 'ปวส.';
        }
        if (str_starts_with($base, 'ช')) {
            return 'ปวช.';
        }
        if (str_starts_with($base, 'ส')) {
            return 'ปวส.';
        }
        $text = $grade . ' ' . $name;
        if (str_contains($text, 'ปริญญา') || str_contains($text, 'ป.ตรี') || str_contains($text, 'ทล.บ')) {
            return 'ป.ตรี';
        }
        if (str_contains($text, 'ปวส')) {
            return 'ปวส.';
        }
        if (str_contains($text, 'ปวช')) {
            return 'ปวช.';
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

    public static function parseHours(array $row): ?array
    {
        $theory = self::intOrNull(self::field($row, ['theory', 'theo', 'hour_theory', 'hourT', 'theoryHour', 'credit_t', 'timeT', 'ท']));
        $practice = self::intOrNull(self::field($row, ['practice', 'prac', 'hour_practice', 'hourP', 'practiceHour', 'credit_p', 'timeP', 'ป']));
        $extra = self::intOrNull(self::field($row, ['extra', 'self', 'selfStudy', 'hour_self', 'hourN', 'selfHour', 'credit_n', 'timeN', 'น']));
        if ($theory !== null || $practice !== null || $extra !== null) {
            return [$theory ?? 0, $practice ?? 0, $extra ?? 0];
        }
        foreach ($row as $key => $value) {
            if (!is_scalar($value) || !preg_match('/credit|hour|show|tpn|unit|theory|prac|self|ท|ป|น/ui', (string) $key)) {
                continue;
            }
            $text = trim((string) $value);
            if (preg_match('/(\d+)\s*\(\s*(\d+)\s*[-–]\s*(\d+)\s*[-–]\s*(\d+)\s*\)/u', $text, $match)) {
                return [(int) $match[2], (int) $match[3], (int) $match[4]];
            }
            if (preg_match('/(?<!\d)(\d+)\s*[-–]\s*(\d+)\s*[-–]\s*(\d+)(?!\d)/u', $text, $match)) {
                return [(int) $match[1], (int) $match[2], (int) $match[3]];
            }
        }
        return null;
    }

    private static function rememberCatalogHours(int $schoolId, string $code, string $name, ?array $hours): void
    {
        if ($hours === null || $code === '') {
            return;
        }
        Database::pdo()->prepare(
            'INSERT INTO rms_subject_catalog (school_id, code, name, theory, practice, extra, hours_known)
             VALUES (:school_id, :code, :name, :theory, :practice, :extra, 1)
             ON DUPLICATE KEY UPDATE
                theory = VALUES(theory), practice = VALUES(practice), extra = VALUES(extra), hours_known = 1'
        )->execute([
            'school_id' => $schoolId,
            'code' => mb_substr($code, 0, 50),
            'name' => mb_substr($name !== '' ? $name : $code, 0, 255),
            'theory' => $hours[0],
            'practice' => $hours[1],
            'extra' => $hours[2],
        ]);
    }

    private static function catalogHours(int $schoolId, string $code): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT theory, practice, extra FROM rms_subject_catalog
             WHERE school_id = :school_id AND code = :code AND hours_known = 1 LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'code' => $code]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        return [(int) $row['theory'], (int) $row['practice'], (int) $row['extra']];
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
