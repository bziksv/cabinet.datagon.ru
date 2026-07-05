<?php

namespace App\Http\Controllers;

use App\Monitoring\Positions;
use App\MonitoringProject;
use Illuminate\Http\Request;

class MonitoringSetPositions extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index()
    {
        $projects = (new MonitoringProject())->all();

        return view('monitoring.admin.set_positions.index', [
            'projects' => $projects,
            'projectCount' => $projects->count(),
        ]);
    }

    public function insertPositions(Request $request)
    {
        $request->validate([
            'projectId' => 'required|integer',
            'engineId' => 'required|integer',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        (new Positions\Fill(
            (int) $request->input('projectId'),
            (int) $request->input('engineId'),
            $request->input('startDate'),
            $request->input('endDate')
        ))->execute();

        return response()->json([
            'status' => true,
            'message' => __('Monitoring set pos run done'),
        ]);
    }
}
