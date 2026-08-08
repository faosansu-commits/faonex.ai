<?php

final class Auth
{
    public static function register(string $username, string $password, string $displayName): array
    {
        [$username, $displayName] = self::validateCredentials($username, $password, $displayName);

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('มีชื่อผู้ใช้นี้อยู่แล้ว');
        }

        // The very first account created on a fresh install becomes the admin.
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $role = $userCount === 0 ? 'admin' : 'user';

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $hash, $displayName, $role]);
        $userId = (int) $pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;

        return self::currentUser();
    }

    public static function login(string $username, string $password): array
    {
        $pdo = Database::connection();
        // เทียบชื่อผู้ใช้แบบไม่สนตัวพิมพ์ใหญ่-เล็ก กัน login พลาดจากเบราว์เซอร์/คีย์บอร์ดที่ auto-capitalize ให้ (เช่น Safari)
        $stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }
        if (!$user['is_active']) {
            throw new InvalidArgumentException('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        $_SESSION['user_id'] = (int) $user['id'];

        return self::currentUser();
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
        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, role, is_active, daily_request_limit, daily_token_limit
             FROM users WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active']) {
            return null;
        }

        return self::mapUser($user);
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

    public static function requireAdmin(): array
    {
        $user = self::requireAuth();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'ต้องใช้สิทธิ์ผู้ดูแลระบบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $user;
    }

    public static function adminCreateUser(
        string $username,
        string $password,
        string $displayName,
        string $role,
        ?int $dailyRequestLimit,
        ?int $dailyTokenLimit
    ): array {
        [$username, $displayName] = self::validateCredentials($username, $password, $displayName);
        $role = $role === 'admin' ? 'admin' : 'user';

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('มีชื่อผู้ใช้นี้อยู่แล้ว');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, display_name, role, daily_request_limit, daily_token_limit)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $displayName, $role, $dailyRequestLimit, $dailyTokenLimit]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, role, is_active, daily_request_limit, daily_token_limit FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);

        return self::mapUser($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public static function adminUpdateUser(int $id, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            throw new InvalidArgumentException('ไม่พบผู้ใช้งานนี้');
        }

        $displayName = isset($data['displayName']) && trim((string) $data['displayName']) !== ''
            ? trim((string) $data['displayName'])
            : $current['display_name'];

        $role = isset($data['role']) && $data['role'] === 'admin' ? 'admin' : (isset($data['role']) ? 'user' : $current['role']);
        $isActive = array_key_exists('isActive', $data) ? (int) (bool) $data['isActive'] : (int) $current['is_active'];

        $requestLimit = array_key_exists('dailyRequestLimit', $data)
            ? ($data['dailyRequestLimit'] === null || $data['dailyRequestLimit'] === '' ? null : (int) $data['dailyRequestLimit'])
            : $current['daily_request_limit'];
        $tokenLimit = array_key_exists('dailyTokenLimit', $data)
            ? ($data['dailyTokenLimit'] === null || $data['dailyTokenLimit'] === '' ? null : (int) $data['dailyTokenLimit'])
            : $current['daily_token_limit'];

        $demotingAdmin = $current['role'] === 'admin' && ($role !== 'admin' || $isActive === 0);
        if ($demotingAdmin) {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                throw new InvalidArgumentException('ต้องมีผู้ดูแลระบบอย่างน้อย 1 คนในระบบ');
            }
        }

        $passwordHash = $current['password_hash'];
        if (!empty($data['password'])) {
            if (mb_strlen((string) $data['password']) < 6) {
                throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
            }
            $passwordHash = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET display_name = ?, role = ?, is_active = ?, daily_request_limit = ?, daily_token_limit = ?, password_hash = ?
             WHERE id = ?'
        );
        $stmt->execute([$displayName, $role, $isActive, $requestLimit, $tokenLimit, $passwordHash, $id]);
    }

    public static function adminDeleteUser(int $id, int $actingAdminId): void
    {
        if ($id === $actingAdminId) {
            throw new InvalidArgumentException('ไม่สามารถลบบัญชีของตัวเองได้');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            throw new InvalidArgumentException('ไม่พบผู้ใช้งานนี้');
        }

        if ($target['role'] === 'admin') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                throw new InvalidArgumentException('ต้องมีผู้ดูแลระบบอย่างน้อย 1 คนในระบบ');
            }
        }

        ConversationStore::deleteAllForUser($id);
        UsageStore::deleteForUser($id);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    private static function validateCredentials(string $username, string $password, string $displayName): array
    {
        $username = trim($username);
        if (mb_strlen($username) < 3) {
            throw new InvalidArgumentException('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร');
        }
        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        }
        $displayName = trim($displayName) !== '' ? trim($displayName) : $username;

        return [$username, $displayName];
    }

    private static function mapUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'displayName' => $row['display_name'],
            'role' => $row['role'],
            'isActive' => (bool) $row['is_active'],
            'dailyRequestLimit' => $row['daily_request_limit'] !== null ? (int) $row['daily_request_limit'] : null,
            'dailyTokenLimit' => $row['daily_token_limit'] !== null ? (int) $row['daily_token_limit'] : null,
        ];
    }
}
