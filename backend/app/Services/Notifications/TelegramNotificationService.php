<?php

namespace App\Services\Notifications;

use App\Models\Account;
use App\Models\BacktestRun;
use App\Models\Signal;
use App\Models\Trade;
use App\Services\Analytics\TradingAnalyticsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    public function isEnabled(): bool
    {
        return (bool) config('trading.telegram.enabled', false)
            && config('trading.telegram.bot_token')
            && config('trading.telegram.chat_id');
    }

    public function send(string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::timeout(15)->post(
                'https://api.telegram.org/bot'.config('trading.telegram.bot_token').'/sendMessage',
                [
                    'chat_id' => config('trading.telegram.chat_id'),
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            );

            if (! $response->successful()) {
                Log::warning('Telegram send failed', ['body' => $response->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function notifySignalReady(Signal $signal, Account $account): void
    {
        if (! config('trading.telegram.notify.signals', true)) {
            return;
        }

        $this->send(sprintf(
            "🟢 <b>Signal ready</b>\nAccount: %s\n%s %s @ %s\nConfidence: %d%%\nSL: %s | TP: %s\n<i>%s</i>",
            $account->mt5_login,
            $signal->action,
            $signal->symbol,
            $signal->entry_price,
            $signal->confidence,
            $signal->stop_loss,
            $signal->take_profit,
            htmlspecialchars((string) $signal->reason, ENT_QUOTES)
        ));
    }

    public function notifySignalRejected(Signal $signal, Account $account): void
    {
        if (! config('trading.telegram.notify.rejections', false)) {
            return;
        }

        $this->send(sprintf(
            "🟡 <b>Signal rejected</b>\nAccount: %s\n%s %s\nReason: %s",
            $account->mt5_login,
            $signal->action,
            $signal->symbol,
            htmlspecialchars((string) ($signal->rejection_reason ?? $signal->reason), ENT_QUOTES)
        ));
    }

    public function notifyTradeOpened(Trade $trade, Account $account): void
    {
        if (! config('trading.telegram.notify.trades', true)) {
            return;
        }

        $this->send(sprintf(
            "📈 <b>Trade opened</b>\nAccount: %s\nTicket: %s\n%s %s @ %s",
            $account->mt5_login,
            $trade->ticket,
            $trade->type,
            $trade->symbol,
            $trade->entry_price
        ));
    }

    public function notifyTradeClosed(Trade $trade, Account $account): void
    {
        if (! config('trading.telegram.notify.trades', true)) {
            return;
        }

        $profit = (float) $trade->profit;
        $emoji = $profit >= 0 ? '✅' : '❌';

        $this->send(sprintf(
            "%s <b>Trade closed</b>\nAccount: %s\nTicket: %s\n%s %s\nProfit: <b>%s</b>",
            $emoji,
            $account->mt5_login,
            $trade->ticket,
            $trade->type,
            $trade->symbol,
            number_format($profit, 2)
        ));
    }

    public function notifyBacktestComplete(BacktestRun $run): void
    {
        if (! config('trading.telegram.notify.backtests', true)) {
            return;
        }

        $results = $run->results_json ?? [];

        $this->send(sprintf(
            "📊 <b>Backtest complete</b>\n%s | %s → %s\nTrades: %d | Win rate: %s%%\nTotal R: %s",
            $run->symbol,
            $run->from_date?->format('Y-m-d'),
            $run->to_date?->format('Y-m-d'),
            $results['total_trades'] ?? 0,
            $results['win_rate'] ?? 0,
            $results['total_r'] ?? 0
        ));
    }

    public function sendDailySummary(?int $accountId = null): void
    {
        if (! config('trading.telegram.notify.daily_summary', true)) {
            return;
        }

        $overview = app(TradingAnalyticsService::class)->overview(1, $accountId);

        $this->send(sprintf(
            "📋 <b>Daily summary</b>\nSignals: %d (pending %d, rejected %d)\nTrades closed: %d\nP&amp;L: <b>%s</b>\nWin rate (30d): %s%%",
            $overview['signals_total'],
            $overview['signals_pending'],
            $overview['signals_rejected'],
            $overview['trades_closed'],
            number_format($overview['total_profit'], 2),
            $overview['win_rate'] ?? 'n/a'
        ));
    }
}
