<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\PayrollConsultantEntry;
use App\Models\PayrollConsultantMapping;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempXlsx = [];

    protected function tearDown(): void
    {
        foreach ($this->tempXlsx as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    private function writeTempXlsx(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ihrp_payroll').'.xlsx';
        $this->tempXlsx[] = $path;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private static function cr(int $columnIndex1Based, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex1Based).$row;
    }

    private function minimalPayrollSpreadsheet(bool $withAlicePeriod = false): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');
        $sheet->setCellValue('A2', 'Owner');
        $hr = 10;
        $headers = [
            'Check Date', 'Sub-Total Gross Income', 'Federal Tax', 'Social Security ', 'Medicare',
            'State Tax', 'Disability', '401k Contribution', 'Check Amount',
        ];
        $c = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue(self::cr($c++, $hr), $h);
        }
        $sheet->setCellValue(self::cr(1, 11), '3/1/2026');
        foreach (range(2, 9) as $i) {
            $sheet->setCellValue(self::cr($i, 11), $i === 2 ? 5000 : ($i === 9 ? 4000 : 0));
        }
        $sheet->setCellValue(self::cr(1, 12), 'Rafael Zobel');

        if ($withAlicePeriod) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('01.01_01.15_2026');
            $ws->setCellValue(self::cr(1, 3), '1/10/2026');
            $ws->setCellValue(self::cr(5, 5), 'OT Hours');
            $ws->setCellValue(self::cr(1, 6), 'Alice Adams');
            $ws->setCellValue(self::cr(2, 6), 8);
            $ws->setCellValue(self::cr(4, 6), 500);
            $ws->setCellValue(self::cr(1, 7), 'Commission 50% Subtotal');
        }

        return $spreadsheet;
    }

    private function uploadedPayrollFile(bool $withAlice = false): UploadedFile
    {
        $path = $this->writeTempXlsx($this->minimalPayrollSpreadsheet($withAlice));

        return new UploadedFile(
            $path,
            'payroll.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_guest_redirected_from_payroll_index(): void
    {
        $this->get(route('payroll.index'))->assertRedirect();
    }

    public function test_guest_unauthenticated_api_dashboard(): void
    {
        $this->getJson('/payroll/api/dashboard?year=2026')->assertUnauthorized();
    }

    public function test_account_manager_can_open_payroll_index(): void
    {
        $u = User::factory()->create(['role' => 'account_manager']);
        $this->actingAs($u)->get(route('payroll.index'))->assertOk();
    }

    public function test_admin_can_open_payroll_index(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $this->actingAs($u)->get(route('payroll.index'))->assertOk();
    }

    public function test_upload_requires_admin(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $file = $this->uploadedPayrollFile();
        $this->actingAs($am)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'user_id' => $am->id,
                'stop_name' => 'Rafael',
            ])
            ->assertForbidden();
    }

    public function test_upload_success_with_valid_file_and_stop_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $file = $this->uploadedPayrollFile();
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'user_id' => $am->id,
                'stop_name' => 'Rafael',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('recordCount', 1);
    }

    public function test_upload_missing_stop_name_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $file = $this->uploadedPayrollFile();
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'user_id' => $am->id,
            ])
            ->assertUnprocessable();
    }

    public function test_upload_missing_user_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->uploadedPayrollFile();
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'stop_name' => 'Rafael',
            ])
            ->assertUnprocessable();
    }

    public function test_upload_user_id_referencing_admin_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->uploadedPayrollFile();
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'user_id' => $admin->id,
                'stop_name' => 'Rafael',
            ])
            ->assertUnprocessable();
    }

    public function test_upload_invalid_mime_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $pdf = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $pdf,
                'user_id' => $am->id,
                'stop_name' => 'Rafael',
            ])
            ->assertUnprocessable();
    }

    public function test_upload_file_over_50mb_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $huge = UploadedFile::fake()->create('big.xlsx', 51201, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $huge,
                'user_id' => $am->id,
                'stop_name' => 'Rafael',
            ])
            ->assertUnprocessable();
    }

    public function test_dashboard_returns_expected_json_shape_for_account_manager(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        PayrollRecord::query()->create([
            'user_id' => $am->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 1000,
            'net_pay' => 800,
            'federal_tax' => 10,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $this->actingAs($am)
            ->getJson('/payroll/api/dashboard?year=2026')
            ->assertOk()
            ->assertJsonStructure([
                'years',
                'summary' => ['periods', 'prior_year_periods', 'totals'],
                'monthly' => ['months'],
                'annualTotals' => ['years'],
                'goal' => ['year', 'amount'],
                'projection',
            ]);
    }

    public function test_am_sees_only_own_data(): void
    {
        $am1 = User::factory()->create(['role' => 'account_manager']);
        $am2 = User::factory()->create(['role' => 'account_manager']);
        PayrollRecord::query()->create([
            'user_id' => $am1->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 100,
            'net_pay' => 50,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        PayrollRecord::query()->create([
            'user_id' => $am2->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 9999,
            'net_pay' => 8888,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $json = $this->actingAs($am1)->getJson('/payroll/api/dashboard?year=2026')->assertOk()->json();
        $this->assertSame('50.0000', $json['summary']['totals']['ytd_net']);
    }

    public function test_admin_sees_aggregate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        PayrollRecord::query()->create([
            'user_id' => $am->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 1000,
            'net_pay' => 400,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $this->actingAs($admin)
            ->getJson('/payroll/api/aggregate?year=2026')
            ->assertOk()
            ->assertJsonPath('aggregate.ytd_net', '400.0000');
    }

    public function test_admin_can_switch_am_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am1 = User::factory()->create(['role' => 'account_manager']);
        $am2 = User::factory()->create(['role' => 'account_manager']);
        PayrollRecord::query()->create([
            'user_id' => $am1->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 100,
            'net_pay' => 10,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        PayrollRecord::query()->create([
            'user_id' => $am2->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 100,
            'net_pay' => 99,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $j1 = $this->actingAs($admin)->getJson('/payroll/api/dashboard?year=2026&user_id='.$am1->id)->assertOk()->json();
        $j2 = $this->actingAs($admin)->getJson('/payroll/api/dashboard?year=2026&user_id='.$am2->id)->assertOk()->json();
        $this->assertSame('10.0000', $j1['summary']['totals']['ytd_net']);
        $this->assertSame('99.0000', $j2['summary']['totals']['ytd_net']);
    }

    public function test_admin_cannot_pass_own_user_id_to_read_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->getJson('/payroll/api/dashboard?year=2026&user_id='.$admin->id)
            ->assertStatus(422);
    }

    public function test_admin_dashboard_requires_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->getJson('/payroll/api/dashboard?year=2026')
            ->assertStatus(422);
    }

    public function test_goal_set_and_appears_on_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $this->actingAs($admin)
            ->postJson(route('payroll.api.goal.set'), [
                'user_id' => $am->id,
                'year' => 2026,
                'goal_amount' => 50000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->actingAs($admin)
            ->getJson('/payroll/api/dashboard?year=2026&user_id='.$am->id)
            ->assertOk()
            ->assertJsonPath('goal.amount', '50000.0000');
    }

    public function test_goal_set_with_admin_user_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->postJson(route('payroll.api.goal.set'), [
                'user_id' => $admin->id,
                'year' => 2026,
                'goal_amount' => 100,
            ])
            ->assertUnprocessable();
    }

    public function test_mappings_list_and_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $client = Client::query()->create(['name' => 'C1', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Zed Consultant',
            'pay_rate' => 10,
            'bill_rate' => 20,
            'state' => 'CA',
            'client_id' => $client->id,
        ]);
        PayrollConsultantMapping::query()->create([
            'raw_name' => 'XLSX Name',
            'consultant_id' => null,
            'user_id' => $am->id,
            'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)
            ->getJson(route('payroll.api.mappings'))
            ->assertOk()
            ->assertJsonCount(1, 'mappings');
        $this->actingAs($admin)
            ->putJson(route('payroll.api.mappings.update'), [
                'raw_name' => 'XLSX Name',
                'user_id' => $am->id,
                'consultant_id' => $consultant->id,
            ])
            ->assertOk();
        $this->assertNotNull(PayrollConsultantMapping::query()->where('raw_name', 'XLSX Name')->value('consultant_id'));
    }

    public function test_upload_auto_resolves_consultant_after_mapping(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $client = Client::query()->create(['name' => 'C2', 'active' => true]);
        Consultant::query()->create([
            'full_name' => 'Alice Adams',
            'pay_rate' => 10,
            'bill_rate' => 20,
            'state' => 'NY',
            'client_id' => $client->id,
        ]);
        $file = $this->uploadedPayrollFile(true);
        $this->actingAs($admin)
            ->post(route('payroll.upload'), [
                'file' => $file,
                'user_id' => $am->id,
                'stop_name' => 'Rafael',
            ])
            ->assertOk();
        $entry = PayrollConsultantEntry::query()
            ->where('user_id', $am->id)
            ->where('consultant_name', 'Alice Adams')
            ->first();
        $this->assertNotNull($entry);
        $this->assertNotNull($entry->consultant_id);
    }

    public function test_non_admin_cannot_access_aggregate(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $this->actingAs($am)->getJson('/payroll/api/aggregate?year=2026')->assertForbidden();
    }
}
