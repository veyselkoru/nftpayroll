<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'message' => 'Employee portal erişimi aktif.',
        ]);
    }
}
