@extends('layouts.admin')

@section('title', 'Trade '.$trade->ticket)
@section('heading', 'Trade #'.$trade->ticket)
@section('subheading', $trade->account?->mt5_login.' · '.$trade->symbol)

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.trades.index') }}">← Trades</a></p>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>Edit trade record</h2></div>
            <form method="POST" action="{{ route('admin.trades.update', $trade) }}" style="padding:1rem">
                @csrf @method('PUT')
                <div class="form-group"><label>Symbol</label><input name="symbol" required value="{{ old('symbol', $trade->symbol) }}"></div>
                <div class="form-group"><label>Type</label><select name="type"><option value="BUY" @selected($trade->type==='BUY')>BUY</option><option value="SELL" @selected($trade->type==='SELL')>SELL</option></select></div>
                <div class="form-group"><label>Lot</label><input name="lot" type="number" step="any" required value="{{ old('lot', $trade->lot) }}"></div>
                <div class="form-group"><label>Entry</label><input name="entry_price" type="number" step="any" value="{{ old('entry_price', $trade->entry_price) }}"></div>
                <div class="form-group"><label>Close</label><input name="close_price" type="number" step="any" value="{{ old('close_price', $trade->close_price) }}"></div>
                <div class="form-group"><label>Profit</label><input name="profit" type="number" step="any" value="{{ old('profit', $trade->profit) }}"></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="OPEN" @selected($trade->status==='OPEN')>OPEN</option><option value="CLOSED" @selected($trade->status==='CLOSED')>CLOSED</option></select></div>
                <button type="submit" class="btn btn-primary">Save trade</button>
            </form>
        </section>

        @if ($trade->status === 'OPEN')
            <section class="panel">
                <div class="panel-header"><h2>Queue EA action</h2></div>
                <form method="POST" action="{{ route('admin.trades.close', $trade) }}" style="padding:1rem;border-bottom:1px solid #1e293b">
                    @csrf
                    <div class="form-group"><label>Close position</label><input name="reason" placeholder="Reason"></div>
                    <button type="submit" class="btn" style="background:#be123c">Queue close</button>
                </form>
                <form method="POST" action="{{ route('admin.trades.modify-sl', $trade) }}" style="padding:1rem">
                    @csrf
                    <div class="form-group"><label>New stop loss</label><input name="new_sl" type="number" step="any" required></div>
                    <div class="form-group"><input name="reason" placeholder="Reason (optional)"></div>
                    <button type="submit" class="btn">Queue SL update</button>
                </form>
            </section>
        @endif
    </div>
@endsection
