<?php

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EconomicCalendarService
{
    /**
     * @var array<string, list<string>>
     */
    private const SYMBOL_CURRENCIES = [
        'XAUUSD' => ['USD'],
        'PAXGUSDT' => ['USD'],
        'EURUSD' => ['EUR', 'USD'],
        'GBPUSD' => ['GBP', 'USD'],
        'USDJPY' => ['USD', 'JPY'],
        'AUDUSD' => ['AUD', 'USD'],
        'USDCAD' => ['USD', 'CAD'],
        'BTCUSDT' => ['USD'],
        'ETHUSDT' => ['USD'],
    ];

    public function getBlockReasonForSymbol(string $symbol): ?string
    {
        $symbol = strtoupper($symbol);
        $currencies = self::SYMBOL_CURRENCIES[$symbol] ?? ['USD'];

        $events = $this->upcomingHighImpactEvents();
        $now = Carbon::now('UTC');
        $blockBefore = (int) config('trading.news.block_minutes_before', 30);
        $blockAfter = (int) config('trading.news.block_minutes_after', 15);

        foreach ($events as $event) {
            $country = strtoupper((string) ($event['country'] ?? ''));
            if (! in_array($country, $currencies, true)) {
                continue;
            }

            $eventTime = $this->parseEventTime($event['date'] ?? null);
            if (! $eventTime) {
                continue;
            }

            $windowStart = $eventTime->copy()->subMinutes($blockBefore);
            $windowEnd = $eventTime->copy()->addMinutes($blockAfter);

            if ($now->between($windowStart, $windowEnd)) {
                $title = (string) ($event['title'] ?? 'High-impact event');

                return "News blackout: {$title} ({$country}) at {$eventTime->format('H:i')} UTC";
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContextForSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $currencies = self::SYMBOL_CURRENCIES[$symbol] ?? ['USD'];
        $events = $this->upcomingHighImpactEvents();
        $now = Carbon::now('UTC');
        $lookaheadHours = (int) config('trading.news.lookahead_hours', 8);

        $relevant = [];
        foreach ($events as $event) {
            $country = strtoupper((string) ($event['country'] ?? ''));
            if (! in_array($country, $currencies, true)) {
                continue;
            }

            $eventTime = $this->parseEventTime($event['date'] ?? null);
            if (! $eventTime || $eventTime->lt($now) || $eventTime->gt($now->copy()->addHours($lookaheadHours))) {
                continue;
            }

            $relevant[] = [
                'title' => $event['title'] ?? 'Event',
                'country' => $country,
                'time_utc' => $eventTime->toDateTimeString(),
                'minutes_until' => (int) $now->diffInMinutes($eventTime, false),
            ];
        }

        return [
            'enabled' => config('trading.news.enabled', true),
            'upcoming_high_impact' => array_slice($relevant, 0, 5),
            'in_blackout' => $this->getBlockReasonForSymbol($symbol) !== null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingHighImpactEvents(): array
    {
        $cacheMinutes = (int) config('trading.news.cache_minutes', 60);

        return Cache::remember('economic_calendar_high_impact', now()->addMinutes($cacheMinutes), function () {
            return $this->fetchHighImpactEvents();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchHighImpactEvents(): array
    {
        $url = config('trading.news.calendar_url');

        try {
            $response = Http::timeout(15)->get($url);
            if (! $response->successful()) {
                Log::warning('Economic calendar fetch failed', ['status' => $response->status()]);

                return [];
            }

            $events = $response->json();
            if (! is_array($events)) {
                return [];
            }

            return array_values(array_filter($events, function ($event) {
                if (! is_array($event)) {
                    return false;
                }

                $impact = strtolower((string) ($event['impact'] ?? ''));

                return in_array($impact, ['high', 'holiday'], true);
            }));
        } catch (\Throwable $e) {
            Log::warning('Economic calendar error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function parseEventTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
