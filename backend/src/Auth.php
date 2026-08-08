<?php

final class Auth
{
    public static function register(string $username, string $password, string $displayName): array
    {
        $username = trim($username);
        if (mb_strlen($username) < 3) {
            throw new InvalidArgumentException('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร');
        }
        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        }
        $displayName = trim($displayName) !== '' ? trim($displayName) : $username;

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('มีชื่อผู้ใช้นี้อยู่แล้ว');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hash, $displayName]);
        $userId = (int) $pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;

        return ['id' => $userId, 'username' => $username, 'displayName' => $displayName];
    }

    public static function login(string $username, string $password): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, display_name FROM users WHERE username = ?');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }

        $_SESSION['user_id'] = (int) $user['id'];

        return ['id' => (int) $user['id'], 'username' => $user['username'], 'displayName' => $user['display_name']];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, username, display_name FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        return ['id' => (int) $user['id'], 'username' => $user['username'], 'displayName' => $user['display_name']];
    }

    public static function requireAuth(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['error' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $user;
    }
}
