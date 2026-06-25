@extends('layouts.admin')

@section('title', 'Edit Account')
@section('heading', 'Edit account '.$account->mt5_login)
@section('subheading', 'AI provider, symbols, risk overrides')

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.accounts.show', $account) }}">← Back to account</a></p>

    <section class="panel" style="max-width:40rem">
        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" style="padding:1rem">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="ai_provider">AI provider</label>
                <select id="ai_provider" name="ai_provider">
                    <option value="">Default ({{ $defaultProvider }})</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->value }}" @selected($account->ai_provider === $provider->value)>{{ $provider->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="symbols">Symbols (comma-separated)</label>
                <input id="symbols" name="symbols" type="text"
                       value="{{ old('symbols', implode(', ', $account->configuredSymbols())) }}"
                       placeholder="XAUUSD or XAUUSD, EURUSD">
            </div>

            <div class="form-group">
                <label><input type="checkbox" name="trading_enabled" value="1" @checked(old('trading_enabled', $account->trading_enabled))> Trading enabled</label>
            </div>

            <div class="form-group">
                <label for="min_confidence">Min confidence override</label>
                <input id="min_confidence" name="min_confidence" type="number" min="0" max="100"
                       value="{{ old('min_confidence', $account->min_confidence) }}">
            </div>

            <div class="form-group">
                <label for="max_open_trades">Max open trades override</label>
                <input id="max_open_trades" name="max_open_trades" type="number" min="1" max="50"
                       value="{{ old('max_open_trades', $account->max_open_trades) }}">
            </div>

            <div class="form-group">
                <label for="admin_notes">Admin notes</label>
                <textarea id="admin_notes" name="admin_notes" rows="3" style="width:100%;border:1px solid #334155;background:#020617;color:#fff;border-radius:0.5rem;padding:0.5rem">{{ old('admin_notes', $account->admin_notes) }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save settings</button>
        </form>
    </section>
@endsection
