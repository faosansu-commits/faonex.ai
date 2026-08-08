<?php

final class OllamaClient
{
    public static function chat(array $messages, string $model): string
    {
        $url = Config::ollamaHost() . '/api/chat';
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
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

        return $content;
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
}
