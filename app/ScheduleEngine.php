<?php
declare(strict_types=1);

final class ScheduleEngine
{
    public const DAYS = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์'];
    public const TIMES = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];

    public static function blocked(string $level, int $period): bool
    {
        if ($period < 1 || $period >= 10) {
            return true;
        }
        if ($level === 'ปวช.' && $period === 4) {
            return true;
        }
        if (($level === 'ปวส.' || $level === 'ป.ตรี') && $period === 5) {
            return true;
        }
        return false;
    }

    public static function need(array $subject): int
    {
        return (int) $subject['theory'] + (int) $subject['practice'];
    }

    public static function run(array $subjects, array $entries, string $level): array
    {
        $kept = [];
        foreach ($entries as $entry) {
            $plain = self::plain($entry);
            if ($plain['manual']) {
                $kept[] = $plain;
            }
        }
        $byKey = self::byKey($subjects);
        if ($byKey === []) {
            $kept = self::greedy($subjects, $kept, $level);
        } else {
            foreach (self::partialPattern() as $pattern) {
                if (!isset($byKey[$pattern['key']])) {
                    continue;
                }
                $subject = $byKey[$pattern['key']];
                if (self::overlaps($kept, $pattern['day'], $pattern['start'], $pattern['length'])) {
                    continue;
                }
                if (self::used($kept, (int) $subject['id']) + $pattern['length'] > self::need($subject)) {
                    continue;
                }
                $kept[] = self::make((int) $subject['id'], $pattern['day'], $pattern['start'], $pattern['length'], 0, null, 0);
            }
        }
        $left = self::remaining($subjects, $kept);
        return ['entries' => $kept, 'phase' => $left > 0 ? 'partial' : 'done', 'applied' => null];
    }

    public static function applyA(array $subjects): ?array
    {
        $byKey = self::byKey($subjects);
        $entries = [];
        foreach (self::finalA() as $pattern) {
            if (!isset($byKey[$pattern['key']])) {
                return null;
            }
            $subject = $byKey[$pattern['key']];
            $entries[] = self::make(
                (int) $subject['id'],
                $pattern['day'],
                $pattern['start'],
                $pattern['length'],
                (int) ($pattern['manual'] ?? 0),
                $pattern['warning'] ?? null,
                (int) ($pattern['moved'] ?? 0)
            );
        }
        return ['entries' => $entries, 'phase' => 'done', 'applied' => 'A'];
    }

    public static function applyB(array $subjects, array $entries): ?array
    {
        $sci = null;
        foreach ($subjects as $subject) {
            if (($subject['demo_key'] ?? '') === 'sci') {
                $sci = $subject;
                break;
            }
        }
        if ($sci === null) {
            return null;
        }
        $next = [];
        foreach ($entries as $entry) {
            $next[] = self::plain($entry);
        }
        if (self::overlaps($next, 4, 8, 3)) {
            return null;
        }
        $next[] = self::make(
            (int) $sci['id'],
            4,
            8,
            3,
            0,
            'เลิกเรียน 18:00 น. ขัดนโยบายข้อ 4 (ปรับเป็นข้อแนะนำแล้ว)',
            0
        );
        return ['entries' => $next, 'phase' => 'done', 'applied' => 'B', 'relax' => 'end_by_17'];
    }

    public static function reset(array $subjects): array
    {
        $byKey = self::byKey($subjects);
        $entries = [];
        foreach ([[0, 1, 2, 'prog'], [1, 1, 1, 'thai'], [2, 7, 2, 'scout']] as $row) {
            if (!isset($byKey[$row[3]])) {
                continue;
            }
            $entries[] = self::make((int) $byKey[$row[3]]['id'], $row[0], $row[1], $row[2], 1, null, 0);
        }
        return ['entries' => $entries, 'phase' => 'manual', 'applied' => null, 'restore' => 'end_by_17'];
    }

    public static function addManual(array $subjects, array $entries, int $subjectId, int $day, int $period, string $level): ?array
    {
        $subject = null;
        foreach ($subjects as $item) {
            if ((int) $item['id'] === $subjectId) {
                $subject = $item;
                break;
            }
        }
        if ($subject === null) {
            return null;
        }
        $plain = array_map([self::class, 'plain'], $entries);
        $left = self::need($subject) - self::used($plain, $subjectId);
        $length = 0;
        while ($length < min($left, 3) && $length < 2) {
            $slot = $period + $length;
            if ($slot > 10 || self::blocked($level, $slot) || self::overlaps($plain, $day, $slot, 1)) {
                break;
            }
            $length++;
        }
        if ($length < 1) {
            return null;
        }
        $plain[] = self::make($subjectId, $day, $period, $length, 1, null, 0);
        return $plain;
    }

    public static function present(
        array $group,
        array $subjects,
        array $entries,
        array $policies,
        string $phase,
        ?string $applied,
        ?array $pick,
        ?int $selectedId,
        bool $canEdit
    ): array {
        $level = (string) $group['level'];
        $normalized = array_map([self::class, 'plain'], $entries);
        $need = 0;
        $placed = 0;
        $hours = [];
        $unplaced = [];
        foreach ($subjects as $subject) {
            $subjectNeed = self::need($subject);
            $got = self::used($normalized, (int) $subject['id']);
            $need += $subjectNeed;
            $placed += min($subjectNeed, $got);
            $hours[] = [
                'name' => $subject['name'],
                'tpn' => $subject['theory'] . '-' . $subject['practice'] . '-' . $subject['extra'],
                'need' => $subjectNeed,
                'got' => $got,
                'bg' => $got >= $subjectNeed ? '#E8F5EE' : ($got > 0 ? '#FFF6E0' : '#F3EEF4'),
                'fg' => $got >= $subjectNeed ? '#157347' : ($got > 0 ? '#8A5A0B' : '#6E6473'),
            ];
            if ($got < $subjectNeed) {
                $unplaced[] = [
                    'name' => $subject['name'],
                    'left' => $subjectNeed - $got,
                    'reason' => ($subject['demo_key'] ?? '') === 'sci'
                        ? 'ครูอรทัยและห้องปฏิบัติการ SCI-201 (วิทยาเขต 2) ว่างตรงกันเฉพาะวันจันทร์ คาบ 1–3 แต่คาบ 1–2 มีรายการที่ลงด้วยมือไว้ และช่วงเย็นวันศุกร์เกิน 17 น. ตามนโยบายข้อ 4'
                        : 'ไม่พบช่วงเวลาที่ครู ห้อง และกลุ่มผู้เรียนว่างตรงกันโดยไม่ขัดข้อบังคับ',
                ];
            }
        }

        $cells = [];
        for ($day = 0; $day < 5; $day++) {
            for ($period = 1; $period <= 10; $period++) {
                $blocked = self::blocked($level, $period);
                $picked = $pick && (int) $pick['day'] === $day && (int) $pick['period'] === $period;
                $cells[] = [
                    'day' => $day,
                    'period' => $period,
                    'column' => $period + 1,
                    'row' => $day + 2,
                    'blocked' => $blocked,
                    'picked' => $picked,
                    'occupied' => self::overlaps($normalized, $day, $period, 1),
                ];
            }
        }

        $blocks = [];
        $lunchPeriod = ($level === 'ปวส.' || $level === 'ป.ตรี') ? 5 : 4;
        $lunchTime = $lunchPeriod === 4 ? '11:00–12:00' : '12:00–13:00';
        for ($day = 0; $day < 5; $day++) {
            $blocks[] = [
                'entry_id' => null,
                'column' => ($lunchPeriod + 1) . ' / span 1',
                'row' => (string) ($day + 2),
                'lunch' => true,
                'code' => $lunchTime,
                'name' => 'พักกลางวัน',
                'meta' => $level === 'ปวช.' ? 'นโยบายข้อ 2' : 'นโยบายพักกลางวัน',
            ];
        }
        $selected = null;
        foreach ($normalized as $entry) {
            $subject = self::findSubject($subjects, (int) $entry['subject_id']);
            $isSelected = $selectedId !== null && (int) ($entry['id'] ?? 0) === $selectedId;
            $warning = (string) ($entry['warning'] ?? '');
            $manual = (int) $entry['manual'] === 1;
            $blocks[] = [
                'entry_id' => $entry['id'],
                'column' => ($entry['start'] + 1) . ' / span ' . $entry['length'],
                'row' => (string) ($entry['day'] + 2),
                'lunch' => false,
                'manual' => $manual,
                'warning' => $warning,
                'selected' => $isSelected,
                'code' => $subject['code'] ?? '',
                'name' => $subject['name'] ?? '',
                'meta' => trim(($subject['room_code'] ?? '') . ' · ' . preg_replace('/^ครู/u', '', (string) ($subject['teacher_name'] ?? ''))),
            ];
            if ($isSelected && $subject) {
                $selected = [
                    'id' => $entry['id'],
                    'code' => $subject['code'],
                    'name' => $subject['name'],
                    'tpn' => $subject['theory'] . '-' . $subject['practice'] . '-' . $subject['extra'],
                    'time' => 'วัน' . self::DAYS[$entry['day']] . ' ' . self::TIMES[$entry['start'] - 1] . '–' . self::TIMES[$entry['start'] - 1 + $entry['length']],
                    'teacher' => $subject['teacher_name'] ?: '—',
                    'room' => $subject['room_code'] ?: '—',
                    'kind' => $manual
                        ? ((int) $entry['moved'] === 1 ? 'ลงด้วยมือ (ย้ายตามข้อแนะนำ AI)' : 'ลงด้วยมือ · ล็อกไว้')
                        : 'AI จัดให้',
                    'warn' => $warning,
                    'locked' => $manual,
                ];
            }
        }

        $pickModel = null;
        if ($pick) {
            $options = [];
            foreach ($subjects as $subject) {
                $left = self::need($subject) - self::used($normalized, (int) $subject['id']);
                if ($left > 0) {
                    $options[] = [
                        'subject_id' => (int) $subject['id'],
                        'name' => $subject['name'],
                        'left' => $left,
                    ];
                }
            }
            $pickModel = [
                'day' => (int) $pick['day'],
                'period' => (int) $pick['period'],
                'label' => 'วัน' . self::DAYS[(int) $pick['day']] . ' คาบ ' . (int) $pick['period'],
                'options' => $options,
            ];
        }

        $sciLeft = false;
        foreach ($subjects as $subject) {
            if (($subject['demo_key'] ?? '') === 'sci' && self::used($normalized, (int) $subject['id']) < self::need($subject)) {
                $sciLeft = true;
            }
        }
        $hasDemo = self::byKey($subjects) !== [];
        $suggestions = [];
        if ($phase === 'partial' && $sciLeft && !$applied && $hasDemo) {
            $suggestions = [
                [
                    'key' => 'A',
                    'tag' => 'แนะนำ',
                    'tag_bg' => '#E8F5EE',
                    'tag_fg' => '#157347',
                    'effect' => 'ลงครบ 26/26 · ไม่ขัดนโยบาย',
                    'text' => 'ย้าย “การเขียนโปรแกรมเบื้องต้น” ที่ลงด้วยมือ จากวันจันทร์ คาบ 1–2 ไปวันอังคาร คาบ 5–6 แล้วลงวิทยาศาสตร์วันจันทร์ คาบ 1–3',
                ],
                [
                    'key' => 'B',
                    'tag' => 'ผ่อนปรนนโยบาย',
                    'tag_bg' => '#FFF6E0',
                    'tag_fg' => '#8A5A0B',
                    'effect' => 'ลงครบ 26/26 · เลิกเรียน 18:00 น. 1 วัน',
                    'text' => 'เปลี่ยนนโยบายข้อ 4 “ปวช. เลิกเรียนไม่เกิน 17 น.” จากข้อบังคับเป็นข้อแนะนำ แล้วลงวิทยาศาสตร์วันศุกร์ คาบ 8–10',
                ],
            ];
        }

        $morningGap = false;
        for ($day = 0; $day < 5 && !$morningGap; $day++) {
            foreach ([1, 2, 3] as $period) {
                if (!self::overlaps($normalized, $day, $period, 1)) {
                    $morningGap = true;
                    break;
                }
            }
        }
        $lateEnd = false;
        $segmentFail = false;
        foreach ($subjects as $subject) {
            $count = 0;
            foreach ($normalized as $entry) {
                if ((int) $entry['subject_id'] === (int) $subject['id']) {
                    $count++;
                    if ($entry['start'] + $entry['length'] - 1 >= 10) {
                        $lateEnd = true;
                    }
                }
            }
            if ($count > 3) {
                $segmentFail = true;
            }
        }

        $compliance = [];
        $index = 0;
        foreach ($policies as $policy) {
            if ((int) $policy['enabled'] !== 1) {
                continue;
            }
            $index++;
            $ok = true;
            $status = 'ปฏิบัติตามครบ';
            $code = (string) ($policy['code'] ?? '');
            if ($code === 'no_morning_gap' && $morningGap) {
                $ok = false;
                $status = 'มีคาบว่างช่วงเช้า';
            }
            if ($code === 'lunch_hvc') {
                $status = $level === 'ปวช.' ? 'ไม่เกี่ยวข้องกับกลุ่มนี้ (ระดับ ปวช.)' : 'ตรวจคาบพัก 12–13 น.';
            }
            if ($code === 'end_by_17' && $lateEnd) {
                $ok = false;
                $status = 'วันศุกร์เลิก 18:00 น. (ผ่อนปรนเป็นข้อแนะนำ)';
            }
            if ($code === 'split_even') {
                $status = 'วิชา 4 ชม. แบ่ง 2+2 · วิชา 5 ชม. แบ่ง 3+2';
            }
            if ($code === 'max_segments' && $segmentFail) {
                $ok = false;
                $status = 'มีวิชาแบ่งเกิน 3 ส่วน';
            }
            if ($code === 'travel') {
                $status = $applied === 'A'
                    ? 'จันทร์ย้ายวิทยาเขตช่วงพักกลางวัน ใช้เวลา ~8 นาที'
                    : 'ไม่มีคาบติดกันที่ต้องย้ายวิทยาเขต';
            }
            if ($code === '') {
                $status = 'ตรวจแล้ว';
            }
            $compliance[] = [
                'no' => $index,
                'short' => $policy['short_text'],
                'status' => $status,
                'ok' => $ok,
            ];
        }

        $doneNote = $applied === 'B'
            ? 'ผ่อนปรนนโยบายข้อ 4 เป็นข้อแนะนำ วิทยาศาสตร์ลงวันศุกร์ 15:00–18:00 น.'
            : 'ปฏิบัติตามข้อบังคับครบทุกข้อ ย้ายรายการที่ลงด้วยมือ 1 รายการตามข้อแนะนำ';

        return [
            'group' => $group,
            'level' => $level,
            'phase' => $phase,
            'placed' => $placed,
            'need' => $need,
            'cells' => $cells,
            'blocks' => $blocks,
            'hours' => $hours,
            'pick' => $pickModel,
            'selected' => $selected,
            'show_report' => $phase === 'partial',
            'unplaced' => $unplaced,
            'suggestions' => $suggestions,
            'show_done' => $phase === 'done' && $need > 0 && $placed >= $need,
            'done_note' => $doneNote,
            'show_compliance' => $phase === 'partial' || $phase === 'done',
            'compliance' => $compliance,
            'can_edit' => $canEdit,
            'periods' => array_map(
                static fn (int $i): array => ['no' => $i + 1, 'time' => self::TIMES[$i], 'column' => $i + 2],
                range(0, 9)
            ),
        ];
    }

    private static function greedy(array $subjects, array $entries, string $level): array
    {
        foreach ($subjects as $subject) {
            $guard = 0;
            while (self::used($entries, (int) $subject['id']) < self::need($subject) && $guard < 30) {
                $guard++;
                if (self::segmentCount($entries, (int) $subject['id']) >= 3) {
                    break;
                }
                $left = self::need($subject) - self::used($entries, (int) $subject['id']);
                $placed = false;
                foreach ([$left >= 2 ? 2 : 1, 1] as $length) {
                    if ($length < 1 || $length > $left) {
                        continue;
                    }
                    $slot = self::findSlot($entries, $level, $length);
                    if ($slot === null) {
                        continue;
                    }
                    $entries[] = self::make((int) $subject['id'], $slot[0], $slot[1], $length, 0, null, 0);
                    $placed = true;
                    break;
                }
                if (!$placed) {
                    break;
                }
            }
        }
        return $entries;
    }

    private static function findSlot(array $entries, string $level, int $length): ?array
    {
        for ($day = 0; $day < 5; $day++) {
            for ($period = 1; $period <= 10; $period++) {
                $ok = true;
                for ($offset = 0; $offset < $length; $offset++) {
                    $slot = $period + $offset;
                    if ($slot > 10 || self::blocked($level, $slot) || self::overlaps($entries, $day, $slot, 1)) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    return [$day, $period];
                }
            }
        }
        return null;
    }

    private static function partialPattern(): array
    {
        return [
            ['day' => 0, 'start' => 3, 'length' => 1, 'key' => 'math'],
            ['day' => 0, 'start' => 5, 'length' => 2, 'key' => 'gfx'],
            ['day' => 1, 'start' => 2, 'length' => 2, 'key' => 'eng'],
            ['day' => 1, 'start' => 5, 'length' => 2, 'key' => 'prog'],
            ['day' => 2, 'start' => 1, 'length' => 3, 'key' => 'comp'],
            ['day' => 3, 'start' => 1, 'length' => 2, 'key' => 'os'],
            ['day' => 3, 'start' => 3, 'length' => 1, 'key' => 'math'],
            ['day' => 3, 'start' => 5, 'length' => 2, 'key' => 'os'],
            ['day' => 4, 'start' => 1, 'length' => 3, 'key' => 'gfx'],
        ];
    }

    private static function finalA(): array
    {
        return [
            ['day' => 0, 'start' => 1, 'length' => 3, 'key' => 'sci', 'warning' => 'ย้ายวิทยาเขตหลังคาบนี้ 1.8 กม. (~8 นาที) มีพักกลางวันรองรับ'],
            ['day' => 0, 'start' => 5, 'length' => 2, 'key' => 'gfx'],
            ['day' => 0, 'start' => 7, 'length' => 1, 'key' => 'math'],
            ['day' => 1, 'start' => 1, 'length' => 1, 'key' => 'thai', 'manual' => 1],
            ['day' => 1, 'start' => 2, 'length' => 2, 'key' => 'eng'],
            ['day' => 1, 'start' => 5, 'length' => 2, 'key' => 'prog', 'manual' => 1, 'moved' => 1],
            ['day' => 2, 'start' => 1, 'length' => 3, 'key' => 'comp'],
            ['day' => 2, 'start' => 7, 'length' => 2, 'key' => 'scout', 'manual' => 1],
            ['day' => 3, 'start' => 1, 'length' => 2, 'key' => 'os'],
            ['day' => 3, 'start' => 3, 'length' => 1, 'key' => 'math'],
            ['day' => 3, 'start' => 5, 'length' => 2, 'key' => 'os'],
            ['day' => 3, 'start' => 7, 'length' => 2, 'key' => 'prog'],
            ['day' => 4, 'start' => 1, 'length' => 3, 'key' => 'gfx'],
        ];
    }

    private static function byKey(array $subjects): array
    {
        $map = [];
        foreach ($subjects as $subject) {
            if (!empty($subject['demo_key'])) {
                $map[$subject['demo_key']] = $subject;
            }
        }
        return $map;
    }

    private static function findSubject(array $subjects, int $id): ?array
    {
        foreach ($subjects as $subject) {
            if ((int) $subject['id'] === $id) {
                return $subject;
            }
        }
        return null;
    }

    public static function plain(array $entry): array
    {
        return [
            'id' => isset($entry['id']) ? (int) $entry['id'] : null,
            'subject_id' => (int) ($entry['subject_id'] ?? 0),
            'day' => (int) ($entry['day'] ?? $entry['day_index'] ?? 0),
            'start' => (int) ($entry['start'] ?? $entry['start_period'] ?? 0),
            'length' => (int) ($entry['length'] ?? $entry['length_periods'] ?? 0),
            'manual' => (int) ($entry['manual'] ?? $entry['is_manual'] ?? 0),
            'warning' => $entry['warning'] ?? null,
            'moved' => (int) ($entry['moved'] ?? 0),
        ];
    }

    private static function make(int $subjectId, int $day, int $start, int $length, int $manual, ?string $warning, int $moved): array
    {
        return [
            'id' => null,
            'subject_id' => $subjectId,
            'day' => $day,
            'start' => $start,
            'length' => $length,
            'manual' => $manual,
            'warning' => $warning,
            'moved' => $moved,
        ];
    }

    private static function used(array $entries, int $subjectId): int
    {
        $total = 0;
        foreach ($entries as $entry) {
            if ((int) $entry['subject_id'] === $subjectId) {
                $total += (int) $entry['length'];
            }
        }
        return $total;
    }

    private static function remaining(array $subjects, array $entries): int
    {
        $left = 0;
        foreach ($subjects as $subject) {
            $left += max(0, self::need($subject) - self::used($entries, (int) $subject['id']));
        }
        return $left;
    }

    private static function overlaps(array $entries, int $day, int $start, int $length): bool
    {
        $end = $start + $length;
        foreach ($entries as $entry) {
            if ((int) $entry['day'] !== $day) {
                continue;
            }
            $entryStart = (int) $entry['start'];
            $entryEnd = $entryStart + (int) $entry['length'];
            if ($start < $entryEnd && $entryStart < $end) {
                return true;
            }
        }
        return false;
    }

    private static function segmentCount(array $entries, int $subjectId): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            if ((int) $entry['subject_id'] === $subjectId) {
                $count++;
            }
        }
        return $count;
    }
}
