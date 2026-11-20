<?php

namespace App\Http\Controllers;

use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkflowController extends Controller
{
    public function index(Request $request, WorkflowService $workflow)
    {
        $p = $request->period ? Carbon::createFromFormat('Y-m-d', $request->period.'-01') : working_period();

        return view('workflow.index', [
            'w' => $workflow->status($p->year, $p->month),
            'recent' => $workflow->recent(12),
            'workingDays' => setting('working_days'),
        ]);
    }
}
