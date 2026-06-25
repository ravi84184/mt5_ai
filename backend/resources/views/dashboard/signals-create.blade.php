@extends('layouts.dashboard')

@section('title', 'Create Signal')
@section('heading', 'Create manual signal')
@section('subheading', 'Account '.$account->mt5_login.' — EA will execute on next poll')

@section('content')
    <p style="margin-bottom:1rem">
        <a href="{{ route('dashboard.accounts.edit', $account) }}">← Back to account settings</a>
    </p>

    <section class="panel" style="max-width:36rem">
        <form method="POST" action="{{ route('dashboard.signals.store', $account) }}" style="padding:1rem">
            @csrf

            <div class="form-group">
                <label for="symbol">Symbol</label>
                <input id="symbol" name="symbol" type="text" required
                       value="{{ old('symbol', $account->configuredSymbols()[0] ?? '') }}"
                       placeholder="XAUUSD">
            </div>

            <div class="form-group">
                <label for="action">Action</label>
                <select id="action" name="action" required>
                    <option value="BUY" @selected(old('action') === 'BUY')>BUY</option>
                    <option value="SELL" @selected(old('action') === 'SELL')>SELL</option>
                </select>
            </div>

            <div class="form-group">
                <label for="confidence">Confidence</label>
                <input id="confidence" name="confidence" type="number" min="0" max="100" required value="{{ old('confidence', 90) }}">
            </div>

            <div class="form-group">
                <label for="entry_price">Entry price</label>
                <input id="entry_price" name="entry_price" type="number" step="any" value="{{ old('entry_price') }}">
            </div>

            <div class="form-group">
                <label for="stop_loss">Stop loss</label>
                <input id="stop_loss" name="stop_loss" type="number" step="any" value="{{ old('stop_loss') }}">
            </div>

            <div class="form-group">
                <label for="take_profit">Take profit</label>
                <input id="take_profit" name="take_profit" type="number" step="any" value="{{ old('take_profit') }}">
            </div>

            <div class="form-group">
                <label for="reason">Reason</label>
                <input id="reason" name="reason" type="text" value="{{ old('reason', 'Manual signal created by admin') }}">
            </div>

            @if ($errors->any())
                <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <button type="submit" class="btn btn-primary">Create signal</button>
        </form>
    </section>
@endsection
