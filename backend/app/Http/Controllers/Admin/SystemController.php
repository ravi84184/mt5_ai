<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Services\TradingSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function __construct(private TradingSettingsService $settings) {}

    public function index(): View
    {
        $values = $this->settings->valuesForForm();

        return view('admin.system.index', [
            'config' => [
                'app_url' => config('app.url'),
                'app_timezone' => config('app.timezone'),
                'queue_connection' => config('queue.default'),
                'ai_provider' => $values['ai_provider'],
                'openai_configured' => $values['openai_configured'],
                'anthropic_configured' => $values['anthropic_configured'],
                'gemini_configured' => $values['gemini_configured'],
                'default_symbols' => $values['symbols'] ?: 'Not set',
                'candle_count' => $values['candle_count'],
                'min_confidence' => $values['min_confidence'],
                'max_open_trades' => $values['max_open_trades'],
                'trading_sessions' => $values['trading_sessions'],
                'max_daily_drawdown_pct' => $values['max_daily_drawdown_pct'],
            ],
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }

    public function settings(): View
    {
        return view('admin.system.settings', [
            'settings' => $this->settings->valuesForForm(),
            'providers' => AiProvider::cases(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->settings->validationRules());

        $this->settings->update($validated);

        return redirect()
            ->route('admin.system.settings')
            ->with('status', 'Trading settings saved. Restart queue workers if they are long-running.');
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
