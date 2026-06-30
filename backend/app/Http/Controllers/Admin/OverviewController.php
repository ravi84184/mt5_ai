<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\MarketSnapshot;
use App\Models\PositionManagementDecision;
use App\Models\Signal;
use App\Models\Trade;
use App\Services\Analytics\TradingAnalyticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __construct(private TradingAnalyticsService $analytics) {}

    public function index(): View
    {
        $today = now()->startOfDay();
        $period = $this->analytics->overview(30);

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
                'win_rate_30d' => $period['win_rate'],
                'total_profit_30d' => $period['total_profit'],
            ],
            'signalBreakdown' => $this->analytics->signalBreakdown(30),
            'rejectionBreakdown' => $this->analytics->rejectionBreakdown(30),
            'dailyPnl' => $this->analytics->dailyPnl(30),
            'symbolPerformance' => $this->analytics->symbolPerformance(30),
            'latestSnapshot' => MarketSnapshot::with('account')->latest()->first(),
            'recentSignals' => Signal::with('account')->latest()->limit(8)->get(),
            'recentTrades' => Trade::with('account')->latest()->limit(8)->get(),
            'accountsNeedingSetup' => Account::whereNull('symbols')->count(),
        ]);
    }
}
