<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollCalculationRequest;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Services\PayrollCalculationService;
use Illuminate\Http\Request;

class PayrollCalculationController extends Controller
{
    protected $service;

    public function __construct(PayrollCalculationService $service)
    {
        $this->service = $service;
    }

    public function previewCalculation(PayrollCalculationRequest $request)
    {
        $res = $this->service->calculatePreview(
            $request->employee_id,
            $request->period_month,
            $request->payroll_id
        );

        return response()->json($res);
    }

    public function autoCalculate(PayrollCalculationRequest $request)
    {
        $payroll = $this->service->calculateAndSave($request->employee_id, $request->period_month, $request->user()->id);

        return response()->json($payroll, 201);
    }

    public function batchPreview(Request $request)
    {
        if ($request->user()->cannot('batch', Payroll::class)) {
            abort(403);
        }
        $request->validate(['period_month' => 'required|date_format:Y-m']);
        $res = $this->service->batchPreview($request->period_month);

        return response()->json($res);
    }

    public function batchGenerate(Request $request)
    {
        if ($request->user()->cannot('batch', Payroll::class)) {
            abort(403);
        }
        $request->validate(['period_month' => 'required|date_format:Y-m']);
        $res = $this->service->batchGenerate($request->period_month, $request->user()->id);

        return response()->json($res);
    }

    public function recalculate(Request $request, Payroll $payroll)
    {
        if ($request->user()->cannot('recalculate', $payroll)) {
            abort(403);
        }
        $force = $request->boolean('force', false);
        try {
            $result = $this->service->recalculate($payroll, $force, $request->user()->id);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function overrideAllowance(Request $request, Payroll $payroll, PayrollAllowance $allowance)
    {
        if ($request->user()->cannot('override', $payroll)) {
            abort(403);
        }
        $request->validate([
            'amount' => 'required|numeric',
            'override_reason' => 'required|string|max:255',
        ]);
        try {
            $result = $this->service->overrideAllowance($payroll, $allowance, $request->amount, $request->override_reason, $request->user()->id);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
