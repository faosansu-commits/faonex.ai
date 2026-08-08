<?php

/**
 * Heuristic keyword-based flagging, not a real content-safety classifier.
 * It only flags matches for admin review — it never blocks a message.
 */
final class ContentModerator
{
    public static function scan(string $text): array
    {
        $normalized = mb_strtolower($text);
        $matched = [];

        foreach (Config::dangerousKeywords() as $keyword) {
            if (mb_strpos($normalized, mb_strtolower($keyword)) !== false) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }
}
