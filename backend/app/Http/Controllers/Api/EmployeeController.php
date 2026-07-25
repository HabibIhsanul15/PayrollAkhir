<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\AllowanceRateResolver;
use App\Services\CryptoService;
use App\Services\SensitiveFieldCipherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(
        private AllowanceRateResolver $rateResolver,
        private SensitiveFieldCipherService $sensitiveCipher,
    ) {}

    private function roleOf(mixed $user): string
    {
        return strtolower((string) ($user->role ?? ''));
    }

    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function inRoles(mixed $user, array $roles): bool
    {
        $r = $this->roleOf($user);
        $roles = array_map(fn (mixed $x) => strtolower((string) $x), $roles);

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

    private function indonesianMobilePhoneRules(bool $sometimes = false): array
    {
        $rules = [];

        if ($sometimes) {
            $rules[] = 'sometimes';
        }

        return [
            ...$rules,
            'nullable',
            'string',
            'regex:/^08[1-9][0-9]{7,10}$/',
        ];
    }

    private function digitFieldMessages(): array
    {
        return [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nik.digits' => 'NIK harus berjumlah 16 digit angka.',
            'npwp.regex' => 'NPWP hanya boleh berisi angka.',
            'npwp.min' => 'NPWP minimal berjumlah 15 digit.',
            'npwp.max' => 'NPWP maksimal berjumlah 16 digit.',
            'phone.regex' => 'Nomor telepon harus nomor seluler Indonesia, diawali 08 dan berjumlah 10-13 digit.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ];
    }

    private function resolveBaseSalaryPayload(Position $Position, array $data): array
    {
        $amount = array_key_exists('base_salary_amount', $data) && $data['base_salary_amount'] !== null && $data['base_salary_amount'] !== ''
            ? (float) $data['base_salary_amount']
            : (float) ($Position->default_base_salary_amount ?? 0);

        return [
            'amount' => $amount,
        ];
    }

    private function resolveBaseSalaryFromProfile(Employee $employee, mixed $profile, ?Position $Position = null): array
    {
        $Position ??= $profile->Position ?? $employee->Position;
        $amount = $profile->base_salary_amount
            ?? ($Position?->default_base_salary_amount ?? 0);

        return [
            'amount' => $amount,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Daftar karyawan hanya diperlukan HCGA dan Finance.
        if (! $this->inRoles($user, ['hcga', 'fat'])) {
            return $this->forbid();
        }

        $qStatus = $request->query('status'); // active/inactive/null
        $query = Employee::query()->orderBy('name');

        if ($qStatus) {
            $query->where('status', $qStatus);
        }

        return $query->with([
            'position:id,code,name,is_active,level',
            'salaryProfiles' => function (mixed $profileQuery) {
                $profileQuery
                    ->whereDate('effective_from', '<=', now()->toDateString())
                    ->orderByDesc('effective_from')
                    ->select(['id', 'employee_id', 'position_id', 'effective_from'])
                    ->with('position:id,code,name,is_active,level');
            },
        ])->get([
            'id',
            'employee_code',
            'name',
            'status',
            'user_id',
            'position_id',
            'join_date',
        ])->map(function (Employee $employee) {
            $currentProfile = $employee->salaryProfiles->first();
            $currentPosition = $currentProfile?->getRelation('position') ?? $employee->position;
            $payload = $employee->toArray();
            $payload['position_id'] = $currentProfile?->position_id ?? $employee->position_id;
            $payload['position'] = $currentPosition?->toArray();
            unset($payload['salary_profiles']);

            return [
                ...$payload,
                'join_date' => optional($employee->join_date)->toDateString(),
                'has_account' => (bool) $employee->user_id,
                'payroll_readiness' => $employee->payrollReadiness(),
            ];
        });

        return response()->json($employees);
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
        $canSeePayrollNominal = $role === 'fat';
        $positionPayload = $currentPosition?->toArray();

        if ($positionPayload && ! $canSeePayrollNominal) {
            unset($positionPayload['default_base_salary_amount']);
        }

        // akses dasar:
        // - HCGA/FAT boleh lihat employee mana pun
        // - STAFF hanya boleh lihat dirinya sendiri
        if (! in_array($role, ['hcga', 'fat'], true)) {
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
            'position' => $currentProfile?->Position?->name ?? $employee->Position?->name,
            'status' => $employee->status,
            'user_id' => $employee->user_id,

            // Phase 1 fields:
            'position_id' => $currentProfile?->position_id ?? $employee->position_id,
            'num_toddlers' => (int) $employee->num_toddlers,
            'Position' => $positionPayload,
            'salary_profile_summary' => $baseSalary ? [
                'base_salary_amount' => $canSeePayrollNominal && $baseSalary['amount'] !== null
                    ? (string) $baseSalary['amount']
                    : null,
                'position_allowance' => $canSeePayrollNominal ? $currentProfile?->position_allowance : null,
                'masked' => ! $canSeePayrollNominal,
            ] : null,
            'payroll_readiness' => $employee->payrollReadiness(),
        ];

        // aturan lihat PII vs Bank
        $canSeePII = ($role === 'hcga') || $isOwner; // NIK/NPWP/Phone/Address
        $canSeeBank = in_array($role, ['hcga', 'fat'], true) || $isOwner; // bank utk transfer

        if ($canSeePII) {
            $base += [
                'nik' => $employee->nik,
                'npwp' => $employee->npwp,
                'phone' => $employee->phone,
                'address' => $employee->address,
            ];
        }

        if ($canSeeBank) {
            $base += [
                'bank_name' => $employee->bank_name,
                'bank_account_name' => $employee->bank_account_name,
                'bank_account_number' => $employee->bank_account_number,
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

        // Nominal profil gaji hanya untuk Finance.
        if ($role !== 'fat') {
            return $this->forbid();
        }

        $date = $request->query('date', now()->toDateString());
        $profile = $employee->currentSalaryProfile($date);

        if (! $profile) {
            return response()->json(['message' => 'Salary profile not found'], 404);
        }

        $positionVal = $profile->position_allowance;
        $allow = (float) ($profile->allowance_fixed ?? 0);
        $ded = (float) ($profile->deduction_fixed ?? 0);

        $effectivepositionId = $profile->position_id ?? $employee->position_id;
        $effectivePosition = $profile->Position?->name ?? $employee->Position?->name;

        $Position = $effectivepositionId ? \App\Models\Position::find($effectivepositionId) : null;
        $baseSalary = $this->resolveBaseSalaryFromProfile($employee, $profile, $Position);

        $is_using_default_base = false;
        // Profil bernilai 0 tidak menonaktifkan tarif master; nilai tersebut
        // diperlakukan sebagai belum ada override per pegawai.
        if ($positionVal === null || $positionVal === '' || (float) $positionVal <= 0) {
            if ($profile->position_allowance > 0) {
                $base = (float) $profile->position_allowance;
                $is_using_default_base = false;
            } else {
                $posRate = $Position ? $this->rateResolver->resolveByCode($Position->id, 'position') : null;
                $base = $posRate ? (float) $posRate->rate_amount : 0.0;
                $is_using_default_base = true;
            }
        } else {
            $base = (float) $positionVal;
            $is_using_default_base = false;
        }

        $is_using_default_salary = $profile->base_salary_amount_enc === null
            && $profile->base_salary_amount === null;

        return response()->json([
            'employee_id' => $employee->id,
            'effective_from' => $profile->effective_from->toDateString(),
            'position_id' => $effectivepositionId,
            'position' => $effectivePosition,
            'base_salary_amount' => $baseSalary['amount'] !== null ? (string) $baseSalary['amount'] : null,
            'position_allowance' => (string) $base,
            'allowance_fixed' => (string) $allow,
            'deduction_fixed' => (string) $ded,
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

            'nik' => ['nullable', 'string', 'digits:16'],
            'npwp' => ['nullable', 'string', 'min:15', 'max:16', 'regex:/^[0-9]+$/'],
            'phone' => $this->indonesianMobilePhoneRules(),
            'address' => ['nullable', 'string', 'max:500'],

            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => $this->digitStringRules(50),

            'pii_alg' => ['nullable', 'in:HYBRID'],

            // Phase 1 fields:
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'num_toddlers' => ['nullable', 'integer', 'min:0'],

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
            $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position');
            $base = (float) ($positionRate?->rate_amount ?? 0);
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

            $employeeData = $data;
            $employeeData['user_id'] = $userId;
            $employeeData['status'] = 'active';
            $employeeData['join_date'] = $data['join_date'] ?? $effectiveFrom;
            $employeeData['num_toddlers'] = $data['num_toddlers'] ?? 0;
            $employeeData = [
                ...$employeeData,
                ...$this->sensitiveCipher->encryptAttributes([
                    'nik' => $data['nik'] ?? null,
                    'npwp' => $data['npwp'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                ], 'pii_alg', 'pii_key_id'),
            ];

            unset($employeeData['nik'], $employeeData['npwp'], $employeeData['phone'], $employeeData['address'], $employeeData['bank_account_number']);

            $employee = Employee::create($employeeData);
            $employee->salaryProfiles()->create([
                'position_id' => $Position->id,
                'effective_from' => $effectiveFrom,
                ...$this->sensitiveCipher->encryptAttributes([
                    'base_salary_amount' => $baseSalaryAmount,
                    'position_allowance' => $base,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                ]),
            ]);

            $employee->jobHistories()->create([
                'position_id' => $Position->id,
                'start_date' => $effectiveFrom,
                'status' => 'active',
                'notes' => 'Penempatan awal karyawan',
            ]);

            return $employee;
        });

        return response()->json([
            'employee' => $employee->fresh(['Position', 'salaryProfiles']),
        ], 201);
    }

    public function salaryProfilesList(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);
        
        $isSelf = (string) $user->employee_id === (string) $employee->id
            || ((int) ($employee->user_id ?? 0) === (int) $user->id);
        
        if (! in_array($role, ['hcga', 'fat'], true) && !$isSelf) {
            return $this->forbid();
        }

        $canSeeNominal = $role === 'fat'
            || ($role === 'staff' && $isSelf);

        $profiles = $employee->salaryProfiles()->orderBy('effective_from', 'desc')->get();
        $employeePosition = $employee->Position;

        $results = $profiles->map(function (mixed $p) use ($employeePosition, $canSeeNominal, $employee) {
            $positionVal = $p->position_allowance;
            $allow = (float) ($p->allowance_fixed ?? 0);
            $ded = (float) ($p->deduction_fixed ?? 0);

            $effectivepositionId = $p->position_id ?? null;
            $Position = $effectivepositionId ? \App\Models\Position::find($effectivepositionId) : $employeePosition;
            $baseSalary = $this->resolveBaseSalaryFromProfile($employee, $p, $Position);

            $is_using_default_base = false;
            // Profil bernilai 0 tidak menonaktifkan tarif master; nilai tersebut
            // diperlakukan sebagai belum ada override per pegawai.
            if ($positionVal === null || $positionVal === '' || (float) $positionVal <= 0) {
                if ($p->position_allowance > 0) {
                    $base = (float) $p->position_allowance;
                    $is_using_default_base = false;
                } else {
                    $posRate = $Position
                        ? $this->rateResolver->resolveByCode($Position->id, 'position')
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
                'position' => $Position?->name,
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
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],

            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],

            'salary_alg' => ['nullable', 'in:HYBRID'],
        ]);

        $profile = DB::transaction(function () use ($data, $employee) {
            $Position = Position::findOrFail($data['position_id']);
            $effectiveFrom = \Carbon\Carbon::parse($data['effective_from'])->startOfDay();
            $salaryConfig = $this->resolveBaseSalaryPayload($Position, $data);

            $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position');
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null
                ? (float) $data['position_allowance']
                : (float) ($positionRate?->rate_amount ?? 0);
            $baseSalaryAmount = $salaryConfig['amount'];
            $allow = (float) ($data['allowance_fixed'] ?? 0);
            $deduction = (float) ($data['deduction_fixed'] ?? 0);
            $profile = $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => $effectiveFrom->toDateString()],
                [
                    'position_id' => $Position->id,
                    ...$this->sensitiveCipher->encryptAttributes([
                        'base_salary_amount' => $baseSalaryAmount,
                        'position_allowance' => $base,
                        'allowance_fixed' => $allow,
                        'deduction_fixed' => $deduction,
                    ]),
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
                    'end_date' => null,
                    'status' => $effectiveFrom->isFuture() ? 'inactive' : 'active',
                    'notes' => $effectiveFrom->isFuture() ? 'Perubahan jabatan terjadwal' : 'Perubahan jabatan diterapkan',
                ]
            );

            if (! $effectiveFrom->isFuture()) {
                $employee->update(['position_id' => $Position->id]);
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
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],

            'nik' => ['sometimes', 'nullable', 'string', 'digits:16'],
            'npwp' => ['sometimes', 'nullable', 'string', 'min:15', 'max:16', 'regex:/^[0-9]+$/'],
            'phone' => $this->indonesianMobilePhoneRules(true),
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_number' => $this->digitStringRules(50, true),

            'num_toddlers' => ['sometimes', 'integer', 'min:0'],
            'position_id' => ['sometimes', 'nullable', Rule::exists('positions', 'id')->where('is_active', true)],
        ], $this->digitFieldMessages());

        // 🚨 HARD BLOCK: jangan pernah update employee_code
        unset($data['employee_code']);

        $oldJoinDate = $employee->join_date;
        $newJoinDate = array_key_exists('join_date', $data) ? $data['join_date'] : $oldJoinDate;

        if ($newJoinDate !== $oldJoinDate) {
            if ($employee->payrolls()->exists()) {
                return response()->json([
                    'message' => 'Tanggal masuk tidak bisa diubah karena karyawan sudah memiliki riwayat payroll.',
                ], 422);
            }
        }

        $piiFields = ['nik', 'npwp', 'phone', 'address', 'bank_account_number'];
        $hasPiiChange = collect($piiFields)->contains(fn (string $field) => array_key_exists($field, $data));
        if ($hasPiiChange) {
            $piiValues = [];
            foreach ($piiFields as $field) {
                $piiValues[$field] = array_key_exists($field, $data) ? $data[$field] : $employee->{$field};
                unset($data[$field]);
            }
            $data = [...$data, ...$this->sensitiveCipher->encryptAttributes($piiValues, 'pii_alg', 'pii_key_id')];
        }

        DB::transaction(function () use ($data, $employee, $oldJoinDate, $newJoinDate) {
            // Jika employee belum punya jabatan dan dikirim position_id baru (Penempatan Awal)
            if (empty($employee->position_id) && !empty($data['position_id'])) {
                $Position = Position::findOrFail($data['position_id']);
                $effectiveFrom = $data['join_date'] ?? $employee->join_date ?? now()->startOfMonth()->toDateString();
                
                // Gunakan default configurasi
                $salaryConfig = $this->resolveBaseSalaryPayload($Position, []);
                $positionRate = $this->rateResolver->resolveByCode($Position->id, 'position');
                $base = (float) ($positionRate?->rate_amount ?? 0);
                $baseSalaryAmount = $salaryConfig['amount'];
                
                $employee->salaryProfiles()->create([
                    'position_id' => $Position->id,
                    ...$this->sensitiveCipher->encryptAttributes([
                        'base_salary_amount' => $baseSalaryAmount,
                        'position_allowance' => $base,
                    ]),
                    'effective_from' => \Carbon\Carbon::parse($effectiveFrom)->startOfDay()->toDateString(),
                ]);

                $employee->jobHistories()->create([
                    'position_id' => $Position->id,
                    'start_date' => $effectiveFrom,
                    'notes' => 'Penempatan awal (Update)',
                ]);
            } else {
                // Jangan update position_id kalau sudah punya
                unset($data['position_id']);
            }

            $employee->update($data);

            // Sinkronisasi join_date ke profil dan riwayat pertama jika berubah
            if ($newJoinDate !== $oldJoinDate) {
                $firstProfile = $employee->salaryProfiles()->orderBy('id', 'asc')->first();
                if ($firstProfile) {
                    $firstProfile->update([
                        'effective_from' => \Carbon\Carbon::parse($newJoinDate)->startOfDay()->toDateString(),
                    ]);
                }

                $firstJob = $employee->jobHistories()->orderBy('id', 'asc')->first();
                if ($firstJob) {
                    $firstJob->update([
                        'start_date' => \Carbon\Carbon::parse($newJoinDate)->startOfDay()->toDateString(),
                    ]);
                }
            }
        });

        // sinkron nama user kalau employee punya akun
        if (array_key_exists('name', $data) && $employee->user_id) {
            User::where('id', $employee->user_id)->update([
                'name' => $data['name'],
            ]);
        }

        return response()->json([
            'message' => 'Employee updated',
            'employee' => $employee->fresh(['Position']),
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

        if (! in_array($role, ['hcga', 'fat'], true) && ! $isOwner) {
            return $this->forbid();
        }

        $histories = $employee->jobHistories()->with('Position')->orderBy('start_date', 'desc')->get();

        return response()->json($histories);
    }
}
