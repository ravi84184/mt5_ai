<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();

        return view('dashboard.index', [
            'stats' => [
                'accounts' => Account::count(),
                'open_trades' => Trade::where('status', 'OPEN')->count(),
                'pending_signals' => Signal::where('status', 'PENDING')->whereIn('action', ['BUY', 'SELL'])->count(),
                'signals_today' => Signal::where('created_at', '>=', $today)->count(),
                'closed_trades_today' => Trade::where('status', 'CLOSED')->where('updated_at', '>=', $today)->count(),
                'queued_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'ai_logs_today' => AiInteractionLog::where('created_at', '>=', $today)->count(),
            ],
            'config' => [
                'ai_provider' => config('trading.ai.provider'),
                'min_confidence' => config('trading.risk.min_confidence'),
                'max_open_trades' => config('trading.risk.max_open_trades'),
                'trading_sessions' => config('trading.risk.trading_sessions'),
                'queue_connection' => config('queue.default'),
            ],
            'latestSnapshot' => MarketSnapshot::with('account')->latest()->first(),
            'recentSignals' => Signal::with('account')->latest()->limit(8)->get(),
            'recentTrades' => Trade::with('account')->latest()->limit(8)->get(),
        ]);
    }

    public function accounts(): View
    {
        $accounts = Account::query()
            ->withCount([
                'signals',
                'trades',
                'trades as open_trades_count' => fn ($q) => $q->where('status', 'OPEN'),
            ])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('dashboard.accounts', compact('accounts'));
    }

    public function signals(Request $request): View
    {
        $query = Signal::with('account')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        if ($action = $request->query('action')) {
            $query->where('action', strtoupper($action));
        }

        if ($symbol = $request->query('symbol')) {
            $query->where('symbol', strtoupper($symbol));
        }

        $signals = $query->paginate(25)->withQueryString();

        return view('dashboard.signals', compact('signals'));
    }

    public function trades(Request $request): View
    {
        $query = Trade::with(['account', 'signal'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        $trades = $query->paginate(25)->withQueryString();

        return view('dashboard.trades', compact('trades'));
    }

    public function aiLogs(Request $request): View
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

        return view('dashboard.ai-logs', compact('logs'));
    }

    public function aiLogShow(AiInteractionLog $aiLog): View
    {
        $aiLog->load(['account', 'signal']);

        return view('dashboard.ai-log-show', compact('aiLog'));
    }
}
