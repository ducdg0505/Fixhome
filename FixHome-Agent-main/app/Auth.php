<?php
final class Auth
{
    private static bool $resolved = false;
    private static ?array $cachedUser = null;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }
        self::$resolved = true;
        if (empty($_SESSION['user_id'])) {
            return self::$cachedUser = null;
        }
        $stmt = db()->prepare('SELECT u.*, c.name AS company_name FROM users u LEFT JOIN companies c ON c.id=u.company_id WHERE u.id=? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            self::logout();
            return self::$cachedUser = null;
        }
        return self::$cachedUser = $user;
    }

    public static function attempt(string $email, string $password): bool
    {
        if (!PasswordPolicy::isSafeBcryptInput($password)) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$resolved = false;
        self::$cachedUser = null;
        audit('login', 'user', (int)$user['id'], 'Đăng nhập thành công');
        return true;
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            audit('logout', 'user', (int)$_SESSION['user_id'], 'Đăng xuất');
        }
        self::$resolved = true;
        self::$cachedUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
