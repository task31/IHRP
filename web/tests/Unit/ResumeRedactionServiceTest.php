<?php

namespace Tests\Unit;

use App\Services\ResumeRedactionService;
use PHPUnit\Framework\TestCase;

class ResumeRedactionServiceTest extends TestCase
{
    public function test_email_is_removed(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['person@example.com']);

        $this->assertSame([], $out);
    }

    public function test_phone_is_removed(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['(555) 111-2222']);

        $this->assertSame([], $out);
    }

    public function test_linkedin_url_is_removed(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['linkedin.com/in/jane-doe']);

        $this->assertSame([], $out);
    }

    public function test_pipe_separated_contact_line_is_removed(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['jane@example.com | (555) 111-2222 | linkedin.com/in/jane']);

        $this->assertSame([], $out);
    }

    public function test_street_address_line_is_dropped(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['123 Main St', 'Skills']);

        $this->assertSame(['Skills'], $out);
    }

    public function test_city_state_zip_line_is_dropped(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo(['Austin, TX 78701', 'Summary']);

        $this->assertSame(['Summary'], $out);
    }

    public function test_name_line_is_preserved(): void
    {
        $service = new ResumeRedactionService;
        $lines = ['Jane Doe', 'Engineer'];
        $out = $service->redactContactInfo($lines);

        $this->assertSame('Jane Doe', $out[0]);
    }

    public function test_empty_input_returns_empty_array(): void
    {
        $service = new ResumeRedactionService;
        $out = $service->redactContactInfo([]);

        $this->assertSame([], $out);
    }
}
