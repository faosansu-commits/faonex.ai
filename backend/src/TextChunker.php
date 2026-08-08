<?php

final class TextChunker
{
    /** Splits text into ~$maxChars-character chunks, preferring paragraph boundaries. */
    public static function chunk(string $text, int $maxChars = 800): array
    {
        $text = trim((string) preg_replace('/\r\n|\r/', "\n", $text));
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $maxChars) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }
                foreach (mb_str_split($paragraph, $maxChars) as $piece) {
                    $piece = trim($piece);
                    if ($piece !== '') {
                        $chunks[] = $piece;
                    }
                }
                continue;
            }

            if ($buffer === '') {
                $buffer = $paragraph;
            } elseif (mb_strlen($buffer) + mb_strlen($paragraph) + 2 <= $maxChars) {
                $buffer .= "\n\n" . $paragraph;
            } else {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_values($chunks);
    }
}
