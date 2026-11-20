<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->q, fn ($q, $s) => $q->where('description', 'like', "%$s%"))
            ->latest()->paginate(30)->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
