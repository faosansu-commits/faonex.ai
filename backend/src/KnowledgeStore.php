<?php

/**
 * Admin-defined "topics" (the Rule half of RAG + Rule): each topic has
 * trigger keywords and reference PDF documents. When a chat message matches
 * a topic's keywords, the answer is forced to come only from that topic's
 * documents (see RagService), falling back to a configured "I don't know"
 * message when nothing relevant is found.
 */
final class KnowledgeStore
{
    public static function listTopics(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT t.id, t.name, t.trigger_keywords, t.fallback_message, t.is_active, t.created_at,
                    COUNT(DISTINCT d.id) AS document_count, COUNT(c.id) AS chunk_count
             FROM knowledge_topics t
             LEFT JOIN knowledge_documents d ON d.topic_id = t.id
             LEFT JOIN knowledge_chunks c ON c.topic_id = t.id
             GROUP BY t.id, t.name, t.trigger_keywords, t.fallback_message, t.is_active, t.created_at
             ORDER BY t.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'mapTopic'], $rows);
    }

    public static function getTopic(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, name, trigger_keywords, fallback_message, is_active, created_at FROM knowledge_topics WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::mapTopic($row) : null;
    }

    public static function createTopic(string $name, string $keywords, string $fallbackMessage, int $createdBy): array
    {
        $name = trim($name);
        $keywords = trim($keywords);
        $fallbackMessage = trim($fallbackMessage) !== ''
            ? trim($fallbackMessage)
            : 'ขออภัย ระบบไม่มีข้อมูลเรื่องนี้ กรุณาติดต่อเจ้าหน้าที่โดยตรง';

        if ($name === '') {
            throw new InvalidArgumentException('กรุณาระบุชื่อหัวข้อ');
        }
        if ($keywords === '') {
            throw new InvalidArgumentException('กรุณาระบุคำค้นอย่างน้อย 1 คำ (คั่นด้วยจุลภาค)');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO knowledge_topics (name, trigger_keywords, fallback_message, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $keywords, $fallbackMessage, $createdBy]);

        return self::getTopic((int) $pdo->lastInsertId());
    }

    public static function updateTopic(int $id, array $data): array
    {
        $topic = self::getTopic($id);
        if ($topic === null) {
            throw new InvalidArgumentException('ไม่พบหัวข้อนี้');
        }

        $name = isset($data['name']) && trim((string) $data['name']) !== '' ? trim((string) $data['name']) : $topic['name'];
        $keywords = isset($data['keywords']) && trim((string) $data['keywords']) !== '' ? trim((string) $data['keywords']) : $topic['keywords'];
        $fallbackMessage = isset($data['fallbackMessage']) && trim((string) $data['fallbackMessage']) !== ''
            ? trim((string) $data['fallbackMessage'])
            : $topic['fallbackMessage'];
        $isActive = array_key_exists('isActive', $data) ? (int) (bool) $data['isActive'] : (int) $topic['isActive'];

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE knowledge_topics SET name = ?, trigger_keywords = ?, fallback_message = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $keywords, $fallbackMessage, $isActive, $id]);

        return self::getTopic($id);
    }

    public static function deleteTopic(int $id): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM knowledge_documents WHERE topic_id = ?');
        $stmt->execute([$id]);
        $docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare('DELETE FROM knowledge_chunks WHERE topic_id = ?')->execute([$id]);
        if (!empty($docIds)) {
            $placeholders = implode(',', array_fill(0, count($docIds), '?'));
            $pdo->prepare("DELETE FROM knowledge_documents WHERE id IN ($placeholders)")->execute($docIds);
        }
        $pdo->prepare('DELETE FROM knowledge_topics WHERE id = ?')->execute([$id]);
    }

    /**
     * The "Rule" step: plain substring/keyword match against active topics
     * only. The topic with the most matching keywords wins; ties go to
     * whichever topic was created first.
     */
    public static function matchTopicByKeyword(string $message): ?array
    {
        $normalized = mb_strtolower($message);

        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT id, name, trigger_keywords, fallback_message FROM knowledge_topics WHERE is_active = 1 ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $best = null;
        $bestScore = 0;

        foreach ($rows as $row) {
            $keywords = array_filter(array_map('trim', explode(',', $row['trigger_keywords'])));
            $score = 0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_stripos($normalized, mb_strtolower($keyword)) !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'id' => (int) $best['id'],
            'name' => $best['name'],
            'fallbackMessage' => $best['fallback_message'],
        ];
    }

    public static function listDocuments(int $topicId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, original_filename, char_count, chunk_count, created_at
             FROM knowledge_documents WHERE topic_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$topicId]);

        return array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'filename' => $r['original_filename'],
            'charCount' => (int) $r['char_count'],
            'chunkCount' => (int) $r['chunk_count'],
            'createdAt' => $r['created_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** $chunks: list of ['content' => string, 'embedding' => float[]] */
    public static function addDocument(int $topicId, string $filename, array $chunks, int $uploadedBy): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $charCount = array_sum(array_map(static fn ($c) => mb_strlen($c['content']), $chunks));

            $stmt = $pdo->prepare(
                'INSERT INTO knowledge_documents (topic_id, original_filename, char_count, chunk_count, uploaded_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$topicId, $filename, $charCount, count($chunks), $uploadedBy]);
            $documentId = (int) $pdo->lastInsertId();

            $chunkStmt = $pdo->prepare(
                'INSERT INTO knowledge_chunks (document_id, topic_id, chunk_index, content, embedding) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($chunks as $index => $chunk) {
                $chunkStmt->execute([$documentId, $topicId, $index, $chunk['content'], json_encode($chunk['embedding'])]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['id' => $documentId, 'filename' => $filename, 'chunkCount' => count($chunks)];
    }

    public static function deleteDocument(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM knowledge_chunks WHERE document_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM knowledge_documents WHERE id = ?')->execute([$id]);
    }

    /** RAG retrieval step: cosine similarity search scoped to one topic's chunks. */
    public static function searchTopChunks(int $topicId, array $queryEmbedding, int $topK, float $threshold): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT content, embedding FROM knowledge_chunks WHERE topic_id = ?');
        $stmt->execute([$topicId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        foreach ($rows as $row) {
            $embedding = json_decode($row['embedding'], true);
            if (!is_array($embedding)) {
                continue;
            }
            $similarity = self::cosineSimilarity($queryEmbedding, $embedding);
            if ($similarity >= $threshold) {
                $scored[] = ['content' => $row['content'], 'similarity' => $similarity];
            }
        }

        usort($scored, static fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $topK);
    }

    private static function cosineSimilarity(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private static function mapTopic(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'keywords' => $row['trigger_keywords'],
            'fallbackMessage' => $row['fallback_message'],
            'isActive' => (bool) $row['is_active'],
            'createdAt' => $row['created_at'],
            'documentCount' => isset($row['document_count']) ? (int) $row['document_count'] : null,
            'chunkCount' => isset($row['chunk_count']) ? (int) $row['chunk_count'] : null,
        ];
    }
}
