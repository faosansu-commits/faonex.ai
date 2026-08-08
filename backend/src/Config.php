<?php

final class Config
{
    public static function ollamaHost(): string
    {
        return rtrim(getenv('OLLAMA_HOST') ?: 'http://ollama:11434', '/');
    }

    public static function ollamaModel(): string
    {
        return getenv('OLLAMA_MODEL') ?: 'llama3.2';
    }

    public static function codeModel(): string
    {
        return getenv('OLLAMA_CODE_MODEL') ?: 'qwen2.5-coder:3b';
    }

    public static function visionModelTag(): string
    {
        // moondream ตอบกลับว่างเปล่าบน Ollama เวอร์ชันที่ทดสอบ (ยืนยันแล้วว่าเป็นบั๊กของโมเดลนั้น
        // ไม่ใช่ของระบบนี้) llava-phi3 ให้ผลลัพธ์ถูกต้องและยังพอรันบน CPU ได้
        return getenv('OLLAMA_VISION_MODEL') ?: 'llava-phi3';
    }

    /**
     * Selectable general-chat models across different open-weight vendors.
     * Only llama3.2 (the OLLAMA_MODEL default) is pulled up front; the rest
     * download on demand the first time a user picks them (see
     * OllamaClient::pull()).
     */
    public static function chatModelCatalog(): array
    {
        return [
            ['id' => 'llama3.2', 'tag' => self::ollamaModel(), 'label' => 'Llama 3.2', 'vendor' => 'Meta', 'sizeGb' => 2.0],
            ['id' => 'qwen2.5', 'tag' => 'qwen2.5:3b', 'label' => 'Qwen 2.5', 'vendor' => 'Alibaba', 'sizeGb' => 1.9],
            ['id' => 'gemma2', 'tag' => 'gemma2:2b', 'label' => 'Gemma 2', 'vendor' => 'Google', 'sizeGb' => 1.6],
            ['id' => 'mistral', 'tag' => 'mistral:7b', 'label' => 'Mistral', 'vendor' => 'Mistral AI', 'sizeGb' => 4.1],
        ];
    }

    public static function resolveChatModelTag(?string $modelId): string
    {
        foreach (self::chatModelCatalog() as $entry) {
            if ($entry['id'] === $modelId) {
                return $entry['tag'];
            }
        }
        return self::ollamaModel();
    }

    /** Resolves a catalog id (chat model id, or "vision") to a pullable Ollama tag; null if unknown. */
    public static function resolvePullableTag(string $modelId): ?string
    {
        if ($modelId === 'vision') {
            return self::visionModelTag();
        }
        foreach (self::chatModelCatalog() as $entry) {
            if ($entry['id'] === $modelId) {
                return $entry['tag'];
            }
        }
        return null;
    }

    public static function orgName(): string
    {
        return getenv('APP_ORG_NAME') ?: 'องค์กรของเรา';
    }

    public static function systemPrompt(): string
    {
        $custom = getenv('APP_SYSTEM_PROMPT');
        if ($custom) {
            return $custom;
        }

        $org = self::orgName();

        return "คุณคือผู้ช่วย AI ประจำ{$org} หน้าที่ของคุณคือตอบคำถามให้กับพนักงานและผู้ใช้งานภายในองค์กร " .
            "อย่างสุภาพ กระชับ และถูกต้อง หากไม่แน่ใจในคำตอบ ให้บอกตามตรงว่าไม่ทราบ และแนะนำให้ติดต่อผู้ดูแลระบบ " .
            "หรือหน่วยงานที่เกี่ยวข้อง ตอบเป็นภาษาไทยเป็นหลัก เว้นแต่ผู้ใช้ถามเป็นภาษาอื่น";
    }

    public static function codeSystemPrompt(): string
    {
        $custom = getenv('APP_CODE_SYSTEM_PROMPT');
        if ($custom) {
            return $custom;
        }

        return 'คุณคือผู้ช่วยเขียนโปรแกรมที่เชี่ยวชาญในทุกภาษา (Python, JavaScript/TypeScript, PHP, Java, C#, C/C++, Go, Rust, SQL ฯลฯ) ' .
            'เมื่อผู้ใช้ขอโค้ด ให้เขียนโค้ดที่ถูกต้อง ทำงานได้จริง และใส่ใน code block แบบ markdown (```ภาษา ... ```) เสมอ ' .
            'อธิบายสั้น กระชับ ตรงประเด็น หากมีข้อควรระวังหรือวิธีใช้งานให้บอกแบบย่อหลังโค้ด ตอบเป็นภาษาไทยเป็นหลัก เว้นแต่ผู้ใช้ถามเป็นภาษาอื่น';
    }

    public static function visionSystemPrompt(): string
    {
        $custom = getenv('APP_VISION_SYSTEM_PROMPT');
        if ($custom) {
            return $custom;
        }

        $org = self::orgName();

        return "คุณคือผู้ช่วย AI ประจำ{$org} ที่สามารถดูและอธิบายรูปภาพที่ผู้ใช้แนบมาได้ " .
            'อธิบายสิ่งที่เห็นในภาพอย่างถูกต้อง ตอบคำถามเกี่ยวกับภาพอย่างกระชับและตรงประเด็น ' .
            'ตอบเป็นภาษาไทยเป็นหลัก เว้นแต่ผู้ใช้ถามเป็นภาษาอื่น';
    }

