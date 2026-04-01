<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
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
     * Remove contact information from extracted text lines (used as fallback).
     * Contact data is silently removed — no [REDACTED] markers.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function redactContactInfo(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $patterns = [
            'email'    => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            'phone'    => '/(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/',
            'linkedin' => '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/[^\s]*/i',
            'url'      => '/https?:\/\/[^\s]+/i',
        ];

        $dropLinePatterns = [
            '/\d+\s+\w.*(?:St|Ave|Blvd|Rd|Dr|Ln|Ct|Way|Pl)\b/i',
            '/[A-Z][a-z]+,\s*[A-Z]{2}\s+\d{5}/',
        ];

        $result = [];
        foreach ($lines as $line) {
            $shouldDrop = false;
            foreach ($dropLinePatterns as $dp) {
                if (preg_match($dp, $line) === 1) {
                    $shouldDrop = true;
                    break;
                }
            }
            if ($shouldDrop) {
                continue;
            }

            $current = $line;
            foreach ($patterns as $pattern) {
                $current = preg_replace($pattern, '', $current) ?? $current;
            }

            // Strip leftover separators (pipe-separated contact lines leave | or • behind)
            $current = trim($current, " \t|•·-,");
            $current = trim($current);

            if ($current !== '') {
                $result[] = $current;
            }
        }

        return $result;
    }

    /**
     * Build a redacted PDF preserving the original formatting using FPDI overlay.
     */
    public function buildRedactedPdf(
        string $pdfPath,
        string $headerMode,
        string $logoBase64,
        string $candidateName = ''
    ): string {
        return $this->overlayWithFpdi($pdfPath, $headerMode, $logoBase64, $candidateName);
    }

    /**
     * Import the original PDF pages via FPDI, overlay white boxes on every line
     * containing contact information, and stamp MPG branding in the cleared area.
     */
    private function overlayWithFpdi(
        string $pdfPath,
        string $headerMode,
        string $logoBase64,
        string $candidateName
    ): string {
        $contactPatterns = [
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            '/(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/',
            '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/[^\s]*/i',
            '/https?:\/\/[^\s]+/i',
            '/\d+\s+\w.*(?:St|Ave|Blvd|Rd|Dr|Ln|Ct|Way|Pl)\b/i',
            '/[A-Z][a-z]+,\s*[A-Z]{2}\s+\d{5}/',
        ];

        // Step 1: Collect y-positions of contact info per page (0-indexed, PDF pts from bottom)
        $parser    = new Parser;
        $parsedPdf = $parser->parseFile($pdfPath);

        /** @var array<int, list<float>> $contactYsByPage */
        $contactYsByPage = [];

        foreach ($parsedPdf->getPages() as $pageNum => $page) {
            try {
                $dataTm = $page->getDataTm();
            } catch (\Throwable) {
                continue;
            }

            foreach ($dataTm as $item) {
                if (! isset($item[0][5], $item[1])) {
                    continue;
                }
                $yPt  = (float) $item[0][5];
                $text = (string) $item[1];

                foreach ($contactPatterns as $pat) {
                    if (preg_match($pat, $text) === 1) {
                        $contactYsByPage[$pageNum][] = $yPt;
                        break;
                    }
                }
            }
        }

        // Step 2: Build modified PDF with FPDI (units = pts to match smalot coords)
        try {
            $fpdi = new Fpdi('P', 'pt');
            $fpdi->SetAutoPageBreak(false);
            $pageCount = $fpdi->setSourceFile($pdfPath);

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $fpdi->importPage($i);
                $size  = $fpdi->getTemplateSize($tplId);
                $w     = (float) $size['width'];
                $h     = (float) $size['height'];

                $fpdi->AddPage('P', [$w, $h]);
                $fpdi->useTemplate($tplId, 0, 0, $w, $h);

                $pageIndex    = $i - 1; // smalot pages are 0-indexed
                $pageContactYs = $contactYsByPage[$pageIndex] ?? [];

                if ($pageContactYs === []) {
                    continue;
                }

                // Deduplicate y-positions: group elements on the same physical line
                $dedupedYs = $this->deduplicateYPositions($pageContactYs, 8.0);

                // Draw a full-width white box over each contact line
                $fpdi->SetFillColor(255, 255, 255);
                foreach ($dedupedYs as $yPt) {
                    // smalot y = from page bottom; FPDI y = from page top
                    $yFpdi = $h - $yPt;
                    $boxY  = max(0.0, $yFpdi - 14); // 14 pt above baseline
                    $fpdi->Rect(0, $boxY, $w, 20.0, 'F');
                }

                // Step 3: Add MPG branding in the cleared contact area on page 1 only
                if ($i === 1) {
                    // Topmost contact line = largest smalot-y = closest to top of page
                    $topContactY  = max($dedupedYs);
                    $brandingYFpdi = max(0.0, $h - $topContactY - 10);

                    if ($headerMode === 'logo' && trim($logoBase64) !== '') {
                        $this->placeLogoInPdf($fpdi, $logoBase64, 5.0, $brandingYFpdi, 80.0);
                    } else {
                        $fpdi->SetFont('Helvetica', 'B', 9);
                        $fpdi->SetTextColor(192, 57, 43);
                        $fpdi->SetXY(5.0, $brandingYFpdi);
                        $fpdi->Cell($w - 10.0, 12.0, 'MatchPointe Group', 0, 0, 'L');
                        $fpdi->SetTextColor(0, 0, 0);
                    }
                }
            }

            return $fpdi->Output('S');
        } catch (\Throwable) {
            throw new \RuntimeException(
                'This PDF format is not supported. Please re-save the file as a standard PDF '
                . '(e.g. File → Save As PDF from Word or Google Docs) and try again.'
            );
        }
    }

    /**
     * Group nearby y-positions (elements on the same line) and return one representative per group.
     *
     * @param  list<float>  $ys
     * @return list<float>
     */
    private function deduplicateYPositions(array $ys, float $tolerance): array
    {
        sort($ys);
        $groups = [];

        foreach ($ys as $y) {
            $matched = false;
            foreach ($groups as &$group) {
                if (abs($group[0] - $y) <= $tolerance) {
                    $group[] = $y;
                    $matched  = true;
                    break;
                }
            }
            unset($group);

            if (! $matched) {
                $groups[] = [$y];
            }
        }

        return array_map(
            fn (array $g): float => (float) (array_sum($g) / count($g)),
            $groups
        );
    }

    /**
     * Decode a base64 data-URI logo and place it on the current FPDI page.
     */
    private function placeLogoInPdf(
        Fpdi $fpdi,
        string $logoBase64,
        float $x,
        float $y,
        float $maxWidth
    ): void {
        if (! preg_match('/^data:(image\/(?:png|jpe?g|gif));base64,(.+)$/s', $logoBase64, $m)) {
            return;
        }

        $ext       = str_contains($m[1], 'png') ? 'png' : (str_contains($m[1], 'gif') ? 'gif' : 'jpg');
        $imageData = base64_decode($m[2]);
        if ($imageData === false) {
            return;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'mpg_logo_') . '.' . $ext;
        try {
            file_put_contents($tmpFile, $imageData);
            $fpdi->Image($tmpFile, $x, $y, $maxWidth, 0, strtoupper($ext));
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * DomPDF text-rebuild fallback (used when FPDI cannot process the source file).
     *
     * @param  list<string>  $redactedLines
     */
    public function buildPdf(
        array $redactedLines,
        string $headerMode,
        string $logoBase64,
        string $candidateName = ''
    ): string {
        return Pdf::loadView('resume-redact.pdf', [
            'redactedLines' => $redactedLines,
            'headerMode'    => $headerMode,
            'logoBase64'    => $logoBase64,
            'candidateName' => $candidateName,
        ])->output();
    }
}
