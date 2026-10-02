<?php
declare(strict_types=1);

function dispatch(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $path = request_path();

    if ($method === 'POST') {
        Csrf::check();
    }

    if ($path === '/' && $method === 'GET') {
        page_landing();
        return;
    }
    if ($path === '/login' && $method === 'GET') {
        page_login();
        return;
    }
    if ($path === '/login' && $method === 'POST') {
        page_login_post();
        return;
    }
    if ($path === '/logout' && $method === 'POST') {
        Auth::logout();
        redirect('/login');
    }
    if ($path === '/school' && $method === 'POST') {
        page_switch_school();
        return;
    }
    if ($path === '/chat' && $method === 'POST') {
        page_chat();
        return;
    }
    if ($path === '/dashboard' && $method === 'GET') {
        page_dashboard();
        return;
    }
    if ($path === '/schedule' && $method === 'GET') {
        page_schedule();
        return;
    }
    if ($path === '/schedule' && $method === 'POST') {
        page_schedule_post();
        return;
    }
    if ($path === '/policies' && $method === 'GET') {
        page_policies();
        return;
    }
    if ($path === '/policies' && $method === 'POST') {
        page_policies_post();
        return;
    }
    if ($path === '/skills' && $method === 'GET') {
        page_skills();
        return;
    }
    if ($path === '/skills' && $method === 'POST') {
        page_skills_post();
        return;
    }
    if ($path === '/data' && $method === 'GET') {
        page_data();
        return;
    }
    if ($path === '/data' && $method === 'POST') {
        page_data_post();
        return;
    }
    if ($path === '/data/template' && $method === 'GET') {
        page_template();
        return;
    }
    if ($path === '/data/skills-export' && $method === 'GET') {
        page_teacher_skills_export();
        return;
    }
    if ($path === '/data/buildings-export' && $method === 'GET') {
        page_buildings_export();
        return;
    }
    if ($path === '/data/rooms-export' && $method === 'GET') {
        page_rooms_export();
        return;
    }
    if ($path === '/print' && $method === 'GET') {
        page_print();
        return;
    }
    if ($path === '/rms' && $method === 'GET') {
        page_rms();
        return;
    }
    if ($path === '/rms' && $method === 'POST') {
        page_rms_post();
        return;
    }
    if ($path === '/ai' && $method === 'GET') {
        page_ai();
        return;
    }
    if ($path === '/ai' && $method === 'POST') {
        page_ai_post();
        return;
    }
    if ($path === '/schools' && $method === 'GET') {
        page_schools();
        return;
    }
    if ($path === '/schools' && $method === 'POST') {
        page_schools_post();
        return;
    }
    if ($path === '/migrations' && $method === 'GET') {
        page_migrations();
        return;
    }
    if ($path === '/migrations' && $method === 'POST') {
        page_migrations_post();
        return;
    }

    http_response_code(404);
    if (!Auth::check()) {
        redirect('/login');
    }
    render('missing', ['currentPage' => '']);
}

function page_landing(): void
{
    render('landing', [
        'features' => [
            ['icon' => 'bi-database-add', 'title' => 'นำเข้าข้อมูลครบชุด', 'text' => 'ครูผู้สอน กลุ่มผู้เรียน ข้อมูลผู้เรียน แผนการเรียน รายวิชา ท-ป-น อาคารและห้องเรียน จากไฟล์ Excel หรือ CSV'],
            ['icon' => 'bi-diagram-3', 'title' => 'วิเคราะห์ทักษะครู', 'text' => 'AI เทียบทักษะ วุฒิ และประสบการณ์ของครูกับรายวิชา เพื่อแบ่งรายวิชาก่อนจัดตาราง'],
            ['icon' => 'bi-sliders', 'title' => 'นโยบายเรียงตามความสำคัญ', 'text' => 'ผู้ดูแลระบบเพิ่ม ลด เรียงลำดับ และกำหนดว่าแต่ละข้อเป็นข้อบังคับหรือข้อแนะนำ'],
            ['icon' => 'bi-calendar3-week', 'title' => 'ลงด้วยมือ แล้วให้ AI จัดต่อ', 'text' => 'ล็อกคาบที่กำหนดเองไว้ก่อน แล้วให้ AI เติมส่วนที่เหลือโดยอัตโนมัติตามนโยบาย'],
            ['icon' => 'bi-clipboard-check', 'title' => 'ตรวจชั่วโมงตาม ท-ป-น', 'text' => 'ตรวจว่าแต่ละรายวิชาลงครบชั่วโมงทฤษฎีและปฏิบัติหรือยัง'],
            ['icon' => 'bi-lightbulb', 'title' => 'บอกส่วนที่จัดไม่ได้พร้อมทางแก้', 'text' => 'แจ้งสาเหตุ และเสนอการผ่อนปรนนโยบายหรือการเลื่อนคาบที่ลงด้วยมือเพื่อให้จัดเสร็จ'],
            ['icon' => 'bi-geo-alt', 'title' => 'พิกัดอาคารและหลายวิทยาเขต', 'text' => 'เตือนเมื่อคาบติดกันต้องเดินทางไกล และตรวจขนาดห้องกับจำนวนผู้เรียน'],
            ['icon' => 'bi-buildings', 'title' => 'หลายสถานศึกษาในระบบเดียว', 'text' => 'แต่ละสถานศึกษามีข้อมูล นโยบาย และการตั้งค่า AI แยกกัน'],
        ],
        'steps' => [
            ['no' => '01', 'title' => 'นำเข้าข้อมูล', 'text' => 'ครู กลุ่มผู้เรียน แผนการเรียน รายวิชา อาคาร และห้องเรียน'],
            ['no' => '02', 'title' => 'แบ่งรายวิชา', 'text' => 'AI วิเคราะห์ทักษะครูและเสนอผู้สอนแต่ละวิชา'],
            ['no' => '03', 'title' => 'กำหนดนโยบาย', 'text' => 'เรียงลำดับความสำคัญ เลือกข้อบังคับหรือข้อแนะนำ'],
            ['no' => '04', 'title' => 'ลงคาบที่กำหนดเอง', 'text' => 'ล็อกคาบกิจกรรมหรือคาบที่ต้องการก่อน'],
            ['no' => '05', 'title' => 'AI จัดตารางและแนะนำ', 'text' => 'จัดส่วนที่เหลือ พร้อมทางแก้สำหรับส่วนที่จัดไม่ได้'],
        ],
        'providers' => array_values(ai_catalog()),
    ], 'blank');
}

function page_login(): void
{
    if (Auth::check()) {
        redirect('/dashboard');
    }
    render('login', ['error' => flash()], 'blank');
}

function page_login_post(): void
{
    $username = post_string('username');
    $password = (string) ($_POST['password'] ?? '');
    if (!Auth::attempt($username, $password)) {
        flash('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'err');
        redirect('/login');
    }
    redirect('/dashboard');
}

function page_switch_school(): void
{
    Auth::requireRole(['superadmin']);
    SchoolContext::switchTo((int) post_string('school_id'));
    $back = post_string('back');
    if (!preg_match('#^/[A-Za-z0-9/_.?=&%-]*$#', $back)) {
        $back = '/dashboard';
    }
    redirect($back);
}

function page_dashboard(): void
{
    Auth::requireUser();
    $schoolId = SchoolContext::id();
    render('dashboard', [
        'currentPage' => 'dashboard',
        'counts' => $schoolId > 0 ? Repo::counts($schoolId) : ['teachers' => 0, 'groups' => 0, 'subjects' => 0, 'rooms' => 0, 'campuses' => 0],
        'progress' => $schoolId > 0 ? Repo::progress($schoolId) : [],
        'alerts' => $schoolId > 0 ? Repo::alerts($schoolId) : [],
        'hasSchool' => $schoolId > 0,
    ]);
}

function schedule_context(int $schoolId): array
{
    $terms = $schoolId > 0 ? Repo::terms($schoolId) : [];
    $requested = (int) ($_GET['term'] ?? 0);
    if ($requested <= 0) {
        $requested = (int) ($_SESSION['schedule_term'][$schoolId] ?? 0);
    }
    $selected = null;
    foreach ($terms as $term) {
        if ((int) $term['id'] === $requested) {
            $selected = $term;
            break;
        }
    }
    if ($selected === null) {
        $current = null;
        $withGroups = null;
        foreach ($terms as $term) {
            if ((int) $term['is_current'] === 1) {
                $current = $term;
            }
            if ($withGroups === null && (int) $term['group_count'] > 0) {
                $withGroups = $term;
            }
        }
        if ($current !== null && (int) $current['group_count'] > 0) {
            $selected = $current;
        } elseif ($withGroups !== null) {
            $selected = $withGroups;
        } else {
            $selected = $current ?? ($terms[0] ?? null);
        }
    }
    if ($selected !== null) {
        $_SESSION['schedule_term'][$schoolId] = (int) $selected['id'];
    }
    return ['terms' => $terms, 'term' => $selected];
}

function page_schedule(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $context = schedule_context($schoolId);
    $termId = (int) ($context['term']['id'] ?? 0);
    $groups = $schoolId > 0 ? Repo::groups($schoolId) : [];
    if ($termId > 0) {
        $groups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => (int) $group['term_id'] === $termId
        ));
    }
    $groups = $schoolId > 0 ? ScheduleActions::visibleGroups($schoolId, $user, $groups) : [];
    $scheduleLimited = !ScheduleActions::seesAllGroups($user);
    $requested = (int) ($_GET['group'] ?? 0);
    $group = null;
    foreach ($groups as $item) {
        if ((int) $item['id'] === $requested) {
            $group = $item;
            break;
        }
    }
    if ($group === null && $groups !== []) {
        $group = $groups[0];
    }
    $model = null;
    if ($group) {
        $_SESSION['schedule_group'] = (int) $group['id'];
        $model = schedule_board_model($schoolId, $user, $group);
    }
    render('schedule', [
        'currentPage' => 'schedule',
        'groups' => $groups,
        'terms' => $context['terms'],
        'term' => $context['term'],
        'scheduleLimited' => $scheduleLimited,
        'model' => $model,
    ]);
}

