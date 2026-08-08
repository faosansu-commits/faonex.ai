<?php

final class ModerationStore
{
    public static function flag(int $userId, string $username, int $conversationId, string $content, array $matchedKeywords): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO flags (user_id, username, conversation_id, role, content, matched_keywords) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $username, $conversationId, 'user', $content, implode(', ', $matchedKeywords)]);
    }

    public static function list(int $limit = 200): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, username, conversation_id, content, matched_keywords, created_at
             FROM flags ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'userId' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'username' => $r['username'],
            'conversationId' => $r['conversation_id'] !== null ? (int) $r['conversation_id'] : null,
            'content' => $r['content'],
            'matchedKeywords' => $r['matched_keywords'],
            'createdAt' => $r['created_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function count(): int
    {
        $pdo = Database::connection();
        return (int) $pdo->query('SELECT COUNT(*) FROM flags')->fetchColumn();
    }
}
