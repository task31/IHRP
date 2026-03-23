<?php

namespace Tests\Unit;

use App\Services\PayrollParseService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PayrollParseServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    private function makeUploadedFile(Spreadsheet $spreadsheet): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll').'.xlsx';
        $this->tempFiles[] = $path;
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'fixture.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private static function cr(int $columnIndex1Based, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex1Based).$row;
    }

    private function baseSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');
        $sheet->setCellValue('A2', 'Fixture Owner');

        $hr = 10;
        $headers = [
            'Check Date',
            'Sub-Total Gross Income',
            'Federal Tax',
            'Social Security ',
            'Medicare',
            'State Tax',
            'Disability',
            '401k Contribution',
            'Check Amount',
        ];
        $c = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue(self::cr($c++, $hr), $h);
        }

        $sheet->setCellValue(self::cr(1, 11), '1/1/2024');
        $sheet->setCellValue(self::cr(2, 11), 2000);
        $sheet->setCellValue(self::cr(3, 11), 100);
        $sheet->setCellValue(self::cr(4, 11), 50);
        $sheet->setCellValue(self::cr(5, 11), 10);
        $sheet->setCellValue(self::cr(6, 11), 20);
        $sheet->setCellValue(self::cr(7, 11), 5);
        $sheet->setCellValue(self::cr(8, 11), 100);
        $sheet->setCellValue(self::cr(9, 11), 1500);

        $sheet->setCellValue(self::cr(1, 12), '1/1/2024');
        $sheet->setCellValue(self::cr(2, 12), 1000);
        $sheet->setCellValue(self::cr(3, 12), 50);
        $sheet->setCellValue(self::cr(4, 12), 25);
        $sheet->setCellValue(self::cr(5, 12), 5);
        $sheet->setCellValue(self::cr(6, 12), 10);
        $sheet->setCellValue(self::cr(7, 12), 2);
        $sheet->setCellValue(self::cr(8, 12), 50);
        $sheet->setCellValue(self::cr(9, 12), 800);

        $sheet->setCellValue(self::cr(1, 13), 'Rafael Zobel');

        $sheet->setCellValue(self::cr(1, 14), '2/1/2024');
        $sheet->setCellValue(self::cr(2, 14), 100);
        $sheet->setCellValue(self::cr(3, 14), 0);
        $sheet->setCellValue(self::cr(4, 14), 0);
        $sheet->setCellValue(self::cr(5, 14), 0);
        $sheet->setCellValue(self::cr(6, 14), 0);
        $sheet->setCellValue(self::cr(7, 14), 0);
        $sheet->setCellValue(self::cr(8, 14), 0);
        $sheet->setCellValue(self::cr(9, 14), 1);

        return $spreadsheet;
    }

    private function addPeriodSheet(Spreadsheet $spreadsheet, string $title, float $aliceGross): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle($title);
        $ws->setCellValue(self::cr(1, 3), '6/15/2024');
        $ws->setCellValue(self::cr(5, 5), 'OT Hours');
        $ws->setCellValue(self::cr(1, 6), 'Alice Adams');
        $ws->setCellValue(self::cr(2, 6), 8);
        $ws->setCellValue(self::cr(4, 6), $aliceGross);
        $ws->setCellValue(self::cr(1, 7), 'Commission 50% Subttal');
    }

    public function test_parse_returns_owner_name(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $this->assertSame('Fixture Owner', $result->ownerName);
    }

    public function test_parse_correct_record_count(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $this->assertCount(1, $result->records);
    }

    public function test_check_dates_are_iso_strings(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result->records[0]['check_date']);
    }

    public function test_grouping_sums_by_check_date(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $this->assertSame('2024-01-01', $result->records[0]['check_date']);
        $this->assertSame('3000.0000', $result->records[0]['gross_pay']);
        $this->assertSame('2300.0000', $result->records[0]['net_pay']);
    }

    public function test_trailing_space_column_name_handled(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $this->assertSame('75.0000', $result->records[0]['social_security']);
    }

    public function test_commission_subtotal_typo_handled(): void
    {
        // col D = 400 (hours × spread), tier = 50% → am_earnings = 400 × 0.50 = 200
        $spreadsheet = $this->baseSpreadsheet();
        $this->addPeriodSheet($spreadsheet, '01.01_01.15_2024', 400);
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $alice = collect($result->consultantRows)->firstWhere('name', 'Alice Adams');
        $this->assertNotNull($alice);
        $this->assertSame('200.0000', $alice['am_earnings']);
    }

    public function test_consultant_data_aggregates_by_year(): void
    {
        // Two periods: col D = 400 + 200 = 600 total spread, tier = 50% → am_earnings = 300
        $spreadsheet = $this->baseSpreadsheet();
        $this->addPeriodSheet($spreadsheet, '01.01_01.15_2024', 400);
        $this->addPeriodSheet($spreadsheet, '02.01_02.15_2024', 200);
        $parser = new PayrollParseService;
        $result = $parser->parse($this->makeUploadedFile($spreadsheet), 'Rafael');
        $alice = collect($result->consultantRows)->firstWhere('name', 'Alice Adams');
        $this->assertSame('300.0000', $alice['am_earnings']);
        $this->assertSame(2024, $alice['year']);
    }

    public function test_stop_name_row_excluded_from_records(): void
    {
        $spreadsheet = $this->baseSpreadsheet();
        $parser = new PayrollParseService;
        $wrong = $parser->parse($this->makeUploadedFile($spreadsheet), 'NoSuchStop');
        $this->assertGreaterThan(1, count($wrong->records));

        $spreadsheet2 = $this->baseSpreadsheet();
        $parser2 = new PayrollParseService;
        $right = $parser2->parse($this->makeUploadedFile($spreadsheet2), 'Rafael');
        $this->assertCount(1, $right->records);
    }
}
