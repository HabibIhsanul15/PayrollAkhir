<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Services\CryptoService;

class EmployeeController extends Controller
{
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

    public function index(Request $request)
    {
        $user = $request->user();

        // HCGA/FAT/DIRECTOR boleh lihat list
        if (!$this->inRoles($user, ['hcga', 'fat', 'director'])) {
            return $this->forbid();
        }

        $qStatus = $request->query('status'); // active/inactive/null
        $query = Employee::query()->orderBy('name');

        if ($qStatus) {
            $query->where('status', $qStatus);
        }

        return $query->get([
            'id',
            'employee_code',
            'name',
            'department',
            'position',
            'status',
            'user_id',
        ]);
    }

    public function nextCode(Request $request)
    {
        $user = $request->user();

        // hanya HCGA
        if (!$this->inRoles($user, ['hcga'])) {
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

        $nextCode = 'EMP-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'next_employee_code' => $nextCode,
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);

        $isOwner = $employee->user_id && (int) $employee->user_id === (int) $user->id;

        // akses dasar:
        // - HCGA/FAT/DIRECTOR boleh lihat employee mana pun
        // - STAFF hanya boleh lihat dirinya sendiri
        if (!in_array($role, ['hcga', 'fat', 'director'], true)) {
            if (!($role === 'staff' && $isOwner)) {
                return $this->forbid();
            }
        }

        // field base (aman)
        $base = [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'department' => $employee->department,
            'position' => $employee->position,
            'status' => $employee->status,
            'user_id' => $employee->user_id,

            // Phase 1 fields:
            'grade_id' => $employee->grade_id,
            'employment_type_id' => $employee->employment_type_id,
            'work_basis_id' => $employee->work_basis_id,
            'num_toddlers' => (int) $employee->num_toddlers,
            'is_trainer' => (bool) $employee->is_trainer,
            'is_on_probation' => (bool) $employee->is_on_probation,
            'grade' => $employee->grade,
            'employment_type' => $employee->employmentType,
            'work_basis' => $employee->workBasis,
        ];

        $alg = strtoupper((string) ($employee->pii_alg ?? 'AES'));

        // aturan lihat PII vs Bank
        $canSeePII  = ($role === 'hcga') || $isOwner; // NIK/NPWP/Phone/Address
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
        if (!$canSeePII && !$canSeeBank) {
            $base['masked'] = true;
        }

        return response()->json($base);
    }

