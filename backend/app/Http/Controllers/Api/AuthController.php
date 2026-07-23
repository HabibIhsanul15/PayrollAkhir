<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * POST /api/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ❌ salah email / password
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = $request->user();

        // 🔗 ambil employee (kalau ada)
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee) {
            if ($employee->join_date && \Carbon\Carbon::parse($employee->join_date)->startOfDay()->isFuture()) {
                $user->tokens()->delete();
                Auth::logout();
                return response()->json([
                    'message' => 'Akun belum aktif (tanggal masuk belum terlewati).'
                ], 403);
            }
        }

        /**
         * 🔒 RULE KEAMANAN
         * - Role 'staff' HARUS terhubung ke employee yang status-nya active
         * - Role lain (HCGA, FAT, Director) bebas login
         */
        if (strtolower($user->role) === 'staff') {
            if (!$employee) {
                Auth::logout();
                return response()->json([
                    'message' => 'Akun pegawai belum terhubung ke data pegawai. Hubungi HCGA.'
                ], 403);
            }

            if (strtolower((string) $employee->status) !== 'active') {
                $user->tokens()->delete();
                Auth::logout();
                return response()->json([
                    'message' => 'Akun dinonaktifkan. Silakan hubungi HCGA.'
                ], 403);
            }
        }

        // ♻️ bersihkan token lama (1 user = 1 session aktif)
        $user->tokens()->delete();

        // 🔑 buat token baru
        $token = $user->createToken('payroll')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'employee' => $employee ? [
                    'id'            => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'status'        => $employee->status,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}
