<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(): View
    {
        return view('admin.system.index', [
            'config' => [
                'app_url' => config('app.url'),
                'app_timezone' => config('app.timezone'),
                'queue_connection' => config('queue.default'),
                'ai_provider' => config('trading.ai.provider'),
                'openai_configured' => (bool) config('trading.ai.openai.api_key'),
                'anthropic_configured' => (bool) config('trading.ai.anthropic.api_key'),
                'gemini_configured' => (bool) config('trading.ai.gemini.api_key'),
                'min_confidence' => config('trading.risk.min_confidence'),
                'max_open_trades' => config('trading.risk.max_open_trades'),
                'trading_sessions' => config('trading.risk.trading_sessions'),
                'max_daily_drawdown_pct' => config('trading.risk.max_daily_drawdown_pct'),
            ],
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }

    public function queue(): View
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get();

        return view('admin.system.queue', [
            'pending' => DB::table('jobs')->count(),
            'failedJobs' => $failedJobs,
        ]);
    }

    public function retryAllFailed(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => 'all']);

        return back()->with('status', 'All failed jobs queued for retry.');
    }

    public function flushFailed(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return back()->with('status', 'Failed jobs cleared.');
    }
}