    public function createUser(Request $request, Employee $employee)
    {
        $actor = $request->user();

        // hanya HCGA bikin akun
        if (!$this->inRoles($actor, ['hcga'])) {
            return $this->forbid();
        }

        if ($employee->user_id) {
            return response()->json(['message' => 'Employee ini sudah punya akun.'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['staff', 'hcga'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        $employee->update(['user_id' => $user->id]);

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function salaryProfile(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);

        // lihat salary profile: HCGA/FAT/DIRECTOR
        if (!in_array($role, ['hcga', 'fat', 'director'], true)) {
            return $this->forbid();
        }

        $date = $request->query('date', now()->toDateString());
        $profile = $employee->currentSalaryProfile($date);

        if (!$profile) {
            return response()->json(['message' => 'Salary profile not found'], 404);
        }

        $alg = strtoupper((string) ($profile->salary_alg ?? 'AES'));

        $positionVal = $profile->position_allowance_enc ? CryptoService::decryptByAlg($profile->position_allowance_enc, $alg) : null;
        $allow = $profile->allowance_fixed_enc ? (float) CryptoService::decryptByAlg($profile->allowance_fixed_enc, $alg) : (float) $profile->allowance_fixed;
        $ded   = $profile->deduction_fixed_enc ? (float) CryptoService::decryptByAlg($profile->deduction_fixed_enc, $alg) : (float) $profile->deduction_fixed;

        $mandaysVal = $profile->mandays_rate_enc ? CryptoService::decryptByAlg($profile->mandays_rate_enc, $alg) : null;

        $effectiveGradeId = $profile->grade_id ?? $employee->grade_id;
        $effectivePosition = $profile->position ?? $employee->position;

        $grade = $effectiveGradeId ? \App\Models\Grade::find($effectiveGradeId) : null;

        $is_using_default_base = false;
        if ($positionVal === null || $positionVal === '') {
            if ($profile->position_allowance > 0) {
                $base = (float)$profile->position_allowance;
                $is_using_default_base = false;
            } else {
                $posRate = $grade ? \App\Models\GradeAllowanceRate::where('grade_id', $grade->id)
                    ->whereHas('allowanceType', function($q) { $q->where('code', 'position'); })
                    ->first() : null;
                $base = $posRate ? (float)$posRate->rate_amount : 0.0;
                $is_using_default_base = true;
            }
        } else {
            $base = (float)$positionVal;
            $is_using_default_base = false;
        }

        $is_using_default_mandays = false;
        if ($mandaysVal === null || $mandaysVal === '') {
            if ($profile->mandays_rate !== null) {
                $mandays_rate = (string)$profile->mandays_rate;
                $is_using_default_mandays = false;
            } else {
                $mandays_rate = $grade ? ($grade->default_mandays_rate !== null ? (string)$grade->default_mandays_rate : null) : null;
                $is_using_default_mandays = true;
            }
        } else {
            $mandays_rate = (string)$mandaysVal;
            $is_using_default_mandays = false;
        }

        return response()->json([
            'employee_id' => $employee->id,
            'effective_from' => $profile->effective_from->toDateString(),
            'grade_id' => $effectiveGradeId,
            'position' => $effectivePosition,
            'position_allowance' => (string) $base,
            'allowance_fixed' => (string) $allow,
            'deduction_fixed' => (string) $ded,
            'mandays_rate' => $mandays_rate,
            'is_using_default_base' => $is_using_default_base,
            'is_using_default_mandays' => $is_using_default_mandays,
            'suggested_total' => (string) ($base + $allow - $ded),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // create employee: hanya HCGA
        if (!$this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],

            'nik' => ['nullable', 'string', 'max:32'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],

            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],

            'pii_alg' => ['nullable', 'in:AES,RSA'],

            // Phase 1 fields:
            'grade_id' => ['nullable', 'exists:grades,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'num_toddlers' => ['nullable', 'integer', 'min:0'],
            'is_trainer' => ['nullable', 'boolean'],
            'is_on_probation' => ['nullable', 'boolean'],
            
            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],
            
            // Create account fields
            'create_account' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if:create_account,true', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'required_if:create_account,true', \Illuminate\Validation\Rule::in(['staff', 'hcga', 'fat', 'director'])],
            'password' => ['nullable', 'required_if:create_account,true', 'string', 'min:8'],
        ]);

        $userId = null;
        if (!empty($data['create_account'])) {
            $userAcc = \App\Models\User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            ]);
            $userId = $userAcc->id;
        }

        $data['user_id'] = $userId;
        $data['num_toddlers'] = $data['num_toddlers'] ?? 0;
        $data['is_trainer'] = $data['is_trainer'] ?? false;
        $data['is_on_probation'] = $data['is_on_probation'] ?? false;

        $piiAlg = strtoupper((string) ($data['pii_alg'] ?? 'AES'));

        $encPII = function (string $v) use ($piiAlg) {
            return $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        $data['nik_enc'] = !empty($data['nik']) ? $encPII((string) $data['nik']) : null;
        $data['npwp_enc'] = !empty($data['npwp']) ? $encPII((string) $data['npwp']) : null;
        $data['phone_enc'] = !empty($data['phone']) ? $encPII((string) $data['phone']) : null;
        $data['address_enc'] = !empty($data['address']) ? $encPII((string) $data['address']) : null;
        $data['bank_account_number_enc'] = !empty($data['bank_account_number']) ? $encPII((string) $data['bank_account_number']) : null;

        $data['pii_alg'] = $piiAlg;
        $data['pii_key_id'] = CryptoService::keyId();

        $employee = Employee::create($data);

        // Auto-generate default salary profile based on selected Grade
        if ($employee->grade_id) {
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null ? (float) $data['position_allowance'] : 0;
            $mandays_rate = array_key_exists('mandays_rate', $data) && $data['mandays_rate'] !== null ? (float) $data['mandays_rate'] : 0;
            
            \App\Models\SalaryProfile::create([
                'employee_id' => $employee->id,
                'grade_id' => $employee->grade_id,
                'position' => $employee->position,
                'position_allowance' => $base, 
                'mandays_rate' => $mandays_rate, 
                'allowance_fixed' => 0,
                'deduction_fixed' => 0,
                'effective_from' => date('Y-m-01'),
                'status' => 'active'
            ]);
            
            // Phase 4: Auto-create JobHistory
            \App\Models\JobHistory::create([
                'employee_id' => $employee->id,
                'grade_id' => $employee->grade_id,
                'position' => $employee->position,
                'start_date' => date('Y-m-01'),
                'status' => 'active'
            ]);
        }

        return response()->json([
            'employee' => $employee->fresh(['grade', 'employmentType', 'workBasis', 'salaryProfiles']),
        ], 201);
    }

    public function salaryProfilesList(Request $request, Employee $employee)
    {
        $user = $request->user();
        $role = $this->roleOf($user);
        $isOwner = $employee->user_id && (int) $employee->user_id === (int) $user->id;

        if (!in_array($role, ['hcga', 'fat', 'director'], true) && !$isOwner) {
            return $this->forbid();
        }

        $profiles = $employee->salaryProfiles()->orderBy('effective_from', 'desc')->get();
        $employeeGrade = $employee->grade;

        $results = $profiles->map(function ($p) use ($employeeGrade) {
            $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));
            
            $positionVal = $p->position_allowance_enc ? CryptoService::decryptByAlg($p->position_allowance_enc, $alg) : null;
            $allow = $p->allowance_fixed_enc ? (float) CryptoService::decryptByAlg($p->allowance_fixed_enc, $alg) : (float) $p->allowance_fixed;
            $ded   = $p->deduction_fixed_enc ? (float) CryptoService::decryptByAlg($p->deduction_fixed_enc, $alg) : (float) $p->deduction_fixed;

            $effectiveGradeId = $p->grade_id ?? null;
            $grade = $effectiveGradeId ? \App\Models\Grade::find($effectiveGradeId) : $employeeGrade;
            
            $is_using_default_base = false;
            if ($positionVal === null || $positionVal === '') {
                if ($p->position_allowance > 0) {
                    $base = (float)$p->position_allowance;
                    $is_using_default_base = false;
                } else {
                    $posRate = $grade ? \App\Models\GradeAllowanceRate::where('grade_id', $grade->id)
                        ->whereHas('allowanceType', function($q) { $q->where('code', 'position'); })
                        ->first() : null;
                    $base = $posRate ? (float)$posRate->rate_amount : 0.0;
                    $is_using_default_base = true;
                }
            } else {
                $base = (float)$positionVal;
                $is_using_default_base = false;
            }

            return [
                'id' => $p->id,
                'effective_from' => $p->effective_from->toDateString(),
                'grade_id' => $effectiveGradeId,
                'grade_name' => $grade ? $grade->name : '-',
                'position' => $p->position,
                'position_allowance' => (string) $base,
                'allowance_fixed' => (string) $allow,
                'deduction_fixed' => (string) $ded,
                'is_using_default_base' => $is_using_default_base,
                'suggested_total' => (string) ($base + $allow - $ded),
                'created_at' => $p->created_at->toISOString(),
            ];
        });

        return response()->json($results);
    }