function schedule_hour_value(string $value): int
{
    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        throw new RuntimeException('ชั่วโมง ท-ป-น ต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 40');
    }
    $hours = (int) $value;
    if ($hours > 40) {
        throw new RuntimeException('ชั่วโมง ท-ป-น ต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 40');
    }
    return $hours;
}

function teacher_max_guard(int $schoolId, int $teacherId, int $addedHours, int $termId = 0): array
{
    if ($teacherId <= 0) {
        return ['block' => null, 'warning' => null];
    }
    $teacher = Repo::teacher($schoolId, $teacherId);
    if ($teacher === null) {
        return ['block' => null, 'warning' => null];
    }
    $modes = ScheduleEngine::teacherHourModes(Repo::policies($schoolId));
    $next = (Repo::teachingHours($schoolId, null, $termId > 0 ? $termId : null)[$teacherId] ?? 0) + $addedHours;
    return [
        'block' => $addedHours > 0 ? ScheduleEngine::maxHoursBlock($teacher, $next, $modes['max']) : null,
        'warning' => ScheduleEngine::maxHoursWarning($teacher, $next, $modes['max']),
    ];
}

function page_schedule_post(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $groupId = (int) post_string('group_id');
    $group = Repo::group($schoolId, $groupId);
    if ($group === null) {
        flash('ไม่พบกลุ่มผู้เรียนของสถานศึกษานี้', 'err');
        redirect('/schedule');
    }
    if (!ScheduleActions::canSchedule($user, $schoolId, $groupId)) {
        if (wants_json()) {
            schedule_board_json(false, 'ไม่มีสิทธิ์จัดตารางกลุ่มนี้');
        }
        flash('ไม่มีสิทธิ์จัดตารางกลุ่มนี้', 'err');
        redirect('/schedule');
    }
    $termId = (int) ($group['term_id'] ?? 0);
    $_SESSION['schedule_term'][$schoolId] = $termId;
    $back = '/schedule?term=' . $termId . '&group=' . $groupId;
    $action = post_string('action');
    $parts = explode(':', $action);
    $name = $parts[0] ?? '';
    $arg = $parts[1] ?? '';
    $arg2 = $parts[2] ?? '';
    $arg3 = $parts[3] ?? '';
    $arg4 = $parts[4] ?? '';
    $ajax = wants_json();
    $editable = ['pick', 'clear_pick', 'add', 'toggle', 'remove', 'reset', 'run', 'apply', 'move', 'resize', 'assign', 'hours'];
    if (in_array($name, $editable, true) && !ScheduleActions::canSchedule($user, $schoolId, $groupId)) {
        if ($ajax) {
            schedule_board_json(false, 'บทบาทนี้แก้ไขตารางไม่ได้');
        }
        flash('บทบาทนี้แก้ไขตารางไม่ได้', 'err');
        redirect($back);
    }
    $subjects = !empty($group['plan_id']) ? Repo::subjectsForPlan($schoolId, (int) $group['plan_id']) : [];
    $entries = Repo::entries($schoolId, $groupId);
    $lockLunch = ScheduleEngine::lunchState(Repo::policies($schoolId), (string) $group['level']) === 'required';
    try {
        if ($name === 'pick') {
            $day = (int) $arg;
            $period = (int) $arg2;
            if ($day < 0 || $day > 4 || ScheduleEngine::blocked((string) $group['level'], $period, $lockLunch)) {
                throw new RuntimeException('ช่องนี้ลงรายวิชาไม่ได้');
            }
            $_SESSION['pick'] = ['group_id' => $groupId, 'day' => $day, 'period' => $period];
            unset($_SESSION['selected_entry']);
        } elseif ($name === 'clear_pick') {
            unset($_SESSION['pick']);
        } elseif ($name === 'add') {
            $pick = $_SESSION['pick'] ?? null;
            if (!is_array($pick) || (int) $pick['group_id'] !== $groupId) {
                throw new RuntimeException('ยังไม่ได้เลือกช่องในตาราง');
            }
            $next = ScheduleEngine::addManual($subjects, $entries, (int) $arg, (int) $pick['day'], (int) $pick['period'], (string) $group['level'], $lockLunch);
            if ($next === null) {
                throw new RuntimeException('ลงรายวิชานี้ในช่องนี้ไม่ได้');
            }
            $createdIndex = array_key_last($next);
            $created = $next[$createdIndex];
            $subject = null;
            foreach ($subjects as $item) {
                if ((int) $item['id'] === (int) $created['subject_id']) {
                    $subject = $item;
                    break;
                }
            }
            $guard = teacher_max_guard($schoolId, (int) ($subject['teacher_id'] ?? 0), (int) $created['length'], $termId);
            if ($guard['block'] !== null) {
                throw new RuntimeException($guard['block']);
            }
            if ($guard['warning'] !== null) {
                $next[$createdIndex]['warning'] = $guard['warning'];
            }
            Repo::replaceEntries($schoolId, $groupId, $next);
            Repo::saveState($groupId, 'manual', null);
            unset($_SESSION['pick'], $_SESSION['selected_entry']);
        } elseif ($name === 'select') {
            $entryId = (int) $arg;
            $found = false;
            foreach ($entries as $entry) {
                if ((int) $entry['id'] === $entryId) {
                    $_SESSION['selected_entry'] = $entryId;
                    unset($_SESSION['pick']);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException('ไม่พบคาบในตารางนี้');
            }
        } elseif ($name === 'move' || $name === 'resize') {
            $entryId = (int) $arg;
            $day = (int) $arg2;
            $start = (int) $arg3;
            $current = null;
            foreach ($entries as $entry) {
                if ((int) $entry['id'] === $entryId) {
                    $current = $entry;
                    break;
                }
            }
            if ($current === null) {
                throw new RuntimeException('ไม่พบคาบในตารางนี้');
            }
            $length = $name === 'resize' ? (int) $arg4 : (int) $current['length_periods'];
            $error = ScheduleEngine::placementError($subjects, $entries, $entryId, $day, $start, $length, (string) $group['level'], $lockLunch);
            if ($error !== null) {
                throw new RuntimeException($error);
            }
            $delta = $length - (int) $current['length_periods'];
            $guard = teacher_max_guard($schoolId, (int) ($current['teacher_id'] ?? 0), $delta, $termId);
            if ($guard['block'] !== null) {
                throw new RuntimeException($guard['block']);
            }
            $warning = $current['warning'] ?? null;
            if ($guard['warning'] !== null) {
                $warning = $guard['warning'];
            } elseif (is_string($warning) && str_starts_with($warning, 'เกินชั่วโมงสูงสุด')) {
                $warning = null;
            }
            $update = Database::pdo()->prepare(
                'UPDATE timetable_entries
                 SET day_index = :day_index, start_period = :start_period, length_periods = :length_periods,
                     is_manual = 1, warning = :warning
                 WHERE id = :id AND school_id = :school_id AND group_id = :group_id'
            );
            $update->bindValue(':day_index', $day, PDO::PARAM_INT);
            $update->bindValue(':start_period', $start, PDO::PARAM_INT);
            $update->bindValue(':length_periods', $length, PDO::PARAM_INT);
            $update->bindValue(':warning', $warning, $warning === null || $warning === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $update->bindValue(':id', $entryId, PDO::PARAM_INT);
            $update->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
            $update->bindValue(':group_id', $groupId, PDO::PARAM_INT);
            $update->execute();
            $_SESSION['selected_entry'] = $entryId;
            unset($_SESSION['pick']);
        } elseif ($name === 'assign') {
            $entryId = (int) $arg;
            $subjectId = 0;
            foreach ($entries as $entry) {
                if ((int) $entry['id'] === $entryId) {
                    $subjectId = (int) $entry['subject_id'];
                    break;
                }
            }
            if ($subjectId <= 0) {
                throw new RuntimeException('ไม่พบคาบในตารางนี้');
            }
            $teacherId = Repo::ensureTeacherId($schoolId, post_string('teacher_name'));
            $oldTeacher = 0;
            foreach ($subjects as $item) {
                if ((int) $item['id'] === $subjectId) {
                    $oldTeacher = (int) ($item['teacher_id'] ?? 0);
                    break;
                }
            }
            $placed = Repo::placedHours($schoolId, $subjectId);
            if ($teacherId !== null && $teacherId !== $oldTeacher && $placed > 0) {
                $guard = teacher_max_guard($schoolId, $teacherId, $placed, $termId);
                if ($guard['block'] !== null) {
                    throw new RuntimeException($guard['block']);
                }
            }
            $roomId = Repo::ensureRoomId($schoolId, post_string('room_code'));
            $update = Database::pdo()->prepare(
                'UPDATE subjects SET teacher_id = :teacher_id, room_id = :room_id
                 WHERE id = :id AND school_id = :school_id'
            );
            $update->bindValue(':teacher_id', $teacherId, $teacherId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $update->bindValue(':room_id', $roomId, $roomId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $update->bindValue(':id', $subjectId, PDO::PARAM_INT);
            $update->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
            $update->execute();
            $_SESSION['selected_entry'] = $entryId;
            unset($_SESSION['pick']);
        } elseif ($name === 'hours') {
            $planId = (int) ($group['plan_id'] ?? 0);
            $subjectId = (int) $arg;
            $theory = schedule_hour_value(post_string('theory'));
            $practice = schedule_hour_value(post_string('practice'));
            $extra = schedule_hour_value(post_string('extra'));
            $placed = 0;
            foreach ($entries as $entry) {
                if ((int) $entry['subject_id'] === $subjectId) {
                    $placed += (int) $entry['length_periods'];
                }
            }
            if ($theory + $practice < $placed) {
                throw new RuntimeException('ชั่วโมงทฤษฎีกับปฏิบัติรวมกันน้อยกว่าคาบที่ลงไว้แล้ว ลบคาบออกก่อนแล้วค่อยลดชั่วโมง');
            }
            Repo::setSubjectHours($schoolId, $planId, $subjectId, $theory, $practice, $extra);
        } elseif ($name === 'clear_select') {
            unset($_SESSION['selected_entry']);
        } elseif ($name === 'toggle') {
            $statement = Database::pdo()->prepare(
                'UPDATE timetable_entries SET is_manual = IF(is_manual = 1, 0, 1)
                 WHERE id = :id AND school_id = :school_id AND group_id = :group_id'
            );
            $statement->execute(['id' => (int) $arg, 'school_id' => $schoolId, 'group_id' => $groupId]);
            $_SESSION['selected_entry'] = (int) $arg;
        } elseif ($name === 'remove') {
            $statement = Database::pdo()->prepare(
                'DELETE FROM timetable_entries WHERE id = :id AND school_id = :school_id AND group_id = :group_id'
            );
            $statement->execute(['id' => (int) $arg, 'school_id' => $schoolId, 'group_id' => $groupId]);
            unset($_SESSION['selected_entry']);
        } elseif ($name === 'reset') {
            $result = ScheduleEngine::reset($subjects);
            Repo::replaceEntries($schoolId, $groupId, $result['entries']);
            Repo::saveState($groupId, $result['phase'], $result['applied']);
            if (!empty($result['restore'])) {
                Repo::setPolicyType($schoolId, (string) $result['restore'], 'required');
            }
            unset($_SESSION['pick'], $_SESSION['selected_entry']);
        } elseif ($name === 'run') {
            flash(ScheduleActions::runGroup($schoolId, $groupId));
            unset($_SESSION['pick'], $_SESSION['selected_entry']);
        } elseif ($name === 'apply' && $arg === 'A') {
            $result = ScheduleEngine::applyA($subjects);
            if ($result === null) {
                throw new RuntimeException('ใช้ข้อแนะนำนี้ได้กับชุดรายวิชาตัวอย่างเท่านั้น');
            }
            Repo::replaceEntries($schoolId, $groupId, $result['entries']);
            Repo::saveState($groupId, $result['phase'], $result['applied']);
            unset($_SESSION['pick'], $_SESSION['selected_entry']);
        } elseif ($name === 'apply' && $arg === 'B') {
            $result = ScheduleEngine::applyB($subjects, $entries);
            if ($result === null) {
                throw new RuntimeException('ยังลงวิทยาศาสตร์ในช่วงที่เหลือไม่ได้');
            }
            Repo::replaceEntries($schoolId, $groupId, $result['entries']);
            Repo::saveState($groupId, $result['phase'], $result['applied']);
            if (!empty($result['relax'])) {
                Repo::setPolicyType($schoolId, (string) $result['relax'], 'recommended');
            }
            unset($_SESSION['pick'], $_SESSION['selected_entry']);
        }
    } catch (RuntimeException $exception) {
        if ($ajax) {
            schedule_board_json(false, $exception->getMessage());
        }
        flash($exception->getMessage(), 'err');
    }
    if ($ajax) {
        $group = Repo::group($schoolId, $groupId);
        schedule_board_json(true, '', $group ? schedule_board_html($schoolId, $user, $group) : '');
    }
    redirect($back);
}

function schedule_board_model(int $schoolId, array $user, array $group): array
{
    Repo::mergeConsecutive($schoolId, (int) $group['id']);
    $subjects = !empty($group['plan_id']) ? Repo::subjectsForPlan($schoolId, (int) $group['plan_id']) : [];
    $entries = Repo::entries($schoolId, (int) $group['id']);
    $state = Repo::state((int) $group['id']);
    $pick = $_SESSION['pick'] ?? null;
    if (!is_array($pick) || (int) ($pick['group_id'] ?? 0) !== (int) $group['id']) {
        $pick = null;
    }
    $selected = (int) ($_SESSION['selected_entry'] ?? 0);
    $owns = false;
    foreach ($entries as $entry) {
        if ((int) $entry['id'] === $selected) {
            $owns = true;
            break;
        }
    }
    $teachers = Repo::teachers($schoolId);
    $model = ScheduleEngine::present(
        $group,
        $subjects,
        $entries,
        Repo::policies($schoolId),
        (string) $state['phase'],
        $state['applied'] !== null ? (string) $state['applied'] : null,
        $pick,
        $owns ? $selected : null,
        ScheduleActions::canSchedule($user, $schoolId, (int) $group['id']),
        $teachers,
        Repo::teachingHours($schoolId, null, (int) ($group['term_id'] ?? 0)),
        Repo::twinContext($schoolId, (int) $group['id'])
    );
    $model['teacher_names'] = array_map(
        static fn (array $teacher): string => (string) $teacher['name'],
        $teachers
    );
    $model['room_codes'] = array_map(
        static fn (array $room): string => (string) $room['code'],
        Repo::rooms($schoolId)
    );
    return $model;
}

function schedule_board_html(int $schoolId, array $user, array $group): string
{
    $model = schedule_board_model($schoolId, $user, $group);
    ob_start();
    require app_root() . '/views/partials/schedule_board.php';
    return (string) ob_get_clean();
}

function plan_edit_json(bool $ok, string $message, array $extra = []): void
{
    if (!$ok) {
        http_response_code(422);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
function schedule_board_json(bool $ok, string $message, string $html = ''): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message, 'html' => $html], JSON_UNESCAPED_UNICODE);
    exit;
}

function page_policies(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $school = SchoolContext::current();
    render('policies', [
        'currentPage' => 'policies',
        'policies' => $schoolId > 0 ? Repo::policies($schoolId) : [],
        'canEdit' => in_array($user['role'], ['superadmin', 'school_admin'], true) && $schoolId > 0,
        'schoolName' => (string) ($school['name'] ?? ''),
    ]);
}

function page_policies_post(): void
{
    $user = Auth::requireRole(['superadmin', 'school_admin']);
    $schoolId = SchoolContext::id();
    $action = post_string('action');
    $policies = Repo::policies($schoolId);
    $id = (int) post_string('id');
    $index = null;
    foreach ($policies as $i => $policy) {
        if ((int) $policy['id'] === $id) {
            $index = $i;
            break;
        }
    }
    $pdo = Database::pdo();
    if ($action === 'add') {
        $text = post_string('text');
        $type = post_string('policy_type') === 'required' ? 'required' : 'recommended';
        if ($text === '') {
            flash('กรอกข้อความนโยบายก่อนเพิ่ม', 'err');
            redirect('/policies');
        }
        $sort = $policies === [] ? 1 : ((int) $policies[array_key_last($policies)]['sort_order'] + 1);
        $pdo->prepare(
            'INSERT INTO policies (school_id, sort_order, code, short_text, body, policy_type, enabled)
             VALUES (:school_id, :sort_order, NULL, :short_text, :body, :policy_type, 1)'
        )->execute([
            'school_id' => $schoolId,
            'sort_order' => $sort,
            'short_text' => mb_substr($text, 0, 120),
            'body' => $text,
            'policy_type' => $type,
        ]);
        redirect('/policies');
    }
    if ($schoolId <= 0 || $index === null) {
        flash('ไม่พบนโยบายของสถานศึกษานี้', 'err');
        redirect('/policies');
    }
    $text = post_string('text');
    if ($action === 'save') {
        if ($text === '') {
            flash('กรอกข้อความนโยบายก่อนบันทึก', 'err');
            redirect('/policies');
        }
        if (mb_strlen($text) > 2000) {
            flash('ข้อความนโยบายยาวเกิน 2,000 ตัวอักษร', 'err');
            redirect('/policies');
        }
        $pdo->prepare(
            'UPDATE policies SET body = :body, short_text = :short_text WHERE id = :id AND school_id = :school_id'
        )->execute([
            'body' => $text,
            'short_text' => mb_substr($text, 0, 120),
            'id' => $id,
            'school_id' => $schoolId,
        ]);
        flash('บันทึกนโยบายของสถานศึกษานี้แล้ว');
        redirect('/policies');
    }
    if ($text !== '' && mb_strlen($text) <= 2000 && $text !== (string) $policies[$index]['body']) {
        $pdo->prepare(
            'UPDATE policies SET body = :body, short_text = :short_text WHERE id = :id AND school_id = :school_id'
        )->execute([
            'body' => $text,
            'short_text' => mb_substr($text, 0, 120),
            'id' => $id,
            'school_id' => $schoolId,
        ]);
    }
    if ($action === 'required' || $action === 'recommended') {
        $pdo->prepare('UPDATE policies SET policy_type = :type WHERE id = :id AND school_id = :school_id')
            ->execute(['type' => $action, 'id' => $id, 'school_id' => $schoolId]);
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE policies SET enabled = IF(enabled = 1, 0, 1) WHERE id = :id AND school_id = :school_id')
            ->execute(['id' => $id, 'school_id' => $schoolId]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM policies WHERE id = :id AND school_id = :school_id')
            ->execute(['id' => $id, 'school_id' => $schoolId]);
    } elseif ($action === 'up' || $action === 'down') {
        $swap = $action === 'up' ? $index - 1 : $index + 1;
        if (isset($policies[$swap])) {
            $currentOrder = (int) $policies[$index]['sort_order'];
            $otherOrder = (int) $policies[$swap]['sort_order'];
            if ($currentOrder === $otherOrder) {
                $otherOrder = $action === 'up' ? $currentOrder - 1 : $currentOrder + 1;
            }
            $update = $pdo->prepare('UPDATE policies SET sort_order = :sort_order WHERE id = :id AND school_id = :school_id');
            $update->execute(['sort_order' => $otherOrder, 'id' => $policies[$index]['id'], 'school_id' => $schoolId]);
            $update->execute(['sort_order' => $currentOrder, 'id' => $policies[$swap]['id'], 'school_id' => $schoolId]);
        }
    }
    redirect('/policies');
}

function page_skills(): void
{
    Auth::requireUser();
    $user = Auth::user();
    $schoolId = SchoolContext::id();
    $school = SchoolContext::current();
    $ready = $school && (int) $school['skills_ready'] === 1;
    render('skills', [
        'currentPage' => 'skills',
        'ready' => $ready,
        'rows' => $ready && $schoolId > 0 ? Repo::suggestions($schoolId) : Repo::subjects($schoolId),
        'loads' => $schoolId > 0 ? Skills::loads($schoolId, (bool) $ready) : [],
        'canEdit' => $user ? ScheduleActions::canEdit($user) : false,
        'raw' => !$ready,
    ]);
}

function page_skills_post(): void
{
    $user = Auth::requireUser();
    if (!ScheduleActions::canEdit($user)) {
        flash('บทบาทนี้สั่งวิเคราะห์ทักษะครูไม่ได้', 'err');
        redirect('/skills');
    }
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษา', 'err');
        redirect('/skills');
    }
    Skills::analyze($schoolId);
    flash('วิเคราะห์ทักษะครูของสถานศึกษานี้แล้ว');
    redirect('/skills');
}

function page_data(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $tab = (string) ($_GET['tab'] ?? 'teachers');
    if (!in_array($tab, ['teachers', 'groups', 'plans', 'subjects', 'rooms'], true)) {
        $tab = 'teachers';
    }
    $context = in_array($tab, ['groups', 'plans'], true) ? schedule_context($schoolId) : ['terms' => [], 'term' => null];
    $termId = (int) ($context['term']['id'] ?? 0);
    $groups = $schoolId > 0 ? Repo::groups($schoolId) : [];
    if ($tab === 'groups' && $termId > 0) {
        $groups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => (int) $group['term_id'] === $termId
        ));
    }
    $plans = $schoolId > 0 ? Repo::plans($schoolId) : [];
    $planId = (int) ($_GET['plan'] ?? 0);
    $planDetail = $tab === 'plans' && $planId > 0 && $schoolId > 0 ? Repo::planDetail($schoolId, $planId) : null;
    if ($tab === 'plans' && $planDetail === null && $termId > 0) {
        $plans = array_values(array_filter(
            $plans,
            static fn (array $plan): bool => (int) $plan['term_id'] === $termId
        ));
    }
    render('data', [
        'currentPage' => 'data',
        'tab' => $tab,
        'canEdit' => ScheduleActions::canEdit($user) && $schoolId > 0,
        'canAssign' => in_array($user['role'], ['superadmin', 'school_admin'], true) && $schoolId > 0,
        'canEditPlan' => in_array($user['role'], ['superadmin', 'school_admin'], true) && $schoolId > 0,
        'planDetail' => $planDetail,
        'teachers' => $schoolId > 0 ? Repo::teachers($schoolId) : [],
        'teacherAccounts' => $schoolId > 0 ? Repo::teacherAccounts($schoolId) : [],
        'schedulers' => $schoolId > 0 ? Repo::groupSchedulers($schoolId) : [],
        'teachingHours' => $schoolId > 0 ? Repo::teachingHours($schoolId) : [],
        'hourModes' => ScheduleEngine::teacherHourModes($schoolId > 0 ? Repo::policies($schoolId) : []),
        'groups' => $groups,
        'twinSets' => $tab === 'groups' && $schoolId > 0 ? Repo::twinSets($schoolId, $termId) : [],
        'twinSuggestions' => $tab === 'groups' && $schoolId > 0 ? Repo::twinSuggestions($schoolId, $termId) : ['pairs' => [], 'notes' => []],
        'terms' => $context['terms'],
        'term' => $context['term'],
        'plans' => $plans,
        'subjects' => $schoolId > 0 ? Repo::subjects($schoolId) : [],
        'buildings' => $schoolId > 0 ? Repo::buildings($schoolId) : [],
        'rooms' => $schoolId > 0 ? Repo::rooms($schoolId) : [],
    ]);
}

function page_template(): void
{
    Auth::requireUser();
    $tab = (string) ($_GET['tab'] ?? 'teachers');
    if ($tab === 'groups') {
        Spreadsheet::csvDownload(['ชื่อกลุ่ม', 'ระดับ', 'จำนวนผู้เรียน', 'ครูที่ปรึกษา', 'ข้อสังเกต'], [
            ['ปวช.1/1 เทคโนโลยีสารสนเทศ', 'ปวช.', '32', 'ครูธนากร ใจงาม', ''],
        ], 'groups.csv');
    }
    if ($tab === 'subjects') {
        Spreadsheet::csvDownload(['รหัส', 'ชื่อวิชา', 'ท', 'ป', 'น', 'ชื่อแผน'], [
            ['20000-1101', 'ภาษาไทยเพื่ออาชีพ', '1', '0', '1', 'ปวช. เทคโนโลยีสารสนเทศ ชั้นปีที่ 1'],
        ], 'subjects.csv');
    }
    Spreadsheet::csvDownload(['ชื่อ', 'แผนก', 'วุฒิ', 'ทักษะ', 'ชมสูงสุด', 'ชมต่ำสุด'], [
        ['ครูตัวอย่าง', 'เทคโนโลยีสารสนเทศ', 'ป.ตรี', 'Python|Network', '18', '12'],
    ], 'teachers.csv');
}

function page_teacher_skills_export(): void
{
    Auth::requireUser();
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษาสำหรับส่งออกทักษะ', 'err');
        redirect('/data?tab=teachers');
    }
    Spreadsheet::csvDownload(['ชื่อ', 'ทักษะ'], Repo::teacherSkillExportRows($schoolId), 'teacher-skills.csv');
}

