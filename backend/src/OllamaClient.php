<?php

final class OllamaClient
{
    public static function chat(array $messages, string $model): array
    {
        $url = Config::ollamaHost() . '/api/chat';
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'num_ctx' => Config::ollamaNumCtx(),
                'num_predict' => Config::ollamaNumPredict(),
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Config::requestTimeout(),
        ]);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            throw new RuntimeException("ไม่สามารถเชื่อมต่อกับ Ollama ได้: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Ollama ตอบกลับด้วยสถานะ {$status}: {$response}");
        }

        $data = json_decode($response, true);
        $content = $data['message']['content'] ?? null;

        if (!is_string($content)) {
            throw new RuntimeException('รูปแบบข้อมูลตอบกลับจาก Ollama ไม่ถูกต้อง');
        }

        return [
            'content' => $content,
            'promptTokens' => (int) ($data['prompt_eval_count'] ?? 0),
            'completionTokens' => (int) ($data['eval_count'] ?? 0),
        ];
    }

    /**
     * Streams the reply from Ollama token-by-token, invoking $onChunk with
     * each text delta as it arrives (for a typing-effect UI) instead of
     * waiting for the full response like chat() does. Returns the same
     * aggregate shape as chat() once the stream finishes.
     */
    public static function chatStream(array $messages, string $model, callable $onChunk): array
    {
        $url = Config::ollamaHost() . '/api/chat';
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'options' => [
                'num_ctx' => Config::ollamaNumCtx(),
                'num_predict' => Config::ollamaNumPredict(),
            ],
        ], JSON_UNESCAPED_UNICODE);

        $fullContent = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $lineBuffer = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => Config::streamTimeout(),
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$lineBuffer, &$fullContent, &$promptTokens, &$completionTokens, $onChunk): int {
                // The user hit "stop" (browser aborted the fetch) — stop pulling
                // more tokens out of Ollama instead of burning CPU on an answer
                // nobody will see.
                if (connection_aborted()) {
                    return 0;
                }

                $lineBuffer .= $chunk;

                while (($pos = strpos($lineBuffer, "\n")) !== false) {
                    $line = trim(substr($lineBuffer, 0, $pos));
                    $lineBuffer = substr($lineBuffer, $pos + 1);
                    if ($line === '') {
                        continue;
                    }

                    $data = json_decode($line, true);
                    if (!is_array($data)) {
                        continue;
                    }

                    $delta = $data['message']['content'] ?? '';
                    if ($delta !== '') {
                        $fullContent .= $delta;
                        $onChunk($delta);
                    }
                    if (!empty($data['done'])) {
                        $promptTokens = (int) ($data['prompt_eval_count'] ?? 0);
                        $completionTokens = (int) ($data['eval_count'] ?? 0);
                    }
                }

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);

        if ($ok === false) {
            // A user-triggered "stop" (connection_aborted() inside the write
            // callback) also makes curl_exec() report failure — that's not a
            // real error, so keep whatever partial answer was generated
            // instead of discarding it.
            if (connection_aborted()) {
                curl_close($ch);
                return [
                    'content' => $fullContent,
                    'promptTokens' => $promptTokens,
                    'completionTokens' => $completionTokens,
                ];
            }

            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("ไม่สามารถเชื่อมต่อกับ Ollama ได้: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Ollama ตอบกลับด้วยสถานะ {$status}");
        }

        if ($fullContent === '') {
            throw new RuntimeException('ไม่ได้รับคำตอบจาก Ollama');
        }

        return [
            'content' => $fullContent,
            'promptTokens' => $promptTokens,
            'completionTokens' => $completionTokens,
        ];
    }

    /** Returns the embedding vector for $text, used by the RAG knowledge-base search. */
    public static function embeddings(string $text, string $model): array
    {
        $url = Config::ollamaHost() . '/api/embeddings';
        $payload = json_encode(['model' => $model, 'prompt' => $text], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Config::requestTimeout(),
        ]);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            throw new RuntimeException("ไม่สามารถเชื่อมต่อกับ Ollama (embeddings) ได้: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Ollama embeddings ตอบกลับด้วยสถานะ {$status}: {$response}");
        }

        $data = json_decode($response, true);
        $embedding = $data['embedding'] ?? null;

        if (!is_array($embedding) || empty($embedding)) {
            throw new RuntimeException('รูปแบบข้อมูล embedding จาก Ollama ไม่ถูกต้อง');
        }

        return array_map('floatval', $embedding);
    }

    public static function ping(): bool
    {
        $ch = curl_init(Config::ollamaHost() . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $result !== false && $status === 200;
    }

    /** Tags of every model currently pulled into Ollama's local store. */
    public static function listInstalledTags(): array
    {
        $ch = curl_init(Config::ollamaHost() . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false || $status !== 200) {
            return [];
        }

        $data = json_decode($result, true);
        $tags = [];
        foreach ($data['models'] ?? [] as $m) {
            if (isset($m['name'])) {
                $tags[] = $m['name'];
            } elseif (isset($m['model'])) {
                $tags[] = $m['model'];
            }
        }

        return array_values(array_unique($tags));
    }

    /** Ollama shows untagged model names with an implicit ":latest" suffix. */
    public static function isInstalled(string $tag, array $installedTags): bool
    {
        if (in_array($tag, $installedTags, true)) {
            return true;
        }
        if (strpos($tag, ':') === false && in_array($tag . ':latest', $installedTags, true)) {
            return true;
        }
        return false;
    }

    /**
     * Blocking pull — can take minutes for a multi-GB model. Callers must
     * raise their own execution/connection timeouts before calling this.
     */
    public static function pull(string $tag): bool
    {
        $ch = curl_init(Config::ollamaHost() . '/api/pull');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['model' => $tag, 'stream' => false], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1800,
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false || $status < 200 || $status >= 300) {
            return false;
        }

        $data = json_decode($result, true);
        return ($data['status'] ?? '') === 'success';
    }
}
