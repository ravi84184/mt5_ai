<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInteractionLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AiInteractionLog::with('account')->latest();

        if ($type = $request->query('type')) {
            $query->where('analysis_type', $type);
        }

        if ($symbol = $request->query('symbol')) {
            $query->where('symbol', strtoupper($symbol));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.ai-logs.index', compact('logs'));
    }

    public function show(AiInteractionLog $aiLog): View
    {
        $aiLog->load(['account', 'signal']);

        return view('admin.ai-logs.show', compact('aiLog'));
    }
}