function page_buildings_export(): void
{
    Auth::requireUser();
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษาสำหรับส่งออกอาคาร', 'err');
        redirect('/data?tab=rooms');
    }
    Spreadsheet::csvDownload(
        ['ชื่อ', 'ชื่อย่อ', 'วิทยาเขต', 'ละติจูด', 'ลองจิจูด', 'ระยะเดิน'],
        Repo::buildingExportRows($schoolId),
        'buildings.csv'
    );
}

function page_rooms_export(): void
{
    Auth::requireUser();
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษาสำหรับส่งออกห้องเรียน', 'err');
        redirect('/data?tab=rooms');
    }
    Spreadsheet::csvDownload(
        ['รหัสห้อง', 'อาคาร', 'ประเภท', 'ความจุ'],
        Repo::roomExportRows($schoolId),
        'rooms.csv'
    );
}

function page_print(): void
{
    Auth::requireUser();
    $schoolId = SchoolContext::id();
    $kind = (string) ($_GET['kind'] ?? 'group');
    if (!in_array($kind, ['group', 'teacher', 'room'], true)) {
        $kind = 'group';
    }
    $showAll = (string) ($_GET['all'] ?? '') === '1';
    $selectedId = (int) ($_GET['id'] ?? 0);
    $context = schedule_context($schoolId);
    $termId = (int) ($context['term']['id'] ?? 0);
    $lessons = $schoolId > 0 ? Repo::placedLessons($schoolId, $termId > 0 ? $termId : null) : [];
    $policies = $schoolId > 0 ? Repo::policies($schoolId) : [];
    $groups = $schoolId > 0 ? Repo::groups($schoolId) : [];
    if ($termId > 0) {
        $groups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => (int) $group['term_id'] === $termId
        ));
    }
    $options = print_options(
        $kind,
        $groups,
        $schoolId > 0 ? Repo::teachers($schoolId) : [],
        $schoolId > 0 ? Repo::rooms($schoolId) : [],
        $lessons,
        $policies
    );
    if (!$showAll && $selectedId <= 0 && $options !== []) {
        $selectedId = (int) $options[0]['id'];
    }
    $targets = [];
    foreach ($options as $option) {
        if ($showAll || (int) $option['id'] === $selectedId) {
            $targets[] = $option;
        }
    }
    if (!$showAll && $targets === [] && $options !== []) {
        $targets = [$options[0]];
        $selectedId = (int) $options[0]['id'];
    }
    $sheets = [];
    foreach ($targets as $option) {
        $mine = [];
        foreach ($lessons as $lesson) {
            if ((int) ($lesson[$option['key']] ?? 0) === (int) $option['id']) {
                $mine[] = $lesson;
            }
        }
        if ($showAll && $mine === []) {
            continue;
        }
        $sheets[] = [
            'title' => $option['heading'] . ' ' . $option['label'],
            'note' => $option['note'],
            'lunch' => $option['lunch'],
            'grid' => ScheduleEngine::lessonCells($mine),
        ];
    }
    $school = $schoolId > 0 ? SchoolContext::current() : null;
    render('print', [
        'currentPage' => 'print',
        'kind' => $kind,
        'showAll' => $showAll,
        'selectedId' => $selectedId,
        'options' => $options,
        'sheets' => $sheets,
        'terms' => $context['terms'],
        'termId' => $termId,
        'schoolName' => (string) ($school['name'] ?? ''),
        'termLabel' => (string) ($context['term']['label'] ?? ''),
    ]);
}

