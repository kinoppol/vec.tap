<?php
declare(strict_types=1);

function seed_sample(PDO $pdo): array
{
    $accounts = [];
    $school1 = create_school($pdo, 'วิทยาลัยเทคนิคเมืองใหม่');
    $school2 = create_school($pdo, 'วิทยาลัยอาชีวศึกษาริมน้ำ');
    $school3 = create_school($pdo, 'วิทยาลัยการอาชีพบ้านสวน');

    $termId = (int) $pdo->query('SELECT id FROM terms WHERE school_id = ' . $school1 . ' ORDER BY id LIMIT 1')->fetchColumn();

    $teacherRows = [
        'nattawut' => ['ครูณัฐวุฒิ พรหมมา', 'เทคโนโลยีสารสนเทศ', 'ป.โท วิทยาการคอมพิวเตอร์', 18, ['Python', 'C', 'Linux', 'การแข่งขันทักษะ']],
        'thanakorn' => ['ครูธนากร ใจงาม', 'เทคโนโลยีสารสนเทศ', 'ป.ตรี คอมพิวเตอร์ศึกษา', 20, ['Office', 'Network', 'Windows Server']],
        'kamon' => ['ครูกมลชนก วงศ์ใหญ่', 'เทคโนโลยีสารสนเทศ', 'ป.ตรี ศิลปกรรม', 20, ['Photoshop', 'Illustrator', 'วิดีโอ']],
        'orathai' => ['ครูอรทัย บุญมา', 'สามัญสัมพันธ์', 'ป.ตรี วิทยาศาสตร์ทั่วไป', 18, ['ฟิสิกส์', 'เคมี', 'ห้องปฏิบัติการ']],
        'weera' => ['ครูวีระพงษ์ ทองดี', 'สามัญสัมพันธ์', 'ป.ตรี คณิตศาสตร์', 18, ['คณิตศาสตร์', 'สถิติ']],
        'pim' => ['ครูพิมพ์ชนก ศรีสุข', 'สามัญสัมพันธ์', 'ป.ตรี ภาษาอังกฤษ', 20, ['ภาษาอังกฤษ', 'TOEIC 850']],
        'sunisa' => ['ครูสุนิสา แก้วมณี', 'สามัญสัมพันธ์', 'ป.โท ภาษาไทย', 20, ['ภาษาไทย', 'การเขียน']],
        'somchai' => ['ครูสมชาย รักชาติ', 'กิจการนักเรียน', 'ป.ตรี พลศึกษา', 16, ['ลูกเสือวิสามัญ', 'พลศึกษา']],
    ];
    $teacherIds = [];
    $insertTeacher = $pdo->prepare(
        'INSERT INTO teachers (school_id, name, dept, degree, max_hours) VALUES (:school_id, :name, :dept, :degree, :max_hours)'
    );
    $insertSkill = $pdo->prepare('INSERT INTO teacher_skills (teacher_id, skill) VALUES (:teacher_id, :skill)');
    foreach ($teacherRows as $key => $row) {
        $insertTeacher->execute([
            'school_id' => $school1,
            'name' => $row[0],
            'dept' => $row[1],
            'degree' => $row[2],
            'max_hours' => $row[3],
        ]);
        $teacherIds[$key] = (int) $pdo->lastInsertId();
        foreach ($row[4] as $skill) {
            $insertSkill->execute(['teacher_id' => $teacherIds[$key], 'skill' => $skill]);
        }
    }

    $buildingRows = [
        ['อาคาร 1 อำนวยการและสามัญ', 'อาคาร 1', 'วิทยาเขตหลัก', '13.7563', '100.5018', '—', 22, 34, '#D63384', '#F3EEF4', '#6E6473'],
        ['อาคาร 3 ช่างอุตสาหกรรม', 'อาคาร 3', 'วิทยาเขตหลัก', '13.7568', '100.5026', '2 นาที', 34, 22, '#D63384', '#E8F5EE', '#157347'],
        ['อาคาร 5 เทคโนโลยีสารสนเทศ', 'อาคาร 5', 'วิทยาเขตหลัก', '13.7559', '100.5031', '3 นาที', 36, 48, '#D63384', '#E8F5EE', '#157347'],
        ['ลานกิจกรรม', 'ลานกิจกรรม', 'วิทยาเขตหลัก', '13.7555', '100.5012', '2 นาที', 18, 62, '#D63384', '#E8F5EE', '#157347'],
        ['อาคารวิทยาศาสตร์ SCI', 'SCI วข.2', 'วิทยาเขต 2', '13.7641', '100.5155', '1.8 กม. · ~8 นาที', 82, 70, '#7B4FB0', '#FFF6E0', '#8A5A0B'],
    ];
    $insertBuilding = $pdo->prepare(
        'INSERT INTO buildings (school_id, name, short_name, campus, lat, lng, dist_label, map_x, map_y, dot_color, badge_bg, badge_fg)
         VALUES (:school_id, :name, :short_name, :campus, :lat, :lng, :dist_label, :map_x, :map_y, :dot_color, :badge_bg, :badge_fg)'
    );
    $buildingIds = [];
    foreach ($buildingRows as $index => $row) {
        $insertBuilding->execute([
            'school_id' => $school1,
            'name' => $row[0],
            'short_name' => $row[1],
            'campus' => $row[2],
            'lat' => $row[3],
            'lng' => $row[4],
            'dist_label' => $row[5],
            'map_x' => $row[6],
            'map_y' => $row[7],
            'dot_color' => $row[8],
            'badge_bg' => $row[9],
            'badge_fg' => $row[10],
        ]);
        $buildingIds[$index] = (int) $pdo->lastInsertId();
    }

    $roomRows = [
        ['1-204', 0, 'ห้องเรียนทฤษฎี', 30],
        ['1-205', 0, 'ห้องเรียนทฤษฎี', 40],
        ['1-301', 0, 'ห้องเรียนทฤษฎี', 40],
        ['5-301', 2, 'ห้องปฏิบัติการคอมพิวเตอร์', 36],
        ['5-302', 2, 'ห้องปฏิบัติการคอมพิวเตอร์', 40],
        ['SCI-201', 4, 'ห้องปฏิบัติการวิทยาศาสตร์ (วข.2)', 36],
        ['ลานกิจกรรม', 3, 'ลานกิจกรรม', 80],
    ];
    $insertRoom = $pdo->prepare(
        'INSERT INTO rooms (school_id, building_id, code, room_type, capacity) VALUES (:school_id, :building_id, :code, :room_type, :capacity)'
    );
    $roomIds = [];
    foreach ($roomRows as $row) {
        $insertRoom->execute([
            'school_id' => $school1,
            'building_id' => $buildingIds[$row[1]],
            'code' => $row[0],
            'room_type' => $row[2],
            'capacity' => $row[3],
        ]);
        $roomIds[$row[0]] = (int) $pdo->lastInsertId();
    }

    $planNames = [
        ['ปวช. เทคโนโลยีสารสนเทศ ชั้นปีที่ 1', 15],
        ['ปวช. เทคโนโลยีสารสนเทศ ชั้นปีที่ 2', 17],
        ['ปวส. เทคโนโลยีสารสนเทศ ชั้นปีที่ 1', 18],
        ['ทล.บ. เทคโนโลยีสารสนเทศ ชั้นปีที่ 1', 19],
    ];
    $insertPlan = $pdo->prepare(
        'INSERT INTO study_plans (school_id, term_id, name, credits) VALUES (:school_id, :term_id, :name, :credits)'
    );
    $planIds = [];
    foreach ($planNames as $plan) {
        $insertPlan->execute([
            'school_id' => $school1,
            'term_id' => $termId,
            'name' => $plan[0],
            'credits' => $plan[1],
        ]);
        $planIds[] = (int) $pdo->lastInsertId();
    }

    $subjectRows = [
        ['thai', '20000-1101', 'ภาษาไทยเพื่ออาชีพ', 1, 0, 1, 'sunisa', '1-204'],
        ['eng', '20000-1201', 'ภาษาอังกฤษในชีวิตจริง', 0, 2, 1, 'pim', '1-205'],
        ['math', '20000-1401', 'คณิตศาสตร์เพื่องานอาชีพ', 2, 0, 2, 'weera', '1-204'],
        ['sci', '20000-1301', 'วิทยาศาสตร์เพื่อพัฒนาทักษะชีวิต', 1, 2, 2, 'orathai', 'SCI-201'],
        ['comp', '20001-2001', 'คอมพิวเตอร์และสารสนเทศเพื่องานอาชีพ', 1, 2, 2, 'thanakorn', '5-301'],
        ['prog', '20901-2002', 'การเขียนโปรแกรมเบื้องต้น', 1, 3, 2, 'nattawut', '5-302'],
        ['os', '20901-2003', 'ระบบปฏิบัติการเบื้องต้น', 1, 3, 2, 'nattawut', '5-302'],
        ['gfx', '20901-2004', 'คอมพิวเตอร์กราฟิกเบื้องต้น', 1, 4, 3, 'kamon', '5-301'],
        ['scout', '20000-2001', 'กิจกรรมลูกเสือวิสามัญ 1', 0, 2, 0, 'somchai', 'ลานกิจกรรม'],
    ];
    $insertSubject = $pdo->prepare(
        'INSERT INTO subjects (school_id, plan_id, demo_key, code, name, theory, practice, extra, sort_order, teacher_id, room_id)
         VALUES (:school_id, :plan_id, :demo_key, :code, :name, :theory, :practice, :extra, :sort_order, :teacher_id, :room_id)'
    );
    $subjectIds = [];
    foreach ($subjectRows as $index => $row) {
        $insertSubject->execute([
            'school_id' => $school1,
            'plan_id' => $planIds[0],
            'demo_key' => $row[0],
            'code' => $row[1],
            'name' => $row[2],
            'theory' => $row[3],
            'practice' => $row[4],
            'extra' => $row[5],
            'sort_order' => $index + 1,
            'teacher_id' => $teacherIds[$row[6]],
            'room_id' => $roomIds[$row[7]],
        ]);
        $subjectIds[$row[0]] = (int) $pdo->lastInsertId();
    }

    seed_placeholder_subjects($pdo, $school1, $planIds[1], 'Y2', [3, 3, 3, 3, 3, 3, 3, 3, 2, 2]);
    seed_placeholder_subjects($pdo, $school1, $planIds[2], 'HVC', [3, 3, 3, 3, 3, 3, 3, 3]);
    seed_placeholder_subjects($pdo, $school1, $planIds[3], 'DEG', [3, 3, 3, 3, 3, 3, 3]);

    $groupRows = [
        ['ปวช.1/1 เทคโนโลยีสารสนเทศ', 'ปวช.', 32, 'thanakorn', 'ห้อง 1-204 (30 ที่) เล็กกว่าจำนวนผู้เรียน', 'danger', 0],
        ['ปวช.1/2 เทคโนโลยีสารสนเทศ', 'ปวช.', 35, 'kamon', 'ห้องที่ใช้รองรับได้ทั้งหมด', 'ok', 0],
        ['ปวช.2/1 เทคโนโลยีสารสนเทศ', 'ปวช.', 29, 'nattawut', 'ห้องที่ใช้รองรับได้ทั้งหมด', 'ok', 1],
        ['ปวส.1/1 เทคโนโลยีสารสนเทศ', 'ปวส.', 28, 'thanakorn', 'ห้อง 5-302 เหลือที่ว่าง 12 ที่', 'muted', 2],
        ['ทล.บ.1/1 เทคโนโลยีสารสนเทศ', 'ป.ตรี', 18, 'nattawut', 'แนะนำห้องขนาดเล็กเพื่อประหยัดพลังงาน', 'muted', 3],
    ];
    $insertGroup = $pdo->prepare(
        'INSERT INTO student_groups (school_id, term_id, plan_id, name, level, student_count, advisor_id, note, note_tone)
         VALUES (:school_id, :term_id, :plan_id, :name, :level, :student_count, :advisor_id, :note, :note_tone)'
    );
    $groupIds = [];
    foreach ($groupRows as $row) {
        $insertGroup->execute([
            'school_id' => $school1,
            'term_id' => $termId,
            'plan_id' => $planIds[$row[6]],
            'name' => $row[0],
            'level' => $row[1],
            'student_count' => $row[2],
            'advisor_id' => $teacherIds[$row[3]],
            'note' => $row[4],
            'note_tone' => $row[5],
        ]);
        $groupIds[] = (int) $pdo->lastInsertId();
    }

    $insertEntry = $pdo->prepare(
        'INSERT INTO timetable_entries (school_id, group_id, subject_id, day_index, start_period, length_periods, is_manual, warning, moved)
         VALUES (:school_id, :group_id, :subject_id, :day_index, :start_period, :length_periods, 1, NULL, 0)'
    );
    foreach ([[0, 1, 2, 'prog'], [1, 1, 1, 'thai'], [2, 7, 2, 'scout']] as $entry) {
        $insertEntry->execute([
            'school_id' => $school1,
            'group_id' => $groupIds[0],
            'subject_id' => $subjectIds[$entry[3]],
            'day_index' => $entry[0],
            'start_period' => $entry[1],
            'length_periods' => $entry[2],
        ]);
    }
    $pdo->prepare('INSERT INTO schedule_states (group_id, phase, applied) VALUES (:group_id, :phase, NULL)')
        ->execute(['group_id' => $groupIds[0], 'phase' => 'manual']);

    $accounts[] = seed_account($pdo, $school1, $teacherIds['nattawut'], 'vichai', 'นายวิชัย ศรีประเสริฐ', 'school_admin', 'วิทยาลัยเทคนิคเมืองใหม่', null);
    $accounts[] = seed_account($pdo, $school1, null, 'preeya', 'ครูปรียา สายสุวรรณ', 'scheduler', 'วิทยาลัยเทคนิคเมืองใหม่', null);
    $accounts[] = seed_account($pdo, $school1, $teacherIds['nattawut'], 'nattawut', 'ครูณัฐวุฒิ พรหมมา', 'teacher', 'วิทยาลัยเทคนิคเมืองใหม่', $teacherIds['nattawut']);
    $accounts[] = seed_account($pdo, $school2, null, 'rimnam', 'ผู้ดูแลวิทยาลัยอาชีวศึกษาริมน้ำ', 'school_admin', 'วิทยาลัยอาชีวศึกษาริมน้ำ', null);
    $accounts[] = seed_account($pdo, $school3, null, 'bansuan', 'ผู้ดูแลวิทยาลัยการอาชีพบ้านสวน', 'school_admin', 'วิทยาลัยการอาชีพบ้านสวน', null);

    return array_values(array_filter($accounts));
}

