<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\CryptoService;

class MeController extends Controller
{
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

    private function resolveEmployee($u): ?Employee
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

        $alg = strtoupper((string) ($emp->pii_alg ?? 'AES'));

        $emp->nik = CryptoService::readEncryptedOrPlain($emp->nik_enc, $emp->nik, $alg);
        $emp->npwp = CryptoService::readEncryptedOrPlain($emp->npwp_enc, $emp->npwp, $alg);
        $emp->bank_account_number = CryptoService::readEncryptedOrPlain(
            $emp->bank_account_number_enc,
            $emp->bank_account_number,
            $alg
        );
        $emp->phone = CryptoService::readEncryptedOrPlain($emp->phone_enc, $emp->phone, $alg);
        $emp->address = CryptoService::readEncryptedOrPlain($emp->address_enc, $emp->address, $alg);

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
            return response()->json(['message' => 'Endpoint ini khusus untuk staff.'], 403);
        }

        $emp = $this->resolveEmployee($u);

        if (!$emp) {
            return response()->json(['message' => 'Akun ini belum terhubung ke data employee.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'nik' => ['sometimes', 'nullable', 'string', 'max:32'],
            'npwp' => ['sometimes', 'nullable', 'string', 'max:32'],

            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:50'],

            // optional kalau kamu mau staff bisa pilih alg:
            // 'pii_alg' => ['sometimes', 'in:AES,RSA'],
        ]);

        $piiAlg = strtoupper((string) ($emp->pii_alg ?? 'AES'));

        $encPII = function (string $v) use ($piiAlg) {
            return $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        if (array_key_exists('nik', $data)) {
            $data['nik_enc'] = !empty($data['nik']) ? $encPII((string)$data['nik']) : null;
        }
        if (array_key_exists('npwp', $data)) {
            $data['npwp_enc'] = !empty($data['npwp']) ? $encPII((string)$data['npwp']) : null;
        }
        if (array_key_exists('bank_account_number', $data)) {
            $data['bank_account_number_enc'] = !empty($data['bank_account_number'])
                ? $encPII((string)$data['bank_account_number'])
                : null;
        }
        if (array_key_exists('phone', $data)) {
            $data['phone_enc'] = !empty($data['phone']) ? $encPII((string)$data['phone']) : null;
        }
        if (array_key_exists('address', $data)) {
            $data['address_enc'] = !empty($data['address']) ? $encPII((string)$data['address']) : null;
        }

        // metadata
        $data['pii_alg'] = $piiAlg;
        $data['pii_key_id'] = CryptoService::keyId();

        $emp->update($data);

        if (array_key_exists('name', $data)) {
            $u->update(['name' => $data['name']]);
        }

        $fresh = $emp->fresh();

        $fresh->nik = CryptoService::readEncryptedOrPlain($fresh->nik_enc, $fresh->nik, $piiAlg);
        $fresh->npwp = CryptoService::readEncryptedOrPlain($fresh->npwp_enc, $fresh->npwp, $piiAlg);
        $fresh->bank_account_number = CryptoService::readEncryptedOrPlain(
            $fresh->bank_account_number_enc,
            $fresh->bank_account_number,
            $piiAlg
        );
        $fresh->phone = CryptoService::readEncryptedOrPlain($fresh->phone_enc, $fresh->phone, $piiAlg);
        $fresh->address = CryptoService::readEncryptedOrPlain($fresh->address_enc, $fresh->address, $piiAlg);

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
