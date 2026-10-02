<?php
declare(strict_types=1);

final class Skills
{
    public static function analyze(int $schoolId): array
    {
        $subjects = Repo::subjects($schoolId);
        $teachers = Repo::teachers($schoolId);
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM skill_suggestions WHERE school_id = :school_id')->execute(['school_id' => $schoolId]);
        $insert = $pdo->prepare(
            'INSERT INTO skill_suggestions (school_id, subject_id, teacher_id, score, reason, alt_text)
             VALUES (:school_id, :subject_id, :teacher_id, :score, :reason, :alt_text)'
        );
        $summary = [];
        foreach ($subjects as $subject) {
            $match = self::match($subject, $teachers);
            $insert->execute([
                'school_id' => $schoolId,
                'subject_id' => $subject['id'],
                'teacher_id' => $match['teacher_id'],
                'score' => $match['score'],
                'reason' => $match['reason'],
                'alt_text' => $match['alt'],
            ]);
            $summary[] = $match;
        }
        $pdo->prepare('UPDATE schools SET skills_ready = 1 WHERE id = :id')->execute(['id' => $schoolId]);
        return $summary;
    }

    public static function loads(int $schoolId, bool $ready): array
    {
        $teachers = Repo::teachers($schoolId);
        $hours = [];
        if ($ready) {
            foreach (Repo::suggestions($schoolId) as $row) {
                $teacherId = (int) ($row['teacher_id'] ?? 0);
                $hours[$teacherId] = ($hours[$teacherId] ?? 0) + (int) $row['theory'] + (int) $row['practice'];
            }
        }
        $loads = [];
        foreach ($teachers as $teacher) {
            $got = $hours[(int) $teacher['id']] ?? 0;
            $max = max(1, (int) $teacher['max_hours']);
            $ratio = $got / $max;
            $loads[] = [
                'name' => $teacher['name'],
                'hrs' => $got,
                'min' => (int) ($teacher['min_hours'] ?? 0),
                'max' => (int) $teacher['max_hours'],
                'pct' => (int) min(100, round($ratio * 100)),
                'bar' => $ratio > 0.85 ? '#E0A526' : '#D63384',
                'fg' => $ratio > 0.85 ? '#8A5A0B' : '#6E6473',
            ];
        }
        return $loads;
    }

    private static function match(array $subject, array $teachers): array
    {
        $known = self::known()[(string) $subject['code']] ?? null;
        if ($known !== null) {
            $teacherId = self::idByName($teachers, $known[0]);
            return [
                'teacher_id' => $teacherId,
                'teacher' => $known[0],
                'score' => $known[1],
                'reason' => $known[2],
                'alt' => $known[3],
            ];
        }
        $best = null;
        $second = null;
        $haystack = mb_strtolower($subject['name'] . ' ' . $subject['code']);
        foreach ($teachers as $teacher) {
            $score = 55;
            foreach ($teacher['skills'] as $skill) {
                if ($skill !== '' && mb_stripos($haystack, $skill) !== false) {
                    $score += 12;
                }
            }
            if (mb_stripos((string) $teacher['dept'], 'สารสนเทศ') !== false && mb_stripos($haystack, 'คอม') !== false) {
                $score += 8;
            }
            $score = min(96, $score);
            $row = ['id' => (int) $teacher['id'], 'name' => $teacher['name'], 'score' => $score, 'degree' => $teacher['degree']];
            if ($best === null || $row['score'] > $best['score']) {
                $second = $best;
                $best = $row;
            } elseif ($second === null || $row['score'] > $second['score']) {
                $second = $row;
            }
        }
        if ($best === null) {
            return ['teacher_id' => null, 'teacher' => '—', 'score' => 0, 'reason' => 'ยังไม่มีข้อมูลครู', 'alt' => '—'];
        }
        $alt = $second ? $second['name'] . ' (' . $second['score'] . '%)' : '—';
        return [
            'teacher_id' => $best['id'],
            'teacher' => $best['name'],
            'score' => $best['score'],
            'reason' => $best['degree'] !== '' ? $best['degree'] : 'เทียบจากแผนกและทักษะที่มีอยู่',
            'alt' => $alt,
        ];
    }

    private static function idByName(array $teachers, string $name): ?int
    {
        foreach ($teachers as $teacher) {
            if ($teacher['name'] === $name) {
                return (int) $teacher['id'];
            }
        }
        return null;
    }

    private static function known(): array
    {
        return [
            '20000-1101' => ['ครูสุนิสา แก้วมณี', 96, 'ป.โท ภาษาไทย · สอนวิชานี้ 6 ภาคเรียน', 'ครูวรรณา (82%)'],
            '20000-1201' => ['ครูพิมพ์ชนก ศรีสุข', 94, 'ป.ตรี ภาษาอังกฤษ · TOEIC 850', 'ครูวรรณา (71%)'],
            '20000-1401' => ['ครูวีระพงษ์ ทองดี', 92, 'ป.ตรี คณิตศาสตร์ · สอนสายอาชีพ 8 ปี', 'ครูธนากร (64%)'],
            '20000-1301' => ['ครูอรทัย บุญมา', 90, 'ป.ตรี วิทยาศาสตร์ทั่วไป · ประจำ Lab วข.2', 'ครูวีระพงษ์ (58%)'],
            '20001-2001' => ['ครูธนากร ใจงาม', 93, 'ป.ตรี คอมพิวเตอร์ศึกษา · Office, Network', 'ครูกมลชนก (80%)'],
            '20901-2002' => ['ครูณัฐวุฒิ พรหมมา', 97, 'Python, C · โค้ชแข่งทักษะ', 'ครูธนากร (78%)'],
            '20901-2003' => ['ครูณัฐวุฒิ พรหมมา', 88, 'Linux, Windows Server', 'ครูธนากร (84%)'],
            '20901-2004' => ['ครูกมลชนก วงศ์ใหญ่', 95, 'Photoshop, Illustrator · ผลงานสื่อ', 'ครูธนากร (61%)'],
            '20000-2001' => ['ครูสมชาย รักชาติ', 91, 'ผู้กำกับลูกเสือวิสามัญ', 'ครูวีระพงษ์ (70%)'],
        ];
    }
}
