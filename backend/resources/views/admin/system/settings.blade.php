@extends('layouts.admin')

@section('title', 'Trading Settings')
@section('heading', 'Trading settings')
@section('subheading', 'AI providers, default symbols, risk limits, and trading sessions')

@section('content')
    <p style="margin-bottom:1rem">
        <a href="{{ route('admin.system.index') }}">← System overview</a>
    </p>

    <form method="POST" action="{{ route('admin.system.settings.update') }}">
        @csrf
        @method('PUT')

        <section class="panel" style="margin-bottom:1.5rem">
            <div class="panel-header"><h2>Trading defaults</h2></div>
            <div style="padding:1rem;max-width:40rem">
                <p class="text-muted" style="margin:0 0 1rem;font-size:0.875rem">
                    Per-account symbols override these in Accounts. These defaults apply when an account has no symbol list.
                </p>
                <div class="form-group">
                    <label for="symbols">Default symbols (comma-separated)</label>
                    <input id="symbols" name="symbols" type="text"
                           value="{{ old('symbols', $settings['symbols']) }}"
                           placeholder="XAUUSD, EURUSD">
                </div>
                <div class="form-group">
                    <label for="candle_count">Candle count sent to AI</label>
                    <input id="candle_count" name="candle_count" type="number" min="10" max="500" required
                           value="{{ old('candle_count', $settings['candle_count']) }}">
                </div>
            </div>
        </section>

        <section class="panel" style="margin-bottom:1.5rem">
            <div class="panel-header"><h2>AI providers</h2></div>
            <div style="padding:1rem;max-width:40rem">
                <div class="form-group">
                    <label for="ai_provider">Default AI provider</label>
                    <select id="ai_provider" name="ai_provider" required>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->value }}" @selected(old('ai_provider', $settings['ai_provider']) === $provider->value)>
                                {{ $provider->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-muted" style="margin:0.25rem 0 0;font-size:0.875rem">
                        Accounts can override this in Accounts → Edit. Configure the matching API key below.
                    </p>
                </div>

                <h3 style="font-size:1rem;margin:1.5rem 0 0.75rem">OpenAI</h3>
                <div class="form-group">
                    <label for="openai_api_key">API key</label>
                    <input id="openai_api_key" name="openai_api_key" type="password" autocomplete="off"
                           placeholder="{{ $settings['openai_configured'] ? 'Configured — leave blank to keep' : 'sk-...' }}">
                    @if ($settings['openai_configured'])
                        <label style="display:block;margin-top:0.5rem;font-size:0.875rem">
                            <input type="checkbox" name="clear_openai_api_key" value="1"> Remove stored key
                        </label>
                    @endif
                </div>
                <div class="form-group">
                    <label for="openai_model">Model</label>
                    <input id="openai_model" name="openai_model" type="text" required list="openai-models"
                           value="{{ old('openai_model', $settings['openai_model']) }}">
                    <datalist id="openai-models">
                        @foreach ($modelSuggestions['openai'] as $model)
                            <option value="{{ $model }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <h3 style="font-size:1rem;margin:1.5rem 0 0.75rem">Anthropic</h3>
                <div class="form-group">
                    <label for="anthropic_api_key">API key</label>
                    <input id="anthropic_api_key" name="anthropic_api_key" type="password" autocomplete="off"
                           placeholder="{{ $settings['anthropic_configured'] ? 'Configured — leave blank to keep' : 'sk-ant-...' }}">
                    @if ($settings['anthropic_configured'])
                        <label style="display:block;margin-top:0.5rem;font-size:0.875rem">
                            <input type="checkbox" name="clear_anthropic_api_key" value="1"> Remove stored key
                        </label>
                    @endif
                </div>
                <div class="form-group">
                    <label for="anthropic_model">Model</label>
                    <input id="anthropic_model" name="anthropic_model" type="text" required list="anthropic-models"
                           value="{{ old('anthropic_model', $settings['anthropic_model']) }}">
                    <datalist id="anthropic-models">
                        @foreach ($modelSuggestions['anthropic'] as $model)
                            <option value="{{ $model }}"></option>
                        @endforeach
                    </datalist>
                    <p class="text-muted" style="margin:0.25rem 0 0;font-size:0.875rem">
                        Recommended: <code>claude-sonnet-4-6</code>. Old IDs like <code>claude-sonnet-4-20250514</code> are invalid.
                    </p>
                </div>

                <h3 style="font-size:1rem;margin:1.5rem 0 0.75rem">Google Gemini</h3>
                <div class="form-group">
                    <label for="gemini_api_key">API key</label>
                    <input id="gemini_api_key" name="gemini_api_key" type="password" autocomplete="off"
                           placeholder="{{ $settings['gemini_configured'] ? 'Configured — leave blank to keep' : 'AIza...' }}">
                    @if ($settings['gemini_configured'])
                        <label style="display:block;margin-top:0.5rem;font-size:0.875rem">
                            <input type="checkbox" name="clear_gemini_api_key" value="1"> Remove stored key
                        </label>
                    @endif
                </div>
                <div class="form-group">
                    <label for="gemini_model">Model</label>
                    <input id="gemini_model" name="gemini_model" type="text" required list="gemini-models"
                           value="{{ old('gemini_model', $settings['gemini_model']) }}">
                    <datalist id="gemini-models">
                        @foreach ($modelSuggestions['gemini'] as $model)
                            <option value="{{ $model }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>
        </section>

        <section class="panel" style="margin-bottom:1.5rem">
            <div class="panel-header"><h2>Risk management</h2></div>
            <div style="padding:1rem;max-width:40rem">
                <p class="text-muted" style="margin:0 0 1rem;font-size:0.875rem">
                    Accounts can override min confidence and max open trades. These are global defaults.
                </p>
                <div class="form-group">
                    <label for="max_risk_per_trade_pct">Max risk per trade (%)</label>
                    <input id="max_risk_per_trade_pct" name="max_risk_per_trade_pct" type="number" step="0.1" min="0.1" max="100" required
                           value="{{ old('max_risk_per_trade_pct', $settings['max_risk_per_trade_pct']) }}">
                </div>
                <div class="form-group">
                    <label for="min_confidence">Min confidence (%)</label>
                    <input id="min_confidence" name="min_confidence" type="number" min="0" max="100" required
                           value="{{ old('min_confidence', $settings['min_confidence']) }}">
                </div>
                <div class="form-group">
                    <label for="max_open_trades">Max open trades</label>
                    <input id="max_open_trades" name="max_open_trades" type="number" min="1" max="50" required
                           value="{{ old('max_open_trades', $settings['max_open_trades']) }}">
                </div>
                <div class="form-group">
                    <label for="max_daily_drawdown_pct">Max daily drawdown (%)</label>
                    <input id="max_daily_drawdown_pct" name="max_daily_drawdown_pct" type="number" step="0.1" min="0" max="100" required
                           value="{{ old('max_daily_drawdown_pct', $settings['max_daily_drawdown_pct']) }}">
                </div>
                <div class="form-group">
                    <label for="max_daily_loss">Max daily loss (optional, account currency)</label>
                    <input id="max_daily_loss" name="max_daily_loss" type="number" step="0.01" min="0"
                           value="{{ old('max_daily_loss', $settings['max_daily_loss']) }}">
                </div>
                <div class="form-group">
                    <label for="max_daily_profit">Max daily profit cap (optional)</label>
                    <input id="max_daily_profit" name="max_daily_profit" type="number" step="0.01" min="0"
                           value="{{ old('max_daily_profit', $settings['max_daily_profit']) }}">
                </div>
                <div class="form-group">
                    <label for="trading_sessions">Trading sessions (server time)</label>
                    <input id="trading_sessions" name="trading_sessions" type="text" required
                           value="{{ old('trading_sessions', $settings['trading_sessions']) }}"
                           placeholder="00:00-23:59 or 08:00-12:00,14:00-18:00">
                </div>
            </div>
        </section>

        @include('admin.system.settings-pipeline', ['settings' => $settings])

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
@endsection
