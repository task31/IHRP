<?php

namespace Tests\Unit;

use App\Services\ResumeRedactionService;
use Tests\TestCase;

class ResumeRedactionServicePythonTest extends TestCase
{
    private function tempPdfPath(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'mpg_ut_');
        $this->assertNotFalse($base);
        @unlink($base);
        $path = $base . '.pdf';
        file_put_contents($path, '%PDF-1.4 test');

        return $path;
    }

    public function test_throws_when_proc_open_function_disabled(): void
    {
        $service = new ResumeRedactionService(
            functionExistsFn: static fn (string $name): bool => $name !== 'proc_open',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Server configuration does not allow PDF processing subprocesses');

        $pdf = $this->tempPdfPath();
        try {
            $service->buildRedactedPdf($pdf, 'text', '');
        } finally {
            @unlink($pdf);
        }
    }

    public function test_throws_when_python_binary_not_found(): void
    {
        $procOpen = static function (array $command, array $descriptorSpec, ?array &$pipes): mixed {
            if (count($command) === 2 && ($command[1] ?? null) === '--version') {
                return \proc_open(['php', '-r', 'exit(1);'], $descriptorSpec, $pipes);
            }

            return \proc_open(['php', '-r', 'exit(1);'], $descriptorSpec, $pipes);
        };

        $service = new ResumeRedactionService(procOpenFn: $procOpen);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Python is not available on this server');

        $pdf = $this->tempPdfPath();
        try {
            $service->buildRedactedPdf($pdf, 'text', '');
        } finally {
            @unlink($pdf);
        }
    }

    public function test_throws_when_proc_open_returns_false_for_worker(): void
    {
        $procOpen = static function (array $command, array $descriptorSpec, ?array &$pipes): mixed {
            if (count($command) === 2 && ($command[1] ?? null) === '--version') {
                return \proc_open(['php', '-r', 'exit(0);'], $descriptorSpec, $pipes);
            }

            return false;
        };

        $service = new ResumeRedactionService(procOpenFn: $procOpen);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Server configuration does not allow PDF processing subprocesses');

        $pdf = $this->tempPdfPath();
        try {
            $service->buildRedactedPdf($pdf, 'text', '');
        } finally {
            @unlink($pdf);
        }
    }

    public function test_throws_when_worker_exits_non_zero(): void
    {
        $procOpen = static function (array $command, array $descriptorSpec, ?array &$pipes): mixed {
            if (count($command) === 2 && ($command[1] ?? null) === '--version') {
                return \proc_open(['php', '-r', 'exit(0);'], $descriptorSpec, $pipes);
            }
            if (isset($command[1]) && str_contains((string) $command[1], 'redact_pdf.py')) {
                return \proc_open(['php', '-r', 'fwrite(STDERR, "fail"); exit(1);'], $descriptorSpec, $pipes);
            }

            return false;
        };

        $service = new ResumeRedactionService(procOpenFn: $procOpen);

        $pdf = $this->tempPdfPath();
        try {
            $service->buildRedactedPdf($pdf, 'text', '');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('processing failed', strtolower($e->getMessage()));
        } finally {
            @unlink($pdf);
        }
    }

    public function test_throws_when_worker_exits_zero_but_output_missing(): void
    {
        $procOpen = static function (array $command, array $descriptorSpec, ?array &$pipes): mixed {
            if (count($command) === 2 && ($command[1] ?? null) === '--version') {
                return \proc_open(['php', '-r', 'exit(0);'], $descriptorSpec, $pipes);
            }
            if (isset($command[1]) && str_contains((string) $command[1], 'redact_pdf.py')) {
                return \proc_open(['php', '-r', 'exit(0);'], $descriptorSpec, $pipes);
            }

            return false;
        };

        $service = new ResumeRedactionService(procOpenFn: $procOpen);

        $pdf = $this->tempPdfPath();
        try {
            $service->buildRedactedPdf($pdf, 'text', '');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('processing failed', strtolower($e->getMessage()));
        } finally {
            @unlink($pdf);
        }
    }
}
