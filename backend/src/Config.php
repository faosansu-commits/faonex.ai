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

    public static function requestTimeout(): int
    {
        $value = getenv('OLLAMA_TIMEOUT');
        return $value !== false ? (int) $value : 120;
    }
}
