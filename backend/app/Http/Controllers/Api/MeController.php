<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\SensitiveFieldCipherService;

class MeController extends Controller
{
    public function __construct(private SensitiveFieldCipherService $sensitiveCipher) {}

    private function digitStringRules(int $maxLength): array
    {
        return ['sometimes', 'nullable', 'string', "max:$maxLength", 'regex:/^[0-9]+$/'];
    }

    private function digitFieldMessages(): array
    {
        return [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nik.digits' => 'NIK harus berjumlah tepat 16 digit angka.',
            'npwp.regex' => 'NPWP hanya boleh berisi angka.',
            'phone.regex' => 'Nomor telepon harus nomor seluler Indonesia, diawali 08 dan berjumlah 10-13 digit.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ];
    }

    public function me(Request $request)
    {
        $u = $request->user();

        $emp = Employee::where('user_id', $u->id)->first();

        return response()->json([
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'role'  => $u->role,
            'employee' => $emp ? [
                'id' => $emp->id,
                'employee_code' => $emp->employee_code,
            ] : null,
        ]);
    }

    private function resolveEmployee(mixed $u): ?Employee
    {
        return Employee::where('user_id', $u->id)->first();
    }

    // =========================
    // STAFF EMPLOYEE PROFILE
    // =========================
    public function employee(Request $request)
    {
        $u = $request->user();

        if ($u->role !== 'staff') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $emp = $this->resolveEmployee($u);

        if (!$emp) {
            return response()->json(['message' => 'Akun ini belum terhubung ke data employee.'], 404);
        }

        // Posisi aktif ditentukan dari profil gaji yang sudah efektif hari ini.
        $currentProfile = $emp->currentSalaryProfile();
        $effectivePositionId = $currentProfile?->position_id ?? $emp->position_id;
        $emp->position_id = $effectivePositionId;
        $emp->setAttribute('position', $effectivePositionId ? Position::find($effectivePositionId)?->name : null);

        $emp->nik = $emp->nik;
        $emp->npwp = $emp->npwp;
        $emp->bank_account_number = $emp->bank_account_number;
        $emp->phone = $emp->phone;
        $emp->address = $emp->address;

        unset(
            $emp->nik_enc,
            $emp->npwp_enc,
            $emp->bank_account_number_enc,
            $emp->phone_enc,
            $emp->address_enc,
            $emp->pii_alg,
            $emp->pii_key_id
        );

        return response()->json($emp);
    }

    public function updateEmployee(Request $request)
    {
        $u = $request->user();

        if ($u->role !== 'staff') {
            return response()->json(['message' => 'Endpoint ini khusus untuk pegawai.'], 403);
        }

        $emp = $this->resolveEmployee($u);

        if (!$emp) {
            return response()->json(['message' => 'Akun ini belum terhubung ke data employee.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^08[1-9][0-9]{7,10}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'nik' => ['sometimes', 'nullable', 'string', 'digits:16'],
            'npwp' => ['sometimes', 'nullable', 'string', 'min:15', 'max:16', 'regex:/^[0-9]+$/'],

            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bank_account_number' => $this->digitStringRules(50),

            // Algoritma PII ditetapkan server dan selalu HYBRID.
        ], $this->digitFieldMessages());

        $piiFields = ['nik', 'npwp', 'bank_account_number', 'phone', 'address'];
        $hasPiiChange = collect($piiFields)->contains(fn (string $field) => array_key_exists($field, $data));
        if ($hasPiiChange) {
            $piiValues = [];
            foreach ($piiFields as $field) {
                $piiValues[$field] = array_key_exists($field, $data) ? $data[$field] : $emp->{$field};
                unset($data[$field]);
            }
            $data = [...$data, ...$this->sensitiveCipher->encryptAttributes($piiValues, 'pii_alg', 'pii_key_id')];
        }

        $emp->update($data);

        if (array_key_exists('name', $data)) {
            $u->update(['name' => $data['name']]);
        }

        $fresh = $emp->fresh();

        $fresh->nik = $fresh->nik;
        $fresh->npwp = $fresh->npwp;
        $fresh->bank_account_number = $fresh->bank_account_number;
        $fresh->phone = $fresh->phone;
        $fresh->address = $fresh->address;

        unset(
            $fresh->nik_enc,
            $fresh->npwp_enc,
            $fresh->bank_account_number_enc,
            $fresh->phone_enc,
            $fresh->address_enc,
            $fresh->pii_alg,
            $fresh->pii_key_id
        );

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => $fresh,
        ]);
    }

    // =========================
    // USER PROFILE (ALL ROLES)
    // =========================
    public function updateMe(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $u->update([
            'name' => $data['name'],
        ]);

        return response()->json([
            'message' => 'Nama berhasil diperbarui.',
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $u->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak sesuai.'],
            ]);
        }

        $u->update([
            'password' => Hash::make($data['password']),
        ]);

        // optional: logout semua device
        // $u->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
