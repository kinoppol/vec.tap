<?php
declare(strict_types=1);

final class ScheduleActions
{
    public static function canEdit(array $user): bool
    {
        return in_array($user['role'], ['superadmin', 'school_admin', 'scheduler'], true);
    }

    public static function seesAllGroups(array $user): bool
    {
        return self::canEdit($user);
    }

    public static function visibleGroups(int $schoolId, array $user, array $groups): array
    {
        if (self::seesAllGroups($user)) {
            return $groups;
        }
        $allowed = array_flip(Repo::schedulerGroupIds($schoolId, Repo::scheduleTeacherId($schoolId, $user)));
        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => isset($allowed[(int) $group['id']])
        ));
    }

    public static function canSchedule(array $user, int $schoolId, int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }
        if (self::seesAllGroups($user)) {
            return Repo::group($schoolId, $groupId) !== null;
        }
        return in_array($groupId, Repo::schedulerGroupIds($schoolId, Repo::scheduleTeacherId($schoolId, $user)), true);
    }

    public static function runGroup(int $schoolId, int $groupId): string
    {
        $group = Repo::group($schoolId, $groupId);
        if ($group === null) {
            return 'ไม่พบกลุ่มผู้เรียนในสถานศึกษานี้ค่ะ';
        }
        $subjects = $group['plan_id'] ? Repo::subjectsForPlan($schoolId, (int) $group['plan_id']) : [];
        $policies = Repo::policies($schoolId);
        $lockLunch = ScheduleEngine::lunchState($policies, (string) $group['level']) === 'required';
        $result = ScheduleEngine::run($subjects, Repo::entries($schoolId, $groupId), (string) $group['level'], $lockLunch);
        $result['entries'] = ScheduleEngine::capTeacherLoad(
            $result['entries'],
            $subjects,
            Repo::teachers($schoolId),
            Repo::teachingHours($schoolId, $groupId),
            ScheduleEngine::teacherHourModes($policies)
        );
        Repo::replaceEntries($schoolId, $groupId, $result['entries']);
        Repo::saveState($groupId, $result['phase'], $result['applied']);
        $need = 0;
        $placed = 0;
        foreach ($subjects as $subject) {
            $subjectNeed = (int) $subject['theory'] + (int) $subject['practice'];
            $need += $subjectNeed;
            $used = 0;
            foreach ($result['entries'] as $entry) {
                if ((int) $entry['subject_id'] === (int) $subject['id']) {
                    $used += (int) $entry['length'];
                }
            }
            $placed += min($subjectNeed, $used);
        }
        $left = max(0, $need - $placed);
        $policies = count(array_filter($policies, static fn (array $policy): bool => (int) $policy['enabled'] === 1));
        if ($left > 0) {
            return 'เริ่มจัดตาราง ' . $group['name'] . ' ตามนโยบาย ' . $policies . ' ข้อ โดยคงรายการที่ลงด้วยมือไว้ จัดได้ ' . $placed . '/' . $need . ' ชั่วโมง ยังลงไม่ได้อีก ' . $left . ' ชั่วโมง ดูสาเหตุและข้อแนะนำได้ที่หน้าจัดตารางค่ะ';
        }
        return 'จัดตาราง ' . $group['name'] . ' ครบ ' . $placed . '/' . $need . ' ชั่วโมง และไม่ขัดนโยบายข้อบังคับค่ะ';
    }
}
