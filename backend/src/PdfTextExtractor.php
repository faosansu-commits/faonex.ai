<?php

final class PdfTextExtractor
{
    public static function extractText(string $filePath): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = (string) $pdf->getText();

        return trim((string) preg_replace('/[ \t]+/', ' ', $text));
    }
}
