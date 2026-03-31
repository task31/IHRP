<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Smalot\PdfParser\Parser;

class ResumeRedactionService
{
    /**
     * @return list<string>
     */
    public function extractLines(string $pdfPath): array
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $lines = [];

        foreach ($pdf->getPages() as $page) {
            $raw = preg_split('/\R/u', $page->getText()) ?: [];
            foreach ($raw as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function redactContactInfo(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $patterns = [
            'email' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            'phone' => '/(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/',
            'linkedin' => '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/[^\s]*/i',
            'url' => '/https?:\/\/[^\s]+/i',
        ];

        $dropLinePatterns = [
            '/\d+\s+\w.*(?:St|Ave|Blvd|Rd|Dr|Ln|Ct|Way|Pl)\b/i',
            '/[A-Z][a-z]+,\s*[A-Z]{2}\s+\d{5}/',
        ];

        $redacted = [];
        foreach ($lines as $line) {
            $shouldDrop = false;
            foreach ($dropLinePatterns as $dropPattern) {
                if (preg_match($dropPattern, $line) === 1) {
                    $shouldDrop = true;
                    break;
                }
            }

            if ($shouldDrop) {
                continue;
            }

            $currentLine = $line;
            foreach ($patterns as $pattern) {
                $currentLine = preg_replace($pattern, '[REDACTED]', $currentLine) ?? $currentLine;
            }

            $redacted[] = $currentLine;
        }

        return $redacted;
    }

    /**
     * @param  list<string>  $redactedLines
     */
    public function buildPdf(array $redactedLines, string $headerMode, string $logoBase64, string $candidateName = ''): string
    {
        return Pdf::loadView('resume-redact.pdf', [
            'redactedLines' => $redactedLines,
            'headerMode' => $headerMode,
            'logoBase64' => $logoBase64,
            'candidateName' => $candidateName,
        ])->output();
    }
}
