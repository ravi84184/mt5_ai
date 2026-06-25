@extends('layouts.dashboard')

@section('title', 'Edit Account '.$account->mt5_login)
@section('heading', 'Account '.$account->mt5_login)
@section('subheading', 'AI provider, symbols, and trading controls')

@section('content')
    @if (session('status'))
        <div class="alert" style="border-color:rgba(16,185,129,0.3);background:rgba(16,185,129,0.1);color:#a7f3d0">{{ session('status') }}</div>
    @endif

    <p style="margin-bottom:1rem"><a href="{{ route('dashboard.accounts') }}">← Back to accounts</a></p>

    <section class="panel" style="max-width:40rem">
        <div class="panel-header"><h2>Account settings</h2></div>
        <form method="POST" action="{{ route('dashboard.accounts.update', $account) }}" style="padding:1rem">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="ai_provider">AI provider</label>
                <select id="ai_provider" name="ai_provider">
                    <option value="">Default ({{ $defaultProvider }})</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->value }}" @selected($account->ai_provider === $provider->value)>
                            {{ $provider->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="symbols">Symbols (comma-separated)</label>
                <input id="symbols" name="symbols" type="text"
                       value="{{ old('symbols', implode(', ', $account->configuredSymbols())) }}"
                       placeholder="XAUUSD or XAUUSD, EURUSD, GBPUSD">
                <p class="text-dim" style="font-size:0.75rem;margin-top:0.35rem">
                    Admin controls which symbols are traded. EA fetches this list from the server. Leave empty to disable AI analysis until configured.
                </p>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="trading_enabled" value="1" @checked(old('trading_enabled', $account->trading_enabled))>
                    Trading enabled
                </label>
            </div>

            <div class="form-group">
                <label for="min_confidence">Min confidence (optional override)</label>
                <input id="min_confidence" name="min_confidence" type="number" min="0" max="100"
                       value="{{ old('min_confidence', $account->min_confidence) }}" placeholder="Default {{ config('trading.risk.min_confidence') }}">
            </div>

            <div class="form-group">
                <label for="max_open_trades">Max open trades (optional override)</label>
                <input id="max_open_trades" name="max_open_trades" type="number" min="1" max="50"
                       value="{{ old('max_open_trades', $account->max_open_trades) }}" placeholder="Default {{ config('trading.risk.max_open_trades') }}">
            </div>

            <div class="form-group">
                <label for="admin_notes">Admin notes</label>
                <textarea id="admin_notes" name="admin_notes" rows="3" style="width:100%;border:1px solid #334155;background:#020617;color:#fff;border-radius:0.5rem;padding:0.5rem">{{ old('admin_notes', $account->admin_notes) }}</textarea>
            </div>

            @if ($errors->any())
                <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary">Save settings</button>
                <a href="{{ route('dashboard.signals.create', $account) }}" class="btn">Create manual signal</a>
            </div>
        </form>
    </section>

    <section class="panel" style="margin-top:1.5rem;max-width:40rem">
        <div class="panel-header"><h2>Current effective config</h2></div>
        <div class="dl-row"><dt>AI provider</dt><dd>{{ $account->resolvedAiProvider() }}</dd></div>
        <div class="dl-row"><dt>Symbols</dt><dd>{{ $account->hasSymbolRestrictions() ? implode(', ', $account->configuredSymbols()) : 'Not configured' }}</dd></div>
        <div class="dl-row"><dt>Min confidence</dt><dd>{{ $account->resolvedMinConfidence() }}%</dd></div>
        <div class="dl-row"><dt>Max open trades</dt><dd>{{ $account->resolvedMaxOpenTrades() }}</dd></div>
    </section>
@endsection
