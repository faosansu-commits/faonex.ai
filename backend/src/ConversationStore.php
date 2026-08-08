<?php

final class ConversationStore
{
    public static function list(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, title, updated_at FROM conversations WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(int $userId, string $title): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title) VALUES (?, ?)');
        $stmt->execute([$userId, $title]);
        return (int) $pdo->lastInsertId();
    }

    public static function find(int $id, int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, title, updated_at FROM conversations WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function messages(int $conversationId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addMessage(int $conversationId, string $role, string $content): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
        $stmt->execute([$conversationId, $role, $content]);
        $pdo->prepare("UPDATE conversations SET updated_at = datetime('now') WHERE id = ?")->execute([$conversationId]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $conversation = self::find($id, $userId);
        if ($conversation === null) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM messages WHERE conversation_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM conversations WHERE id = ?')->execute([$id]);

        return true;
    }

    public static function makeTitle(string $firstMessage): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $firstMessage));
        if (mb_strlen($title) > 40) {
            $title = mb_substr($title, 0, 40) . '…';
        }

        return $title !== '' ? $title : 'บทสนทนาใหม่';
    }
}