function seed_account(PDO $pdo, int $schoolId, ?int $ignored, string $username, string $name, string $role, string $school, ?int $teacherId): ?array
{
    $exists = $pdo->prepare('SELECT id FROM users WHERE username = :username');
    $exists->execute(['username' => $username]);
    if ($exists->fetchColumn()) {
        return null;
    }
    $password = 'Vec' . random_int(100000, 999999);
    Auth::insert($pdo, $schoolId, $teacherId, $username, $password, $name, $role);
    return [
        'username' => $username,
        'password' => $password,
        'name' => $name,
        'role' => role_label($role),
        'school' => $school,
    ];
}

function seed_placeholder_subjects(PDO $pdo, int $schoolId, int $planId, string $prefix, array $hours): void
{
    $statement = $pdo->prepare(
        'INSERT INTO subjects (school_id, plan_id, demo_key, code, name, theory, practice, extra, sort_order)
         VALUES (:school_id, :plan_id, NULL, :code, :name, :theory, :practice, 0, :sort_order)'
    );
    foreach ($hours as $index => $hour) {
        $theory = intdiv($hour, 2);
        $practice = $hour - $theory;
        $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $statement->execute([
            'school_id' => $schoolId,
            'plan_id' => $planId,
            'code' => $prefix . '-' . $number,
            'name' => 'รายวิชา' . $prefix . ' ' . ($index + 1),
            'theory' => $theory,
            'practice' => $practice,
            'sort_order' => $index + 1,
        ]);
    }
}
