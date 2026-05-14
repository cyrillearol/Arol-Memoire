<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $status = $request->query('status');

        $query = Report::query()->with(['reporter', 'reportedUser'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/Reports', [
            'reports' => $query->paginate(12)->through(fn (Report $report) => [
                'id' => $report->id,
                'subject' => $report->subject,
                'description' => $report->description,
                'status' => $report->status,
                'created_at' => $report->created_at?->diffForHumans(),
                'reporter' => $report->reporter ? [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'role' => $report->reporter->role,
                ] : null,
                'reported_user' => $report->reportedUser ? [
                    'id' => $report->reportedUser->id,
                    'name' => $report->reportedUser->name,
                    'role' => $report->reportedUser->role,
                ] : null,
            ]),
            'filters' => ['status' => $status],
        ]);
    }
}