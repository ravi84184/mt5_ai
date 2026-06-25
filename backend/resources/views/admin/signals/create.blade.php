@extends('layouts.admin')

@section('title', 'Create Signal')
@section('heading', 'Create manual signal')
@section('subheading', 'Account '.$account->mt5_login)

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.accounts.show', $account) }}">← Back to account</a></p>
    <section class="panel" style="max-width:36rem">
        <form method="POST" action="{{ route('admin.signals.store', $account) }}" style="padding:1rem">
            @csrf
            <div class="form-group"><label>Symbol</label><input name="symbol" required value="{{ old('symbol', $account->configuredSymbols()[0] ?? '') }}"></div>
            <div class="form-group"><label>Action</label><select name="action"><option value="BUY">BUY</option><option value="SELL">SELL</option></select></div>
            <div class="form-group"><label>Confidence</label><input name="confidence" type="number" min="0" max="100" required value="{{ old('confidence', 90) }}"></div>
            <div class="form-group"><label>Entry price</label><input name="entry_price" type="number" step="any"></div>
            <div class="form-group"><label>Stop loss</label><input name="stop_loss" type="number" step="any"></div>
            <div class="form-group"><label>Take profit</label><input name="take_profit" type="number" step="any"></div>
            <div class="form-group"><label>Reason</label><input name="reason" value="{{ old('reason', 'Manual signal by admin') }}"></div>
            <button type="submit" class="btn btn-primary">Create signal</button>
        </form>
    </section>
@endsection
