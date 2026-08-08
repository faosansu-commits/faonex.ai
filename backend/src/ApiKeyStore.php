<?php

/**
 * Admin-issued API keys for external servers to call the /v1/* public API.
 * The raw key is only ever shown once, at creation time — only its SHA-256
 * hash is stored, the same way GitHub/Stripe-style tokens work.
 */
final class ApiKeyStore
{
    public static function generate(string $label, int $createdBy): array
    {
        $rawKey = 'fao_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 12);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO api_keys (label, key_hash, key_prefix, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$label, $hash, $prefix, $createdBy]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'label' => $label,
            'prefix' => $prefix,
            'rawKey' => $rawKey,
        ];
    }

    public static function list(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT id, label, key_prefix, is_active, created_at, last_used_at FROM api_keys ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'prefix' => $r['key_prefix'],
            'isActive' => (bool) $r['is_active'],
            'createdAt' => $r['created_at'],
            'lastUsedAt' => $r['last_used_at'],
        ], $rows);
    }

    public static function revoke(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE api_keys SET is_active = 0 WHERE id = ?')->execute([$id]);
    }

    /** Returns the api_keys row id if valid and active, null otherwise. */
    public static function verify(string $rawKey): ?int
    {
        if (!str_starts_with($rawKey, 'fao_')) {
            return null;
        }

        $hash = hash('sha256', $rawKey);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM api_keys WHERE key_hash = ? AND is_active = 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $id = (int) $row['id'];
        $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$id]);

        return $id;
    }
}
