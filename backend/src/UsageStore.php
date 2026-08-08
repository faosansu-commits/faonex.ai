<?php

final class UsageStore
{
    /** $userId is 0 for calls made through an API key rather than a logged-in user. */
    public static function logUsage(int $userId, ?int $conversationId, string $mode, int $promptTokens, int $completionTokens, ?int $apiKeyId = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO usage_log (user_id, conversation_id, mode, prompt_tokens, completion_tokens, api_key_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $conversationId, $mode, $promptTokens, $completionTokens, $apiKeyId]);
    }

    public static function todayUsage(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log WHERE user_id = ? AND DATE(created_at) = CURDATE()'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['requests' => (int) $row['requests'], 'tokens' => (int) $row['tokens']];
    }

    public static function todayUsageForApiKey(int $apiKeyId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log WHERE api_key_id = ? AND DATE(created_at) = CURDATE()'
        );
        $stmt->execute([$apiKeyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['requests' => (int) $row['requests'], 'tokens' => (int) $row['tokens']];
    }

    public static function deleteForUser(int $userId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM usage_log WHERE user_id = ?')->execute([$userId]);
    }

    public static function overallStats(): array
    {
        $pdo = Database::connection();

        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $totalConversations = (int) $pdo->query('SELECT COUNT(*) FROM conversations')->fetchColumn();
        $totalMessages = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();

        $today = $pdo->query(
            'SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log WHERE DATE(created_at) = CURDATE()'
        )->fetch(PDO::FETCH_ASSOC);

        $last7Days = $pdo->query(
            'SELECT DATE(created_at) AS day, COUNT(*) AS requests,
                    COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 DAY)
             GROUP BY day
             ORDER BY day ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $topUsersToday = $pdo->query(
            "SELECT u.id, u.username, u.display_name,
                    COUNT(l.id) AS requests,
                    COALESCE(SUM(l.prompt_tokens + l.completion_tokens), 0) AS tokens
             FROM users u
             LEFT JOIN usage_log l ON l.user_id = u.id AND DATE(l.created_at) = CURDATE()
             GROUP BY u.id, u.username, u.display_name
             ORDER BY tokens DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'totalUsers' => $totalUsers,
            'totalConversations' => $totalConversations,
            'totalMessages' => $totalMessages,
            'todayRequests' => (int) $today['requests'],
            'todayTokens' => (int) $today['tokens'],
            'last7Days' => array_map(static fn ($r) => [
                'day' => $r['day'],
                'requests' => (int) $r['requests'],
                'tokens' => (int) $r['tokens'],
            ], $last7Days),
            'topUsersToday' => array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'username' => $r['username'],
                'displayName' => $r['display_name'],
                'requests' => (int) $r['requests'],
                'tokens' => (int) $r['tokens'],
            ], $topUsersToday),
        ];
    }

    public static function allTimeTotals(): array
    {
        $pdo = Database::connection();
        $row = $pdo->query(
            'SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens FROM usage_log'
        )->fetch(PDO::FETCH_ASSOC);

        return ['requests' => (int) $row['requests'], 'tokens' => (int) $row['tokens']];
    }

    /** Bar-chart series bucketed by day (last 14), month (last 12), or year (last 5). */
    public static function periodSeries(string $period): array
    {
        $pdo = Database::connection();

        switch ($period) {
            case 'month':
                $groupExpr = "DATE_FORMAT(created_at, '%Y-%m')";
                $since = "DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')";
                break;
            case 'year':
                $groupExpr = "DATE_FORMAT(created_at, '%Y')";
                $since = "DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 4 YEAR), '%Y-01-01')";
                break;
            case 'day':
            default:
                $groupExpr = 'DATE(created_at)';
                $since = 'DATE_SUB(NOW(), INTERVAL 13 DAY)';
                break;
        }

        $rows = $pdo->query(
            "SELECT {$groupExpr} AS bucket, COUNT(*) AS requests,
                    COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log
             WHERE created_at >= {$since}
             GROUP BY bucket
             ORDER BY bucket ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn ($r) => [
            'bucket' => (string) $r['bucket'],
            'requests' => (int) $r['requests'],
            'tokens' => (int) $r['tokens'],
        ], $rows);
    }

    /** Pie-chart data: all-time token/request share by chat mode. */
    public static function modeBreakdown(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT mode, COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log GROUP BY mode'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn ($r) => [
            'mode' => $r['mode'],
            'requests' => (int) $r['requests'],
            'tokens' => (int) $r['tokens'],
        ], $rows);
    }

    public static function dailyUsageForUser(int $userId, int $days = 30): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS requests,
                    COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens
             FROM usage_log
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY day ORDER BY day ASC'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $days, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn ($r) => [
            'day' => $r['day'],
            'requests' => (int) $r['requests'],
            'tokens' => (int) $r['tokens'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