    public static function requestTimeout(): int
    {
        $value = getenv('OLLAMA_TIMEOUT');
        return $value !== false ? (int) $value : 120;
    }

    /** Separate (longer) timeout for streamed chat — a long essay-length answer on CPU can legitimately take minutes, and the user already sees progress as it streams. */
    public static function streamTimeout(): int
    {
        $value = getenv('OLLAMA_STREAM_TIMEOUT');
        return $value !== false ? (int) $value : 600;
    }

    /**
     * Context window size given to Ollama for each chat request. Larger =
     * the model can "see" more of the conversation/attached text at once,
     * at the cost of more RAM/CPU per request. 8192 is a reasonable default
     * for CPU-only hosts; raise it (and watch RAM) if you need longer
     * back-and-forth conversations to stay fully in context.
     */
    public static function ollamaNumCtx(): int
    {
        $value = getenv('OLLAMA_NUM_CTX');
        return $value !== false && (int) $value > 0 ? (int) $value : 8192;
    }

    /**
     * Max tokens the model is allowed to generate in one reply. -1 tells
     * Ollama not to impose an artificial cap — the model keeps writing
     * until it naturally stops or runs out of context, which is what makes
     * long, ChatGPT-style answers possible instead of getting cut short.
     */
    public static function ollamaNumPredict(): int
    {
        $value = getenv('OLLAMA_NUM_PREDICT');
        return $value !== false && $value !== '' ? (int) $value : -1;
    }

    /** Embedding model used to index/search admin-uploaded knowledge documents (RAG). */
    public static function embedModelTag(): string
    {
        return getenv('OLLAMA_EMBED_MODEL') ?: 'nomic-embed-text';
    }

    /** Max number of matching chunks fed to the model as forced context. */
    public static function ragTopK(): int
    {
        $value = getenv('APP_RAG_TOP_K');
        return $value !== false && (int) $value > 0 ? (int) $value : 4;
    }

    /** Minimum cosine similarity a chunk needs to count as "found" — below this, the topic's fallback message is used instead. */
    public static function ragSimilarityThreshold(): float
    {
        $value = getenv('APP_RAG_SIMILARITY_THRESHOLD');
        return $value !== false && is_numeric($value) ? (float) $value : 0.35;
    }

    /** null = unlimited */
    public static function defaultDailyRequestLimit(): ?int
    {
        $value = getenv('APP_DEFAULT_DAILY_REQUEST_LIMIT');
        if ($value === false || $value === '') {
            return 100;
        }
        return (int) $value > 0 ? (int) $value : null;
    }

    /** null = unlimited */
    public static function defaultDailyTokenLimit(): ?int
    {
        $value = getenv('APP_DEFAULT_DAILY_TOKEN_LIMIT');
        if ($value === false || $value === '') {
            return 20000;
        }
        return (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Basic heuristic keyword blocklist for flagging potentially dangerous or
     * illegal requests for admin review. This is a substring match, not a
     * real content-safety classifier — it will miss rephrased requests and
     * can also false-positive on legitimate topics (e.g. school chemistry
     * questions). It only flags for review; it does not block the message.
     */
    public static function dangerousKeywords(): array
    {
        return [
            // วัตถุระเบิด/อาวุธ
            'ระเบิด', 'วัตถุระเบิด', 'ทำระเบิด', 'ปืนเถื่อน', 'ระเบิดแสวงเครื่อง',
            'bomb', 'explosive', 'how to make a bomb', 'pipe bomb', 'gun modification',
            // ยาเสพติด/สารเคมีอันตราย
            'ยาบ้า', 'ยาเสพติด', 'ผลิตยาเสพติด', 'สังเคราะห์ยาเสพติด', 'ไอซ์ ยาเสพติด',
            'synthesize meth', 'methamphetamine synthesis', 'how to make drugs', 'nerve agent',
            // แฮก/มัลแวร์
            'แฮกระบบ', 'แฮกเว็บ', 'ขโมยรหัสผ่าน', 'ไวรัสทำลายระบบ', 'แรนซัมแวร์',
            'hack into', 'write ransomware', 'create a virus to destroy', 'ddos attack script', 'steal password',
            // ทำร้ายตนเอง
            'ฆ่าตัวตาย', 'ทำร้ายตัวเอง', 'อยากตาย',
            'suicide method', 'how to kill myself', 'self harm method',
            // การล่วงละเมิดเด็ก
            'ล่วงละเมิดเด็ก', 'สื่อลามกเด็ก',
            'child abuse', 'child exploitation',
            // การก่อการร้าย/ความรุนแรงร้ายแรง
            'ก่อการร้าย', 'วางแผนก่อการร้าย',
            'terrorist attack plan', 'mass casualty attack',
        ];
    }
}
