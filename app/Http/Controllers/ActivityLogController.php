<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $logs = ActivityLog::with('user')
            ->whereYear('created_at', $year)
            ->when($request->month, fn ($q, $m) => $q->whereMonth('created_at', (int) $m))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->q, fn ($q, $s) => $q->where('description', 'like', "%$s%"))
            ->latest()->paginate(30)->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'year' => $year,
            'users' => User::orderBy('name')->get(),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
