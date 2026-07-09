<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyRecap;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyRecapController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->user()->cannot('viewAny', MonthlyRecap::class)) {
            // Simplified permission check: HCGA and FAT can view
            if (!in_array(strtolower($request->user()->role), ['hcga', 'fat', 'director'])) {
                abort(403);
            }
        }

        $periodMonth = $request->query('period_month', now()->format('Y-m'));

        $recaps = MonthlyRecap::with('employee')
            ->where('period_month', $periodMonth)
            ->get();

        return response()->json($recaps);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat menginput Rekap Bulanan.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|date_format:Y-m',
            'recaps' => 'required|array|min:1',
            'recaps.*.salary_profile_id' => 'nullable|exists:salary_profiles,id',
            'recaps.*.wfo_days' => 'numeric|min:0',
            'recaps.*.wfh_days' => 'numeric|min:0',
            'recaps.*.out_of_town_days' => 'numeric|min:0',
            'recaps.*.business_trips' => 'integer|min:0',
            'recaps.*.training_days' => 'numeric|min:0',
            'recaps.*.overtime_hours' => 'numeric|min:0',
        ]);

        $employeeId = $validated['employee_id'];
        $periodMonth = $validated['period_month'];

        $createdRecaps = [];

        foreach ($validated['recaps'] as $recapData) {
            $totalMandays = ($recapData['wfo_days'] ?? 0) + ($recapData['wfh_days'] ?? 0) + ($recapData['out_of_town_days'] ?? 0) + ($recapData['training_days'] ?? 0);

            $data = [
                'employee_id' => $employeeId,
                'period_month' => $periodMonth,
                'salary_profile_id' => $recapData['salary_profile_id'] ?? null,
                'wfo_days' => $recapData['wfo_days'] ?? 0,
                'wfh_days' => $recapData['wfh_days'] ?? 0,
                'out_of_town_days' => $recapData['out_of_town_days'] ?? 0,
                'business_trips' => $recapData['business_trips'] ?? 0,
                'training_days' => $recapData['training_days'] ?? 0,
                'overtime_hours' => $recapData['overtime_hours'] ?? 0,
                'total_mandays' => $totalMandays,
            ];

            $createdRecaps[] = MonthlyRecap::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'period_month' => $periodMonth,
                    'salary_profile_id' => $recapData['salary_profile_id'] ?? null,
                ],
                $data
            );
        }

        return response()->json($createdRecaps, 201);
    }

    /**
     * Finalize the recap so it can be processed by PayrollEngine.
     */
    public function finalize(Request $request, MonthlyRecap $recap)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat melakukan finalisasi.');
        }

        $recap->update([
            'is_finalized' => true,
            'finalized_by' => $request->user()->id,
            'finalized_at' => now(),
        ]);

        return response()->json($recap);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, MonthlyRecap $recap)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat menghapus data.');
        }

        if ($recap->is_finalized) {
            abort(422, 'Tidak dapat menghapus rekap yang sudah difinalisasi.');
        }

        $recap->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
