<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class Phase3Controller extends Controller
{
    protected function canWrite($user)
    {
        return in_array($user->role, ['hcga']);
    }

    protected function canRead($user)
    {
        return in_array($user->role, ['hcga', 'fat', 'director', 'staff', 'employee']);
    }

    protected function auditLog(Request $request, $action, $meta = [])
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta'       => $meta,
        ]);
    }
}
