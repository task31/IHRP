<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $q = DB::table('audit_log')->orderByDesc('timestamp')->limit(500);

        if ($request->filled('table')) {
            $q->where('table_name', $request->string('table'));
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('from')) {
            $q->where('timestamp', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('timestamp', '<=', $request->date('to')->endOfDay());
        }

        return response()->json($q->get());
    }
}
