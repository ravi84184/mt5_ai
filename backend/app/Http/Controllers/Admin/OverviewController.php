<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\MarketSnapshot;
use App\Models\PositionManagementDecision;
use App\Models\Signal;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();

        return view('admin.overview', [
            'stats' => [
                'accounts' => Account::count(),
                'active_accounts' => Account::where('trading_enabled', true)->count(),
                'open_trades' => Trade::where('status', 'OPEN')->count(),
                'pending_signals' => Signal::where('status', 'PENDING')->whereIn('action', ['BUY', 'SELL'])->count(),
                'pending_management' => PositionManagementDecision::where('status', 'PENDING')->count(),
                'signals_today' => Signal::where('created_at', '>=', $today)->count(),
                'closed_trades_today' => Trade::where('status', 'CLOSED')->where('updated_at', '>=', $today)->count(),
                'queued_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'ai_logs_today' => AiInteractionLog::where('created_at', '>=', $today)->count(),
                'snapshots_today' => MarketSnapshot::where('created_at', '>=', $today)->count(),
            ],
            'latestSnapshot' => MarketSnapshot::with('account')->latest()->first(),
            'recentSignals' => Signal::with('account')->latest()->limit(8)->get(),
            'recentTrades' => Trade::with('account')->latest()->limit(8)->get(),
            'accountsNeedingSetup' => Account::whereNull('symbols')->count(),
        ]);
    }
}
