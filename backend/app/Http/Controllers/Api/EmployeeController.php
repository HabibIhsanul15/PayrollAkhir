<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\AllowanceRateResolver;
use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(private AllowanceRateResolver $rateResolver) {}

    private function roleOf($user): string
    {
        return strtolower((string) ($user->role ?? ''));
    }

    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function inRoles($user, array $roles): bool
    {
        $r = $this->roleOf($user);
        $roles = array_map(fn ($x) => strtolower((string) $x), $roles);

        return in_array($r, $roles, true);
    }

    private function digitStringRules(int $maxLength, bool $sometimes = false): array
    {
        $rules = [];

        if ($sometimes) {
            $rules[] = 'sometimes';
        }

        return [
            ...$rules,
            'nullable',
            'string',
            "max:$maxLength",
            'regex:/^[0-9]+$/',
        ];
    }

    private function digitFieldMessages(): array
    {
        return [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'npwp.regex' => 'NPWP hanya boleh berisi angka.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ];
    }

    private function resolveBaseSalaryPayload(Position $Position, array $data): array
    {
        $basis = $data['base_salary_basis']
            ?? $Position->base_salary_basis
            ?? 'daily';

        $amount = array_key_exists('base_salary_amount', $data) && $data['base_salary_amount'] !== null && $data['base_salary_amount'] !== ''
            ? (float) $data['base_salary_amount']
            : (array_key_exists('mandays_rate', $data) && $data['mandays_rate'] !== null && $data['mandays_rate'] !== ''
                ? (float) $data['mandays_rate']
                : (float) ($Position->default_base_salary_amount ?? $Position->default_mandays_rate ?? 0));

        return [
            'basis' => $basis,
            'amount' => $amount,
        ];
    }

    private function resolveBaseSalaryFromProfile(Employee $employee, $profile, ?Position $Position = null): array
    {
        $Position ??= $profile->Position ?? $employee->Position;
        $alg = strtoupper((string) ($profile->salary_alg ?? 'AES'));

        $amount = $profile->base_salary_amount_enc
            ? CryptoService::decryptByAlg($profile->base_salary_amount_enc, $alg)
            : null;

        if ($amount === null || $amount === '') {
            if ($profile->base_salary_amount !== null) {
                $amount = (string) $profile->base_salary_amount;
            } elseif ($profile->mandays_rate_enc) {
                $amount = CryptoService::decryptByAlg($profile->mandays_rate_enc, $alg);
            } elseif ($profile->mandays_rate !== null) {
                $amount = (string) $profile->mandays_rate;
            } elseif ($Position?->default_base_salary_amount !== null) {
                $amount = (string) $Position->default_base_salary_amount;
            } elseif ($Position?->default_mandays_rate !== null) {
                $amount = (string) $Position->default_mandays_rate;
            }
        }

        $basis = $profile->base_salary_basis
            ?? $Position?->base_salary_basis
            ?? match ($employee->workBasis?->code) {
                'monthly' => 'monthly',
                default => 'daily',
            };

        return [
            'basis' => $basis ?: 'daily',
            'amount' => $amount,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // HCGA/FAT/DIRECTOR boleh lihat list
        if (! $this->inRoles($user, ['hcga', 'fat', 'director'])) {
            return $this->forbid();
        }

        $qStatus = $request->query('status'); // active/inactive/null
        $query = Employee::query()->orderBy('name');

        if ($qStatus) {
            $query->where('status', $qStatus);
        }

        return $query->with([
            'position:id,code,name,is_active,level',
            'employmentType:id,code,name',
        ])->get([
            'id',
            'employee_code',
            'name',
            'status',
            'user_id',
            'position_id',
            'join_date',
            'employment_type_id',
            'work_basis_id',
        ])->map(function (Employee $employee) {
            return [
                ...$employee->toArray(),
                'join_date' => optional($employee->join_date)->toDateString(),
                'has_account' => (bool) $employee->user_id,
                'payroll_readiness' => $employee->payrollReadiness(),
            ];
        });
    }

    public function nextCode(Request $request)
    {
        $user = $request->user();

        // hanya HCGA
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        $last = Employee::whereNotNull('employee_code')
            ->where('employee_code', 'like', 'EMP-%')
            ->orderByDesc('id')
            ->value('employee_code');

        $nextNumber = 1;
        if ($last && preg_match('/EMP-(\d{1,})$/', $last, $m)) {
            $nextNumber = ((int) $m[1]) + 1;
        }

        $nextCode = 'EMP-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'next_employee_code' => $nextCode,
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);
        $currentProfile = $employee->currentSalaryProfile();
        $currentPosition = Position::find($currentProfile?->position_id ?? $employee->position_id);
        $baseSalary = $currentProfile
            ? $this->resolveBaseSalaryFromProfile($employee, $currentProfile, $currentPosition)
            : null;

        $isOwner = $employee->user_id && (int) $employee->user_id === (int) $user->id;
        $canSeePayrollNominal = in_array($role, ['fat', 'director'], true);
        $positionPayload = $currentPosition?->toArray();

        if ($positionPayload && ! $canSeePayrollNominal) {
            unset($positionPayload['default_base_salary_amount'], $positionPayload['default_mandays_rate']);
        }

        // akses dasar:
        // - HCGA/FAT/DIRECTOR boleh lihat employee mana pun
        // - STAFF hanya boleh lihat dirinya sendiri
        if (! in_array($role, ['hcga', 'fat', 'director'], true)) {
            if (! ($role === 'staff' && $isOwner)) {
                return $this->forbid();
            }
        }

        // field base (aman)
        $base = [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'join_date' => optional($employee->join_date)->toDateString(),
            'department' => $employee->department,
            'position' => $currentProfile?->position ?? $employee->Position?->name ?? $employee->position,
            'status' => $employee->status,
            'user_id' => $employee->user_id,

            // Phase 1 fields:
            'position_id' => $currentProfile?->position_id ?? $employee->position_id,
            'employment_type_id' => $employee->employment_type_id,
            'work_basis_id' => $employee->work_basis_id,
            'num_toddlers' => (int) $employee->num_toddlers,
            'is_trainer' => (bool) $employee->is_trainer,
            'is_on_probation' => (bool) $employee->is_on_probation,
            'Position' => $positionPayload,
            'employment_type' => $employee->employmentType,
            'work_basis' => $employee->workBasis,
            'salary_profile_summary' => $baseSalary ? [
                'base_salary_basis' => $baseSalary['basis'],
                'base_salary_amount' => $canSeePayrollNominal && $baseSalary['amount'] !== null
                    ? (string) $baseSalary['amount']
                    : null,
                'position_allowance' => $canSeePayrollNominal ? $currentProfile?->position_allowance : null,
                'masked' => ! $canSeePayrollNominal,
            ] : null,
            'payroll_readiness' => $employee->payrollReadiness(),
        ];

        $alg = strtoupper((string) ($employee->pii_alg ?? 'AES'));

        // aturan lihat PII vs Bank
        $canSeePII = ($role === 'hcga') || $isOwner; // NIK/NPWP/Phone/Address
        $canSeeBank = in_array($role, ['hcga', 'fat'], true) || $isOwner; // bank utk transfer

        if ($canSeePII) {
            $base += [
                'nik' => CryptoService::readEncryptedOrPlain($employee->nik_enc, $employee->nik, $alg),
                'npwp' => CryptoService::readEncryptedOrPlain($employee->npwp_enc, $employee->npwp, $alg),
                'phone' => CryptoService::readEncryptedOrPlain($employee->phone_enc, $employee->phone, $alg),
                'address' => CryptoService::readEncryptedOrPlain($employee->address_enc, $employee->address, $alg),
            ];
        }

        if ($canSeeBank) {
            $base += [
                'bank_name' => $employee->bank_name,
                'bank_account_name' => $employee->bank_account_name,
                'bank_account_number' => CryptoService::readEncryptedOrPlain(
                    $employee->bank_account_number_enc,
                    $employee->bank_account_number,
                    $alg
                ),
            ];
        }

        // ====== tambahin ACCOUNT INFO (emp.user) ======
        // Aturan aman:
        // - HCGA: boleh lihat user detail (id,name,email,role)
        // - OWNER: boleh lihat user detail miliknya sendiri
        // - FAT/DIRECTOR: tidak perlu email, jadi tidak dikirim
        if ($employee->user_id && ($role === 'hcga' || $isOwner)) {
            $u = User::query()
                ->select(['id', 'name', 'email', 'role'])
                ->find($employee->user_id);

            $base['user'] = $u ? [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
            ] : null;
        } else {
            // biar frontend bisa tahu "punya akun atau tidak" tanpa bocorin email
            $base['user'] = $employee->user_id ? [
                'id' => (int) $employee->user_id,
            ] : null;
        }

        // masked kalau sama sekali gak dapat info sensitif
        if (! $canSeePII && ! $canSeeBank) {
            $base['masked'] = true;
        }

        return response()->json($base);
    }

    public function createUser(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        if ($employee->user_id) {
            return response()->json(['message' => 'Pegawai ini sudah memiliki akun.'], 400);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', \Illuminate\Validation\Rule::in(['staff', 'hcga', 'fat', 'director'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $account = User::create([
            'name' => $employee->name,
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        $employee->user_id = $account->id;
        $employee->save();

        return response()->json([
            'message' => 'Akun berhasil dibuat dan dihubungkan.',
            'user' => $account,
        ], 201);
    }

    public function resetPassword(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        if (! $employee->user_id) {
            return response()->json(['message' => 'Karyawan ini tidak memiliki akun login.'], 400);
        }

        $newPassword = 'Password123!'; 
        $account = \App\Models\User::find($employee->user_id);
        $account->password = \Illuminate\Support\Facades\Hash::make($newPassword);
        $account->save();

        return response()->json([
            'message' => 'Password berhasil di-reset.',
            'new_password' => $newPassword
        ]);
    }

    public function salaryProfile(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);

        // Nominal profil gaji hanya untuk Finance dan Director.
        if (! in_array($role, ['fat', 'director'], true)) {
            return $this->forbid();
        }

        $date = $request->query('date', now()->toDateString());
        $profile = $employee->currentSalaryProfile($date);

        if (! $profile) {
            return response()->json(['message' => 'Salary profile not found'], 404);
        }

        $alg = strtoupper((string) ($profile->salary_alg ?? 'AES'));

        $positionVal = $profile->position_allowance_enc ? CryptoService::decryptByAlg($profile->position_allowance_enc, $alg) : null;
        $allow = $profile->allowance_fixed_enc ? (float) CryptoService::decryptByAlg($profile->allowance_fixed_enc, $alg) : (float) $profile->allowance_fixed;
        $ded = $profile->deduction_fixed_enc ? (float) CryptoService::decryptByAlg($profile->deduction_fixed_enc, $alg) : (float) $profile->deduction_fixed;

        $effectivepositionId = $profile->position_id ?? $employee->position_id;
        $effectivePosition = $profile->position ?? $employee->position;

        $Position = $effectivepositionId ? \App\Models\Position::find($effectivepositionId) : null;
        $baseSalary = $this->resolveBaseSalaryFromProfile($employee, $profile, $Position);

        $is_using_default_base = false;
        if ($positionVal === null || $positionVal === '') {
            if ($profile->position_allowance > 0) {
                $base = (float) $profile->position_allowance;
                $is_using_default_base = false;
            } else {
                $posRate = $Position ? $this->rateResolver->resolveByCode($Position->id, 'position', $date) : null;
                $base = $posRate ? (float) $posRate->rate_amount : 0.0;
                $is_using_default_base = true;
            }
        } else {
            $base = (float) $positionVal;
            $is_using_default_base = false;
        }

        $is_using_default_salary = $profile->base_salary_amount_enc === null
            && $profile->base_salary_amount === null
            && $profile->mandays_rate_enc === null
            && $profile->mandays_rate === null;

        return response()->json([
            'employee_id' => $employee->id,
            'effective_from' => $profile->effective_from->toDateString(),
            'position_id' => $effectivepositionId,
            'position' => $effectivePosition,
            'base_salary_basis' => $baseSalary['basis'],
            'base_salary_amount' => $baseSalary['amount'] !== null ? (string) $baseSalary['amount'] : null,
            'position_allowance' => (string) $base,
            'allowance_fixed' => (string) $allow,
            'deduction_fixed' => (string) $ded,
            'mandays_rate' => $baseSalary['basis'] === 'daily'
                ? ($baseSalary['amount'] !== null ? (string) $baseSalary['amount'] : null)
                : null,
            'is_using_default_base' => $is_using_default_base,
            'is_using_default_salary' => $is_using_default_salary,
            'suggested_total' => (string) ($base + $allow - $ded),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // create employee: hanya HCGA
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'join_date' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:255'],

            'nik' => $this->digitStringRules(32),
            'npwp' => $this->digitStringRules(32),
            'phone' => $this->digitStringRules(20),
            'address' => ['nullable', 'string', 'max:500'],

            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => $this->digitStringRules(50),

            'pii_alg' => ['nullable', 'in:AES,RSA'],

            // Phase 1 fields:
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'employment_type_id' => ['nullable', Rule::exists('employment_types', 'id')->where('is_active', true)],
            'num_toddlers' => ['nullable', 'integer', 'min:0'],
            'is_trainer' => ['nullable', 'boolean'],
            'is_on_probation' => ['nullable', 'boolean'],

            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'base_salary_basis' => ['nullable', Rule::in(['daily', 'monthly'])],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],

            // Create account fields
            'create_account' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if:create_account,true', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'required_if:create_account,true', \Illuminate\Validation\Rule::in(['staff', 'hcga', 'fat', 'director'])],
            'password' => ['nullable', 'required_if:create_account,true', 'string', 'min:8', 'confirmed'],
        ], $this->digitFieldMessages());

        $employee = DB::transaction(function () use ($data) {
            $Position = Position::findOrFail($data['position_id']);
            $salaryConfig = $this->resolveBaseSalaryPayload($Position, $data);
            $effectiveFrom = $data['join_date'] ?? now()->startOfMonth()->toDateString();
            $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position', $effectiveFrom);
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null
                ? (float) $data['position_allowance']
                : (float) ($positionRate?->rate_amount ?? 0);
            $baseSalaryAmount = $salaryConfig['amount'];

            $userId = null;
            if (! empty($data['create_account'])) {
                $account = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'password' => Hash::make($data['password']),
                ]);
                $userId = $account->id;
            }

            $piiAlg = strtoupper((string) ($data['pii_alg'] ?? 'AES'));
            $encryptPii = fn (string $value) => $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);

            $employeeData = $data;
            $employeeData['user_id'] = $userId;
            $employeeData['position'] = $Position->name;
            $employeeData['status'] = 'active';
            $employeeData['join_date'] = $data['join_date'] ?? $effectiveFrom;
            $employeeData['num_toddlers'] = $data['num_toddlers'] ?? 0;
            $employeeData['is_trainer'] = $data['is_trainer'] ?? false;
            $employeeData['is_on_probation'] = $data['is_on_probation'] ?? false;
            $employeeData['nik_enc'] = ! empty($data['nik']) ? $encryptPii((string) $data['nik']) : null;
            $employeeData['npwp_enc'] = ! empty($data['npwp']) ? $encryptPii((string) $data['npwp']) : null;
            $employeeData['phone_enc'] = ! empty($data['phone']) ? $encryptPii((string) $data['phone']) : null;
            $employeeData['address_enc'] = ! empty($data['address']) ? $encryptPii((string) $data['address']) : null;
            $employeeData['bank_account_number_enc'] = ! empty($data['bank_account_number'])
                ? $encryptPii((string) $data['bank_account_number'])
                : null;
            $employeeData['pii_alg'] = $piiAlg;
            $employeeData['pii_key_id'] = $piiAlg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId();

            foreach (['nik', 'npwp', 'phone', 'address', 'bank_account_number'] as $plainField) {
                $employeeData[$plainField] = null;
            }

            $employee = Employee::create($employeeData);
            $salaryAlg = 'AES';

            $employee->salaryProfiles()->create([
                'position_id' => $Position->id,
                'position' => $Position->name,
                'base_salary_basis' => $salaryConfig['basis'],
                'base_salary_amount' => 0,
                'position_allowance' => 0,
                'base_salary_amount_enc' => CryptoService::encryptAESGCM((string) $baseSalaryAmount),
                'mandays_rate' => null,
                'allowance_fixed' => 0,
                'deduction_fixed' => 0,
                'effective_from' => $effectiveFrom,
                'position_allowance_enc' => CryptoService::encryptAESGCM((string) $base),
                'mandays_rate_enc' => CryptoService::encryptAESGCM((string) $baseSalaryAmount),
                'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'salary_alg' => $salaryAlg,
                'salary_key_id' => CryptoService::keyId(),
            ]);

            $employee->jobHistories()->create([
                'position_id' => $Position->id,
                'position' => $Position->name,
                'start_date' => $effectiveFrom,
                'status' => 'active',
                'notes' => 'Penempatan awal karyawan',
            ]);

            return $employee;
        });

        return response()->json([
            'employee' => $employee->fresh(['Position', 'employmentType', 'workBasis', 'salaryProfiles']),
        ], 201);
    }

    public function salaryProfilesList(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);
        
        $isSelf = (string) $user->employee_id === (string) $employee->id;
        
        if (! in_array($role, ['hcga', 'fat', 'director'], true) && !$isSelf) {
            return $this->forbid();
        }

        $canSeeNominal = in_array($role, ['fat', 'director'], true) || $isSelf;

        $profiles = $employee->salaryProfiles()->orderBy('effective_from', 'desc')->get();
        $employeePosition = $employee->Position;

        $results = $profiles->map(function ($p) use ($employeePosition, $canSeeNominal, $employee) {
            $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));

            $positionVal = $p->position_allowance_enc ? CryptoService::decryptByAlg($p->position_allowance_enc, $alg) : null;
            $allow = $p->allowance_fixed_enc ? (float) CryptoService::decryptByAlg($p->allowance_fixed_enc, $alg) : (float) $p->allowance_fixed;
            $ded = $p->deduction_fixed_enc ? (float) CryptoService::decryptByAlg($p->deduction_fixed_enc, $alg) : (float) $p->deduction_fixed;

            $effectivepositionId = $p->position_id ?? null;
            $Position = $effectivepositionId ? \App\Models\Position::find($effectivepositionId) : $employeePosition;
            $baseSalary = $this->resolveBaseSalaryFromProfile($employee, $p, $Position);

            $is_using_default_base = false;
            if ($positionVal === null || $positionVal === '') {
                if ($p->position_allowance > 0) {
                    $base = (float) $p->position_allowance;
                    $is_using_default_base = false;
                } else {
                    $posRate = $Position
                        ? $this->rateResolver->resolveByCode($Position->id, 'position', $p->effective_from)
                        : null;
                    $base = $posRate ? (float) $posRate->rate_amount : 0.0;
                    $is_using_default_base = true;
                }
            } else {
                $base = (float) $positionVal;
                $is_using_default_base = false;
            }

            return [
                'id' => $p->id,
                'effective_from' => $p->effective_from->toDateString(),
                'position_id' => $effectivepositionId,
                'position_name' => $Position ? $Position->name : '-',
                'position' => $p->position,
                'base_salary_basis' => $baseSalary['basis'],
                'base_salary_amount' => $canSeeNominal ? ($baseSalary['amount'] !== null ? (string) $baseSalary['amount'] : null) : null,
                'position_allowance' => $canSeeNominal ? (string) $base : null,
                'allowance_fixed' => $canSeeNominal ? (string) $allow : null,
                'deduction_fixed' => $canSeeNominal ? (string) $ded : null,
                'is_using_default_base' => $is_using_default_base,
                'suggested_total' => $canSeeNominal ? (string) ($base + $allow - $ded) : null,
                'masked' => ! $canSeeNominal,
                'created_at' => $p->created_at->toISOString(),
            ];
        });

        return response()->json($results);
    }

    public function storeSalaryProfile(Request $request, Employee $employee)
    {
        $user = $request->user();

        // Override profil gaji individual hanya dilakukan Finance.
        if (! $this->inRoles($user, ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'allowance_fixed' => ['nullable', 'numeric', 'min:0'],
            'deduction_fixed' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'base_salary_basis' => ['nullable', Rule::in(['daily', 'monthly'])],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],

            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate_per_hour' => ['nullable', 'numeric', 'min:0'],
            'late_penalty_per_minute' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],

            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],

            'salary_alg' => ['nullable', 'in:AES,RSA'],
        ]);

        $profile = DB::transaction(function () use ($data, $employee) {
            $Position = Position::findOrFail($data['position_id']);
            $effectiveFrom = \Carbon\Carbon::parse($data['effective_from'])->startOfDay();
            $alg = strtoupper((string) ($data['salary_alg'] ?? 'AES'));
            $encrypt = fn (string $value) => $alg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);
            $salaryConfig = $this->resolveBaseSalaryPayload($Position, $data);

            $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position', $effectiveFrom);
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null
                ? (float) $data['position_allowance']
                : (float) ($positionRate?->rate_amount ?? 0);
            $baseSalaryAmount = $salaryConfig['amount'];
            $allow = (float) ($data['allowance_fixed'] ?? 0);
            $deduction = (float) ($data['deduction_fixed'] ?? 0);
            $daily = array_key_exists('daily_rate', $data) ? (float) ($data['daily_rate'] ?? 0) : null;
            $overtime = array_key_exists('overtime_rate_per_hour', $data) ? (float) ($data['overtime_rate_per_hour'] ?? 0) : null;
            $latePenalty = array_key_exists('late_penalty_per_minute', $data) ? (float) ($data['late_penalty_per_minute'] ?? 0) : null;

            $profile = $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => $effectiveFrom->toDateString()],
                [
                    'position_id' => $Position->id,
                    'position' => $Position->name,
                    'base_salary_basis' => $salaryConfig['basis'],
                    'base_salary_amount' => 0,
                    'position_allowance' => 0,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                    'daily_rate' => null,
                    'overtime_rate_per_hour' => null,
                    'late_penalty_per_minute' => null,
                    'mandays_rate' => null,
                    'base_salary_amount_enc' => $encrypt((string) $baseSalaryAmount),
                    'position_allowance_enc' => $encrypt((string) $base),
                    'allowance_fixed_enc' => $encrypt((string) $allow),
                    'deduction_fixed_enc' => $encrypt((string) $deduction),
                    'daily_rate_enc' => $daily !== null ? $encrypt((string) $daily) : null,
                    'overtime_rate_per_hour_enc' => $overtime !== null ? $encrypt((string) $overtime) : null,
                    'late_penalty_per_minute_enc' => $latePenalty !== null ? $encrypt((string) $latePenalty) : null,
                    'mandays_rate_enc' => $encrypt((string) $baseSalaryAmount),
                    'salary_alg' => $alg,
                    'salary_key_id' => $alg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId(),
                ]
            );

            $previous = $employee->jobHistories()
                ->whereDate('start_date', '<', $effectiveFrom->toDateString())
                ->orderByDesc('start_date')
                ->first();

            if ($previous) {
                $previous->update([
                    'end_date' => $effectiveFrom->copy()->subDay()->toDateString(),
                    'status' => $effectiveFrom->isFuture() ? 'active' : 'inactive',
                ]);
            }

            $employee->jobHistories()->updateOrCreate(
                ['start_date' => $effectiveFrom->toDateString()],
                [
                    'position_id' => $Position->id,
                    'position' => $Position->name,
                    'end_date' => null,
                    'status' => $effectiveFrom->isFuture() ? 'inactive' : 'active',
                    'notes' => $effectiveFrom->isFuture() ? 'Perubahan jabatan terjadwal' : 'Perubahan jabatan diterapkan',
                ]
            );

            if (! $effectiveFrom->isFuture()) {
                $employee->update(['position_id' => $Position->id, 'position' => $Position->name]);
            }

            return $profile;
        });

        return response()->json([
            'salary_profile' => $profile,
        ], 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $user = $request->user();

        // update employee: hanya HCGA
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        // NOTE: employee_code sengaja TIDAK BOLEH diupdate (read-only)
        $data = $request->validate([
            'employee_code' => ['sometimes'], // <- diterima biar validator gak error kalau kekirim, tapi nanti kita buang
            'name' => ['sometimes', 'string', 'max:255'],
            'join_date' => ['sometimes', 'nullable', 'date'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],

            'nik' => $this->digitStringRules(32, true),
            'npwp' => $this->digitStringRules(32, true),
            'phone' => $this->digitStringRules(20, true),
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_number' => $this->digitStringRules(50, true),

            'employment_type_id' => ['sometimes', 'nullable', Rule::exists('employment_types', 'id')->where('is_active', true)],
            'num_toddlers' => ['sometimes', 'integer', 'min:0'],
            'is_trainer' => ['sometimes', 'boolean'],
            'is_on_probation' => ['sometimes', 'boolean'],
            'position_id' => ['sometimes', 'nullable', Rule::exists('positions', 'id')->where('is_active', true)],
        ], $this->digitFieldMessages());

        // 🚨 HARD BLOCK: jangan pernah update employee_code
        unset($data['employee_code']);

        $piiAlg = strtoupper((string) ($employee->pii_alg ?? 'AES'));

        $encPII = function (string $v) use ($piiAlg) {
            return $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        if (array_key_exists('nik', $data)) {
            $data['nik_enc'] = ! empty($data['nik']) ? $encPII((string) $data['nik']) : null;
            $data['nik'] = null;
        }
        if (array_key_exists('npwp', $data)) {
            $data['npwp_enc'] = ! empty($data['npwp']) ? $encPII((string) $data['npwp']) : null;
            $data['npwp'] = null;
        }
        if (array_key_exists('phone', $data)) {
            $data['phone_enc'] = ! empty($data['phone']) ? $encPII((string) $data['phone']) : null;
            $data['phone'] = null;
        }
        if (array_key_exists('address', $data)) {
            $data['address_enc'] = ! empty($data['address']) ? $encPII((string) $data['address']) : null;
            $data['address'] = null;
        }
        if (array_key_exists('bank_account_number', $data)) {
            $data['bank_account_number_enc'] = ! empty($data['bank_account_number'])
                ? $encPII((string) $data['bank_account_number'])
                : null;
            $data['bank_account_number'] = null;
        }

        if (collect(['nik_enc', 'npwp_enc', 'phone_enc', 'address_enc', 'bank_account_number_enc'])->contains(fn ($field) => array_key_exists($field, $data))) {
            $data['pii_key_id'] = $piiAlg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId();
        }

        DB::transaction(function () use ($data, $employee, $piiAlg) {
            // Jika employee belum punya jabatan dan dikirim position_id baru (Penempatan Awal)
            if (empty($employee->position_id) && !empty($data['position_id'])) {
                $Position = Position::findOrFail($data['position_id']);
                $effectiveFrom = $data['join_date'] ?? $employee->join_date ?? now()->startOfMonth()->toDateString();
                
                // Gunakan default configurasi
                $salaryConfig = $this->resolveBaseSalaryPayload($Position, []);
                $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position', $effectiveFrom);
                $base = (float) ($positionRate?->rate_amount ?? 0);
                $baseSalaryAmount = $salaryConfig['amount'];
                
                $encryptPii = fn (string $value) => $piiAlg === 'RSA'
                    ? CryptoService::encryptRSA($value)
                    : CryptoService::encryptAESGCM($value);

                $employee->salaryProfiles()->create([
                    'position_id' => $Position->id,
                    'position' => $Position->name,
                    'base_salary_basis' => $salaryConfig['basis'],
                    'base_salary_amount' => 0,
                    'position_allowance' => 0,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                    'base_salary_amount_enc' => $encryptPii((string) $baseSalaryAmount),
                    'position_allowance_enc' => $encryptPii((string) $base),
                    'salary_alg' => $piiAlg,
                    'salary_key_id' => $piiAlg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId(),
                    'effective_from' => \Carbon\Carbon::parse($effectiveFrom)->startOfDay()->toDateString(),
                ]);

                $employee->jobHistories()->create([
                    'position_id' => $Position->id,
                    'position_name' => $Position->name,
                    'start_date' => $effectiveFrom,
                    'notes' => 'Penempatan awal (Update)',
                ]);
                $data['position'] = $Position->name;
            } else {
                // Jangan update position_id kalau sudah punya
                unset($data['position_id']);
            }

            $employee->update($data);
        });

        // sinkron nama user kalau employee punya akun
        if (array_key_exists('name', $data) && $employee->user_id) {
            User::where('id', $employee->user_id)->update([
                'name' => $data['name'],
            ]);
        }

        return response()->json([
            'message' => 'Employee updated',
            'employee' => $employee->fresh(['Position', 'employmentType', 'workBasis']),
        ]);
    }

    public function destroy(Request $request, Employee $employee)
    {
        $user = $request->user();

        // delete employee: hanya HCGA
        if (! $this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        if ($employee->payrolls()->exists()) {
            return response()->json([
                'message' => 'Employee tidak bisa dihapus karena sudah memiliki payroll.',
            ], 422);
        }

        $employee->salaryProfiles()->delete();
        $employee->jobHistories()->delete();
        $employee->delete();

        return response()->json(['message' => 'Employee deleted']);
    }

    public function jobHistories(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);
        $isOwner = $employee->user_id && (int) $employee->user_id === (int) $user->id;

        if (! in_array($role, ['hcga', 'fat', 'director'], true) && ! $isOwner) {
            return $this->forbid();
        }

        $histories = $employee->jobHistories()->with('Position')->orderBy('start_date', 'desc')->get();

        return response()->json($histories);
    }
}
