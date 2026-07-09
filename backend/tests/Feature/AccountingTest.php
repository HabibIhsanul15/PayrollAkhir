<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\EmploymentType;
use App\Models\WorkBasis;
use App\Models\Payroll;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Services\AccountingService;
use App\Services\CryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed COA
        $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);
        $this->seed(\Database\Seeders\EmploymentTypeSeeder::class);
        $this->seed(\Database\Seeders\WorkBasisSeeder::class);
        $this->seed(\Database\Seeders\GradeSeeder::class);
    }

    public function test_journal_is_created_only_when_all_payrolls_in_period_are_approved()
    {
        $user = User::factory()->create(['role' => 'fat']);
        $director = User::factory()->create(['role' => 'director']);

        $emp1 = Employee::create([
            'employee_code' => 'EMP-01',
            'name' => 'Karyawan 1',
            'status' => 'active',
            'grade_id' => Grade::first()->id,
            'employment_type_id' => EmploymentType::first()->id,
            'work_basis_id' => WorkBasis::first()->id,
        ]);

        $emp2 = Employee::create([
            'employee_code' => 'EMP-02',
            'name' => 'Karyawan 2',
            'status' => 'active',
            'grade_id' => Grade::first()->id,
            'employment_type_id' => EmploymentType::first()->id,
            'work_basis_id' => WorkBasis::first()->id,
        ]);

        $periode = '2026-07-01';

        // Gaji Pokok, Tunjangan, Potongan
        $p1 = Payroll::create([
            'user_id' => $user->id,
            'employee_id' => $emp1->id,
            'periode' => $periode,
            'status' => 'requested',
            'gaji_pokok' => 5000000,
            'tunjangan' => 1000000,
            'potongan' => 200000,
            'total' => 5800000,
            'salary_alg' => 'AES',
        ]);

        $p2 = Payroll::create([
            'user_id' => $user->id,
            'employee_id' => $emp2->id,
            'periode' => $periode,
            'status' => 'requested',
            'gaji_pokok' => 4000000,
            'tunjangan' => 500000,
            'potongan' => 100000,
            'total' => 4400000,
            'salary_alg' => 'AES',
        ]);

        // Mock decrypt method in AccountingService so we don't depend on actual keys in tests
        $this->mock(AccountingService::class, function ($mock) use ($p1, $p2) {
            $mock->shouldReceive('decryptPayrollValues')
                ->with(\Mockery::on(fn($p) => $p->id === $p1->id))
                ->andReturn([
                    'gaji_pokok' => 5000000.0,
                    'tunjangan' => 1000000.0,
                    'potongan' => 200000.0,
                    'total' => 5800000.0,
                ]);

            $mock->shouldReceive('decryptPayrollValues')
                ->with(\Mockery::on(fn($p) => $p->id === $p2->id))
                ->andReturn([
                    'gaji_pokok' => 4000000.0,
                    'tunjangan' => 500000.0,
                    'potongan' => 100000.0,
                    'total' => 4400000.0,
                ]);

            $mock->makePartial();
        });

        // 1. Approve Payroll 1
        $response = $this->actingAs($director)->postJson("/api/payrolls/{$p1->id}/approve", [
            'approval_note' => 'Approve 1',
        ]);
        $response->assertStatus(200);

        // Pastikan jurnal belum terbentuk karena p2 belum approved
        $this->assertEquals(0, JournalEntry::where('journal_type', 'ACCRUAL')->count());

        // 2. Approve Payroll 2
        $response2 = $this->actingAs($director)->postJson("/api/payrolls/{$p2->id}/approve", [
            'approval_note' => 'Approve 2',
        ]);
        $response2->assertStatus(200);

        // Pastikan jurnal ACCRUAL sekarang sudah terbentuk
        $this->assertEquals(1, JournalEntry::where('journal_type', 'ACCRUAL')->count());

        $entry = JournalEntry::where('journal_type', 'ACCRUAL')->first();
        $this->assertEquals('JU-202607-0001', $entry->journal_number);
        $this->assertEquals('posted', $entry->status);

        // Total Gaji Pokok = 5jt + 4jt = 9jt
        // Total Tunjangan = 1jt + 500rb = 1.5jt
        // Total Potongan = 200rb + 100rb = 300rb
        // Total Gaji Bersih = 5.8jt + 4.4jt = 10.2jt
        $debitSum = $entry->items()->sum('debit');
        $creditSum = $entry->items()->sum('credit');

        // Balanced check (9jt + 1.5jt = 10.5jt)
        $this->assertEquals(10500000.0, $debitSum);
        $this->assertEquals(10500000.0, $creditSum);
    }

    public function test_rejection_deletes_accrual_journal()
    {
        $user = User::factory()->create(['role' => 'fat']);
        $director = User::factory()->create(['role' => 'director']);

        $emp1 = Employee::create([
            'employee_code' => 'EMP-01',
            'name' => 'Karyawan 1',
            'status' => 'active',
            'grade_id' => Grade::first()->id,
            'employment_type_id' => EmploymentType::first()->id,
            'work_basis_id' => WorkBasis::first()->id,
        ]);

        $periode = '2026-07-01';

        $p1 = Payroll::create([
            'user_id' => $user->id,
            'employee_id' => $emp1->id,
            'periode' => $periode,
            'status' => 'requested',
            'gaji_pokok' => 5000000,
            'tunjangan' => 1000000,
            'potongan' => 200000,
            'total' => 5800000,
            'salary_alg' => 'AES',
        ]);

        $this->mock(AccountingService::class, function ($mock) use ($p1) {
            $mock->shouldReceive('decryptPayrollValues')->andReturn([
                'gaji_pokok' => 5000000.0,
                'tunjangan' => 1000000.0,
                'potongan' => 200000.0,
                'total' => 5800000.0,
            ]);
            $mock->makePartial();
        });

        // 1. Approve
        $this->actingAs($director)->postJson("/api/payrolls/{$p1->id}/approve");
        $this->assertEquals(1, JournalEntry::where('journal_type', 'ACCRUAL')->count());

        // 2. Reject
        $this->actingAs($director)->postJson("/api/payrolls/{$p1->id}/reject", [
            'approval_note' => 'Batal dulu',
        ]);

        // Jurnal ACCRUAL harus terhapus
        $this->assertEquals(0, JournalEntry::where('journal_type', 'ACCRUAL')->count());
    }

    public function test_payment_journal_created_only_when_all_payrolls_are_paid()
    {
        // Mock Storage for file upload
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create(['role' => 'fat']);
        $director = User::factory()->create(['role' => 'director']);

        $emp1 = Employee::create([
            'employee_code' => 'EMP-01',
            'name' => 'Karyawan 1',
            'status' => 'active',
            'grade_id' => Grade::first()->id,
            'employment_type_id' => EmploymentType::first()->id,
            'work_basis_id' => WorkBasis::first()->id,
        ]);

        $periode = '2026-07-01';

        $p1 = Payroll::create([
            'user_id' => $user->id,
            'employee_id' => $emp1->id,
            'periode' => $periode,
            'status' => 'approved',
            'gaji_pokok' => 5000000,
            'tunjangan' => 1000000,
            'potongan' => 200000,
            'total' => 5800000,
            'salary_alg' => 'AES',
        ]);

        $this->mock(AccountingService::class, function ($mock) use ($p1) {
            $mock->shouldReceive('decryptPayrollValues')->andReturn([
                'gaji_pokok' => 5000000.0,
                'tunjangan' => 1000000.0,
                'potongan' => 200000.0,
                'total' => 5800000.0,
            ]);
            $mock->makePartial();
        });

        // Upload fake proof file
        $file = \Illuminate\Http\UploadedFile::fake()->create('proof.pdf', 500);

        // Mark Paid
        $response = $this->actingAs($user)->postJson("/api/payrolls/{$p1->id}/mark-paid", [
            'proof' => $file,
            'paid_ref' => 'REF-123',
            'paid_note' => 'Gaji Lunas',
        ]);
        $response->assertStatus(200);

        // Harus terbentuk jurnal PAYMENT
        $this->assertEquals(1, JournalEntry::where('journal_type', 'PAYMENT')->count());

        $entry = JournalEntry::where('journal_type', 'PAYMENT')->first();
        $this->assertEquals('posted', $entry->status);

        // Debit Utang Gaji, Kredit Kas (sebesar 5.8jt)
        $debitItem = $entry->items()->where('debit', '>', 0)->first();
        $creditItem = $entry->items()->where('credit', '>', 0)->first();

        $this->assertEquals('20100', $debitItem->account->code);
        $this->assertEquals(5800000.0, $debitItem->debit);
        
        $this->assertEquals('10100', $creditItem->account->code);
        $this->assertEquals(5800000.0, $creditItem->credit);
    }
}
