<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollWorkflowController extends Controller
{
    private function audit(Request $request, string $action, Payroll $payroll, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'payroll_id' => $payroll->id,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {}
    }

    public function submit(Request $request, Payroll $payroll)
    {
        if ($request->user()->cannot('update', $payroll) && $request->user()->role !== 'fat') {
            abort(403, 'Unauthorized action.');
        }

        if ($payroll->status !== 'draft') {
            return response()->json(['message' => 'Hanya payroll berstatus draft yang bisa diajukan.'], 422);
        }

        $payroll->update([
            'status' => 'submitted',
            'requested_by' => $request->user()->id,
            'requested_at' => Carbon::now(),
        ]);

        $this->audit($request, 'PAYROLL_SUBMIT', $payroll);

        return response()->json(['message' => 'Payroll berhasil diajukan ke Direktur.', 'payroll' => $payroll]);
    }

    public function approve(Request $request, Payroll $payroll)
    {
        if ($request->user()->role !== 'director') {
            abort(403, 'Hanya direktur yang berhak menyetujui payroll.');
        }

        if ($payroll->status !== 'submitted') {
            return response()->json(['message' => 'Payroll belum diajukan atau sudah diproses.'], 422);
        }

        $payroll->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => Carbon::now(),
            'approval_note' => $request->note,
        ]);

        $this->audit($request, 'PAYROLL_APPROVE', $payroll, ['note' => $request->note]);

        return response()->json(['message' => 'Payroll berhasil disetujui.', 'payroll' => $payroll]);
    }

    public function pay(Request $request, Payroll $payroll)
    {
        if ($request->user()->role !== 'fat') {
            abort(403, 'Hanya Finance yang berhak mentransfer payroll.');
        }

        if ($payroll->status !== 'approved') {
            return response()->json(['message' => 'Payroll harus disetujui oleh Direktur terlebih dahulu.'], 422);
        }

        $payroll->update([
            'status' => 'paid',
            'paid_by' => $request->user()->id,
            'paid_at' => Carbon::now(),
            'paid_note' => $request->note,
        ]);

        $this->audit($request, 'PAYROLL_MARK_PAID', $payroll, ['note' => $request->note]);

        return response()->json(['message' => 'Payroll berhasil ditandai sebagai dibayar.', 'payroll' => $payroll]);
    }

    public function reject(Request $request, Payroll $payroll)
    {
        if ($request->user()->role !== 'director') {
            abort(403, 'Hanya direktur yang berhak menolak payroll.');
        }

        if ($payroll->status !== 'submitted') {
            return response()->json(['message' => 'Hanya payroll yang sedang diajukan yang dapat ditolak.'], 422);
        }

        $payroll->update([
            'status' => 'draft',
            'approval_note' => 'Ditolak: ' . ($request->note ?? 'Tanpa alasan'),
        ]);

        $this->audit($request, 'PAYROLL_REJECT', $payroll, ['note' => $request->note]);

        return response()->json(['message' => 'Payroll berhasil ditolak dan dikembalikan ke Draft.', 'payroll' => $payroll]);
    }
}
