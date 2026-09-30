<?php
declare(strict_types=1);

final class Assistant
{
    public static function reply(array $user, int $schoolId, string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'พิมพ์สิ่งที่ต้องการได้เลยค่ะ';
        }
        $settings = Repo::ai($schoolId) ?? [];
        $local = self::local($user, $schoolId, $settings, $text);
        if ($local !== null) {
            self::maybeLog($schoolId, (int) $user['id'], $settings, 'chat', $text);
            return $local;
        }
        if (self::ready($settings)) {
            try {
                $key = Crypto::decrypt((string) $settings['api_key_encrypted']);
                $history = [];
                foreach ($_SESSION['chat'][$schoolId] ?? [] as $message) {
                    $history[] = [
                        'role' => !empty($message['me']) ? 'user' : 'assistant',
                        'content' => (string) $message['text'],
                    ];
                }
                $history[] = ['role' => 'user', 'content' => $text];
                $answer = AiClient::chat($settings, $key, self::systemPrompt($user, $schoolId, $settings), array_slice($history, -12));
                self::maybeLog($schoolId, (int) $user['id'], $settings, 'chat-api', $text);
                return trim($answer);
            } catch (Throwable $exception) {
                app_log('AI chat: ' . $exception->getMessage());
            }
        }
        self::maybeLog($schoolId, (int) $user['id'], $settings, 'chat', $text);
        return self::fallback($user, $text);
    }

    private static function local(array $user, int $schoolId, array $settings, string $text): ?string
    {
        $role = (string) $user['role'];
        $canSchedule = in_array($role, ['superadmin', 'school_admin', 'scheduler'], true);
        $canAdmin = in_array($role, ['superadmin', 'school_admin'], true);
        $allowAct = !isset($settings['allow_act']) || (int) $settings['allow_act'] === 1;
        $confirm = isset($settings['require_confirm']) && (int) $settings['require_confirm'] === 1;

        if (preg_match('/^ยืนยัน$/u', $text) && !empty($_SESSION['pending_action']) && is_array($_SESSION['pending_action'])) {
            if (!$allowAct) {
                unset($_SESSION['pending_action']);
                return 'ปิดไว้ไม่ให้ผู้ช่วยดำเนินการแทนผู้ใช้ คำสั่งนี้จึงไม่ถูกบันทึกค่ะ';
            }
            $pending = $_SESSION['pending_action'];
            unset($_SESSION['pending_action']);
            if (($pending['type'] ?? '') === 'policy' && $canAdmin) {
                self::addPolicy($schoolId, (string) $pending['text']);
                return 'เพิ่มนโยบาย “' . $pending['text'] . '” เป็นข้อแนะนำลำดับสุดท้ายแล้ว ปรับเป็นข้อบังคับหรือเลื่อนลำดับได้ในหน้านโยบายค่ะ';
            }
            if (($pending['type'] ?? '') === 'schedule' && $canSchedule) {
                return ScheduleActions::runGroup($schoolId, (int) $pending['group_id']);
            }
            if (($pending['type'] ?? '') === 'skills' && $canSchedule) {
                return self::runSkills($schoolId);
            }
        }

        if (preg_match('/จัด.*ตาราง|อัตโนมัติ/u', $text) && !preg_match('/ของฉัน/u', $text)) {
            if (!$canSchedule) {
                $who = self::contact($schoolId, 'scheduler', 'ผู้จัดตาราง งานพัฒนาหลักสูตรการเรียนการสอน');
                return 'บทบาทครูผู้สอนไม่มีสิทธิ์จัดหรือแก้ไขตาราง กรุณาติดต่อ ' . $who . "\n\nระหว่างนี้ฉันช่วยสรุปตารางสอนของคุณ หรือร่างคำขอเปลี่ยนคาบเพื่อส่งให้ผู้จัดตารางได้ค่ะ";
            }
            if (!$allowAct) {
                return 'ขณะนี้ปิดการให้ผู้ช่วยดำเนินการแทนผู้ใช้ จึงยังไม่จัดตารางให้ ใช้ปุ่มบนหน้าจัดตารางได้ค่ะ';
            }
            $groupId = self::groupId($schoolId);
            if ($groupId <= 0) {
                return 'สถานศึกษานี้ยังไม่มีกลุ่มผู้เรียนให้จัดตารางค่ะ';
            }
            if ($confirm) {
                $_SESSION['pending_action'] = ['type' => 'schedule', 'group_id' => $groupId];
                return 'เตรียมจัดตารางตามนโยบายของสถานศึกษานี้ โดยคงรายการที่ลงด้วยมือไว้ พิมพ์ “ยืนยัน” เพื่อให้ดำเนินการค่ะ';
            }
            return ScheduleActions::runGroup($schoolId, $groupId);
        }

        if (preg_match('/นโยบาย/u', $text)) {
            if (!$canAdmin) {
                $who = self::contact($schoolId, 'school_admin', 'ผู้ดูแลระบบสถานศึกษา');
                $hint = (int) ($settings['suggest_contact'] ?? 1) === 1 ? ' กรุณาติดต่อ ' . $who : '';
                return 'การเพิ่ม ลด หรือเปลี่ยนนโยบายเป็นสิทธิ์ของผู้ดูแลระบบสถานศึกษา' . $hint . ' เปิดหน้านโยบายเพื่อดูแบบอ่านอย่างเดียวได้ค่ะ';
            }
            if (preg_match('/เพิ่ม/u', $text)) {
                $policyText = trim((string) preg_replace('/.*เพิ่มนโยบาย\s*/u', '', $text));
                if ($policyText === '' || $policyText === $text) {
                    $policyText = 'ครูแต่ละคนสอนไม่เกิน 6 ชั่วโมงต่อวัน';
                }
                if (!$allowAct) {
                    return 'ร่างนโยบายไว้ให้แล้ว: “' . $policyText . '” แต่ปิดไว้ไม่ให้ผู้ช่วยบันทึกแทน นำไปเพิ่มในหน้านโยบายได้ค่ะ';
                }
                if ($confirm) {
                    $_SESSION['pending_action'] = ['type' => 'policy', 'text' => $policyText];
                    return 'เตรียมเพิ่มนโยบาย “' . $policyText . '” เป็นข้อแนะนำ พิมพ์ “ยืนยัน” เพื่อบันทึกค่ะ';
                }
                self::addPolicy($schoolId, $policyText);
                return 'เพิ่มนโยบาย “' . $policyText . '” เป็นข้อแนะนำลำดับสุดท้ายแล้ว ปรับเป็นข้อบังคับหรือเลื่อนลำดับได้ในหน้านโยบายค่ะ';
            }
            return 'เปิดหน้านโยบายได้จากเมนูซ้าย พิมพ์ “เพิ่มนโยบาย …” ตามด้วยข้อความ ฉันจะเตรียมให้ตามสิทธิ์ของคุณค่ะ';
        }

        if (preg_match('/ทักษะ|แบ่ง.*วิชา|วิเคราะห์/u', $text)) {
            if (!$canSchedule) {
                return 'การแบ่งรายวิชาให้ครูเป็นงานของผู้จัดตาราง หากต้องการอัปเดตทักษะการสอน ส่งข้อมูลให้หัวหน้าแผนกได้ค่ะ';
            }
            if (!$allowAct) {
                return 'ปิดการให้ผู้ช่วยดำเนินการแทนผู้ใช้ จึงยังไม่วิเคราะห์ให้ ใช้ปุ่มในหน้าวิเคราะห์ทักษะครูได้ค่ะ';
            }
            if ($confirm) {
                $_SESSION['pending_action'] = ['type' => 'skills'];
                return 'เตรียมวิเคราะห์ทักษะครูเทียบกับรายวิชาของสถานศึกษานี้ พิมพ์ “ยืนยัน” เพื่อเริ่มค่ะ';
            }
            return self::runSkills($schoolId);
        }

        if (preg_match('/ห้อง|ขนาด/u', $text)) {
            foreach (Repo::groups($schoolId) as $group) {
                if (($group['note_tone'] ?? '') === 'danger' && !empty($group['note'])) {
                    return $group['name'] . ' · ' . $group['note'] . ' แนะนำตรวจห้องที่จุได้มากกว่าจำนวนผู้เรียนในคาบเดียวกันค่ะ';
                }
            }
            return 'ยังไม่พบกลุ่มผู้เรียนที่จำนวนคนเกินขนาดห้องที่บันทึกไว้ค่ะ';
        }

        if (preg_match('/ของฉัน|ตารางสอน|ชั่วโมงสอน/u', $text)) {
            return self::myLoad($user, $schoolId);
        }

        if (mb_strlen($text) > 140) {
            $parts = preg_split('/[\n.。]|และ|แล้ว|จากนั้น/u', $text) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => mb_strlen($part) > 8));
            $parts = array_slice($parts, 0, 4);
            $lines = [];
            foreach ($parts as $index => $part) {
                $lines[] = ($index + 1) . '. ' . $part;
            }
            $body = $lines === [] ? $text : implode("\n", $lines);
            return 'รับคำอธิบาย ' . mb_strlen($text) . " ตัวอักษรแล้ว สรุปเป็นงานย่อยดังนี้\n" . $body
                . "\n\nงานที่อยู่ในสิทธิ์" . role_label($role) . ' จะเตรียมให้ตรวจก่อนบันทึก ส่วนที่เกินสิทธิ์จะแจ้งผู้ที่ต้องติดต่อค่ะ';
        }

        return null;
    }

    private static function runSkills(int $schoolId): string
    {
        $rows = Skills::analyze($schoolId);
        if ($rows === []) {
            return 'ยังไม่มีรายวิชาให้วิเคราะห์ค่ะ';
        }
        $low = 0;
        foreach ($rows as $row) {
            if ((int) $row['score'] < 80) {
                $low++;
            }
        }
        return 'วิเคราะห์ทักษะครูเทียบกับ ' . count($rows) . ' รายวิชาของสถานศึกษานี้แล้ว'
            . ($low === 0 ? ' ทุกรายวิชามีผู้สอนที่คะแนนไม่ต่ำกว่า 80%' : ' มี ' . $low . ' รายวิชาที่คะแนนต่ำกว่า 80%')
            . ' ดูรายชื่อและภาระสอนได้ในหน้าวิเคราะห์ทักษะครูค่ะ';
    }

    private static function addPolicy(int $schoolId, string $text): void
    {
        $statement = Database::pdo()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM policies WHERE school_id = :school_id');
        $statement->execute(['school_id' => $schoolId]);
        $sort = (int) $statement->fetchColumn() + 1;
        $insert = Database::pdo()->prepare(
            'INSERT INTO policies (school_id, sort_order, code, short_text, body, policy_type, enabled)
             VALUES (:school_id, :sort_order, NULL, :short_text, :body, \'recommended\', 1)'
        );
        $insert->execute([
            'school_id' => $schoolId,
            'sort_order' => $sort,
            'short_text' => mb_substr($text, 0, 120),
            'body' => $text,
        ]);
    }

    private static function myLoad(array $user, int $schoolId): string
    {
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return 'บัญชีนี้ไม่ได้ผูกกับครูผู้สอน จึงยังสรุปตารางสอนรายบุคคลไม่ได้ค่ะ';
        }
        $hours = 0;
        $groups = [];
        foreach (Repo::groups($schoolId) as $group) {
            foreach (Repo::entries($schoolId, (int) $group['id']) as $entry) {
                $subjectTeacher = null;
                foreach (Repo::subjectsForPlan($schoolId, (int) $group['plan_id']) as $subject) {
                    if ((int) $subject['id'] === (int) $entry['subject_id']) {
                        $subjectTeacher = (int) ($subject['teacher_id'] ?? 0);
                        break;
                    }
                }
                if ($subjectTeacher === $teacherId) {
                    $hours += (int) $entry['length_periods'];
                    $groups[$group['name']] = true;
                }
            }
        }
        $term = Repo::term($schoolId);
        return 'ตารางสอนที่ลงไว้แล้ว' . ($term ? ' ' . $term['label'] : '') . ': ' . $hours . ' ชั่วโมง/สัปดาห์ ใน ' . count($groups) . ' กลุ่มผู้เรียนค่ะ';
    }

    private static function groupId(int $schoolId): int
    {
        $selected = (int) ($_SESSION['schedule_group'] ?? 0);
        if ($selected > 0 && Repo::group($schoolId, $selected)) {
            return $selected;
        }
        $groups = Repo::groups($schoolId);
        return $groups === [] ? 0 : (int) $groups[0]['id'];
    }

    private static function contact(int $schoolId, string $role, string $fallback): string
    {
        $statement = Database::pdo()->prepare(
            'SELECT display_name FROM users WHERE school_id = :school_id AND role = :role ORDER BY id LIMIT 1'
        );
        $statement->execute(['school_id' => $schoolId, 'role' => $role]);
        $name = $statement->fetchColumn();
        return $name ? (string) $name . ' (' . role_label($role) . ')' : $fallback;
    }

    private static function ready(array $settings): bool
    {
        return ($settings['last_test_status'] ?? '') === 'ok' && !empty($settings['api_key_encrypted']);
    }

    private static function maybeLog(int $schoolId, int $userId, array $settings, string $action, string $detail): void
    {
        if ($settings === []) {
            return;
        }
        Repo::logAi($schoolId, $userId, $action, $detail);
    }

    private static function systemPrompt(array $user, int $schoolId, array $settings): string
    {
        $school = SchoolContext::current();
        $policies = [];
        foreach (Repo::policies($schoolId) as $policy) {
            if ((int) $policy['enabled'] === 1) {
                $policies[] = $policy['short_text'];
            }
        }
        $groups = array_map(static fn (array $group): string => $group['name'], Repo::groups($schoolId));
        $act = (int) ($settings['allow_act'] ?? 1) === 1
            ? 'ห้ามอ้างว่าได้บันทึกหรือแก้ไขข้อมูลแล้ว การเปลี่ยนแปลงจริงทำโดยระบบเท่านั้น'
            : 'ห้ามดำเนินการแทนผู้ใช้ ตอบเป็นคำแนะนำอย่างเดียว';
        return "คุณเป็นผู้ช่วย AI ของระบบจัดตารางเรียนอาชีวศึกษา\n"
            . 'สถานศึกษาปัจจุบัน: ' . ($school['name'] ?? '') . "\n"
            . 'ผู้ใช้: ' . $user['display_name'] . ' บทบาท: ' . role_label((string) $user['role']) . "\n"
            . "ใช้เฉพาะข้อมูลของสถานศึกษานี้ ห้ามอ้างสถานศึกษาอื่น\n"
            . $act . "\n"
            . 'นโยบาย: ' . ($policies === [] ? 'ไม่มี' : implode(' | ', $policies)) . "\n"
            . 'กลุ่มผู้เรียน: ' . ($groups === [] ? 'ไม่มี' : implode(', ', $groups)) . "\n"
            . 'ตอบภาษาไทย สั้นและชัด';
    }

    private static function fallback(array $user, string $text): string
    {
        $can = match ($user['role']) {
            'teacher' => 'ดูและสรุปตารางสอน ตรวจขนาดห้อง ร่างคำขอเปลี่ยนคาบ',
            'school_admin', 'superadmin' => 'จัดตาราง วิเคราะห์ทักษะครู เพิ่มหรือแก้นโยบาย ตั้งค่า AI',
            default => 'จัดตารางอัตโนมัติ ลงตารางด้วยมือ วิเคราะห์ทักษะครู ตรวจขนาดห้อง',
        };
        return 'ในบทบาทของคุณ ฉันช่วยได้ในเรื่อง: ' . $can . ' ลองพิมพ์สิ่งที่ต้องการได้เลยค่ะ';
    }
}
