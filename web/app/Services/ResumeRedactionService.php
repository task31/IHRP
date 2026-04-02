<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Smalot\PdfParser\Parser;

class ResumeRedactionService
{
    /**
     * @param  callable(string): bool  $functionExistsFn
     * @param  callable(array<int|string, mixed>, array<int, array<int, string>>, array<int, mixed>|null): (resource|false)  $procOpenFn
     */
    public function __construct(
        private mixed $functionExistsFn = null,
        private mixed $procOpenFn = null,
    ) {
        $this->functionExistsFn ??= static fn (string $name): bool => \function_exists($name);
        $this->procOpenFn ??= static function (array $command, array $descriptorSpec, ?array &$pipes): mixed {
            return \proc_open($command, $descriptorSpec, $pipes);
        };
    }

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
     * Build a redacted PDF preserving layout via PyMuPDF worker (scripts/redact_pdf.py).
     */
    public function buildRedactedPdf(
        string $pdfPath,
        string $headerMode,
        string $logoBase64,
        string $candidateName = ''
    ): string {
        return $this->overlayWithPython($pdfPath, $headerMode, $logoBase64, $candidateName);
    }

    private function overlayWithPython(
        string $pdfPath,
        string $headerMode,
        string $logoBase64,
        string $candidateName
    ): string {
        $functionExists = $this->functionExistsFn;
        $procOpen = $this->procOpenFn;

        if (! $functionExists('proc_open')) {
            throw new \RuntimeException(
                'Server configuration does not allow PDF processing subprocesses. Contact your administrator.'
            );
        }

        $python = $this->detectPythonBinary($procOpen);

        $configPath = null;
        $outputPath = null;

        try {
            $configPath = tempnam(sys_get_temp_dir(), 'mpg_redact_cfg_');
            if ($configPath === false) {
                throw new \RuntimeException(
                    'PDF processing failed. The file may be encrypted or in an unsupported format.'
                );
            }

            $outBase = tempnam(sys_get_temp_dir(), 'mpg_redact_out_');
            if ($outBase === false) {
                throw new \RuntimeException(
                    'PDF processing failed. The file may be encrypted or in an unsupported format.'
                );
            }
            @unlink($outBase);
            $outputPath = $outBase . '.pdf';

            $payload = [
                'input_path'  => $pdfPath,
                'output_path' => $outputPath,
                'header_mode' => $headerMode,
                'logo_b64'    => $logoBase64,
            ];
            if (file_put_contents($configPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
                throw new \RuntimeException(
                    'PDF processing failed. The file may be encrypted or in an unsupported format.'
                );
            }

            $scriptPath = base_path('scripts/redact_pdf.py');
            $process = $procOpen(
                [$python, $scriptPath, $configPath],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            if ($process === false) {
                throw new \RuntimeException(
                    'Server configuration does not allow PDF processing subprocesses. Contact your administrator.'
                );
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($stderr !== '' && $stderr !== false) {
                \Illuminate\Support\Facades\Log::warning('redact_pdf.py stderr', ['output' => $stderr]);
            }

            if ($exitCode !== 0 || ! is_file($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException(
                    'PDF processing failed. The file may be encrypted or in an unsupported format.'
                );
            }

            $contents = file_get_contents($outputPath);
            if ($contents === false) {
                throw new \RuntimeException(
                    'PDF processing failed. The file may be encrypted or in an unsupported format.'
                );
            }

            return $contents;
        } finally {
            if ($configPath !== null && is_file($configPath)) {
                @unlink($configPath);
            }
            if ($outputPath !== null && is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * @param  callable(array<int|string, mixed>, array<int, array<int, string>>, array<int, mixed>|null): (resource|false)  $procOpen
     */
    private function detectPythonBinary(callable $procOpen): string
    {
        foreach (['python3', 'python'] as $binary) {
            $process = $procOpen(
                [$binary, '--version'],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            if ($process === false) {
                throw new \RuntimeException(
                    'Server configuration does not allow PDF processing subprocesses. Contact your administrator.'
                );
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode === 0) {
                return $binary;
            }
        }

        throw new \RuntimeException(
            'Python is not available on this server. Contact your administrator.'
        );
    }

    /**
     * DomPDF text-rebuild (used by tests and any direct PDF-from-lines use).
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
