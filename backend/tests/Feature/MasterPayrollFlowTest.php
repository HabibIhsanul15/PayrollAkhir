<?php

namespace Tests\Feature;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\GradeAllowanceRate;
use App\Models\JobHistory;
use App\Models\MonthlyRecap;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Models\WorkBasis;
use App\Services\AllowanceCalculationService;
use App\Services\AllowanceRateResolver;
use App\Services\CryptoService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollCipherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterPayrollFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('AES_KEY_128=1234567890123456');
    }

    public function test_resolver_uses_the_rate_that_is_active_on_the_requested_date(): void
    {
        $grade = $this->grade('staff', 'Staff');
        $type = $this->allowanceType('meal', 'Tunjangan Makan', 'per_mandays', 'total_mandays');

        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 20000,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'is_active' => true,
        ]);
        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $resolver = app(AllowanceRateResolver::class);

        $this->assertSame('20000.00', $resolver->resolveByCode($grade->id, 'meal', '2026-06-15')->rate_amount);
        $this->assertSame('25000.00', $resolver->resolveByCode($grade->id, 'meal', '2026-07-15')->rate_amount);
    }

    public function test_creating_a_new_rate_version_closes_the_previous_open_period(): void
    {
        $finance = User::factory()->create(['role' => 'fat']);
        Sanctum::actingAs($finance);
        $grade = $this->grade('staff', 'Staff');
        $type = $this->allowanceType('meal', 'Tunjangan Makan', 'per_mandays', 'total_mandays');
        $old = GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 20000,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/master/grade-allowance-rates', [
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertSame('2026-06-30', $old->fresh()->effective_to->toDateString());
    }

    public function test_allowance_calculation_reads_units_from_the_monthly_recap(): void
    {
        $grade = $this->grade('staff', 'Staff');
        $type = $this->allowanceType('meal', 'Tunjangan Makan', 'per_mandays', 'total_mandays');
        $overtimeType = $this->allowanceType('overtime', 'Tunjangan Lembur', 'per_mandays', 'overtime_hours');
        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $overtimeType->id,
            'rate_amount' => 50000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');
        $employee = Employee::create([
            'employee_code' => 'EMP-TEST-1',
            'name' => 'Test Employee',
            'position' => 'Staff',
            'grade_id' => $grade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ])->load('employmentType');
        $recap = new MonthlyRecap(['total_mandays' => 10, 'overtime_hours' => 2]);

        $results = app(AllowanceCalculationService::class)->calculate(
            $employee,
            $recap,
            $grade->id,
            '2026-07-01',
            150000,
            1
        );

        $meal = collect($results)->firstWhere('type.code', 'meal');
        $overtime = collect($results)->firstWhere('type.code', 'overtime');
        $this->assertSame(250000.0, $meal['amount']);
        $this->assertSame(100000.0, $overtime['amount']);
    }

    public function test_creating_employee_auto_activates_and_uses_grade_salary_basis(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff', 'Staff', 8, 'daily', 175000);

        $response = $this->postJson('/api/employees', [
            'employee_code' => 'EMP-NEW-1',
            'name' => 'Auto Active Employee',
            'grade_id' => $grade->id,
            'join_date' => '2026-07-10',
        ]);

        $response->assertCreated()
            ->assertJsonPath('employee.status', 'active');

        $employee = Employee::query()->where('employee_code', 'EMP-NEW-1')->firstOrFail();
        $profile = $employee->salaryProfiles()->firstOrFail();

        $this->assertSame('active', $employee->status);
        $this->assertSame('2026-07-10', $employee->join_date?->toDateString());
        $this->assertNull($employee->employment_type_id);
        $this->assertSame('daily', $profile->base_salary_basis);
        $this->assertSame('175000', CryptoService::decryptAESGCM($profile->base_salary_amount_enc));
        $this->assertTrue($employee->salaryProfiles()->exists());
        $this->assertTrue($employee->jobHistories()->exists());
    }

    public function test_creating_employee_rejects_non_digit_nik_npwp_phone_and_bank_account_number(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff-digit', 'Staff Digit');

        $response = $this->postJson('/api/employees', [
            'employee_code' => 'EMP-DIGIT-1',
            'name' => 'Digit Validation',
            'grade_id' => $grade->id,
            'nik' => '3273ABC',
            'npwp' => '12.345.678',
            'phone' => '0812TEST',
            'bank_account_number' => '123-456-789',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nik', 'npwp', 'phone', 'bank_account_number']);
    }

    public function test_creating_employee_with_account_requires_confirmed_password(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff-account', 'Staff Account');

        $response = $this->postJson('/api/employees', [
            'employee_code' => 'EMP-ACCOUNT-1',
            'name' => 'Account Validation',
            'grade_id' => $grade->id,
            'create_account' => true,
            'email' => 'account.validation@example.test',
            'role' => 'staff',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret321',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_grade_name_is_unique_and_code_follows_name(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $existing = Grade::create([
            'code' => 'pm',
            'name' => 'Project Manager',
            'level' => 3,
            'base_salary_basis' => 'daily',
            'default_base_salary_amount' => 100000,
            'default_mandays_rate' => 100000,
            'is_active' => true,
        ]);

        $this->postJson('/api/master/grades', [
            'name' => 'Project Manager',
            'level' => 4,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $response = $this->postJson('/api/master/grades', [
            'name' => 'Payroll Manager',
            'level' => 4,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'pm-2')
            ->assertJsonPath('base_salary_basis', 'daily');

        $createdId = $response->json('id');
        $this->assertArrayNotHasKey('default_base_salary_amount', $response->json());

        $this->putJson("/api/master/grades/{$createdId}", [
            'name' => 'Senior Payroll Manager',
            'level' => 4,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('code', 'spm');

        $this->putJson("/api/master/grades/{$createdId}", [
            'name' => $existing->name,
            'level' => 4,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->putJson("/api/master/grades/{$createdId}", [
            'default_base_salary_amount' => 95000,
        ])->assertOk();

        $this->assertSame(0.0, Grade::findOrFail($createdId)->default_base_salary_amount);
    }

    public function test_finance_can_manage_grade_salary_nominal(): void
    {
        $finance = User::factory()->create(['role' => 'fat']);
        Sanctum::actingAs($finance);

        $grade = $this->grade('finance-payroll-staff', 'Finance Payroll Staff', 9, 'daily', 0);

        $this->putJson("/api/master/grades/{$grade->id}", [
            'default_base_salary_amount' => 150000,
        ])->assertOk();

        $grade->refresh();
        $this->assertSame(150000.0, $grade->default_base_salary_amount);
        $this->assertSame(150000.0, $grade->default_mandays_rate);

        $this->postJson('/api/master/grades', [
            'name' => 'Finance Cannot Create Grade',
            'level' => 10,
            'is_active' => true,
        ])->assertForbidden();

        $this->putJson("/api/master/grades/{$grade->id}", [
            'name' => 'Finance Cannot Rename Grade',
            'level' => 1,
        ])->assertForbidden();

        $grade->refresh();
        $this->assertSame('Finance Payroll Staff', $grade->name);
        $this->assertSame(9, $grade->level);

        $this->deleteJson("/api/master/grades/{$grade->id}")
            ->assertForbidden();
    }

    public function test_finance_can_manage_grade_allowance_rate(): void
    {
        $finance = User::factory()->create(['role' => 'fat']);
        Sanctum::actingAs($finance);

        $grade = $this->grade('staff-rate', 'Staff Rate');
        $type = $this->allowanceType('meal-rate', 'Tunjangan Makan Rate', 'per_mandays', 'total_mandays');

        $response = $this->postJson('/api/master/grade-allowance-rates', [
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('rate_amount', '25000.00');

        $rate = GradeAllowanceRate::query()->findOrFail($response->json('id'));

        $this->putJson("/api/master/grade-allowance-rates/{$rate->id}", [
            'rate_amount' => 30000,
            'effective_from' => '2026-07-01',
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('rate_amount', '30000.00');
    }

    public function test_allowance_type_requires_attendance_indicator_for_daily_calculation(): void
    {
        $finance = User::factory()->create(['role' => 'fat']);
        Sanctum::actingAs($finance);

        $this->postJson('/api/master/allowance-types', [
            'code' => 'daily_without_indicator',
            'name' => 'Harian Tanpa Indikator',
            'calculation_type' => 'per_mandays',
            'display_order' => 99,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['input_source']);

        $response = $this->postJson('/api/master/allowance-types', [
            'code' => 'wfo_allowance',
            'name' => 'Tunjangan WFO',
            'calculation_type' => 'per_mandays',
            'input_source' => 'wfo_days',
            'display_order' => 100,
            'applies_to' => 'all',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('input_source', 'wfo_days')
            ->assertJsonPath('applies_to', 'all');

        $this->postJson('/api/master/allowance-types', [
            'code' => 'overtime_allowance',
            'name' => 'Tunjangan Lembur',
            'calculation_type' => 'per_mandays',
            'input_source' => 'overtime_hours',
            'display_order' => 101,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('input_source', 'overtime_hours')
            ->assertJsonPath('applies_to', 'all');

        $this->putJson('/api/master/allowance-types/'.$response->json('id'), [
            'calculation_type' => 'per_trip',
            'input_source' => 'business_trips',
            'display_order' => 100,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('calculation_type', 'per_trip')
            ->assertJsonPath('input_source', 'business_trips')
            ->assertJsonPath('applies_to', 'all');
    }

    public function test_hcga_can_manage_grades_without_seeing_nominal_and_cannot_manage_allowance_master(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff-readonly', 'Staff Readonly');
        $type = $this->allowanceType('meal-readonly', 'Tunjangan Makan Readonly', 'per_mandays', 'total_mandays');

        $gradeResponse = $this->getJson('/api/master/grades?active_only=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $grade->id]);

        $gradePayload = collect($gradeResponse->json())->firstWhere('id', $grade->id);
        $this->assertIsArray($gradePayload);
        $this->assertArrayNotHasKey('default_base_salary_amount', $gradePayload);
        $this->assertArrayNotHasKey('default_mandays_rate', $gradePayload);
        $this->assertArrayNotHasKey('allowance_rates', $gradePayload);

        $createdGrade = $this->postJson('/api/master/grades', [
            'name' => 'HCGA Created Grade',
            'level' => 10,
            'default_base_salary_amount' => 100000,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'HCGA Created Grade');

        $this->assertArrayNotHasKey('default_base_salary_amount', $createdGrade->json());
        $this->assertSame(0.0, Grade::findOrFail($createdGrade->json('id'))->default_base_salary_amount);

        $this->getJson('/api/master/allowance-types')
            ->assertForbidden();

        $this->postJson('/api/master/grade-allowance-rates', [
            'grade_id' => $grade->id,
            'allowance_type_id' => $type->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-07-01',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_monthly_base_salary_is_paid_as_fixed_amount_for_the_period(): void
    {
        config(['crypto.payroll_write_alg' => 'AES']);
        $finance = User::factory()->create(['role' => 'fat']);
        $grade = $this->grade('manager', 'Manager', 4, 'monthly', 4000000);
        $position = $this->allowanceType('position', 'Tunjangan Jabatan', 'flat', null);

        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $position->id,
            'rate_amount' => 500000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'employee_code' => 'EMP-MONTHLY-1',
            'name' => 'Monthly Salary Employee',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'status' => 'active',
            'join_date' => '2026-07-01',
        ]);
        $profile = SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $grade->id,
            'position' => $grade->name,
            'base_salary_basis' => 'monthly',
            'base_salary_amount' => 0,
            'base_salary_amount_enc' => CryptoService::encryptAESGCM('4000000'),
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('500000'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('4000000'),
            'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'effective_from' => '2026-07-01',
            'salary_alg' => 'AES',
        ]);
        MonthlyRecap::create([
            'employee_id' => $employee->id,
            'salary_profile_id' => $profile->id,
            'period_month' => '2026-07',
            'wfo_days' => 10,
            'total_mandays' => 10,
            'is_finalized' => true,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculateAndSave(
            $employee->id,
            '2026-07',
            $finance->id
        );
        $plain = app(PayrollCipherService::class)->decrypt($payroll->fresh());

        $this->assertSame(4000000.0, (float) $plain['gaji_pokok']);
        $this->assertSame(500000.0, (float) $plain['total_allowances']);
        $this->assertSame(4500000.0, (float) $plain['total']);
    }

    public function test_monthly_recap_rejects_total_paid_days_above_days_in_month(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff', 'Staff');
        $employee = Employee::create([
            'employee_code' => 'EMP-RECAP-1',
            'name' => 'Recap Employee',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'status' => 'active',
        ]);
        $profile = SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $grade->id,
            'position' => $grade->name,
            'position_allowance' => 0,
            'base_salary_basis' => 'daily',
            'base_salary_amount' => 0,
            'base_salary_amount_enc' => CryptoService::encryptAESGCM('100000'),
            'effective_from' => '2026-02-01',
            'salary_alg' => 'AES',
        ]);

        $response = $this->postJson('/api/monthly-recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-02',
            'recaps' => [[
                'salary_profile_id' => $profile->id,
                'wfo_days' => 20,
                'wfh_days' => 9,
                'out_of_town_days' => 0,
                'business_trips' => 0,
                'training_days' => 0,
                'overtime_hours' => 0,
            ]],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaps']);
        $this->assertDatabaseMissing('monthly_recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-02',
        ]);
    }

    public function test_monthly_recap_rejects_fractional_paid_days(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff', 'Staff');
        $employee = Employee::create([
            'employee_code' => 'EMP-RECAP-2',
            'name' => 'Fractional Recap Employee',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/monthly-recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'recaps' => [[
                'salary_profile_id' => null,
                'wfo_days' => 1.5,
                'wfh_days' => 0,
                'out_of_town_days' => 0,
                'business_trips' => 0,
                'training_days' => 0,
                'overtime_hours' => 1.5,
            ]],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaps.0.wfo_days']);
    }

    public function test_monthly_recap_stays_draft_until_sent_to_finance_and_records_late_count(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff', 'Staff');
        $employee = Employee::create([
            'employee_code' => 'EMP-RECAP-4',
            'name' => 'Draft Recap Employee',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/monthly-recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'recaps' => [[
                'salary_profile_id' => null,
                'wfo_days' => 10,
                'wfh_days' => 5,
                'out_of_town_days' => 1,
                'business_trips' => 2,
                'training_days' => 0,
                'overtime_hours' => 1.5,
                'late_count' => 3,
            ]],
        ])
            ->assertCreated();

        $recap = MonthlyRecap::where('employee_id', $employee->id)->firstOrFail();
        $this->assertFalse($recap->is_finalized);
        $this->assertSame(3, $recap->late_count);

        $this->postJson('/api/monthly-recaps/submit-to-finance', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
        ])->assertOk();

        $this->assertTrue($recap->fresh()->is_finalized);

        $this->postJson('/api/monthly-recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'recaps' => [[
                'salary_profile_id' => null,
                'wfo_days' => 12,
                'wfh_days' => 5,
                'out_of_town_days' => 1,
                'business_trips' => 2,
                'training_days' => 0,
                'overtime_hours' => 1.5,
                'late_count' => 4,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaps']);
    }

    public function test_finalized_monthly_recap_cannot_be_overwritten(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $grade = $this->grade('staff', 'Staff');
        $employee = Employee::create([
            'employee_code' => 'EMP-RECAP-3',
            'name' => 'Final Recap Employee',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'status' => 'active',
        ]);
        MonthlyRecap::create([
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'wfo_days' => 10,
            'total_mandays' => 10,
            'is_finalized' => true,
        ]);

        $response = $this->postJson('/api/monthly-recaps', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'recaps' => [[
                'salary_profile_id' => null,
                'wfo_days' => 12,
                'wfh_days' => 0,
                'out_of_town_days' => 0,
                'business_trips' => 0,
                'training_days' => 0,
                'overtime_hours' => 0,
            ]],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaps']);
        $this->assertSame('10.00', MonthlyRecap::where('employee_id', $employee->id)->first()->wfo_days);
    }

    public function test_future_promotion_does_not_change_the_employee_before_its_effective_date(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);
        $oldGrade = $this->grade('staff', 'Staff');
        $newGrade = $this->grade('manager', 'Manager', 2);
        $position = $this->allowanceType('position', 'Tunjangan Jabatan', 'flat', null);
        GradeAllowanceRate::create([
            'grade_id' => $newGrade->id,
            'allowance_type_id' => $position->id,
            'rate_amount' => 1200000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');
        $employee = Employee::create([
            'employee_code' => 'EMP-TEST-2',
            'name' => 'Future Promotion',
            'position' => $oldGrade->name,
            'grade_id' => $oldGrade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ]);
        SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $oldGrade->id,
            'position' => $oldGrade->name,
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
            'effective_from' => '2026-01-01',
            'salary_alg' => 'AES',
        ]);
        JobHistory::create([
            'employee_id' => $employee->id,
            'grade_id' => $oldGrade->id,
            'position' => $oldGrade->name,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/employees/{$employee->id}/mutate", [
            'mutation_type' => 'promotion',
            'grade_id' => $newGrade->id,
            'effective_from' => '2026-08-01',
            'notes' => 'Promosi terjadwal',
        ]);

        $response->assertOk()->assertJsonPath('status', 'scheduled');
        $this->assertSame($oldGrade->id, $employee->fresh()->grade_id);
        $this->assertDatabaseHas('salary_profiles', [
            'employee_id' => $employee->id,
            'grade_id' => $newGrade->id,
        ]);
        $this->assertSame(
            '2026-08-01',
            $employee->salaryProfiles()->where('grade_id', $newGrade->id)->first()->effective_from->toDateString()
        );

        $this->artisan('employees:sync-effective-jobs', ['--date' => '2026-08-01'])->assertSuccessful();
        $this->assertSame($newGrade->id, $employee->fresh()->grade_id);
    }

    public function test_promotion_must_target_grade_above_current_level(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $currentGrade = $this->grade('supervisor', 'Supervisor', 4);
        $lowerGrade = $this->grade('staff', 'Staff', 8);
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');

        $employee = Employee::create([
            'employee_code' => 'EMP-TEST-4',
            'name' => 'Invalid Promotion',
            'position' => $currentGrade->name,
            'grade_id' => $currentGrade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ]);

        SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $currentGrade->id,
            'position' => $currentGrade->name,
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
            'effective_from' => '2026-01-01',
            'salary_alg' => 'AES',
        ]);

        JobHistory::create([
            'employee_id' => $employee->id,
            'grade_id' => $currentGrade->id,
            'position' => $currentGrade->name,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->postJson("/api/employees/{$employee->id}/mutate", [
            'mutation_type' => 'promotion',
            'grade_id' => $lowerGrade->id,
            'effective_from' => '2026-08-01',
            'notes' => 'Uji validasi promosi',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grade_id']);

        $this->assertDatabaseMissing('salary_profiles', [
            'employee_id' => $employee->id,
            'grade_id' => $lowerGrade->id,
            'effective_from' => '2026-08-01',
        ]);
    }

    public function test_demotion_must_target_grade_below_current_level(): void
    {
        $hcga = User::factory()->create(['role' => 'hcga']);
        Sanctum::actingAs($hcga);

        $currentGrade = $this->grade('staff', 'Staff', 6);
        $higherGrade = $this->grade('assistant-manager', 'Assistant Manager', 3);
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');

        $employee = Employee::create([
            'employee_code' => 'EMP-TEST-5',
            'name' => 'Invalid Demotion',
            'position' => $currentGrade->name,
            'grade_id' => $currentGrade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ]);

        SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $currentGrade->id,
            'position' => $currentGrade->name,
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
            'effective_from' => '2026-01-01',
            'salary_alg' => 'AES',
        ]);

        JobHistory::create([
            'employee_id' => $employee->id,
            'grade_id' => $currentGrade->id,
            'position' => $currentGrade->name,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->postJson("/api/employees/{$employee->id}/mutate", [
            'mutation_type' => 'demotion',
            'grade_id' => $higherGrade->id,
            'effective_from' => '2026-08-01',
            'notes' => 'Uji validasi demosi',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grade_id']);

        $this->assertDatabaseMissing('salary_profiles', [
            'employee_id' => $employee->id,
            'grade_id' => $higherGrade->id,
            'effective_from' => '2026-08-01',
        ]);
    }

    public function test_auto_payroll_uses_master_rates_and_stores_nominal_as_cipher_only(): void
    {
        config(['crypto.payroll_write_alg' => 'AES']);
        $finance = User::factory()->create(['role' => 'fat']);
        $grade = $this->grade('staff', 'Staff');
        $position = $this->allowanceType('position', 'Tunjangan Jabatan', 'flat', null);
        $meal = $this->allowanceType('meal', 'Tunjangan Makan', 'per_mandays', 'total_mandays');
        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $position->id,
            'rate_amount' => 1200000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        GradeAllowanceRate::create([
            'grade_id' => $grade->id,
            'allowance_type_id' => $meal->id,
            'rate_amount' => 25000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');
        $employee = Employee::create([
            'employee_code' => 'EMP-TEST-3',
            'name' => 'Payroll Cipher',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ]);
        $profile = SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $grade->id,
            'position' => $grade->name,
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('1200000'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
            'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'effective_from' => '2026-07-01',
            'salary_alg' => 'AES',
        ]);
        MonthlyRecap::create([
            'employee_id' => $employee->id,
            'salary_profile_id' => $profile->id,
            'period_month' => '2026-07',
            'wfo_days' => 10,
            'total_mandays' => 10,
            'is_finalized' => true,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculateAndSave(
            $employee->id,
            '2026-07',
            $finance->id
        );
        $plain = app(PayrollCipherService::class)->decrypt($payroll->fresh());

        $this->assertNull($payroll->gaji_pokok);
        $this->assertNull($payroll->total_allowances);
        $this->assertNotNull($payroll->gaji_pokok_enc);
        $this->assertSame(1000000.0, (float) $plain['gaji_pokok']);
        $this->assertSame(1450000.0, (float) $plain['total_allowances']);
        $this->assertSame(2450000.0, (float) $plain['total']);
        $this->assertTrue($payroll->allowances()->whereNull('amount')->whereNotNull('amount_enc')->exists());
    }

    public function test_director_can_read_payroll_grid_and_detail_but_cannot_create_payroll(): void
    {
        config(['crypto.payroll_write_alg' => 'AES']);
        Storage::fake('public');
        $finance = User::factory()->create(['role' => 'fat']);
        $director = User::factory()->create(['role' => 'director']);
        $staff = User::factory()->create(['role' => 'staff']);
        $grade = $this->grade('staff', 'Staff');
        $employmentType = $this->employmentType('project', 'Project Partner');
        $workBasis = $this->workBasis('mandays', 'Mandays');
        $employee = Employee::create([
            'user_id' => $staff->id,
            'employee_code' => 'EMP-DIR-1',
            'name' => 'Director Payroll Read',
            'position' => $grade->name,
            'grade_id' => $grade->id,
            'employment_type_id' => $employmentType->id,
            'work_basis_id' => $workBasis->id,
            'status' => 'active',
        ]);
        $profile = SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $grade->id,
            'position' => $grade->name,
            'position_allowance' => 0,
            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
            'effective_from' => '2026-07-01',
            'salary_alg' => 'AES',
        ]);
        MonthlyRecap::create([
            'employee_id' => $employee->id,
            'salary_profile_id' => $profile->id,
            'period_month' => '2026-07',
            'wfo_days' => 10,
            'total_mandays' => 10,
            'is_finalized' => true,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculateAndSave(
            $employee->id,
            '2026-07',
            $finance->id
        );

        Sanctum::actingAs($director);
        $this->postJson('/api/payrolls/batch-preview', ['period_month' => '2026-07'])
            ->assertOk()
            ->assertJsonPath('results.0.payroll_status', 'draft');

        $this->postJson('/api/payrolls/auto', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
        ])->assertForbidden();

        $this->postJson('/api/payrolls/preview-calculation', [
            'employee_id' => $employee->id,
            'period_month' => '2026-07',
            'payroll_id' => $payroll->id,
        ])
            ->assertOk()
            ->assertJsonPath('employee_id', $employee->id);

        Sanctum::actingAs($finance);
        $this->postJson("/api/payrolls/{$payroll->id}/submit")->assertOk();

        Sanctum::actingAs($director);
        $this->postJson('/api/payrolls/batch-preview', ['period_month' => '2026-07'])
            ->assertOk()
            ->assertJsonPath('results.0.payroll_status', 'submitted');

        $this->postJson("/api/payrolls/{$payroll->id}/approve")
            ->assertOk()
            ->assertJsonPath('payroll.status', 'approved');

        Sanctum::actingAs($finance);
        $expectedPaidRef = 'TRF-202607-' . str_pad((string) $payroll->id, 5, '0', STR_PAD_LEFT);

        $this->post("/api/payrolls/{$payroll->id}/mark-paid", [
            'proof' => UploadedFile::fake()->create('bukti-transfer.pdf', 100, 'application/pdf'),
            'paid_note' => 'Transfer gaji via mobile banking.',
        ])
            ->assertOk()
            ->assertJsonPath('payroll.status', 'paid')
            ->assertJsonPath('payroll.paid_ref', $expectedPaidRef);

        $paidPayroll = $payroll->fresh();
        $this->assertSame('paid', $paidPayroll->status);
        Storage::disk('public')->assertExists($paidPayroll->paid_proof_path);

        Sanctum::actingAs($staff);
        $staffList = $this->getJson('/api/payrolls?periode=2026-07-01&status=paid')
            ->assertOk()
            ->assertJsonPath('0.status', 'paid')
            ->assertJsonPath('0.masked', false);

        $this->assertSame($payroll->id, $staffList->json('0.id'));

        $this->getJson("/api/payrolls/{$payroll->id}")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('paid_ref', $expectedPaidRef)
            ->assertJsonPath('masked', false);

        $this->get("/api/payrolls/{$payroll->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    private function grade(
        string $code,
        string $name,
        int $level = 8,
        string $baseSalaryBasis = 'daily',
        float $baseSalaryAmount = 100000
    ): Grade {
        return Grade::create([
            'code' => $code,
            'name' => $name,
            'level' => $level,
            'is_active' => true,
            'base_salary_basis' => $baseSalaryBasis,
            'default_base_salary_amount' => $baseSalaryAmount,
            'default_mandays_rate' => $baseSalaryBasis === 'daily' ? $baseSalaryAmount : null,
        ]);
    }

    private function allowanceType(string $code, string $name, string $calculationType, ?string $inputSource): AllowanceType
    {
        return AllowanceType::create([
            'code' => $code,
            'name' => $name,
            'calculation_type' => $calculationType,
            'input_source' => $inputSource,
            'applies_to' => 'all',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    private function employmentType(string $code, string $name): EmploymentType
    {
        return EmploymentType::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function workBasis(string $code, string $name): WorkBasis
    {
        return WorkBasis::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }
}
