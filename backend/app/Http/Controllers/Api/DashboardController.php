<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private function roleOf($user): string
    {
        return strtolower((string) ($user->role ?? ''));
    }

    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function normalizeMonth(?string $month): string
    {
        // month harus YYYY-MM, kalau tidak valid fallback ke sekarang
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }
        return PayrollPeriod::currentMonth();
    }

    /**
     * ✅ HCGA Dashboard (HR/Admin focus)
     * GET /api/dashboard/hcga
     */
    public function hcga(Request $request)
    {
        $user = $request->user();
        $role = $this->roleOf($user);

        if ($role !== 'hcga') {
            return $this->forbid();
        }

        $activeCount   = Employee::where('status', 'active')->count();
        $inactiveCount = Employee::where('status', 'inactive')->count();

        // employee belum punya akun (user_id null)
        $noAccountCount = Employee::whereNull('user_id')->count();

        // employee belum punya salary profile sama sekali
        $noSalaryCount = Employee::whereDoesntHave('salaryProfiles')->count();

        $noAccountList = Employee::whereNull('user_id')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'employee_code', 'name', 'department', 'position', 'status', 'user_id']);

        $noSalaryList = Employee::whereDoesntHave('salaryProfiles')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'employee_code', 'name', 'department', 'position', 'status', 'user_id']);

        $currentMonth = date('Y-m');
        $pendingRecapsCount = Employee::where('status', 'active')
            ->whereDoesntHave('monthlyRecaps', function ($q) use ($currentMonth) {
                $q->where('period_month', $currentMonth)->where('is_finalized', true);
            })->count();

        return response()->json([
            'cards' => [
                'active' => (int) $activeCount,
                'inactive' => (int) $inactiveCount,
                'no_account' => (int) $noAccountCount,
                'no_salary_profile' => (int) $noSalaryCount,
                'pending_recap' => (int) $pendingRecapsCount,
            ],
            'lists' => [
                'no_account' => $noAccountList,
                'no_salary_profile' => $noSalaryList,
            ],
        ]);
    }

    /**
     * ✅ Payroll summary dashboard
     * GET /api/dashboard/summary?month=YYYY-MM
     *
     * Rules:
     * - HCGA boleh lihat (tanpa nominal)
     * - FAT & Director boleh lihat semua payroll summary
     * - Staff dibatasi payroll miliknya (kalau payrolls.user_id ada)
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $role = $this->roleOf($user);

        // yang boleh akses summary
        if (!in_array($role, ['hcga', 'fat', 'director', 'staff'], true)) {
            return $this->forbid();
        }

        // privileged untuk payroll summary penuh
        // (HCGA juga boleh lihat summary global karena tidak ada nominal)
        $isPrivileged = in_array($role, ['hcga', 'fat', 'director'], true);

        $month = $this->normalizeMonth($request->query('month'));

        try {
            [$start, $end] = $this->monthRange($month);
        } catch (\Throwable $e) {
            $month = PayrollPeriod::currentMonth();
            [$start, $end] = $this->monthRange($month);
        }

        // ===== KPI: Employees =====
        // Ini harusnya selalu benar (kamu sudah buktiin di tinker ada 7)
        $employeeActive   = Employee::where('status', 'active')->count();
        $employeeInactive = Employee::where('status', 'inactive')->count();

        // ===== Payroll query (filtered by month) =====
        $payrollQuery = Payroll::query();
        $this->applyPeriodFilter($payrollQuery, $month, $start, $end);

        // staff: batasi ke payroll miliknya (kalau payrolls.user_id ada)
        if (!$isPrivileged && $role === 'staff' && Schema::hasColumn('payrolls', 'user_id')) {
            $payrollQuery->where('user_id', $user->id);
        }

        $payrollCount = (clone $payrollQuery)->count();

        // ===== Status counts =====
        $statusCounts = [];
        if (Schema::hasColumn('payrolls', 'status')) {
            $statusCounts = (clone $payrollQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderByRaw("FIELD(status,'draft','requested','approved','paid','rejected')")
                ->get()
                ->map(fn ($r) => [
                    'status' => $r->status,
                    'total' => (int) $r->total,
                ])
                ->values()
                ->all();
        }

        // ===== Alg counts (metadata only) =====
        $algCounts = [];
        if (Schema::hasColumn('payrolls', 'salary_alg')) {
            $algCounts = (clone $payrollQuery)
                ->selectRaw('salary_alg, COUNT(*) as total')
                ->groupBy('salary_alg')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'salary_alg' => $r->salary_alg,
                    'total' => (int) $r->total,
                ])
                ->values()
                ->all();
        }

        // ===== Trend 6 months =====
        $trend = [];
        if (Schema::hasColumn('payrolls', 'periode')) {
            $endMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $start6 = (clone $endMonth)->subMonths(5)->startOfMonth();

            $trendBase = Payroll::query()
                ->whereBetween('periode', [
                    $start6->toDateString(),
                    $endMonth->endOfMonth()->toDateString(),
                ]);

            if ($role === 'staff' && Schema::hasColumn('payrolls', 'user_id')) {
                $trendBase->where('user_id', $user->id);
            }

            $trendRows = $trendBase
                ->selectRaw("DATE_FORMAT(periode, '%Y-%m') as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $map = [];
            foreach ($trendRows as $r) {
                $map[$r->month] = (int) $r->total;
            }

            $cursor = (clone $start6);
            for ($i = 0; $i < 6; $i++) {
                $key = $cursor->format('Y-m');
                $trend[] = [
                    'month' => $key,
                    'total' => $map[$key] ?? 0,
                ];
                $cursor->addMonth();
            }
        }

        // ===== Recent payrolls =====
        $select = ['id', 'employee_id', 'periode', 'period_to', 'created_at'];
        if (Schema::hasColumn('payrolls', 'status')) $select[] = 'status';

        $recentRows = (clone $payrollQuery)
            ->with(['employee:id,name,employee_code,department,position'])
            ->orderByDesc('id')
            ->limit(8)
            ->get($select);

        $recent = $recentRows->map(function ($p) {
            $periodMonth = optional($p->period_to)->format('Y-m') ?? optional($p->periode)->format('Y-m');
            $recap = \App\Models\MonthlyRecap::where('employee_id', $p->employee_id)
                ->where('period_month', $periodMonth)
                ->first();

            return [
                'id' => $p->id,
                'employee_id' => $p->employee_id,
                'employee_name' => $p->employee?->name,
                'employee_code' => $p->employee?->employee_code,
                'department' => $p->employee?->department,
                'position' => $p->employee?->position,
                'periode' => optional($p->period_to)->toDateString() ?? optional($p->periode)->toDateString(),
                'status' => $p->status ?? null,
                'total_mandays' => $recap ? $recap->total_mandays : 0,
                'created_at' => optional($p->created_at)->toISOString(),
            ];
        })->values();

        return response()->json([
            'month' => $month,
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'kpi' => [
                'employees_active' => (int) $employeeActive,
                'employees_inactive' => (int) $employeeInactive,
                'payroll_count' => (int) $payrollCount,
            ],
            'status_counts' => $statusCounts,
            'alg_counts' => $algCounts,
            'trend' => $trend,
            'recent_payrolls' => $recent,
        ]);
    }

    private function monthRange(string $yyyyMm): array
    {
        $period = PayrollPeriod::forMonth($yyyyMm);
        $start = Carbon::parse($period->start_date);
        $end = Carbon::parse($period->end_date);
        return [$start, $end];
    }

    private function applyPeriodFilter($query, string $month, Carbon $start, Carbon $end): void
    {
        if (Schema::hasColumn('payrolls', 'periode')) {
            $query->whereBetween('periode', [$start->toDateString(), $end->toDateString()]);
            return;
        }

        if (Schema::hasColumn('payrolls', 'period_month')) {
            $query->where('period_month', $month);
            return;
        }
    }
}
