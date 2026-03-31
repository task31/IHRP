<?php

namespace Tests\Feature;

use App\Services\ResumeRedactionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResumeRedactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get(route('resume.redact.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_loads_for_account_manager(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);

        $this->actingAs($am)
            ->get(route('resume.redact.index'))
            ->assertOk();
    }

    public function test_process_rejects_non_pdf(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $invalid = UploadedFile::fake()->create('resume.txt', 5, 'text/plain');

        $this->actingAs($am)
            ->from(route('resume.redact.index'))
            ->post(route('resume.redact.process'), [
                'resume' => $invalid,
                'header_mode' => 'text',
            ])
            ->assertRedirect(route('resume.redact.index'))
            ->assertSessionHasErrors(['resume']);
    }

    public function test_process_rejects_invalid_header_mode(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);

        $this->actingAs($am)
            ->from(route('resume.redact.index'))
            ->post(route('resume.redact.process'), [
                'resume' => UploadedFile::fake()->create('sample-resume.pdf', 50, 'application/pdf'),
                'header_mode' => 'garbage',
            ])
            ->assertRedirect(route('resume.redact.index'))
            ->assertSessionHasErrors(['header_mode']);
    }

    public function test_process_text_header_returns_pdf_download(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $this->mock(ResumeRedactionService::class, function ($mock): void {
            $mock->shouldReceive('extractLines')->once()->andReturn(['Jane Candidate', 'Email jane@example.com']);
            $mock->shouldReceive('redactContactInfo')->once()->andReturn(['Jane Candidate', 'Email [REDACTED]']);
            $mock->shouldReceive('buildPdf')->once()->andReturn('%PDF-1.4 mocked');
        });

        $response = $this->actingAs($am)
            ->post(route('resume.redact.process'), [
                'resume' => UploadedFile::fake()->create('sample-resume.pdf', 50, 'application/pdf'),
                'header_mode' => 'text',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="mpg-jane-candidate.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_process_logo_header_returns_pdf_download(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $this->mock(ResumeRedactionService::class, function ($mock): void {
            $mock->shouldReceive('extractLines')->once()->andReturn(['Jane Candidate', 'Phone 555-111-2222']);
            $mock->shouldReceive('redactContactInfo')->once()->andReturn(['Jane Candidate', 'Phone [REDACTED]']);
            $mock->shouldReceive('buildPdf')->once()->andReturn('%PDF-1.4 mocked');
        });
        DB::table('settings')->insert([
            'key' => 'agency_logo_base64',
            'value' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2loAAAAASUVORK5CYII=',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($am)
            ->post(route('resume.redact.process'), [
                'resume' => UploadedFile::fake()->create('sample-resume.pdf', 50, 'application/pdf'),
                'header_mode' => 'logo',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="mpg-jane-candidate.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
