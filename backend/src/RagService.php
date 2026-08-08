<?php

/**
 * RAG half of the "RAG + Rule" knowledge system: once KnowledgeStore has
 * matched a message to a topic by keyword, this retrieves the most relevant
 * chunks from that topic's uploaded documents (via embedding similarity)
 * and builds a system prompt that forces the model to answer only from
 * them — or returns the topic's configured fallback message directly when
 * nothing relevant is found.
 */
final class RagService
{
    public static function matchTopic(string $message): ?array
    {
        return KnowledgeStore::matchTopicByKeyword($message);
    }

    public static function buildAnswer(array $topic, string $message): array
    {
        $embedding = self::embedWithAutoPull($message);
        $chunks = KnowledgeStore::searchTopChunks(
            $topic['id'],
            $embedding,
            Config::ragTopK(),
            Config::ragSimilarityThreshold()
        );

        if (empty($chunks)) {
            return ['forcedReply' => $topic['fallbackMessage'], 'systemPrompt' => null];
        }

        $context = '';
        foreach ($chunks as $i => $chunk) {
            $n = $i + 1;
            $context .= "[ข้อมูลที่ {$n}]\n{$chunk['content']}\n\n";
        }

        $fallback = $topic['fallbackMessage'];
        $systemPrompt = "คุณคือผู้ช่วยตอบคำถามเฉพาะเรื่อง \"{$topic['name']}\" เท่านั้น " .
            "ให้ตอบคำถามของผู้ใช้โดยอ้างอิงจาก \"ข้อมูลอ้างอิง\" ด้านล่างนี้เท่านั้น ห้ามใช้ความรู้อื่นนอกเหนือจากนี้ " .
            "ห้ามเดาหรือแต่งข้อมูลเพิ่มเติมเด็ดขาด ถ้าข้อมูลอ้างอิงไม่มีคำตอบสำหรับคำถามนี้ ให้ตอบด้วยข้อความนี้เท่านั้น (คำต่อคำ ห้ามแก้ไข): " .
            "\"{$fallback}\"\n\nข้อมูลอ้างอิง:\n{$context}";

        return ['forcedReply' => null, 'systemPrompt' => $systemPrompt];
    }

    /** Used both when answering chat and when ingesting a newly uploaded document. */
    public static function embedWithAutoPull(string $text): array
    {
        $model = Config::embedModelTag();
        try {
            return OllamaClient::embeddings($text, $model);
        } catch (Throwable $e) {
            set_time_limit(0);
            $pulled = OllamaClient::pull($model);
            if (!$pulled) {
                throw new RuntimeException('ไม่สามารถเตรียมโมเดลสำหรับค้นหาข้อมูล (embedding) ได้: ' . $e->getMessage());
            }
            return OllamaClient::embeddings($text, $model);
        }
    }
}
