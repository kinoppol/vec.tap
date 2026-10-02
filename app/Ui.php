<?php
declare(strict_types=1);

function app_shell(string $currentPage): array
{
    $user = Auth::user();
    $schoolId = $user ? SchoolContext::id() : 0;
    $school = $schoolId > 0 ? SchoolContext::current() : null;
    $settings = $schoolId > 0 ? Repo::ai($schoolId) : null;
    $catalog = ai_catalog();
    $preferred = (int) ($_SESSION['ai_model_id'][$schoolId] ?? 0);
    $working = $schoolId > 0 ? Repo::resolveModel($schoolId, $preferred) : null;
    $providerKey = is_array($working) ? (string) $working['provider'] : 'openrouter';
    $provider = $catalog[$providerKey] ?? $catalog['openrouter'];
    $connected = is_array($working) && ($working['last_test_status'] ?? '') === 'ok';
    $aiModels = $schoolId > 0 ? Repo::enabledModels($schoolId) : [];
    $term = $schoolId > 0 ? Repo::term($schoolId) : null;
    if ($schoolId > 0 && in_array($currentPage, ['schedule', 'print'], true)) {
        $pickedTerm = (int) ($_SESSION['schedule_term'][$schoolId] ?? 0);
        foreach (Repo::terms($schoolId) as $item) {
            if ((int) $item['id'] === $pickedTerm) {
                $term = $item;
                break;
            }
        }
    }

    $item = static function (string $key, string $label, string $icon) use ($currentPage): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'href' => url('/' . $key),
            'active' => $currentPage === $key,
        ];
    };

    $main = [
        $item('dashboard', 'แดชบอร์ด', 'bi-grid-1x2'),
        $item('schedule', 'จัดตารางเรียน', 'bi-calendar3-week'),
        $item('print', 'พิมพ์ตาราง', 'bi-printer'),
        $item('policies', 'นโยบายการจัดตาราง', 'bi-sliders'),
        $item('skills', 'วิเคราะห์ทักษะครู', 'bi-diagram-3'),
    ];
    $system = [
        $item('data', 'ข้อมูลพื้นฐาน', 'bi-database'),
    ];
    if ($user && in_array($user['role'], ['superadmin', 'school_admin', 'scheduler'], true)) {
        $system[] = $item('rms', 'นำเข้าจาก RMS', 'bi-cloud-download');
    }
    $system[] = $item('ai', 'ตั้งค่าผู้ช่วย AI', 'bi-cpu');
    if ($user && $user['role'] === 'superadmin') {
        $system[] = $item('schools', 'สถานศึกษา', 'bi-buildings');
        $system[] = $item('migrations', 'ปรับปรุงฐานข้อมูล', 'bi-arrow-repeat');
    }

    $prompts = match ($user['role'] ?? '') {
        'teacher' => ['สรุปตารางสอนของฉัน', 'ขอจัดตารางใหม่', 'ตรวจขนาดห้อง'],
        'school_admin', 'superadmin' => ['จัดตารางอัตโนมัติ', 'เพิ่มนโยบาย ครูสอนไม่เกิน 6 ชม./วัน', 'วิเคราะห์ทักษะครู'],
        default => ['จัดตารางอัตโนมัติ', 'วิเคราะห์ทักษะครู', 'แก้นโยบายข้อ 4'],
    };

    return [
        'user' => $user,
        'roleLabel' => $user ? role_label((string) $user['role']) : '',
        'initial' => $user ? user_initial((string) $user['display_name']) : '',
        'school' => $school,
        'schools' => $user && $user['role'] === 'superadmin' ? Repo::schools() : ($school ? [$school] : []),
        'canSwitch' => SchoolContext::canSwitch(),
        'termLabel' => $term['label'] ?? 'ยังไม่มีภาคเรียน',
        'providerName' => is_array($working) ? (string) $working['label'] : $provider['name'],
        'aiConnected' => $connected,
        'aiModels' => $aiModels,
        'aiModelId' => (int) ($working['id'] ?? 0),
        'navMain' => $main,
        'navSys' => $system,
        'prompts' => $prompts,
        'chat' => $_SESSION['chat'][$schoolId] ?? [[
            'ai' => 1,
            'text' => 'สวัสดีค่ะ ฉันเป็นผู้ช่วย AI ของระบบจัดตาราง สั่งงานได้ตามสิทธิ์ของบทบาทคุณ เช่น จัดตารางอัตโนมัติ วิเคราะห์ทักษะครู หรือตรวจขนาดห้องเรียน',
        ]],
        'flash' => flash(),
        'pageTitle' => [
            'dashboard' => 'แดชบอร์ด',
            'schedule' => 'จัดตารางเรียน',
            'print' => 'พิมพ์ตาราง',
            'policies' => 'นโยบายการจัดตาราง',
            'skills' => 'วิเคราะห์ทักษะครูและแบ่งรายวิชา',
            'data' => 'ข้อมูลพื้นฐาน',
            'rms' => 'นำเข้าจาก RMS',
            'ai' => 'ตั้งค่าผู้ช่วย AI',
            'schools' => 'สถานศึกษา',
            'migrations' => 'ปรับปรุงฐานข้อมูล',
        ][$currentPage] ?? '',
    ];
}