function print_options(string $kind, array $groups, array $teachers, array $rooms, array $lessons, array $policies): array
{
    $options = [];
    if ($kind === 'teacher') {
        $seen = [];
        foreach ($teachers as $teacher) {
            $id = (int) $teacher['id'];
            $seen[$id] = true;
            $options[] = [
                'id' => $id,
                'key' => 'teacher_id',
                'label' => (string) $teacher['name'],
                'heading' => 'ตารางสอน',
                'note' => (string) $teacher['dept'],
                'lunch' => 0,
            ];
        }
        foreach ($lessons as $lesson) {
            $id = (int) ($lesson['teacher_id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $options[] = [
                'id' => $id,
                'key' => 'teacher_id',
                'label' => (string) ($lesson['teacher_name'] ?? ''),
                'heading' => 'ตารางสอน',
                'note' => '',
                'lunch' => 0,
            ];
        }
        return $options;
    }
    if ($kind === 'room') {
        $seen = [];
        foreach ($rooms as $room) {
            $id = (int) $room['id'];
            $seen[$id] = true;
            $building = (string) ($room['building_name'] ?? '');
            $options[] = [
                'id' => $id,
                'key' => 'room_id',
                'label' => (string) $room['code'] . ($building !== '' ? ' · ' . $building : ''),
                'heading' => 'ตารางใช้ห้องเรียน',
                'note' => trim((string) $room['room_type'] . ($room['capacity'] ? ' · ' . (int) $room['capacity'] . ' ที่นั่ง' : '')),
                'lunch' => 0,
            ];
        }
        foreach ($lessons as $lesson) {
            $id = (int) ($lesson['room_id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $options[] = [
                'id' => $id,
                'key' => 'room_id',
                'label' => (string) ($lesson['room_code'] ?? ''),
                'heading' => 'ตารางใช้ห้องเรียน',
                'note' => '',
                'lunch' => 0,
            ];
        }
        return $options;
    }
    foreach ($groups as $group) {
        $level = (string) $group['level'];
        $lunchState = ScheduleEngine::lunchState($policies, $level);
        $lunch = 0;
        if ($lunchState !== 'off') {
            $lunch = ($level === 'ปวส.' || $level === 'ป.ตรี') ? 5 : 4;
        }
        $options[] = [
            'id' => (int) $group['id'],
            'key' => 'group_id',
            'label' => (string) $group['name'],
            'heading' => 'ตารางเรียน',
            'note' => trim($level . ' · ' . (int) $group['student_count'] . ' คน'),
            'lunch' => $lunch,
        ];
    }
    return $options;
}

function page_rms(): void
{
    $user = Auth::requireRole(['superadmin', 'school_admin', 'scheduler']);
    $schoolId = SchoolContext::id();
    $resource = (string) ($_GET['view'] ?? 'students');
    if (!in_array($resource, ['students', 'groups', 'plans', 'catalog', 'curricula', 'timetables', 'blocks', 'enrollments', 'holidays', 'schedules'], true)) {
        $resource = 'students';
    }
    render('rms', [
        'currentPage' => 'rms',
        'baseUrl' => $schoolId > 0 ? Rms::baseUrl($schoolId) : '',
        'canEditUrl' => in_array($user['role'], ['superadmin', 'school_admin'], true),
        'terms' => $schoolId > 0 ? Rms::terms($schoolId) : [],
        'counts' => $schoolId > 0 ? Rms::counts($schoolId) : [],
        'browse' => $schoolId > 0 ? Rms::browse($schoolId, $resource, trim((string) ($_GET['q'] ?? '')), (int) ($_GET['page'] ?? 1)) : ['resource' => 'students', 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1],
        'query' => trim((string) ($_GET['q'] ?? '')),
    ]);
}

function page_rms_post(): void
{
    $user = Auth::requireRole(['superadmin', 'school_admin', 'scheduler']);
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        rms_fail('ยังไม่มีสถานศึกษา');
    }
    $action = post_string('action');
    try {
        if ($action === 'save_url') {
            if (!in_array($user['role'], ['superadmin', 'school_admin'], true)) {
                throw new RuntimeException('เฉพาะผู้ดูแลสถานศึกษาตั้ง URL ของ RMS ได้');
            }
            Rms::saveBaseUrl($schoolId, post_string('base_url'));
            flash('บันทึก URL ของ RMS สำหรับสถานศึกษานี้แล้ว');
            redirect('/rms');
        }
        if ($action === 'current_term') {
            Rms::setCurrentTerm($schoolId, (int) post_string('term_id'));
            flash('ตั้งภาคเรียนปัจจุบันแล้ว การโหลดตารางเรียนจะใช้ภาคเรียนนี้');
            redirect('/rms');
        }
        if ($action === 'count') {
            $dataset = post_string('dataset');
            if ($dataset === '') {
                $dataset = 'students';
            }
            rms_ok(['total' => Rms::remoteCount($schoolId, $dataset)]);
        }
        if ($action === 'sync' || $action === 'sync_batch') {
            $dataset = post_string('dataset');
            $offset = max(0, (int) post_string('offset'));
            $row = (int) post_string('row');
            if ($row < 1) {
                $row = $dataset === 'schedules' ? 1000 : 100;
            }
            rms_ok(Rms::sync($schoolId, $dataset, $offset, $row));
        }
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    } catch (Throwable $exception) {
        if ($action === 'save_url' || $action === 'current_term') {
            flash($exception->getMessage(), 'err');
            redirect('/rms');
        }
        rms_fail($exception->getMessage());
    }
}

function rms_ok(array $data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function rms_fail(string $message): void
{
    http_response_code(422);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function page_data_post(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $action = post_string('action');
    if ($action === 'add_building' || $action === 'add_room' || $action === 'set_point') {
        if (!ScheduleActions::canEdit($user) || $schoolId <= 0) {
            if ($action === 'set_point' && wants_json()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => 'บทบาทนี้แก้พิกัดอาคารไม่ได้'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            flash('บทบาทนี้เพิ่มอาคารหรือห้องเรียนไม่ได้', 'err');
            redirect('/data?tab=rooms');
        }
        try {
            if ($action === 'set_point') {
                Repo::setBuildingPoint($schoolId, (int) post_string('building_id'), post_string('lat'), post_string('lng'));
                if (wants_json()) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                flash('บันทึกพิกัดอาคารแล้ว');
            } elseif ($action === 'add_building') {
                Repo::addBuilding(
                    $schoolId,
                    post_string('name'),
                    post_string('short_name'),
                    post_string('campus'),
                    post_string('dist_label'),
                    post_string('lat'),
                    post_string('lng')
                );
                flash('เพิ่มอาคารเรียนแล้ว');
            } else {
                Repo::addRoom(
                    $schoolId,
                    post_string('code'),
                    (int) post_string('building_id'),
                    post_string('room_type'),
                    (int) post_string('capacity')
                );
                flash('เพิ่มห้องเรียนแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกไม่สำเร็จ';
            if ($action === 'set_point' && wants_json()) {
                http_response_code(422);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
                exit;
            }
            flash($message, 'err');
        }
        redirect('/data?tab=rooms');
    }
    if ($action === 'add_skill' || $action === 'remove_skill') {
        if (!ScheduleActions::canEdit($user) || $schoolId <= 0) {
            flash('บทบาทนี้แก้ทักษะการสอนไม่ได้', 'err');
            redirect('/data?tab=teachers');
        }
        try {
            $teacherId = (int) post_string('teacher_id');
            if ($action === 'add_skill') {
                Repo::addTeacherSkill($schoolId, $teacherId, post_string('skill'));
                flash('เพิ่มทักษะการสอนแล้ว');
            } else {
                Repo::removeTeacherSkill($schoolId, $teacherId, post_string('skill'));
                flash('ลบทักษะการสอนแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกทักษะไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect('/data?tab=teachers');
    }
    if ($action === 'set_hours') {
        if (!ScheduleActions::canEdit($user) || $schoolId <= 0) {
            flash('บทบาทนี้แก้ชั่วโมงสอนของครูไม่ได้', 'err');
            redirect('/data?tab=teachers');
        }
        try {
            Repo::setTeacherHours(
                $schoolId,
                (int) post_string('teacher_id'),
                (int) post_string('min_hours'),
                (int) post_string('max_hours')
            );
            flash('บันทึกชั่วโมงสอนของครูแล้ว');
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกชั่วโมงไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect('/data?tab=teachers');
    }
    if ($action === 'assign_scheduler' || $action === 'remove_scheduler') {
        if (!in_array($user['role'], ['superadmin', 'school_admin'], true) || $schoolId <= 0) {
            flash('เฉพาะผู้ดูแลสถานศึกษามอบหมายผู้จัดตารางได้', 'err');
            redirect('/data?tab=groups');
        }
        try {
            $groupId = (int) post_string('group_id');
            $teacherId = (int) post_string('teacher_id');
            if ($action === 'assign_scheduler') {
                Repo::assignGroupScheduler($schoolId, $groupId, $teacherId);
                flash('มอบหมายผู้จัดตารางแล้ว');
            } else {
                Repo::removeGroupScheduler($schoolId, $groupId, $teacherId);
                flash('ยกเลิกการมอบหมายแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกการมอบหมายไม่สำเร็จ';
            flash($message, 'err');
        }
        $returnTerm = (int) post_string('term_id');
        if ($returnTerm <= 0) {
            $assignedGroup = Repo::group($schoolId, (int) post_string('group_id'));
            $returnTerm = (int) ($assignedGroup['term_id'] ?? 0);
        }
        redirect('/data?tab=groups' . ($returnTerm > 0 ? '&term=' . $returnTerm : ''));
    }
    if ($action === 'create_teacher_user') {
        if (!in_array($user['role'], ['superadmin', 'school_admin'], true) || $schoolId <= 0) {
            flash('เฉพาะผู้ดูแลสถานศึกษาสร้างบัญชีจากข้อมูลครูได้', 'err');
            redirect('/data?tab=teachers');
        }
        try {
            Repo::createTeacherUser(
                $schoolId,
                (int) post_string('teacher_id'),
                (string) ($_POST['password'] ?? ''),
                post_string('citizen_id')
            );
            flash('สร้างบัญชีครูแล้ว จัดตารางได้เฉพาะกลุ่มที่มอบหมายไว้');
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'สร้างบัญชีไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect('/data?tab=teachers');
    }
    if (in_array($action, ['save_twin_set', 'delete_twin_set'], true)) {
        $termId = (int) post_string('term_id');
        $back = '/data?tab=groups' . ($termId > 0 ? '&term=' . $termId : '');
        if (!in_array($user['role'], ['superadmin', 'school_admin'], true) || $schoolId <= 0) {
            flash('เฉพาะผู้ดูแลสถานศึกษาจัดกลุ่มแฝดได้', 'err');
            redirect($back);
        }
        try {
            if ($action === 'save_twin_set') {
                $posted = $_POST['group_ids'] ?? [];
                $posted = is_array($posted) ? $posted : [];
                Repo::saveTwinSet($schoolId, $termId, $posted);
                $report = Repo::twinReport($schoolId, $posted);
                if (!$report['alike']) {
                    flash('จับคู่แล้ว แต่แผนการเรียนไม่เหมือนกัน ' . $report['detail'] . ' ควรเลิกจับกลุ่มนี้', 'err');
                } else {
                    flash('จับกลุ่มแฝดแล้ว ตอนจัดตารางคาบที่กลุ่มแฝดลงไว้จะเป็นสีเหลือง');
                }
            } else {
                Repo::deleteTwinSet($schoolId, (int) post_string('twin_id'));
                flash('เลิกจับกลุ่มแฝดแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกกลุ่มแฝดไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect($back);
    }
    if (in_array($action, ['add_degree_group', 'set_degree_count', 'delete_degree_group'], true)) {
        $termId = (int) post_string('term_id');
        $back = '/data?tab=groups' . ($termId > 0 ? '&term=' . $termId : '');
        if (!in_array($user['role'], ['superadmin', 'school_admin'], true) || $schoolId <= 0) {
            flash('เฉพาะผู้ดูแลสถานศึกษาเพิ่มกลุ่มปริญญาตรีได้', 'err');
            redirect($back);
        }
        try {
            $countText = post_string('student_count');
            if ($action === 'add_degree_group') {
                if (!preg_match('/^\d+$/', $countText)) {
                    throw new RuntimeException('จำนวนผู้เรียนต้องเป็นจำนวนเต็ม');
                }
                Repo::addDegreeGroup($schoolId, $termId, post_string('name'), (int) $countText);
                flash('เพิ่มกลุ่มปริญญาตรีแล้ว');
            } elseif ($action === 'set_degree_count') {
                if (!preg_match('/^\d+$/', $countText)) {
                    throw new RuntimeException('จำนวนผู้เรียนต้องเป็นจำนวนเต็ม');
                }
                Repo::setDegreeGroupCount($schoolId, (int) post_string('group_id'), (int) $countText);
                flash('บันทึกจำนวนผู้เรียนแล้ว');
            } else {
                Repo::deleteDegreeGroup($schoolId, (int) post_string('group_id'));
                flash('ลบกลุ่มปริญญาตรีแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกกลุ่มปริญญาตรีไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect($back);
    }
    if (in_array($action, ['save_plan_subject', 'delete_plan_subject', 'add_plan_subject', 'rename_plan', 'create_group_plan'], true)) {
        $planId = (int) post_string('plan_id');
        $back = '/data?tab=plans' . ($planId > 0 ? '&plan=' . $planId : '');
        if (!in_array($user['role'], ['superadmin', 'school_admin'], true) || $schoolId <= 0) {
            if (wants_json()) {
                plan_edit_json(false, 'เฉพาะผู้ดูแลสถานศึกษาแก้ไขแผนการเรียนได้');
            }
            flash('เฉพาะผู้ดูแลสถานศึกษาแก้ไขแผนการเรียนได้', 'err');
            redirect('/data?tab=plans');
        }
        try {
            if ($action === 'create_group_plan') {
                $planId = Repo::ensureGroupPlan($schoolId, (int) post_string('group_id'));
                flash('สร้างแผนการเรียนของกลุ่มนี้แล้ว');
                redirect('/data?tab=plans&plan=' . $planId);
            }
            if ($action === 'rename_plan') {
                Repo::renamePlan($schoolId, $planId, post_string('name'));
                flash('บันทึกชื่อแผนการเรียนแล้ว');
            } elseif ($action === 'add_plan_subject') {
                $subjectId = Repo::addPlanSubject(
                    $schoolId,
                    $planId,
                    post_string('code'),
                    post_string('name'),
                    schedule_hour_value(post_string('theory')),
                    schedule_hour_value(post_string('practice')),
                    schedule_hour_value(post_string('extra'))
                );
                if (wants_json()) {
                    plan_edit_json(true, 'เพิ่มรายวิชาในแผนแล้ว', ['subject_id' => $subjectId]);
                }
                flash('เพิ่มรายวิชาในแผนแล้ว');
            } elseif ($action === 'save_plan_subject') {
                Repo::updatePlanSubject(
                    $schoolId,
                    $planId,
                    (int) post_string('subject_id'),
                    post_string('code'),
                    post_string('name'),
                    schedule_hour_value(post_string('theory')),
                    schedule_hour_value(post_string('practice')),
                    schedule_hour_value(post_string('extra'))
                );
                if (wants_json()) {
                    plan_edit_json(true, 'บันทึกรายวิชาในแผนแล้ว');
                }
                flash('บันทึกรายวิชาในแผนแล้ว');
            } else {
                $removed = Repo::deletePlanSubject($schoolId, $planId, (int) post_string('subject_id'));
                $message = $removed > 0
                    ? 'ลบรายวิชาแล้ว และนำ ' . $removed . ' คาบที่ลงไว้ของวิชานี้ออกจากตาราง'
                    : 'ลบรายวิชาออกจากแผนแล้ว';
                if (wants_json()) {
                    plan_edit_json(true, $message);
                }
                flash($message);
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'บันทึกแผนการเรียนไม่สำเร็จ';
            if (wants_json()) {
                plan_edit_json(false, $message);
            }
            flash($message, 'err');
        }
        redirect($back);
    }
    if ($action === 'import_skills') {
        if (!ScheduleActions::canEdit($user) || $schoolId <= 0) {
            flash('บทบาทนี้นำเข้าทักษะครูไม่ได้', 'err');
            redirect('/data?tab=teachers');
        }
        try {
            $result = Repo::importTeacherSkillRows($schoolId, uploaded_rows());
            $message = 'นำเข้าทักษะของครู ' . $result['imported'] . ' คนแล้ว';
            if ($result['skipped'] !== []) {
                $shown = array_slice($result['skipped'], 0, 8);
                $message .= ' ข้าม ' . count($result['skipped']) . ' คนที่ไม่พบในระบบ: ' . implode(', ', $shown);
                $more = count($result['skipped']) - count($shown);
                if ($more > 0) {
                    $message .= ' และอีก ' . $more . ' คน';
                }
            }
            flash($message, $result['imported'] === 0 ? 'err' : 'ok');
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'นำเข้าทักษะไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect('/data?tab=teachers');
    }
    if ($action === 'import_buildings' || $action === 'import_rooms') {
        if (!ScheduleActions::canEdit($user) || $schoolId <= 0) {
            flash('บทบาทนี้นำเข้าอาคารหรือห้องเรียนไม่ได้', 'err');
            redirect('/data?tab=rooms');
        }
        try {
            $rows = uploaded_rows();
            $kind = Repo::facilityFileKind($rows);
            if ($kind === '') {
                $kind = $action === 'import_buildings' ? 'buildings' : 'rooms';
            }
            if ($kind === 'buildings') {
                $count = Repo::importBuildingRows($schoolId, $rows);
                flash('นำเข้าอาคาร ' . $count . ' หลังแล้ว');
            } else {
                $count = Repo::importRoomRows($schoolId, $rows);
                flash('นำเข้าห้องเรียน ' . $count . ' ห้องแล้ว');
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'นำเข้าไม่สำเร็จ';
            flash($message, 'err');
        }
        redirect('/data?tab=rooms');
    }
    $tab = post_string('tab');
    if (!in_array($tab, ['teachers', 'groups', 'subjects'], true)) {
        flash('นำเข้าได้เฉพาะครู กลุ่มผู้เรียน และรายวิชา', 'err');
        redirect('/data');
    }
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษาสำหรับนำเข้าข้อมูล', 'err');
        redirect('/data?tab=' . $tab);
    }
    try {
        $rows = uploaded_rows();
        $count = match ($tab) {
            'groups' => import_groups($schoolId, $rows),
            'subjects' => import_subjects($schoolId, $rows),
            default => import_teachers($schoolId, $rows),
        };
        flash('นำเข้า ' . $count . ' แถวเข้าสถานศึกษาปัจจุบันแล้ว');
    } catch (Throwable $exception) {
        $message = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'นำเข้าไม่สำเร็จ ตรวจรูปแบบไฟล์หรือข้อมูลซ้ำ';
        flash($message, 'err');
    }
    redirect('/data?tab=' . $tab);
}

function page_ai(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $settings = $schoolId > 0 ? Repo::ai($schoolId) : null;
    if ($settings === null && $schoolId > 0) {
        insert_default_ai(Database::pdo(), $schoolId);
        $settings = Repo::ai($schoolId);
    }
    $preferred = (int) ($_SESSION['ai_model_id'][$schoolId] ?? 0);
    render('ai', [
        'currentPage' => 'ai',
        'settings' => $settings,
        'catalog' => ai_catalog(),
        'credentials' => $schoolId > 0 ? Repo::credentialsWithModels($schoolId) : [],
        'enabledModels' => $schoolId > 0 ? Repo::enabledModels($schoolId) : [],
        'workingModelId' => $schoolId > 0 ? (int) (Repo::resolveModel($schoolId, $preferred)['id'] ?? 0) : 0,
        'canEdit' => in_array($user['role'], ['superadmin', 'school_admin'], true) && $schoolId > 0,
    ]);
}

function page_ai_post(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    if ($schoolId <= 0) {
        flash('ยังไม่มีสถานศึกษาให้ตั้งค่า', 'err');
        redirect('/ai');
    }
    $action = post_string('action');
    if ($action === 'pick') {
        ai_pick_model($schoolId);
        redirect('/ai');
    }
    if (!in_array($user['role'], ['superadmin', 'school_admin'], true)) {
        http_response_code(403);
        render('forbidden', ['currentPage' => ''], 'app');
        exit;
    }
    if (Repo::ai($schoolId) === null) {
        insert_default_ai(Database::pdo(), $schoolId);
    }
    try {
        if ($action === 'add') {
            ai_add_credential($user, $schoolId);
        } elseif ($action === 'refresh') {
            ai_refresh_credential($user, $schoolId);
        } elseif ($action === 'models') {
            ai_save_models($schoolId);
        } elseif ($action === 'delete') {
            Repo::deleteCredential($schoolId, (int) post_string('credential_id'));
            flash('ลบชุด API Key แล้ว');
        } elseif ($action === 'permissions') {
            Repo::saveAiPermissions($schoolId, [
                'allow_act' => isset($_POST['allow_act']) ? 1 : 0,
                'require_confirm' => isset($_POST['require_confirm']) ? 1 : 0,
                'suggest_contact' => isset($_POST['suggest_contact']) ? 1 : 0,
                'log_actions' => isset($_POST['log_actions']) ? 1 : 0,
            ], (int) $user['id']);
            flash('บันทึกขอบเขตการทำงานของผู้ช่วย AI แล้ว');
        } else {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'err');
    }
    redirect('/ai');
}

function ai_pick_model(int $schoolId): void
{
    $modelId = (int) post_string('model_id');
    $chosen = null;
    foreach (Repo::enabledModels($schoolId) as $model) {
        if ((int) $model['id'] === $modelId) {
            $chosen = $model;
            break;
        }
    }
    if ($chosen === null) {
        flash('โมเดลนี้ไม่ได้เปิดใช้งาน', 'err');
        return;
    }
    $_SESSION['ai_model_id'][$schoolId] = $modelId;
    flash('ใช้โมเดล ' . $chosen['model_name'] . ' ในการทำงาน');
}

function ai_add_credential(array $user, int $schoolId): void
{
    $parsed = ai_connection_from_post();
    $apiKey = trim((string) ($_POST['api_key'] ?? ''));
    if ($apiKey === '' || strlen($apiKey) > 500) {
        throw new RuntimeException('กรอก API Key');
    }
    $result = AiClient::listModels($parsed, $apiKey);
    $label = post_string('label');
    if ($label === '') {
        $label = $parsed['name'];
    }
    if (mb_strlen($label) > 128) {
        throw new RuntimeException('ชื่อชุดคีย์ยาวเกิน 128 ตัว');
    }
    Repo::addCredential($schoolId, [
        'label' => $label,
        'provider' => $parsed['provider'],
        'base_url' => $parsed['base_url'],
        'api_key_encrypted' => Crypto::encrypt($apiKey),
        'api_key_hint' => Crypto::hint($apiKey),
        'last_test_ms' => (int) $result['ms'],
    ], $result['models'], (int) $user['id']);
    Repo::logAi($schoolId, (int) $user['id'], 'test', 'เพิ่มชุดคีย์ ' . $label);
    flash($result['message'] . ' เลือกเปิดใช้งานโมเดลจากรายการของชุดนี้');
}

function ai_refresh_credential(array $user, int $schoolId): void
{
    $credentialId = (int) post_string('credential_id');
    $credential = Repo::credentialKey($schoolId, $credentialId);
    if ($credential === null || empty($credential['api_key_encrypted'])) {
        throw new RuntimeException('ไม่พบชุด API Key ของสถานศึกษานี้');
    }
    try {
        $result = AiClient::listModels($credential, Crypto::decrypt((string) $credential['api_key_encrypted']));
        Repo::refreshModels($schoolId, $credentialId, $result['models'], (int) $result['ms']);
        Repo::logAi($schoolId, (int) $user['id'], 'test', 'ดึงรายการโมเดลของ ' . $credential['label']);
        flash($result['message']);
    } catch (Throwable $exception) {
        Repo::markCredentialTest($schoolId, $credentialId, 'fail', null);
        throw $exception;
    }
}

function ai_save_models(int $schoolId): void
{
    $credentialId = (int) post_string('credential_id');
    $enabled = $_POST['enabled'] ?? [];
    if (!is_array($enabled)) {
        $enabled = [];
    }
    Repo::saveEnabledModels($schoolId, $credentialId, $enabled, (int) post_string('default_model'));
    flash('บันทึกโมเดลที่เปิดใช้งานแล้ว');
}

function ai_connection_from_post(): array
{
    $catalog = ai_catalog();
    $provider = post_string('provider');
    if (!isset($catalog[$provider])) {
        throw new RuntimeException('ผู้ให้บริการไม่ถูกต้อง');
    }
    $baseUrl = post_string('base_url');
    if (!preg_match('#^https?://#i', $baseUrl) || mb_strlen($baseUrl) > 255) {
        throw new RuntimeException('Base URL ต้องขึ้นต้นด้วย http:// หรือ https://');
    }
    return [
        'provider' => $provider,
        'base_url' => $baseUrl,
        'name' => $catalog[$provider]['name'],
    ];
}

function page_chat(): void
{
    $user = Auth::requireUser();
    $schoolId = SchoolContext::id();
    $text = post_string('message');
    if (!isset($_SESSION['chat'][$schoolId])) {
        $_SESSION['chat'][$schoolId] = [[
            'ai' => 1,
            'text' => 'สวัสดีค่ะ ฉันเป็นผู้ช่วย AI ของระบบจัดตาราง สั่งงานได้ตามสิทธิ์ของบทบาทคุณ เช่น จัดตารางอัตโนมัติ วิเคราะห์ทักษะครู หรือตรวจขนาดห้องเรียน',
        ]];
    }
    $modelId = (int) post_string('model_id');
    if ($modelId > 0) {
        foreach (Repo::enabledModels($schoolId) as $model) {
            if ((int) $model['id'] === $modelId) {
                $_SESSION['ai_model_id'][$schoolId] = $modelId;
                break;
            }
        }
    }
    $reply = $text === '' ? 'พิมพ์สิ่งที่ต้องการได้เลยค่ะ' : Assistant::reply($user, $schoolId, $text);
    if ($text !== '') {
        $_SESSION['chat'][$schoolId][] = ['me' => 1, 'text' => $text];
        $_SESSION['chat'][$schoolId][] = ['ai' => 1, 'text' => $reply];
        $_SESSION['chat'][$schoolId] = array_slice($_SESSION['chat'][$schoolId], -40);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}

function page_schools(): void
{
    Auth::requireRole(['superadmin']);
    $schools = [];
    foreach (Repo::schools() as $school) {
        $school['users'] = Repo::usersForSchool((int) $school['id']);
        $schools[] = $school;
    }
    render('schools', ['currentPage' => 'schools', 'rows' => $schools]);
}

function page_schools_post(): void
{
    Auth::requireRole(['superadmin']);
    $action = post_string('action');
    try {
        if ($action === 'create') {
            $name = post_string('name');
            if ($name === '') {
                throw new RuntimeException('กรอกชื่อสถานศึกษา');
            }
            $id = create_school(Database::pdo(), $name);
            SchoolContext::switchTo($id);
            flash('เพิ่มสถานศึกษาและค่าเริ่มต้นของ AI แยกจากแห่งอื่นแล้ว');
        } elseif ($action === 'user') {
            $schoolId = (int) post_string('school_id');
            if (!SchoolContext::exists($schoolId)) {
                throw new RuntimeException('ไม่พบสถานศึกษา');
            }
            $username = post_string('username');
            $password = (string) ($_POST['password'] ?? '');
            $display = post_string('display_name');
            $role = post_string('role');
            if (!valid_username($username)) {
                throw new RuntimeException(username_error());
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัว');
            }
            if ($display === '') {
                throw new RuntimeException('กรอกชื่อที่แสดง');
            }
            if (!in_array($role, ['school_admin', 'scheduler', 'teacher'], true)) {
                throw new RuntimeException('บทบาทไม่ถูกต้อง');
            }
            Auth::insert(Database::pdo(), $schoolId, null, $username, $password, $display, $role);
            flash('เพิ่มผู้ใช้ให้สถานศึกษาแล้ว');
        }
    } catch (Throwable $exception) {
        $message = $exception instanceof PDOException ? 'บันทึกไม่สำเร็จ ชื่อผู้ใช้อาจซ้ำ' : $exception->getMessage();
        flash($message, 'err');
    }
    redirect('/schools');
}

function page_migrations(): void
{
    Auth::requireRole(['superadmin']);
    render('migrations', [
        'currentPage' => 'migrations',
        'items' => Migrator::catalog(Database::pdo()),
    ]);
}

function page_migrations_post(): void
{
    Auth::requireRole(['superadmin']);
    $action = post_string('action');
    try {
        $pdo = Database::pdo();
        if ($action === 'all') {
            $ran = Migrator::run($pdo);
        } else {
            $version = basename(post_string('version'));
            $ran = Migrator::run($pdo, $version);
        }
        flash($ran === [] ? 'ไม่มีรายการที่ต้องปรับปรุง' : 'ปรับปรุงแล้ว ' . count($ran) . ' รายการ');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'err');
    }
    redirect('/migrations');
}

function uploaded_rows(): array
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('เลือกไฟล์ CSV หรือ Excel .xlsx ก่อนนำเข้า');
    }
    if ((int) $file['size'] > 2000000) {
        throw new RuntimeException('ไฟล์ใหญ่เกิน 2 MB');
    }
    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $target = app_root() . '/storage/uploads/' . bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('บันทึกไฟล์อัปโหลดไม่ได้ ตรวจสิทธิ์โฟลเดอร์ storage/uploads');
    }
    try {
        return Spreadsheet::rows($target, $extension);
    } finally {
        @unlink($target);
    }
}

function import_teachers(int $schoolId, array $rows): int
{
    if (isset($rows[0][0]) && mb_stripos((string) $rows[0][0], 'ชื่อ') !== false) {
        array_shift($rows);
    }
    $pdo = Database::pdo();
    $insert = $pdo->prepare(
        'INSERT INTO teachers (school_id, name, dept, degree, max_hours, min_hours)
         VALUES (:school_id, :name, :dept, :degree, :max_hours, :min_hours)'
    );
    $skill = $pdo->prepare('INSERT INTO teacher_skills (teacher_id, skill) VALUES (:teacher_id, :skill)');
    $count = 0;
    foreach ($rows as $row) {
        $name = trim((string) ($row[0] ?? ''));
        if ($name === '') {
            continue;
        }
        $maxHours = max(1, (int) ($row[4] ?? 18));
        $minHours = min($maxHours, max(0, (int) ($row[5] ?? 0)));
        $insert->execute([
            'school_id' => $schoolId,
            'name' => $name,
            'dept' => trim((string) ($row[1] ?? '')),
            'degree' => trim((string) ($row[2] ?? '')),
            'max_hours' => $maxHours,
            'min_hours' => $minHours,
        ]);
        $teacherId = (int) $pdo->lastInsertId();
        foreach (preg_split('/[|,]/', (string) ($row[3] ?? '')) ?: [] as $item) {
            $item = trim($item);
            if ($item !== '') {
                $skill->execute(['teacher_id' => $teacherId, 'skill' => $item]);
            }
        }
        $count++;
    }
    return $count;
}

function import_groups(int $schoolId, array $rows): int
{
    if (isset($rows[0][0]) && mb_stripos((string) $rows[0][0], 'ชื่อ') !== false) {
        array_shift($rows);
    }
    $term = Repo::term($schoolId);
    if ($term === null) {
        throw new RuntimeException('สถานศึกษานี้ยังไม่มีภาคเรียน');
    }
    $teachers = Repo::teachers($schoolId);
    $plans = Repo::plans($schoolId);
    $planId = $plans[0]['id'] ?? null;
    $insert = Database::pdo()->prepare(
        'INSERT INTO student_groups (school_id, term_id, plan_id, name, level, student_count, advisor_id, note, note_tone)
         VALUES (:school_id, :term_id, :plan_id, :name, :level, :student_count, :advisor_id, :note, :note_tone)'
    );
    $count = 0;
    foreach ($rows as $row) {
        $name = trim((string) ($row[0] ?? ''));
        if ($name === '') {
            continue;
        }
        $advisor = null;
        $advisorName = trim((string) ($row[3] ?? ''));
        foreach ($teachers as $teacher) {
            if ($teacher['name'] === $advisorName) {
                $advisor = (int) $teacher['id'];
                break;
            }
        }
        $note = trim((string) ($row[4] ?? ''));
        $insert->execute([
            'school_id' => $schoolId,
            'term_id' => $term['id'],
            'plan_id' => $planId,
            'name' => $name,
            'level' => trim((string) ($row[1] ?? '')),
            'student_count' => max(0, (int) ($row[2] ?? 0)),
            'advisor_id' => $advisor,
            'note' => $note !== '' ? $note : null,
            'note_tone' => str_contains($note, 'เล็กกว่า') ? 'danger' : ($note !== '' ? 'muted' : null),
        ]);
        $count++;
    }
    return $count;
}

function import_subjects(int $schoolId, array $rows): int
{
    if (isset($rows[0][0]) && mb_stripos((string) $rows[0][0], 'รหัส') !== false) {
        array_shift($rows);
    }
    $plans = Repo::plans($schoolId);
    if ($plans === []) {
        throw new RuntimeException('ยังไม่มีแผนการเรียนสำหรับผูกวิชา');
    }
    $insert = Database::pdo()->prepare(
        'INSERT INTO subjects (school_id, plan_id, code, name, theory, practice, extra, sort_order)
         VALUES (:school_id, :plan_id, :code, :name, :theory, :practice, :extra, :sort_order)'
    );
    $count = 0;
    foreach ($rows as $index => $row) {
        $code = trim((string) ($row[0] ?? ''));
        $name = trim((string) ($row[1] ?? ''));
        if ($code === '' || $name === '') {
            continue;
        }
        $planId = (int) $plans[0]['id'];
        $planName = trim((string) ($row[5] ?? ''));
        foreach ($plans as $plan) {
            if ($planName !== '' && $plan['name'] === $planName) {
                $planId = (int) $plan['id'];
                break;
            }
        }
        $insert->execute([
            'school_id' => $schoolId,
            'plan_id' => $planId,
            'code' => $code,
            'name' => $name,
            'theory' => max(0, (int) ($row[2] ?? 0)),
            'practice' => max(0, (int) ($row[3] ?? 0)),
            'extra' => max(0, (int) ($row[4] ?? 0)),
            'sort_order' => $index + 1,
        ]);
        $count++;
    }
    return $count;
}
