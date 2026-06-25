@extends('layouts.dashboard')

@section('title', 'Edit Trade '.$trade->ticket)
@section('heading', 'Trade #'.$trade->ticket)
@section('subheading', $trade->account?->mt5_login.' · '.$trade->symbol)

@section('content')
    @if (session('status'))
        <div class="alert" style="border-color:rgba(16,185,129,0.3);background:rgba(16,185,129,0.1);color:#a7f3d0">{{ session('status') }}</div>
    @endif

    <p style="margin-bottom:1rem"><a href="{{ route('dashboard.trades') }}">← Back to trades</a></p>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>Edit trade record</h2></div>
            <form method="POST" action="{{ route('dashboard.trades.update', $trade) }}" style="padding:1rem">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="symbol">Symbol</label>
                    <input id="symbol" name="symbol" type="text" required value="{{ old('symbol', $trade->symbol) }}">
                </div>

                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="BUY" @selected(old('type', $trade->type) === 'BUY')>BUY</option>
                        <option value="SELL" @selected(old('type', $trade->type) === 'SELL')>SELL</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lot">Lot</label>
                    <input id="lot" name="lot" type="number" step="any" required value="{{ old('lot', $trade->lot) }}">
                </div>

                <div class="form-group">
                    <label for="entry_price">Entry price</label>
                    <input id="entry_price" name="entry_price" type="number" step="any" value="{{ old('entry_price', $trade->entry_price) }}">
                </div>

                <div class="form-group">
                    <label for="close_price">Close price</label>
                    <input id="close_price" name="close_price" type="number" step="any" value="{{ old('close_price', $trade->close_price) }}">
                </div>

                <div class="form-group">
                    <label for="profit">Profit</label>
                    <input id="profit" name="profit" type="number" step="any" value="{{ old('profit', $trade->profit) }}">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="OPEN" @selected(old('status', $trade->status) === 'OPEN')>OPEN</option>
                        <option value="CLOSED" @selected(old('status', $trade->status) === 'CLOSED')>CLOSED</option>
                    </select>
                </div>

                @if ($errors->any())
                    <div class="error-box">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="btn btn-primary">Save trade</button>
            </form>
        </section>

        @if ($trade->status === 'OPEN')
            <section class="panel">
                <div class="panel-header"><h2>EA actions (queued for MT5)</h2></div>

                <form method="POST" action="{{ route('dashboard.trades.close', $trade) }}" style="padding:1rem;border-bottom:1px solid #1e293b">
                    @csrf
                    <div class="form-group">
                        <label for="close_reason">Close position</label>
                        <input id="close_reason" name="reason" type="text" placeholder="Reason for close">
                    </div>
                    <button type="submit" class="btn" style="background:#be123c">Queue close</button>
                </form>

                <form method="POST" action="{{ route('dashboard.trades.modify-sl', $trade) }}" style="padding:1rem">
                    @csrf
                    <div class="form-group">
                        <label for="new_sl">Move stop loss</label>
                        <input id="new_sl" name="new_sl" type="number" step="any" required placeholder="New SL price">
                    </div>
                    <div class="form-group">
                        <input name="reason" type="text" placeholder="Reason (optional)">
                    </div>
                    <button type="submit" class="btn">Queue SL update</button>
                </form>
            </section>
        @endif
    </div>
@endsection
