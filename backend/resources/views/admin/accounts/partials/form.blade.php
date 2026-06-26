@if ($account === null)
    <div class="form-group">
        <label for="mt5_login">MT5 login</label>
        <input id="mt5_login" name="mt5_login" type="number" required
               value="{{ old('mt5_login') }}" placeholder="104392039">
    </div>
@endif

<div class="form-group">
    <label for="broker">Broker (optional)</label>
    <input id="broker" name="broker" type="text"
           value="{{ old('broker', $account?->broker) }}" placeholder="e.g. MetaQuotes-Demo">
</div>

<div class="form-group">
    <label for="ai_provider">AI provider</label>
    <select id="ai_provider" name="ai_provider">
        <option value="">Default ({{ $defaultProvider }})</option>
        @foreach ($providers as $provider)
            <option value="{{ $provider->value }}" @selected(old('ai_provider', $account?->ai_provider) === $provider->value)>{{ $provider->label() }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="symbols">Symbols (comma-separated)</label>
    <input id="symbols" name="symbols" type="text"
           value="{{ old('symbols', $account ? implode(', ', $account->configuredSymbols()) : '') }}"
           placeholder="XAUUSD or XAUUSD, EURUSD">
</div>

<div class="form-group">
    <label><input type="checkbox" name="trading_enabled" value="1" @checked(old('trading_enabled', $account?->trading_enabled ?? false))> Trading enabled</label>
</div>

<div class="form-group">
    <label for="min_confidence">Min confidence override</label>
    <input id="min_confidence" name="min_confidence" type="number" min="0" max="100"
           value="{{ old('min_confidence', $account?->min_confidence) }}">
</div>

<div class="form-group">
    <label for="max_open_trades">Max open trades override</label>
    <input id="max_open_trades" name="max_open_trades" type="number" min="1" max="50"
           value="{{ old('max_open_trades', $account?->max_open_trades) }}">
</div>

<div class="form-group">
    <label for="admin_notes">Admin notes</label>
    <textarea id="admin_notes" name="admin_notes" rows="3" style="width:100%;border:1px solid #334155;background:#020617;color:#fff;border-radius:0.5rem;padding:0.5rem">{{ old('admin_notes', $account?->admin_notes) }}</textarea>
</div>