    public function storeSalaryProfile(Request $request, Employee $employee)
    {
        $user = $request->user();

        // SET SALARY: hanya HCGA
        if (!$this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'allowance_fixed' => ['nullable', 'numeric', 'min:0'],
            'deduction_fixed' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],

            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate_per_hour' => ['nullable', 'numeric', 'min:0'],
            'late_penalty_per_minute' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],

            'grade_id' => ['nullable', 'exists:grades,id'],
            'position' => ['nullable', 'string', 'max:255'],

            'salary_alg' => ['nullable', 'in:AES,RSA'],
        ]);

        $alg = strtoupper((string) ($data['salary_alg'] ?? 'AES'));

        $enc = function (string $v) use ($alg) {
            return $alg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null ? (float) $data['position_allowance'] : null;
        $allow = (float) ($data['allowance_fixed'] ?? 0);
        $ded   = (float) ($data['deduction_fixed'] ?? 0);

        $daily = array_key_exists('daily_rate', $data) ? (float) ($data['daily_rate'] ?? 0) : null;
        $ot    = array_key_exists('overtime_rate_per_hour', $data) ? (float) ($data['overtime_rate_per_hour'] ?? 0) : null;
        $late  = array_key_exists('late_penalty_per_minute', $data) ? (float) ($data['late_penalty_per_minute'] ?? 0) : null;
        $mandays_rate = array_key_exists('mandays_rate', $data) && $data['mandays_rate'] !== null ? (float) $data['mandays_rate'] : null;

        $gradeId = array_key_exists('grade_id', $data) ? $data['grade_id'] : $employee->grade_id;
        $position = array_key_exists('position', $data) ? $data['position'] : $employee->position;

        $profile = $employee->salaryProfiles()->create([
            'grade_id' => $gradeId,
            'position' => $position,
            'position_allowance' => $base ?? 0,
            'allowance_fixed' => $allow,
            'deduction_fixed' => $ded,
            'daily_rate' => $daily,
            'overtime_rate_per_hour' => $ot,
            'late_penalty_per_minute' => $late,
            'mandays_rate' => $mandays_rate,
            'effective_from' => $data['effective_from'],

            'position_allowance_enc' => $base !== null ? $enc((string) $base) : null,
            'allowance_fixed_enc' => $enc((string) $allow),
            'deduction_fixed_enc' => $enc((string) $ded),
            'daily_rate_enc' => $daily !== null ? $enc((string) $daily) : null,
            'overtime_rate_per_hour_enc' => $ot !== null ? $enc((string) $ot) : null,
            'late_penalty_per_minute_enc' => $late !== null ? $enc((string) $late) : null,
            'mandays_rate_enc' => $mandays_rate !== null ? $enc((string) $mandays_rate) : null,

            'salary_alg' => $alg,
            'salary_key_id' => CryptoService::keyId(),
        ]);

        // Job History tracking
        $lastJobHistory = $employee->jobHistories()->where('status', 'active')->orderBy('start_date', 'desc')->first();
        
        $shouldCreateNewHistory = false;
        if (!$lastJobHistory) {
            $shouldCreateNewHistory = true;
        } else {
            // Check if grade or position changed
            if ((int)$lastJobHistory->grade_id !== (int)$gradeId || $lastJobHistory->position !== $position) {
                $shouldCreateNewHistory = true;
                
                // End the previous one
                $endDate = \Carbon\Carbon::parse($data['effective_from'])->subDay()->toDateString();
                $lastJobHistory->update([
                    'end_date' => $endDate,
                    'status' => 'inactive',
                    'notes' => 'Posisi digantikan pada ' . $data['effective_from']
                ]);
            }
        }

        if ($shouldCreateNewHistory) {
            $employee->jobHistories()->create([
                'grade_id' => $gradeId,
                'position' => $position,
                'start_date' => $data['effective_from'],
                'status' => 'active'
            ]);
        }

        return response()->json([
            'salary_profile' => $profile,
        ], 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $user = $request->user();

        // update employee: hanya HCGA
        if (!$this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        // NOTE: employee_code sengaja TIDAK BOLEH diupdate (read-only)
        $data = $request->validate([
            'employee_code' => ['sometimes'], // <- diterima biar validator gak error kalau kekirim, tapi nanti kita buang
            'name' => ['sometimes', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],

            'nik' => ['sometimes', 'nullable', 'string', 'max:32'],
            'npwp' => ['sometimes', 'nullable', 'string', 'max:32'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:50'],

            'pii_alg' => ['sometimes', 'in:AES,RSA'],

            // Phase 1 fields:
            'grade_id' => ['sometimes', 'nullable', 'exists:grades,id'],
            'employment_type_id' => ['sometimes', 'nullable', 'exists:employment_types,id'],
            'num_toddlers' => ['sometimes', 'integer', 'min:0'],
            'is_trainer' => ['sometimes', 'boolean'],
            'is_on_probation' => ['sometimes', 'boolean'],
        ]);

        // ✅ HARD BLOCK: jangan pernah update employee_code
        unset($data['employee_code']);

        $piiAlg = strtoupper((string) ($data['pii_alg'] ?? ($employee->pii_alg ?? 'AES')));

        $encPII = function (string $v) use ($piiAlg) {
            return $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        if (array_key_exists('nik', $data)) {
            $data['nik_enc'] = !empty($data['nik']) ? $encPII((string) $data['nik']) : null;
        }
        if (array_key_exists('npwp', $data)) {
            $data['npwp_enc'] = !empty($data['npwp']) ? $encPII((string) $data['npwp']) : null;
        }
        if (array_key_exists('phone', $data)) {
            $data['phone_enc'] = !empty($data['phone']) ? $encPII((string) $data['phone']) : null;
        }
        if (array_key_exists('address', $data)) {
            $data['address_enc'] = !empty($data['address']) ? $encPII((string) $data['address']) : null;
        }
        if (array_key_exists('bank_account_number', $data)) {
            $data['bank_account_number_enc'] = !empty($data['bank_account_number'])
                ? $encPII((string) $data['bank_account_number'])
                : null;
        }

        $data['pii_alg'] = $piiAlg;
        $data['pii_key_id'] = CryptoService::keyId();

        $employee->update($data);

        // sinkron nama user kalau employee punya akun
        if (array_key_exists('name', $data) && $employee->user_id) {
            User::where('id', $employee->user_id)->update([
                'name' => $data['name'],
            ]);
        }

        return response()->json([
            'message' => 'Employee updated',
            'employee' => $employee->fresh(['grade', 'employmentType', 'workBasis']),
        ]);
    }

    public function destroy(Request $request, Employee $employee)
    {
        $user = $request->user();

        // delete employee: hanya HCGA
        if (!$this->inRoles($user, ['hcga'])) {
            return $this->forbid();
        }

        if ($employee->payrolls()->exists()) {
            return response()->json([
                'message' => 'Employee tidak bisa dihapus karena sudah memiliki payroll.'
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

        if (!in_array($role, ['hcga', 'fat', 'director'], true) && !$isOwner) {
            return $this->forbid();
        }

        $histories = $employee->jobHistories()->with('grade')->orderBy('start_date', 'desc')->get();

        return response()->json($histories);
    }
}
