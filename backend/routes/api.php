<?php


use App\Http\Controllers\Api\AllowanceTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeductionTypeController;
use App\Http\Controllers\Api\EmployeeController;

use App\Http\Controllers\Api\PositionAllowanceRateController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollReportController;

use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Kalau mau staff bisa register dari halaman login, aktifkan ini:
// Route::post('/register', [AuthController::class, 'registerStaff']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/hcga', [DashboardController::class, 'hcga']); // ✅ HCGA dashboard (HR/Admin focus)
    Route::get('/reports/payroll', [PayrollReportController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | PAYROLL
    |--------------------------------------------------------------------------
    */
    // Phase 4 Routes (Placed ABOVE resource / dynamic param routes)
    // Phase 4 Routes (Placed ABOVE resource / dynamic param routes)
    Route::post('/payrolls/preview-calculation', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'previewCalculation']);
    Route::post('/payrolls/auto', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'autoCalculate']);
    Route::post('/payrolls/batch-preview', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'batchPreview']);
    Route::post('/payrolls/batch-generate', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'batchGenerate']);

    // Phase 4 Specific Item Routes
    Route::post('/payrolls/{payroll}/recalculate', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'recalculate']);
    Route::patch('/payrolls/{payroll}/allowances/{allowance}', [\App\Http\Controllers\Api\PayrollCalculationController::class, 'overrideAllowance']);

    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::post('/payrolls', [PayrollController::class, 'store']);

    Route::post('/payrolls/{payroll}/submit', [\App\Http\Controllers\Api\PayrollWorkflowController::class, 'submit']);
    Route::post('/payrolls/{payroll}/approve', [\App\Http\Controllers\Api\PayrollWorkflowController::class, 'approve']);

    Route::get('/payrolls/{payroll}', [PayrollController::class, 'show']);
    Route::put('/payrolls/{payroll}', [PayrollController::class, 'update']);
    Route::patch('/payrolls/{payroll}', [PayrollController::class, 'update']);
    Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy']);

    Route::apiResource('/special-deductions', \App\Http\Controllers\Api\SpecialDeductionController::class)
        ->only(['index', 'store', 'destroy']);

    Route::apiResource('/master/deduction-types', DeductionTypeController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Legacy workflow actions kept only where they do not conflict with the current payroll flow.
    Route::post('/payrolls/{payroll}/reject', [\App\Http\Controllers\Api\PayrollWorkflowController::class, 'reject']);
    Route::post('/payrolls/{payroll}/mark-paid', [PayrollController::class, 'markPaid']);

    // export
    Route::get('/payrolls/{payroll}/pdf', [PayrollController::class, 'pdf']);

    // Security Inspection
    Route::get('/payrolls/{payroll}/inspection', [PayrollController::class, 'inspection']);
    Route::get('/payrolls/{payroll}/inspection-pdf', [PayrollController::class, 'inspectionPdf']);

    Route::get('/payrolls/{payroll}/proof', [PayrollController::class, 'proof']);

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEES
    |--------------------------------------------------------------------------
    | Penting: taruh route static dulu (next-code) sebelum {employee}
    */
    Route::get('/employees/next-code', [EmployeeController::class, 'nextCode']);
    Route::post('/employees/{employee}/create-user', [EmployeeController::class, 'createUser']);
    Route::post('/employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);

    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
    Route::patch('/employees/{employee}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

    // salary profile & job histories

    Route::get('/employees/{employee}/salary-profile', [EmployeeController::class, 'salaryProfile']);
    Route::get('/employees/{employee}/salary-profiles', [EmployeeController::class, 'salaryProfilesList']);
    Route::post('/employees/{employee}/salary-profiles', [EmployeeController::class, 'storeSalaryProfile']);
    Route::get('/employees/{employee}/job-histories', [EmployeeController::class, 'jobHistories']);
    Route::post('/employees/{employee}/mutate', [\App\Http\Controllers\Api\MutationController::class, 'store']);

    // Mutation Requests (Approvals)
    Route::get('/mutation-requests', [\App\Http\Controllers\Api\MutationRequestController::class, 'index']);
    Route::post('/mutation-requests', [\App\Http\Controllers\Api\MutationRequestController::class, 'store']);
    Route::get('/mutation-requests/{id}', [\App\Http\Controllers\Api\MutationRequestController::class, 'show']);
    Route::put('/mutation-requests/{id}', [\App\Http\Controllers\Api\MutationRequestController::class, 'update']);
    Route::post('/mutation-requests/{id}/cancel', [\App\Http\Controllers\Api\MutationRequestController::class, 'cancel']);
    Route::post('/mutation-requests/{id}/approve', [\App\Http\Controllers\Api\MutationRequestController::class, 'approve']);
    Route::post('/mutation-requests/{id}/reject', [\App\Http\Controllers\Api\MutationRequestController::class, 'reject']);


    /*
    |--------------------------------------------------------------------------
    | ME (Profil user login)
    |--------------------------------------------------------------------------
    */
    Route::get('/me', [MeController::class, 'me']);
    Route::put('/me', [MeController::class, 'updateMe']);
    Route::put('/me/password', [MeController::class, 'updatePassword']);

    Route::get('/me/employee', [MeController::class, 'employee']);
    Route::put('/me/employee', [MeController::class, 'updateEmployee']);

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA (Phase 1)
    |--------------------------------------------------------------------------
    */
    Route::prefix('master')->group(function () {
        Route::apiResource('positions', PositionController::class);
        Route::apiResource('allowance-types', AllowanceTypeController::class);
        Route::apiResource('position-allowance-rates', PositionAllowanceRateController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | PHASE 3: ATTENDANCE, SCHEDULE, PROJECT ASSIGNMENT, MANDAYS SUMMARY
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | PHASE 3: MONTHLY RECAP (Replaced ERP Modules)
    |--------------------------------------------------------------------------
    */
    Route::get('/monthly-recaps', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'index']);
    Route::post('/monthly-recaps', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'store']);
    Route::post('/monthly-recaps/submit-to-finance', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'submitToFinance']);
    Route::post('/monthly-recaps/{recap}/finalize', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'finalize']);
    Route::delete('/monthly-recaps/{recap}', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'destroy']);
    Route::get('/payroll-periods', [\App\Http\Controllers\Api\PayrollPeriodController::class, 'index']);

});

// Hidden route for benchmark
Route::get('/benchmark', [\App\Http\Controllers\Api\BenchmarkController::class, 'index']);
