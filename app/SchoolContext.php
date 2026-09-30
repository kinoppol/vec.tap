<?php
declare(strict_types=1);

final class SchoolContext
{
    public static function id(): int
    {
        $user = Auth::user();
        if ($user === null) {
            return 0;
        }
        if ($user['role'] === 'superadmin') {
            $selected = (int) ($_SESSION['school_id'] ?? 0);
            if ($selected > 0 && self::exists($selected)) {
                return $selected;
            }
            $first = (int) Database::pdo()->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
            $_SESSION['school_id'] = $first;
            return $first;
        }
        return (int) $user['school_id'];
    }

    public static function canSwitch(): bool
    {
        $user = Auth::user();
        return $user !== null && $user['role'] === 'superadmin';
    }

    public static function switchTo(int $schoolId): void
    {
        if (!self::canSwitch() || !self::exists($schoolId)) {
            throw new RuntimeException('ไม่สามารถสลับสถานศึกษาได้');
        }
        $_SESSION['school_id'] = $schoolId;
        unset($_SESSION['pick'], $_SESSION['selected_entry'], $_SESSION['pending_action']);
    }

    public static function exists(int $schoolId): bool
    {
        $statement = Database::pdo()->prepare('SELECT id FROM schools WHERE id = :id');
        $statement->execute(['id' => $schoolId]);
        return (bool) $statement->fetchColumn();
    }

    public static function current(): ?array
    {
        $id = self::id();
        if ($id <= 0) {
            return null;
        }
        $statement = Database::pdo()->prepare('SELECT * FROM schools WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row ?: null;
    }
}
