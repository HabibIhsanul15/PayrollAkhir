<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollWorkflowController extends Controller
{
    public function submit(Request $request, Payroll $payroll)
    {
        if ($request->user()->cannot('update', $payroll) && $request->user()->role !== 'fat') {
            abort(403, 'Unauthorized action.');
        }

        if (! in_array($payroll->status, ['draft', 'rejected'], true)) {
            return response()->json(['message' => 'Hanya payroll berstatus draft atau ditolak yang bisa diajukan.'], 422);
        }

        $payroll->update([
            'status' => 'submitted',
            'requested_by' => $request->user()->id,
            'requested_at' => Carbon::now(),
            'approval_note' => null,
        ]);

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

        return response()->json(['message' => 'Payroll berhasil disetujui.', 'payroll' => $payroll]);
    }

    public function reject(Request $request, Payroll $payroll)
    {
        if ($request->user()->role !== 'director') {
            abort(403, 'Hanya direktur yang berhak menolak payroll.');
        }

        if ($payroll->status !== 'submitted') {
            return response()->json(['message' => 'Hanya payroll yang sedang diajukan yang dapat ditolak.'], 422);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $payroll->update([
            'status' => 'rejected',
            'approval_note' => $data['note'],
        ]);

        return response()->json(['message' => 'Payroll berhasil ditolak.', 'payroll' => $payroll]);
    }
}
