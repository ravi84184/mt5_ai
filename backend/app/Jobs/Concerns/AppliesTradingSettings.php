<?php

namespace App\Jobs\Concerns;

use App\Services\TradingSettingsService;

trait AppliesTradingSettings
{
    protected function applyTradingSettings(): void
    {
        app(TradingSettingsService::class)->applyToConfig();
    }
}
