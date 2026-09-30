<?php
declare(strict_types=1);

final class Auth
{
    public static function insert(
        PDO $pdo,
        ?int $schoolId,
        ?int $teacherId,
        string $username,
        string $password,
        string $displayName,
        string $role
    ): int {
        $statement = $pdo->prepare(
            'INSERT INTO users (school_id, teacher_id, username, password_hash, display_name, role)
             VALUES (:school_id, :teacher_id, :username, :password_hash, :display_name, :role)'
        );
        $statement->execute([
            'school_id' => $schoolId,
            'teacher_id' => $teacherId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function attempt(string $username, string $password): bool
    {
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        if ($user['role'] === 'superadmin') {
            $schoolId = (int) Database::pdo()->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
            $_SESSION['school_id'] = $schoolId > 0 ? $schoolId : 0;
        } else {
            $_SESSION['school_id'] = (int) $user['school_id'];
        }
        unset($_SESSION['pick'], $_SESSION['selected_entry'], $_SESSION['chat'], $_SESSION['pending_action']);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id <= 0) {
            return null;
        }
        static $cached = null;
        static $cachedId = 0;
        if ($cachedId === $id && is_array($cached)) {
            return $cached;
        }
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        if (!$user) {
            return null;
        }
        $cached = $user;
        $cachedId = $id;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            redirect('/login');
        }
        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            render('forbidden', ['currentPage' => ''], 'app');
            exit;
        }
        return $user;
    }
}
