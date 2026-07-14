<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['staff','fat','director','hcga'], true);
    }

    public function view(User $user, Payroll $payroll): bool
    {
        if (in_array($user->role, ['fat','director','hcga'], true)) return true;

        if ($user->role !== 'staff') return false;

        $payroll->loadMissing('employee:id,user_id');

        return (int)($payroll->employee?->user_id) === (int)$user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'fat';
    }
    public function update(User $user, Payroll $payroll): bool
    {
        return $user->role === 'fat';
    }
    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->role === 'fat';
    }
    
    public function calculate(User $user): bool
    {
        return $user->role === 'fat';
    }
    public function batch(User $user): bool
    {
        return in_array($user->role, ['fat', 'director'], true);
    }
    public function recalculate(User $user, Payroll $payroll): bool
    {
        return $user->role === 'fat';
    }
    public function override(User $user, Payroll $payroll): bool
    {
        return $user->role === 'fat';
    }

        /**
         * Determine whether the user can restore the model.
         */
    public function restore(User $user, Payroll $payroll): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payroll $payroll): bool
    {
        return false;
    }
}
