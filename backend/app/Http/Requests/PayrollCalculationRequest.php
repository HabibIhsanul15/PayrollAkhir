<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollCalculationRequest extends FormRequest
{
    public function authorize()
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($this->is('api/payrolls/preview-calculation')) {
            return in_array($user->role, ['fat', 'director'], true);
        }

        return $user->can('calculate', \App\Models\Payroll::class);
    }

    public function rules()
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|date_format:Y-m',
            'payroll_id' => 'nullable|integer|exists:payrolls,id',
        ];
    }
}
