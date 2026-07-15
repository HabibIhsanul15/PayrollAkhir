<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;

class PayrollPeriodController extends Controller
{
    public function index()
    {
        PayrollPeriod::ensureUpcoming(12);
        $periods = PayrollPeriod::orderBy('start_date', 'desc')->get();
        return response()->json($periods);
    }
}
