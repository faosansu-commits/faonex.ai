<?php

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'mysql';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'faonex';
            $user = getenv('DB_USER') ?: 'faonex';
            $pass = getenv('DB_PASSWORD') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            self::$pdo = $pdo;
            self::migrate($pdo);
        }

        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(191) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                daily_request_limit INT NULL,
                daily_token_limit INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT 'แชทใหม่',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_conversations_user (user_id),
                CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                role VARCHAR(20) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_messages_conversation (conversation_id),
                CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS usage_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                conversation_id INT NULL,
                api_key_id INT NULL,
                mode VARCHAR(20) NOT NULL DEFAULT 'chat',
                prompt_tokens INT NOT NULL DEFAULT 0,
                completion_tokens INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_usage_log_user_date (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        // ไม่ผูก FOREIGN KEY กับ users/conversations โดยตั้งใจ — flags ต้องอยู่รอดแม้ผู้ใช้/บทสนทนาจะถูกลบไปแล้ว
        // (เก็บไว้เป็นหลักฐานสำหรับแอดมินตรวจสอบย้อนหลัง)
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS flags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(191) NOT NULL,
                conversation_id INT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                content MEDIUMTEXT NOT NULL,
                matched_keywords VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_flags_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(255) NOT NULL,
                key_hash CHAR(64) NOT NULL UNIQUE,
                key_prefix VARCHAR(20) NOT NULL,
                created_by INT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        // ระบบ RAG + Rule: แอดมินกำหนด "หัวข้อ" (Topic) ที่มีคำค้น (Rule) และเอกสารอ้างอิง (RAG)
        // ผูกกัน ถ้าคำถามผู้ใช้ตรงกับคำค้นของหัวข้อไหน จะบังคับให้ตอบจากเอกสารของหัวข้อนั้นเท่านั้น
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS knowledge_topics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                trigger_keywords TEXT NOT NULL,
                fallback_message TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS knowledge_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                topic_id INT NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                char_count INT NOT NULL DEFAULT 0,
                chunk_count INT NOT NULL DEFAULT 0,
                uploaded_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_kdocs_topic (topic_id),
                CONSTRAINT fk_kdocs_topic FOREIGN KEY (topic_id) REFERENCES knowledge_topics(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS knowledge_chunks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT NOT NULL,
                topic_id INT NOT NULL,
                chunk_index INT NOT NULL DEFAULT 0,
                content MEDIUMTEXT NOT NULL,
                embedding LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_kchunks_topic (topic_id),
                KEY idx_kchunks_document (document_id),
                CONSTRAINT fk_kchunks_document FOREIGN KEY (document_id) REFERENCES knowledge_documents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public static function isHealthy(): bool
    {
        try {
            self::connection()->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function tableSummary(): array
    {
        $pdo = self::connection();
        $tables = [
            'users', 'conversations', 'messages', 'usage_log', 'flags', 'api_keys',
            'knowledge_topics', 'knowledge_documents', 'knowledge_chunks',
        ];
        $summary = [];

        foreach ($tables as $table) {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $summary[] = ['table' => $table, 'rows' => $count];
        }

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(data_length + index_length), 0) AS size
             FROM information_schema.TABLES WHERE table_schema = ?'
        );
        $stmt->execute([getenv('DB_NAME') ?: 'faonex']);
        $sizeBytes = (float) $stmt->fetchColumn();

        return [
            'tables' => $summary,
            'fileSizeMb' => round($sizeBytes / 1048576, 2),
        ];
    }
}
